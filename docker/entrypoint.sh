#!/bin/sh
set -e

# ─────────────────────────────────────────────────────────────────────────
#  Arranque de todos los contenedores de la aplicación.
#
#  Lo comparten php-fpm, el worker de colas y el scheduler, para que los
#  tres vean exactamente la misma configuración cacheada.
# ─────────────────────────────────────────────────────────────────────────

# ── ¿Arranque de servicio, o comando suelto? ────────────────────────────
#
# La diferencia importa: las comprobaciones y el cacheo de abajo aplican a
# un servicio que se queda corriendo, no a un `artisan` de una sola vez.
#
# Sin esta distinción, exigir APP_KEY bloqueaba también el comando que
# sirve para generarla, y no había forma de arrancar la primera vez.
es_servicio=0
case "$1" in
    php-fpm)
        es_servicio=1
        ;;
    php)
        case "$*" in
            *queue:work*|*schedule:work*|*queue:listen*) es_servicio=1 ;;
        esac
        ;;
esac

if [ "$es_servicio" -eq 0 ]; then
    exec "$@"
fi

# ── APP_KEY: se exige, no se genera ─────────────────────────────────────
#
# Esta aplicación firma los códigos QR con APP_KEY, y el secreto del webhook
# cae en ella si no se define PAGO_SECRETO_WEBHOOK. Generar una llave al
# arrancar dejaría sin validar todos los QR ya emitidos, y los visitantes se
# quedarían fuera del zoológico con un boleto pagado. Mejor no arrancar.
if [ -z "${APP_KEY}" ]; then
    echo "ERROR: APP_KEY está vacía." >&2
    echo "" >&2
    echo "  Generala UNA sola vez y guardala en el .env del servidor:" >&2
    echo "    docker compose run --rm --no-deps app php artisan key:generate --show" >&2
    echo "" >&2
    echo "  No la cambies después: los códigos QR ya emitidos están firmados" >&2
    echo "  con ella y dejarían de validar en el acceso." >&2
    exit 1
fi

# ── Esperar a PostgreSQL ────────────────────────────────────────────────
# El healthcheck de compose ya lo cubre al levantar, pero un reinicio de la
# base con los contenedores arriba deja al worker intentando contra nada.
if [ -n "${DB_HOST}" ]; then
    printf 'Esperando a PostgreSQL en %s:%s' "${DB_HOST}" "${DB_PORT:-5432}"
    intentos=0
    until php -r "new PDO('pgsql:host=${DB_HOST};port=${DB_PORT:-5432};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
        intentos=$((intentos + 1))
        if [ "$intentos" -ge 30 ]; then
            echo ""
            echo "ERROR: no se pudo conectar a PostgreSQL tras 30 intentos." >&2
            echo "" >&2
            echo "  Base: ${DB_DATABASE}  Usuario: ${DB_USERNAME}" >&2
            echo "" >&2
            echo "  Si la base SÍ está arriba, lo más probable es que se haya" >&2
            echo "  creado con otras credenciales. El contenedor de Postgres las" >&2
            echo "  toma por interpolación, o sea del archivo que reciba" >&2
            echo "  --env-file, mientras que la aplicación las toma de env_file." >&2
            echo "  Si corriste compose sin --env-file .env.docker, cada uno" >&2
            echo "  quedó con un juego distinto." >&2
            echo "" >&2
            echo "    docker compose --env-file .env.docker down -v" >&2
            echo "    docker compose --env-file .env.docker up -d --build" >&2
            exit 1
        fi
        printf '.'
        sleep 2
    done
    echo " listo."
fi

# ── Migraciones: explícitas, no automáticas ─────────────────────────────
# Se activan con RUN_MIGRATIONS=true y SOLO en el servicio `app`, que es el
# único que las corre. Por eso no lleva `--isolated`: ese lock vive en la
# tabla `cache_locks`, que la crean estas mismas migraciones, así que contra
# una base nueva falla antes de poder crear nada.
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "Ejecutando migraciones..."
    php artisan migrate --force
fi

# ── Caché de configuración ──────────────────────────────────────────────
# Aquí y no en el build: al construir la imagen todavía no existe el .env del
# servidor, y `config:cache` habría congelado valores vacíos.
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
