# CostoReal

**Del precio de compra al costo real de una importación o un viaje de compras.**

CostoReal es una aplicación gratuita y de código abierto que calcula cuánto te costó de verdad cada producto que compraste afuera. Registras lo que pagaste, los gastos (pasaje, hotel, flete, aduana…) y el tipo de cambio; CostoReal pasa todo a pesos chilenos (CLP), reparte los gastos entre los productos y te muestra, por unidad, cuánto pagaste, cuánto te costó y cuánto ganas a tu precio de venta.

Se instala en tu propio hosting: tú administras los datos, las cuentas y los documentos. Está pensada para importadores independientes, pequeños comercios y personas que traen productos de un viaje para vender.

## Qué puedes hacer

- Crear empresas e importaciones o viajes, con productos y gastos en su moneda original (dólar, euro, real, sol o yuan) o en pesos.
- Escribir los números como en Chile: `450.000`, `1.050,5`. Cada campo muestra cómo lo leyó.
- Repartir cada gasto por valor de compra, unidades, peso, volumen o a un producto específico, en pesos enteros y sin perder ni inventar un peso.
- Ver por producto lo pagado por unidad, el costo real por unidad, el precio de venta, la ganancia y el margen.
- Calcular precios por margen o recargo, comparar escenarios y cargar productos desde una planilla o un CSV.
- Adjuntar comprobantes privados y exportar PDF y Excel.
- Cerrar una compra, consultar su historial y reabrir con un motivo conservando el cierre anterior.
- Descargar un respaldo completo con un clic.

Los precios de venta muestran una **proyección**, no ventas realizadas. Las tasas las ingresa el usuario; no se consultan cotizaciones automáticamente. CostoReal no reemplaza un sistema contable, de facturación ni la determinación de impuestos o aranceles.

**Gratis** significa que el código no tiene costo de licencia. El dominio y el hosting se pagan aparte.

## Instalar sin terminal

Esta es la forma recomendada. Sirve en cualquier hosting con PHP 8.5, incluido el plan **Single de Hostinger**, que no trae terminal (SSH). Solo usas el panel de tu hosting y el navegador.

