#!/bin/bash
# ============================================================
# REACTIVAR combinaciones 5ml - Monoesencias y Esencias Parfum
# Categorías: 82 (Monoesencias) y 114 (Esencias Parfum)
# ============================================================

DB_USER="perfumhada_syspre"
DB_PASS='0RDK&6mBq[URw0z$'
DB_NAME="perfumhada_syspre"

echo ">>> Reactivando combinaciones de 5ml..."

mariadb -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
-- 1. Reactivar combinaciones de 5ml (atributo id=6) en categorías 82 y 114
UPDATE ps_product_attribute pa
JOIN ps_product_attribute_combination pac ON pa.id_product_attribute = pac.id_product_attribute
JOIN ps_category_product cp ON pa.id_product = cp.id_product
SET pa.available_for_order = 1
WHERE pac.id_attribute = 6
AND cp.id_category IN (82, 114);

-- 2. Reactivar el producto independiente 'Monoesencia 5 ML' (id_product=527)
UPDATE ps_product SET available_for_order = 1 WHERE id_product = 527;
UPDATE ps_product_shop SET available_for_order = 1 WHERE id_product = 527;
"

echo ">>> Verificando resultado..."

mariadb -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
SELECT COUNT(*) as combinaciones_reactivadas
FROM ps_product_attribute pa
JOIN ps_product_attribute_combination pac ON pa.id_product_attribute = pac.id_product_attribute
JOIN ps_category_product cp ON pa.id_product = cp.id_product
WHERE pac.id_attribute = 6
AND cp.id_category IN (82, 114)
AND pa.available_for_order = 1;
"

echo ">>> Listo. 44 combinaciones + producto 527 reactivados."
