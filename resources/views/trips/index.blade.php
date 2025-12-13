<x-modern>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Mis Importaciones</h1>
                <p class="mt-1 text-sm text-gray-600">Gestiona tus importaciones y calcula prorrateo de gastos</p>
            </div>
            <a href="{{ route('trips.create') }}" class="btn btn-primary inline-flex items-center gap-2 shadow-md hover:shadow-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nueva Importación
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

        @if($trips->isEmpty())
            <!-- Empty state moderno -->
            <div class="text-center py-20">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full gradient-primary mb-6">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No tienes importaciones aún</h3>
                <p class="text-gray-600 mb-8 max-w-md mx-auto">
                    Comienza creando tu primera importación para gestionar gastos y calcular prorrateo de productos
                </p>
                <a href="{{ route('trips.create') }}" class="btn btn-primary inline-flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Crear Mi Primera Importación
                </a>
            </div>
        @else
            <!-- Grid de importaciones moderno -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($trips as $trip)
                    <article class="card group">
                        <!-- Header -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    @if($trip->company)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium"
                                              style="background-color: {{ $trip->company->color }}20; color: {{ $trip->company->color }}; border: 1px solid {{ $trip->company->color }}40;">
                                            <x-company-icon :icon="$trip->company->icono ?: 'building'" class="w-3 h-3" />
                                            <span>{{ $trip->company->nombre }}</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            <span>Sin empresa</span>
                                        </span>
                                    @endif
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">
                                    {{ $trip->nombre }}
                                </h3>
                                @if($trip->descripcion)
                                    <p class="mt-1 text-sm text-gray-600 line-clamp-2">{{ $trip->descripcion }}</p>
                                @endif
                            </div>
                            <span class="badge
                                @if($trip->estado === 'activo') badge-success
                                @elseif($trip->estado === 'finalizado') badge-info
                                @else bg-gray-100 text-gray-700
                                @endif">
                                @if($trip->estado === 'activo') ● Activo
                                @elseif($trip->estado === 'finalizado') ✓ Finalizado
                                @else ⊙ Archivado
                                @endif
                            </span>
                        </div>

                        <!-- Info -->
                        <div class="space-y-3 mb-4">
                            @if($trip->fecha_inicio)
                                <div class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    {{ $trip->fecha_inicio->format('d M Y') }}
                                    @if($trip->fecha_fin)
                                        - {{ $trip->fecha_fin->format('d M Y') }}
                                    @endif
                                </div>
                            @endif

                            <div class="flex items-center text-sm text-gray-600">
                                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="font-semibold">{{ $trip->moneda_trabajo }}</span>
                                <span class="mx-1">:</span>
                                <span>${{ number_format($trip->tipo_cambio, 0) }} CLP</span>
                            </div>
                        </div>

                        <!-- Stats -->
                        <div class="pt-4 border-t border-gray-100 grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Gastos</p>
                                <p class="text-sm font-semibold text-gray-900">{{ $trip->expenses->count() }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Productos</p>
                                <p class="text-sm font-semibold text-gray-900">{{ $trip->products->count() }}</p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-2">
                            <a href="{{ route('trips.show', $trip) }}" class="flex-1 btn btn-secondary text-sm py-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Ver
                            </a>
                            <a href="{{ route('trips.edit', $trip) }}" class="btn btn-secondary text-sm py-2 px-3">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                            <form method="POST" action="{{ route('trips.destroy', $trip) }}" class="inline" onsubmit="return confirm('¿Estás seguro de eliminar esta importación? Esta acción no se puede deshacer y se eliminarán todos los gastos y productos asociados.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn bg-red-500 hover:bg-red-600 text-white text-sm py-2 px-3">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-modern>
