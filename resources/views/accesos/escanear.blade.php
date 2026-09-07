@extends('layouts.interno')

@section('titulo', 'Acceso')
@section('subtitulo', 'Escaneo de códigos QR')

@section('contenido')

    <div class="grid gap-4 lg:grid-cols-2">

        {{-- ═══ Escáner ═══ --}}
        <div class="card">
            <div class="flex items-center justify-between gap-3">
                <p class="titulo text-sm text-jade">Escáner</p>
                <span id="estadoCamara" class="text-xs text-texto-suave">apagada</span>
            </div>

            <div class="relative mt-4 overflow-hidden rounded-input bg-texto" style="aspect-ratio: 4/3;">
                <video id="camara" playsinline muted class="h-full w-full object-cover"></video>
                <canvas id="lienzo" class="hidden"></canvas>

                <div id="capaCamara" class="absolute inset-0 grid place-items-center p-6 text-center">
                    <div>
                        <p class="text-sm text-white/80">Cámara apagada</p>
                        <button type="button" id="btnCamara" class="btn-primary mt-3">Encender cámara</button>
                    </div>
                </div>

                {{-- Se muestra mientras hay un pase en pantalla: deja claro que
                     la cámara está detenida a propósito. --}}
                <div id="capaPausa" class="absolute inset-0 hidden place-items-center bg-texto/85 p-6 text-center">
                    <div>
                        <p class="titulo text-sm text-white">Escáner en pausa</p>
                        <p class="mt-2 text-xs text-white/70">Termina con este visitante para continuar.</p>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <label class="label">Captura manual (folio o código)</label>
                <div class="flex gap-2">
                    <input type="text" id="codigoManual" class="input font-mono text-xs"
                           placeholder="ZM-2026-000001" autocomplete="off">
                    <button type="button" id="btnManual" class="btn-outline shrink-0">Buscar</button>
                </div>
                <p class="mt-1.5 text-[11px] text-texto-suave">
                    Si la cámara falla, escribe el folio impreso en el comprobante.
                </p>
            </div>
        </div>

        {{-- ═══ Resultado ═══ --}}
        <div>
            <div id="panel" class="card min-h-64 grid place-items-center text-center">
                <p class="text-sm text-texto-suave">Esperando un código…</p>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="card text-center">
                    <p id="contadorEntradas" class="text-2xl font-semibold tabular-nums">{{ number_format($entradasHoy) }}</p>
                    <p class="mt-1 text-xs text-texto-suave">Entradas de hoy</p>
                </div>
                <div class="card text-center">
                    <p class="text-2xl font-semibold tabular-nums">{{ number_format($compradasHoy) }}</p>
                    <p class="mt-1 text-xs text-texto-suave">Compras para hoy</p>
                </div>
            </div>

            @can('ver_bitacora_accesos')
                <a href="{{ route('accesos.bitacora') }}" class="btn-outline mt-4 w-full">Ver bitácora</a>
            @endcan
        </div>
    </div>

    {{-- Lector de QR empaquetado con Vite y servido desde este servidor, no
         desde un CDN: el acceso debe funcionar sin internet (brief §8). --}}
    @vite('resources/js/escaner.js')
    <script>
        (function () {
            const RUTAS = {
                consultar: '{{ route('accesos.consultar') }}',
                validar:   '{{ route('accesos.validar') }}',
            };
            const CSRF = '{{ csrf_token() }}';

            const video      = document.getElementById('camara');
            const lienzo     = document.getElementById('lienzo');
            const ctx        = lienzo.getContext('2d', { willReadFrequently: true });
            const capaCamara = document.getElementById('capaCamara');
            const capaPausa  = document.getElementById('capaPausa');
            const btnCamara  = document.getElementById('btnCamara');
            const btnManual  = document.getElementById('btnManual');
            const inputCod   = document.getElementById('codigoManual');
            const panel      = document.getElementById('panel');
            const estado     = document.getElementById('estadoCamara');
            const contador   = document.getElementById('contadorEntradas');

            let camaraLista = false;   // el hardware está encendido
            let leyendo     = false;   // el bucle busca códigos activamente
            let codigoEnCurso = null;

            const pitar = (ok) => {
                try {
                    const audio = new AudioContext();
                    const osc = audio.createOscillator();
                    const vol = audio.createGain();
                    osc.connect(vol); vol.connect(audio.destination);
                    osc.frequency.value = ok ? 880 : 220;
                    vol.gain.value = 0.08;
                    osc.start(); osc.stop(audio.currentTime + (ok ? 0.12 : 0.35));
                } catch (e) { /* sin audio, ni modo */ }
            };

            // ── Control del bucle de lectura ──────────────────────────────
            // Detener la lectura tras CADA código es lo que evita que un
            // pase agotado, dejado frente a la cámara, se revalide en bucle
            // y llene la bitácora de rechazos.
            const pausar = () => {
                leyendo = false;
                if (camaraLista) {
                    capaPausa.classList.remove('hidden');
                    capaPausa.classList.add('grid');
                    estado.textContent = 'en pausa';
                }
            };

            const reanudar = () => {
                codigoEnCurso = null;
                inputCod.value = '';
                panel.className = 'card min-h-64 grid place-items-center text-center';
                panel.innerHTML = '<p class="text-sm text-texto-suave">Esperando un código…</p>';

                if (camaraLista) {
                    capaPausa.classList.add('hidden');
                    capaPausa.classList.remove('grid');
                    estado.textContent = 'buscando…';
                    leyendo = true;
                    requestAnimationFrame(cuadro);
                }
            };

            const pedir = async (ruta, cuerpo) => {
                const respuesta = await fetch(ruta, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(cuerpo),
                });
                return respuesta.json();
            };

            // ── Paso 1: consultar (no consume) ────────────────────────────
            const consultar = async (codigo) => {
                if (!codigo || codigoEnCurso) return;

                codigoEnCurso = codigo;
                pausar();

                try {
                    const datos = await pedir(RUTAS.consultar, { codigo, id_torniquete: 'web' });
                    pitar(datos.permitido);
                    datos.permitido ? pintarConfirmacion(datos) : pintarRechazo(datos);
                } catch (e) {
                    pintarSinConexion();
                }
            };

            // ── Paso 2: confirmar (sí consume) ────────────────────────────
            const confirmar = async (pases) => {
                try {
                    const datos = await pedir(RUTAS.validar, {
                        codigo: codigoEnCurso, pases, id_torniquete: 'web',
                    });
                    pitar(datos.permitido);
                    datos.permitido ? pintarEntrada(datos) : pintarRechazo(datos);

                    if (datos.permitido) {
                        contador.textContent = (parseInt(contador.textContent.replace(/\D/g, ''), 10) || 0)
                            + datos.consumido;
                    }
                } catch (e) {
                    pintarSinConexion();
                }
            };

            const bloqueCompra = (c) => c ? `
                <div class="mt-4 border-t border-borde/60 pt-3 text-left text-xs">
                    <p><strong>Folio:</strong> ${c.folio}</p>
                    <p><strong>Visitante:</strong> ${c.visitante ?? '—'}</p>
                    <p><strong>Visita:</strong> ${c.fecha_visita}</p>
                    ${(c.detalle ?? []).map(d =>
                        `<p>${d.cantidad} × ${d.concepto}</p>`).join('')}
                </div>` : '';

            // Pantalla de decisión: cuántos entran de los que quedan.
            const pintarConfirmacion = (datos) => {
                const c = datos.compra;
                const quedan = c.pases_restantes;

                panel.className = 'card min-h-64 border-jade bg-jade-suave text-center';
                panel.innerHTML = `
                    <p class="titulo text-lg text-jade">Pase válido</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums">${quedan}</p>
                    <p class="text-xs text-texto-suave">
                        ${quedan === 1 ? 'persona puede entrar' : 'personas pueden entrar'}
                        (de ${c.pases_total})
                    </p>
                    ${bloqueCompra(c)}
                    <div class="mt-4 flex items-center justify-center gap-2">
                        <label class="text-xs text-texto-suave">Entran</label>
                        <input type="number" id="cuantos" value="${quedan}" min="1" max="${quedan}"
                               class="input w-20 py-1.5 text-center text-sm">
                        <button type="button" id="btnConfirmar" class="btn-primary btn-sm">Registrar entrada</button>
                    </div>
                    <button type="button" id="btnCancelar"
                            class="mt-2 text-xs text-texto-suave underline-offset-2 hover:underline">
                        Cancelar
                    </button>`;

                document.getElementById('btnConfirmar').addEventListener('click', () => {
                    const n = Math.min(quedan, Math.max(1, parseInt(document.getElementById('cuantos').value, 10) || 1));
                    confirmar(n);
                });
                document.getElementById('btnCancelar').addEventListener('click', reanudar);
            };

            const pintarEntrada = (datos) => {
                const c = datos.compra;
                const agotado = c.pases_restantes === 0;

                panel.className = 'card min-h-64 border-jade bg-jade-suave text-center';
                panel.innerHTML = `
                    <p class="titulo text-lg text-jade">Puede pasar</p>
                    <p class="mt-1 text-4xl font-semibold tabular-nums">${datos.consumido}</p>
                    <p class="text-xs text-texto-suave">
                        ${datos.consumido === 1 ? 'persona autorizada' : 'personas autorizadas'}
                    </p>
                    ${bloqueCompra(c)}
                    <p class="mt-3 text-xs ${agotado ? 'text-texto-suave' : 'font-medium text-magenta'}">
                        ${agotado
                            ? 'Código agotado: no quedan pases.'
                            : `Quedan ${c.pases_restantes} pases en este código.`}
                    </p>
                    <button type="button" id="btnSiguiente" class="btn-primary mt-4 w-full">
                        Escanear siguiente
                    </button>
                    ${agotado ? '' : `
                        <button type="button" id="btnMas"
                                class="mt-2 text-xs text-jade underline-offset-2 hover:underline">
                            Registrar más pases de este mismo código
                        </button>`}`;

                document.getElementById('btnSiguiente').addEventListener('click', reanudar);
                document.getElementById('btnMas')?.addEventListener('click', () => {
                    const codigo = codigoEnCurso;
                    codigoEnCurso = null;
                    consultar(codigo);
                });
            };

            const pintarRechazo = (datos) => {
                panel.className = 'card min-h-64 border-cinabrio bg-cinabrio-suave text-center';
                panel.innerHTML = `
                    <p class="titulo text-lg text-cinabrio">No puede pasar</p>
                    <p class="mt-2 text-sm">${datos.mensaje}</p>
                    ${bloqueCompra(datos.compra)}
                    <button type="button" id="btnSiguiente" class="btn-primary mt-4 w-full">
                        Escanear siguiente
                    </button>`;
                document.getElementById('btnSiguiente').addEventListener('click', reanudar);
            };

            const pintarSinConexion = () => {
                panel.className = 'card min-h-64 border-cinabrio bg-cinabrio-suave text-center';
                panel.innerHTML = `
                    <p class="titulo text-lg text-cinabrio">Sin conexión</p>
                    <p class="mt-2 text-sm">No se pudo contactar al servidor.</p>
                    <button type="button" id="btnSiguiente" class="btn-primary mt-4 w-full">Reintentar</button>`;
                document.getElementById('btnSiguiente').addEventListener('click', reanudar);
            };

            const cuadro = () => {
                if (!leyendo) return;

                if (video.readyState === video.HAVE_ENOUGH_DATA && window.jsQR) {
                    lienzo.width  = video.videoWidth;
                    lienzo.height = video.videoHeight;
                    ctx.drawImage(video, 0, 0, lienzo.width, lienzo.height);

                    const imagen = ctx.getImageData(0, 0, lienzo.width, lienzo.height);
                    const codigo = window.jsQR(imagen.data, imagen.width, imagen.height);

                    if (codigo && codigo.data) {
                        consultar(codigo.data);
                        return;   // el bucle se detiene hasta que el operador continúe
                    }
                }

                requestAnimationFrame(cuadro);
            };

            btnCamara?.addEventListener('click', async () => {
                try {
                    video.srcObject = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment' },
                    });
                    await video.play();
                    capaCamara.classList.add('hidden');
                    camaraLista = true;
                    reanudar();
                } catch (e) {
                    capaCamara.innerHTML = '<p class="px-6 text-sm text-white/80">'
                        + 'No se pudo abrir la cámara. Usa la captura manual de abajo.</p>';
                }
            });

            btnManual.addEventListener('click', () => consultar(inputCod.value.trim()));
            inputCod.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); consultar(inputCod.value.trim()); }
            });
        })();
    </script>
@endsection
