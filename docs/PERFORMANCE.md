# Rendimiento y observación local

## Qué se verificó

Perfil HTTP serial sobre PHP 8.5.8/Laravel 13, servidor local de desarrollo y SQLite. Cada caso tiene dos calentamientos y veinte respuestas medidas; el p95 empírico es la muestra 19 ordenada. Incluye bootstrap, transporte local, autorización y sesiones persistidas. No equivale a un SLA de producción, a una prueba de carga concurrente ni a medir la interacción del navegador.

Matriz de control y analítica: 1/20/200 importaciones × 10/100/1000 productos por importación. Cada importación tiene un gasto por cada dos productos, totales sintéticos fraccionarios y 50% de cobertura de precios. Además: detalle, empresa (200 importaciones), historial con 60 snapshots, listas vacías de auditoría/escenarios y exportaciones Excel/PDF. Las métricas corresponden al filtro completo. Los historiales se paginan sin hidratar los snapshots. Las listas vacías no certifican paginación de grandes auditorías/escenarios.

Con 200 × 1000, control pasó de mediana 18.505,60 ms / p95 18.574,65 ms a 120,91 ms / 122,72 ms. Empresa quedó en 100,58 ms / 103,44 ms. Analítica quedó en 1.377,03 ms / 1.413,92 ms. Consultas constantes: control 8, analítica 9, detalle 17, empresa 9, historial 8, auditoría/escenarios 6, exportaciones 9. Ocasionalmente se agrega una consulta por la limpieza aleatoria de sesiones de Laravel; esa variación no depende de las filas.

SQLite no creaba índices hijos por las claves foráneas. La migración `2026_10_04_070000_index_importation_children` agrega búsquedas por `trip_id` a products/expenses/documents/pricing_batches solo si no existe un índice equivalente. EXPLAIN confirma USING INDEX para los agregados de compra y gastos. Down elimina únicamente los índices nombrados por esta migración. No recalcula ni redondea registros.

El PDF HTML anterior agotaba 128 MiB con 1000 productos/500 gastos. FinancialPdf usa el Canvas vectorial de Dompdf ya instalado, genera filas por iteración y pagina encabezados/pies. No contiene un DOM con todas las filas. La primera versión midió unos 0,37 s / 24 MiB; el perfil final conserva la nueva presentación de importes grandes. Cada número permanece completo en su celda, con tamaño ajustado al ancho. Se destacan costo del lote y total de productos; nombres/descripciones se ajustan a varias líneas, sin interpretar HTML. Se preservan las centésimas de datos heredados, cobertura incompleta y pérdidas. PDF actual/histórico usa ReportTrip autorizado y FinancialSummaryData exacto. El PDF no es un documento etiquetado; para lectura accesible se mantiene la pantalla financiera. La plantilla Blade anterior se conserva como fixture de compatibilidad de pruebas; el endpoint usa FinancialPdf.

## Repetir el perfil sin tocar datos propios

Requiere dependencias PHP de desarrollo, Python 3 estándar y wrapper PHP 8.5. En un checkout sin configuración cacheada:

```sh
python3 tools/profile-http.py --samples 20 > perfil-http.json
python3 tools/profile-http.py --samples 3 --lines 10 > smoke-http.json
bin/php tools/profile-analytics.php > perfil-lectura.json
```

El perfil HTTP crea directorios privados dentro del temporal del sistema, genera una contraseña aleatoria exclusiva, migra DB sintéticas nuevas y levanta procesos en puertos libres de loopback. El helper rechaza un directorio ajeno al prefijo autorizado o una DB que ya existe. Al terminar detiene cada grupo de procesos y elimina sus DB, claves, logs y almacenamiento. Nunca apunta a la DB personal ni utiliza credenciales reales. El password no va en argumentos CLI ni en el informe. No borra config cache del proyecto; si existe, debe usarse un checkout de ensayo. Las muestras menores de veinte no presentan p95. JSON/stdout contiene resultados; stderr muestra progreso. Un caso fallido se registra y devuelve exit code distinto de cero.

Los totales, cobertura, histórico y bytes HTTP concilian en cada respuesta. Las firmas PDF/XLSX del perfil verifican transporte; la evidencia complementaria extrae sus filas e importes y revisa páginas renderizadas con Poppler. El perfil de lectura puro no incluye autenticación/sesiones/bootstrap HTTP; conservar ambos alcances separados.

