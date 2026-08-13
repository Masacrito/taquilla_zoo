<?php

use App\Http\Controllers\WebhookPagoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
|
| Sin CSRF y sin sesión (brief §7). La autenticidad la da la firma HMAC que
| verifica el adaptador de la pasarela ANTES de tocar la base de datos.
|
| Esta es la ÚNICA vía por la que una compra se marca pagada y se emite el
| QR (§4.4). La pantalla de retorno del cliente no emite nada.
|
*/

Route::post('/webhooks/pago/{proveedor}', [WebhookPagoController::class, 'recibir'])
    ->name('webhooks.pago');
