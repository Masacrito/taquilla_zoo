# Despliegue en contenedores — Taquilla ZooMAT

Entorno de pruebas tipo producción. Todo lo de aquí se levantó y se probó de
punta a punta antes de escribirse: compra, pago simulado, webhook, generación
del QR, correo del comprobante con su PDF, y expiración programada.

## Requisitos

- Docker Engine >= 24 y Docker Compose >= 2.20
- Un proxy que termine TLS delante (nginx, Caddy o Traefik en el host)

## Qué levanta

| Servicio    | Para qué |
|-------------|----------|
| `nginx`     | Servidor web. Trae los assets compilados dentro de la imagen |
| `app`       | php-fpm. Es el único que corre migraciones |
| `queue`     | Worker de colas. **Sin él el comprobante con el QR nunca sale por correo**, y nada reporta error |
| `scheduler` | Equivale al cron de `schedule:run`. **Sin él las compras sin pagar no expiran** |
| `db`        | PostgreSQL 16. Sin puerto publicado: solo se alcanza desde la red interna |
| `mailpit`   | Captura los correos en vez de mandarlos. Se leen en el puerto 8025 |

## El archivo de entorno y el flag

La configuración vive en **`.env.docker`**, no en `.env`. Son dos entornos
distintos: `.env` es el del desarrollo local con `artisan serve` y apunta a
una base en `127.0.0.1`; `.env.docker` apunta al contenedor. Separados, se
puede pasar de uno a otro sin intercambiar archivos.

Por eso **todos los comandos llevan `--env-file .env.docker`**. Para no
escribirlo cada vez, expórtalo una vez por terminal:

```bash
export COMPOSE_ENV_FILES=.env.docker
```

Si se te olvida y existe un `.env` en el directorio, Compose tomaría de ahí
las credenciales de Postgres mientras la aplicación usa las de `.env.docker`,
y la base quedaría creada con un juego y consultada con otro. El contenedor
lo detecta y aborta explicando eso; si no hay `.env`, Compose ni siquiera
arranca y dice qué variable falta.

## Primer arranque

```bash
cp .env.docker.example .env.docker
```

Genera la llave **una sola vez** y pégala en `APP_KEY` de `.env.docker`:

```bash
docker compose --env-file .env.docker run --rm --no-deps app php artisan key:generate --show
```

> ⚠️ **No cambies `APP_KEY` nunca más.** Los códigos QR se firman con ella
> (`QrTokenService`) y de ella cuelga el secreto del webhook si no defines
> `PAGO_SECRETO_WEBHOOK`. Si la cambias, todos los boletos ya vendidos dejan
> de validar en el acceso y la gente se queda afuera con su compra pagada.
> Los contenedores se niegan a arrancar si está vacía, a propósito.

Ajusta también `APP_URL`, `DB_PASSWORD` y, si vas a usar correo real, los
`MAIL_*`. Después:

```bash
docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker ps
```

Los cinco servicios deben quedar `running`, y `app`, `db`, `nginx` y
`mailpit` además `healthy`.

## El día uno no vas a poder vender nada

Las migraciones corren solas al arrancar, pero **sembrar no**, porque sembrar
a ciegas en un servidor es mala idea. Recién levantado no hay roles, ni
catálogos, ni tarifas, ni calendario, así que la portada dirá que no hay
tarifas y `/comprar` que no hay fechas. Eso es esperado:

```bash
# Roles, permisos y catálogos geográficos. Obligatorio.
docker compose --env-file .env.docker exec app php artisan db:seed --force
```

Después entra al panel y haz dos cosas, que son las que habilitan la venta:

1. **Captura los rubros** con las tarifas reales en `/admin/rubros`.
2. **Genera los días** en `/admin/aforo`.

> El usuario inicial es `admin` / `admin123` y **no hay rotación forzada de
> contraseña**. Cámbiala antes de que el servidor sea alcanzable desde
> internet.

Si solo quieres probar el recorrido sin capturar nada, hay datos ficticios
—con precios inventados— en `DemoSeeder`:

