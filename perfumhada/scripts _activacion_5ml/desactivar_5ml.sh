#!/bin/bash
DB_USER="perfumhada_syspre"
DB_PASS='0RDK&6mBq[URw0z$'
DB_NAME="perfumhada_syspre"

echo ">>> Creando backup del stock actual..."

mariadb -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
-- Crear tabla de backup si no existe
CREATE TABLE IF NOT EXISTS ps_stock_backup_5ml AS
SELECT sa.*
FROM ps_stock_available sa
JOIN ps_product_attribute_combination pac ON sa.id_product_attribute = pac.id_product_attribute
JOIN ps_category_product cp ON sa.id_product = cp.id_product
WHERE pac.id_attribute = 6
AND cp.id_category IN (82, 114)
AND 1=0;

ALTER TABLE ps_stock_backup_5ml
ADD COLUMN IF NOT EXISTS backup_date DATETIME DEFAULT NOW();

-- Vaciar y rellenar con los datos actuales
TRUNCATE TABLE ps_stock_backup_5ml;

INSERT INTO ps_stock_backup_5ml
SELECT sa.*, NOW()
FROM ps_stock_available sa
JOIN ps_product_attribute_combination pac ON sa.id_product_attribute = pac.id_product_attribute
JOIN ps_category_product cp ON sa.id_product = cp.id_product
WHERE pac.id_attribute = 6
AND cp.id_category IN (82, 114);

-- Poner quantity a 0 y out_of_stock a 0 (no permite pedidos sin stock)
UPDATE ps_stock_available sa
JOIN ps_product_attribute_combination pac ON sa.id_product_attribute = pac.id_product_attribute
JOIN ps_category_product cp ON sa.id_product = cp.id_product
SET sa.quantity = 0, sa.out_of_stock = 0
WHERE pac.id_attribute = 6
AND cp.id_category IN (82, 114);

-- Producto independiente 527
UPDATE ps_product SET available_for_order = 0 WHERE id_product = 527;
UPDATE ps_product_shop SET available_for_order = 0 WHERE id_product = 527;
"

echo ">>> Verificando..."
mariadb -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
SELECT COUNT(*) as filas_en_backup FROM ps_stock_backup_5ml;
SELECT COUNT(*) as combinaciones_a_0
FROM ps_stock_available sa
JOIN ps_product_attribute_combination pac ON sa.id_product_attribute = pac.id_product_attribute
JOIN ps_category_product cp ON sa.id_product = cp.id_product
WHERE pac.id_attribute = 6
AND cp.id_category IN (82, 114)
AND sa.quantity = 0;
"

echo ">>> Listo. Stock original guardado en ps_stock_backup_5ml. Combinaciones de 5ml desactivadas."
