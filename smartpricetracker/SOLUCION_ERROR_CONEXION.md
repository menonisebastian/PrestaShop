# 🔴 SOLUCIÓN: Error de Conexión en Smart Price Tracker

## El Problema

Estás viendo el mensaje: **"Error de conexión. Por favor, inténtalo de nuevo."**

Este error indica que la llamada AJAX al servidor está fallando. Aquí te muestro cómo solucionarlo paso a paso.

---

## 🎯 Solución Rápida (Más Común)

### Causa #1: Controlador AJAX no encontrado o mal ubicado

**El archivo debe estar en esta ruta EXACTA:**
```
modules/smartpricetracker/controllers/admin/AdminSmartPriceTrackerAjaxController.php
```

#### ¿Cómo verificar?

1. Ve a tu servidor por FTP o SSH
2. Navega a: `modules/smartpricetracker/controllers/admin/`
3. Verifica que el archivo `AdminSmartPriceTrackerAjaxController.php` exista ahí

#### Si NO está en esa ubicación:

```bash
# Crear la carpeta si no existe
mkdir -p modules/smartpricetracker/controllers/admin/

# Mover/copiar el archivo a la ubicación correcta
cp AdminSmartPriceTrackerAjaxController.php modules/smartpricetracker/controllers/admin/
```

---

## 🔧 Solución Paso a Paso Completa

### PASO 1: Subir los Scripts de Diagnóstico

Sube estos 2 archivos a `modules/smartpricetracker/`:
- `diagnostic.php`
- `repair.php`

### PASO 2: Ejecutar el Script de Reparación

Accede desde tu navegador:
```
https://tutienda.com/modules/smartpricetracker/repair.php
```

Este script:
- ✅ Reinstala el Tab del controlador AJAX
- ✅ Verifica la estructura de carpetas
- ✅ Crea carpetas faltantes
- ✅ Limpia la caché
- ✅ Verifica la tabla de BD
- ✅ Reinstala hooks

### PASO 3: Ejecutar el Diagnóstico

Accede desde tu navegador:
```
https://tutienda.com/modules/smartpricetracker/diagnostic.php
```

Este script te mostrará:
- Estado del módulo
- Si el controlador AJAX existe
- Si el método `searchCompetitorsByTitle` existe
- Test de conectividad

### PASO 4: Verificar los Archivos Clave

Asegúrate de tener EXACTAMENTE estos archivos en estas rutas:

```
modules/smartpricetracker/
├── smartpricetracker.php                                    ✅ Archivo principal
├── classes/
│   └── SmartPriceScraper.php                                ✅ Con método searchCompetitorsByTitle
├── controllers/
│   └── admin/
│       └── AdminSmartPriceTrackerAjaxController.php         ✅ CRÍTICO: Debe estar aquí
└── views/
    └── templates/
        └── hook/
            └── admin_products_extra.tpl                     ✅ Interfaz
```

---

## 🔍 Causas Comunes y Soluciones

### Causa #1: Tab no instalado

**Error:** El controlador AJAX no está registrado en PrestaShop

**Solución:**
```sql
-- Ejecuta esto en phpMyAdmin
SELECT * FROM ps_tab WHERE class_name = 'AdminSmartPriceTrackerAjax';
```

Si no devuelve ningún resultado, ejecuta `repair.php` o reinstala el módulo.

---

### Causa #2: Ruta del require_once incorrecta

**Error en el controlador:** La ruta para cargar `SmartPriceScraper.php` está mal

**Versión CORRECTA:**
```php
require_once _PS_MODULE_DIR_ . 'smartpricetracker/classes/SmartPriceScraper.php';
```

**Versiones INCORRECTAS:**
```php
// ❌ INCORRECTO - Ruta relativa
require_once dirname(__FILE__) . '/../../classes/SmartPriceScraper.php';

// ❌ INCORRECTO - Ruta incorrecta
require_once dirname(__FILE__) . '/../classes/SmartPriceScraper.php';
```

---

### Causa #3: Método searchCompetitorsByTitle no existe

**Error:** La clase SmartPriceScraper no tiene el método

**Cómo verificar:**
```bash
grep -n "searchCompetitorsByTitle" modules/smartpricetracker/classes/SmartPriceScraper.php
```

Si no devuelve nada, necesitas el archivo actualizado. Este método debe existir:

