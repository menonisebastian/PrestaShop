<?php
/**
 * SYSPROVIDER Newsletter Popup — Admin Subscribers Controller
 *
 * @author    SYSPROVIDER S.L.
 * @copyright 2024 SYSPROVIDER S.L.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminSyspNewsletterSubscribersController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'syspnl_subscribers';
        $this->identifier = 'id_subscriber';
        $this->className = 'Configuration'; // clase dummy
        $this->lang = false;

        $this->module = Module::getInstanceByName('syspnewsletter');

        // Sincronizamos la base de datos local con la nativa de PrestaShop antes de cargar nada
        $this->syncSubscribers();

        parent::__construct();

        $this->meta_title = $this->l('Todos los Suscriptores (Sincronizado)');

        // ── Columnas ──────────────────────────────────────────────────────
        $this->fields_list = [
            'id_subscriber' => [
                'title' => '#',
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'email' => [
                'title' => $this->l('Email'),
                'havingFilter' => true,
            ],
            'discount_code' => [
                'title' => $this->l('Cupón generado'),
                'align' => 'center',
                'empty_value' => '—',
            ],
            'id_shop' => [
                'title' => $this->l('Tienda'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'date_add' => [
                'title' => $this->l('Fecha de suscripción'),
                'align' => 'center',
                'type' => 'datetime',
                'havingFilter' => true,
            ],
        ];

        // ── Acciones ──────────────────────────────────────────────────────
        $this->actions = ['delete'];
        $this->bulk_actions = [
            'delete' => [
                'text' => $this->l('Darlos de baja'),
                'confirm' => $this->l('¿Dar de baja del boletín a los seleccionados?'),
                'icon' => 'icon-trash',
            ],
        ];
    }

    /**
     * MAGIA PURA: Esta función asegura que tu tabla interna del popup sea un espejo exacto 
     * de los clientes suscritos (clientes reales + invitados).
     */
    private function syncSubscribers()
    {
        $db = Db::getInstance();
        $pfx = _DB_PREFIX_;

        // 1. Añadir clientes registrados que activaron el newsletter manualmente
        $db->execute("
            INSERT IGNORE INTO `{$pfx}syspnl_subscribers` (email, id_shop, date_add)
            SELECT email, id_shop, newsletter_date_add 
            FROM `{$pfx}customer` 
            WHERE newsletter = 1
        ");

        // 2. Añadir invitados del módulo nativo
        $table_exists = $db->executeS("SHOW TABLES LIKE '{$pfx}emailsubscription'");
        if (!empty($table_exists)) {
            $db->execute("
                INSERT IGNORE INTO `{$pfx}syspnl_subscribers` (email, id_shop, date_add)
                SELECT email, id_shop, newsletter_date_add 
                FROM `{$pfx}emailsubscription` 
                WHERE active = 1
            ");
        }

        // 3. Limpiar a los que se hayan dado de baja de forma nativa (desde su perfil)
        $db->execute("
            DELETE s FROM `{$pfx}syspnl_subscribers` s
            LEFT JOIN `{$pfx}customer` c ON s.email = c.email AND s.id_shop = c.id_shop AND c.newsletter = 1
            LEFT JOIN `{$pfx}emailsubscription` e ON s.email = e.email AND s.id_shop = e.id_shop AND e.active = 1
            WHERE c.id_customer IS NULL AND e.id IS NULL
        ");
    }

    // ── Sobrescribir SELECT para usar nuestra tabla ────────────────────────

    public function renderList()
    {
        $this->_select = 'a.id_subscriber, a.email, a.discount_code, a.id_shop, a.date_add';
        $this->_orderBy = 'date_add';
        $this->_orderWay = 'DESC';
        return parent::renderList();
    }

    // ── Inyectar stats + CSS antes del listado ─────────────────────────────

    public function initContent()
    {
        $this->context->controller->addCSS(
            _MODULE_DIR_ . 'syspnewsletter/views/css/admin_subscribers.css'
        );

        $db = Db::getInstance();
        $tbl = _DB_PREFIX_ . 'syspnl_subscribers';

        $total = (int) $db->getValue('SELECT COUNT(*) FROM `' . $tbl . '`');
        $today = (int) $db->getValue('SELECT COUNT(*) FROM `' . $tbl . '` WHERE DATE(date_add) = CURDATE()');
        $thisMonth = (int) $db->getValue('SELECT COUNT(*) FROM `' . $tbl . '` WHERE YEAR(date_add)=YEAR(NOW()) AND MONTH(date_add)=MONTH(NOW())');
        $withCoupon = (int) $db->getValue('SELECT COUNT(*) FROM `' . $tbl . '` WHERE discount_code != \'\'');

        $rows = $db->executeS(
            'SELECT DATE(date_add) AS day, COUNT(*) AS cnt
             FROM `' . $tbl . '`
             WHERE date_add >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(date_add)
             ORDER BY day ASC'
        );

        $chartLabels = [];
        $chartData = [];
        foreach ((array) $rows as $r) {
            $chartLabels[] = $r['day'];
            $chartData[] = (int) $r['cnt'];
        }

        $moduleUrl = $this->context->link->getAdminLink('AdminModules') . '&configure=syspnewsletter';
        $exportUrl = $this->context->link->getAdminLink('AdminSyspNewsletterSubscribers') . '&export_csv=1';

        $this->context->smarty->assign([
            'syspnl_total' => $total,
            'syspnl_today' => $today,
            'syspnl_month' => $thisMonth,
            'syspnl_with_coupon' => $withCoupon,
            'syspnl_chart_labels' => json_encode($chartLabels),
            'syspnl_chart_data' => json_encode($chartData),
            'syspnl_module_url' => $moduleUrl,
            'syspnl_export_url' => $exportUrl,
        ]);

        $statsHtml = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'syspnewsletter/views/templates/admin/subscribers_stats.tpl'
        );

        $this->content = $statsHtml;

        parent::initContent();
    }

    // ── Exportar CSV ───────────────────────────────────────────────────────

    public function initProcess()
    {
        parent::initProcess();

        if (Tools::getValue('export_csv')) {
            $this->exportCsv();
        }
    }

    protected function exportCsv()
    {
        $rows = Db::getInstance()->executeS(
            'SELECT email, discount_code, id_shop, date_add
             FROM `' . _DB_PREFIX_ . 'syspnl_subscribers`
             ORDER BY date_add DESC'
        );

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="newsletter_subscribers_' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Email', 'Cupón', 'Tienda', 'Fecha suscripción'], ';');

        foreach ((array) $rows as $r) {
            fputcsv($out, [
                $r['email'],
                $r['discount_code'] ?: '—',
                $r['id_shop'],
                $r['date_add'],
            ], ';');
        }

        fclose($out);
        exit;
    }

    public function initToolbar()
    {
        parent::initToolbar();

        $this->toolbar_btn['export'] = [
            'href' => $this->context->link->getAdminLink('AdminSyspNewsletterSubscribers') . '&export_csv=1',
            'desc' => $this->l('Exportar CSV'),
            'icon' => 'process-icon-download',
            'class' => 'btn btn-default',
        ];

        unset($this->toolbar_btn['new']);
    }

    // ── Borrado Individual y Sincronizar (Darse de baja real) ────────────────
    public function processDelete()
    {
        $id = (int) Tools::getValue($this->identifier);

        if ($id) {
            $db = Db::getInstance();
            $email = $db->getValue('SELECT `email` FROM `' . _DB_PREFIX_ . bqSQL($this->table) . '` WHERE `' . bqSQL($this->identifier) . '` = ' . $id);

            if ($email) {
                $email = strtolower(trim($email));

                // 1. Clientes registrados: desuscribir mediante Objeto (limpia caché)
                $customers = Customer::getCustomersByEmail($email);
                if (!empty($customers)) {
                    foreach ($customers as $custData) {
                        $customer = new Customer((int)$custData['id_customer']);
                        if ($customer->id && $customer->newsletter) {
                            $customer->newsletter = 0;
                            $customer->update(); 
                        }
                    }
                }

                // 2. Invitados
                $table_exists = $db->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . 'emailsubscription"');
                if (!empty($table_exists)) {
                    $db->execute('UPDATE `' . _DB_PREFIX_ . 'emailsubscription` SET `active` = 0 WHERE `email` = \'' . pSQL($email) . '\'');
                }
                
                Hook::exec('actionNewsletterRegistrationAfter', [
                    'email' => $email,
                    'action' => 'unsubscribe',
                    'error' => false
                ]);
            }

            // 3. Borrar visualmente de la tabla
            $sql = 'DELETE FROM `' . _DB_PREFIX_ . bqSQL($this->table) . '` WHERE `' . bqSQL($this->identifier) . '` = ' . $id;

            if ($db->execute($sql)) {
                $this->redirect_after = self::$currentIndex . '&conf=1&token=' . $this->token;
            } else {
                $this->errors[] = $this->l('Error al intentar eliminar el suscriptor.');
            }
        }
        return false;
    }

    protected function processBulkDelete()
    {
        if (is_array($this->boxes) && !empty($this->boxes)) {
            $ids = array_map('intval', $this->boxes);
            $id_list = implode(',', $ids);
            $db = Db::getInstance();

            $emails = $db->executeS('SELECT `email` FROM `' . _DB_PREFIX_ . bqSQL($this->table) . '` WHERE `' . bqSQL($this->identifier) . '` IN (' . $id_list . ')');

            if ($emails) {
                $table_exists = $db->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . 'emailsubscription"');
                foreach ($emails as $row) {
                    $email = strtolower(trim($row['email']));

                    // 1. Clientes
                    $customers = Customer::getCustomersByEmail($email);
                    if (!empty($customers)) {
                        foreach ($customers as $custData) {
                            $customer = new Customer((int)$custData['id_customer']);
                            if ($customer->id && $customer->newsletter) {
                                $customer->newsletter = 0;
                                $customer->update(); 
                            }
                        }
                    }

                    // 2. Invitados
                    if (!empty($table_exists)) {
                        $db->execute('UPDATE `' . _DB_PREFIX_ . 'emailsubscription` SET `active` = 0 WHERE `email` = \'' . pSQL($email) . '\'');
                    }
                    
                    Hook::exec('actionNewsletterRegistrationAfter', [
                        'email' => $email,
                        'action' => 'unsubscribe',
                        'error' => false
                    ]);
                }
            }

            // 3. Tabla interna
            $sql = 'DELETE FROM `' . _DB_PREFIX_ . bqSQL($this->table) . '` WHERE `' . bqSQL($this->identifier) . '` IN (' . $id_list . ')';

            if ($db->execute($sql)) {
                $this->redirect_after = self::$currentIndex . '&conf=2&token=' . $this->token;
            } else {
                $this->errors[] = $this->l('Error al eliminar los suscriptores seleccionados.');
            }
        }
    }
}