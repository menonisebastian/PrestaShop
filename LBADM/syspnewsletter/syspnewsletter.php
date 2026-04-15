<?php
/**
 * SYSPROVIDER Newsletter Popup Module
 *
 * @author    SYSPROVIDER S.L.
 * @copyright 2024 SYSPROVIDER S.L.
 * @license   Commercial License
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SyspNewsletter extends Module
{
    public function __construct()
    {
        $this->name = 'syspnewsletter';
        $this->tab = 'advertising_marketing';
        $this->version = '3.0.0';
        $this->author = 'Sebastián / SYSPROVIDER S.L.';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('SYSPROVIDER - Newsletter Popup');
        $this->description = $this->l('Popup de suscripción al newsletter con descuento opcional y personalización total');
        $this->confirmUninstall = $this->l('¿Estás seguro de que deseas desinstalar este módulo?');
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        include(dirname(__FILE__) . '/sql/install.php');

        return parent::install()
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('displayBeforeBodyClosingTag')
            && $this->registerHook('displayFooter')
            && $this->installAdminTab()
            && $this->setDefaultConfig();
    }

    public function uninstall()
    {
        include(dirname(__FILE__) . '/sql/uninstall.php');

        $keys = [
            'SYSPNL_ACTIVE',
            'SYSPNL_FREQUENCY',
            'SYSPNL_FREQUENCY_VALUE',
            'SYSPNL_DELAY',
            'SYSPNL_TITLE',
            'SYSPNL_SUBTITLE',
            'SYSPNL_BTN_TEXT',
            'SYSPNL_PLACEHOLDER',
            'SYSPNL_FONT_FAMILY',
            'SYSPNL_FONT_SIZE_TITLE',
            'SYSPNL_FONT_SIZE_SUBTITLE',
            'SYSPNL_COLOR_BG',
            'SYSPNL_COLOR_OVERLAY',
            'SYSPNL_COLOR_TITLE',
            'SYSPNL_COLOR_SUBTITLE',
            'SYSPNL_COLOR_BTN_BG',
            'SYSPNL_COLOR_BTN_TEXT',
            'SYSPNL_COLOR_INPUT_BORDER',
            'SYSPNL_BORDER_RADIUS',
            'SYSPNL_WIDTH',
            'SYSPNL_ANIMATION',
            'SYSPNL_DISCOUNT_ACTIVE',
            'SYSPNL_DISCOUNT_TYPE',
            'SYSPNL_DISCOUNT_VALUE',
            'SYSPNL_DISCOUNT_MSG',
            'SYSPNL_SUCCESS_MSG',
            'SYSPNL_BG_IMAGE',
            'SYSPNL_POSITION',
        ];
        foreach ($keys as $key) {
            Configuration::deleteByName($key);
        }

        $this->uninstallAdminTab();

        return parent::uninstall();
    }

    protected function setDefaultConfig()
    {
        $defaults = [
            'SYSPNL_ACTIVE' => 1,
            'SYSPNL_FREQUENCY' => 'once',
            'SYSPNL_FREQUENCY_VALUE' => 7,
            'SYSPNL_DELAY' => 3,
            'SYSPNL_TITLE' => '¡Únete a nuestra comunidad!',
            'SYSPNL_SUBTITLE' => 'Suscríbete y recibe las últimas novedades y ofertas exclusivas.',
            'SYSPNL_BTN_TEXT' => 'Suscribirme',
            'SYSPNL_PLACEHOLDER' => 'Tu correo electrónico',
            'SYSPNL_FONT_FAMILY' => 'inherit',
            'SYSPNL_FONT_SIZE_TITLE' => 26,
            'SYSPNL_FONT_SIZE_SUBTITLE' => 15,
            'SYSPNL_COLOR_BG' => '#ffffff',
            'SYSPNL_COLOR_OVERLAY' => 'rgba(0,0,0,0.65)',
            'SYSPNL_COLOR_TITLE' => '#1a1a1a',
            'SYSPNL_COLOR_SUBTITLE' => '#555555',
            'SYSPNL_COLOR_BTN_BG' => '#e8927c',
            'SYSPNL_COLOR_BTN_TEXT' => '#ffffff',
            'SYSPNL_COLOR_INPUT_BORDER' => '#cccccc',
            'SYSPNL_BORDER_RADIUS' => 12,
            'SYSPNL_WIDTH' => 480,
            'SYSPNL_ANIMATION' => 'zoom',
            'SYSPNL_DISCOUNT_ACTIVE' => 0,
            'SYSPNL_DISCOUNT_TYPE' => 'percentage',
            'SYSPNL_DISCOUNT_VALUE' => 10,
            'SYSPNL_DISCOUNT_MSG' => '¡Usa este código en tu próxima compra!',
            'SYSPNL_SUCCESS_MSG' => '¡Gracias por suscribirte! 🎉',
            'SYSPNL_BG_IMAGE' => '',
            'SYSPNL_POSITION' => 'center',
        ];

        foreach ($defaults as $key => $value) {
            Configuration::updateValue($key, $value);
        }

        return true;
    }

    // ── BACKOFFICE ──────────────────────────────────────────────────────────

    public function getContent()
    {
        $subscribersUrl = $this->context->link
            ->getAdminLink('AdminSyspNewsletterSubscribers');


        $btnHtml = '<a href="' . $subscribersUrl . '" class="btn btn-default" '
            . 'style="margin-bottom:16px; display:inline-flex; align-items:center; '
            . 'gap:8px; background:#e8927c; border-color:#e8927c; color:#fff; '
            . 'font-weight:600; padding:10px 20px; border-radius:6px;">'
            . '👥 &nbsp;Ver suscriptores</a>';


        $output = '';

        if (Tools::isSubmit('submitSyspNewsletter')) {
            $output .= $this->postProcess();
        }

        $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        $this->context->controller->addJS($this->_path . 'views/js/back.js');

        return $btnHtml . $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitSyspNewsletter';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getFormGeneral(), $this->getFormDesign(), $this->getFormDiscount()]);
    }

    protected function getFormGeneral()
    {
        return [
            'form' => [
                'legend' => ['title' => $this->l('⚙️ Configuración General'), 'icon' => 'icon-cogs'],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Activar popup'),
                        'name' => 'SYSPNL_ACTIVE',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => true, 'label' => $this->l('Sí')],
                            ['id' => 'active_off', 'value' => false, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Frecuencia'),
                        'name' => 'SYSPNL_FREQUENCY',
                        'desc' => $this->l('Con qué frecuencia se muestra el popup'),
                        'options' => [
                            'query' => [
                                ['id' => 'always', 'name' => $this->l('Siempre')],
                                ['id' => 'once', 'name' => $this->l('Solo una vez')],
                                ['id' => 'days', 'name' => $this->l('Cada X días')],
                            ],
                            'id' => 'id',
                            'name' => 'name'
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Días entre apariciones'),
                        'name' => 'SYSPNL_FREQUENCY_VALUE',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Solo si elegiste "Cada X días"'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Delay (segundos)'),
                        'name' => 'SYSPNL_DELAY',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Segundos que espera antes de aparecer (0 = inmediato)'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Posición'),
                        'name' => 'SYSPNL_POSITION',
                        'options' => [
                            'query' => [
                                ['id' => 'center', 'name' => $this->l('Centro')],
                                ['id' => 'top', 'name' => $this->l('Arriba')],
                                ['id' => 'bottom', 'name' => $this->l('Abajo')],
                                ['id' => 'bottom-right', 'name' => $this->l('Abajo-derecha')],
                                ['id' => 'bottom-left', 'name' => $this->l('Abajo-izquierda')],
                            ],
                            'id' => 'id',
                            'name' => 'name'
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Animación'),
                        'name' => 'SYSPNL_ANIMATION',
                        'options' => [
                            'query' => [
                                ['id' => 'fade', 'name' => $this->l('Fade')],
                                ['id' => 'zoom', 'name' => $this->l('Zoom')],
                                ['id' => 'slide-down', 'name' => $this->l('Deslizar abajo')],
                                ['id' => 'slide-up', 'name' => $this->l('Deslizar arriba')],
                            ],
                            'id' => 'id',
                            'name' => 'name'
                        ],
                    ],
                ],
                'submit' => ['title' => $this->l('Guardar')],
            ],
        ];
    }

    protected function getFormDesign()
    {
        return [
            'form' => [
                'legend' => ['title' => $this->l('🎨 Diseño y Textos'), 'icon' => 'icon-paint-brush'],
                'input' => [
                    // Textos
                    [
                        'type' => 'text',
                        'label' => $this->l('Título'),
                        'name' => 'SYSPNL_TITLE',
                        'desc' => $this->l('Título principal del popup'),
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->l('Subtítulo'),
                        'name' => 'SYSPNL_SUBTITLE',
                        'rows' => 3,
                        'desc' => $this->l('Texto descriptivo debajo del título'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Texto del botón'),
                        'name' => 'SYSPNL_BTN_TEXT',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Placeholder del campo email'),
                        'name' => 'SYSPNL_PLACEHOLDER',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Mensaje de éxito'),
                        'name' => 'SYSPNL_SUCCESS_MSG',
                        'desc' => $this->l('Se muestra tras suscribirse correctamente'),
                    ],
                    // Dimensiones
                    [
                        'type' => 'text',
                        'label' => $this->l('Ancho del popup (px)'),
                        'name' => 'SYSPNL_WIDTH',
                        'class' => 'fixed-width-sm',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Radio de bordes (px)'),
                        'name' => 'SYSPNL_BORDER_RADIUS',
                        'class' => 'fixed-width-sm',
                    ],
                    // Tipografía
                    [
                        'type' => 'select',
                        'label' => $this->l('Fuente'),
                        'name' => 'SYSPNL_FONT_FAMILY',
                        'options' => [
                            'query' => [
                                ['id' => 'inherit', 'name' => $this->l('Heredar del tema')],
                                ['id' => 'Arial, sans-serif', 'name' => 'Arial'],
                                ['id' => 'Georgia, serif', 'name' => 'Georgia'],
                                ['id' => "'Helvetica Neue', sans-serif", 'name' => 'Helvetica Neue'],
                                ['id' => "'Times New Roman', serif", 'name' => 'Times New Roman'],
                                ['id' => "'Trebuchet MS', sans-serif", 'name' => 'Trebuchet MS'],
                                ['id' => "Verdana, sans-serif", 'name' => 'Verdana'],
                                ['id' => "'Playfair Display', serif", 'name' => 'Playfair Display (Google)'],
                                ['id' => "'Montserrat', sans-serif", 'name' => 'Montserrat (Google)'],
                                ['id' => "'Lato', sans-serif", 'name' => 'Lato (Google)'],
                                ['id' => "'Open Sans', sans-serif", 'name' => 'Open Sans (Google)'],
                            ],
                            'id' => 'id',
                            'name' => 'name'
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Tamaño título (px)'),
                        'name' => 'SYSPNL_FONT_SIZE_TITLE',
                        'class' => 'fixed-width-sm',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Tamaño subtítulo (px)'),
                        'name' => 'SYSPNL_FONT_SIZE_SUBTITLE',
                        'class' => 'fixed-width-sm',
                    ],
                    // Colores
                    [
                        'type' => 'color',
                        'label' => $this->l('Color de fondo del popup'),
                        'name' => 'SYSPNL_COLOR_BG',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Color del overlay (fondo oscuro)'),
                        'name' => 'SYSPNL_COLOR_OVERLAY',
                        'desc' => $this->l('Formato: rgba(0,0,0,0.65) — puedes ajustar la opacidad'),
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Color del título'),
                        'name' => 'SYSPNL_COLOR_TITLE',
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Color del subtítulo'),
                        'name' => 'SYSPNL_COLOR_SUBTITLE',
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Color del botón (fondo)'),
                        'name' => 'SYSPNL_COLOR_BTN_BG',
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Color del botón (texto)'),
                        'name' => 'SYSPNL_COLOR_BTN_TEXT',
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Color borde del input'),
                        'name' => 'SYSPNL_COLOR_INPUT_BORDER',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Imagen de fondo (URL o ruta)'),
                        'name' => 'SYSPNL_BG_IMAGE',
                        'desc' => $this->l('Opcional. Ej: /img/popups/fondo.jpg'),
                        'placeholder' => '/img/popups/fondo.jpg',
                    ],
                ],
                'submit' => ['title' => $this->l('Guardar')],
            ],
        ];
    }

    protected function getFormDiscount()
    {
        return [
            'form' => [
                'legend' => ['title' => $this->l('🎁 Descuento Automático'), 'icon' => 'icon-tag'],
                'description' => $this->l('Si activas esta opción, se generará un código de descuento único y se mostrará al usuario tras suscribirse.'),
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Activar descuento'),
                        'name' => 'SYSPNL_DISCOUNT_ACTIVE',
                        'is_bool' => true,
                        'desc' => $this->l('Genera y muestra un cupón al suscribirse'),
                        'values' => [
                            ['id' => 'disc_on', 'value' => true, 'label' => $this->l('Sí')],
                            ['id' => 'disc_off', 'value' => false, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Tipo de descuento'),
                        'name' => 'SYSPNL_DISCOUNT_TYPE',
                        'options' => [
                            'query' => [
                                ['id' => 'percentage', 'name' => $this->l('Porcentaje (%)')],
                                ['id' => 'amount', 'name' => $this->l('Cantidad fija (€)')],
                                ['id' => 'shipping', 'name' => $this->l('Envío gratis')],
                            ],
                            'id' => 'id',
                            'name' => 'name'
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Valor del descuento'),
                        'name' => 'SYSPNL_DISCOUNT_VALUE',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Ej: 10 (para 10% o 10€). No aplica para envío gratis.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Mensaje con el cupón'),
                        'name' => 'SYSPNL_DISCOUNT_MSG',
                        'desc' => $this->l('Texto que acompaña al código generado'),
                    ],
                ],
                'submit' => ['title' => $this->l('Guardar')],
            ],
        ];
    }

    protected function getConfigValues()
    {
        return [
            'SYSPNL_ACTIVE' => Configuration::get('SYSPNL_ACTIVE'),
            'SYSPNL_FREQUENCY' => Configuration::get('SYSPNL_FREQUENCY'),
            'SYSPNL_FREQUENCY_VALUE' => Configuration::get('SYSPNL_FREQUENCY_VALUE'),
            'SYSPNL_DELAY' => Configuration::get('SYSPNL_DELAY'),
            'SYSPNL_TITLE' => Configuration::get('SYSPNL_TITLE'),
            'SYSPNL_SUBTITLE' => Configuration::get('SYSPNL_SUBTITLE'),
            'SYSPNL_BTN_TEXT' => Configuration::get('SYSPNL_BTN_TEXT'),
            'SYSPNL_PLACEHOLDER' => Configuration::get('SYSPNL_PLACEHOLDER'),
            'SYSPNL_FONT_FAMILY' => Configuration::get('SYSPNL_FONT_FAMILY'),
            'SYSPNL_FONT_SIZE_TITLE' => Configuration::get('SYSPNL_FONT_SIZE_TITLE'),
            'SYSPNL_FONT_SIZE_SUBTITLE' => Configuration::get('SYSPNL_FONT_SIZE_SUBTITLE'),
            'SYSPNL_COLOR_BG' => Configuration::get('SYSPNL_COLOR_BG'),
            'SYSPNL_COLOR_OVERLAY' => Configuration::get('SYSPNL_COLOR_OVERLAY'),
            'SYSPNL_COLOR_TITLE' => Configuration::get('SYSPNL_COLOR_TITLE'),
            'SYSPNL_COLOR_SUBTITLE' => Configuration::get('SYSPNL_COLOR_SUBTITLE'),
            'SYSPNL_COLOR_BTN_BG' => Configuration::get('SYSPNL_COLOR_BTN_BG'),
            'SYSPNL_COLOR_BTN_TEXT' => Configuration::get('SYSPNL_COLOR_BTN_TEXT'),
            'SYSPNL_COLOR_INPUT_BORDER' => Configuration::get('SYSPNL_COLOR_INPUT_BORDER'),
            'SYSPNL_BORDER_RADIUS' => Configuration::get('SYSPNL_BORDER_RADIUS'),
            'SYSPNL_WIDTH' => Configuration::get('SYSPNL_WIDTH'),
            'SYSPNL_ANIMATION' => Configuration::get('SYSPNL_ANIMATION'),
            'SYSPNL_DISCOUNT_ACTIVE' => Configuration::get('SYSPNL_DISCOUNT_ACTIVE'),
            'SYSPNL_DISCOUNT_TYPE' => Configuration::get('SYSPNL_DISCOUNT_TYPE'),
            'SYSPNL_DISCOUNT_VALUE' => Configuration::get('SYSPNL_DISCOUNT_VALUE'),
            'SYSPNL_DISCOUNT_MSG' => Configuration::get('SYSPNL_DISCOUNT_MSG'),
            'SYSPNL_SUCCESS_MSG' => Configuration::get('SYSPNL_SUCCESS_MSG'),
            'SYSPNL_BG_IMAGE' => Configuration::get('SYSPNL_BG_IMAGE'),
            'SYSPNL_POSITION' => Configuration::get('SYSPNL_POSITION'),
        ];
    }

    protected function postProcess()
    {
        $keys = [
            'SYSPNL_ACTIVE',
            'SYSPNL_FREQUENCY',
            'SYSPNL_FREQUENCY_VALUE',
            'SYSPNL_DELAY',
            'SYSPNL_TITLE',
            'SYSPNL_SUBTITLE',
            'SYSPNL_BTN_TEXT',
            'SYSPNL_PLACEHOLDER',
            'SYSPNL_FONT_FAMILY',
            'SYSPNL_FONT_SIZE_TITLE',
            'SYSPNL_FONT_SIZE_SUBTITLE',
            'SYSPNL_COLOR_BG',
            'SYSPNL_COLOR_OVERLAY',
            'SYSPNL_COLOR_TITLE',
            'SYSPNL_COLOR_SUBTITLE',
            'SYSPNL_COLOR_BTN_BG',
            'SYSPNL_COLOR_BTN_TEXT',
            'SYSPNL_COLOR_INPUT_BORDER',
            'SYSPNL_BORDER_RADIUS',
            'SYSPNL_WIDTH',
            'SYSPNL_ANIMATION',
            'SYSPNL_DISCOUNT_ACTIVE',
            'SYSPNL_DISCOUNT_TYPE',
            'SYSPNL_DISCOUNT_VALUE',
            'SYSPNL_DISCOUNT_MSG',
            'SYSPNL_SUCCESS_MSG',
            'SYSPNL_BG_IMAGE',
            'SYSPNL_POSITION',
        ];

        foreach ($keys as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

        return $this->displayConfirmation($this->l('Configuración guardada correctamente'));
    }

    // ── AJAX: métodos públicos llamados desde el FrontController ───────────

    /**
     * Comprueba si el email ya está en la tabla de suscriptores.
     */
    public function checkAlreadySubscribed($email, $idShop)
    {
        $result = Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'syspnl_subscribers`
             WHERE `email` = \'' . pSQL($email) . '\' AND `id_shop` = ' . (int) $idShop
        );
        return (bool) $result;
    }

    /**
     * Guarda el suscriptor en la tabla propia e intenta marcar newsletter en ps_customer.
     */
    public function saveNewSubscriber($email, $idShop)
    {
        Db::getInstance()->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'syspnl_subscribers`
            (`email`, `id_shop`, `date_add`)
            VALUES (\'' . pSQL($email) . '\', ' . (int) $idShop . ', \'' . pSQL(date('Y-m-d H:i:s')) . '\')'
        );

        try {
            $idCustomer = (int) Db::getInstance()->getValue(
                'SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'customer`
                WHERE `email` = "' . pSQL($email) . '"
                AND `deleted` = 0'
            );

            // Debug temporal
            $lastError = Db::getInstance()->getMsgError();
            file_put_contents(
                '/tmp/syspnl_debug.log',
                date('Y-m-d H:i:s') . ' PREFIX=' . _DB_PREFIX_ .
                ' email=' . $email .
                ' idCustomer=' . $idCustomer .
                ' dbError=' . $lastError .
                ' query=SELECT id_customer FROM ' . _DB_PREFIX_ . 'customer WHERE email=' . pSQL($email) . "\n",
                FILE_APPEND
            );

            if ($idCustomer) {
                Db::getInstance()->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'customer`
                    SET `newsletter` = 1
                    WHERE `id_customer` = ' . $idCustomer
                );
            }
        } catch (Exception $e) {
            file_put_contents(
                '/tmp/syspnl_debug.log',
                date('Y-m-d H:i:s') . ' EXCEPTION: ' . $e->getMessage() . "\n",
                FILE_APPEND
            );
        }
    }

    /**
     * Recupera el código de descuento ya generado para este email (si existe).
     */
    public function getExistingCode($email)
    {
        return Db::getInstance()->getValue(
            'SELECT `discount_code` FROM `' . _DB_PREFIX_ . 'syspnl_subscribers`
             WHERE `email` = \'' . pSQL($email) . '\' AND `discount_code` != \'\''
        );
    }

    /**
     * Crea un CartRule de PrestaShop y devuelve el código generado.
     * Compatible con PS 1.7 y PS 8.
     */
    public function createDiscountCode($email, $idShop, $idLang)
    {
        try {
            $type = Configuration::get('SYSPNL_DISCOUNT_TYPE');
            $value = (float) Configuration::get('SYSPNL_DISCOUNT_VALUE');
            $code = 'NL-' . strtoupper(substr(md5($email . microtime(true)), 0, 8));

            // Rellenar nombre para TODOS los idiomas activos (evita error de validación)
            $languages = Language::getLanguages(true, $idShop);
            $nameByLang = [];
            foreach ($languages as $lang) {
                $nameByLang[(int) $lang['id_lang']] = 'Newsletter ' . $code;
            }
            if (empty($nameByLang)) {
                $nameByLang[$idLang] = 'Newsletter ' . $code;
            }

            $cartRule = new CartRule();
            $cartRule->name = $nameByLang;
            $cartRule->code = $code;
            $cartRule->description = 'Descuento por suscripción al newsletter';
            $cartRule->quantity = 1;
            $cartRule->quantity_per_user = 1;
            $cartRule->priority = 1;
            $cartRule->partial_use = 0;
            $cartRule->highlight = 1;
            $cartRule->active = 1;
            $cartRule->date_from = date('Y-m-d H:i:s');
            $cartRule->date_to = date('Y-m-d H:i:s', strtotime('+1 year'));
            //$cartRule->id_shop           = (int) $idShop;

            // Inicializar todos los campos de reducción a 0 explícitamente
            $cartRule->reduction_percent = 0;
            $cartRule->reduction_amount = 0;
            $cartRule->reduction_tax = 1;
            $cartRule->reduction_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
            $cartRule->free_shipping = false;
            $cartRule->gift_product = 0;

            if ($type === 'percentage') {
                $cartRule->reduction_percent = (float) min(100, $value);
            } elseif ($type === 'amount') {
                $cartRule->reduction_amount = (float) $value;
                $cartRule->reduction_tax = 1;
                $cartRule->reduction_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
            } elseif ($type === 'shipping') {
                $cartRule->free_shipping = true;
            }

            if (!$cartRule->add()) {
                return false;
            }

            // Guardar el código en la tabla de suscriptores para recuperarlo si hace falta
            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'syspnl_subscribers`
                 SET `discount_code` = \'' . pSQL($code) . '\'
                 WHERE `email` = \'' . pSQL($email) . '\''
            );

            return $code;

        } catch (Exception $e) {
            return false;
        }
    }

    // ── HOOKS ───────────────────────────────────────────────────────────────

    public function hookActionFrontControllerSetMedia()
    {
        if (!Configuration::get('SYSPNL_ACTIVE')) {
            return;
        }

        $this->context->controller->registerStylesheet(
            'syspnl-front',
            $this->_path . 'views/css/front.css',
            ['media' => 'all', 'priority' => 200]
        );
        $this->context->controller->registerJavascript(
            'syspnl-front',
            $this->_path . 'views/js/front.js',
            ['position' => 'bottom', 'priority' => 200]
        );
    }

    /** Evitar doble render si ambos hooks están activos */
    private $popupRendered = false;

    public function hookDisplayBeforeBodyClosingTag()
    {
        $this->popupRendered = true;
        return $this->renderPopup();
    }
    public function hookDisplayFooter()
    {
        if ($this->popupRendered)
            return '';
        return $this->renderPopup();
    }

    protected function renderPopup()
    {
        if (!Configuration::get('SYSPNL_ACTIVE')) {
            return '';
        }

        $fontFamily = Configuration::get('SYSPNL_FONT_FAMILY');
        // Añadir Google Fonts si corresponde
        $googleFonts = ['Playfair Display', 'Montserrat', 'Lato', 'Open Sans'];
        $googleFontLink = '';
        foreach ($googleFonts as $gf) {
            if (strpos($fontFamily, $gf) !== false) {
                $googleFontLink = 'https://fonts.googleapis.com/css2?family=' . urlencode($gf) . ':wght@400;600;700&display=swap';
                break;
            }
        }

        // Construir estilos inline dinámicos
        $bgImage = Configuration::get('SYSPNL_BG_IMAGE');
        $bgStyle = 'background-color:' . Configuration::get('SYSPNL_COLOR_BG') . ';';
        if ($bgImage) {
            $bgStyle .= 'background-image:url(' . $bgImage . ');background-size:cover;background-position:center;';
        }

        // Intentar URL amigable primero, luego fallback a URL clásica
        try {
            $ajaxUrl = Tools::getShopDomainSsl(true, true)
                . '/index.php?fc=module&module=syspnewsletter&controller=subscribe';
        } catch (Exception $e) {
            $ajaxUrl = '';
        }
        if (empty($ajaxUrl)) {
            $ajaxUrl = Tools::getShopDomainSsl(true, true)
                . '/index.php?fc=module&module=syspnewsletter&controller=subscribe';
        }

        $this->context->smarty->assign([
            'syspnl_title' => Configuration::get('SYSPNL_TITLE'),
            'syspnl_subtitle' => Configuration::get('SYSPNL_SUBTITLE'),
            'syspnl_btn_text' => Configuration::get('SYSPNL_BTN_TEXT'),
            'syspnl_placeholder' => Configuration::get('SYSPNL_PLACEHOLDER'),
            'syspnl_success_msg' => Configuration::get('SYSPNL_SUCCESS_MSG'),
            'syspnl_frequency' => Configuration::get('SYSPNL_FREQUENCY'),
            'syspnl_frequency_val' => Configuration::get('SYSPNL_FREQUENCY_VALUE'),
            'syspnl_delay' => Configuration::get('SYSPNL_DELAY'),
            'syspnl_animation' => Configuration::get('SYSPNL_ANIMATION'),
            'syspnl_position' => Configuration::get('SYSPNL_POSITION'),
            'syspnl_width' => (int) Configuration::get('SYSPNL_WIDTH'),
            'syspnl_border_radius' => (int) Configuration::get('SYSPNL_BORDER_RADIUS'),
            'syspnl_color_overlay' => Configuration::get('SYSPNL_COLOR_OVERLAY'),
            'syspnl_color_title' => Configuration::get('SYSPNL_COLOR_TITLE'),
            'syspnl_color_subtitle' => Configuration::get('SYSPNL_COLOR_SUBTITLE'),
            'syspnl_color_btn_bg' => Configuration::get('SYSPNL_COLOR_BTN_BG'),
            'syspnl_color_btn_text' => Configuration::get('SYSPNL_COLOR_BTN_TEXT'),
            'syspnl_color_input_border' => Configuration::get('SYSPNL_COLOR_INPUT_BORDER'),
            'syspnl_font_family' => $fontFamily,
            'syspnl_font_size_title' => (int) Configuration::get('SYSPNL_FONT_SIZE_TITLE'),
            'syspnl_font_size_subtitle' => (int) Configuration::get('SYSPNL_FONT_SIZE_SUBTITLE'),
            'syspnl_bg_style' => $bgStyle,
            'syspnl_discount_active' => (bool) Configuration::get('SYSPNL_DISCOUNT_ACTIVE'),
            'syspnl_ajax_url' => $ajaxUrl,
            'syspnl_google_font' => $googleFontLink,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/newsletter_popup.tpl');
    }

    /**
     * Instala el tab de administración (aparece en el menú lateral de PS)
     */
    protected function installAdminTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminSyspNewsletterSubscribers';
        $tab->module = $this->name;
        $tab->id_parent = -1; // -1 = oculto del menú principal (accesible por URL)

        // Nombre visible en todos los idiomas activos
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Suscriptores Newsletter';
        }

        return (bool) $tab->add();
    }

    /**
     * Elimina el tab de administración al desinstalar
     */
    protected function uninstallAdminTab()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminSyspNewsletterSubscribers');
        if ($idTab) {
            $tab = new Tab($idTab);
            return (bool) $tab->delete();
        }
        return true;
    }
}
