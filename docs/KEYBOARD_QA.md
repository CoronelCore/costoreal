# Foco y validación: checkpoint de interfaz

## Cambios aplicados

Hoja de productos enfoca el nombre al abrir alta o edición. En móvil elige el campo realmente visible, sin enfocar la fila de escritorio oculta. Cancelar/Escape vuelve al disparador; guardar en creación integrada vuelve al botón disponible aunque el estado vacío desaparezca. Los errores básicos y de tasas/peso/volumen se vinculan con aria-invalid y aria-describedby. Foco de validación acotado al panel: primer campo inválido visible/habilitado, o resumen de error enfocable para errores sin campo.

Las rutas Inertia y axios comparten esta recuperación. En creación integrada los errores se asignan directamente a form.errors: se comprueban sus claves, sin depender de un indicador hasErrors que no se actualiza con esa asignación. Los borradores se conservan tras error y el guardado válido mantiene el flujo existente.

Modal expone initialFocus a Headless UI. Reapertura enfoca el motivo; las otras confirmaciones de importación enfocan Cancelar. Los disparadores de cierre/reapertura/papelera recuerdan el foco también con activación por puntero en macOS. Mientras guarda, el diálogo no ofrece cierre ni doble confirmación. Tab/Shift+Tab permanecen en el diálogo.

Confirmaciones de importación conservan título y mensaje hasta terminar la transición; las acciones se deshabilitan al iniciarse el cierre. El desglose conserva producto, revisión, resultado y cifras hasta after-leave, cancela requests al cerrar y libera los datos al terminar. Reintentos/paginación quedan deshabilitados durante el cierre. El retorno al costo seleccionado sigue funcionando.

## Evidencia de navegador

Copia privada de la QA, DB/discos aislados, servidor temporal en loopback 8011. Se comprobó escritorio, productos/errores en oscuro a 390 px y reapertura clara a 320 px. El viewport se retiró y el servicio/pestaña temporales se cerraron. Solo la copia recibió un cierre sintético y una importación/producto de prueba.

- Alta normal e integrada: tasa 2 en CLP rechazada, foco new-product-rate, error asociado y borrador conservado. Se verificó recuperación válida de alta integrada: costo 10 CLP/revisión 2 y foco de vuelta a Agregar producto.
- Fila desktop/móvil con cantidad 0: rechazo sin escritura, foco en desktop-quantity-1/mobile-quantity-1 y mensaje asociado. Escape retorna al botón Editar.
- Cierre: foco Cancelar, Tab a Confirmar, Escape de vuelta al disparador. La captura durante la salida conserva título/mensaje.
- Reapertura: motivo enfocado al abrir y ante error vacío; el diálogo mide 320 px sin overflow global. Shift+Tab desde cerrar cicla a Confirmar dentro del diálogo.
- Desglose: costo original y título se mantienen durante la salida; al finalizar no queda diálogo y el foco vuelve al botón de costo.
- Cero errores de consola observados. Build/TypeScript y 18 tests frontend pasan. No se vuelve a ejecutar toda la suite PHP por una fase sin cambios backend; la fase del motor vigente pasó 245/4.945.

Fuente y 42 referencias del manifest iguales a la copia ensayada. Assets con hash anteriores se conservan para sesiones abiertas; manifest se sustituye atómicamente. Esto evita borrar chunks que una pestaña anterior pueda necesitar. Limpieza de assets/release sigue en el ensayo integral.

Smoke real en 8010: abrir/cancelar producto enfoca nombre y vuelve al botón; resumen sigue revisión 10/costo 530.012/utilidad 32.489. Las 21 tablas de dominio en personal/QA iguales, backups restaurados 29/29, integrity ok, FK 0, .env y tres archivos storage idénticos. Checkpoint privado work/backups/keyboard-focus-checkpoint. Evidencia de expediente local: outputs/verificacion-teclado-costoreal.json y capturas de error móvil/reapertura/320 claro.

## Alcance pendiente

CR-009/012/013/030 continúan en curso. Este checkpoint no certifica todos los estados/rutas, contraste, indicadores de foco, zoom 200 %, lector de pantalla ni aceptación humana. El contenido y foco transitorio se comprobaron con el navegador actual, sin afirmar comportamiento idéntico en todos los motores. La matriz global debe incluir catálogos, autenticación, configuraciones, creación, gastos/documentos, escenarios, lotes, historial y recuperación.
