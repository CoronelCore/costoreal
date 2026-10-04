# Catálogos, acceso y configuración financieros

La ficha de empresa presentaba inversión cero y cargaba importaciones completas; edición y acceso conservaban una dirección visual distinta del Control. El bloque actual comparte la interfaz financiera, presenta inversión registrada y lista veinte importaciones por página. El contexto del selector se conserva al abrir o editar una empresa.

## Implementación

- `ReadCompany`: lectura autorizada por propietario/empresa, agregado del conjunto completo, paginación y metadata explícita. Cuatro consultas del read model tanto con una como con doscientas importaciones. Las importaciones ajenas heredadas y la papelera se excluyen de métricas; cualquier vínculo retenido impide eliminar una empresa.
- `companyRecord`: evita colisión con `company`, que contiene el contexto global compartido. Prueba HTTP y recorrido real con otra empresa seleccionada acreditan ambos alcances.
- `CompanyRequest` y `ExpenseCategoryRequest`: autorización y validación en servidor; mensajes claros en español. Las políticas y protección del catálogo base continúan vigentes.
- Formularios `FinancialField`: etiquetas, ayuda/error vinculados únicamente cuando existen, contraseña visible opcional, teclado y envío deshabilitado. `focusInvalid` dirige el foco al primer campo inválido.
- `RecordEditor` y `RecordList`: empresas/categorías con búsqueda, nombres largos, vista previa, colores/iconos accesibles, estados vacíos y confirmación que conserva errores. Cancelar usa un botón deshabilitable durante envío.
- Seis pantallas de acceso y configuración de importación usan TypeScript y el diseño financiero. Configuración conserva tasas de ocho decimales y las cinco monedas admitidas; cierre/reapertura permanece en Revisión.
- El lector DOM compartido acepta textarea: la descripción multilínea ahora llega al servidor. Se comprobó su persistencia en la base, además de su apariencia en el formulario.
- Menú móvil incluye salida con objetivo de 44 px, desplazamiento vertical para pantallas bajas, ciclo Tab, Escape y restauración del foco.

## Verificación

Proyecto principal: **217 pruebas PHP / 3.440 aserciones**, **17 frontend**, tipos/build/Pint pasan. Tres pruebas nuevas cubren inversión no cero, propietario/contexto, paginación, ausencia de escrituras y consultas constantes. CI remota no ejecutada.

Matriz visual de **17 pantallas × 2 temas × 4 anchuras = 136 observaciones únicas**: 320/390/1024/1280 px, sin desbordamiento global ni referencias `aria-describedby` ausentes. Capturas revisadas de acceso, editor móvil con nombre largo y creación de categoría. Los últimos ajustes de plural y salida móvil se comprobaron en el servicio aplicado; el botón de salida mide 44 px en un viewport 320×480.

Recorridos en copia aislada de DB/discos:

- Login inválido en español conserva correo, limpia contraseña y enfoca el error; reintento válido y salida móvil funcionan.
- Nombre vacío y categoría duplicada muestran error con foco y conservan campos. Creación de empresa/categoría de demostración persiste; empresa vinculada presenta eliminación deshabilitada.
- Tasa cero conserva descripción/rate y enfoca la tasa. Configuración válida incrementa revisión 10→11; tras corregir el lector de textarea, segundo guardado conserva descripción y revisión 12. Productos/gastos no cambian y costo permanece **530.012 CLP**. No se alteran datos de los servicios originales.
- Nombre largo visible en catálogo y editor; búsqueda sin coincidencias se puede limpiar. Abrir/cancelar confirmación devuelve foco al disparador. No se ejecuta eliminación irreversible.
- Contexto de empresa, tema y densidad sobreviven navegación/recarga. Ficha de empresa uno conserva **530.012 CLP** mientras el selector mantiene empresa dos; Control de empresa dos muestra cero importaciones.
- Registro/reset/verificación se revisan visualmente; no se crean credenciales ni se envían correos.

Servicios originales: sesiones válidas y sin errores observados en consola. **21/21 tablas de dominio idénticas** en personal y QA, integridad `ok` y cero violaciones FK. Copias restauradas: **29/29 tablas iguales**. Los tres archivos de storage y `.env` permanecen iguales. QA mantiene revisiones **10/4**; personal mantiene cero importaciones.

## Alcance del backlog y recuperación

CR-011 acredita el mapa `UX_NAVIGATION.md`; CR-022 acredita navegación/contexto/tema/densidad/foco móvil. CR-008/009/012/013/014/021/024/027/030/040 conservan sus verificaciones globales pendientes. CR-041 mantiene el ensayo integral de release y CR-042 la aceptación humana.

Checkpoint previo `1cb2930`, en `work/backups/financial-catalog-checkpoint`: código, vendor, build, DB personal/QA, storage y entorno privado. No hay migraciones ni cambios de dependencias en este bloque. Para revertir código se restaura el checkout de ese commit junto con su build y se regenera autoload; reiniciar ambos servicios conservando los paths de DB/entorno. Este bloque no exige revertir datos. Las copias DB/storage sirven como evidencia de recuperación, y no deben reemplazar cambios legítimos posteriores del usuario sin conciliarlos.
