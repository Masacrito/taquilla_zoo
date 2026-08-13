@extends('layouts.interno')

@section('titulo', 'Gestión de usuarios')
@section('subtitulo', 'Cuentas internas del sistema')

@section('contenido')

    @if (auth()->user()->puedeCrearUsuarios())
        <details class="card mb-6">
            <summary class="titulo cursor-pointer text-sm text-jade">+ Nuevo usuario</summary>

            <form method="POST" action="{{ route('admin.users.store') }}"
                  class="mt-5 grid max-w-2xl gap-4 sm:grid-cols-2">
                @csrf

                <div>
                    <label class="label">Nombre completo</label>
                    <input type="text" name="nombre" class="input" value="{{ old('nombre') }}" required>
                </div>
                <div>
                    <label class="label">Puesto</label>
                    <input type="text" name="puesto" class="input" value="{{ old('puesto') }}">
                </div>
                <div>
                    <label class="label">Correo</label>
                    <input type="email" name="email" class="input" value="{{ old('email') }}">
                </div>
                <div>
                    <label class="label">Rol</label>
                    <select name="id_rol" class="input" required>
                        <option value="">— Seleccionar —</option>
                        @foreach ($roles as $rol)
                            <option value="{{ $rol->id_rol }}" @selected(old('id_rol') == $rol->id_rol)>{{ $rol->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Username</label>
                    <input type="text" name="username" class="input" value="{{ old('username') }}" required autocomplete="off">
                </div>
                <div>
                    <label class="label">Contraseña (mín. 6)</label>
                    <input type="password" name="password" class="input" required autocomplete="new-password">
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" class="btn-primary">Crear usuario</button>
                </div>
            </form>
        </details>
    @endif

    <div class="overflow-hidden rounded-card border border-borde bg-superficie shadow-soft">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="titulo bg-jade text-left text-[11px] text-white">
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Username</th>
                        <th class="px-4 py-3">Rol</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cuentas as $cuenta)
                        @php $esYo = $cuenta->id_cuenta === auth()->user()->id_cuenta; @endphp
                        <tr class="border-b border-borde align-top transition-colors last:border-0 hover:bg-jade/4">
                            <td class="px-4 py-3 text-texto-suave">{{ $cuenta->id_cuenta }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $cuenta->usuario->nombre }}</p>
                                @if ($cuenta->usuario->puesto)
                                    <p class="text-xs text-texto-suave">{{ $cuenta->usuario->puesto }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-texto-suave">{{ $cuenta->username }}</td>
                            <td class="px-4 py-3">
                                @if (! $esYo && auth()->user()->puedeCambiarRoles())
                                    <form method="POST" action="{{ route('admin.users.update-role', $cuenta->id_cuenta) }}">
                                        @csrf @method('PUT')
                                        <select name="id_rol" onchange="this.form.submit()"
                                                class="input px-2 py-1 text-xs">
                                            @foreach ($roles as $rol)
                                                <option value="{{ $rol->id_rol }}" @selected($rol->id_rol === $cuenta->id_rol)>{{ $rol->nombre }}</option>
                                            @endforeach
                                        </select>
                                        <noscript><button type="submit" class="btn-outline btn-sm mt-1">Cambiar</button></noscript>
                                    </form>
                                @else
                                    {{ $cuenta->rol->nombre }}
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="badge-{{ $cuenta->estado }}">{{ $cuenta->estado }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($esYo)
                                    <span class="text-xs text-texto-suave italic">(tu cuenta)</span>
                                @else
                                    <div class="flex flex-wrap gap-2">
                                        @if (auth()->user()->puedeActivarCuentas())
                                            <form method="POST" action="{{ route('admin.users.toggle-status', $cuenta->id_cuenta) }}">
                                                @csrf @method('PUT')
                                                <button type="submit" class="btn-outline btn-sm">
                                                    {{ $cuenta->estado === 'activo' ? 'Desactivar' : 'Activar' }}
                                                </button>
                                            </form>
                                        @endif
                                        @if (auth()->user()->puedeEliminarUsuarios())
                                            <form method="POST" action="{{ route('admin.users.destroy', $cuenta->id_cuenta) }}"
                                                  onsubmit="return confirm('¿Eliminar a {{ $cuenta->usuario->nombre }}? La cuenta se conserva en la bitácora, pero no podrá volver a entrar.');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn-danger btn-sm">Eliminar</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif

                                @if (auth()->user()->puedeEditarUsuarios() && ! $esYo)
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-xs text-jade">Editar datos</summary>
                                        <form method="POST" action="{{ route('admin.users.update', $cuenta->id_cuenta) }}"
                                              class="mt-2 grid max-w-xs gap-2">
                                            @csrf @method('PUT')
                                            <input type="text" name="nombre" class="input py-1.5 text-xs"
                                                   value="{{ $cuenta->usuario->nombre }}" required>
                                            <input type="text" name="puesto" class="input py-1.5 text-xs"
                                                   value="{{ $cuenta->usuario->puesto }}" placeholder="Puesto">
                                            <input type="email" name="email" class="input py-1.5 text-xs"
                                                   value="{{ $cuenta->usuario->email }}" placeholder="Correo">
                                            <input type="password" name="password" class="input py-1.5 text-xs"
                                                   placeholder="Nueva contraseña (opcional)" autocomplete="new-password">
                                            <button type="submit" class="btn-primary btn-sm">Guardar</button>
                                        </form>
                                    </details>
                                @endif

                                @if (auth()->user()->puedeGestionarPermisos())
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-xs text-jade">
                                            Permisos del rol «{{ $cuenta->rol->nombre }}»
                                        </summary>
                                        <form method="POST" action="{{ route('admin.users.update-permissions', $cuenta->id_cuenta) }}"
                                              class="mt-2">
                                            @csrf @method('PUT')
                                            @php $actuales = $cuenta->permisosArray(); @endphp
                                            <div class="grid gap-1">
                                                @foreach ($permisos as $permiso)
                                                    <label class="flex items-start gap-2 text-xs">
                                                        <input type="checkbox" name="permisos[]" value="{{ $permiso->id_permiso }}"
                                                               class="mt-0.5 accent-jade"
                                                               @checked(in_array($permiso->id_permiso, $actuales, true))>
                                                        <span>
                                                            <span class="font-medium">{{ $permiso->nombre }}</span>
                                                            <span class="text-texto-suave">— {{ $permiso->descripcion }}</span>
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <button type="submit" class="btn-primary btn-sm mt-3">Guardar permisos</button>
                                            <p class="mt-2 text-[11px] text-texto-suave">
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
        </div>
    </div>
@endsection
