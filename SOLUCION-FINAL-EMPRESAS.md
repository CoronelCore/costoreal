# 🎯 SOLUCIÓN AL PROBLEMA DE EMPRESAS EN EL SERVIDOR

## 🔴 El Problema

Cuando agregaste la funcionalidad de empresas y sus links al menú, subiste los archivos al servidor PERO:

- ❌ NO actualizaste el autoload de Composer
- ❌ NO limpiaste los cachés de Laravel

**Resultado:** Las nuevas clases (`CompanyHelper`, `CompanyComposer`) NO están siendo cargadas, causando que las variables como `$activeCompany`, `$activeCompanyName`, etc. **no existan** en las vistas.

---

## ✅ SOLUCIÓN (3 Minutos)

### **Opción A: Por SSH (RECOMENDADO)**

Conéctate por SSH a tu servidor y ejecuta:

```bash
cd /ruta/de/tu/aplicacion

# 1. Actualizar autoload de Composer (CRÍTICO)
composer dump-autoload --optimize

# 2. Limpiar TODOS los cachés
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan optimize:clear

# 3. Cachear para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Luego recarga tu navegador con `Ctrl + Shift + R`**

---

### **Opción B: Usando un script**

1. Sube el archivo `fix-composer-servidor.sh` a la raíz de tu aplicación
2. Conéctate por SSH
3. Ejecuta:

```bash
chmod +x fix-composer-servidor.sh
./fix-composer-servidor.sh
```

---

### **Opción C: Manualmente desde cPanel Terminal**

Si tu hosting tiene Terminal en cPanel:

1. Abre cPanel → **Terminal**
2. Navega a tu aplicación:
   ```bash
   cd costoreal.kruje.cl  # o la ruta correcta
   ```
3. Ejecuta estos comandos UNO POR UNO:

```bash
composer dump-autoload --optimize
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan optimize:clear
```

---

## 🔍 ¿Por Qué Pasó Esto?

Cuando agregaste las nuevas clases en local:
- `app/Helpers/CompanyHelper.php`
- `app/View/Composers/CompanyComposer.php`
- `app/Providers/AppServiceProvider.php` (modificado)

Composer las registró en el autoload automáticamente.

**PERO** al subir estos archivos al servidor, el autoload del servidor **NO SE ACTUALIZÓ**, entonces el servidor no sabe que estas clases existen.

---

## 📋 Checklist para Futuros Deployments

Cuando subas cambios al servidor que incluyan **nuevas clases**, SIEMPRE ejecuta:

```bash
composer dump-autoload --optimize
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## ✅ Verificación

Después de ejecutar los comandos:

1. Abre: `https://costoreal.kruje.cl/`
2. Presiona `Ctrl + Shift + R` (recarga forzada)
3. Verifica que:
   - ✅ La página carga con estilos correctos
   - ✅ El menú muestra el selector de empresas
   - ✅ NO hay errores en la consola del navegador (F12)

Si ves errores como:
```
Class 'App\Helpers\CompanyHelper' not found
```

Significa que necesitas ejecutar `composer dump-autoload` de nuevo.

---

## 🚨 Si Aún No Funciona

Si después de ejecutar los comandos sigue sin funcionar:

1. **Verifica que subiste TODOS estos archivos al servidor:**
   ```
   app/Helpers/CompanyHelper.php
   app/View/Composers/CompanyComposer.php
   app/Providers/AppServiceProvider.php
   ```

2. **Verifica que la carpeta `vendor/` existe en el servidor**
   - Si NO existe, ejecuta: `composer install --no-dev --optimize-autoloader`

3. **Reinicia PHP-FPM** (desde cPanel o ejecutando):
   ```bash
   sudo systemctl restart php8.2-fpm
   ```

---

## 💡 Explicación Técnica

Laravel usa Composer para **autocargar clases automáticamente**. Cuando creas una nueva clase:

1. Composer la registra en `vendor/composer/autoload_*.php`
2. Laravel puede encontrarla y usarla

**El problema:** Cuando subes archivos directamente (FTP/cPanel File Manager), el archivo `vendor/composer/autoload_*.php` NO se actualiza.

**La solución:** Ejecutar `composer dump-autoload` regenera estos archivos de autoload con las nuevas clases.

---

**Fecha:** 2025-11-04
**Tiempo estimado:** 3 minutos
**Dificultad:** Baja
