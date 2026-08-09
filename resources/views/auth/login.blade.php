<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión</title>
    <style>
        :root {
            --color-primary: #1e3a8a;
            --color-primary-dark: #1e40af;
            --color-error: #dc2626;
            --color-bg: #f3f4f6;
            --color-card: #ffffff;
            --color-text: #1f2937;
            --color-muted: #6b7280;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1rem;
        }
        .card {
            background: var(--color-card);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 380px;
        }
        h1 { margin: 0 0 1.5rem; font-size: 1.5rem; text-align: center; }
        label {
            display: block;
            font-size: 0.85rem;
            color: var(--color-muted);
            margin-bottom: 0.4rem;
        }
        input {
            width: 100%;
            padding: 0.7rem 0.9rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            outline: none;
            transition: border 0.15s;
        }
        input:focus { border-color: var(--color-primary); }
        .field { margin-bottom: 1rem; }
        button {
            width: 100%;
            padding: 0.8rem;
            background: var(--color-primary);
            color: white;
            border: 0;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: background 0.15s;
        }
        button:hover { background: var(--color-primary-dark); }
        .errors {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--color-error);
            padding: 0.7rem 0.9rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .errors ul { margin: 0; padding-left: 1.2rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Iniciar sesión</h1>

        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username"
                       value="{{ old('username') }}" required autofocus>
            </div>

            <div class="field">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
