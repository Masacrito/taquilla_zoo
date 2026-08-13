<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante {{ $compra->folio }}</title>
    <style>
        /* dompdf no ejecuta Tailwind: los estilos van embebidos y en unidades
           absolutas, que es lo único que rasteriza de forma predecible. */
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2933;
            margin: 0;
        }
        .encabezado {
            border-bottom: 3px solid #009887;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .titulo {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #1f2933;
            margin: 0;
        }
        .subtitulo { font-size: 10px; color: #6b7280; margin: 3px 0 0; }
        .folio {
            font-size: 20px;
            font-weight: bold;
            color: #009887;
            letter-spacing: 1px;
        }
        table { width: 100%; border-collapse: collapse; }
        .datos td { padding: 3px 0; vertical-align: top; }
        .datos .etiqueta { color: #6b7280; width: 38%; }
        .detalle { margin-top: 16px; }
        .detalle th {
            background: #009887;
            color: #fff;
            text-align: left;
            padding: 7px 9px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        .detalle td { padding: 7px 9px; border-bottom: 1px solid #e5e7eb; }
        .num { text-align: right; }
        .centro { text-align: center; }
        .total {
            margin-top: 14px;
            border-top: 2px solid #1f2933;
            padding-top: 9px;
            font-size: 15px;
            font-weight: bold;
        }
        .qr-caja {
            margin-top: 26px;
            text-align: center;
            border: 2px dashed #d3c2b4;
            border-radius: 10px;
            padding: 18px;
        }
        .avisos {
            margin-top: 26px;
            font-size: 9.5px;
            color: #6b7280;
            line-height: 1.65;
        }
        .pie {
            margin-top: 22px;
            border-top: 1px solid #e5e7eb;
            padding-top: 9px;
            font-size: 8.5px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="encabezado">
        <table>
            <tr>
                <td>
                    <p class="titulo">Zoológico Regional<br>Miguel Álvarez del Toro</p>
                    <p class="subtitulo">Comprobante de compra en línea</p>
                </td>
                <td class="num" style="vertical-align: top;">
                    <span class="folio">{{ $compra->folio }}</span>
                </td>
            </tr>
        </table>
    </div>

    <table class="datos">
        <tr>
            <td class="etiqueta">Visitante</td>
            <td>{{ $compra->cliente->nombreCompleto() }}</td>
            <td class="etiqueta">Fecha de compra</td>
            <td>{{ $compra->fecha_compra->translatedFormat('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Correo</td>
            <td>{{ $compra->cliente->correo }}</td>
            <td class="etiqueta">Fecha de visita</td>
            <td><strong>{{ $compra->fecha_visita->translatedFormat('d/m/Y') }}</strong></td>
        </tr>
        <tr>
            <td class="etiqueta">Pases</td>
            <td>{{ $compra->pases_total }}</td>
            <td class="etiqueta">Estado</td>
            <td>{{ str_replace('_', ' ', $compra->estado) }}</td>
        </tr>
    </table>

    <table class="detalle">
        <thead>
            <tr>
                <th>Concepto</th>
                <th class="centro">Cantidad</th>
                <th class="num">Precio unitario</th>
                <th class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($compra->detalle as $renglon)
                <tr>
                    {{-- Nombre y precio congelados al momento de comprar (§4.3) --}}
                    <td>{{ $renglon->rubro_nombre_snap }}</td>
                    <td class="centro">{{ $renglon->cantidad }}</td>
                    <td class="num">${{ number_format($renglon->precio_centavos_snap / 100, 2) }}</td>
                    <td class="num">{{ $renglon->importeFormateado() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="total">
        <tr>
            <td>TOTAL</td>
            <td class="num">{{ $compra->totalFormateado() }}</td>
        </tr>
    </table>

    <div class="qr-caja">
        <p style="margin:0 0 10px; font-weight:bold; letter-spacing:1px; text-transform:uppercase;">
            Presenta este código en el acceso
        </p>
        <img src="{{ $qrPng }}" alt="Código QR {{ $compra->folio }}" style="width:190px; height:190px;">
        <p style="margin:10px 0 0; font-size:10px; color:#6b7280;">{{ $compra->folio }}</p>
    </div>

    <div class="avisos">
        <strong>Antes de tu visita</strong><br>
        • Horario: martes a domingo, 8:30 a 16:00 hrs. Lunes cerrado.<br>
        • Verifica que el tipo de visitante sea el correcto: se valida en el acceso y, de lo contrario, se paga boleto.<br>
        • Tercera edad presenta INAPAM. Estudiante presenta credencial. Niño Pavón gratis hasta 1.20 m de estatura.<br>
        • Este comprobante es válido únicamente para la fecha de visita indicada.
    </div>

    <div class="pie">
        Secretaría de Medio Ambiente e Historia Natural · Gobierno del Estado de Chiapas 2024–2030
    </div>

</body>
</html>
