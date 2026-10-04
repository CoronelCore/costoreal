# Motor de costeo: memoria, persistencia y compatibilidad

## Representación y precisión

El algoritmo sigue siendo `largest-remainder-clp-v1`. Los resultados, orden natural de IDs, residuos, prioridades, premios y todos los campos de trazabilidad mantienen el contrato v1. No cambia el redondeo ni la política financiera.

AllocationBuffer comprime bloques de 256 asignaciones JSON con zlib y entrega una AllocationSequence inmutable, iterable, contable y con acceso por índice. La iteración expande un solo bloque; puede repetirse. No usa archivos, DB ni I/O en el dominio. Conserva también repartos de cero. La representación total crece con el número de asignaciones comprimidas; no promete memoria constante para cualquier volumen ilimitado.

AllocationBasis comparte bases de reparto entre gastos, con una caché limitada a cuatro bases. La división usa enteros escalados solo cuando pesos, suma y cada multiplicación caben en PHP_INT_MAX. Los casos restantes usan Brick\Math con precisión arbitraria. Las escalas decimales de las trazas permanecen idénticas. DecimalSum acumula valores exactos y evita crear un objeto decimal por cada suma común; no incorpora floats.

CostResult::summary() expone únicamente cifras. `storageData()` añade líneas y `storage_version=2`, `allocation_source=cost_allocations`, `allocation_count`. `toArray()` conserva el contrato completo anterior para consumidores explícitos y pruebas pequeñas: materializa todas las asignaciones y no debe usarse en rutas de gran volumen. Importación de productos y comparación de costos usan summary().

## Escritura e históricos

RecordCostRun recorre la secuencia y guarda lotes de hasta 5.000 filas, limitados por los parámetros admitidos por el driver. El helper consulta MAX_VARIABLE_NUMBER de SQLite, aplica margen y limita el payload transitorio. Los upserts de productos/gastos se acotan a 100 filas según columnas. Solo SQLite se ensayó realmente; los límites de otros drivers no certifican su compatibilidad integral.

La misma transacción guarda cost_run, todas sus asignaciones y proyecciones. Un fallo después de guardar el segundo lote revierte datos, revisión, versión, trazas, auditoría y proyecciones. No aparece una versión parcial. Los cost_runs anteriores con allocations dentro del JSON se leen sin reescribirlos. No hay migración de datos ni schema nuevo; `.env`, credenciales, DB en uso y documentos se preservan. Composer declara ext-zlib; no cambian las versiones de paquetes.

## Evidencia y reproducción

245 pruebas PHP / 4.945 aserciones. ReferenceCostEngine es una copia congelada de la implementación anterior, utilizada exclusivamente como oráculo de pruebas. Sesenta casos con reglas por valor, unidades, peso, volumen, destino válido/inválido, fracciones, escalas, IDs naturales y valores que exceden enteros comparan el payload completo. La matriz anterior de CostEngineTest se conserva. Los límites de bloque, repetición, inmutabilidad y JSON histórico tienen cobertura propia.

MaximumCostRunTest ejecuta una actualización de gasto y un cierre por HTTP en un proceso aislado con 128 MiB: 1.000 productos × 500 gastos, costo exacto 10.501 CLP, revisiones 2/3, dos versiones completas y un millón de cost_allocations. La versión inicial permanece idéntica y los costos por línea concilian. Esta prueba verifica comportamiento y memoria; su duración de suite no es una medición de latencia HTTP.

En un checkout sin configuración cacheada y con dependencias de desarrollo:

```sh
bin/php -d memory_limit=128M tools/profile-costing.php 10 > perfil-costeo-10.json
bin/php -d memory_limit=128M tools/profile-costing.php 100 > perfil-costeo-100.json
bin/php -d memory_limit=128M tools/profile-costing.php 1000 > perfil-costeo-1000.json
```

El script siempre crea su propio directorio privado 0700/DB 0600 y discos sintéticos; no acepta una ruta existente. Hace migrate:fresh únicamente sobre su archivo recién creado tras verificar su nombre. Desconecta y elimina el temporal al terminar y en excepciones capturables; una terminación fatal del proceso puede requerir limpieza del temporal identificado. No usa credenciales reales ni imprime contraseñas/SQL/bindings. Stdout es JSON, stderr progreso sanitizado; error devuelve exit code 1 después de limpiar.

Comprueba preview, mutación atómica, comparación, revisión, escenario sin escrituras, cierre y fallo inyectado después del segundo lote. Un SHA-256 secuencial compara cada campo de las 500.000 asignaciones con el motor congelado y las filas persistidas. Revisa integridad/FK e inmutabilidad. La DB sintética de dos versiones completas ocupa unos 251 MB: cada versión mantiene todas las trazas. No se purga ni modifica el historial personal para reducir espacio.

## Límites vigentes

El motor puro máximo antes abortaba a unos 126 MiB; ahora completa unas 500.000 asignaciones en 1,49 s y 14 MiB en un proceso sin Laravel. El perfil con Laravel tiene una línea base diferente: preview ~46 MiB; mutación/revisión/comparación ~74,5 MiB; escenario ~78,5 MiB; cierre ~82,5 MiB. El pico es el del proceso, con baseline retenida y contador reiniciado por operación en el profiler; no es memoria incremental.

Guardar y cerrar el máximo todavía tardan varios segundos, y revisión/escenario superan los presupuestos iniciales. No se eleva el límite de memoria ni el presupuesto para ocultarlo. CR-040 continúa abierto para perfiles HTTP seriales por ruta y optimización de escrituras/relecturas, mediciones de alertas y comportamiento visual durante la espera. Los ensayos CLI miden servicios/acciones, sin transporte/sesión HTTP y sin p95 ni carga concurrente. Valores finales de las muestras y alcance se guardan en outputs/verificacion-motor-costoreal.json en el expediente local.

## Tres fixtures nuevas: mediana CLI, caso 1000/500

| Operación | Mediana ms | Pico máximo del proceso MiB |
|---|---:|---:|
| complete_engine_preview | 1543.77 | 46 |
| atomic_mutation_and_trace_recording | 6621.72 | 74.5 |
| stored_cost_comparison | 1647.04 | 74.5 |
| importation_review | 1664.73 | 74.5 |
| scenario_simulation | 3262.51 | 78.5 |
| atomic_close_and_trace_recording | 8492.64 | 82.5 |
