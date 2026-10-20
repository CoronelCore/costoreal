# CostoReal

**Del precio de compra al costo real de una importación.**

CostoReal es una aplicación gratuita y de código abierto para registrar compras, convertir monedas, repartir gastos y conocer el costo final de cada producto en pesos chilenos (CLP). Se instala en tu propio alojamiento: tú administras la base de datos, las cuentas y los documentos.

Está pensada para importadores independientes, pequeños comercios y personas que necesitan ordenar sus compras internacionales. Cada cuenta tiene su propio espacio y puede organizar varias empresas. Incluye un diseño financiero claro, tema oscuro y uso desde el móvil.

## Qué puedes hacer

- Crear empresas e importaciones, con productos y gastos en su moneda original.
- Diferenciar tasas de referencia y tasas históricas de pagos.
- Distribuir gastos por valor, unidades, peso, volumen o producto específico.
- Consultar el costo por unidad y cómo se asignó cada gasto, incluidos los residuos de redondeo.
- Calcular precios por margen o recargo y comparar escenarios de costos.
- Importar productos desde CSV, adjuntar documentos privados y exportar PDF y Excel.
- Cerrar una revisión, consultar su historial y reabrir con un motivo conservando el cierre anterior.

Los precios de venta muestran una **proyección**, no ventas realizadas. Las tasas las ingresa el usuario; no se consultan cotizaciones automáticamente. CostoReal no reemplaza un sistema contable, de facturación ni la determinación de impuestos o aranceles.

## Descargar y requisitos

