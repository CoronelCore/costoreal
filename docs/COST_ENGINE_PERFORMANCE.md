# Motor de costeo: memoria, persistencia y compatibilidad

## Representación y precisión

El algoritmo sigue siendo `largest-remainder-clp-v1`. Los resultados, orden natural de IDs, residuos, prioridades, premios y todos los campos de trazabilidad mantienen el contrato v1. No cambia el redondeo ni la política financiera.

AllocationBuffer comprime bloques de 256 asignaciones JSON con zlib y entrega una AllocationSequence inmutable, iterable, contable y con acceso por índice. La iteración expande un solo bloque; puede repetirse. No usa archivos, DB ni I/O en el dominio. Conserva también repartos de cero. La representación total crece con el número de asignaciones comprimidas; no promete memoria constante para cualquier volumen ilimitado.

AllocationBasis comparte bases de reparto entre gastos, con una caché limitada a cuatro bases. CostEngine reutiliza hasta dieciséis planes idénticos de base/importe dentro de un cálculo de hasta 1.000 líneas, conservando la identidad y traza de cada gasto. Por encima de ese tamaño usa el recorrido anterior. La división usa enteros escalados solo cuando pesos, suma y cada multiplicación caben en PHP_INT_MAX. Los casos restantes usan Brick\Math con precisión arbitraria. Las escalas decimales de las trazas permanecen idénticas. DecimalSum acumula valores exactos y evita crear un objeto decimal por cada suma común; no incorpora floats.

CostResult::summary() expone únicamente cifras. `storageData()` añade líneas y `storage_version=2`, `allocation_source=cost_allocations`, `allocation_count`. `toArray()` conserva el contrato completo anterior para consumidores explícitos y pruebas pequeñas: materializa todas las asignaciones y no debe usarse en rutas de gran volumen. Importación de productos y comparación de costos usan summary().

## Escritura e históricos

RecordCostRun delega la escritura a WriteAllocations. Con SQLite/JSONB disponible, consume JSON de hasta veinte bloques (5.120 filas), materializa el parseo nativo solo una vez por fila y conserva trace como texto JSON. Se envían dos parámetros por lote, sin rehidratar todas las trazas en PHP. Arrays anteriores y drivers sin JSONB utilizan los inserts portables de hasta 5.000 filas, acotados al límite real del driver. El helper consulta MAX_VARIABLE_NUMBER, aplica margen y limita el payload transitorio. Los upserts de productos/gastos se acotan a 500 filas según columnas. Solo SQLite se ensayó realmente; los límites de otros drivers no certifican su compatibilidad integral.

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


## Optimización de escritura y reutilización acotada · 4 de octubre de 2026

CalculationMemo retiene un solo CostResult inmutable en el scope de una solicitud/job. La clave incorpora todas las entradas del motor; convertir/validar tasas sigue ocurriendo antes de consultar el memo. No guarda modelos, nombres, actores, permisos ni snapshots. El cierre puede reutilizar su cálculo de revisión dentro de la misma transacción, pero sigue comparando los importes originales antes de escribir. Cada scope nuevo obtiene otro memo. Un cálculo inválido no publica una nueva clave/result.

