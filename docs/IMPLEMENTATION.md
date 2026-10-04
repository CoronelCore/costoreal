# Ejecución de la modernización de Costo Real

Objetivo: ejecutar el plan completo del 3 de octubre de 2026. Uso personal preparado para crecer; dirección financiera clara, elegante y compacta.

Las tareas se cierran por su criterio de aceptación. Los estados parciales identifican el trabajo que falta; las extensiones conservan sus activadores.

| Tarea | Estado | Evidencia / pendiente |
|---|---|---|
| CR-001 · Entorno y CI mínimo · Desarrollo | Verificado | Instalación limpia y setup repetido preservan clave; PHP 8.3/Node 24, CI configurada. CI remota aún no ejecutada. |
| CR-002 · Fixtures y restauración · Desarrollo/QA | Verificado | Copia personal y fixture QA con valores no cero restaurados: conteos/totales coinciden, integridad ok y cero violaciones FK. Ensayo DB/storage de release continúa en CR-041. |
| CR-003 · Política de costeo · Producto/contabilidad | Verificado | docs/COSTING_POLICY.md con ejemplos, estimaciones/pagos, precisión y legado. |
| CR-004 · Padre/hijo y empresa · Backend | Verificado | CoreIntegrityTest y PrivateDocumentTest: relaciones ajenas 404/403, cero escrituras/archivos y asignación de empresa atómica. |
| CR-005 · Categorías y analítica · Backend | Verificado | CoreIntegrityTest: categorías base protegidas, agregados autorizados y analítica SQLite. |
| CR-006 · Conversión coherente · Backend | Verificado | RateTest y VersionedCostingTest: tasas/monedas explícitas, pago histórico estable; ejemplo estimado 110.000. |
| CR-007 · Contrato de reportes · Backend/QA | Verificado | ExcelContractTest verifica XLSX numérico y texto literal; CoreIntegrityTest y reportes históricos validan campos, nulos y PDF. |
| CR-008 · Edición y cierre básico · Full stack | En curso | Edición real por Enter, multipart probado, cierre bloquea servidor. Falta smoke visual completo. |
| CR-009 · Fechas, dinero y móvil actual · Frontend | En curso | Dinero exacto/date-only probados; 390 px sin overflow. Falta recorrido 320 px. |
| CR-010 · Diagnóstico y parches · Desarrollo | En curso | Diagnósticos retirados, seeder seguro, Composer sin advisories. Npm conserva cinco altos de Tailwind 3 hasta migración. |
| CR-011 · Mapa de tareas y navegación · Diseño/Producto | En curso | Navegación financiera implementada; alta guiada aún pendiente. |
| CR-012 · Tokens y componentes · Diseño/Frontend | En curso | Tokens claro/oscuro y densidades implementados. Faltan mediciones completas de contraste y foco. |
| CR-013 · Pantallas y móvil · Diseño | En curso | Control/detalle/hoja/gastos/documentos/revisión implementados. Falta QA en todas las anchuras. |
| CR-014 · Acciones y AccessContext · Backend | En curso | Requests/AccessContext/MutateTrip y reportes/documentos autorizados. Falta completar separación de lecturas. |
| CR-015 · Money y tasas · Backend | Verificado | Money/Currency/Rate decimales, sin floats en dominio; RateTest y CostEngineTest. |
| CR-016 · Motor de reparto por valor · Backend | Verificado | CostEngineTest: 100 casos deterministas, fracciones, valores grandes, residuales, cero y reglas avanzadas. |
| CR-017 · Atomicidad y conflictos · Backend | Verificado | Rollback de datos/versiones/archivos y dos procesos SQLite reales probados, con CAS y reintento acotado. Revisión obligatoria en toda mutación de importación y mapa completo en asignación por lote; cinco pruebas de contrato y flujo de dos pestañas con comparación de origen/borrador/actual, resolución explícita y cambios remotos conservados. |
| CR-018 · Ejecuciones y snapshots · Backend | Verificado | VersionedCostingTest: cierre/reapertura mantienen snapshot inmutable y reportes históricos. |
| CR-019 · Auditoría y comparación · Backend/QA | En curso | Comparación de auditoría paginada bajo demanda, actor/resúmenes e inicial sin snapshots completos. Backfill de resúmenes reentrante probado. Falta comparación y backfill de costeo heredado. |
| CR-020 · DTOs y TypeScript · Full stack | Pendiente | — |
| CR-021 · Lecturas y consultas · Backend | En curso | Control mantiene cuatro consultas en 1/200 registros y analítica ocho en 1/20. Historial inicial paginado (20 resúmenes) sin columnas before/after; diferencias bajo demanda. Falta perfil de otras rutas. |
| CR-022 · Layout financiero · Frontend | En curso | Layout financiero, empresa/tema/densidad y menú móvil. Foco móvil pendiente de verificación tras último ajuste. |
| CR-023 · Control de importaciones · Full stack | En curso | Control paginado, filtros/métricas del conjunto completo y enlaces a pendientes probados. QA visual de vacíos/errores pendiente. |
| CR-024 · Detalle y resumen · Frontend | En curso | Show separado, resumen/cobertura/pérdidas y tabs persistidas. Falta verificar eventos tras última compilación. |
| CR-025 · Hoja editable · Full stack | Verificado | Edición por fila; tasas pagadas/fuente/fecha, peso/volumen; comparación de conflicto de tres versiones, resolución por campo y guardado posterior explícito. Pruebas API y frontend; Enter/Escape y error que conserva inputs verificados a 320 px. |
| CR-026 · Desglose de costo · Frontend | En curso | Panel de compra/tasa/gastos/versión disponible. Falta detalle de residual y prueba visual. |
| CR-027 · Formularios de gastos · Full stack | En curso | Formulario estimado/pagado y metadata; multipart con/sin archivo probado. Falta QA de preservación de errores. |
| CR-028 · Documentos privados · Backend/Frontend | En curso | Privacidad, metadatos, download/preview, papelera y restauración probados. Falta migración de archivos públicos heredados y política final de retención. |
| CR-029 · Alta y revisión guiadas · Full stack | En curso | Revisión/cierre/reapertura disponibles. Alta guiada y borrador recuperable pendientes. |
| CR-030 · QA visual y accesible · Diseño/QA | En curso | Evidencia desktop/390 px. Falta 320/1024, zoom, contraste, ambos temas y keyboard completo. |
| CR-031 · Pegado y CSV · Full stack | Verificado | Mapeo, vista previa por fila, política todo/nada, 100 filas conciliadas, reintento sin duplicados; 1.000 filas con 98 consultas. Flujo de pegado guardado en UI QA. |
| CR-032 · Reglas avanzadas de reparto · Full stack | En curso | Motor de unidades/peso/volumen/directo probado y formulario de gastos disponible. Metadata editable de productos existentes implementada; reglas necesitan todas las medidas y rechazan datos ausentes. Falta detalle del residual por producto en el desglose. |
| CR-033 · Precio por objetivo · Full stack | Verificado | Margen 20 % → 37.500 y recargo 5 % → 31.500; comisión/redondeo, preview puro, aplicación atómica por revisión. Flujo real QA a 320/1024 px. |
| CR-034 · Escenarios y revaluación · Full stack | Verificado | Preview puro, conservación separada e idempotente; promoción atómica por revisión, pagos preservados y precios/reglas alternativos. Diez pruebas y flujo UI real: 450.000 → 520.000, revisión 5 → 6; comparación original conservada. |
| CR-035 · Analítica renovada · Full stack | Pendiente | — |
| CR-036 · Historial y recuperación · Full stack | En curso | Papelera/restauración de importaciones con CAS, auditoría, costos/pagos/cierres/snapshots/documentos conservados; recorrido QA móvil real. Retención automática desactivada. Falta completar la política global y archivos heredados junto con CR-028. |
| CR-037 · Runtime y Laravel 13 · Desarrollo | En curso | PHP 8.3/Node 24 certificados en setup limpio. Laravel 13 pendiente. |
| CR-038 · Inertia y tooling · Desarrollo | Pendiente | — |
| CR-039 · Tailwind y limpieza · Frontend | Pendiente | — |
| CR-040 · Rendimiento y observación · Desarrollo/QA | En curso | Control 1/200 y escritura 1.000 líneas (64 consultas, 412 ms local) medidos. Faltan presupuestos por ruta y observación. |
| CR-041 · Ensayo de release y restauración · Desarrollo/QA | Pendiente | — |
| CR-042 · Aceptación con usuario · Producto/Diseño/QA | Pendiente | — |
| CR-043 · OCR asistido · Full stack | Condicional | Activador: captura manual repetitiva; cola y presupuesto. |
| CR-044 · Equipo y aprobaciones · Backend/Producto | Condicional | Activador: otro usuario de la misma empresa; memberships/roles. |
| CR-045 · Catálogo y proveedores · Full stack | Condicional | Activador: SKU/proveedor repetido; catálogo ≠ línea de compra. |
| CR-046 · Ventas e inventario · Producto/Backend | Condicional | Activador: existencias/ventas reales; reglas de valoración y devoluciones. |
| CR-047 · API e integración · Backend | Condicional | Activador: sistema externo identificado; contrato, outbox, ids externos. |

