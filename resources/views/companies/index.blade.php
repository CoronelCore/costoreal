<x-modern>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Mis Empresas</h1>
                <p class="mt-1 text-sm text-gray-600">Gestiona tus empresas y organiza tus importaciones</p>
            </div>
            <a href="{{ route('companies.create') }}" class="btn btn-primary inline-flex items-center gap-2 shadow-md hover:shadow-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nueva Empresa
            </a>
        </div>
    </x-slot>

    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if(session('success'))
            <div class="mb-6">
                <x-alert type="success">
                    {{ session('success') }}
                </x-alert>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6">
                <x-alert type="error">
                    {{ session('error') }}
                </x-alert>
            </div>
        @endif

        @if($tripsUnassigned->count() > 0)
            <div class="mb-6">
                <x-alert type="warning">
                    <div class="flex items-start justify-between">
                        <div>
                            <strong>⚠️ Tienes {{ $tripsUnassigned->count() }} importación(es) sin asignar a ninguna empresa</strong>
                            <p class="mt-1 text-sm">Asigna estas importaciones a una empresa para poder filtrarlas correctamente.</p>
                        </div>
                    </div>
                </x-alert>
            </div>
        @endif

        @if($companies->isEmpty())
            <!-- Empty state -->
            <div class="text-center py-20">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full gradient-primary mb-6">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No tienes empresas aún</h3>
                <p class="text-gray-600 mb-8 max-w-md mx-auto">
                    Crea tu primera empresa para organizar tus importaciones y ver analíticas separadas
                </p>
                <a href="{{ route('companies.create') }}" class="btn btn-primary inline-flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Crear Mi Primera Empresa
                </a>
            </div>
        @else
            <!-- Grid de empresas -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($companies as $company)
                    <article class="card group" style="border-left: 4px solid {{ $company->color }}">
                        <!-- Header -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center gap-3 flex-1">
                                <div style="color: {{ $company->color }}">
                                    <x-company-icon :icon="$company->icono ?: 'building'" class="w-8 h-8" />
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        {{ $company->nombre }}
                                    </h3>
                                    @if($company->rut)
                                        <p class="text-xs text-gray-500">RUT: {{ $company->rut }}</p>
                                    @endif
                                </div>
                            </div>
                            <span class="badge {{ $company->activa ? 'badge-success' : 'bg-gray-100 text-gray-700' }}">
                                {{ $company->activa ? '● Activa' : '○ Inactiva' }}
                            </span>
                        </div>

                        @if($company->descripcion)
                            <p class="text-sm text-gray-600 mb-4 line-clamp-2">{{ $company->descripcion }}</p>
                        @endif

                        <!-- Stats -->
                        <div class="pt-4 border-t border-gray-100 grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Importaciones</p>
                                <p class="text-lg font-semibold text-gray-900">{{ $company->trips_count }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Total Gastado</p>
                                <p class="text-lg font-semibold text-gray-900">
                                    ${{ number_format($company->total_gastado, 0, ',', '.') }}
                                </p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-2">
                            <a href="{{ route('company.switch', $company->id) }}"
                               class="flex-1 btn btn-secondary text-sm py-2"
                               title="Seleccionar esta empresa">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Seleccionar
                            </a>
                            <a href="{{ route('companies.show', $company) }}"
                               class="btn btn-secondary text-sm py-2 px-3"
                               title="Ver detalles">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </a>
                            <a href="{{ route('companies.edit', $company) }}"
                               class="btn btn-secondary text-sm py-2 px-3"
                               title="Editar">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                            <form method="POST" action="{{ route('companies.destroy', $company) }}" class="inline"
                                  onsubmit="return confirm('¿Estás seguro de eliminar esta empresa? Solo puedes eliminar empresas sin importaciones asignadas.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn bg-red-500 hover:bg-red-600 text-white text-sm py-2 px-3"
                                        title="Eliminar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>

            @if($tripsUnassigned->count() > 0)
                <!-- Sección de importaciones sin asignar -->
                <div class="mt-8">
                    <div class="card">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center">
                                <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Importaciones Sin Asignar</h2>
                                <p class="text-sm text-gray-600">Asigna estas importaciones a una empresa</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            @foreach($tripsUnassigned as $trip)
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                                    <div>
                                        <h4 class="font-medium text-gray-900">{{ $trip->nombre }}</h4>
                                        <p class="text-sm text-gray-600">
                                            {{ $trip->fecha_inicio ? $trip->fecha_inicio->format('d M Y') : 'Sin fecha' }}
                                        </p>
                                    </div>
                                    <a href="{{ route('trips.edit', $trip) }}"
                                       class="btn btn-secondary text-sm">
                                        Asignar Empresa
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-modern>
