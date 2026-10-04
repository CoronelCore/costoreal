# Tailwind 4: configuración y comparación visual

La instalación usa Tailwind CSS y @tailwindcss/vite 4.3.3, Vite 7.3.6 y Node 24.21.0. El lockfile fija las versiones. La migración empezó en una copia aislada del código 8041429 y siguió la [guía oficial](https://tailwindcss.com/docs/upgrade-guide).

## Configuración

Vite integra el plugin dedicado. app.css importa Tailwind, declara @theme, el plugin forms y la variante dark basada en la clase del documento. Las clases propias detectables usan @utility. No se mantienen en paralelo tailwind.config.js ni postcss.config.js. Se retiraron las dependencias directas PostCSS/autoprefixer y Alpine, sin consumidores en el cliente Vue actual.

Los tokens antiguos de sombras/radios/fuentes/tipografía tienen prefijo cr: no sobrescriben las variables que Tailwind 4 usa para sus utilidades. La comparación reprodujo el cambio accidental de radios antes de aislarlos. FinancialLayout conserva su paleta, fuente, tablas, estados y densidad. Button, Badge y Alert incluyen nombres de clases literales para que las variantes se compilen en producción.

El inventario de rutas, controllers, includes y componentes confirmó que las vistas activas son app.blade.php y exports/trip-pdf.blade.php. Se quitaron 45 Blade antiguos, tres clases de vistas sin consumidor, la página Test y 32 reglas/utilidades CSS sin llamadas confirmadas. El PDF se preservó byte a byte; tiene estilos propios y no depende de Tailwind.

## Navegadores y evidencia

Tailwind 4 exige Safari 16.4+, Chrome 111+ o Firefox 128+, según la guía oficial. La comparación se realizó en el navegador integrado de este equipo; no se afirma certificación en todos esos motores ni compatibilidad con versiones anteriores. Un cliente antiguo exige conservar un conjunto coherente del checkpoint anterior o cambiar sus requisitos de navegador.

Se recorrieron 21 pantallas autenticadas —Control, Analítica, ocho secciones de detalle, alta, perfil, configuración, empresas/categorías y papelera— y tres públicas —acceso, registro y recuperación—. Cada versión tiene 192 observaciones únicas: 24 pantallas × claro/oscuro × 320/390/1024/1280. Capturas y medidas DOM/CSS conservan texto, geometría, fuentes, bordes y sombras de los componentes visibles.

Antes, configuración a 320 px medía 337 px en ambos temas. Selectores width:100%/min-width:0 y acciones que se ajustan corrigen ese desborde. La matriz final no tiene overflow global. Los inputs de fecha nativos cambian ligeramente su ancho intrínseco; la paleta estándar de Tailwind 4 usa representaciones oklch. Los tokens financieros se conservan.

También se reprodujo una pérdida de foco del Modal original: Escape volvía a BODY después de abrir con teclado nativo. El componente conserva el botón inicial y restaura su foco tras la transición, si sigue conectado. Se comprobó en vista previa privada móvil, Cancelar y la confirmación del servicio QA. No se ejecutaron las acciones destructivas para comprobar el diálogo.

214 pruebas PHP / 3.409 aserciones, 17 frontend, tipos/build/Pint y Composer validate/platform pasan. Composer y npm audit actuales: cero advisories. Descargas reales: PDF inversión 530.012/utilidad 32.489 y Excel B15 numérico 530.012. Las sesiones anteriores siguen válidas. CSS total compilado: 112.553 → 99.905 bytes; ese tamaño no sustituye los perfiles de latencia CR-040. CI remota no ejecutada.

La comparación técnica de la migración cierra CR-039. CR-030 conserva zoom, contraste de todos los estados, nombres largos y recorrido de teclado global. Acceso y formularios antiguos de empresas/categorías/configuración necesitan adoptar la dirección financiera; también deben revisarse los títulos y la salida de sesión móvil. La aceptación humana final sigue en CR-042.

## Checkpoint y recuperación

work/backups/tailwind4-checkpoint conserva código 8041429, manifests/locks, vendor, build, DB personal/QA, storage y entorno privado con permisos 600. No se guarda en Git. Veintiuna tablas originales permanecen iguales; todas las tablas de las copias after/restored coinciden, integrity=ok y cero FK. Los archivos originales coinciden por lista y SHA-256. No hubo migraciones ni escrituras financieras originales.

Para recuperar: detener escrituras, servicios y workers; preparar una copia con código, dependencias, build, entorno y datos del mismo checkpoint. Este checkpoint ya usa PHP 8.5, Laravel 13 e Inertia 3. Restaurar manifests/locks de Node si se recompila, con npm ci; no combinar el viejo build con la nueva configuración CSS. Limpiar vistas/config/routes y comprobar autenticación, reportes, tablas y hashes antes de activar el conjunto reconciliado. La restauración de copias comprobada aquí no sustituye el ensayo integral de release/rollback CR-041.
