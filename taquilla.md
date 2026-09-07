# Sistema de Taquilla Web — ZooMAT

Brief de desarrollo. Este archivo es la fuente de verdad del proyecto: si algo aquí contradice una
suposición tuya, gana este archivo. Si algo no está aquí, pregunta antes de inventarlo.

---

## 1. Qué es esto

Venta anticipada de boletos en línea para el Zoológico Regional Miguel Álvarez del Toro (ZooMAT),
Gobierno del Estado de Chiapas.

El visitante compra desde su celular, paga en línea, recibe un código QR por correo y lo presenta en el
acceso. El personal de taquilla escanea el QR y descuenta pases. El administrador gestiona catálogos,
precios, calendario y cortes.

**Anteproyecto aprobado.** El alcance de abajo ya está validado; no lo amplíes por iniciativa propia.

---

## 2. Punto de partida — lo que YA existe

El proyecto Laravel 12 ya está creado y tiene aplicada la skill `laravel-auth-rbac`. **No regeneres nada
de esto, no lo dupliques y no lo "mejores" sin avisar.**

### Tablas existentes

```
roles         id_rol, nombre
permisos      id_permiso, nombre (snake_case), descripcion
rol_permiso   id_rol, id_permiso           (PK compuesta)
usuarios      id_usuario, nombre, puesto, extension, email, id_departamento
cuentas       id_cuenta, username, password, estado, id_usuario, id_rol, remember_token
movimientos   id_movimiento, id_cuenta, tabla, accion, registro_id, detalles (JSON), fecha
```

### Modelos existentes

`App\Models\Rol`, `Permiso`, `Usuario`, `Cuenta`, `Movimiento`.

`Cuenta` es el modelo autenticable (`config/auth.php` ya apunta ahí). Métodos disponibles:

```php
$cuenta->usuario()              // belongsTo Usuario
$cuenta->rol()                  // belongsTo Rol
$cuenta->isAdmin(): bool
$cuenta->tieneRol(string): bool // case-insensitive
$cuenta->tienePermiso(string $permiso): bool
$cuenta->permisosArray(): array
```

### Controladores y middleware existentes

`AuthController`, `AdminController`, `UserController`.
`EnsureRole`, `EnsurePermission`, `CheckAccountStatus`.

Alias ya registrados en `bootstrap/app.php`:

```php
'rol'            => EnsureRole::class
'permission'     => EnsurePermission::class
'account.status' => CheckAccountStatus::class   // ya aplicado global al grupo web
```

Uso:

```php
->middleware('rol:Administrador')
->middleware('permission:editar_rubros')
->middleware('permission:any,generar_cortes,ver_estadisticas')   // al menos uno
```

### Reglas heredadas que NO se tocan

1. Login por `username`, nunca por email.
2. Orden de validación en login: existe la cuenta → está activa → `Auth::attempt()`. Nunca filtres por
   `estado` dentro del `attempt()`.
3. Ninguna cuenta puede eliminarse, desactivarse ni cambiarse de rol a sí misma.
4. Solo `id_usuario = 1` puede modificar a otros Administradores o asignar el rol Administrador.
   Enforced en el controlador, no solo en la vista.
5. Los permisos viven en `session('permisos_usuario')` y se refrescan cuando el usuario edita los
   permisos de su propio rol.
6. Rutas admin protegidas por **permiso**. Los dashboards sí van por **rol**.
7. Sin `spatie/laravel-permission`. Sin Breeze. Sin Jetstream. Sin Filament.

---

## 3. Ajustes a la base existente (primero que nada)

### 3.1 Renombrar el rol `Usuario` → `Taquilla`

La skill sembró los roles `Administrador` y `Usuario`. Este proyecto usa **dos roles internos**:
`Administrador` y `Taquilla`. El visitante NO es un rol de esta tabla (ver 3.2).

- Renombra el registro en `roles`.
- Renombra la ruta `user.dashboard` → `taquilla.dashboard` y su vista.
- `Cuenta::isUsuario()` → `Cuenta::isTaquilla()`, comparando contra `'Taquilla'`.

### 3.2 Guard separado para el visitante

El visitante **no vive en `cuentas`**. Vive en `clientes`, con su propio guard.

