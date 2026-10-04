# Gastos, comprobantes y cierre

El editor de gastos ahora usa el formulario financiero común, presenta errores asociados al campo y enfoca el control cuando termina la solicitud. El alta guiada adapta los errores JSON a texto legible; campos, archivo seleccionado y metadata permanecen tras un rechazo.

## Cambios

- FinancialField para concepto, monto, tasa histórica, fuente y fecha; selects y archivo con labels/aria/errores vinculados. El formulario utiliza validación del servidor, decimales como texto e inputmode decimal. La cuadrícula evita estirar controles vecinos; altura desktop medida: 40 px.
- Envío deshabilita controles; foco de error después de liberarlos, tanto Inertia como axios guiado. Errores generales tienen resumen enfocable. Cancelar restaura el disparador. Una importación que deja de admitir cambios mantiene el borrador visible y bloquea envío.
- Conversión estimada/pagada explícita. Los campos históricos permiten ocho decimales y declaran tasa 1 para CLP. No cambia la política de costeo.
- Comprobante vinculado visible con ruta privada. Reemplazar o desvincular conserva el documento; la selección aún no enviada se conserva en errores y debe repetirse al recargar un borrador. Tabla y tarjetas móviles permiten consultar comprobante en solo lectura. Tarjetas pagadas presentan tasa y fecha.
- Eliminar queda deshabilitado durante edición/envío; tabla cerrada omite columna vacía de acciones. Mensajes de categoría/destino/formato/tamaño de archivo en español, preservando autorización y reglas.
- `formFieldErrors` adapta mensajes de JSON/Inertia sin cambiar el ErrorBag original; ExpensePanel y ProductSheet guiados lo utilizan. Se corrige en el mapa de navegación el estado interno de Cerrada: `finalizado`.

## Evidencia

**219 PHP / 3.462 aserciones**, **18 frontend**, tipos/build/Pint pasan en el proyecto principal. Dos pruebas feature nuevas: edición inválida con archivo/desvinculación no cambia importes, revisión, documento ni archivos; edición sin upload conserva vínculo y desvinculación mantiene descarga privada. Una prueba frontend acredita mensajes equivalentes entre JSON e Inertia.

Recorrido real en copia aislada:

1. Monto negativo y tasa pagada cero conservan concepto, monto, fuente, fecha y PNG seleccionado; foco en monto/tasa. Comparación de trips/products/expenses/documents: filas originales iguales después de rechazos.
2. Guardar con comprobante: revisión 10→11, monto USD120.25, tasa histórica 900.12345678, fuente sintética y fecha 2026-10-03. El archivo privado tiene 2.170 bytes y SHA igual al PNG enviado.
3. Editar sin archivo: revisión 12, USD121.25 → **109.140 CLP**, mismo document_id=1, misma tasa y un solo documento. La conversión se compara con Decimal independiente.
4. Cerrar crea ejecución 13, estado `finalizado`, snapshot **519.152 CLP**; controles de gasto ausentes y comprobante accesible. Reabrir con motivo llega a revisión 14 y conserva cierre 13. Suite prueba rechazo de configuración/productos/gastos/precios/documentos y aplicaciones avanzadas en estado cerrado.
5. Alta guiada: error de destino directo conserva concepto/12.50/archivo y enfoca destino. Recarga recupera borrador e informa reselección del comprobante aún no enviado. Selección válida guarda **12.500 CLP**, producto destino 5 y documento 2; cancelar edición vuelve al botón original.

Matriz **6 estados × 2 temas × 4 anchuras = 48 observaciones únicas**: error pagado con archivo, editor con vínculo, lista guardada, cerrada de solo lectura, vacío y error de alta guiada. 320/390/1024/1280 px: sin overflow global ni referencias aria inexistentes. Se revisan capturas y alturas finales; la matriz registra estados en distintos momentos del recorrido. CR-030 mantiene contraste, zoom y teclado globales.

Servicios originales: sesiones válidas y consola sin errores observados; **21/21 tablas de dominio iguales**, restauración **29/29 tablas iguales**, integrity=ok/FK=0, entorno/tres archivos conservados. Personal sin importaciones; QA mantiene revisiones 10/4 y datos anteriores. Solo se abre/cancela el editor en QA; las escrituras financieras se hacen en la copia aislada.

## Seguimiento y recuperación

CR-008 y CR-027 verificados. Las tareas de QA global, separación/perfil de lecturas, rendimiento, release y aceptación conservan sus criterios pendientes. No se ejecuta CI remota en este bloque.

Checkpoint anterior `c64792f` en `work/backups/expense-flow-checkpoint`: código, build, DB personal/QA, storage y entorno privado. Vendor/lockfiles no cambian; no hay migraciones. Para revertir este bloque, recuperar código de ese commit y su build, y reiniciar ambos servicios conservando DB/entorno. Una recuperación de DB/storage posterior exige conciliar cambios legítimos del usuario.
