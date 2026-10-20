# Navegación y tareas de CostoReal

Dirección vigente: uso personal preparado para crecer; interfaz financiera clara, elegante y compacta. Pantalla principal: Control de importaciones (`/trips`).

## Mapa de tareas

| Tarea | Pantalla / destino | Acción principal | Resultado y recuperación |
|---|---|---|---|
| Revisar la operación | Control de importaciones | Filtrar o abrir una importación | Los indicadores abarcan todo el filtro; páginas y pendientes enlazan a su tarea. |
| Registrar una compra | Nueva importación (`/trips/create`) | Alta guiada o alta rápida | Contexto → productos → gastos → revisión; el borrador guarda editores pendientes y puede retomarse. Alta rápida abre la hoja. |
| Entender una compra | Detalle (`/trips/{id}#resumen`) | Abrir la tarea pendiente | Inversión guardada, cobertura de precios y utilidad proyectada; precios incompletos conservan su ausencia. |
| Registrar productos | Hoja de productos (`#productos`) | Guardar fila o importar un lote | Borrador de fila separado del total guardado; comparación explícita ante conflicto. Pegado/CSV se previsualiza antes de aplicar. |
| Explicar un costo | Desglose desde una línea | Consultar compra, conversión y reparto | Importes, versión y residual conciliados; un dato heredado sin trazabilidad se identifica como tal. |
| Registrar gastos | Gastos (`#gastos`) | Guardar gasto | Estimado/pagado, moneda, tasa y evidencia; errores conservan los campos; comprobante opcional según política. |
| Preparar precios | Precios (`#precio-venta`) | Previsualizar y aplicar | Margen y recargo diferenciados; aplicación exige revisión vigente. La proyección no registra ventas. |
| Conservar evidencia | Documentos (`#documentos`) | Adjuntar, abrir o descargar | Acceso privado; papelera y restauración según política de retención. |
| Comparar decisiones | Escenarios (`#escenarios`) | Simular, conservar o aplicar | Simulación separada del costo registrado; promoción versionada y pagos preservados. |
| Cerrar una compra | Revisión (`#revision`) | Revisar bloqueos y cerrar | Validación en servidor; conserva snapshot. Reabrir exige motivo y mantiene la revisión anterior. |
| Revisar cambios | Historial (`#historial`) | Abrir una ejecución o diferencia | Listas paginadas, actor/revisión y detalles bajo demanda; documentos heredados mantienen su evidencia. |
| Ajustar contexto | Configurar (`/trips/{id}/edit`) | Guardar cambios | Nombre, descripción, empresa, fechas y conversión; estado se cambia en Revisión. Conflicto conserva el borrador. |
| Recuperar una compra | Papelera (`/trips/trash`) | Restaurar | Conserva hijos e historial; la recuperación respeta autorización y estado. |
| Analizar inversiones | Analítica (`/analytics`) | Aplicar período y estado | Mismo alcance de empresa; tablas equivalentes a gráficos, cobertura y proyección explícitas. |
| Organizar empresas | Empresas (`/companies`) | Crear, ver o editar | Ficha con inversión real y lista paginada; eliminar exige ausencia de vínculos, incluida papelera. |
| Clasificar gastos | Categorías (`/categories`) | Crear o editar una categoría personal | Categorías base de solo lectura; eliminación exige ausencia de gastos asociados. |
| Gestionar acceso | Login/registro/recuperación/perfil | Acción específica de la pantalla | Formularios financieros con etiquetas, estados de envío y mensajes; acceso existente y recuperación conservan sus contratos. |

## Estados y lenguaje

- **Borrador:** progreso de alta independiente del costo definitivo; revisión propia y reintento idempotente. Un editor pendiente debe guardarse o cancelarse antes de terminar.
- **En curso:** estado interno `activo`; permite cambios con `expected_revision` y recálculo atómico. La pantalla indica revisión e importes guardados.
- **Cerrada:** estado interno `finalizado`; mutaciones normales bloqueadas en servidor. Conserva un snapshot; reabrir con motivo permite una nueva revisión sin reemplazar el cierre.
- **Archivada:** estado heredado `archivado`, disponible como filtro/lectura. La configuración no ofrece un cambio libre de estado.
- **Papelera:** eliminación recuperable de la importación; excluida de indicadores habituales. Retención y retiro de archivos se rigen por `RETENTION.md`.
- **Estimado / pagado:** procedencia de conversión; al cambiar la referencia solo se revalúan estimaciones. Los pagos conservan importe/tasa registrados.
- **Sin precio / cobertura parcial:** ausencia explícita, sin inventar ingresos ni utilidad total.
- **Conflicto:** una revisión distinta exige comparar o recargar; no se aplica silenciosamente el borrador antiguo.

## Jerarquía y contexto

La navegación principal expone Importaciones, Analítica, Empresas y Categorías. Perfil y salida están en el encabezado; en móvil, salida está dentro del menú. La marca devuelve al Control. El detalle agrupa tareas mediante secciones con anclas; configuración es una pantalla independiente.

El selector de empresa mantiene su contexto al navegar y recargar. Control y Analítica explicitan el alcance seleccionado. La ficha de una empresa presenta sus propias cifras aunque el selector tenga otra empresa; el texto de alcance y el selector permanecen visibles. Su DTO utiliza `companyRecord` para no reemplazar el contexto compartido `company`.

Tema y densidad se conservan por origen. Menú móvil: foco al abrir, ciclo Tab, Escape y devolución al botón. Formularios: etiquetas vinculadas, descripciones existentes, error con foco y envío deshabilitado. Cancelar una confirmación devuelve el foco al disparador.

## Evidencia y seguimiento

Mapa contrastado con rutas, secciones y contratos de dominio actuales. La alta/revisión, hoja/conflictos, desglose, escenarios, reportes e historial tienen evidencia en `IMPLEMENTATION.md`. El bloque de catálogos/acceso/configuración se verifica en `FINANCIAL_CATALOGS.md`. El cierre de CR-011 acredita este mapa y la coherencia de tareas/estados; CR-030 conserva el recorrido global de zoom, contraste, estados y teclado. La aceptación visual final corresponde a CR-042.