## Evidencia del checkpoint

- 118 pruebas PHP / 1.737 aserciones; formato Pint pasa.
- Cinco tests frontend de dinero/date-only y resolución de conflictos y build pasan con Node 24 en este checkpoint.
- Capturas reales de Control desktop y Resumen a 390 px en outputs/.
- La base personal no recibió fixtures; pruebas y demostración usan bases aisladas.
- Composer sin advisories después de parches compatibles; cinco advisories altos npm pendientes de Tailwind 4.
- No se ha desplegado ni ejecutado CI remota. Las migraciones locales estructurales no recalcularon datos heredados automáticamente.

## Avance de funciones avanzadas

- Precios por objetivo y pegado/CSV implementados con pruebas de servidor y recorridos reales en la base QA.
- Navegación a 320 px: foco contenido, Escape devuelve al botón, anchura del documento 320 px. Precio por objetivo a 1024 px: documento 1024 px. No sustituye la matriz visual completa pendiente.
- La capa del diálogo y los tokens de overlays se corrigieron después de detectarlos en la revisión visual.
- Asignación de empresa por lote ahora conserva revisiones, snapshots y auditoría; una revisión obsoleta revierte el lote completo.
- Pendientes principales: contratos de versiones obligatorias/DTO/TypeScript; alta guiada; analítica; recuperación/retención; auditoría/backfill; majors de framework/Inertia/Tailwind; rendimiento y ensayo de release; aceptación final.

