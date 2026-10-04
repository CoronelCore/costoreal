# Costo Real

Control personal de importaciones: compra, conversión de moneda, gastos, reparto exacto, precio proyectado, revisiones y reportes. Preparado para crecer mediante módulos internos de acceso, importaciones, costeo, precios, documentos y reportes.

## Entorno puente

PHP 8.3 con mbstring, pdo_sqlite, sqlite3, gd, zip y las extensiones exigidas por Composer. Composer 2. Node 24 LTS y npm. SQLite para uso local. Los lockfiles forman parte del repositorio.

En este equipo, PHP 8.3 está en `/opt/homebrew/opt/php@8.3/bin/php`. Antes de usar Composer en este checkout:

```sh
export PATH="/opt/homebrew/opt/php@8.3/bin:$PATH"
```

Instalación de un checkout limpio:

```sh
composer run setup
php artisan db:seed
php artisan costoreal:create-user --email=tu-correo@example.com --name="Tu nombre"
php artisan serve --host=127.0.0.1 --port=8000
```

La inicialización conserva una clave APP_KEY y una base de datos existentes. El seeder principal solo crea categorías base; no genera cuentas de ejemplo ni contraseñas conocidas. El comando de usuario pide la contraseña sin mostrarla y rechaza correos existentes. `--generate` crea una contraseña aleatoria y la muestra una única vez en la terminal. No pases contraseñas como argumentos.

Los documentos nuevos se guardan en `storage/app/private/documents`. Se sirven por rutas autenticadas y con comprobación del padre; no necesitan un enlace público de storage. Conserva la clave y copia DB + storage al respaldar. Los datos reales, `.env`, documentos y contraseñas no se incluyen en Git.

## Verificación

```sh
php vendor/bin/pint --test
php artisan test
npm run test:frontend
npm run build
composer audit
npm audit
```

CI ejecuta formato, pruebas PHP, pruebas de presentación decimal y fechas, y build sobre PHP 8.3 / Node 24. Una configuración de CI no demuestra por sí misma que haya corrido en GitHub.

Las pruebas usan SQLite en memoria y archivos falsos. La prueba visual usa una base independiente fuera del checkout, con datos claramente sintéticos; no llena las importaciones personales con ejemplos.

## Arquitectura y decisiones

- `app/Modules/Access`: contexto autorizado, reutilizado por solicitud.
- `app/Modules/Importations/Actions/MutateTrip`: transacción, bloqueo, control de versión, costeo y auditoría.
- `app/Modules/Costing/Domain`: motor decimal puro, reparto determinista y resultado conciliable.
- `app/Modules/Costing/Actions/RecordCostRun`: snapshots y asignaciones conservadas.
- `app/Modules/Reporting/ReportTrip`: reconstrucción de reportes históricos.
- `app/Modules/Documents`: almacenamiento privado, metadatos y staging con limpieza en rollback.
- `app/Modules/Pricing`: proyección que distingue precios pendientes y pérdidas.
- `resources/js/Components/Importations`: resumen, hoja, gastos, documentos, precios, revisión e historial.

Consulta `docs/COSTING_POLICY.md` para precisión, tasas y cierre; `docs/IMPLEMENTATION.md` y `docs/backlog.csv` para la ejecución del plan. Las actualizaciones mayores de framework, adaptadores y CSS se validan en entregas independientes del entorno puente.
