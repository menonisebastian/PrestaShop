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

class AdminSyspNewsletterSuscribersController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'syspnl_subscribers';
        $this->className = 'ObjectModel'; // usamos queries directas
        $this->identifier = 'id_subscriber';
        $this->lang = false;
        $this->allow_export = true;

        parent::__construct();

        $this->module = Module::getInstanceByName('syspnewsletter');
        $this->meta_title = $this->l('Suscriptores Newsletter');

        // Columnas de la lista
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

        // Opciones de lista
        $this->list_simple_header = false;
        $this->toolbar_scroll = false;

        // Acciones por fila
        $this->actions = ['delete'];

        // Bulk actions
        $this->bulk_actions = [
            'delete' => [
                'text' => $this->l('Eliminar seleccionados'),
                'confirm' => $this->l('¿Eliminar los suscriptores seleccionados?'),
                'icon' => 'icon-trash',
            ],
        ];
    }

    /**
     * Sobreescribimos initContent para inyectar las estadísticas y el CSS propio
     * antes del listado estándar de PrestaShop.
     */
    public function initContent()
    {
        $this->context->controller->addCSS(
            _MODULE_DIR_ . 'syspnewsletter/views/css/admin_subscribers.css'
        );

        // Estadísticas rápidas
        $db = Db::getInstance();
        $table = _DB_PREFIX_ . 'syspnl_subscribers';

        $total = (int) $db->getValue('SELECT COUNT(*) FROM `' . $table . '`');

        $today = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . $table . '` WHERE DATE(date_add) = CURDATE()'
        );

        $thisMonth = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . $table . '`
             WHERE YEAR(date_add) = YEAR(NOW()) AND MONTH(date_add) = MONTH(NOW())'
        );

        $withCoupon = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . $table . '` WHERE discount_code != \'\''
        );

        // Últimos 30 días — agrupar por día para mini-gráfico
        $rows = $db->executeS(
            'SELECT DATE(date_add) AS day, COUNT(*) AS cnt
             FROM `' . $table . '`
             WHERE date_add >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(date_add)
             ORDER BY day ASC'
        );

        $chartLabels = [];
        $chartData = [];
        foreach ($rows as $r) {
            $chartLabels[] = $r['day'];
            $chartData[] = (int) $r['cnt'];
        }

        $this->context->smarty->assign([
            'syspnl_total' => $total,
            'syspnl_today' => $today,
            'syspnl_month' => $thisMonth,
            'syspnl_with_coupon' => $withCoupon,
            'syspnl_chart_labels' => json_encode($chartLabels),
            'syspnl_chart_data' => json_encode($chartData),
            'syspnl_module_url' => $this->context->link->getAdminLink('AdminModules') .
                '&configure=syspnewsletter',
        ]);

        // Renderizar cabecera de estadísticas
        $statsHtml = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'syspnewsletter/views/templates/admin/subscribers_stats.tpl'
        );

        // Prepend al contenido estándar
        $this->content = $statsHtml;

        parent::initContent();
    }

    /**
     * Query base para el listado
     */
    public function getList(
        $id_lang,
        $orderBy = null,
        $orderWay = null,
        $start = 0,
        $limit = null,
        $id_lang_shop = false
    ) {
        parent::getList($id_lang, $orderBy, $orderWay, $start, $limit, $id_lang_shop);
    }

    /**
     * Ruta de la query SELECT principal
     */
    protected function getFromClause()
    {
        return ' FROM `' . _DB_PREFIX_ . 'syspnl_subscribers` a ';
    }

    /**
     * Export CSV de todos los suscriptores
     */
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
        // BOM para Excel
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Email', 'Cupón', 'Tienda', 'Fecha suscripción'], ';');

        foreach ($rows as $r) {
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

    /**
     * Añadir botón de exportación a la barra de herramientas
     */
    public function initToolbar()
    {
        parent::initToolbar();

        $exportUrl = $this->context->link->getAdminLink('AdminSyspNewsletterSubscribers')
            . '&export_csv=1';

        $this->toolbar_btn['export'] = [
            'href' => $exportUrl,
            'desc' => $this->l('Exportar CSV'),
            'icon' => 'process-icon-download',
            'class' => 'btn btn-default',
        ];
    }
}