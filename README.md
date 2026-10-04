# Costo Real

Control personal de importaciones: compra, conversión de moneda, gastos, reparto exacto, precio proyectado, revisiones y reportes. Preparado para crecer mediante módulos internos de acceso, importaciones, costeo, precios, documentos y reportes.

## Entorno actual

PHP 8.5 con mbstring, pdo_sqlite, sqlite3, gd, zip y las extensiones exigidas por Composer. Composer 2. Node 24 LTS y npm. Laravel 13, Excel 4 / PhpSpreadsheet 5 y PHPUnit 12. SQLite para uso local. Los lockfiles forman parte del repositorio.

En este equipo, PHP 8.5 está en `/opt/homebrew/opt/php@8.5/bin/php`. Antes de usar Composer en este checkout:

```sh
export PATH="/opt/homebrew/opt/php@8.5/bin:$PATH"
```

El ejecutable `bin/php` selecciona PHP 8.5 en Homebrew o en tu PATH; `COSTOREAL_PHP_BIN` permite indicar otra instalación 8.5 y rechaza una versión incompatible. Usa `bin/php artisan ...` para evitar ejecutar el checkout con otro PHP. Los servicios de este equipo usan ese runtime.

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

CI ejecuta formato, pruebas PHP, pruebas de presentación decimal y fechas, y build sobre PHP 8.5 / Node 24. PHPUnit limita la suite completa a 256 MB y recoge objetos periódicamente entre pruebas de exportación; ese ajuste no cambia el límite del servicio HTTP. Una configuración de CI no demuestra por sí misma que haya corrido en GitHub.

Las pruebas usan SQLite en memoria y archivos falsos; la concurrencia se verifica con dos procesos y una base temporal independiente. La prueba visual usa una base independiente fuera del checkout, con datos claramente sintéticos; no llena las importaciones personales con ejemplos.

## Arquitectura y decisiones

- `app/Modules/Access`: contexto autorizado, reutilizado por solicitud.
- `app/Modules/Importations/Actions/MutateTrip`: transacción, bloqueo, control de versión, costeo y auditoría.
- `app/Modules/Costing/Domain`: motor decimal puro, reparto determinista y resultado conciliable.
- `app/Modules/Costing/Actions/RecordCostRun`: snapshots y asignaciones conservadas.
- `app/Modules/Reporting/ReportTrip`: reconstrucción de reportes históricos.
- `app/Modules/Documents`: almacenamiento privado, metadatos y staging con limpieza en rollback.
- `app/Modules/Pricing`: proyección que distingue precios pendientes y pérdidas, y cálculo de precio por margen/recargo con comisión opcional.
- `resources/js/Components/Importations`: resumen, hoja, gastos, documentos, precios, revisión e historial.

Consulta `docs/COSTING_POLICY.md` para precisión, tasas y cierre; `docs/IMPLEMENTATION.md` y `docs/backlog.csv` para la ejecución del plan. Laravel 13 y PHP 8.5 ya forman parte del entorno actual. Inertia v3 ya está alineado y verificado; su contrato y recuperación se documentan en `docs/INERTIA_UPGRADE.md`. La migración de CSS conserva su validación visual independiente.

## Funciones avanzadas actuales

En la hoja de productos, «Pegar / importar CSV» permite mapear columnas, revisar cada fila e importar todo el lote sin duplicar reintentos. Acepta hasta 1.000 filas, texto UTF-8 y punto decimal. En Precios, «Precio por objetivo» compara precios por margen o recargo, con comisión opcional y redondeo hacia arriba. La vista previa no escribe costos; aplicar requiere la revisión vigente. Consulta la política para las fórmulas y la diferencia entre utilidad del resumen y utilidad tras comisión.

SQLite limita la espera de bloqueos mediante `DB_BUSY_TIMEOUT` (1.000 ms por defecto); la acción de mutación usa una revisión condicional y reintentos acotados. Las rutas heredadas aún aceptan una versión omitida mientras se completa la migración de sus clientes.
