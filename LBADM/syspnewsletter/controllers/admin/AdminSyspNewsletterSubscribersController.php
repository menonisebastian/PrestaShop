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

        parent::__construct();

        $this->module = Module::getInstanceByName('syspnewsletter');
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
}