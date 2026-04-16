<?php
/**
 * SYSPROVIDER Newsletter Popup — Front Controller AJAX (subscribe)
 *
 * @author    SYSPROVIDER S.L.
 * @copyright 2024 SYSPROVIDER S.L.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SyspNewsletterSubscribeModuleFrontController extends ModuleFrontController
{
    public $ajax = true;
    public $display_column_left  = false;
    public $display_column_right = false;

    public function initContent()
    {
        ob_start();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        try {
            $email = strtolower(trim(Tools::getValue('email', '')));

            if (empty($email) || !Validate::isEmail($email)) {
                ob_end_clean();
                $this->jsonDie(['success' => false, 'error' => 'Email inválido o vacío.']);
            }

            if (!$this->module || !is_object($this->module)) {
                $this->module = Module::getInstanceByName('syspnewsletter');
            }

            if (!$this->module || !$this->module->active) {
                ob_end_clean();
                $this->jsonDie(['success' => false, 'error' => 'Módulo no disponible.']);
            }

            $idShop      = (int) $this->context->shop->id;
            $idShopGroup = (int) $this->context->shop->id_shop_group;
            $idLang      = (int) $this->context->language->id;
            $db          = Db::getInstance();

            // ── 1. ¿A qué tabla pertenece este email? ────────────────────────
            $id_customer = (int) $db->getValue(
                'SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'customer` 
                 WHERE `email` = \'' . pSQL($email) . '\' AND `id_shop` = ' . $idShop
            );

            if ($id_customer) {
                // RUTA A: ES UN CLIENTE REGISTRADO
                $is_sub = (int) $db->getValue('SELECT `newsletter` FROM `' . _DB_PREFIX_ . 'customer` WHERE `id_customer` = ' . $id_customer);
                
                if ($is_sub === 1) {
                    ob_end_clean();
                    $this->jsonDie(['success' => false, 'error' => 'Este email ya está suscrito al boletín.']);
                }

                $this->subscribeRegisteredCustomer($id_customer, $db);
                
            } else {
                // RUTA B: ES UN VISITANTE / INVITADO
                $table_exists = $db->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . 'emailsubscription"');
                if (empty($table_exists)) {
                    ob_end_clean();
                    $this->jsonDie(['success' => false, 'error' => 'El módulo nativo de newsletter no está instalado.']);
                }

                $guest = $db->getRow('SELECT `id`, `active` FROM `' . _DB_PREFIX_ . 'emailsubscription` WHERE `email` = \'' . pSQL($email) . '\' AND `id_shop` = ' . $idShop);

                if ($guest && (int)$guest['active'] === 1) {
                    ob_end_clean();
                    $this->jsonDie(['success' => false, 'error' => 'Este email ya está suscrito al boletín.']);
                }

                $this->subscribeGuestEmail($email, $guest, $idShop, $idShopGroup, $idLang, $db);
            }

            // ── 2. Guardar en tabla interna (Solo para estadísticas y panel) ──
            $this->saveInModuleTable($email, $idShop, $db);

            // ── 3. Disparar hooks nativos de PS (Mailchimp/Brevo) ────────────
            Hook::exec('actionNewsletterRegistrationAfter', [
                'email'  => $email,
                'action' => 'subscribe',
                'error'  => false,
            ]);

            // ── 4. Preparar respuesta y Cupones ──────────────────────────────
            $response = [
                'success' => true,
                'msg'     => Configuration::get('SYSPNL_SUCCESS_MSG') ?: '¡Gracias por suscribirte!',
            ];

            if ((int) Configuration::get('SYSPNL_DISCOUNT_ACTIVE') === 1) {
                $code = $this->module->createDiscountCode($email, $idShop, $idLang);
                if (!$code) {
                    $code = $this->module->getExistingCode($email);
                }
                if ($code) {
                    $response['discount_code'] = $code;
                    $response['discount_msg']  = Configuration::get('SYSPNL_DISCOUNT_MSG') ?: '¡Usa este código en tu próxima compra!';
                }
            }

            ob_end_clean();
            $this->jsonDie($response);

        } catch (\Throwable $e) {
            ob_end_clean();
            PrestaShopLogger::addLog('[SyspNewsletter] Error en subscribe: ' . $e->getMessage(), 3);
            $this->jsonDie(['success' => false, 'error' => 'Error interno. Por favor, inténtalo de nuevo.']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MÉTODOS PRIVADOS
    // ─────────────────────────────────────────────────────────────────────────

    private function subscribeRegisteredCustomer(int $id_customer, Db $db): void
    {
        $db->execute(
            'UPDATE `' . _DB_PREFIX_ . 'customer`
             SET `newsletter` = 1,
                 `newsletter_date_add` = NOW(),
                 `ip_registration_newsletter` = \'' . pSQL((string) Tools::getRemoteAddr()) . '\'
             WHERE `id_customer` = ' . $id_customer
        );
        
        // Engañar a la caché de PS para que otros módulos lo vean como activo instantáneamente
        $customerObj = new Customer($id_customer);
        $customerObj->newsletter = 1;
        Hook::exec('actionObjectCustomerUpdateAfter', ['object' => $customerObj]);
    }

    private function subscribeGuestEmail(string $email, $guestRow, int $idShop, int $idShopGroup, int $idLang, Db $db): void 
    {
        if ($guestRow) {
            $db->execute(
                'UPDATE `' . _DB_PREFIX_ . 'emailsubscription`
                 SET `active` = 1, `newsletter_date_add` = NOW()
                 WHERE `id` = ' . (int) $guestRow['id']
            );
        } else {
            $db->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'emailsubscription`
                    (`id_shop`, `id_shop_group`, `email`, `newsletter_date_add`, `ip_registration_newsletter`, `http_referer`, `active`, `id_lang`)
                 VALUES (
                    ' . $idShop . ', ' . $idShopGroup . ', \'' . pSQL($email) . '\', NOW(),
                    \'' . pSQL((string) Tools::getRemoteAddr()) . '\', \'' . pSQL((string) Tools::getHttpHost()) . '\', 1, ' . $idLang . '
                 )'
            );
        }
    }

    private function saveInModuleTable(string $email, int $idShop, Db $db): void
    {
        $db->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'syspnl_subscribers` (`email`, `id_shop`, `date_add`)
             VALUES (\'' . pSQL($email) . '\', ' . $idShop . ', NOW())'
        );
    }

    private function jsonDie(array $data): void
    {
        die(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}