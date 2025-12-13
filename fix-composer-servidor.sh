#!/bin/bash

echo "═══════════════════════════════════════════════════════════"
echo "  Arreglo Rápido - Problema de Empresas en Servidor"
echo "═══════════════════════════════════════════════════════════"
echo ""

echo "📦 Paso 1: Actualizando autoload de Composer..."
composer dump-autoload --optimize

if [ $? -ne 0 ]; then
    echo "❌ Error al ejecutar composer dump-autoload"
    exit 1
fi

echo "✅ Autoload actualizado"
echo ""

echo "🧹 Paso 2: Limpiando todos los cachés de Laravel..."

php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan optimize:clear

echo "✅ Cachés limpiados"
echo ""

echo "🔄 Paso 3: Optimizando para producción..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✅ Optimización completa"
echo ""

echo "═══════════════════════════════════════════════════════════"
echo "  ¡Listo! Ahora recarga tu navegador"
echo "═══════════════════════════════════════════════════════════"
echo ""
echo "🌐 Visita: https://costoreal.kruje.cl/"
echo "   Presiona: Ctrl + Shift + R (recarga forzada)"
echo ""