## Escenarios, recuperación y auditoría

- Escenarios: entradas alternativas de tasa, productos/gastos/reparto y objetivo de precio; simulación conservada antes/después, hash de base, revisión requerida y rollback completo. Una operación pagada conserva monto, cantidad y tasa original.
- Recuperar una importación incrementa su revisión para invalidar ediciones antiguas, pero no reejecuta costeo. Una importación cerrada vuelve cerrada y conserva los mismos cost_runs y reportes.
- Auditoría: listado de 20 resúmenes sin cargar JSON antes/después; comparación autorizada paginada de 20 registros, whitelist de campos sin rutas de almacenamiento.
- Tras migrar, ejecutar `php artisan costoreal:audit-backfill`. Completa únicamente resúmenes ausentes; puede repetirse y no altera snapshots, entradas ni resultados de costeo.
- QA: escenario aplicado a importación 1 (revisión 6/costo 520.000); importación 2 restaurada (revisión 4/costo 400.000). La base personal sigue sin importaciones y sin fixtures.
- Evidencia visual: escenarios a 1024 px y papelera/restauración/historial a 320 px, sin overflow global. La matriz completa de accesibilidad sigue en CR-030.

- Checkpoint `6ccd273`: instalación desde archivo Git limpia, setup/build/Pint, 112 pruebas PHP (1.631 aserciones) y dos frontend pasan. Nueva prueba cubre reintento tras rollback sin perder applied_revision del escenario. Copias personal y QA restauradas con conteos/totales iguales, integrity ok y FK=0.

## Contratos de edición y hoja avanzada

- Guardar sin expected_revision se rechaza antes de escribir; creación de una importación nueva no necesita revisión. Asignar empresa por lote exige una revisión para cada importación.
- POST para cambiar empresa activa; GET devuelve 405 sin mutar la sesión. Empresas con importaciones en papelera no pueden eliminarse perdiendo su asociación.
- Hoja: compras estimadas/pagadas, tasa histórica de ocho decimales, fecha/fuente y medidas totales por línea. Guardado JSON mantiene el formulario ante 422/403/404/401/419, sin navegar a una página de error.
- Conflictos: lectura autorizada por padre/hijo, tres versiones, combinación de campos independientes y elección explícita en campos simultáneamente modificados. Preparar revisión no guarda; el guardado vuelve a exigir CAS.
- QA de dos pestañas: base costo 100, borrador 105, edición remota 110/peso 3; resolución guardó 105 conservando peso 3. Compra pagada conserva tasa 950.12345678/peso 2.12345678. Importación QA 1 terminó en revisión 10 con costo 530.012; QA 2 mantiene revisión 4/costo 400.000.
- Error de fuente de pago preservó nombre/tasa/peso y mostró mensaje en español. Enter guardó en móvil; Escape descartó un cambio de cantidad sin escritura. DOM a 320 px: scrollWidth 320.
- Auditoría corregida para comparar los campos persistidos gastos_prorrateados_clp y expense_category_id; prueba verifica diferencia financiera real.

- Reintento de restauración de documento: se recarga el modelo dentro de cada transacción, evitando reutilizar un estado de Eloquent que fue revertido. Prueba con fallo de bloqueo después de cost_runs verifica una sola restauración, archivo conservado y auditoría correcta.
