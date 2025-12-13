# 🔍 Cómo Ver y Usar el Selector de Empresas

## 📍 Ubicación del Selector

El selector de empresas está en el **menú de navegación superior**, entre "Analítica" y tu foto de perfil.

```
┌─────────────────────────────────────────────────────────────┐
│  CostoReal   Importaciones   Analítica   [📊 Empresa] 👤   │
└─────────────────────────────────────────────────────────────┘
                                             ↑
                                    Selector aquí
```

Debería verse como un **botón con borde** que muestra:
- Un ícono de empresa (📊 o similar)
- El nombre de la empresa actual o "Todas las empresas"
- Una flecha hacia abajo (▼)

---

## ❓ Si NO lo ves

### **Causa más probable:** Alpine.js no se está ejecutando

Alpine.js es la librería JavaScript que hace funcionar los dropdowns (menús desplegables). Si no funciona, el botón existe pero el dropdown no se abre.

### **Solución 1: Verificar en el navegador**

1. Abre tu sitio: `https://costoreal.kruje.cl/`
2. Presiona `F12` para abrir las herramientas de desarrollador
3. Ve a la pestaña **Console**
4. Busca errores en rojo relacionados con Alpine o JavaScript

Si ves errores, toma una captura y revísalos.

### **Solución 2: Limpiar caché del navegador**

1. En tu navegador, presiona `Ctrl + Shift + Delete`
2. Selecciona "Imágenes y archivos en caché"
3. Haz clic en "Borrar datos"
4. Recarga la página con `Ctrl + Shift + R`

### **Solución 3: Verificar que Alpine.js está cargando**

En la consola del navegador (F12), escribe:

```javascript
typeof Alpine
```

Si responde `"object"` → ✅ Alpine está cargado
Si responde `"undefined"` → ❌ Alpine NO está cargado

---

## ✅ Si lo ves pero no abre

El botón está ahí pero al hacer clic no pasa nada:

1. **Limpia el caché de Laravel** (en el servidor):
   ```bash
   /opt/alt/php83/usr/bin/php artisan view:clear
   /opt/alt/php83/usr/bin/php artisan cache:clear
   ```

2. **Recarga la página** con `Ctrl + Shift + R`

---

## 🎯 Cómo Usar el Selector (cuando funcione)

1. **Click en el botón** que muestra el nombre de la empresa
2. Se abre un menú con opciones:
   ```
   • Todas las empresas
   ────────────────────
   🏢 Brasil
   🏭 Corenet Spa
   ────────────────────
   ⚙️  Gestionar Empresas
   ```

3. **Selecciona una empresa** haciendo clic
4. La página se recarga y muestra **solo los datos de esa empresa**
5. Para ver todas las empresas juntas, selecciona "Todas las empresas"

---

## 📸 Referencia Visual

El selector debería verse aproximadamente así:

```
┌─────────────────────────┐
│  📊 Brasil          ▼  │  ← Botón principal
└─────────────────────────┘

Al hacer clic:

┌─────────────────────────┐
│ • Todas las empresas    │
│ ───────────────────────│
│ 🏢 Brasil           ✓ │
│ 🏭 Corenet Spa        │
│ ───────────────────────│
│ ⚙️  Gestionar Empresas │
└─────────────────────────┘
```

---

## 🚨 Diagnóstico Rápido

Ejecuta esto en tu servidor para verificar:

```bash
/opt/alt/php83/usr/bin/php artisan tinker --execute="
echo 'Empresas en BD: ' . \App\Models\Company::count() . PHP_EOL;
echo 'Usuario tiene empresas: ' . \App\Models\User::first()->companies->count() . PHP_EOL;
"
```

Debería mostrar:
```
Empresas en BD: 2
Usuario tiene empresas: 2
```

Si muestra 0, significa que las empresas no están asignadas a tu usuario.

---

## 🔧 Solución de Emergencia

Si después de todo esto NO ves el selector, puedes acceder directamente a las empresas mediante URLs:

**Ver todas las empresas:**
```
https://costoreal.kruje.cl/company/switch/all
```

**Ver solo Brasil (ID: 1):**
```
https://costoreal.kruje.cl/company/switch/1
```

**Ver solo Corenet Spa (ID: 2):**
```
https://costoreal.kruje.cl/company/switch/2
```

**Gestionar empresas:**
```
https://costoreal.kruje.cl/companies
```

---

**Fecha:** 2025-11-04