```php
public static function searchCompetitorsByTitle($search_term)
{
    // ... código del método
}
```

---

### Causa #4: Permisos de archivos

**Error:** PrestaShop no puede leer los archivos

**Solución:**
```bash
# Dar permisos correctos
chmod 644 modules/smartpricetracker/smartpricetracker.php
chmod 644 modules/smartpricetracker/classes/SmartPriceScraper.php
chmod 644 modules/smartpricetracker/controllers/admin/AdminSmartPriceTrackerAjaxController.php
chmod 755 modules/smartpricetracker/classes/
chmod 755 modules/smartpricetracker/controllers/
chmod 755 modules/smartpricetracker/controllers/admin/
```

---

### Causa #5: Caché de PrestaShop

**Error:** PrestaShop está usando archivos antiguos cacheados

**Solución:**

#### Opción A - Desde el Backoffice:
1. Ve a **Parámetros Avanzados → Rendimiento**
2. Haz clic en **Limpiar caché**

#### Opción B - Manualmente:
```bash
rm -rf var/cache/*
rm -rf cache/class_index.php
```

#### Opción C - Ejecutar repair.php que lo hace automáticamente

---

## 🐛 Debug Avanzado

### Activar Errores PHP

Edita `config/defines.inc.php`:

```php
define('_PS_MODE_DEV_', true);
```

O añade al inicio de `AdminSmartPriceTrackerAjaxController.php`:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Ver Logs de PrestaShop

Revisa el archivo:
```
var/logs/
```

Busca mensajes como:
- "Smart Price Tracker: Iniciando búsqueda AJAX"
- Errores de PHP
- Errores de conexión a BD

### Probar el Controlador Directamente

Accede a:
```
https://tutienda.com/admin-XXXX/index.php?controller=AdminSmartPriceTrackerAjax&ajax=1&action=SearchCompetitors&id_product=1&search_term=test
```

(Reemplaza `admin-XXXX` con tu carpeta de admin real)

Si funciona, verás un JSON. Si no, verás el error exacto.

---

## 📋 Checklist de Verificación

Antes de contactar soporte, verifica:

- [ ] El archivo `AdminSmartPriceTrackerAjaxController.php` está en `controllers/admin/`
- [ ] El archivo `SmartPriceScraper.php` tiene el método `searchCompetitorsByTitle`
- [ ] El Tab está instalado (verifica con diagnostic.php)
- [ ] La caché está limpia
- [ ] Los permisos de archivos son correctos (644 para archivos, 755 para carpetas)
- [ ] PHP tiene las extensiones: curl, dom, json
- [ ] Has ejecutado `repair.php` y corregido los errores

---

## 🆘 Solución Nuclear (Si Todo Falla)

Si nada funciona, haz esto:

### 1. Desinstalar Completamente
```sql
-- Ejecuta en phpMyAdmin
DELETE FROM ps_module WHERE name = 'smartpricetracker';
DELETE FROM ps_hook_module WHERE id_module IN (SELECT id_module FROM ps_module WHERE name = 'smartpricetracker');
DELETE FROM ps_tab WHERE class_name = 'AdminSmartPriceTrackerAjax';
DROP TABLE IF EXISTS ps_smart_competitor_price;
```

### 2. Borrar Carpeta
```bash
rm -rf modules/smartpricetracker/
```

### 3. Subir Módulo Fresco

Sube la carpeta `smartpricetracker` con TODOS los archivos actualizados

### 4. Instalar

Ve a Módulos → Instalar

---

## 🎯 Test Final

Después de aplicar las soluciones:

1. Ve a `tutienda.com/modules/smartpricetracker/diagnostic.php`
2. Todos los checks deben estar en verde ✅
3. Ve a un producto → Pestaña Módulos
4. Deberías ver el Radar de Precios funcionando
5. Si aún falla, revisa los logs de PrestaShop

---

## 💡 Prevención

Para evitar este problema en el futuro:

1. **Siempre limpia la caché** después de actualizar archivos del módulo
2. **Verifica las rutas** de los archivos antes de subirlos
3. **Haz backup** antes de hacer cambios
4. **Usa diagnostic.php** regularmente para verificar el estado

---

¿Sigues teniendo problemas? Envíame:
- Captura de pantalla de `diagnostic.php`
- Captura de pantalla del error
- Contenido del archivo `var/logs/` (las últimas líneas)
