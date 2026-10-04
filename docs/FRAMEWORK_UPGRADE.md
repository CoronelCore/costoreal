# Laravel 13 y runtime PHP 8.5

## Versiones verificadas

PHP 8.5.8, Laravel 13.34.0, Tinker 3.0.2, Excel 4.0.3, PhpSpreadsheet 5.10.0, PHPUnit 12.5.37 y Symfony 8.1. Los lockfiles fijan la instalación; composer.json acepta PHP 8.5 y rechaza otras ramas. Node sigue en 24 LTS. Inertia continúa con sus adaptadores anteriores hasta la siguiente entrega, CR-038; Tailwind corresponde a CR-039.

La actualización sigue la [guía oficial de Laravel 13](https://laravel.com/framework/docs/13.x/upgrade) y la [guía de Excel 4](https://github.com/SpartnerNL/Laravel-Excel/blob/4.x/UPGRADE-4.x.md). Excel 3.1 dependía de PhpSpreadsheet 1.x, que rechazó el runtime 8.5 al resolver. Se adoptó Excel 4 con PhpSpreadsheet 5, se tiparon los métodos styles y se añadió el marcador Export al libro de múltiples hojas. SafeValueBinder conserva los textos ajenos como texto literal y los importes como números.

## Cambios de configuración y ejecución

- bin/php selecciona Homebrew PHP 8.5 o el PHP disponible en PATH. COSTOREAL_PHP_BIN permite una ruta explícita; una versión incompatible se rechaza antes de ejecutar Artisan. .php-version y CI indican 8.5.
- La configuración PDO usa Pdo\Mysql::ATTR_SSL_CA, eliminando la constante obsoleta evaluada también al iniciar el entorno SQLite.
- cache.serializable_classes=false; el proyecto no almacena objetos propios en cache. La serialización de sesiones se fija en php para conservar la lectura de los payloads previos. No se cambia APP_KEY ni el nombre de cookie configurado por la aplicación.
- Laravel instala PreventRequestForgery por defecto. No se añaden excepciones CSRF ni se permite same-site de forma global. Las pruebas adicionales ejecutan su política real, desactivando únicamente el bypass que ese middleware aplica automáticamente al entorno de test: ausencia de token, origen cross-site/same-site, fallback con token, X-XSRF-TOKEN cifrado y same-origin.
- fix-composer-servidor.sh usa el wrapper, se detiene ante errores y resuelve el directorio del proyecto antes de regenerar autoload/cachés. Su ejecución se ensayó solo en el checkout aislado.

La suite completa de nuevas exportaciones superó el límite CLI original de 128 MB en una ejecución acumulada. PHPUnit recoge objetos cada diez pruebas y tiene un límite explícito de 256 MB; el ensayo final aislado indicó 113 MB. El servicio HTTP conserva 128 MB: se verificaron los reportes reales de la QA, sin afirmar que ese límite ya cubra cualquier volumen. Los perfiles máximos permanecen en CR-040.

## Evidencia y recuperación

Resolución e instalación realizadas primero en work/framework13-checkout. Inicialización de DB nueva y repetición conservan clave/base y cero cuentas automáticas. Ensayo de autoload, config:cache, route:cache y view:cache en esa copia. Después de verificar, se sustituyeron dependencias y se reiniciaron los servicios personal/QA con bin/php. No hubo migraciones de esquema en esta entrega.

207 pruebas PHP / 3.392 aserciones, 17 frontend, tipos/build/Pint, composer validate --strict, check-platform-reqs e instalación dry-run pasan. composer audit --locked: cero advisories y paquetes abandonados. CI se actualizó, pero no se afirma ejecución remota de GitHub.

Browser real: login con QA existente, validación de Nombre vacío/foco, sesión personal existente aún legible, descarga PDF/XLSX. Libro descargado: Resumen/Gastos/Productos y B15 numérico = 530.012 CLP. Multipart en DB/discos aislados: revisión 3→4, segundo documento privado con bytes verificados, 99.000 CLP conservados y cero nuevos cost_runs. La pestaña y el servicio temporal 8011 se retiraron; 8000 y 8010 permanecen activos.

Veintiuna tablas originales personal/QA permanecen idénticas respecto al checkpoint anterior a instalar. Las copias restauradas concilian, integrity=ok y FK=0. Discos originales iguales. Respaldo local incluye código anterior, vendor anterior, DB, discos y entorno privado; nunca se incluye en Git. Para recuperar, detener escrituras/workers y restaurar código/dependencias/DB/discos/entorno del mismo checkpoint con PHP 8.3; comprobar en copia antes de activar. El ensayo integral de release/rollback sigue en CR-041 y no se sustituye por esta verificación de actualización.

CR-037 verificado. CR-038/039, la QA global, rendimiento, ensayo integral y aceptación siguen abiertos en docs/backlog.csv.
