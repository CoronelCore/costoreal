<x-modern>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('companies.index') }}" class="p-2 rounded-lg hover:bg-gray-100 transition">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div class="flex items-center gap-3">
                    <div style="color: {{ $company->color }}">
                        <x-company-icon :icon="$company->icono ?: 'building'" class="w-10 h-10" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ $company->nombre }}</h1>
                        @if($company->rut)
                            <p class="text-sm text-gray-600">RUT: {{ $company->rut }}</p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('company.switch', $company) }}" class="btn btn-secondary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    Seleccionar Empresa
                </a>
                <a href="{{ route('companies.edit', $company) }}" class="btn btn-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Editar
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Información de la empresa -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Card de información general -->
            <div class="card" style="border-left: 4px solid {{ $company->color }}">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900">Información</h2>
                </div>
                @if($company->descripcion)
                    <p class="text-sm text-gray-600 mb-4">{{ $company->descripcion }}</p>
                @endif
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="w-3 h-3 rounded-full" style="background-color: {{ $company->color }}"></span>
                        <span class="text-gray-600">Color:</span>
                        <span class="font-mono font-semibold">{{ $company->color }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <span class="badge {{ $company->activa ? 'badge-success' : 'bg-gray-100 text-gray-700' }}">
                            {{ $company->activa ? '● Activa' : '○ Inactiva' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Card de estadísticas -->
            <div class="card">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900">Estadísticas</h2>
                </div>
                <div class="space-y-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Total Importaciones</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $company->trips->count() }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Total Gastado</p>
                        <p class="text-2xl font-bold text-gray-900">
                            ${{ number_format($company->total_gastado, 0, ',', '.') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Card de acciones rápidas -->
            <div class="card">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900">Acciones</h2>
                </div>
                <div class="space-y-2">
                    <a href="{{ route('trips.create') }}" class="btn btn-secondary w-full justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Nueva Importación
                    </a>
                    <a href="{{ route('analytics.index') }}" class="btn btn-secondary w-full justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        Ver Analítica
                    </a>
                </div>
            </div>
        </div>

        <!-- Listado de importaciones -->
        <div class="card">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg gradient-primary flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Importaciones de esta Empresa</h2>
                        <p class="text-sm text-gray-600">{{ $company->trips->count() }} importación(es)</p>
                    </div>
                </div>
            </div>

            @if($company->trips->isEmpty())
                <div class="text-center py-12">
                    <div class="text-gray-400 mb-4">
                        <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay importaciones</h3>
                    <p class="text-gray-600 mb-4">Esta empresa aún no tiene importaciones asignadas</p>
                    <a href="{{ route('trips.create') }}" class="btn btn-primary inline-flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Crear Importación
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($company->trips as $trip)
                        <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 hover:shadow-md transition">
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="font-semibold text-gray-900">{{ $trip->nombre }}</h4>
                                <span class="badge
                                    @if($trip->estado === 'activo') badge-success
                                    @elseif($trip->estado === 'finalizado') badge-info
                                    @else bg-gray-100 text-gray-700
                                    @endif text-xs">
                                    {{ ucfirst($trip->estado) }}
                                </span>
                            </div>
                            @if($trip->fecha_inicio)
                                <p class="text-sm text-gray-600 mb-3">
                                    {{ $trip->fecha_inicio->format('d M Y') }}
                                    @if($trip->fecha_fin) - {{ $trip->fecha_fin->format('d M Y') }} @endif
                                </p>
                            @endif
                            <div class="flex items-center justify-between text-sm mb-3">
                                <span class="text-gray-600">Gastos: {{ $trip->expenses->count() }}</span>
                                <span class="text-gray-600">Productos: {{ $trip->products->count() }}</span>
                            </div>
                            <a href="{{ route('trips.show', $trip) }}" class="btn btn-secondary w-full text-sm justify-center">
                                Ver Detalle
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-modern>