```php
// config/auth.php
'guards' => [
    'web'     => ['driver' => 'session', 'provider' => 'cuentas'],   // personal interno
    'cliente' => ['driver' => 'session', 'provider' => 'clientes'],  // visitantes
],
'providers' => [
    'cuentas'  => ['driver' => 'eloquent', 'model' => App\Models\Cuenta::class],
    'clientes' => ['driver' => 'eloquent', 'model' => App\Models\Cliente::class],
],
```

Un cliente **jamás** debe alcanzar `/admin/*`. Una cuenta interna **jamás** debe poder comprar.
`CheckAccountStatus` aplica solo al guard `web`; no lo apliques al portal público.

### 3.3 Permisos nuevos

La skill sembró 7 permisos. Agrega estos 14 en un seeder nuevo (`PermisosTaquillaSeeder`), sin tocar
`AuthSeeder`:

| Permiso | Administrador | Taquilla |
|---|---|---|
| `gestion_rubros` | ✓ | |
| `editar_rubros` | ✓ | |
| `gestion_catalogos` | ✓ | |
| `editar_catalogos` | ✓ | |
| `gestion_aforo` | ✓ | |   <!-- calendario de operación -->
| `gestion_clientes` | ✓ | |
| `validar_accesos` | ✓ | ✓ |
| `ver_bitacora_accesos` | ✓ | ✓ |
| `generar_cortes` | ✓ | ✓ |
| `conciliar_pagos` | ✓ | |
| `ver_estadisticas` | ✓ | |
| `cancelar_compras` | ✓ | |
| `autorizar_reembolsos` | ✓ | |
| `ver_bitacora_auditoria` | ✓ | |

Los 7 originales (`gestion_usuarios`, `crear_usuarios`, `editar_usuarios`, `eliminar_usuarios`,
`cambiar_roles`, `activar_cuentas`, `gestion_permisos`) siguen siendo solo de Administrador.

---

## 4. Reglas de negocio innegociables

Estas siete son la razón de ser del sistema. Si una implementación las viola, está mal aunque compile.

### 4.1 El dinero se guarda en enteros

Todos los montos en **centavos**, como `integer`. `4000` es $40.00.
Nunca `float`, nunca `decimal`, nunca `double`. Los cortes tienen que cuadrar al centavo.

Formatea solo en la vista con un helper. Nunca guardes el valor formateado.

### 4.2 El precio JAMÁS viene del navegador

El total se calcula del lado del servidor, leyendo el catálogo `rubros` vigente. Lo que mande el cliente
es solo `rubro_id` y cantidades. Si el request trae un precio o un total, ignóralo.

### 4.3 Precio y nombre del rubro se congelan al comprar

`compra_detalle` guarda `precio_centavos_snap` y `rubro_nombre_snap` además de `rubro_id`.
Si mañana suben la tarifa, los cortes históricos no se mueven. Esto no es opcional: es requisito de
auditoría.

### 4.4 El QR se emite SOLO contra webhook verificado

Nunca desde la pantalla de retorno del cliente. El flujo es:

```
compra creada  → estado 'pendiente_pago'
webhook banco  → verifica firma HMAC
               → verifica que el monto coincida con total_centavos
               → estado 'pagada' + genera qr_token + encola correo
```

El procesamiento del webhook debe ser **idempotente**: los bancos reenvían. Usa
`pagos.referencia_externa` como llave única; si ya existe procesada, responde 200 y no hagas nada más.

### 4.5 Los pases se descuentan de forma atómica

**No hay aforo.** El área operativa definió que no existe cupo máximo ni
mínimo: pueden entrar tres personas o mil, y el visitante compra los boletos
que quiera. Ninguna compra reserva lugares. Lo único que decide la fecha es el
calendario de operación: si el día está abierto, la venta es ilimitada.

Donde el descuento atómico sí sigue siendo obligatorio es en los pases. Nada de
`SELECT` y luego `UPDATE`; una sola sentencia condicional:

```php
// Consumo de pases en el acceso
$ok = DB::table('compras')
    ->where('id', $compraId)
    ->where('estado', 'pagada')
    ->whereRaw('pases_usados + ? <= pases_total', [$n])
    ->update(['pases_usados' => DB::raw("pases_usados + {$n}")]);
if ($ok === 0) throw new AccesoNoDisponibleException();
```

