<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de usuarios</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; }
        header { padding: 1rem 2rem; background: #1e3a8a; color: #fff; display: flex; justify-content: space-between; align-items: center; }
        main { padding: 2rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.6rem 0.8rem; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        .badge { padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.8rem; }
        .badge-activo { background: #d1fae5; color: #065f46; }
        .badge-inactivo { background: #fee2e2; color: #991b1b; }
        .flash { padding: 0.7rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .flash-ok { background: #d1fae5; color: #065f46; }
        .flash-err { background: #fee2e2; color: #991b1b; }
        form.inline { display: inline; margin: 0; }
        button { cursor: pointer; }
        details { margin-top: 0.4rem; }
        summary { cursor: pointer; font-size: 0.85rem; color: #1e3a8a; }
        .perm-list { display: grid; gap: 0.2rem; margin: 0.5rem 0; font-size: 0.85rem; }
    </style>
</head>
<body>
    <header>
        <strong>Gestión de usuarios</strong>
        <a href="{{ route('admin.dashboard') }}" style="color:#fff;">← Volver</a>
    </header>

    <main>
        @if (session('success'))
            <div class="flash flash-ok">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="flash flash-err">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="flash flash-err">
                <ul style="margin:0; padding-left:1.2rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (auth()->user()->puedeCrearUsuarios())
        <details>
            <summary><strong>+ Nuevo usuario</strong></summary>
            <form method="POST" action="{{ route('admin.users.store') }}" style="margin-top:1rem; display:grid; gap:0.5rem; max-width:480px;">
                @csrf
                <input type="text" name="nombre" placeholder="Nombre completo" value="{{ old('nombre') }}" required>
                <input type="text" name="puesto" placeholder="Puesto (opcional)" value="{{ old('puesto') }}">
                <input type="email" name="email" placeholder="Email (opcional)" value="{{ old('email') }}">
                <input type="text" name="username" placeholder="Username" value="{{ old('username') }}" required>
                <input type="password" name="password" placeholder="Contraseña (mín. 6)" required>
                <select name="id_rol" required>
                    <option value="">— Rol —</option>
                    @foreach ($roles as $rol)
                        <option value="{{ $rol->id_rol }}">{{ $rol->nombre }}</option>
                    @endforeach
                </select>
                <button type="submit">Crear</button>
            </form>
        </details>
        @endif

        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Nombre</th><th>Username</th><th>Rol</th><th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cuentas as $cuenta)
                    @php $esYo = $cuenta->id_cuenta === auth()->user()->id_cuenta; @endphp
                    <tr>
                        <td>{{ $cuenta->id_cuenta }}</td>
                        <td>{{ $cuenta->usuario->nombre }}</td>
                        <td>{{ $cuenta->username }}</td>
                        <td>
                            {{ $cuenta->rol->nombre }}

                            @if (! $esYo && auth()->user()->puedeCambiarRoles())
                                <form class="inline" method="POST" action="{{ route('admin.users.update-role', $cuenta->id_cuenta) }}">
                                    @csrf @method('PUT')
                                    <select name="id_rol" onchange="this.form.submit()">
                                        @foreach ($roles as $rol)
                                            <option value="{{ $rol->id_rol }}" @selected($rol->id_rol === $cuenta->id_rol)>{{ $rol->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <noscript><button type="submit">Cambiar</button></noscript>
                                </form>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-{{ $cuenta->estado }}">{{ $cuenta->estado }}</span>
                        </td>
                        <td>
                            @if (! $esYo)
                                @if (auth()->user()->puedeActivarCuentas())
                                    <form class="inline" method="POST" action="{{ route('admin.users.toggle-status', $cuenta->id_cuenta) }}">
                                        @csrf @method('PUT')
                                        <button type="submit">{{ $cuenta->estado === 'activo' ? 'Desactivar' : 'Activar' }}</button>
                                    </form>
                                @endif
                                @if (auth()->user()->puedeEliminarUsuarios())
                                    <form class="inline" method="POST" action="{{ route('admin.users.destroy', $cuenta->id_cuenta) }}"
                                          onsubmit="return confirm('¿Eliminar este usuario?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" style="color:#b91c1c;">Eliminar</button>
                                    </form>
                                @endif
                            @else
                                <em>(tú)</em>
                            @endif

                            @if (auth()->user()->puedeEditarUsuarios() && ! $esYo)
                                <details>
                                    <summary>Editar datos</summary>
                                    <form method="POST" action="{{ route('admin.users.update', $cuenta->id_cuenta) }}"
                                          style="display:grid; gap:0.3rem; margin-top:0.5rem; max-width:300px;">
                                        @csrf @method('PUT')
                                        <input type="text" name="nombre" value="{{ $cuenta->usuario->nombre }}" required>
                                        <input type="text" name="puesto" value="{{ $cuenta->usuario->puesto }}" placeholder="Puesto">
                                        <input type="email" name="email" value="{{ $cuenta->usuario->email }}" placeholder="Email">
                                        <input type="password" name="password" placeholder="Nueva contraseña (opcional)">
                                        <button type="submit">Guardar</button>
                                    </form>
                                </details>
                            @endif

                            @if (auth()->user()->puedeGestionarPermisos())
                                <details>
                                    <summary>Permisos del rol «{{ $cuenta->rol->nombre }}»</summary>
                                    <form method="POST" action="{{ route('admin.users.update-permissions', $cuenta->id_cuenta) }}">
                                        @csrf @method('PUT')
                                        <div class="perm-list">
                                            @php $actuales = $cuenta->permisosArray(); @endphp
                                            @foreach ($permisos as $permiso)
                                                <label>
                                                    <input type="checkbox" name="permisos[]" value="{{ $permiso->id_permiso }}"
                                                           @checked(in_array($permiso->id_permiso, $actuales, true))>
                                                    {{ $permiso->nombre }}
                                                    <span style="color:#6b7280;">— {{ $permiso->descripcion }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <button type="submit">Guardar permisos</button>
                                        <p style="font-size:0.75rem;color:#6b7280;margin:0.3rem 0 0;">
                                            Aplica a <strong>todas</strong> las cuentas con el rol «{{ $cuenta->rol->nombre }}».
                                        </p>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </main>
</body>
</html>
