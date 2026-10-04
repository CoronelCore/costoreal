# Inertia v3: navegación y formularios

La instalación actual usa inertia-laravel 3.5.1 y Vue/core 3.8.0 con Laravel 13.34.0, PHP 8.5.8, Node 24.21.0 y Vite 7.3.6. Los lockfiles fijan esas versiones. La actualización se resolvió primero en un checkout aislado, siguiendo la [guía oficial](https://inertiajs.com/docs/v3/getting-started/upgrade-guide).

## Contratos adaptados

La configuración comprueba las páginas Vue en resources/js/Pages, respetando su mayúscula en Linux. SSR permanece desactivado porque la aplicación no tiene bundle ni servicio SSR. DevTools queda desactivado también en local: no registra ni expone datos financieros. El build y TypeScript usan ES2022. Se conserva la resolución explícita mediante el helper de Laravel Vite; no se introduce el plugin SSR opcional. Axios sigue atendiendo las peticiones JSON de costeo, borradores, conflictos y vistas previas; Inertia usa su transporte nativo.

La directiva Blade @inertia genera el script application/json con etiquetas escapadas; la prueba de protocolo incluye un nombre con cierre de script. Head usa data-inertia. InertiaConfig declara los props compartidos y mensajes flash. El contrato de importes sigue en los DTOs: decimales en texto y revisión obligatoria.

Los mensajes success/error/info de los redirects existentes se trasladan a page.flash mediante el middleware. El layout consume ese contrato nativo, fuera del estado de history. La prueba de navegador comprueba que el mensaje no vuelva al retroceder. HandleInertiaRequests.onVersionChange preserva los mensajes que la respuesta JSON ya había consumido antes del 409 que pide recargar el build. Se reprodujo la pérdida con el middleware anterior al hook; la prueba exige recepción única después del reload.

Los callbacks y bloqueos de useForm se revisaron con el nuevo momento de finalización: processing/progress terminan en onFinish. Los editores de producto/gasto/documento cierran o resetean directamente al guardar; los focos de errores se aplican después de habilitar los campos. El listener finish de useTabs conserva el hash solo en su ruta y se retira al desmontarse. No había listeners invalid/exception ni router.cancel que migrar; el contrato de tipos rechaza el antiguo evento exception.

## Verificación

214 pruebas PHP / 3.409 aserciones, 17 frontend, tipos/build/Pint y Composer validate/platform/audit pasan. Siete pruebas nuevas cubren script inicial, recarga parcial con errores siempre presentes, validación con bag nombrado y redirect 303, flash único, multipart privado sin recálculo, assets 409/diagnósticos y preservación de confirmación durante reload. CI está configurada, pero no se afirma ejecución remota.

En navegador se comprobó login/logout, credenciales inválidas y reintento; nombre vacío con foco/input conservados; pestañas/hash, atrás/adelante, historial 21–40 de 62 y recuperación del contexto del borrador al recargar. No hubo errores de consola en el recorrido. Multipart sobre copia de DB/discos: revisión 4→5, tercer documento con bytes/SHA iguales al fixture, 99.000 CLP conservados y cero nuevos cost_runs. Las pruebas de costeo, CSV, escenarios y reportes siguen pasando.

Los servicios personal/QA se reiniciaron con cliente y servidor alineados. Sus sesiones anteriores siguen válidas, la QA mantiene 930.012 CLP y las veintiuna tablas originales de dominio/identidad son iguales al checkpoint. Todas las tablas de las copias restauradas coinciden, integrity=ok y cero violaciones FK. Los discos originales coinciden por lista y hash. Inicialización limpia/repetida y cachés de config/routes/views se ensayaron solo en la copia; no hubo migraciones ni escrituras financieras originales. El temporal 8011 está detenido.

Npm conserva cinco advisories altos en las dependencias de Tailwind 3. La actualización mayor de CSS y su comparación visual siguen en CR-039/030; no se ejecutó audit fix --force. El cierre de CR-038 no cierra la QA global ni el ensayo integral CR-041.

## Recuperación del checkpoint

El respaldo local work/backups/inertia3-checkpoint conserva el código f0cf2ba, los manifests/lockfiles, vendor y build anteriores, DB personal/QA, storage y entorno privado con permisos 600. No se guarda en Git. Las bases after/restored comprueban la recuperación de copias; este paso no equivale al ensayo integral de release/rollback.

Para volver al estado anterior, detener escrituras, servicios y workers; reconstruir primero una copia con el código, vendor, build, entorno y datos del mismo checkpoint. Usar PHP 8.5, pues este checkpoint ya incluye Laravel 13. Recuperar las dependencias Node con sus manifests/lockfiles originales si se necesita recompilar. Limpiar las vistas/config/routes antes del arranque y comprobar login, reportes, identidad de tablas y hashes de archivos en esa copia. Activar solo un conjunto coherente de cliente/servidor/build después de reconciliarlo. CR-041 conserva el ensayo completo pendiente.