Sin esto, dos escaneos simultáneos meten al doble de gente.

### 4.6 Nada se borra

Eliminación lógica en todas las tablas operativas. Las cancelaciones cambian `estado`, conservan folio.
Los folios son consecutivos, sin huecos, generados dentro de transacción, nunca reutilizados.

### 4.7 Toda operación sensible va a `movimientos`

Alta/cambio/baja sobre: catálogos, rubros, precios, cuentas, calendario, cancelaciones, reembolsos y accesos.
Con cuenta responsable y marca de tiempo. La tabla es de solo inserción.

---

## 5. Modelo de datos a construir

### 5.1 Catálogos

```
paises              id, nombre, iso, activo
estados             id, nombre, activo
municipios          id, id_estado, nombre, activo       -- solo Chiapas por ahora
nacionalidades      id, nombre                          -- NACIONAL, EXTRANJERO
subnacionalidades   id, nombre                          -- ADULTO NACIONAL, ADULTO EXTRANJERO,
                                                        -- NIÑO NACIONAL, NIÑO EXTRANJERO
tipos_acceso        id, nombre                          -- PAGO NORMAL, GRATIS
promociones         id, nombre, vigente_desde, vigente_hasta, activo   -- previsto, sin operación
```

### 5.2 Rubros de cobro

```
rubros
  id                    bigint PK
  tipo                  varchar(80)     -- descripción corta
  descripcion           text            -- descripción a detalle
  id_nacionalidad       FK nacionalidades
  id_subnacionalidad    FK subnacionalidades
  id_tipo_acceso        FK tipos_acceso
  precio_centavos       integer
  vigente_desde         date
  vigente_hasta         date NULL
  activo                boolean
  timestamps, softDeletes
```

Validación: si `id_tipo_acceso` es GRATIS, `precio_centavos` debe ser 0 y el campo se deshabilita en la UI.

### 5.3 Clientes (visitantes)

```
clientes
  id                    uuid PK
  correo                varchar UNIQUE
  password              varchar NULL        -- null si entró por OAuth
  proveedor_oauth       varchar NULL        -- 'google' | null
  proveedor_oauth_id    varchar NULL
  nombre                varchar
  apellidos             varchar
  fecha_nacimiento      date
  genero                varchar
  telefono              varchar
  id_pais               FK NULL
  id_estado             FK NULL
  correo_verificado_en  timestamptz NULL
  timestamps, softDeletes
```

```
verificaciones_correo
  id, correo, codigo_hash, intentos, expira_en, consumido_en, created_at
```

Código de 6 dígitos, vigencia 10 minutos, máximo 3 reenvíos. Guarda el **hash** del código, no el código.

### 5.4 Compras

```
compras
  id                bigint PK
  folio             varchar UNIQUE
  id_cliente        FK clientes
  fecha_compra      timestamptz
  fecha_visita      date
  total_centavos    integer
  pases_total       integer
  pases_usados      integer DEFAULT 0
  estado            varchar          -- ver 5.7
  qr_token          varchar UNIQUE NULL
  qr_expira_en      timestamptz NULL
  id_promocion      FK NULL
  observaciones     text NULL
  timestamps, softDeletes
```

```
compra_detalle
  id                    bigint PK
  id_compra             FK compras (cascade)
  id_rubro              FK rubros
  rubro_nombre_snap     varchar
  precio_centavos_snap  integer
  cant_hombre           integer
  cant_mujer            integer
  cantidad              integer          -- suma de las dos anteriores
  importe_centavos      integer          -- cantidad * precio_centavos_snap
  id_pais               FK NULL
  id_estado             FK NULL
  id_municipio          FK NULL          -- solo si id_estado = Chiapas
  id_nacionalidad       FK
  id_subnacionalidad    FK
  id_tipo_acceso        FK
```

La procedencia va **por renglón**, no por compra: un mismo comprador puede traer gente de distintos
lugares.

### 5.5 Pagos