**Lo que necesitas:** un hosting con PHP 8.5, un dominio o subdominio apuntando a él, y el archivo `costoreal-vX.Y.Z.zip` de la [última versión](https://github.com/CoronelCore/costoreal/releases/latest). Descarga ese ZIP de la sección «Releases», no el botón verde «Code»: el de Releases ya trae todo instalado y compilado.

### 1. Prepara el sitio en hPanel

1. Entra a hPanel y abre tu sitio.
2. En **Configuración de PHP**, elige **PHP 8.5** y aprieta Actualizar. Hostinger trae 8.3 por defecto.
3. En **Seguridad → SSL**, activa el certificado gratis para que el sitio use `https://`.

### 2. Sube y descomprime el ZIP

1. Abre el **Administrador de archivos**.
2. Entra a la carpeta que **contiene** `public_html` (por ejemplo `domains/tu-dominio.cl`), no a `public_html` misma.
3. Sube `costoreal-vX.Y.Z.zip` ahí, haz clic derecho sobre él y elige **Extraer**, en esa misma carpeta.
4. Quedan dos carpetas, una al lado de la otra:

```text
tu-dominio.cl/
├── costoreal/      ← privada: código, datos y configuración. Nunca dentro de public_html.
└── public_html/    ← lo público: la entrada de la app y el diseño.
```

Si en `public_html` había un archivo `default.php` de Hostinger, bórralo. Puedes borrar también el ZIP.

### 3. Abre tu sitio y sigue el instalador

Entra a `https://tu-dominio.cl`.

- Si al hosting le falta algo (la versión de PHP, una extensión, un permiso), verás una página que dice exactamente qué arreglar en hPanel. Arréglalo y recarga.
- Si todo está bien, aparece **Instalar CostoReal**. Te pide:
  1. **El código de instalación.** Está en el archivo `costoreal/CODIGO-INSTALACION.txt`: ábrelo con el Administrador de archivos y cópialo. Así nadie más puede instalar tu sitio antes que tú.
  2. **Dónde guardar los datos.** Elige **SQLite**: es un archivo dentro de `costoreal`, no hay nada que configurar. Usa MySQL solo si tu hosting no tiene SQLite; en ese caso, primero crea la base en hPanel (Bases de datos → MySQL).
  3. **Tu cuenta**: nombre, correo y una contraseña de al menos 10 caracteres, con letras y números. Será la dueña de la instalación.
  4. Si quieres, **datos de demostración**: un viaje de compras a Brasil y una importación, ambos ficticios.

Al terminar entras directo a CostoReal. El archivo del código se borra solo y el instalador queda bloqueado.

### Usarla en un subdominio

Crea el subdominio en hPanel (Dominios → Subdominios). Hostinger le asigna una carpeta dentro de `public_html`, por ejemplo `public_html/costoreal`.

1. Extrae el ZIP en la carpeta que contiene `public_html`, igual que arriba.
2. Mueve el **contenido** de la carpeta `public_html` que trae el ZIP a la carpeta del subdominio.
3. Deja la carpeta `costoreal` donde quedó, fuera de `public_html`. La entrada la encuentra sola.

### Después de instalar

- **Nadie más puede crear cuentas.** Si quieres invitar a alguien, actívalo en **Perfil → Acceso de otras personas**. Cada cuenta ve solo sus datos.
- **Respalda de vez en cuando:** **Perfil → Respaldo → Descargar respaldo** baja un ZIP con la base de datos, los documentos y los pasos para restaurarlo. Guárdalo fuera del hosting.

### Actualizar a una versión nueva

1. Descarga un respaldo desde tu perfil.
2. Descarga el ZIP de la versión nueva y súbelo al mismo lugar que el primero.
3. Extráelo encima, aceptando reemplazar los archivos. Tu configuración, tus datos y tus documentos no vienen en el ZIP, así que se conservan.
4. Abre tu sitio. Si la versión nueva necesita preparar la base de datos, verás **Falta aplicar la actualización**: entra con la cuenta dueña y aprieta **Aplicar actualización**. Antes de aplicarla se guarda una copia de la base de datos.

## Problemas frecuentes

| Lo que ves | Qué hacer |
| --- | --- |
| «Falta preparar el hosting» | La página dice qué falta y dónde arreglarlo en hPanel. Arréglalo y recarga. |
| «Falta la carpeta costoreal» | La carpeta `costoreal` quedó dentro de `public_html` o no se subió. Muévela al lado de `public_html`. |
| El código no coincide | Cópialo de nuevo desde `costoreal/CODIGO-INSTALACION.txt`. Puedes escribirlo con o sin guion, en mayúsculas o minúsculas. |
| La página se ve sin diseño | Falta la carpeta `public_html/build`. Vuelve a extraer el ZIP. |
| Error 500 | Revisa `costoreal/storage/logs/laravel.log` en el Administrador de archivos. No lo compartas: puede traer datos privados. |
| Olvidé mi contraseña | La recuperación por correo necesita un correo configurado (ver «Correo» más abajo). |

## Probar con datos ficticios

Al instalar, marca **Cargar datos de demostración**. Crea una empresa ficticia, sin RUT, y dos compras inventadas:

- **Un viaje de compras a Brasil**: shampoo, crema y vestidos, con pasaje, hotel, comida y traslados repartidos por valor de compra.
- **Una importación de accesorios tecnológicos**: con flete, seguro, aduana, transporte, comisión y almacenaje, repartidos por peso, valor, unidades y volumen.

| Resultado de la demo · CLP | Viaje a Brasil | Accesorios |
| --- | ---: | ---: |
| Compra de productos | 1.386.000 | 1.485.000 |
| Gastos | 677.000 | 385.000 |
| Costo total | 2.063.000 | 1.870.000 |
| Venta proyectada | 2.658.700 | 2.730.000 |
| Utilidad proyectada | 595.700 | 860.000 |

Las referencias son **180 CLP/BRL** y **900 CLP/USD**, cifras inventadas. No son tarifas aduaneras ni cotizaciones vigentes. Puedes mandar las dos compras a la papelera cuando ya no las necesites.

## Correo

Sin configurar, CostoReal no envía correos y la recuperación de contraseña no llega. Para activarla, edita `costoreal/.env` con el Administrador de archivos y completa los datos SMTP de tu correo. En Hostinger: servidor `smtp.hostinger.com`, puerto `465`, cifrado `ssl` y la cuenta de correo que creaste en hPanel.

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=hola@tu-dominio.cl
MAIL_PASSWORD="TU_CONTRASENA_DEL_CORREO"
MAIL_FROM_ADDRESS=hola@tu-dominio.cl
```

`.env` es privado: no lo compartas ni lo subas a internet.

## Instalar con terminal (avanzado)

Para hostings con SSH (en Hostinger, desde el plan Premium) o servidores propios. Requisitos:

- **PHP 8.5**, tanto en la web como en la terminal.
- **Composer 2.**
- **Node 24 y npm 11** para compilar el diseño (puede ser en tu computador).
- **SQLite o MySQL/MariaDB.**
- **Extensiones de PHP:** `ctype`, `dom`, `fileinfo`, `filter`, `gd`, `iconv`, `mbstring`, `openssl`, `session`, `simplexml`, `tokenizer`, `xml`, `xmlreader`, `xmlwriter`, `zip`, `zlib`, más `pdo_sqlite` o `pdo_mysql`.

1. Clona el repositorio **al lado** de `public_html`, nunca dentro:
   ```sh
   cd ~/domains/tu-dominio.cl
   git clone https://github.com/CoronelCore/costoreal.git costoreal
   ```
   En Hostinger, la terminal puede usar otra versión de PHP que la web. Si `php -v` no dice 8.5, usa `export PATH="/opt/alt/php85/usr/bin:$PATH"`.
2. Instala las dependencias y compila el diseño:
   ```sh
   composer2 install --no-dev --prefer-dist --optimize-autoloader
   npm ci && npm run build
   ```
   Si compilas en tu computador, sube después la carpeta `public/build`.
3. Publica la entrada:
   ```sh
   mkdir -p ../public_html
   cp -a public/. ../public_html/
   cp deploy/hostinger/index.php ../public_html/index.php
   ```
4. Abre tu sitio y sigue el instalador, igual que en la instalación sin terminal.

Si prefieres hacerlo todo por terminal, en vez del paso 4:

```sh
cp .env.example .env
php artisan costoreal:initialize
php artisan db:seed --force
php artisan costoreal:create-user --email=tu-correo@example.com --name="Tu nombre"
```

Después edita `.env` para producción: `APP_ENV=production`, `APP_DEBUG=false` y tu `APP_URL`.

Para actualizar:

```sh
git pull
composer2 install --no-dev --prefer-dist --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
```

Copia otra vez los archivos públicos, como en el paso 3. Nunca uses `migrate:fresh` ni `db:wipe` sobre tus datos.

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

- **Pruebas:** `php artisan test`, `npm run test:frontend` y `npm run build`.
- **Paquete para subir:** con `npm run build` ya hecho, `php deploy/package.php v1.0.0` deja el ZIP en `dist/`. GitHub lo arma solo al publicar una etiqueta `v*`.
- **Más documentación:** [la política de costos](docs/COSTING_POLICY.md), [la arquitectura](docs/IMPLEMENTATION.md) y [el sistema de diseño](docs/DESIGN_SYSTEM.md). Las notas antiguas de desarrollo están en `docs/historial`.

La aplicación usa Laravel 13, Vue 3, Inertia 3 y Tailwind 4.

## Licencia

[MIT](LICENSE). Puedes descargar, usar, modificar y redistribuir CostoReal, incluso comercialmente, conservando el aviso de licencia. Se proporciona sin garantía. Las dependencias conservan sus respectivas licencias.

Reporta errores en [Issues de GitHub](https://github.com/CoronelCore/costoreal/issues). No adjuntes contraseñas, el archivo `.env`, respaldos ni documentos reales.

## Referencias de Hostinger

Revisadas el 20 de octubre de 2026:

- [Cambiar la versión de PHP](https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/)
- [Extraer archivos con el Administrador de archivos](https://www.hostinger.com/support/1583613-how-to-extract-archives-using-the-file-manager-in-hostinger/)
- [Carpeta raíz del sitio y de los subdominios](https://www.hostinger.com/support/1583494-what-is-the-path-to-your-website-s-root-home-directory-and-how-to-change-it-in-hostinger/)
- [Acceso SSH por plan](https://www.hostinger.com/support/1583245-how-to-connect-to-a-hosting-plan-via-ssh-in-hostinger/)
