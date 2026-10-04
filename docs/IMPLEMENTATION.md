# Ejecución de la modernización de Costo Real

Objetivo: ejecutar el plan completo del 3 de octubre de 2026. Uso personal preparado para crecer; dirección financiera clara, elegante y compacta.

Las tareas se cierran por su criterio de aceptación. Los estados parciales identifican el trabajo que falta; las extensiones conservan sus activadores.

| Tarea | Estado | Evidencia / pendiente |
|---|---|---|
| CR-001 · Entorno y CI mínimo · Desarrollo | Verificado | Instalación limpia y setup repetido preservan clave; PHP 8.3/Node 24, CI configurada. CI remota aún no ejecutada. |
| CR-002 · Fixtures y restauración · Desarrollo/QA | En curso | Fixture aislado sin datos personales y copia SQLite íntegra. Falta reconciliación completa DB/storage de release. |
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
| CR-017 · Atomicidad y conflictos · Backend | En curso | Rollback de datos/versiones/archivos y conflicto de versión probado. Versión aún opcional para clientes heredados; falta concurrencia real SQLite. |
| CR-018 · Ejecuciones y snapshots · Backend | Verificado | VersionedCostingTest: cierre/reapertura mantienen snapshot inmutable y reportes históricos. |
| CR-019 · Auditoría y comparación · Backend/QA | En curso | Actor, antes/después e historial implementados. Falta comparador y backfill reentrante. |
| CR-020 · DTOs y TypeScript · Full stack | Pendiente | — |
| CR-021 · Lecturas y consultas · Backend | En curso | Control mantiene cuatro consultas en 1/200 registros y analítica ocho en 1/20. Falta perfil de rutas restantes. |
| CR-022 · Layout financiero · Frontend | En curso | Layout financiero, empresa/tema/densidad y menú móvil. Foco móvil pendiente de verificación tras último ajuste. |
| CR-023 · Control de importaciones · Full stack | En curso | Control paginado, filtros/métricas del conjunto completo y enlaces a pendientes probados. QA visual de vacíos/errores pendiente. |
| CR-024 · Detalle y resumen · Frontend | En curso | Show separado, resumen/cobertura/pérdidas y tabs persistidas. Falta verificar eventos tras última compilación. |
| CR-025 · Hoja editable · Full stack | En curso | Edición por fila, Enter/Escape y vista móvil. Falta conflicto por fila con comparación y edición de metadata. |
| CR-026 · Desglose de costo · Frontend | En curso | Panel de compra/tasa/gastos/versión disponible. Falta detalle de residual y prueba visual. |
| CR-027 · Formularios de gastos · Full stack | En curso | Formulario estimado/pagado y metadata; multipart con/sin archivo probado. Falta QA de preservación de errores. |
| CR-028 · Documentos privados · Backend/Frontend | En curso | Privacidad, metadatos, download/preview, papelera y restauración probados. Falta migración de archivos públicos heredados y política final de retención. |
| CR-029 · Alta y revisión guiadas · Full stack | En curso | Revisión/cierre/reapertura disponibles. Alta guiada y borrador recuperable pendientes. |
| CR-030 · QA visual y accesible · Diseño/QA | En curso | Evidencia desktop/390 px. Falta 320/1024, zoom, contraste, ambos temas y keyboard completo. |
| CR-031 · Pegado y CSV · Full stack | Pendiente | — |
| CR-032 · Reglas avanzadas de reparto · Full stack | En curso | Motor de unidades/peso/volumen/directo probado y formulario de gastos disponible. Falta metadata de productos existentes y trazabilidad completa. |
| CR-033 · Precio por objetivo · Full stack | Pendiente | — |
| CR-034 · Escenarios y revaluación · Full stack | Pendiente | — |
| CR-035 · Analítica renovada · Full stack | Pendiente | — |
| CR-036 · Historial y recuperación · Full stack | En curso | Historial y recuperación de documentos implementados. Falta papelera/restauración de importaciones. |
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

- 74 pruebas PHP / 1.142 aserciones; formato Pint pasa.
- Tests frontend de dinero/date-only y build reproducible con Node 24 en el checkpoint previo; se repetirán con los cambios actuales.
- Capturas reales de Control desktop y Resumen a 390 px en outputs/.
- La base personal no recibió fixtures; pruebas y demostración usan bases aisladas.
- Composer sin advisories después de parches compatibles; cinco advisories altos npm pendientes de Tailwind 4.
- No se ha desplegado ni ejecutado CI remota. Las migraciones locales estructurales no recalcularon datos heredados automáticamente.