```
pagos
  id                  bigint PK
  id_compra           FK compras
  proveedor           varchar
  referencia_externa  varchar UNIQUE      -- llave de idempotencia
  monto_centavos      integer
  estado              varchar             -- iniciado | aprobado | rechazado | reembolsado
  autorizacion        varchar NULL
  payload_webhook     jsonb NULL
  conciliado_en       timestamptz NULL
  timestamps
```

### 5.6 Accesos, reagendas y calendario

```
accesos
  id, id_compra FK, id_torniquete, pases_consumidos, escaneado_en,
  id_cuenta FK (operador), resultado varchar, motivo_rechazo varchar NULL

reagendas
  id, id_compra FK, fecha_anterior, fecha_nueva, motivo, created_at

aforo_diario                                   -- calendario de operación
  fecha date PK, cerrado boolean DEFAULT false, motivo_cierre varchar NULL
```

`aforo_diario` NO lleva cupo: es el calendario que dice qué días abre el
zoológico. Los lunes van `cerrado = true` por defecto (el ZooMAT no abre) y
cualquier día puede cerrarse por contingencia. Horario: martes a domingo,
8:30 a 16:00.

### 5.7 Estados de la compra

```
pendiente_pago  → pagada | expirada
pagada          → acceso_parcial | utilizada | vencida | cancelada
                → pagada  (reagendar, registra en bitácora)
acceso_parcial  → utilizada
cancelada       → reembolsada
```

`expirada` ocurre a los 15 minutos sin pago. Job programado. No libera cupo
—no hay cupo—, pero sin él un carrito abandonado se queda como
`pendiente_pago` para siempre y ensucia cortes y conciliación.

---

## 6. Servicios de dominio

Toda la lógica de negocio vive aquí. Los controladores solo orquestan: validan request, llaman al
servicio, devuelven respuesta. **Cero reglas de negocio en controladores, cero en modelos, cero en vistas.**

```
app/Services/Venta/CotizarCompraService.php      -- calcula total contra catálogo vigente
app/Services/Venta/RegistrarCompraService.php    -- transacción: folio + cabecera + detalle
app/Services/Venta/ReagendarCompraService.php    -- cambia la fecha de visita, bitácora
app/Services/Pago/PasarelaPago.php               -- INTERFAZ
app/Services/Pago/PasarelaSimulada.php           -- implementación para desarrollo
app/Services/Pago/ConfirmarPagoService.php       -- procesa webhook, idempotente
app/Services/Acceso/ValidarAccesoService.php     -- valida token, descuenta pases, bitácora
app/Services/Acceso/QrTokenService.php           -- firma y verifica el token HMAC
app/Services/Reporte/CorteIngresosService.php
app/Services/Reporte/EstadisticaService.php
app/Services/Auditoria/BitacoraService.php       -- escribe en movimientos
```

### Interfaz de la pasarela

```php
interface PasarelaPago
{
    public function crearCobro(Compra $compra): CobroCreado;      // devuelve url de redirección + referencia
    public function verificarEstado(string $referencia): EstadoPago;
    public function procesarNotificacion(Request $request): NotificacionPago;  // valida firma
}
```

Bíndala en un service provider. **Todo el desarrollo corre contra `PasarelaSimulada`** hasta que el banco
entregue credenciales. Cuando llegue el proveedor real, se agrega una clase nueva y se cambia el binding:
nada más se toca.

### Token del QR

HMAC-SHA256 sobre `{compra_id}.{folio}.{fecha_visita}` con `APP_KEY`. La imagen del QR se genera al vuelo,
no se almacena. Un token alterado se rechaza sin consultar la base.

---

## 7. Rutas

### `routes/web.php` — portal público (guard `cliente`)

```
GET   /                          inicio
GET   /registro                  formulario
POST  /registro                  crea cliente + envía código
GET   /registro/verificar        captura del código
POST  /registro/verificar        valida código
POST  /registro/reenviar         máx 3, con throttle
GET   /ingresar                  login cliente
POST  /ingresar
GET   /auth/google/redirect      Socialite
GET   /auth/google/callback
POST  /salir

--- middleware: auth:cliente ---
GET   /comprar                   selector de rubros
POST  /comprar/cotizar           devuelve total calculado en servidor
POST  /comprar                   registra compra → redirige a pasarela
GET   /comprar/retorno           pantalla de "procesando", NO emite QR
GET   /mis-compras
GET   /mis-compras/{folio}
GET   /mis-compras/{folio}/qr    imagen del QR
POST  /mis-compras/{folio}/reagendar
POST  /mis-compras/{folio}/reembolso
GET   /mi-cuenta
```

