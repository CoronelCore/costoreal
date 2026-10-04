# Conservación, documentos heredados y recuperación

## Política del producto

Uso personal con conservación indefinida por defecto. No existe un plazo de purga automática, job de eliminación permanente ni limpieza de archivos en papelera. Esta es una decisión del producto, no una declaración de cumplimiento de una norma legal.

- La papelera de importaciones conserva sus productos, gastos, documentos, ejecuciones y auditoría. Restaurar respeta la propiedad y las reglas de estado existentes.
- La papelera de documentos retira el acceso HTTP al archivo y mantiene sus bytes. Restaurar exige una importación activa y la revisión vigente. Una importación cerrada mantiene su documentación y reportes; sus mutaciones normales siguen bloqueadas.
- Adjuntar, mover y restaurar documentos incrementa la revisión y registra actor/antes/después de forma atómica, sin recalcular importes ni generar un cost_run. Los archivos ya conservados se mantienen ante un fallo de metadatos; los errores permiten reintentar sin duplicar la operación.
- Cada uso del mismo contenido mantiene su asociación independiente. El aviso de contenido repetido cuenta solamente los documentos activos de la importación autorizada.
- Eliminar una cuenta exige contraseña actual y ausencia de expedientes, incluso en papelera, referencias a sus empresas/categorías y registros históricos de actor. El servidor vuelve a comprobarlo dentro de una transacción antes de cerrar la sesión. Las categorías personales sin uso se retiran junto a una cuenta vacía para evitar convertirlas en catálogo compartido.
- La FK de trips.user_id restringe borrado físico del propietario; trips.company_id usa SET NULL para impedir un borrado en cascada del expediente. La acción normal de eliminar empresa sigue bloqueada si conserva importaciones, incluso en papelera.

## Historia de cálculos

Historial → Revisiones conservadas consulta metadatos autorizados en páginas de veinte, sin el límite anterior de cincuenta. Permite filtrar cierres y revisión exacta. El máximo id del primer resultado ancla la paginación; Buscar revisiones inicia una lectura nueva. Los enlaces PDF/Excel seleccionan el snapshot original de esa ejecución.

El detalle inicial carga solamente la última ejecución; abrir Historial solicita la lista. No se envían entradas, resultados completos, rutas privadas ni snapshots en la lista. La auditoría registra por separado papelera, recuperación y reapertura, que no representan nuevos cálculos. Una copia heredada conservada identifica su procedencia incompleta y no se presenta como un cálculo histórico verificado.

## Discos y entornos aislados

Los valores predeterminados son storage/app/private para el disco local privado y storage/app/public para el público heredado. Se pueden configurar rutas absolutas con COSTOREAL_PRIVATE_STORAGE_ROOT y COSTOREAL_PUBLIC_STORAGE_ROOT. El primero debe existir fuera del directorio servido por HTTP y del almacenamiento público. Cambiar una base de datos de entorno exige seleccionar también sus discos correspondientes: una DB aislada con almacenamiento compartido no constituye una prueba aislada.

Las descargas y previews usan el controlador autorizado por propietario/importación/documento. El uploader desconocido de un archivo heredado permanece null. No se expone el storage_path mediante el DTO. Los tipos no reconocidos se sirven como application/octet-stream en descarga, sin inventar una vista previa segura.

## Inventario y migración heredada

Antes de aplicar: copia consistente de DB, ambos discos y versión del código; ejecuta sobre una copia y reconcilia importes/filas/hashes. El comando está probado con discos locales. No certifica almacenamiento remoto ni otros motores mediante los ensayos SQLite.

```sh
php artisan costoreal:legacy-documents --limit=100
php artisan costoreal:legacy-documents --apply --limit=100
php artisan costoreal:legacy-documents --apply --after-product=123 --after-expense=456 --limit=100
```

Sin --apply solo inventaría referencias públicas originales de products.imagen en productos/ y expenses.comprobante en comprobantes/. Rechaza URLs, rutas absolutas, segmentos vacíos/dot/dotdot, caracteres no reconocidos y enlaces simbólicos. Las rutas documents/ ya migradas no se reprocesan. Un origen ausente, inseguro o modificado requiere revisión; el comando no busca una coincidencia por nombre ni inventa archivos.