Descarga [el ZIP desde GitHub](https://github.com/CoronelCore/costoreal/archive/refs/heads/main.zip) o clona el repositorio:

```sh
git clone https://github.com/CoronelCore/costoreal.git
```

| Componente | Requisito de esta versión |
| --- | --- |
| PHP | **8.5.x**, tanto en la web como en la terminal |
| Composer | 2 |
| Node.js / npm | Node 24 y npm 11 para compilar el frontend; puedes hacerlo en tu equipo |
| Base de datos | SQLite con `pdo_sqlite` o MySQL/MariaDB con `pdo_mysql` |
| PHP y archivos | Extensiones requeridas por Composer, incluidas `mbstring`, `dom`, `xml`, `xmlreader`, `xmlwriter`, `simplexml`, `gd`, `zip`, `fileinfo`, `ctype`, `curl`, `openssl`, `tokenizer`, `session`, `filter`, `iconv`, `zlib` y el controlador PDO elegido |
| Servidor | HTTPS y raíz pública separada del código privado; en Hostinger, Apache/LiteSpeed con `.htaccess` |

La aplicación usa Laravel 13, Vue 3, Inertia 3 y Tailwind 4. Las versiones reproducibles están en `composer.lock` y `package-lock.json`. Usa `install` / `npm ci`, sin actualizar dependencias durante la instalación.

**Gratis** significa que el código no tiene costo de licencia. El dominio, el alojamiento y el correo que contrates se pagan por separado. No hay una instalación automática por el instalador de Laravel de hPanel: debes instalar este repositorio.

## Instalar en Hostinger, paso a paso

Esta guía parte de **un sitio nuevo y vacío** con acceso SSH, PHP 8.5 y Composer 2. No ejecutes estos pasos sobre otro sitio o una instalación con datos sin un respaldo previo. La disponibilidad de PHP, SSH y extensiones depende de tu plan; compruébala en hPanel antes de comenzar.

### 1. Preparar dominio, HTTPS y PHP

En hPanel, abre el panel del sitio, activa el certificado SSL y el acceso SSH, y selecciona **PHP 8.5** en Configuración PHP. Comprueba las extensiones indicadas arriba. Selecciona la versión en el plan y verifica también la versión de terminal; cambiarla solo para una carpeta no cambia necesariamente Composer.

Conéctate usando el comando SSH que muestra hPanel. Los siguientes ejemplos usan `tu-dominio.cl`: cámbialo por tu dominio real.

```sh
php -v
composer2 --version
cd ~/domains/tu-dominio.cl
```

Si `php -v` no devuelve 8.5, Hostinger documenta cómo seleccionar explícitamente el binario PHP. Cuando esté disponible en tu servidor:

```sh
export PATH="/opt/alt/php85/usr/bin:$PATH"
php -v
```

Si esa ruta no existe, solicita a Hostinger la ruta de PHP 8.5 para tu plan. No utilices `--ignore-platform-reqs` para saltarte requisitos.

### 2. Colocar el código fuera de `public_html`

```sh
cd ~/domains/tu-dominio.cl
git clone https://github.com/CoronelCore/costoreal.git costoreal
cd costoreal
```

Si descargaste el ZIP, extráelo en una carpeta llamada `costoreal`, al lado de `public_html`, incluyendo sus archivos ocultos. La estructura final será:

```text
tu-dominio.cl/
├── costoreal/          # aplicación privada, .env, vendor y almacenamiento
└── public_html/        # solo archivos públicos y el punto de entrada
```

No clones la aplicación completa dentro de `public_html`. El archivo `.htaccess` de la raíz del repositorio es un recurso heredado y no sustituye esta separación.

### 3. Compilar el diseño y JavaScript

El repositorio no incluye `node_modules`, `vendor` ni los archivos compilados. En tu **equipo local**, con Node 24 y npm 11, abre una copia de la misma versión del repositorio y ejecuta:

```sh
npm ci
npm run build
```

Sube la carpeta resultante `public/build` por SFTP o el administrador de archivos a `costoreal/public/build` en Hostinger. Debe incluir `manifest.json` y `assets/`.

Si tu plan ofrece Node 24 en SSH, puedes ejecutar los mismos comandos dentro de `costoreal` en el servidor. Node no necesita quedar ejecutándose: en producción se sirven los archivos compilados, sin `npm run dev`.

### 4. Instalar las dependencias PHP

Desde `~/domains/tu-dominio.cl/costoreal`:

```sh
composer2 install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer2 check-platform-reqs --no-dev
```

Si tu terminal ofrece Composer 2 como `composer`, puedes usar ese nombre después de verificar la versión. Corrige cualquier extensión o versión faltante antes de continuar.

### 5. Configurar tu archivo privado `.env`

Solo en una instalación nueva, crea el archivo desde la plantilla:

```sh
cp .env.example .env
chmod 600 .env
```

Edita `.env` en el servidor. Sustituye el dominio de ejemplo; conserva el resto de las opciones de la plantilla salvo las indicadas:

```dotenv
APP_NAME="CostoReal"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://tu-dominio.cl
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
LOG_LEVEL=warning
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_COOKIE=costoreal_session
SESSION_DRIVER=database
CACHE_STORE=database
```

Usa un nombre de cookie diferente si alojas varias instalaciones en el mismo dominio.

**Opción A: SQLite, para comenzar con uso personal.** No necesita usuario ni contraseña de base de datos. Asegúrate de que `pdo_sqlite` esté habilitado y coloca la ruta absoluta que corresponde a tu cuenta:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/home/TU_USUARIO/domains/tu-dominio.cl/costoreal/database/database.sqlite
```

No copies literalmente `TU_USUARIO`: consulta tu ruta con `pwd`. El inicializador creará el archivo SQLite si falta. La verificación automatizada del proyecto usa SQLite; su concurrencia es limitada, por lo que varias escrituras simultáneas pueden requerir reintentar.

**Opción B: MySQL/MariaDB.** En hPanel crea una base nueva y un usuario con permisos sobre ella, habilita `pdo_mysql` y utiliza los datos que te entrega el panel:

```dotenv
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=NOMBRE_DE_TU_BASE
DB_USERNAME=USUARIO_DE_TU_BASE
DB_PASSWORD="TU_CONTRASENA_PRIVADA"
```

Para el controlador específico de MariaDB puedes usar `DB_CONNECTION=mariadb`. Los nombres y la contraseña anteriores son marcadores, no credenciales del proyecto. No subas `.env` a GitHub ni lo compartas para pedir soporte.

### 6. Crear las tablas y las categorías

```sh
php artisan costoreal:initialize
php artisan db:seed --force
```

`costoreal:initialize` genera una clave solo si falta, crea el archivo SQLite si corresponde y ejecuta las migraciones pendientes. `db:seed` carga categorías; **no crea usuarios ni una contraseña predeterminada**. Nunca uses `migrate:fresh` o `db:wipe` sobre tus datos de trabajo.

### 7. Crear tu cuenta

```sh
php artisan costoreal:create-user --email=tu-correo@example.com --name="Tu nombre"
```

Escribe una contraseña propia cuando la terminal la solicite: mínimo 12 caracteres, con mayúsculas, minúsculas, números y símbolos. No se muestra ni se pasa como argumento. Si prefieres una contraseña aleatoria, agrega `--generate` y guárdala cuando aparezca una sola vez en la terminal.

No existe una cuenta de administrador compartida. El registro de nuevas cuentas está disponible en `/register`; cada cuenta tiene datos separados. Para una instalación estrictamente privada, restringe el acceso al sitio o revisa esa ruta antes de ofrecerlo a terceros.

### 8. Publicar únicamente los archivos públicos

En el sitio **nuevo y vacío**, desde la carpeta `costoreal`:

```sh
mkdir -p ../public_html
cp -a public/. ../public_html/
cp deploy/hostinger/index.php ../public_html/index.php
```

El segundo archivo es el punto de entrada preparado para esta estructura. Mantiene Laravel, `.env`, los documentos y la base de datos fuera de la carpeta pública y señala a Laravel dónde están los recursos públicos. Si cambias el nombre `costoreal`, ajusta `$basePath` en `public_html/index.php`.

Verifica que `public_html` contenga `.htaccess`, `index.php`, los favicons y `build/manifest.json`. No debe contener `.env`, `.git`, `vendor`, `database`, `storage` ni documentos financieros.

### 9. Permisos, caché y primer acceso

```sh
chmod -R u+rwX,go-rwx storage bootstrap/cache
php artisan config:cache
php artisan view:cache
```

El usuario que ejecuta PHP debe poder escribir en `costoreal/storage`, `costoreal/bootstrap/cache` y, si usas SQLite, en `costoreal/database`. En hPanel PHP suele ejecutarse con el usuario de tu alojamiento; si tu configuración usa otro usuario/grupo, ajusta los permisos con soporte. No uses permisos `777`.

No es necesario ejecutar `php artisan serve` en Hostinger ni crear un enlace `storage:link`: los documentos se descargan por rutas autenticadas. Tampoco es obligatorio un worker ni un cron para el flujo de compra y costeo actual.

Abre `https://tu-dominio.cl/login`, inicia sesión con la cuenta que creaste y crea una empresa o importación. Comprueba que `/test`, `/diagnostico.php` y `/clear-cache.php` no ejecuten herramientas de diagnóstico. El endpoint `/up` es la comprobación de salud de Laravel y se conserva.

## Probar con datos ficticios

Usa **otra instalación y otra base de datos**, sin empresas ni importaciones, y con una sola cuenta creada con `costoreal:create-user`. Después ejecuta, usando el correo de esa cuenta:

```sh
php artisan costoreal:demo tu-correo@example.com
```

La demo no crea cuentas ni contraseñas. Crea una empresa marcada como ficticia, sin RUT, y dos compras inventadas: un **viaje de compras a Brasil** (shampoo, crema y vestidos, con pasaje, hotel, comida y traslados repartidos por valor de compra) y una **importación de accesorios tecnológicos** (con flete, seguro, aduana, transporte, comisión y almacenaje, repartidos por peso, valor, unidades y volumen). Usa el motor de costeo actual, registra el historial y deja ambas en curso para que puedas revisarlas y cerrarlas desde la app. Ejecuta antes `php artisan db:seed` para que los gastos usen las categorías de base.

| Resultado de la demo · CLP | Viaje a Brasil | Accesorios |
| --- | ---: | ---: |
| Compra de productos | 1.386.000 | 1.485.000 |
| Gastos | 677.000 | 385.000 |
| Costo total | 2.063.000 | 1.870.000 |
| Venta proyectada | 2.658.700 | 2.730.000 |
| Utilidad proyectada | 595.700 | 860.000 |

Las referencias son **180 CLP/BRL** y **900 CLP/USD**, cifras inventadas. No son tarifas aduaneras ni cotizaciones vigentes. No se importan archivos ni documentos personales. Si ya hay empresas/importaciones, o más de una cuenta, el comando se detiene sin cambiar datos; repetirlo tampoco duplica la demo. El seeder normal nunca carga esta demostración.

## Correo, respaldo y actualizaciones

- La plantilla usa correo en logs. Para recuperar contraseñas por email, configura `MAIL_MAILER=smtp`, servidor, puerto, usuario, contraseña y remitente de tu proveedor, y vuelve a ejecutar `php artisan config:cache`. Trata los logs como privados: con correo en logs pueden contener enlaces de recuperación.
- Respalda juntos la base de datos, `.env` y `storage/app/private` en un destino privado. Conserva `APP_KEY`; no regeneres la clave al actualizar. Para SQLite usa una copia consistente con la aplicación detenida o una herramienta de backup de SQLite.
- Antes de actualizar, respalda y revisa las notas de cambios. Descarga la versión nueva, ejecuta `composer2 install --no-dev --prefer-dist --optimize-autoloader`, compila/sube de nuevo `public/build`, ejecuta `php artisan migrate --force` y vuelve a copiar los archivos públicos como en el paso 8. Conserva tu `.env` y tus datos; no vuelvas a ejecutar la demo. Renueva las cachés de configuración y vistas.
- Reporta errores mediante [Issues de GitHub](https://github.com/CoronelCore/costoreal/issues). No adjuntes contraseñas, `.env`, respaldos ni documentos reales. Para un problema de seguridad, usa el canal privado de contacto del propietario del repositorio.

## Problemas frecuentes

| Síntoma | Qué revisar |
| --- | --- |
| Pantalla sin estilos / error del manifiesto Vite | Falta `public_html/build/manifest.json` o `assets/`; compila y sube toda la carpeta `build` |
| Error de versión PHP o extensiones | Comprueba tanto PHP web como `php -v`, y ejecuta `composer2 check-platform-reqs --no-dev` |
| Error 500 | Consulta en privado `storage/logs/laravel.log`; revisa `.env`, `APP_KEY`, tablas y permisos. Mantén `APP_DEBUG=false` |
| Error 419 al entrar | Revisa HTTPS, `APP_URL`, cookie segura y permisos de sesiones; después de cambiar `.env`, regenera `config:cache` |
| Error de conexión a base de datos | Comprueba nombre, usuario y permisos de MySQL, o ruta absoluta/controlador/permisos de SQLite |
| Recuperación de contraseña sin correo | Configura SMTP; el modo `log` no envía mensajes |

## Desarrollo local

```sh
composer install
cp .env.example .env
php artisan costoreal:initialize
php artisan db:seed
php artisan costoreal:create-user --email=tu-correo@example.com --name="Tu nombre"
npm ci
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Usa un checkout nuevo para esos comandos. La plantilla usa SQLite. `bin/php` ayuda a seleccionar PHP 8.5; puedes indicar su ruta con `COSTOREAL_PHP_BIN`. Para desarrollar el frontend con recarga en vivo, usa `npm run dev` junto con el servidor PHP.

Pruebas relevantes: `php artisan test`, `npm run test:frontend` y `npm run build`. Las pruebas PHP usan SQLite en memoria y almacenamiento simulado. Consulta [la política de costos](docs/COSTING_POLICY.md), [la arquitectura](docs/IMPLEMENTATION.md) y [el sistema de diseño](docs/DESIGN_SYSTEM.md).

## Licencia

[MIT](LICENSE). Puedes descargar, usar, modificar y redistribuir CostoReal, incluso comercialmente, conservando el aviso de licencia. Se proporciona sin garantía. Las dependencias conservan sus respectivas licencias.

## Referencias de instalación

Guía revisada el 20 de octubre de 2026 a partir de la configuración del proyecto y la documentación oficial de Hostinger:

- [Acceso SSH y planes compatibles](https://www.hostinger.com/support/1583245-how-to-connect-to-a-hosting-plan-via-ssh-in-hostinger/).
- [Seleccionar la versión PHP](https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/).
- [Composer 2 y diferencias de PHP entre web y terminal](https://www.hostinger.com/support/5792082-how-to-solve-common-composer-issues-at-hostinger/).

La guía no supone acceso a tu cuenta Hostinger. Comprueba los requisitos en tu alojamiento antes de instalar; las verificaciones locales no sustituyen una prueba en tu plan concreto.
