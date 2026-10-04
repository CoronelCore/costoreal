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
| CR-020 · DTOs y TypeScript · Full stack | Verificado | Resumen compartido con Excel/PDF; contratos decimales/resultado/mutaciones; consumidores financieros y avanzados TypeScript estrictos; fixtures negativos, 127 pruebas PHP y nueve frontend. Páginas heredadas migran en sus tareas UX correspondientes. |
| CR-021 · Lecturas y consultas · Backend | En curso | Control mantiene cuatro consultas en 1/200 registros y analítica ocho en 1/20. Historial inicial paginado (20 resúmenes) sin columnas before/after; diferencias bajo demanda. Falta perfil de otras rutas. |
| CR-022 · Layout financiero · Frontend | En curso | Layout financiero, empresa/tema/densidad y menú móvil. Foco móvil pendiente de verificación tras último ajuste. |
| CR-023 · Control de importaciones · Full stack | En curso | Control paginado, filtros/métricas del conjunto completo y enlaces a pendientes probados. QA visual de vacíos/errores pendiente. |
| CR-024 · Detalle y resumen · Frontend | En curso | Show separado, resumen/cobertura/pérdidas y tabs persistidas. Falta verificar eventos tras última compilación. |
| CR-025 · Hoja editable · Full stack | Verificado | Edición por fila; tasas pagadas/fuente/fecha, peso/volumen; comparación de conflicto de tres versiones, resolución por campo y guardado posterior explícito. Pruebas API y frontend; Enter/Escape y error que conserva inputs verificados a 320 px. |
| CR-026 · Desglose de costo · Frontend | Verificado | Lectura autorizada por producto y revisión, 20 gastos/página; importes y tasas históricos. Residuo exacto, parte entera y prioridad; UI desktop/320 px, Escape y foco probados. Legado conserva importe y declara ausencia de trace. |
| CR-027 · Formularios de gastos · Full stack | En curso | Formulario estimado/pagado y metadata; multipart con/sin archivo probado. Falta QA de preservación de errores. |
| CR-028 · Documentos privados · Backend/Frontend | En curso | Privacidad, metadatos, download/preview, papelera y restauración probados. Falta migración de archivos públicos heredados y política final de retención. |
| CR-029 · Alta y revisión guiadas · Full stack | En curso | Revisión/cierre/reapertura disponibles. Alta guiada y borrador recuperable pendientes. |
| CR-030 · QA visual y accesible · Diseño/QA | En curso | Evidencia desktop/390 px. Falta 320/1024, zoom, contraste, ambos temas y keyboard completo. |
| CR-031 · Pegado y CSV · Full stack | Verificado | Mapeo, vista previa por fila, política todo/nada, 100 filas conciliadas, reintento sin duplicados; 1.000 filas con 98 consultas. Flujo de pegado guardado en UI QA. |
| CR-032 · Reglas avanzadas de reparto · Full stack | Verificado | Valor/unidades/peso/volumen/directo, metadata requerida y reparto exacto probados. Explicación por producto conserva base, fracción racional, prioridad y adjudicación residual. |
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
- Pendientes principales: DTO/TypeScript; alta guiada; analítica; recuperación/retención; auditoría/backfill; majors de framework/Inertia/Tailwind; rendimiento y ensayo de release; aceptación final.

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

- Checkpoint `00af9ae`: instalación desde archivo Git limpia; setup/build/Pint, 118 pruebas PHP (1.737 aserciones) y cinco frontend pasan. Copias personal y QA restauradas con conteos/totales iguales, integrity ok y FK=0. Cancelar edición devuelve el foco al botón Editar del producto; verificado en la UI.


## Resumen compartido y contratos financieros