## Presupuestos y alertas

`config/performance.php` contiene presupuestos operativos locales: lecturas 750 ms/20 consultas/1,5 MB, analítica 2000 ms y Excel/PDF 1500 ms. Escrituras 2500 ms/150 consultas son límites iniciales pendientes de certificar con perfil de mutaciones y costeo; no afirmar que esos volúmenes ya pasan. Una respuesta pequeña no demuestra un cálculo eficiente. Algunas rutas de alta/catálogos/configuración quedan fuera del mapa actual.

ObservePerformance se antepone a las sesiones web. RequestMetrics está scoped y reinicia sus contadores por solicitud; DB::listen cuenta eventos, sin retener consultas ni bindings. El registro privado incluye nombre de ruta, método, estado, duración del middleware, duración SQL, número de consultas, pico de memoria del proceso, bytes construidos, presupuesto y campos excedidos. No incluye actor, empresa, IDs/URL, querystring, SQL, tokens, payloads, nombres de documentos ni entradas financieras. El pico del proceso no atribuye memoria incremental a una solicitud en un worker persistente. Respuestas streamed usan Content-Length cuando está disponible; el middleware no mide transferencia de streaming ni render de UI.

Por defecto solo registra excesos, con una acción concreta para investigar. `COSTOREAL_PERFORMANCE_LOG_ALL=true` habilita muestreo de todas las rutas presupuestadas para un ensayo. `COSTOREAL_PERFORMANCE_ENABLED=false` lo desactiva. `COSTOREAL_PERFORMANCE_LOG_PATH` permite aislar el destino. Canal performance diario, JSON, retención siete días, permisos 0600 y escritura con lock. No muestra diagnósticos en headers ni props. Un fallo de escritura del log no convierte una operación completada en error. Un OOM fatal puede impedir el registro: revisar también stderr del proceso; no interpretar su ausencia como éxito.

Ante una alerta: repetir el mismo volumen con el profiler, identificar si cambió SQL, bytes o cálculo, comprobar planes de consultas, comparar totales y autorización y corregir la causa. No aumentar presupuesto ni memoria para silenciar una regresión. Estos logs son locales; integración con monitor externo y alertas distribuidas no está configurada.

## Trabajo pendiente

CR-021 satisface el criterio de consultas constantes y perfiles conocidos de control/analítica. El bloqueo de memoria del motor en 1000 productos/500 gastos se corrigió mediante secuencias comprimidas completas y persistencia por lotes: se preservan todas las asignaciones, residuos y trace; nuevos cost_runs referencian las filas atómicas de cost_allocations sin duplicarlas en result JSON. Ver docs/COST_ENGINE_PERFORMANCE.md para representación, compatibilidad, pruebas y perfiles propios. CR-040 sigue en curso: guardar/cerrar el caso máximo todavía excede 2500 ms y revisión/escenarios exceden el presupuesto inicial de lectura. El ensayo CLI no certifica p95 HTTP de estas rutas. QA visual global, release/restauración integral y aceptación siguen en sus tareas respectivas.


## Escrituras acotadas y scopes de cálculo · 4 de octubre de 2026

El runtime SQLite 3.53.4 recibe lotes JSONB transitorios de hasta 5.120 asignaciones mediante la API de Laravel. Se conservan todas las filas, trace textual, eventos de consultas y transacción. Fallback portable y upserts respetan parámetros del driver. Cálculos idénticos se reutilizan únicamente dentro del scope, con un solo resultado puro inmutable y clave de entradas completas; autorización, snapshots y auditoría siguen reconsultando el contexto.

En tres fixtures CLI nuevas de 1000/500, mediana de guardar 3.772 ms y cerrar 4.115 ms, frente a 6.809/8.401 de la línea base previa. No son p95 HTTP. Consultas 142/133 frente a 156/147; coste de escritura por lotes, sin una consulta por asignación. Cada fixture conserva/hash-verifica un millón de trazas en dos versiones y revierte el fallo de segunda tanda.

Caso alterno con trece importes diferentes: guardar/cerrar ~4.625 ms y escenario ~3.297 ms; sigue excediendo 2.500. Presupuestos/alertas vigentes no se aumentan. El contador process_peak_mb representa heap PHP rastreado; no certifica RSS o memoria nativa SQLite. Detalles/método en docs/COST_ENGINE_PERFORMANCE.md. CR-040 continúa para HTTP y optimización restante.