### `routes/web.php` — panel interno (guard `web`)

```
--- middleware: auth, rol:Administrador ---
GET   /admin/dashboard

--- middleware: auth, rol:Taquilla ---
GET   /taquilla/dashboard

--- middleware: auth + permission:* ---
/admin/rubros              gestion_rubros / editar_rubros
/admin/catalogos/*         gestion_catalogos / editar_catalogos
/admin/aforo               gestion_aforo   -- calendario de operación
/admin/clientes            gestion_clientes
/admin/compras             gestion_clientes
/admin/compras/{id}/cancelar     cancelar_compras
/admin/reembolsos/{id}/autorizar autorizar_reembolsos
/admin/cortes              generar_cortes
/admin/conciliacion        conciliar_pagos
/admin/estadisticas        ver_estadisticas
/admin/bitacora            ver_bitacora_auditoria
/accesos/escanear          validar_accesos          -- módulo torniquetes
/accesos/bitacora          ver_bitacora_accesos
```

### `routes/api.php` — módulo de torniquetes (Sanctum)

```
POST  /api/accesos/validar       { qr_token, pases, id_torniquete }
GET   /api/accesos/vigentes      compras del día, para modo contingencia
POST  /api/accesos/sincronizar   escaneos registrados sin conexión
```

### `routes/webhooks.php` — sin CSRF, con firma

```
POST  /webhooks/pago/{proveedor}
```

Regístralo en `bootstrap/app.php` y **exclúyelo de la verificación CSRF**. Valida firma HMAC antes de
tocar la base.

---

## 8. Frontend

Blade + Tailwind. Sin React, sin Vue, sin Inertia. Alpine.js solo donde haga falta interactividad puntual.

Aplica la skill `humanismo-frontend`. Paleta obligatoria:

```css
--color-jade:     #009887;   /* acción primaria, CTA */
--color-magenta:  #c90166;   /* badges, estados especiales */
--color-cinabrio: #ae192d;   /* alertas, eliminar */
--color-arena:    #d3c2b4;
--color-fondo:    #f7f5f2;
--color-superficie: #ffffff;
--color-texto:    #1f2933;
--color-texto-suave: #6b7280;
--color-borde:    #e5e7eb;
```

Tipografía: Novecento Wide (títulos, en mayúsculas), Gilroy (cuerpo). Fallback `Inter, system-ui`.

```
resources/views/publico/    portal de compra
resources/views/admin/      panel administrativo
resources/views/accesos/    módulo de torniquetes (PWA)
resources/views/emails/     comprobante con QR
```

El módulo de accesos es una PWA: debe funcionar en tablet, usar la cámara y cachear las compras del día.

La fecha de visita se elige en un **calendario**, no en una lista: el visitante
ve el mes, los días abiertos son elegibles y los cerrados quedan apagados.
Cambiar de mes no recarga la página, para no perder las cantidades capturadas.

### Leyendas obligatorias en el portal

- Horario: **martes a domingo, 8:30 a 16:00 hrs. Lunes cerrado.**
- Si no llega el correo, revisar spam o correo no deseado.
- Verificar que el tipo de visitante sea el correcto; se valida en el acceso y de lo contrario se paga boleto.
- Tercera edad presenta INAPAM. Estudiante presenta credencial. Niño Pavón gratis hasta 1.20 m de estatura.

---

## 9. Orden de trabajo

Trabaja por fases. **Al terminar cada una, detente y reporta antes de seguir.**

### Fase 0 — Ajustes a la base (empieza aquí)
- [ ] Renombrar rol `Usuario` → `Taquilla`, ruta y vista incluidas
- [ ] `Cuenta::isUsuario()` → `isTaquilla()`
- [ ] Guard `cliente` en `config/auth.php`
- [ ] `PermisosTaquillaSeeder` con los 14 permisos nuevos
- [ ] Verificar que `php artisan migrate:fresh --seed` corra limpio

