# Contexto autorizado y límites internos

Uso personal con aislamiento por propietario; estructura preparada para nuevos puntos de entrada. El selector de empresa filtra navegación, no concede permisos ni restringe el acceso explícito a otra importación propia. Una empresa propia inactiva sigue siendo válida para leer su historia.

## Contrato de entrada

- HTTP: autenticación y binding padre/hijo en `routes/web.php`; Requests validan origen, dinero, relaciones y revisión. `AccessContext::forRequest()` conserva el usuario de la solicitud y el selector autorizado.
- Operación sin sesión: `AccessContext::forActor($actorId)` vuelve a consultar un usuario existente. El ID debe provenir de un registro del servidor autorizado al encolar. El consumidor vuelve a autorizar la importación al ejecutar; un ID enviado por el cliente nunca constituye autorización.
- `MutateTrip`, `CloseImportation`, `RecoverTrip`, `ReviewStoredCosts` y `RecordCostRun` reciben contexto explícito. Los callers síncronos conservan el fallback autenticado para compatibilidad. Una llamada sin contexto ni usuario se rechaza. El callback de mutación es código interno; no se acepta código o atributos arbitrarios de un cliente.
- Una operación diferida debe proporcionar `expectedRevision`. El bloqueo y compare-and-swap revalidan propietario, estado y revisión antes de escribir hijos. Un conflicto se devuelve para resolución explícita; no se sustituye por la última revisión ni se reintenta una decisión financiera automáticamente.

No hay jobs propios ni un worker de producto activado actualmente. Las pruebas ejecutan acciones sin sesión y con otro usuario HTTP presente para comprobar el contrato que usaría un futuro consumidor. OCR, equipo y API continúan sujetos a sus activadores; esta fase no introduce esos productos.

## Matriz de límites

| Área | Autorización y delegación | Evidencia automatizada |
|---|---|---|
| Control, ficha, alta y papelera | `ControlImports`, `ReadImportation`, `ReadCreation`, `ReadTrash`; consultas por propietario y DTOs públicos | FinancialDataContractTest, LegacyReadIsolationTest, ImportCreationDraftTest, TripRecoveryTest |
| Empresa y analítica | `ReadCompany`, `AnalyticsQuery`; SQL del conjunto autorizado y paginación | CoreIntegrityTest, CompanyReadTest, AnalyticsTest, PerformanceObservationTest |
| Productos, gastos y configuración | Requests; `MutateTrip` autoriza el registro bloqueado, reclama revisión, calcula, persiste versión y auditoría en una transacción | CoreIntegrityTest, VersionedCostingTest, RevisionContractTest, ConcurrentMutationTest |
| Cierre y recuperación | `CloseImportation` revisa coherencia y delega mutación; `RecoverTrip` conserva importes/estado y registra revisión/actor | ImportReviewTest, TripRecoveryTest, ActionAccessContextTest |
| PDF y XLSX | `ReportTrip` autoriza antes de consultar run/baseline del padre; `FinancialSummaryData` comparte cifras y `FinancialPdf`/TripExport presentan | ExcelContractTest, VersionedCostingTest, LegacyReadIsolationTest, ActionAccessContextTest |
| Documentos privados | `StoreDocument` requiere actor, vincula recibo de staging a ese actor, reconsulta estado del padre y comprueba hijo; `ReadDocument` reautoriza y busca documento activo dentro del padre antes de acceder al disco | PrivateDocumentTest, ActionAccessContextTest |
| Historial, trazas y escenarios | Controladores autorizan padre antes de consultar hijos; `ListAuditEvents`/`ListScenarios` admiten contexto; simulación/cálculo quedan en servicios | AdvancedReadContractTest, CostBreakdownReadTest, CostScenarioTest, LegacyReadIsolationTest |
| Precios e importación por lote | Requests y controladores validan/pautan vista previa; los cálculos delegan a dominio y la aplicación final pasa por `MutateTrip` con revisión | TargetPricingTest, ProductImportTest, RevisionContractTest |
| Conciliación | `ReviewStoredCosts` exige actor también fuera del controlador; aplicación pasa por `MutateTrip`, conserva copia original y verifica fingerprint/diferencias | CostReconciliationTest, ActionAccessContextTest |

Las suites citadas están en `tests/Feature/`. Los resultados de ejecución local se registran en IMPLEMENTATION y en la evidencia de entrega; no se afirma ejecución de CI remota o de un worker real.

## Archivos y autoría

`stage()` se invoca después de la validación/autorización HTTP, o con archivo validado por un consumidor interno. Devuelve un recibo interno con ruta aleatoria y `staged_by`; no es un token firmado ni un payload aceptado del cliente. `attach()` comprueba que el recibo corresponde al actor y que un producto/gasto pertenece al padre actual. `discard()` aplica el mismo actor para limpiar un fallo sin borrar staging de otro usuario. `uploaded_by`, autor de cost run, baseline y auditoría proceden del contexto explícito, aunque exista otro usuario en Auth.

`ReadDocument` nunca usa la ruta de un objeto de documento desactualizado recibido por el caller: reconsulta el documento activo del padre. Imágenes JPEG/PNG/WebP pueden mostrarse inline; el resto se descarga. Se conservan `nosniff`, cache privado sin almacenamiento y CSP sandbox. Trashing mantiene el archivo; restauración sigue el mismo contrato de revisión.

## Historia y privacidad

Los importes y enlaces heredados no se corrigen al leer. Las relaciones públicas de empresa/categoría se filtran; los DTOs muestran null cuando faltan permisos. Borradores conservan su JSON de origen y texto del editor, pero ocultan referencias privadas al proyectarlos. Antes de redirigir un borrador completado, se autoriza su importación. La lista de borradores tampoco publica IDs de importaciones ajenas vinculadas por legado.

Las copias originales internas pueden conservar metadata histórica para reconciliación; los reportes vuelven a filtrar su proyección. Las versiones nuevas cargan metadata con el contexto al registrar. No se reescribe una versión financiera anterior. El motor decimal recibe datos normalizados y no depende de Auth, sesión, Request o políticas.

## Mantenimiento privilegiado

`costoreal:legacy-cost-review` usa `ReviewStoredCosts::executeForMaintenance()`: punto de entrada explícito exclusivo de consola, conserva actor null y nunca aplica correcciones. Backfills de lecturas/auditoría y migración de archivos heredados son operaciones del administrador local sobre DB/storage; no se reutilizan como jobs de usuario ni se exponen por HTTP. Los helpers `CompareStoredCosts`, `PreserveLegacyCosts` y `ProjectCostRun` son internos a límites previamente autorizados o mantenimiento privilegiado. No añaden una segunda decisión de propietario al dominio puro.

Al incorporar un job real, rehidratar contexto/Trip desde IDs del servidor en `handle()`, verificar revisión y llamar las acciones públicas anteriores. Nunca serializar la sesión, usar el selector como permiso, establecer Auth global para suplantar al usuario o llamar el punto de mantenimiento desde ese job.
