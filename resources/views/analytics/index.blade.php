<x-modern>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Analíticas</h1>
                <p class="mt-1 text-sm text-gray-600">Resumen general de tus importaciones y gastos</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- KPIs Principales -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Gastado -->
            <div class="card hover:shadow-lg">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl gradient-primary flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Total Gastado</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">${{ number_format($totalGastado, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            <!-- Total Importaciones -->
            <div class="card hover:shadow-lg">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Total Importaciones</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ $totalTrips }}</p>
                    </div>
                </div>
            </div>

            <!-- Promedio por Viaje -->
            <div class="card hover:shadow-lg">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Promedio / Importación</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">${{ number_format($promedioViaje, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            <!-- Importación Más Costosa -->
            <div class="card hover:shadow-lg">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Importación Más Costosa</p>
                        @if($viajeMasCostoso)
                            <p class="text-lg font-bold text-gray-900 mt-1 truncate" title="{{ $viajeMasCostoso->nombre }}">
                                {{ Str::limit($viajeMasCostoso->nombre, 20) }}
                            </p>
                            <p class="text-sm text-gray-600">${{ number_format($viajeMasCostoso->costo_total, 0, ',', '.') }}</p>
                        @else
                            <p class="text-sm text-gray-500 mt-1">Sin importaciones</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        @if($gastosPorCategoria->where('total_monto', '>', 0)->isNotEmpty())
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Gráfico de Dona -->
            <div class="card">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Distribución de Gastos</h2>
                        <p class="text-sm text-gray-600">Por categoría</p>
                    </div>
                </div>
                <div class="h-80 flex items-center justify-center">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>

            <!-- Gráfico de Barras -->
            <div class="card">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Top Importaciones</h2>
                        <p class="text-sm text-gray-600">Por costo total</p>
                    </div>
                </div>
                <div class="h-80 flex items-center justify-center">
                    <canvas id="topTripsChart"></canvas>
                </div>
            </div>
        </div>
        @endif

        <!-- Gastos por Categoría -->
        <div class="card mb-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Gastos por Categoría</h2>
                    <p class="text-sm text-gray-600">Distribución de gastos en todas tus categorías</p>
                </div>
            </div>

            @if($gastosPorCategoria->where('total_monto', '>', 0)->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Categoría</th>
                                <th class="text-right py-3 px-4 text-sm font-semibold text-gray-700">Total Gastos</th>
                                <th class="text-right py-3 px-4 text-sm font-semibold text-gray-700">Monto Total</th>
                                <th class="text-right py-3 px-4 text-sm font-semibold text-gray-700">% del Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($gastosPorCategoria->where('total_monto', '>', 0) as $categoria)
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: {{ $categoria->color }}20;">
                                            <x-category-icon :name="$categoria->icono" :color="$categoria->color" class="w-4 h-4" />
                                        </div>
                                        <span class="font-medium text-gray-900">{{ $categoria->nombre }}</span>
                                    </div>
                                </td>
                                <td class="text-right py-3 px-4 text-gray-700">{{ $categoria->total_gastos }}</td>
                                <td class="text-right py-3 px-4 font-semibold text-gray-900">${{ number_format($categoria->total_monto, 0, ',', '.') }}</td>
                                <td class="text-right py-3 px-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" style="background-color: {{ $categoria->color }}20; color: {{ $categoria->color }}">
                                        {{ $totalGastado > 0 ? number_format(($categoria->total_monto / $totalGastado) * 100, 1) : 0 }}%
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <p class="text-gray-500 text-sm">No hay gastos registrados aún</p>
                </div>
            @endif
        </div>

        <!-- Grid de 2 columnas -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Top 5 Importaciones Más Costosas -->
            <div class="card">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Top 5 Importaciones Más Costosas</h2>
                        <p class="text-sm text-gray-600">Tus importaciones con mayor inversión</p>
                    </div>
                </div>

                @if($topViajes->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($topViajes as $index => $viaje)
                        <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <div class="w-8 h-8 rounded-full gradient-primary flex items-center justify-center text-white font-bold text-sm">
                                {{ $index + 1 }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('trips.show', $viaje) }}" class="font-medium text-gray-900 hover:text-primary-600 truncate block">
                                    {{ $viaje->nombre }}
                                </a>
                                <p class="text-xs text-gray-500">{{ $viaje->fecha_inicio->format('d/m/Y') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-900">${{ number_format($viaje->costo_total, 0, ',', '.') }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-gray-500 text-sm">No hay importaciones registradas</p>
                    </div>
                @endif
            </div>

            <!-- Últimos Gastos -->
            <div class="card">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Últimos 10 Gastos</h2>
                        <p class="text-sm text-gray-600">Gastos más recientes registrados</p>
                    </div>
                </div>

                @if($ultimosGastos->isNotEmpty())
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        @foreach($ultimosGastos as $gasto)
                        <div class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg transition">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: {{ $gasto->category->color }}20;">
                                <x-category-icon :name="$gasto->category->icono" :color="$gasto->category->color" class="w-4 h-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $gasto->concepto }}</p>
                                <p class="text-xs text-gray-500">{{ $gasto->category->nombre }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-gray-900">${{ number_format($gasto->monto_clp, 0, ',', '.') }}</p>
                                <p class="text-xs text-gray-500">{{ $gasto->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-gray-500 text-sm">No hay gastos registrados</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Chart.js Script -->
    @if($gastosPorCategoria->where('total_monto', '>', 0)->isNotEmpty())
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Gráfico de Dona - Distribución por Categoría
        const categoryCtx = document.getElementById('categoryChart');
        if (categoryCtx) {
            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: @json($gastosPorCategoria->where('total_monto', '>', 0)->pluck('nombre')),
                    datasets: [{
                        data: @json($gastosPorCategoria->where('total_monto', '>', 0)->pluck('total_monto')),
                        backgroundColor: @json($gastosPorCategoria->where('total_monto', '>', 0)->pluck('color')),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += '$' + context.parsed.toLocaleString('es-CL');
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Gráfico de Barras - Top Importaciones
        const topTripsCtx = document.getElementById('topTripsChart');
        if (topTripsCtx) {
            new Chart(topTripsCtx, {
                type: 'bar',
                data: {
                    labels: @json($topViajes->pluck('nombre')),
                    datasets: [{
                        label: 'Costo Total',
                        data: @json($topViajes->map(fn($v) => $v->costo_total)),
                        backgroundColor: 'rgba(99, 102, 241, 0.8)',
                        borderColor: 'rgb(99, 102, 241)',
                        borderWidth: 1,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString('es-CL');
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Total: $' + context.parsed.y.toLocaleString('es-CL');
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
    @endif
</x-modern>
