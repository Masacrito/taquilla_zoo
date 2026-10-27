#!/usr/bin/env bash
# =============================================================
#  Taquilla ZooMAT — redeploy en el VPS
#  Uso:  ./scripts/redeploy.sh            → despliega si hay commits nuevos
#        ./scripts/redeploy.sh --force    → reconstruye aunque no haya cambios
#        ./scripts/redeploy.sh --no-backup
#  Las migraciones las corre el servicio `app` al arrancar (RUN_MIGRATIONS).
# =============================================================
set -euo pipefail

BRANCH="${BRANCH:-master}"
FORCE=0; BACKUP=1
for arg in "$@"; do
  case "$arg" in
    --force)     FORCE=1 ;;
    --no-backup) BACKUP=0 ;;
    -h|--help)   sed -n '2,8p' "$0"; exit 0 ;;
    *) echo "Opción desconocida: $arg" >&2; exit 1 ;;
  esac
done

cd "$(dirname "$0")/.."
ROOT="$(pwd)"
LOG_DIR="$ROOT/storage/deploy-logs"; mkdir -p "$LOG_DIR"
LOG="$LOG_DIR/deploy_$(date +%F_%H-%M-%S).log"
exec > >(tee -a "$LOG") 2>&1

log()  { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
fail() { printf '\n\033[1;31mERROR: %s\033[0m\n' "$*"; exit 1; }
dc()   { docker compose --env-file .env.docker "$@"; }

# --- Un solo deploy a la vez ---
exec 9>"/tmp/taquilla-redeploy.lock"
flock -n 9 || fail "Ya hay otro redeploy corriendo."

# --- Validaciones ---
[ -f .env.docker ] || fail "No existe .env.docker (ver README-Docker.md)"
grep -qE '^APP_KEY=.+' .env.docker || fail "APP_KEY vacía en .env.docker"
grep -qE '^APP_DEBUG=false' .env.docker || echo "AVISO: APP_DEBUG no está en false"
docker network inspect proxy >/dev/null 2>&1 || fail "No existe la red 'proxy': levanta primero semahn-infra en /var/www/proxy"
[ -z "$(git status --porcelain --untracked-files=no)" ] || \
  fail "Hay cambios locales en el servidor. Revisa 'git status' (no se edita código en prod)."

# --- ¿Hay cambios? ---
log "Buscando cambios en origin/$BRANCH"
git fetch --quiet origin "$BRANCH"
PREV="$(git rev-parse HEAD)"
NEXT="$(git rev-parse "origin/$BRANCH")"
if [ "$PREV" = "$NEXT" ] && [ "$FORCE" -eq 0 ]; then
  echo "Sin cambios ($(git log -1 --format='%h %s')). Usa --force para reconstruir."
  exit 0
fi
git --no-pager log --oneline "$PREV..$NEXT" || true

# --- Respaldo previo (las migraciones corren al arrancar app) ---
if [ "$BACKUP" -eq 1 ] && docker ps --format '{{.Names}}' | grep -qx taquilla_db; then
  log "Respaldo previo de la base"
  BACKUP_TAG="predeploy" "$ROOT/scripts/backup.sh"
fi

log "git pull ($PREV → $NEXT)"
git merge --ff-only --quiet "origin/$BRANCH"

log "Construyendo imágenes"
dc build --pull

log "Levantando contenedores"
dc up -d --remove-orphans

# --- Esperar a que nginx responda (app ya migró: nginx depende de app healthy) ---
log "Esperando healthcheck"
for i in $(seq 1 60); do
  STATUS="$(docker inspect -f '{{.State.Health.Status}}' taquilla_nginx 2>/dev/null || echo starting)"
  [ "$STATUS" = "healthy" ] && break
  [ "$i" -eq 60 ] && { dc logs --tail=50 app nginx; fail "nginx no quedó healthy. Rollback: git reset --hard $PREV && docker compose --env-file .env.docker up -d --build (si migró, restaura el respaldo predeploy)"; }
  sleep 3
done
echo "nginx: healthy"

for svc in queue scheduler; do
  [ "$(docker inspect -f '{{.State.Running}}' "taquilla_$svc" 2>/dev/null)" = "true" ] \
    || { dc logs --tail=30 "$svc"; fail "$svc no está corriendo (sin él no salen correos o no expiran compras)"; }
done
echo "queue y scheduler: corriendo"

DOMAIN="$(grep -E '^DOMAIN=' .env.docker | cut -d= -f2- | tr -d '"' || true)"
if [ -n "$DOMAIN" ]; then
  if curl -fsS -m 10 -o /dev/null "https://$DOMAIN/up"; then
    echo "https://$DOMAIN/up → OK"
  else
    echo "AVISO: https://$DOMAIN/up no respondió (¿está arriba el proxy en /var/www/proxy?)"
  fi
fi

log "Limpiando imágenes viejas"
docker image prune -f >/dev/null
find "$LOG_DIR" -name 'deploy_*.log' -mtime +30 -delete

log "Deploy OK → $(git log -1 --format='%h %s')"
echo "Anterior: $PREV   (rollback: git reset --hard $PREV && docker compose --env-file .env.docker up -d --build)"
dc ps