```bash
docker compose exec app php artisan db:seed --class=DemoSeeder --force
```

## Comandos de operación

> Los ejemplos omiten `--env-file .env.docker` por brevedad. Si exportaste
> `COMPOSE_ENV_FILES` funcionan tal cual; si no, agrégalo.

```bash
docker compose logs -f                    # todo
docker compose logs -f queue              # ver los correos procesándose
docker compose exec app php artisan ...   # cualquier comando de artisan
docker compose exec db psql -U taquilla taquilla_zoomat

docker compose restart app
docker compose down                       # detiene, conserva la base
docker compose down -v                    # ⚠️ BORRA la base y los archivos
```

### Redesplegar una versión nueva

```bash
git pull
docker compose up -d --build
```

Las migraciones corren solas en el arranque de `app`. El worker tiene 130 s
de gracia para terminar el job en curso antes de morir, así que un
redespliegue no deja un comprobante a medias.

## Verificar que quedó bien

Las tres cosas que fallan calladas. Si estas pasan, el despliegue está sano:

```bash
# 1. GD y PostgreSQL presentes — si falta gd, el QR truena cuando alguien pague
docker compose exec app php -m | grep -E "^(gd|pdo_pgsql)$"

# 2. El worker está tomando trabajo
docker compose logs queue --tail 20

# 3. El scheduler dispara cada minuto
docker compose logs scheduler --tail 20
```

Y la prueba de verdad: compra un boleto, simula el pago aprobado y abre
Mailpit en `http://<servidor>:8025`. Debe llegar un correo con el QR
incrustado y el PDF adjunto. Si llega, el recorrido completo funciona.

## Antes de exponerlo a internet

- [ ] `APP_DEBUG=false`. Con `true`, las pantallas de error de Laravel
      muestran las credenciales de la base.
- [ ] Contraseña de `admin` cambiada.
- [ ] TLS terminado en el proxy del host, y `SESSION_SECURE_COOKIE=true`.
- [ ] `APP_URL` con el dominio real: de ahí salen los enlaces del correo y
      el callback de Google.
- [ ] El puerto de `APP_PORT` **no** abierto directo a internet; que pase
      por el proxy.
- [ ] Mailpit apagado si vas a usar SMTP real, y su puerto 8025 nunca
      público — cualquiera podría leer los comprobantes.

## Producción real

Esta configuración sirve para pruebas. Para producción faltan dos cosas:

1. **La pasarela.** `PAGO_PASARELA=simulada` no cobra dinero, y solo opera
   en los entornos de `config/taquilla.php → taquilla.pago.entornos_simulada`.
   Con `APP_ENV=production` la aplicación se niega a arrancar cobros en vez
   de fingir que cobra.
2. **Los secretos.** Aquí van en un `.env`. En producción conviene moverlos
   al gestor de secretos del orquestador.

## Problemas comunes

**El sitio sale sin estilos.** Los assets se compilan en la imagen. Si
cambiaste algo de `resources/`, hay que reconstruir: `docker compose up -d --build`.

**`queue` o `scheduler` reiniciándose.** Casi siempre es que arrancaron antes
de que las migraciones terminaran. Ambos esperan a que `app` esté `healthy`,
que es después de migrar; si aun así pasa, mira `docker compose logs app`.

**Cambié `DB_PASSWORD` y ahora Postgres rechaza la conexión.** El volumen ya
se inicializó con la contraseña anterior; Docker no la actualiza. Si no
necesitas conservar los datos: `docker compose down -v && docker compose up -d`.
Si sí, sácalos antes con `pg_dump`.

**Contraseñas con `$`, `!` o `#` fallan sin decir por qué.** Compose interpola
`$` en las variables. Usa contraseñas alfanuméricas.

**El healthcheck de nginx falla pero el sitio responde.** Tiene que apuntar a
`127.0.0.1`, no a `localhost`: dentro del contenedor eso resuelve primero a
IPv6 y nginx solo escucha en IPv4.
