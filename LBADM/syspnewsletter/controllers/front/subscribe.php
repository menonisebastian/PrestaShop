<?php
/**
 * AJAX Controller – Newsletter subscribe
 *
 * @author    SYSPROVIDER S.L.
 * @copyright 2024 SYSPROVIDER S.L.
 */

class SyspNewsletterSubscribeModuleFrontController extends ModuleFrontController
{
    public $ajax = true;

    public function initContent()
    {
        parent::initContent();
        $this->module->displayAjax();
    }
}
