<?php
if (!defined('_PS_VERSION_')) { exit; }

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
            $email = trim(Tools::getValue('email', ''));

            if (empty($email) || !Validate::isEmail($email)) {
                ob_end_clean();
                $this->jsonDie(['success' => false, 'error' => 'Email inválido o vacío.']);
            }

            $idShop = (int) $this->context->shop->id;
            $idLang = (int) $this->context->language->id;

            // Cargar módulo manualmente si no está disponible
            if (!$this->module || !is_object($this->module)) {
                $this->module = Module::getInstanceByName('syspnewsletter');
            }

            if (!$this->module || !$this->module->active) {
                ob_end_clean();
                $this->jsonDie(['success' => false, 'error' => 'Módulo no disponible.']);
            }

            $this->module->saveNewSubscriber($email, $idShop);

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
                    $response['discount_msg']  = Configuration::get('SYSPNL_DISCOUNT_MSG')
                                                 ?: '¡Usa este código en tu próxima compra!';
                }
            }

            ob_end_clean();
            $this->jsonDie($response);

        } catch (\Throwable $e) {
            ob_end_clean();
            $this->jsonDie([
                'success' => false,
                'error'   => 'Error interno: ' . $e->getMessage(),
            ]);
        }
    }

    private function jsonDie(array $data)
    {
        die(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}