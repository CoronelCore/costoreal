# Sistema visual financiero

## Catálogo y alcance

`/design-system` permite revisar los componentes reales del producto con una sesión autorizada, exclusivamente en entornos local/testing. No se registra la ruta en producción. Los ejemplos no escriben importaciones, preferencias de negocio ni archivos. El tema y la densidad utilizan el mismo layout del producto.

Incluye paleta, jerarquía tipográfica, métricas monetarias, importes negativos, fechas sin hora, acciones, estados, mensajes, campos, validación recuperable, tabla/tarjetas y confirmación. Mantiene el catálogo fuera de la navegación diaria. Los tokens compartidos viven en `resources/css/financial.css`; FinancialField y Modal aportan asociación de etiquetas/errores y manejo de foco.

`--f-line` identifica separadores decorativos. El nuevo `--f-control-line` identifica campos: #788995 en claro y #718a99 en oscuro. Su contraste medido sobre la superficie del catálogo es 3,61392086 y 4,31446559, respectivamente; el borde anterior de campos claros medía 1,2966163. Placeholders usan texto secundario explícito, sin transparencia adicional. El cierre de diálogo usa el mismo texto secundario y área de 36 px en escritorio/44 px en móvil. Las pestañas reservan seis píxeles interiores para el contorno de foco.

## Evidencia de medición

Se usó una copia privada de DB/storage con importación activa/cerrada/sin productos, documentos y nombres extensos; un SKU sin espacios reproducía 1.103 px de exceso de ancho global en Precios a 320 px. Un contenedor flexible acotado y `overflow-wrap:anywhere` corrigen esa causa.

Matriz final: veinte vistas × claro/oscuro × 320/390/1024/1280 px, 160 observaciones. Control, resumen, productos, gastos, precios, documentos, revisión, escenarios, historial, creación, configuración, vacíos, cierre, papelera, empresas, categorías, analítica y perfil. Todas presentan cero exceso de ancho global y cero fallos en las muestras de texto, placeholders, bordes de campos y geometría de controles medidas. Contraste mínimo de texto: 4,783887122461561 en claro y 5,941541337159073 en oscuro. El scroll local de tablas y pestañas se permite y se registra separadamente.

La medición lee estilos/rectángulos y nodos de texto del DOM público, compone fondos transparentes y compara razones sin redondear con 4,5:1 para texto normal, 3:1 para texto grande y 3:1 para límites de campo. Se excluyen campos deshabilitados y contenido inerte. Fondos no interpretables o transiciones se señalan como desconocidos; no cuentan como aprobados. Los diálogos se comprobaron de nuevo al estabilizar la transición: mínimos de texto 7,251031504842457 oscuro y 7,2565269183028525 claro a 320 px.

Guía normativa: [contraste de texto](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html), [contraste de componentes](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html) y [reflujo](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html). Estas mediciones no constituyen certificación global WCAG ni comprueban cada detalle pintado por controles nativos.

## Estados y foco comprobados

- Catálogo: envío vacío enfoca nombre con `aria-invalid`, ayuda/error asociados; corregir el nombre produce mensaje `role=status`.
- Diálogo: inicia en Cancelar; Tab alcanza Entendido con contorno visible de tres píxeles; Escape devuelve foco a Abrir confirmación. Claro/oscuro a 320 px sin desbordamiento.
- Pestañas: foco en Hoja de productos a 320 px; contorno de tres píxeles y offset de tres píxeles caben en el área de scroll, incluido el borde superior/inferior.
- Control: fechas invertidas conservan valores, muestran mensaje español, enlazan error y enfocan Hasta. Sin resultados ofrece limpieza que devuelve el conjunto completo. Pendientes abren Productos/Precios correspondientes.

CR-012 cubre catálogo/componentes y CR-023 cubre el Control con sus pruebas backend de filtros/totales. CR-009/013/024/030 continúan: la matriz mide vistas base; no certifica todos los estados dinámicos, acciones, lectura asistida o zoom real al 200 %. Este último aún no tiene evidencia de ejecución. El ensayo de release y la aceptación humana permanecen abiertos.

## Validación y recuperación

Build con TypeScript y 18 pruebas frontend pasan. ImportControlTest/InertiaProtocolTest: 10 pruebas y 100 aserciones; Pint pasa. Sin migraciones ni actualización de dependencias. Checkpoint privado `work/backups/visual-contrast-checkpoint`: código/build/DB/storage/entorno anteriores. Fuentes y assets referenciados por el manifest se aplican con servicios detenidos; se mantienen chunks anteriores para pestañas abiertas. El ensayo integral de recuperación continúa en CR-041.