SQLite 3.53.4 del runtime actual soporta la ruta JSONB. Se utiliza solo como representación transitoria: no cambia schema, archivos, algoritmo, trace persistida ni resultados antiguos. [La documentación de SQLite](https://www.sqlite.org/json1.html) describe JSONB y las funciones de extracción; versiones sin jsonb conservan el escritor portable. La comprobación de capacidad solo absorbe la ausencia de esa función, no otros errores de DB. Todos los inserts utilizan la API normal de Laravel, por lo que eventos, alertas, errores y rollback de segunda tanda permanecen activos. No se incorpora un acceso PDO alternativo.

El profiler ahora inicia un scope nuevo por operación para evitar medir un memo calentado por operaciones CLI anteriores. Añade el modo varied, con trece importes alternos que superan la capacidad del caché de planes. El pico registrado es heap rastreado por PHP con memory_limit=128M, no RSS total ni memoria nativa del driver. No se certifica memoria residente del proceso mediante ese contador.

Tres fixtures máximas nuevas, seriales; comparación con una línea base anterior del mismo runtime. Cada fixture conserva las 500.000 trazas por versión y ambas versiones al cerrar. Hash completo contra ReferenceCostEngine y DB, rollback después del segundo lote, integridad/FK e inmutabilidad pasan.

| Operación | Antes ms | Mediana actual ms | Pico heap PHP MiB | Consultas actuales |
|---|---:|---:|---:|---:|
| complete_engine_preview | 1518.66 | 900.94 | 48 | 2 |
| atomic_mutation_and_trace_recording | 6808.56 | 3772.25 | 68.5 | 142 |
| stored_cost_comparison | 1651.76 | 990.18 | 70.5 | 3 |
| importation_review | 1670.57 | 1020.31 | 70.5 | 4 |
| scenario_simulation | 3234.15 | 1943.17 | 74.5 | 3 |
| atomic_close_and_trace_recording | 8400.50 | 4114.65 | 80.5 | 133 |

El modo varied no obtiene la mejora del caché de planes: preview 1.550 ms, guardar 4.624 ms, revisión 1.686 ms, escenario 3.297 ms y cierre 4.625 ms en una fixture nueva. Estos son tiempos de servicios/acciones CLI, no p95 HTTP. Los casos 10/100 también concilian: guardar 17/85 ms y cerrar 10/85 ms en una muestra nueva por caso.

CR-040 sigue abierto. No se eleva el presupuesto de 2.500 ms: el máximo sigue excediéndolo. Quedan perfil HTTP por ruta con scopes reales, optimización restante y estados visibles durante operaciones largas. Checkpoint privado work/backups/cost-write-checkpoint; sin migraciones ni cambios de dependencias.

Validación final de esta entrega: 265 pruebas PHP / 5.093 aserciones; siete nuevas regresiones sobre batching JSON, planes repetidos, scopes/invalidez, codec exacto y pretend. Pint pasa. El test máximo HTTP conserva dos versiones y un millón de trazas a memory_limit=128M; no es un perfil de latencia HTTP.


## Revisión puntual del guardado y cierre · 4 de octubre de 2026

La escritura usa jsonb_each en SQLite >= 3.51 para recorrer valores binarios directamente, con un CTE no materializado. Evita convertir cada fila a texto y volver a analizarla. SQLite con JSONB anterior mantiene la ruta materializada; los demás drivers y versiones sin JSONB mantienen el escritor portable. La detección consulta versión y capacidad a la vez, sin consultas adicionales por lote. JSONB sigue siendo solo transporte: trace persistida, schema y atomicidad conservan su formato y comportamiento. [Referencia de SQLite sobre jsonb_each](https://www.sqlite.org/json1.html#jeach).

El caché local de planes pasa de cuatro a dieciséis entradas, limitado a cálculos de hasta 1.000 líneas. Las claves siguen incluyendo base y monto completos, con expulsión acotada e identidad/traza propias por gasto. No se cambia la aritmética ni se comparten resultados entre solicitudes.

Comprobación HTTP puntual, serial y con DB sintéticas nuevas: tres muestras medidas por ruta/modo, además de dos pares de calentamiento; 1.000 líneas y 500 gastos. No se informa p95 con este tamaño de muestra. Comparación orientativa con las medianas del perfil previo de veinte muestras del mismo runtime:

| Caso | Ruta | Mediana anterior ms | Mediana puntual ms |
|---|---|---:|---:|
| Importes iguales | Guardar gasto | 3714.49 | 3200.57 |
| Importes iguales | Cerrar | 3912.065 | 3369.48 |
| Trece importes alternos | Guardar gasto | 4412.395 | 3275.96 |
| Trece importes alternos | Cerrar | 4590.975 | 3473.47 |

Consultas constantes: 148 al guardar y 141 al cerrar; heap PHP máximo 30 MiB / 42 MiB según modo, bajo memory_limit=128M. Todas las respuestas concilian totales y conteos completos por gasto/versión, con integridad/FK y primera versión inmutable. El presupuesto máximo de 2.500 ms todavía se excede: no se modifica ni se presenta como resuelto. Esta mejora reduce la espera un 14–26 % en las muestras locales; no garantiza esa latencia en otras máquinas.

Validación acotada: diez pruebas existentes del codec, persistencia y rollback (289 aserciones), incluyendo la ruta JSONB antigua y portable, tipos/build y Pint. La prueba de planes expulsados incorpora treinta importes distintos para superar el nuevo límite. El editor de precio recibe foco al abrir y retorna al disparador al cancelar, pulsar Escape o completar el guardado; comprobados apertura de dos líneas, Cancelar y Escape en la sesión QA, sin guardar cambios. Los datos de dominio de personal/QA y sus comprobaciones de integridad siguen intactos. Evidencia local: outputs/verificacion-ajustes-costoreal.json.
