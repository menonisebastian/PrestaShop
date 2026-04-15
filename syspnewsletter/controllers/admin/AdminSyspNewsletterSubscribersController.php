<?php
/**
 * SYSPROVIDER Newsletter Popup — Admin Subscribers Controller
 *
 * Ruta correcta: /modules/syspnewsletter/controllers/admin/AdminSyspNewsletterSubscribersController.php
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
        $this->className = 'Configuration'; // clase dummy — usamos queries directas
        $this->lang = false;

        $this->module = Module::getInstanceByName('syspnewsletter');

        parent::__construct();

        $this->meta_title = $this->l('Suscriptores Newsletter');

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
                'text' => $this->l('Eliminar seleccionados'),
                'confirm' => $this->l('¿Eliminar los suscriptores seleccionados?'),
                'icon' => 'icon-trash',
            ],
        ];
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
        // CSS propio
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
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM para Excel
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

    // ── Botón exportar en toolbar ──────────────────────────────────────────

    public function initToolbar()
    {
        parent::initToolbar();

        $this->toolbar_btn['export'] = [
            'href' => $this->context->link->getAdminLink('AdminSyspNewsletterSubscribers') . '&export_csv=1',
            'desc' => $this->l('Exportar CSV'),
            'icon' => 'process-icon-download',
            'class' => 'btn btn-default',
        ];

        // Quitar botón "Añadir nuevo" (no aplica)
        unset($this->toolbar_btn['new']);
    }

    // ── Sobrescribir el Borrado Individual y Sincronizar ────────────────────
    public function processDelete()
    {
        $id = (int) Tools::getValue($this->identifier);

        if ($id) {
            // 1. Obtenemos el email ANTES de borrarlo de nuestra tabla
            $email = Db::getInstance()->getValue(
                'SELECT `email` FROM `' . _DB_PREFIX_ . bqSQL($this->table) . '` 
                 WHERE `' . bqSQL($this->identifier) . '` = ' . $id
            );

            if ($email) {
                $email = strtolower(trim($email)); // ← AÑADIR ESTA LÍNEA
                Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'customer` SET `newsletter` = 0 WHERE LOWER(`email`) = \'' . pSQL($email) . '\'');
                Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'emailsubscription` SET `active` = 0 WHERE LOWER(`email`) = \'' . pSQL($email) . '\'');
            }
            // 3. Ahora sí, lo borramos de la tabla interna del módulo
            $sql = 'DELETE FROM `' . _DB_PREFIX_ . bqSQL($this->table) . '` WHERE `' . bqSQL($this->identifier) . '` = ' . $id;

            if (Db::getInstance()->execute($sql)) {
                $this->redirect_after = self::$currentIndex . '&conf=1&token=' . $this->token;
            } else {
                $this->errors[] = $this->l('Error al intentar eliminar el suscriptor.');
            }
        }

        return false;
    }

    // ── Sobrescribir el Borrado Masivo y Sincronizar ────────────────────────
    protected function processBulkDelete()
    {
        if (is_array($this->boxes) && !empty($this->boxes)) {
            $ids = array_map('intval', $this->boxes);
            $id_list = implode(',', $ids);

            // 1. Obtenemos todos los emails de los IDs seleccionados
            $emails = Db::getInstance()->executeS(
                'SELECT `email` FROM `' . _DB_PREFIX_ . bqSQL($this->table) . '` 
                 WHERE `' . bqSQL($this->identifier) . '` IN (' . $id_list . ')'
            );

            // 2. Desuscribimos a todos de las tablas nativas
            foreach ($emails as $row) {
                $e = strtolower(trim($row['email'])); // ← strtolower() aquí también
                Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'customer` SET `newsletter` = 0 WHERE LOWER(`email`) = \'' . pSQL($e) . '\'');
                Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'emailsubscription` SET `active` = 0 WHERE LOWER(`email`) = \'' . pSQL($e) . '\'');
            }

            // 3. Los borramos de la tabla interna del módulo
            $sql = 'DELETE FROM `' . _DB_PREFIX_ . bqSQL($this->table) . '` WHERE `' . bqSQL($this->identifier) . '` IN (' . $id_list . ')';

            if (Db::getInstance()->execute($sql)) {
                $this->redirect_after = self::$currentIndex . '&conf=2&token=' . $this->token;
            } else {
                $this->errors[] = $this->l('Error al eliminar los suscriptores seleccionados.');
            }
        }
    }
}