- FinancialSummaryData concentra estadísticas exactas y cobertura/proyección. El detalle, la compatibilidad de ProrrateoService y los tres sheets de Excel/PDF usan esa misma fuente. Las relaciones de un snapshot ya cargadas no se sustituyen por datos actuales.
- ImportationData/ProductData exponen campos explícitos en Control, detalle, configuración y lectura de conflictos; importes, tasas y medidas viajan como texto decimal. Los documentos solo exponen metadatos y URL autorizada; no se envían storage_path, imagen/comprobante, trip_id ni user_id de estos registros.
- TypeScript 6.0.3 y vue-tsc 3.3.12 fijados en lock. Arranque, layout financiero, Control/detalle, resumen/revisión, edición/comparación de productos, gastos, documentos y precios individuales usan tipos estrictos. Dinero, resultado de costeo, cobertura, paginación y mutaciones con expected_revision tienen contratos explícitos.
- `npm run build` comprueba los tipos antes de compilar. CI construye antes de las pruebas PHP que renderizan Blade y necesitan el manifiesto. Fixtures negativos comprueban campos inexistentes, dinero numérico, revisión ausente y proyección nullable. No hay declaraciones globales de componentes como any.
- La migración es gradual: allowJs=true/checkJs=false para páginas heredadas y flujos avanzados aún sin migrar. CSV, precios por objetivo, escenarios y comparación de auditoría necesitan completar sus consumidores TypeScript en CR-020; sus pruebas funcionales previas siguen pasando. CR-020 continúa En curso.
- Excel/PDF incorporan la proyección del lote, cobertura y estado pendiente. Pérdidas conservan su signo; precios ausentes conservan null/Pendiente. Excel sigue usando celdas numéricas y texto no confiable literal. Separadores vacíos explícitos mantienen las posiciones del resumen; estilos de tasa de ocho decimales y margen se aplican a sus celdas reales.
- PDF formatea importes con BigDecimal y separadores chilenos, sin convertir dinero a float. Conversiones numéricas de Excel quedan en el límite de presentación, después de calcular con decimales exactos.
- Cinco pruebas nuevas: DTOs privados/decimales, pérdida con gasto sin distribuir, cobertura incompleta en reportes, exportación numérica histórica tras editar el lote actual y redondeo de importes grandes. Suite: 123 pruebas PHP/1.828 aserciones, cinco frontend, comprobación de tipos, build y Pint pasan.
- QA de la versión compilada: error de fuente de pago preservó tasa 950.12345678 y borrador de peso 2.12345679 a 320 px (scrollWidth=320). Cancelar descartó el borrador. QA sigue en revisiones 10/4 y costo agregado 930.012; datos personales siguen sin fixtures.
- Referencia de tooling: https://vuejs.org/guide/typescript/overview.html (Vite transpila; vue-tsc comprueba SFC y templates). La versión JavaScript de TypeScript 6 mantiene la integración de vue-tsc, sin introducir el compilador nativo 7 durante esta migración.

- Checkpoint `871004b`: archivo Git limpio, setup/build con typecheck, Pint, 123 pruebas PHP/1.828 aserciones y cinco frontend pasan. Datos personal/QA conservados. Evidencias exportadas en verificacion-contratos-financieros.json y capturas de resumen/validación móvil.

## Contratos de los flujos avanzados · 4 de octubre

- CSV, precios por objetivo, escenarios y comparación de auditoría usan script setup TypeScript, respuestas Axios explícitas y contratos de mutación que exigen revisión y clave de idempotencia cuando corresponde. Se completa el criterio de aceptación de CR-020: totales compartidos y errores de contrato detectados antes de compilar. Las páginas heredadas fuera de estos flujos siguen en JavaScript y se migrarán junto con su renovación visual.
- PricingData, ScenarioData, BulkImportData y AuditEventData exponen datos de lectura explícitos. Normalizan decimales y listas de clientes anteriores únicamente al responder; no reescriben las entradas, resultados ni hashes de escenarios o auditoría. Errores vacíos se serializan como objetos.
- Los constructores de borradores conservan objetos File y texto decimal. La copia de un escenario no comparte filas o selecciones con el original; la simulación captura el input exacto que se usará al guardar. Los pagos conservan montos originales y un objetivo de precio omite precios manuales sin modificar el borrador.
- Paginación de escenarios cancela solicitudes anteriores e ignora sus respuestas mediante secuencia. Las pruebas de este bloque cubren contratos, copias y capturas; no simulan artificialmente una red desordenada.
- Validación de filas CSV en español con nombres de campos comprensibles, límites y precisión. Una fila inválida conserva su texto y desactiva la aplicación del lote completo.
- Verificación: 127 pruebas PHP / 1.896 aserciones, nueve frontend, typecheck, build y Pint pasan. Nuevas pruebas comprueban no escritura en previews, normalización de clientes antiguos sin alterar registros históricos, ausencia de campos privados, captura inmutable de borradores y conservación de archivos/decimales.
- QA real: escenario con tasa 950.12345678 y variación 20.25 muestra 530.012 → 531.404; a 320 px, innerWidth=scrollWidth=320. Precio objetivo 20.25 % y comisión 1.25 % muestra precios 172.906 / 78.230 sin aplicarlos. CSV inválido conserva =100 y bloquea importar; historial recupera la diferencia de nombre de revisión 10 bajo demanda.
- Base personal: cero importaciones/productos/gastos/documentos/escenarios. QA: revisiones 10/4, costos 530.012/400.000, cuatro productos, un gasto, un escenario; sin escrituras nuevas durante este recorrido.
- El plan completo permanece activo. Siguiente frente: limitar la lectura del resultado de costeo y llevar el desglose de gastos/residuos bajo demanda, con trazabilidad histórica y presupuesto de consultas (CR-019/021/026/032/040).

