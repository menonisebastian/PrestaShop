<?php
/**
 * SYSPROVIDER Popup Manager Module
 *
 * @author    SYSPROVIDER S.L.
 * @copyright 2024 SYSPROVIDER S.L.
 * @license   Commercial License
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SyspPopup extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'sysppopup';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.0';
        $this->author = 'SYSPROVIDER S.L.';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('SYSPROVIDER - Popup Manager');
        $this->description = $this->l('Gestiona popups personalizados con control de frecuencia y estilos avanzados');
        $this->confirmUninstall = $this->l('¿Estás seguro de que deseas desinstalar este módulo?');
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        include(dirname(__FILE__) . '/sql/install.php');

        // Crear directorio para imágenes
        $img_dir = _PS_IMG_DIR_ . 'popups';
        if (!file_exists($img_dir)) {
            mkdir($img_dir, 0755, true);
        }

        return parent::install() &&
            $this->registerHook('actionFrontControllerSetMedia') &&
            $this->registerHook('displayFooter') &&
            $this->registerHook('displayFooterBefore') &&
            $this->registerHook('displayWrapperBottom') &&
            $this->registerHook('displayBeforeBodyClosingTag') &&
            Configuration::updateValue('SYSPPOPUP_ACTIVE', true);
    }

    public function uninstall()
    {
        include(dirname(__FILE__) . '/sql/uninstall.php');

        Configuration::deleteByName('SYSPPOPUP_ACTIVE');

        return parent::uninstall();
    }

    /**
     * Cargar el contenido del formulario de configuración
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitSyspPopupModule')) {
            $output .= $this->postProcess();
        }

        // Añadir JavaScript y CSS para el backoffice
        $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        $this->context->controller->addJS($this->_path . 'views/js/back.js');

        return $output . $this->renderForm();
    }

    /**
     * Crear el formulario de configuración
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitSyspPopupModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Estructura del formulario
     */
    protected function getConfigForm()
    {
        // Obtener imágenes disponibles
        $images = $this->getAvailableImages();
        $image_options = array();
        foreach ($images as $image) {
            $image_options[] = array(
                'id' => $image,
                'name' => $image
            );
        }

        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Configuración del Popup'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Activar popup'),
                        'name' => 'SYSPPOPUP_ACTIVE',
                        'is_bool' => true,
                        'desc' => $this->l('Activar o desactivar el popup'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Sí')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('No')
                            )
                        ),
                    ),
                    array(
                        'type' => 'file',
                        'label' => $this->l('Subir nueva imagen'),
                        'name' => 'SYSPPOPUP_NEW_IMAGE',
                        'desc' => $this->l('Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 2MB'),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Seleccionar imagen'),
                        'name' => 'SYSPPOPUP_IMAGE',
                        'desc' => $this->l('Selecciona una imagen de las disponibles'),
                        'options' => array(
                            'query' => $image_options,
                            'id' => 'id',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Enlace'),
                        'name' => 'SYSPPOPUP_LINK',
                        'desc' => $this->l('URL a la que redirigirá el popup al hacer clic'),
                        'placeholder' => 'https://ejemplo.com',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Frecuencia de visualización'),
                        'name' => 'SYSPPOPUP_FREQUENCY',
                        'desc' => $this->l('Controla con qué frecuencia se muestra el popup'),
                        'options' => array(
                            'query' => array(
                                array('id' => 'always', 'name' => $this->l('Mostrar siempre')),
                                array('id' => 'once', 'name' => $this->l('Mostrar solo una vez')),
                                array('id' => 'hours', 'name' => $this->l('Mostrar cada X horas')),
                                array('id' => 'days', 'name' => $this->l('Mostrar cada X días')),
                            ),
                            'id' => 'id',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Valor de frecuencia'),
                        'name' => 'SYSPPOPUP_FREQUENCY_VALUE',
                        'desc' => $this->l('Número de horas o días (solo si has elegido "cada X horas/días")'),
                        'class' => 'fixed-width-sm',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Delay de apertura (segundos)'),
                        'name' => 'SYSPPOPUP_DELAY',
                        'desc' => $this->l('Tiempo de espera antes de mostrar el popup (en segundos). 0 = inmediato'),
                        'class' => 'fixed-width-sm',
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Pantalla completa'),
                        'name' => 'SYSPPOPUP_FULLSCREEN',
                        'is_bool' => true,
                        'desc' => $this->l('El popup ocupará toda la pantalla'),
                        'values' => array(
                            array(
                                'id' => 'fullscreen_on',
                                'value' => true,
                                'label' => $this->l('Sí')
                            ),
                            array(
                                'id' => 'fullscreen_off',
                                'value' => false,
                                'label' => $this->l('No')
                            )
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Estilo de bordes'),
                        'name' => 'SYSPPOPUP_BORDER_STYLE',
                        'options' => array(
                            'query' => array(
                                array('id' => 'none', 'name' => $this->l('Sin borde')),
                                array('id' => 'rounded', 'name' => $this->l('Bordes redondeados')),
                                array('id' => 'sharp', 'name' => $this->l('Bordes rectos')),
                                array('id' => 'circle', 'name' => $this->l('Muy redondeado')),
                            ),
                            'id' => 'id',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Sombra'),
                        'name' => 'SYSPPOPUP_SHADOW',
                        'options' => array(
                            'query' => array(
                                array('id' => 'none', 'name' => $this->l('Sin sombra')),
                                array('id' => 'light', 'name' => $this->l('Sombra ligera')),
                                array('id' => 'medium', 'name' => $this->l('Sombra media')),
                                array('id' => 'heavy', 'name' => $this->l('Sombra pronunciada')),
                            ),
                            'id' => 'id',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Ancho del popup'),
                        'name' => 'SYSPPOPUP_WIDTH_TYPE',
                        'desc' => $this->l('Selecciona el ancho del popup'),
                        'options' => array(
                            'query' => array(
                                array('id' => '400', 'name' => $this->l('Pequeño (400px)')),
                                array('id' => '500', 'name' => $this->l('Mediano (500px)')),
                                array('id' => '600', 'name' => $this->l('Normal (600px)')),
                                array('id' => '700', 'name' => $this->l('Grande (700px)')),
                                array('id' => '800', 'name' => $this->l('Muy grande (800px)')),
                                array('id' => 'auto', 'name' => $this->l('Auto (según imagen)')),
                                array('id' => 'custom', 'name' => $this->l('Personalizado')),
                            ),
                            'id' => 'id',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Ancho personalizado (px)'),
                        'name' => 'SYSPPOPUP_MAX_WIDTH',
                        'desc' => $this->l('Solo si has elegido "Personalizado" arriba'),
                        'class' => 'fixed-width-sm',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Altura del popup'),
                        'name' => 'SYSPPOPUP_HEIGHT_TYPE',
                        'desc' => $this->l('Selecciona la altura del popup'),
                        'options' => array(
                            'query' => array(
                                array('id' => '300', 'name' => $this->l('Pequeña (300px)')),
                                array('id' => '400', 'name' => $this->l('Mediana (400px)')),
                                array('id' => '500', 'name' => $this->l('Normal (500px)')),
                                array('id' => '600', 'name' => $this->l('Grande (600px)')),
                                array('id' => '700', 'name' => $this->l('Muy grande (700px)')),
                                array('id' => 'auto', 'name' => $this->l('Auto (según imagen)')),
                                array('id' => 'custom', 'name' => $this->l('Personalizado')),
                            ),
                            'id' => 'id',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Altura personalizada (px)'),
                        'name' => 'SYSPPOPUP_MAX_HEIGHT',
                        'desc' => $this->l('Solo si has elegido "Personalizado" arriba'),
                        'class' => 'fixed-width-sm',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Animación de entrada'),
                        'name' => 'SYSPPOPUP_ANIMATION',
                        'options' => array(
                            'query' => array(
                                array('id' => 'fade', 'name' => $this->l('Fade In')),
                                array('id' => 'slide-down', 'name' => $this->l('Deslizar desde arriba')),
                                array('id' => 'slide-up', 'name' => $this->l('Deslizar desde abajo')),
                                array('id' => 'zoom', 'name' => $this->l('Zoom In')),
                            ),
                            'id' => 'id',
                            'name' => 'name'
                        )
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Guardar'),
                ),
            ),
        );
    }

    /**
     * Obtener valores actuales de configuración
     */
    protected function getConfigFormValues()
    {
        return array(
            'SYSPPOPUP_ACTIVE' => Configuration::get('SYSPPOPUP_ACTIVE', true),
            'SYSPPOPUP_IMAGE' => Configuration::get('SYSPPOPUP_IMAGE', ''),
            'SYSPPOPUP_LINK' => Configuration::get('SYSPPOPUP_LINK', ''),
            'SYSPPOPUP_FREQUENCY' => Configuration::get('SYSPPOPUP_FREQUENCY', 'always'),
            'SYSPPOPUP_FREQUENCY_VALUE' => Configuration::get('SYSPPOPUP_FREQUENCY_VALUE', '24'),
            'SYSPPOPUP_DELAY' => Configuration::get('SYSPPOPUP_DELAY', '2'),
            'SYSPPOPUP_FULLSCREEN' => Configuration::get('SYSPPOPUP_FULLSCREEN', false),
            'SYSPPOPUP_BORDER_STYLE' => Configuration::get('SYSPPOPUP_BORDER_STYLE', 'rounded'),
            'SYSPPOPUP_SHADOW' => Configuration::get('SYSPPOPUP_SHADOW', 'medium'),
            'SYSPPOPUP_WIDTH_TYPE' => Configuration::get('SYSPPOPUP_WIDTH_TYPE', '600'),
            'SYSPPOPUP_MAX_WIDTH' => Configuration::get('SYSPPOPUP_MAX_WIDTH', '600'),
            'SYSPPOPUP_HEIGHT_TYPE' => Configuration::get('SYSPPOPUP_HEIGHT_TYPE', 'auto'),
            'SYSPPOPUP_MAX_HEIGHT' => Configuration::get('SYSPPOPUP_MAX_HEIGHT', '500'),
            'SYSPPOPUP_ANIMATION' => Configuration::get('SYSPPOPUP_ANIMATION', 'fade'),
        );
    }

    /**
     * Procesar el formulario
     */
    protected function postProcess()
    {
        $output = '';

        // Procesar subida de imagen
        if (isset($_FILES['SYSPPOPUP_NEW_IMAGE']) && $_FILES['SYSPPOPUP_NEW_IMAGE']['error'] == 0) {
            $upload = $this->processImageUpload($_FILES['SYSPPOPUP_NEW_IMAGE']);
            if ($upload['success']) {
                Configuration::updateValue('SYSPPOPUP_IMAGE', $upload['filename']);
                $output .= $this->displayConfirmation($this->l('Imagen subida correctamente: ') . $upload['filename']);
            } else {
                $output .= $this->displayError($upload['error']);
            }
        }

        // Guardar configuraciones
        $form_values = array(
            'SYSPPOPUP_ACTIVE',
            'SYSPPOPUP_IMAGE',
            'SYSPPOPUP_LINK',
            'SYSPPOPUP_FREQUENCY',
            'SYSPPOPUP_FREQUENCY_VALUE',
            'SYSPPOPUP_DELAY',
            'SYSPPOPUP_FULLSCREEN',
            'SYSPPOPUP_BORDER_STYLE',
            'SYSPPOPUP_SHADOW',
            'SYSPPOPUP_WIDTH_TYPE',
            'SYSPPOPUP_MAX_WIDTH',
            'SYSPPOPUP_HEIGHT_TYPE',
            'SYSPPOPUP_MAX_HEIGHT',
            'SYSPPOPUP_ANIMATION',
        );

        foreach ($form_values as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

        $output .= $this->displayConfirmation($this->l('Configuración actualizada correctamente'));

        return $output;
    }

    /**
     * Procesar subida de imagen
     */
    protected function processImageUpload($file)
    {
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');
        $max_size = 2 * 1024 * 1024; // 2MB

        $filename = $file['name'];
        $tmp_name = $file['tmp_name'];
        $file_size = $file['size'];

        // Validar tamaño
        if ($file_size > $max_size) {
            return array('success' => false, 'error' => $this->l('El archivo es demasiado grande. Máximo 2MB.'));
        }

        // Validar extensión
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed_extensions)) {
            return array('success' => false, 'error' => $this->l('Formato de archivo no permitido.'));
        }

        // Generar nombre único
        $new_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.]/', '_', $filename);
        $destination = _PS_IMG_DIR_ . 'popups/' . $new_filename;

        // Mover archivo
        if (move_uploaded_file($tmp_name, $destination)) {
            return array('success' => true, 'filename' => $new_filename);
        } else {
            return array('success' => false, 'error' => $this->l('Error al subir el archivo.'));
        }
    }

    /**
     * Obtener imágenes disponibles
     */
    protected function getAvailableImages()
    {
        $img_dir = _PS_IMG_DIR_ . 'popups/';
        $images = array();

        if (is_dir($img_dir)) {
            $files = scandir($img_dir);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..' && preg_match('/\.(jpg|jpeg|png|gif)$/i', $file)) {
                    $images[] = $file;
                }
            }
        }

        return $images;
    }

    /**
     * Hook actionFrontControllerSetMedia - Añadir CSS y JS (PS 1.7+)
     */
    public function hookActionFrontControllerSetMedia()
    {
        if (!Configuration::get('SYSPPOPUP_ACTIVE')) {
            return;
        }

        $this->context->controller->registerStylesheet(
            'sysppopup-front',
            $this->_path . 'views/css/front.css',
            ['media' => 'all', 'priority' => 200]
        );
        $this->context->controller->registerJavascript(
            'sysppopup-front',
            $this->_path . 'views/js/front.js',
            ['position' => 'bottom', 'priority' => 200]
        );
    }

    /**
     * Hooks alternativos PS8 - todos llaman al mismo método
     */
    public function hookDisplayBeforeBodyClosingTag() { return $this->hookDisplayFooter(); }
    public function hookDisplayFooterBefore() { return $this->hookDisplayFooter(); }
    public function hookDisplayWrapperBottom() { return $this->hookDisplayFooter(); }

    /**
     * Hook DisplayFooter - Mostrar el popup
     */
    public function hookDisplayFooter()
    {
        if (!Configuration::get('SYSPPOPUP_ACTIVE')) {
            return;
        }

        $image = Configuration::get('SYSPPOPUP_IMAGE');
        if (empty($image)) {
            return;
        }

        // Calcular ancho
        $width_type = Configuration::get('SYSPPOPUP_WIDTH_TYPE');
        $popup_width = ($width_type === 'custom') ? Configuration::get('SYSPPOPUP_MAX_WIDTH') : $width_type;
        
        // Calcular altura
        $height_type = Configuration::get('SYSPPOPUP_HEIGHT_TYPE');
        $popup_height = ($height_type === 'custom') ? Configuration::get('SYSPPOPUP_MAX_HEIGHT') : $height_type;

        $this->context->smarty->assign(array(
            'popup_image' => _PS_IMG_ . 'popups/' . $image,
            'popup_link' => Configuration::get('SYSPPOPUP_LINK'),
            'popup_frequency' => Configuration::get('SYSPPOPUP_FREQUENCY'),
            'popup_frequency_value' => Configuration::get('SYSPPOPUP_FREQUENCY_VALUE'),
            'popup_delay' => Configuration::get('SYSPPOPUP_DELAY'),
            'popup_fullscreen' => Configuration::get('SYSPPOPUP_FULLSCREEN'),
            'popup_border_style' => Configuration::get('SYSPPOPUP_BORDER_STYLE'),
            'popup_shadow' => Configuration::get('SYSPPOPUP_SHADOW'),
            'popup_width' => $popup_width,
            'popup_height' => $popup_height,
            'popup_animation' => Configuration::get('SYSPPOPUP_ANIMATION'),
        ));

        return $this->display(__FILE__, 'views/templates/hook/popup.tpl');
    }
}
