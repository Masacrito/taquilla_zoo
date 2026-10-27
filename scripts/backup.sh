#!/usr/bin/env bash
# =============================================================
#  Taquilla ZooMAT — respaldo de la base (PostgreSQL en Docker)
#  Uso manual:  ./scripts/backup.sh
#  Cron (2:00 a.m.):  0 2 * * * /var/www/taquilla/scripts/backup.sh >> /var/log/taquilla-backup.log 2>&1
#
#  Guarda FUERA del repo, con retención. Copia también .env.docker:
#  sin su APP_KEY los QR ya emitidos no se pueden validar.
#  Restaurar (base vacía):
#    gunzip -c archivo.sql.gz | docker exec -i taquilla_db sh -c 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"'
# =============================================================
set -euo pipefail

cd "$(dirname "$0")/.."
BACKUP_DIR="${BACKUP_DIR:-/var/backups/taquilla}"
RETENCION_DIAS="${RETENCION_DIAS:-14}"
TAG="${BACKUP_TAG:-daily}"
FILE="$BACKUP_DIR/taquilla_${TAG}_$(date +%F_%H-%M).sql.gz"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
umask 077

# container_name fijo en compose.yaml: funciona sin --env-file
docker exec taquilla_db sh -c 'exec pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --no-owner --no-acl' \
  | gzip -9 > "$FILE.tmp"

# Validar que el dump está completo antes de darlo por bueno
gzip -t "$FILE.tmp"
zcat "$FILE.tmp" | tail -n 5 | grep -q "PostgreSQL database dump complete" \
  || { rm -f "$FILE.tmp"; echo "ERROR: dump incompleto" >&2; exit 1; }
mv "$FILE.tmp" "$FILE"

cp .env.docker "$BACKUP_DIR/env_latest"

find "$BACKUP_DIR" -name 'taquilla_*.sql.gz' -mtime +"$RETENCION_DIAS" -delete

echo "[$(date '+%F %T')] OK $(du -h "$FILE" | cut -f1) → $FILE"