- Checkpoint `01b6adb`: instalación desde archivo Git limpia, setup/build con typecheck, 127 pruebas PHP (1.896 aserciones), nueve frontend y Pint pasan. Logs clean-advanced-contracts-* en work/. La instalación limpia usa su propia base vacía y no altera los servicios personal/QA.

## Lectura de costeo bajo demanda y residuos · 4 de octubre

- La página inicial deja de seleccionar cost_runs.result; usa únicamente metadatos de las últimas 50 ejecuciones y latestCostRun. No consulta cost_allocations. El resultado completo sigue inmutable para reportes y auditoría.
- cost_run_views/lines/expenses constituyen una proyección de lectura. Cada línea conserva nombre, cantidades, costos, moneda y procedencia de tasa del snapshot/input originales; IDs históricos no dependen de que el producto o gasto actual siga existiendo. La proyección se escribe atómicamente con la ejecución; un fallo revierte tanto la acción financiera como las tablas de lectura. RecordCostRun también es atómico sin transacción externa.
- Consulta autorizada por importación + ejecución + producto histórico. Devuelve 20 repartos por página y usa el índice cost_allocations_product_page, confirmado por EXPLAIN QUERY PLAN. En datasets 20/500 gastos mantiene ocho consultas y respuesta inferior a 16 KB. Producto/run ajeno o inexistente devuelve 404; otro usuario, 403; página inválida, 422.
- Nuevos repartos registran base/base total, parte entera, fracción restante exacta, prioridad estable, pool residual y adjudicación 0/1 CLP. El algoritmo financiero y los importes no cambian; pruebas de conservación/reordenamiento siguen pasando. Se muestran reglas en español y detalle ampliable con conciliación parte entera + ajuste = asignación.
- costoreal:cost-read-backfill procesa una ejecución antigua por vez, acepta --limit y puede repetirse/reanudarse. No recalcula ni altera JSON/hash originales o montos de cost_allocations. Un GET con proyección ausente responde 409 y no escribe; los traces antiguos ausentes se declaran como no registrados.
- Migration 2026_10_04_010000 aplicada tras copia SQLite de personal/QA. Backfill preparó diez ejecuciones QA y cero personales; hashes de input/result/snapshot/input_hash y revisiones 10/4 conservados. Copias migradas restauradas: nueve tablas idénticas, integrity ok y FK=0. El ensayo DB/storage completo sigue en CR-041.
- UI en copia aislada (puerto temporal 8011, detenido al finalizar): tres productos iguales, gasto 2 CLP → 1/1/0. Sensor A muestra fracción 2/3, prioridad 1 y +1 CLP; Sensor C, prioridad 3 y +0. A 320 px innerWidth=scrollWidth=320. Escape vuelve al botón Sensor C después de la transición; el componente conserva un opener visible también al cambiar de breakpoint.
- UI QA original: producto pagado mantiene tasa 950.12345678, fuente/fecha y gasto asignado 27.808. La revisión 10 indica que su detalle residual no fue registrado. Se conservan las revisiones y costos originales; la demostración nueva solo existe en work/cost-read-ui.sqlite.
- Ocho pruebas de lectura/seguridad/backfill/atomicidad/no asignación y una del trace exacto. Typecheck, build y nueve tests frontend pasan. Costeo de 1.000 líneas: 72 consultas, 453 ms en el recorrido local de esta entrega; la duración depende del equipo, no es un SLA.
- CR-026/032 verificados; CR-019 continúa con comparación de costos heredados, CR-021/040 con los perfiles y presupuestos restantes. La matriz completa de accesibilidad/temas/zoom permanece en CR-030.

- Checkpoint `8738060`: archivo Git limpio, setup/build/types, 136 pruebas PHP (2.005 aserciones), nueve frontend y Pint pasan en instalación nueva. Presupuestos de lectura de prueba: 20/500 gastos → 8/8 consultas y 7.270/8.718 bytes. Copias migradas personal/QA restauradas y comparadas en nueve tablas; la base personal continúa sin fixtures.
