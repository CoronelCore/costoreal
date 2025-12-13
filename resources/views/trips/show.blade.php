<x-modern>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('trips.index') }}" class="p-2 rounded-lg hover:bg-gray-100 transition">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $trip->nombre }}</h1>
                        @if($trip->estado === 'finalizado')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Finalizada
                            </span>
                        @elseif($trip->estado === 'activo')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <circle cx="10" cy="10" r="3" />
                                </svg>
                                Activa
                            </span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-gray-600">Moneda Base: <span class="font-semibold">CLP</span></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <!-- Dropdown Exportar -->
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="btn bg-green-500 hover:bg-green-600 text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Exportar
                        <svg class="w-4 h-4" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open" @click.away="open = false" x-cloak
                        class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-10 animate-scaleIn">
                        <a href="{{ route('trips.export.pdf', $trip) }}" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            Exportar a PDF
                        </a>
                        <a href="{{ route('trips.export.excel', $trip) }}" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Exportar a Excel
                        </a>
                    </div>
                </div>

                <a href="{{ route('trips.edit', $trip) }}" class="btn btn-secondary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Editar
                </a>

                @if($trip->estado === 'activo')
                    <form method="POST" action="{{ route('trips.close', $trip) }}" class="inline">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn bg-orange-500 hover:bg-orange-600 text-white" onclick="return confirm('¿Finalizar esta importación? Ya no podrás agregar más gastos o productos.')">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Cerrar Importación
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('trips.reopen', $trip) }}" class="inline">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn bg-blue-500 hover:bg-blue-600 text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reabrir Importación
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="{
        tab: localStorage.getItem('trip_tab') || window.location.hash.substring(1) || 'config',
        setTab(newTab) {
            this.tab = newTab;
            localStorage.setItem('trip_tab', newTab);
            window.location.hash = newTab;
        }
    }">
        @if(session('success'))
            <div class="mb-6">
                <x-alert type="success">
                    {{ session('success') }}
                </x-alert>
            </div>
        @endif

        @if($trip->estado === 'finalizado')
            <div class="mb-6">
                <x-alert type="info">
                    <strong>Importación Finalizada:</strong> Esta importación está cerrada. No puedes agregar más gastos o productos. Si necesitas hacer cambios, reabre la importación desde el botón "Reabrir Importación".
                </x-alert>
            </div>
        @endif

        <!-- Tabs Navigation -->
        <div class="bg-white rounded-xl border border-gray-200 mb-6 p-1">
            <nav class="flex gap-1">
                <button @click="setTab('config')" :class="tab === 'config' ? 'bg-primary-50 text-primary-700 shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
                    class="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-lg font-medium text-sm transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Configuración
                </button>
                <button @click="setTab('expenses')" :class="tab === 'expenses' ? 'bg-primary-50 text-primary-700 shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
                    class="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-lg font-medium text-sm transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    Gastos ({{ $trip->expenses->count() }})
                </button>
                <button @click="setTab('products')" :class="tab === 'products' ? 'bg-primary-50 text-primary-700 shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
                    class="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-lg font-medium text-sm transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    Productos ({{ $trip->products->count() }})
                </button>
                <button @click="setTab('sales')" :class="tab === 'sales' ? 'bg-primary-50 text-primary-700 shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
                    class="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-lg font-medium text-sm transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Precio Venta
                </button>
                <button @click="setTab('summary')" :class="tab === 'summary' ? 'bg-primary-50 text-primary-700 shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
                    class="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-lg font-medium text-sm transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Resumen
                </button>
            </nav>
        </div>

        <!-- Tab Content: Configuración -->
        <div x-show="tab === 'config'" x-cloak class="animate-fadeIn">
            <div class="card">
                <h3 class="text-lg font-semibold text-gray-900 mb-6">Configuración de Monedas</h3>

                <x-alert type="info" class="mb-6">
                    La moneda base es <strong>CLP</strong>. Todos los cálculos se realizan automáticamente en pesos chilenos.
                </x-alert>

                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl border border-green-200">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-green-500 flex items-center justify-center">
                                <span class="text-white font-bold text-sm">CLP</span>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Peso Chileno</p>
                                <p class="text-xs text-gray-600">Moneda Base</p>
                            </div>
                        </div>
                        <span class="text-lg font-bold text-gray-900">1.00</span>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-primary-500 flex items-center justify-center">
                                <span class="text-white font-bold text-sm">{{ $trip->moneda_trabajo }}</span>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">
                                    @if($trip->moneda_trabajo === 'USD') Dólar Estadounidense
                                    @elseif($trip->moneda_trabajo === 'BRL') Real Brasileño
                                    @elseif($trip->moneda_trabajo === 'PEN') Sol Peruano
                                    @endif
                                </p>
                                <p class="text-xs text-gray-600">Moneda de Trabajo</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-gray-900">${{ number_format($trip->tipo_cambio, 2) }}</p>
                            <p class="text-xs text-gray-600">CLP</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content: Gastos -->
        <div x-show="tab === 'expenses'" x-cloak class="animate-fadeIn space-y-6">
            @if($trip->estado === 'activo')
            <div class="card">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Agregar Gasto</h3>

                <form method="POST" action="{{ route('trips.expenses.store', $trip) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Categoría</label>
                            <select name="expense_category_id" class="w-full">
                                <option value="">Sin categoría</option>
                                @foreach(\App\Models\ExpenseCategory::all() as $category)
                                    <option value="{{ $category->id }}">{{ $category->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Concepto</label>
                            <input type="text" name="concepto" required class="w-full" placeholder="Ej: Hotel">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Moneda</label>
                            <select name="moneda" required class="w-full">
                                <option value="{{ $trip->moneda_trabajo }}" selected>{{ $trip->moneda_trabajo }}</option>
                                <option value="CLP">CLP</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Monto</label>
                            <input type="number" name="monto" required step="0.01" min="0" class="w-full" placeholder="0.00">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Comprobante (opcional)</label>
                        <input type="file" name="comprobante" accept="image/jpeg,image/png,image/jpg,image/webp" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                        <p class="mt-1 text-xs text-gray-500">JPG, PNG o WEBP. Máximo 2MB.</p>
                    </div>
                    <button type="submit" class="btn btn-primary w-full sm:w-auto">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Agregar Gasto
                    </button>
                </form>
            </div>
            @endif

            <!-- Lista de gastos -->
            @if($trip->expenses->isEmpty())
                <div class="card text-center py-12">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <p class="text-gray-600">No hay gastos registrados</p>
                </div>
            @else
                <div class="card overflow-hidden p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Categoría</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Concepto</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Monto</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">CLP</th>
                                    <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Comp.</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($trip->expenses as $expense)
                                    <tr x-data="{ editing: false }" class="hover:bg-gray-50 transition">
                                        <!-- Vista Normal -->
                                        <template x-if="!editing">
                                            <td class="px-6 py-4">
                                                @if($expense->category)
                                                    <span class="inline-flex items-center gap-2 text-sm">
                                                        <x-category-icon :name="$expense->category->icono" :color="$expense->category->color" class="w-5 h-5" />
                                                        <span class="font-medium">{{ $expense->category->nombre }}</span>
                                                    </span>
                                                @else
                                                    <span class="text-gray-400 text-sm">Sin categoría</span>
                                                @endif
                                            </td>
                                        </template>
                                        <!-- Vista Edición -->
                                        <template x-if="editing">
                                            <td class="px-6 py-4">
                                                <select name="expense_category_id" form="edit-expense-{{ $expense->id }}" class="w-full text-sm">
                                                    <option value="">Sin categoría</option>
                                                    @foreach(\App\Models\ExpenseCategory::all() as $category)
                                                        <option value="{{ $category->id }}" {{ $expense->expense_category_id == $category->id ? 'selected' : '' }}>
                                                            {{ $category->nombre }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </template>

                                        <!-- Concepto -->
                                        <template x-if="!editing">
                                            <td class="px-6 py-4 font-medium text-gray-900">{{ $expense->concepto }}</td>
                                        </template>
                                        <template x-if="editing">
                                            <td class="px-6 py-4">
                                                <input type="text" name="concepto" form="edit-expense-{{ $expense->id }}" value="{{ $expense->concepto }}" required class="w-full text-sm">
                                            </td>
                                        </template>

                                        <!-- Monto -->
                                        <template x-if="!editing">
                                            <td class="px-6 py-4">
                                                <span class="badge">{{ $expense->moneda }}</span>
                                                <span class="ml-2 text-sm font-medium">{{ number_format($expense->monto, 2) }}</span>
                                            </td>
                                        </template>
                                        <template x-if="editing">
                                            <td class="px-6 py-4">
                                                <div class="flex gap-2">
                                                    <select name="moneda" form="edit-expense-{{ $expense->id }}" required class="text-sm">
                                                        <option value="{{ $trip->moneda_trabajo }}" {{ $expense->moneda == $trip->moneda_trabajo ? 'selected' : '' }}>{{ $trip->moneda_trabajo }}</option>
                                                        <option value="CLP" {{ $expense->moneda == 'CLP' ? 'selected' : '' }}>CLP</option>
                                                    </select>
                                                    <input type="number" name="monto" form="edit-expense-{{ $expense->id }}" value="{{ $expense->monto }}" required step="0.01" min="0" class="w-32 text-sm">
                                                </div>
                                            </td>
                                        </template>

                                        <!-- Monto CLP (solo vista) -->
                                        <td class="px-6 py-4 font-bold text-gray-900">${{ number_format($expense->monto_clp, 0) }}</td>

                                        <!-- Comprobante -->
                                        <template x-if="!editing">
                                            <td class="px-6 py-4 text-center">
                                                @if($expense->comprobante)
                                                    <button type="button" @click="$dispatch('open-image', { url: '{{ asset('storage/' . $expense->comprobante) }}' })"
                                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-100 text-green-600 hover:bg-green-200 transition">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </button>
                                                @else
                                                    <span class="text-gray-300">-</span>
                                                @endif
                                            </td>
                                        </template>
                                        <template x-if="editing">
                                            <td class="px-6 py-4 text-center">
                                                <input type="file" name="comprobante" form="edit-expense-{{ $expense->id }}" accept="image/jpeg,image/png,image/jpg,image/webp" class="text-xs">
                                                @if($expense->comprobante)
                                                    <label class="flex items-center gap-1 mt-1 text-xs text-gray-600">
                                                        <input type="checkbox" name="eliminar_comprobante" form="edit-expense-{{ $expense->id }}" value="1" class="rounded">
                                                        Eliminar actual
                                                    </label>
                                                @endif
                                            </td>
                                        </template>

                                        <!-- Acciones -->
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <!-- Modo Normal -->
                                                <template x-if="!editing">
                                                    <div class="flex items-center gap-2">
                                                        @if($trip->estado === 'activo')
                                                            <button @click="editing = true" class="text-blue-600 hover:text-blue-700 transition">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                </svg>
                                                            </button>
                                                        @endif
                                                        <form method="POST" action="{{ route('trips.expenses.destroy', [$trip, $expense]) }}" class="inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" onclick="return confirm('¿Eliminar este gasto?')"
                                                                class="text-red-600 hover:text-red-700 transition">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </template>
                                                <!-- Modo Edición -->
                                                <template x-if="editing">
                                                    <div class="flex items-center gap-2">
                                                        <button type="submit" form="edit-expense-{{ $expense->id }}" class="text-green-600 hover:text-green-700 transition">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                        </button>
                                                        <button @click="editing = false" class="text-gray-600 hover:text-gray-700 transition">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                            <!-- Formulario oculto para edición -->
                                            <form id="edit-expense-{{ $expense->id }}" method="POST" action="{{ route('trips.expenses.update', [$trip, $expense]) }}" enctype="multipart/form-data" class="hidden">
                                                @csrf
                                                @method('PUT')
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <!-- Tab Content: Productos -->
        <div x-show="tab === 'products'" x-cloak class="animate-fadeIn space-y-6">
            @if($trip->estado === 'activo')
            <div class="card">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Agregar Producto</h3>

                <form method="POST" action="{{ route('trips.products.store', $trip) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nombre</label>
                            <input type="text" name="nombre" required class="w-full" placeholder="Ej: Polera">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Moneda</label>
                            <select name="moneda" required class="w-full">
                                <option value="{{ $trip->moneda_trabajo }}" selected>{{ $trip->moneda_trabajo }}</option>
                                <option value="CLP">CLP</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cantidad</label>
                            <input type="number" name="cantidad" required value="1" min="1" class="w-full">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Costo Unitario</label>
                            <input type="number" name="costo_unitario" required step="0.01" min="0" class="w-full" placeholder="0.00">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Imagen (opcional)</label>
                        <input type="file" name="imagen" accept="image/jpeg,image/png,image/jpg,image/webp" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                        <p class="mt-1 text-xs text-gray-500">JPG, PNG o WEBP. Máximo 2MB.</p>
                    </div>
                    <button type="submit" class="btn btn-primary w-full sm:w-auto">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Agregar Producto
                    </button>
                </form>
            </div>
            @endif

            <!-- Lista de productos -->
            @if($trip->products->isEmpty())
                <div class="card text-center py-12">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    <p class="text-gray-600">No hay productos registrados</p>
                </div>
            @else
                <div class="card overflow-hidden p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Producto</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Cant.</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Costo Unit.</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Subtotal</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Gastos Prorr.</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Costo Final</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Img.</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($trip->products as $product)
                                    <tr x-data="{ editing: false }" class="hover:bg-gray-50 transition">
                                        <!-- Nombre -->
                                        <template x-if="!editing">
                                            <td class="px-4 py-4 font-medium text-gray-900">{{ $product->nombre }}</td>
                                        </template>
                                        <template x-if="editing">
                                            <td class="px-4 py-4">
                                                <input type="text" name="nombre" form="edit-product-{{ $product->id }}" value="{{ $product->nombre }}" required class="w-full text-sm">
                                            </td>
                                        </template>

                                        <!-- Cantidad -->
                                        <template x-if="!editing">
                                            <td class="px-4 py-4">{{ $product->cantidad }}</td>
                                        </template>
                                        <template x-if="editing">
                                            <td class="px-4 py-4">
                                                <input type="number" name="cantidad" form="edit-product-{{ $product->id }}" value="{{ $product->cantidad }}" required min="1" class="w-20 text-sm">
                                            </td>
                                        </template>

                                        <!-- Costo Unitario -->
                                        <template x-if="!editing">
                                            <td class="px-4 py-4">{{ $product->moneda }} {{ number_format($product->costo_unitario, 2) }}</td>
                                        </template>
                                        <template x-if="editing">
                                            <td class="px-4 py-4">
                                                <div class="flex gap-2">
                                                    <select name="moneda" form="edit-product-{{ $product->id }}" required class="text-sm">
                                                        <option value="{{ $trip->moneda_trabajo }}" {{ $product->moneda == $trip->moneda_trabajo ? 'selected' : '' }}>{{ $trip->moneda_trabajo }}</option>
                                                        <option value="CLP" {{ $product->moneda == 'CLP' ? 'selected' : '' }}>CLP</option>
                                                    </select>
                                                    <input type="number" name="costo_unitario" form="edit-product-{{ $product->id }}" value="{{ $product->costo_unitario }}" required step="0.01" min="0" class="w-28 text-sm">
                                                </div>
                                            </td>
                                        </template>

                                        <!-- Subtotal (solo vista) -->
                                        <td class="px-4 py-4">{{ $product->moneda }} {{ number_format($product->costo_unitario * $product->cantidad, 2) }}</td>

                                        <!-- Gastos Prorrateados (solo vista) -->
                                        <td class="px-4 py-4">{{ $product->moneda }} {{ number_format($product->gastos_prorrateados_clp / ($product->moneda === 'CLP' ? 1 : $trip->tipo_cambio), 2) }}</td>

                                        <!-- Costo Final (solo vista) -->
                                        <td class="px-4 py-4 font-bold text-primary-600">${{ number_format($product->costo_final_unitario_clp, 0) }} CLP</td>

                                        <!-- Imagen -->
                                        <template x-if="!editing">
                                            <td class="px-4 py-4 text-center">
                                                @if($product->imagen)
                                                    <button type="button" @click="$dispatch('open-image', { url: '{{ asset('storage/' . $product->imagen) }}' })"
                                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-100 text-green-600 hover:bg-green-200 transition">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </button>
                                                @else
                                                    <span class="text-gray-300">-</span>
                                                @endif
                                            </td>
                                        </template>
                                        <template x-if="editing">
                                            <td class="px-4 py-4 text-center">
                                                <input type="file" name="imagen" form="edit-product-{{ $product->id }}" accept="image/jpeg,image/png,image/jpg,image/webp" class="text-xs">
                                                @if($product->imagen)
                                                    <label class="flex items-center gap-1 mt-1 text-xs text-gray-600">
                                                        <input type="checkbox" name="eliminar_imagen" form="edit-product-{{ $product->id }}" value="1" class="rounded">
                                                        Eliminar actual
                                                    </label>
                                                @endif
                                            </td>
                                        </template>

                                        <!-- Acciones -->
                                        <td class="px-4 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <!-- Modo Normal -->
                                                <template x-if="!editing">
                                                    <div class="flex items-center gap-2">
                                                        @if($trip->estado === 'activo')
                                                            <button @click="editing = true" class="text-blue-600 hover:text-blue-700 transition">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                </svg>
                                                            </button>
                                                        @endif
                                                        <form method="POST" action="{{ route('trips.products.destroy', [$trip, $product]) }}" class="inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" onclick="return confirm('¿Eliminar este producto?')"
                                                                class="text-red-600 hover:text-red-700 transition">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </template>
                                                <!-- Modo Edición -->
                                                <template x-if="editing">
                                                    <div class="flex items-center gap-2">
                                                        <button type="submit" form="edit-product-{{ $product->id }}" class="text-green-600 hover:text-green-700 transition">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                        </button>
                                                        <button @click="editing = false" class="text-gray-600 hover:text-gray-700 transition">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                            <!-- Formulario oculto para edición -->
                                            <form id="edit-product-{{ $product->id }}" method="POST" action="{{ route('trips.products.update', [$trip, $product]) }}" enctype="multipart/form-data" class="hidden">
                                                @csrf
                                                @method('PUT')
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <!-- Tab Content: Precio Venta -->
        <div x-show="tab === 'sales'" x-cloak class="animate-fadeIn space-y-6">
            @if($trip->products->isEmpty())
                <div class="card text-center py-12">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    <p class="text-gray-600">No hay productos registrados</p>
                    <p class="text-sm text-gray-500 mt-2">Agrega productos en la pestaña "Productos" para poder establecer precios de venta</p>
                </div>
            @else
                <!-- Tabla de precios de venta -->
                <div class="card overflow-hidden p-0">
                    <div class="bg-gradient-to-r from-primary-50 to-blue-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Gestión de Precios de Venta</h3>
                        <p class="text-sm text-gray-600 mt-1">Define el precio de venta para cada producto y visualiza las ganancias proyectadas</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Producto</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Cantidad</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Costo Unit.</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Costo Total</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Precio Venta</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Ingreso Total</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Ganancia</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Margen %</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @php
                                    $total_costo_real = 0;
                                    $total_ingresos = 0;
                                @endphp
                                @foreach($trip->products as $product)
                                    @php
                                        $costo_real_total = $product->costo_final_unitario_clp * $product->cantidad;
                                        $ingreso_total = $product->precio_venta ? ($product->precio_venta * $product->cantidad) : 0;
                                        $ganancia = $ingreso_total - $costo_real_total;
                                        $margen = $costo_real_total > 0 ? (($ganancia / $costo_real_total) * 100) : 0;

                                        $total_costo_real += $costo_real_total;
                                        $total_ingresos += $ingreso_total;
                                    @endphp
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-4 font-medium text-gray-900">{{ $product->nombre }}</td>
                                        <td class="px-4 py-4 text-center text-gray-700">{{ $product->cantidad }}</td>
                                        <td class="px-4 py-4 text-right text-gray-700">${{ number_format($product->costo_final_unitario_clp, 0, ',', '.') }}</td>
                                        <td class="px-4 py-4 text-right font-semibold text-gray-900">${{ number_format($costo_real_total, 0, ',', '.') }}</td>
                                        <td class="px-4 py-4 text-right">
                                            <form method="POST" action="{{ route('trips.products.update-precio-venta', [$trip, $product]) }}" class="flex items-center justify-end gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <span class="text-gray-500 text-sm">$</span>
                                                <input type="number"
                                                    name="precio_venta"
                                                    value="{{ $product->precio_venta ? intval($product->precio_venta) : '' }}"
                                                    step="1"
                                                    min="0"
                                                    class="w-28 text-right border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 text-sm"
                                                    placeholder="0">
                                                <button type="submit" class="px-3 py-1.5 bg-primary-500 text-white text-xs rounded-lg hover:bg-primary-600 transition">
                                                    Guardar
                                                </button>
                                            </form>
                                        </td>
                                        <td class="px-4 py-4 text-right font-semibold text-blue-600">${{ number_format($ingreso_total, 0, ',', '.') }}</td>
                                        <td class="px-4 py-4 text-right font-bold {{ $ganancia >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $ganancia >= 0 ? '+' : '' }}${{ number_format($ganancia, 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $margen >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $margen >= 0 ? '+' : '' }}{{ number_format($margen, 1, ',', '.') }}%
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gradient-to-r from-gray-50 to-gray-100 border-t-2 border-gray-300">
                                @php
                                    $total_ganancia = $total_ingresos - $total_costo_real;
                                    $total_margen = $total_costo_real > 0 ? (($total_ganancia / $total_costo_real) * 100) : 0;
                                @endphp
                                <tr>
                                    <td colspan="3" class="px-4 py-4 text-right font-bold text-gray-900">TOTALES:</td>
                                    <td class="px-4 py-4 text-right font-bold text-gray-900 text-lg">${{ number_format($total_costo_real, 0, ',', '.') }}</td>
                                    <td class="px-4 py-4"></td>
                                    <td class="px-4 py-4 text-right font-bold text-blue-600 text-lg">${{ number_format($total_ingresos, 0, ',', '.') }}</td>
                                    <td class="px-4 py-4 text-right font-bold text-lg {{ $total_ganancia >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $total_ganancia >= 0 ? '+' : '' }}${{ number_format($total_ganancia, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-bold {{ $total_margen >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $total_margen >= 0 ? '+' : '' }}{{ number_format($total_margen, 1, ',', '.') }}%
                                        </span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Tarjetas de resumen de ventas -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="card bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-blue-600 mb-1">Inversión Total</p>
                                <p class="text-2xl font-bold text-blue-900">${{ number_format($total_costo_real, 0, ',', '.') }}</p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-blue-500 flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="card bg-gradient-to-br from-green-50 to-green-100 border-green-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-green-600 mb-1">Ingresos Proyectados</p>
                                <p class="text-2xl font-bold text-green-900">${{ number_format($total_ingresos, 0, ',', '.') }}</p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-green-500 flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="card bg-gradient-to-br {{ $total_ganancia >= 0 ? 'from-emerald-50 to-emerald-100 border-emerald-200' : 'from-red-50 to-red-100 border-red-200' }}">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium {{ $total_ganancia >= 0 ? 'text-emerald-600' : 'text-red-600' }} mb-1">
                                    {{ $total_ganancia >= 0 ? 'Ganancia' : 'Pérdida' }} Neta
                                </p>
                                <p class="text-2xl font-bold {{ $total_ganancia >= 0 ? 'text-emerald-900' : 'text-red-900' }}">
                                    {{ $total_ganancia >= 0 ? '+' : '' }}${{ number_format($total_ganancia, 0, ',', '.') }}
                                </p>
                                <p class="text-xs {{ $total_ganancia >= 0 ? 'text-emerald-700' : 'text-red-700' }} mt-1">
                                    Margen: {{ $total_margen >= 0 ? '+' : '' }}{{ number_format($total_margen, 1, ',', '.') }}%
                                </p>
                            </div>
                            <div class="w-12 h-12 rounded-full {{ $total_ganancia >= 0 ? 'bg-emerald-500' : 'bg-red-500' }} flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Tab Content: Resumen -->
        <div x-show="tab === 'summary'" x-cloak class="animate-fadeIn space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="card text-center">
                    <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <p class="text-sm text-gray-600 mb-1">Total Gastos</p>
                    <p class="text-2xl font-bold text-gray-900">${{ number_format($estadisticas['total_gastos'], 0) }}</p>
                </div>
                <div class="card text-center">
                    <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    <p class="text-sm text-gray-600 mb-1">Total Productos</p>
                    <p class="text-2xl font-bold text-gray-900">${{ number_format($estadisticas['total_productos'], 0) }}</p>
                </div>
                <div class="card text-center">
                    <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                        </svg>
                    </div>
                    <p class="text-sm text-gray-600 mb-1">Gastos Prorrateados</p>
                    <p class="text-2xl font-bold text-gray-900">${{ number_format($estadisticas['gastos_prorrateados'], 0) }}</p>
                </div>
                <div class="card text-center gradient-primary">
                    <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-sm text-white/90 mb-1">Costo Total</p>
                    <p class="text-2xl font-bold text-white">${{ number_format($estadisticas['costo_total'], 0) }}</p>
                </div>
            </div>

            <!-- Sección de Ventas -->
            @if(!$trip->products->isEmpty())
                @php
                    $total_costo_real = 0;
                    $total_ingresos = 0;
                    foreach($trip->products as $product) {
                        $total_costo_real += $product->costo_final_unitario_clp * $product->cantidad;
                        $total_ingresos += $product->precio_venta ? ($product->precio_venta * $product->cantidad) : 0;
                    }
                    $total_ganancia = $total_ingresos - $total_costo_real;
                    $total_margen = $total_costo_real > 0 ? (($total_ganancia / $total_costo_real) * 100) : 0;
                @endphp

                <div class="card bg-gradient-to-r from-slate-50 to-gray-50">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Análisis de Ventas</h3>
                        <button @click="setTab('sales')" class="text-sm text-primary-600 hover:text-primary-700 font-medium flex items-center gap-1">
                            Ver detalle
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white rounded-lg p-4 border border-blue-200">
                            <p class="text-xs font-medium text-blue-600 mb-1">Inversión Total</p>
                            <p class="text-xl font-bold text-blue-900">${{ number_format($total_costo_real, 0, ',', '.') }}</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 border border-green-200">
                            <p class="text-xs font-medium text-green-600 mb-1">Ingresos Proyectados</p>
                            <p class="text-xl font-bold text-green-900">${{ number_format($total_ingresos, 0, ',', '.') }}</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 border {{ $total_ganancia >= 0 ? 'border-emerald-200' : 'border-red-200' }}">
                            <p class="text-xs font-medium {{ $total_ganancia >= 0 ? 'text-emerald-600' : 'text-red-600' }} mb-1">
                                {{ $total_ganancia >= 0 ? 'Ganancia' : 'Pérdida' }} Proyectada
                            </p>
                            <div class="flex items-end justify-between">
                                <p class="text-xl font-bold {{ $total_ganancia >= 0 ? 'text-emerald-900' : 'text-red-900' }}">
                                    {{ $total_ganancia >= 0 ? '+' : '' }}${{ number_format($total_ganancia, 0, ',', '.') }}
                                </p>
                                <span class="text-xs font-semibold {{ $total_ganancia >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                    {{ $total_margen >= 0 ? '+' : '' }}{{ number_format($total_margen, 1, ',', '.') }}%
                                </span>
                            </div>
                        </div>
                    </div>

                    @if($total_ingresos == 0)
                        <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                            <p class="text-xs text-amber-800">
                                <strong>Nota:</strong> Define precios de venta en la pestaña "Precio Venta" para ver análisis de rentabilidad.
                            </p>
                        </div>
                    @endif
                </div>
            @endif

            <div class="card">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Detalle de Prorrateo</h3>
                @if($trip->products->isEmpty() || $trip->expenses->isEmpty())
                    <x-alert type="info">
                        Agrega gastos y productos para ver el análisis de prorrateo.
                    </x-alert>
                @else
                    <x-alert type="success">
                        <strong>Distribución:</strong> Se han distribuido ${{ number_format($estadisticas['total_gastos'], 0) }} en gastos entre {{ $trip->products->count() }} producto(s) según su valor proporcional.<br>
                        <strong>Método:</strong> Cada producto recibe gastos según su porcentaje del total de compras.
                    </x-alert>
                @endif
            </div>
        </div>
    </div>

    <!-- Modal de visualización de comprobante -->
    <div x-data="{ open: false, imageUrl: '' }"
         @open-image.window="open = true; imageUrl = $event.detail.url"
         x-show="open"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="open = false">
        <!-- Overlay -->
        <div class="fixed inset-0 bg-black/70 transition-opacity"
             @click="open = false"
             x-show="open"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"></div>

        <!-- Modal Content -->
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-xl max-w-4xl w-full p-4 animate-scaleIn"
                 x-show="open"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 transform scale-100"
                 x-transition:leave-end="opacity-0 transform scale-95"
                 @click.stop>

                <!-- Header -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Comprobante</h3>
                    <button @click="open = false" class="p-2 hover:bg-gray-100 rounded-lg transition">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Image -->
                <div class="bg-gray-50 rounded-xl overflow-hidden">
                    <img :src="imageUrl" alt="Comprobante" class="w-full h-auto max-h-[70vh] object-contain">
                </div>

                <!-- Actions -->
                <div class="mt-4 flex justify-end gap-2">
                    <a :href="imageUrl" target="_blank" download class="btn btn-secondary text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Descargar
                    </a>
                    <button @click="open = false" class="btn btn-primary text-sm">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-modern>