### Fase 1 — Catálogos y administración
- [ ] Migraciones de catálogos + rubros + calendario
- [ ] Seeders: países, estados (32), municipios de Chiapas (124), nacionalidades, subnacionalidades, tipos de acceso
- [ ] CRUD de rubros con la validación de GRATIS → precio 0
- [ ] CRUD de catálogos
- [ ] Pantalla de calendario de operación, con lunes cerrados por defecto
- [ ] `BitacoraService` conectado a todos los CRUD
- [ ] Vista de bitácora de auditoría

### Fase 2 — Compra y emisión
- [ ] Migraciones: clientes, verificaciones_correo, compras, compra_detalle, pagos
- [ ] Registro con verificación por código, con throttle
- [ ] Login cliente + Google (Socialite). **Enlazar cuenta local con OAuth solo si el correo ya está verificado.**
- [ ] `CotizarCompraService` — con pruebas
- [ ] `RegistrarCompraService` — transacción completa, con pruebas de venta simultánea
- [ ] `PasarelaSimulada` + `ConfirmarPagoService` idempotente
- [ ] `QrTokenService` + generación de imagen
- [ ] Correo con comprobante PDF y QR (en cola)
- [ ] Mis compras + reagendar
- [ ] Job de expiración a los 15 minutos

### Fase 3 — Accesos y reportes
- [ ] `ValidarAccesoService` con descuento atómico, con pruebas de concurrencia
- [ ] PWA de escaneo + modo contingencia
- [ ] Bitácora de accesos
- [ ] `CorteIngresosService`
- [ ] `EstadisticaService`

### Fase 4 — Integración bancaria (bloqueada)
No arranca hasta tener convenio y credenciales de pruebas. Cuando llegue: adaptador nuevo + cambio de
binding. Nada más.

---

## 10. Pruebas que sí importan

No busco cobertura alta, busco estas seis:

1. Cotización: el total calculado ignora cualquier precio que venga del request.
2. Venta sin tope: 50 compras simultáneas para el mismo día pasan las 50. No hay cupo que agotar.
3. Concurrencia de acceso: dos escaneos simultáneos del mismo QR consumen los pases una sola vez.
4. Idempotencia: el mismo webhook procesado tres veces produce una sola compra pagada.
5. Snapshot: cambiar el precio de un rubro no altera el importe de compras anteriores.
6. Aislamiento de guards: un cliente autenticado recibe 403 en `/admin/*`.

---

## 11. Cosas que NO debes hacer

- No instales `spatie/laravel-permission`, Breeze, Jetstream, Filament ni Livewire.
- No uses React, Vue ni Inertia.
- No guardes montos como float o decimal.
- No confíes en ningún precio, total o cantidad que venga del navegador.
- No emitas el QR en la pantalla de retorno del pago.
- No hagas `SELECT` + `UPDATE` para el consumo de pases.
- No borres registros con `delete()` duro en tablas operativas.
- No sobrescribas archivos generados por la skill sin avisar primero qué cambiarías y por qué.
- No implementes facturación CFDI, punto de venta en taquilla ni cola virtual: están fuera de alcance.
- No agregues promociones ni descuentos: los precios son fijos.

---

## 12. Pendientes de definición

Si el código topa con alguno de estos, **pregunta en vez de asumir**:

| Punto | Estado |
|---|---|
| Cupo máximo diario | **Definido: no hay.** Sin aforo máximo ni mínimo; la venta de un día abierto es ilimitada |
| Tolerancia de la fecha de visita | Sin definir. Asume solo el día programado |
| Anticipación mínima para reagendar | Sin definir. Asume 24 h |
| Número máximo de reagendados | Sin definir. Asume 1 |
| Política de reembolso | Sin definir. Deja el flujo hasta "solicitud registrada" |
| Proveedor de pago | Sin definir. Todo contra `PasarelaSimulada` |
| Grupo incompleto | Sin devolución. Confirmado por el área operativa |

---

## 13. Entorno

```bash
php artisan migrate:fresh --seed
php artisan queue:work          # el correo con QR va en cola
php artisan serve
```

Base de datos: PostgreSQL 16. Zona horaria: `America/Mexico_City`. Locale: `es_MX`.

Credenciales iniciales de la skill: `admin` / `admin123` — cámbiala en el primer ingreso.
