#!/bin/bash

# SYSPROVIDER Popup Manager - Script de Empaquetado
# Este script crea un archivo ZIP listo para instalar en PrestaShop

echo "========================================="
echo "  SYSPROVIDER - Popup Manager"
echo "  Script de Empaquetado"
echo "========================================="
echo ""

# Nombre del módulo
MODULE_NAME="sysppopup"
ZIP_NAME="${MODULE_NAME}.zip"

# Verificar que estamos en el directorio correcto
if [ ! -f "${MODULE_NAME}.php" ]; then
    echo "❌ Error: No se encuentra el archivo ${MODULE_NAME}.php"
    echo "   Asegúrate de ejecutar este script desde la carpeta del módulo"
    exit 1
fi

echo "📦 Empaquetando módulo..."
echo ""

# Eliminar ZIP anterior si existe
if [ -f "../${ZIP_NAME}" ]; then
    rm "../${ZIP_NAME}"
    echo "🗑️  ZIP anterior eliminado"
fi

# Crear el ZIP
cd ..
zip -r "${ZIP_NAME}" "${MODULE_NAME}/" \
    -x "${MODULE_NAME}/.git/*" \
    -x "${MODULE_NAME}/.gitignore" \
    -x "${MODULE_NAME}/package.sh" \
    -x "${MODULE_NAME}/*.zip" \
    -x "${MODULE_NAME}/.DS_Store" \
    -x "${MODULE_NAME}/.idea/*" \
    -x "${MODULE_NAME}/node_modules/*"

cd "${MODULE_NAME}"

# Verificar que se creó el ZIP
if [ -f "../${ZIP_NAME}" ]; then
    echo ""
    echo "✅ ¡Módulo empaquetado correctamente!"
    echo ""
    echo "📁 Archivo creado: ../${ZIP_NAME}"
    echo ""
    echo "Tamaño: $(du -h ../${ZIP_NAME} | cut -f1)"
    echo ""
    echo "========================================="
    echo "  Siguiente paso:"
    echo "  1. Ve a tu PrestaShop"
    echo "  2. Módulos > Module Manager"
    echo "  3. Subir módulo"
    echo "  4. Selecciona ${ZIP_NAME}"
    echo "========================================="
else
    echo ""
    echo "❌ Error al crear el archivo ZIP"
    exit 1
fi

exit 0
