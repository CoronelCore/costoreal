<x-modern>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('trips.index') }}" class="p-2 rounded-lg hover:bg-gray-100 transition">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Nueva Importación</h1>
                <p class="mt-1 text-sm text-gray-600">Configura los detalles de tu importación</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <form method="POST" action="{{ route('trips.store') }}" class="space-y-6">
            @csrf

            <!-- Información General -->
            <div class="card">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Información General</h2>
                        <p class="text-sm text-gray-600">Detalles básicos del viaje</p>
                    </div>
                </div>

                <div class="space-y-5">
                    <!-- Nombre -->
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">
                            Nombre de la Importación <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nombre" id="nombre" required
                            value="{{ old('nombre') }}"
                            class="w-full"
                            placeholder="Ej: Importación Brasil 2025">
                        @error('nombre')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Descripción -->
                    <div>
                        <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">
                            Descripción <span class="text-gray-400">(Opcional)</span>
                        </label>
                        <textarea name="descripcion" id="descripcion" rows="3"
                            class="w-full resize-none"
                            placeholder="Describe el propósito de la importación...">{{ old('descripcion') }}</textarea>
                        @error('descripcion')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Empresa -->
                    <div>
                        <label for="company_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Empresa <span class="text-gray-400">(Opcional)</span>
                        </label>
                        <select name="company_id" id="company_id" class="w-full">
                            <option value="">Sin asignar</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}"
                                    {{ old('company_id', $selectedCompanyId) == $company->id ? 'selected' : '' }}>
                                    {{ $company->nombre }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Asigna esta importación a una empresa para filtrarla después</p>
                        @error('company_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Fechas -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="fecha_inicio" class="block text-sm font-medium text-gray-700 mb-2">
                                Fecha de Inicio
                            </label>
                            <input type="date" name="fecha_inicio" id="fecha_inicio"
                                value="{{ old('fecha_inicio') }}"
                                class="w-full">
                            @error('fecha_inicio')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="fecha_fin" class="block text-sm font-medium text-gray-700 mb-2">
                                Fecha de Fin
                            </label>
                            <input type="date" name="fecha_fin" id="fecha_fin"
                                value="{{ old('fecha_fin') }}"
                                class="w-full">
                            @error('fecha_fin')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuración de Moneda -->
            <div class="card">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Configuración de Moneda</h2>
                        <p class="text-sm text-gray-600">Define la moneda de trabajo y tipo de cambio</p>
                    </div>
                </div>

                <x-alert type="info" class="mb-6">
                    <strong>Importante:</strong> La moneda base es <strong>CLP (Peso Chileno)</strong>. Selecciona la moneda extranjera que usarás en el viaje.
                </x-alert>

                <div class="space-y-5">
                    <!-- Moneda Base (readonly) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Moneda Base
                        </label>
                        <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <span class="badge badge-success">CLP</span>
                            <span class="text-sm font-medium text-gray-900">Peso Chileno</span>
                            <span class="ml-auto text-sm text-gray-600">1.00</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Moneda de Trabajo -->
                        <div>
                            <label for="moneda_trabajo" class="block text-sm font-medium text-gray-700 mb-2">
                                Moneda de la Importación <span class="text-red-500">*</span>
                            </label>
                            <select name="moneda_trabajo" id="moneda_trabajo" required class="w-full">
                                <option value="USD" {{ old('moneda_trabajo') === 'USD' ? 'selected' : '' }}>USD - Dólar Estadounidense</option>
                                <option value="BRL" {{ old('moneda_trabajo') === 'BRL' ? 'selected' : '' }}>BRL - Real Brasileño</option>
                                <option value="PEN" {{ old('moneda_trabajo') === 'PEN' ? 'selected' : '' }}>PEN - Sol Peruano</option>
                            </select>
                            @error('moneda_trabajo')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Tipo de Cambio -->
                        <div>
                            <label for="tipo_cambio" class="block text-sm font-medium text-gray-700 mb-2">
                                Tipo de Cambio (CLP) <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">$</span>
                                <input type="number" name="tipo_cambio" id="tipo_cambio"
                                    value="{{ old('tipo_cambio', '950.00') }}"
                                    step="0.01" min="0" required
                                    class="w-full pl-7"
                                    placeholder="950.00">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Cuántos pesos chilenos equivale 1 unidad de la moneda extranjera</p>
                            @error('tipo_cambio')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="flex items-center justify-end gap-3 pt-4">
                <a href="{{ route('trips.index') }}" class="btn btn-secondary">
                    Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Crear Importación
                </button>
            </div>
        </form>
    </div>
</x-modern>
