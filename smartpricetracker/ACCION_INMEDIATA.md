# 🚨 ACCIÓN INMEDIATA - Solucionar Error de Conexión

## 🎯 HAZ ESTO PRIMERO (5 minutos)

### PASO 1: Sube los Scripts de Diagnóstico

Sube estos 2 archivos por FTP a tu carpeta del módulo:

```
📁 Tu tienda
└── 📁 modules
    └── 📁 smartpricetracker
        ├── 📄 repair.php          ⬅️ SUBE ESTE
        └── 📄 diagnostic.php      ⬅️ SUBE ESTE
```

### PASO 2: Ejecuta la Reparación

Abre tu navegador y ve a:
```
https://tutienda.com/modules/smartpricetracker/repair.php
```

Verás algo como esto:
- ✅ Tab creado correctamente
- ✅ Carpetas verificadas
- ✅ Caché limpiada
- ✅ Base de datos OK

### PASO 3: Ejecuta el Diagnóstico

Ahora ve a:
```
https://tutienda.com/modules/smartpricetracker/diagnostic.php
```

**SI TODO ESTÁ EN VERDE ✅:**
- Ve a tu producto y prueba el módulo
- Debería funcionar ahora

**SI HAY ERRORES ROJOS ❌:**
- Lee qué dice el error
- Continúa con el PASO 4

---

## 🔧 PASO 4: Corregir Errores Específicos

### Error: "Método searchCompetitorsByTitle NO EXISTE"

1. Descarga el archivo: `SmartPriceScraper.php`
2. Súbelo por FTP a:
```
modules/smartpricetracker/classes/SmartPriceScraper.php
```
3. Vuelve a ejecutar `diagnostic.php`

---

### Error: "Tab no encontrado" o "Controlador no existe"

1. Descarga: `AdminSmartPriceTrackerAjaxController_v2.php`
2. Renómbralo a: `AdminSmartPriceTrackerAjaxController.php` (quita el "_v2")
3. Súbelo por FTP a:
```
modules/smartpricetracker/controllers/admin/AdminSmartPriceTrackerAjaxController.php
```
4. Ejecuta de nuevo `repair.php`

---

### Error: "Carpetas no existen"

Esto lo arregla automáticamente `repair.php`, pero si persiste:

```bash
# Por SSH/terminal
cd modules/smartpricetracker/
mkdir -p controllers/admin
mkdir -p classes
mkdir -p views/templates/hook
```

---

## 🎯 PASO 5: Prueba Final

1. Ve a `diagnostic.php`
2. Verifica que TODO esté en verde ✅
3. Ve al backoffice → Productos
4. Abre un producto → Pestaña "Módulos"
5. Deberías ver el Radar de Precios funcionando

---

## 📋 Checklist Rápido

- [ ] He subido `repair.php` y `diagnostic.php`
- [ ] He ejecutado `repair.php`
- [ ] He ejecutado `diagnostic.php`
- [ ] He corregido los errores que indicaba
- [ ] He limpiado la caché de PrestaShop
- [ ] He probado el módulo en un producto

---

## 🆘 ¿Sigue sin funcionar?

### Opción A: Revisar Logs

Ve a tu servidor y mira:
```
var/logs/
```

Busca mensajes de error recientes.

### Opción B: Test Manual del AJAX

Abre esta URL en tu navegador (cambia los valores):
```
https://tutienda.com/admin-XXX/index.php?controller=AdminSmartPriceTrackerAjax&ajax=1&action=SearchCompetitors&id_product=1&search_term=test
```

¿Qué ves?
- **Si ves JSON** → El controlador funciona, el problema es JavaScript
- **Si ves error 404** → El Tab no está instalado, ejecuta `repair.php`
- **Si ves error 500** → Hay un error de PHP, revisa los logs
- **Si ves error de método** → Falta el archivo actualizado de SmartPriceScraper.php

---

## 💡 Causa Más Común (90% de los casos)

El archivo `AdminSmartPriceTrackerAjaxController.php` NO está en:
```
modules/smartpricetracker/controllers/admin/
```

**Solución:**
1. Verifica por FTP que el archivo EXISTE en esa ruta EXACTA
2. Si no existe, súbelo ahí
3. Si existe, reemplázalo con la versión `_v2`
4. Ejecuta `repair.php`

---

## ⚡ Solución Express (Si tienes prisa)

1. Descarga TODOS los archivos actualizados
2. Borra la carpeta `modules/smartpricetracker/`
3. Sube la nueva carpeta completa
4. Ve a Módulos → Gestionar → Instalar el módulo
5. Listo

Esta es la forma más segura de asegurarte que todo está correcto.

---

**Archivos que necesitas:**
- ✅ `repair.php` - Script de reparación automática
- ✅ `diagnostic.php` - Script de diagnóstico
- ✅ `SmartPriceScraper.php` - Clase con método de búsqueda
- ✅ `AdminSmartPriceTrackerAjaxController_v2.php` - Controlador mejorado
- ✅ `SOLUCION_ERROR_CONEXION.md` - Guía completa

Todos los archivos están incluidos en la descarga. ¡Síguelos en orden!
