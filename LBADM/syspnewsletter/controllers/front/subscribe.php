<?php
/**
 * AJAX Controller – Newsletter subscribe
 *
 * Compatible con PrestaShop 1.7 y 8.
 * Responde en JSON a peticiones POST desde el popup de newsletter.
 *
 * @author    SYSPROVIDER S.L.
 * @copyright 2024 SYSPROVIDER S.L.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SyspNewsletterSubscribeModuleFrontController extends ModuleFrontController
{
    /** Deshabilitar layout completo de PS */
    public $ajax         = true;
    public $display_column_left  = false;
    public $display_column_right = false;

    /**
     * initContent se ejecuta SIEMPRE, con o sin token, en GET y POST.
     * Es el método más seguro para endpoints AJAX en PS 1.7/8.
     */
    public function initContent()
    {
        // No llamar a parent::initContent() — evita que PS intente renderizar plantillas
        header('Content-Type: application/json; charset=utf-8');
        // Evitar que PS o Varnish cacheen la respuesta
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $email = trim(Tools::getValue('email', ''));

        if (empty($email) || !Validate::isEmail($email)) {
            $this->jsonDie(['success' => false, 'error' => 'Email inválido o vacío.']);
        }

        $idShop = (int) $this->context->shop->id;
        $idLang = (int) $this->context->language->id;

        // Guardar suscriptor
        $alreadySub = $this->module->checkAlreadySubscribed($email, $idShop);
        if (!$alreadySub) {
            $this->module->saveNewSubscriber($email, $idShop);
        }

        $response = [
            'success' => true,
            'msg'     => Configuration::get('SYSPNL_SUCCESS_MSG') ?: '¡Gracias por suscribirte!',
        ];

        // Generar o recuperar cupón de descuento
        if ((int) Configuration::get('SYSPNL_DISCOUNT_ACTIVE') === 1) {
            // Intentar crear nuevo código
            $code = $this->module->createDiscountCode($email, $idShop, $idLang);
            // Si falla (p.ej. ya tenía uno), recuperar el existente
            if (!$code) {
                $code = $this->module->getExistingCode($email);
            }
            if ($code) {
                $response['discount_code'] = $code;
                $response['discount_msg']  = Configuration::get('SYSPNL_DISCOUNT_MSG')
                                             ?: '¡Usa este código en tu próxima compra!';
            }
        }

        $this->jsonDie($response);
    }

    private function jsonDie(array $data)
    {
        die(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