Al aplicar, copia por stream a una ruta privada determinista, verifica SHA-256 y confirma Document + referencia + legacy_document_moves en una transacción. No modifica importes, tasas, fechas financieras, timestamps del padre, revisión ni cost_runs. Una asociación document_id existente se conserva y el archivo heredado queda accesible desde la biblioteca. La copia pública se retira después de confirmar metadatos y comprobar hash/ausencia de referencias públicas restantes. Si varios padres usan un archivo, se espera a migrarlos todos antes de retirarlo.

Estados del manifiesto: copied, awaiting_references, cleanup_failed y retired. El próximo --apply reintenta retiros pendientes aunque el padre ya use documents/. Una copia privada corrupta o un archivo público cambiado bloquea el retiro; se conserva evidencia para revisión. El bloqueo de proceso .legacy-document-migration.lock impide dos comandos concurrentes sobre el mismo almacenamiento y se libera al salir o morir el proceso.

El checkpoint indica el último id examinado por tipo. Si un registro falló, corregir su causa y reintentar desde un checkpoint anterior a ese id. El límite es por tipo y por lote de retiros. La aplicación devuelve código 1 si hay errores o trabajo pendiente, aunque haya completado un lote válido; no representa rollback de todo el lote. Repetir es recuperable y no duplica asociaciones confirmadas. El conteo de referencias pendientes está acotado a 2.000; cero errores, cero pendientes, cero retiros y cero archivos sin referencia son las condiciones de finalización.

### Archivos públicos sin asociación

El inventario también detecta archivos sin referencia en los dos directorios conocidos. Para conservarlos fuera del acceso público sin atribuir un propietario:

```sh
php artisan costoreal:legacy-documents --apply --quarantine-orphans --limit=100
```

Se copian a documents/quarantine, se verifica hash y se registra legacy_document_quarantine antes de retirar el origen público. No se crea un Document ni se asigna importación/usuario ficticio. El operador conserva ese manifiesto y los bytes en los respaldos; una asociación futura requiere evidencia fiable y un procedimiento específico. La opción es explícita y los retiros incompletos son reintentables.

## Restauración y rollback

Una recuperación consistente usa la misma versión del código, DB, disco privado y disco público del checkpoint. Poner el servicio y workers fuera de escritura; restaurar en una copia; comprobar integrity_check/foreign_key_check, conteos y hashes de tablas/documentos; activar únicamente después de reconciliar. Los SQLite deben copiarse con la API backup o equivalente consistente, incluyendo su estado WAL mediante esa API.

Las migraciones 050000 y 060000 se ensayaron en copias: subir, bajar dos pasos y volver a subir. El down de 050000 restaura las FK históricas CASCADE; solo sirve para rollback fuera de servicio junto al código/DB correspondientes. Bajar 060000 elimina los manifiestos, pero no deshace la migración de bytes/referencias: recuperar un estado anterior exige restaurar el conjunto DB + ambos discos. No ejecutar down sobre el servicio activo como sustituto de esa recuperación.

## Evidencia de esta entrega

203 pruebas PHP / 3.363 aserciones, 17 frontend, tipos/build/Pint. La copia aislada mostró 62 cálculos en cuatro páginas y recuperó el cierre de revisión 3. La imagen heredada abrió con 420×240 píxeles; mover/restaurar avanzó la revisión 1→3 conservando 99.000 CLP y cero cost_runs nuevos. El huérfano quedó privado sin propietario. Perfil: error de nombre vacío en español y foco en el campo; cuenta con expediente bloqueada; claro/oscuro y 320 px sin desbordamiento global.

Personal y QA conservaron diecinueve tablas originales idénticas. Se restauraron y compararon veintiuna tablas, integrity=ok y FK=0. La copia de demostración recuperó DB y bytes privados con hashes iguales. En los servicios originales el inventario heredado fue cero pendientes/huérfanos/errores. No se introdujeron fixtures en la base personal.

Esto verifica CR-028 y CR-036. La matriz global de accesibilidad, los upgrades de framework/tooling, los perfiles restantes y el ensayo integral de release/aceptación continúan en el backlog.
