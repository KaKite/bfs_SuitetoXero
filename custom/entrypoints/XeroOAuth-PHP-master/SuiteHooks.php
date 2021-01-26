<?php

class SuiteHooks
{
    public function syncToXero(SugarBean $bean, $event, $arguments)
    {
        $GLOBALS['log']->debug('syncToXero ' . $bean->name);
        if (class_exists('BfsSuitetoXeroHook')) {
            $GLOBALS['log']->debug('BfsSuitetoXeroHook exist'); // After_save should not called again, for this direct update
        } else {
            $GLOBALS['log']->debug('BfsSuitetoXeroHook not exist');
        }
        $GLOBALS['log']->debug($_REQUEST);

        // code for block when recall hooks start
        if (isset($_REQUEST['entryPoint']) && ($_REQUEST['entryPoint'] == 'xeroWebhooks' || $_REQUEST['entryPoint'] == 'xeroLink' || $_REQUEST['entryPoint'] == 'syncAllRecords')) {
            return true;
        }

        if (isset($_REQUEST['hook_called']) && $_REQUEST['hook_called'] == 1) {
            return true;
        }
        // code for block when recall hooks end

        $goAhead = true;
        // when this function called from AOS_Products_Quotes for saving Invoice in Xero
        // This file not called from invoices after save, cause that time item not created
        // here checing condition that , API call when all item should be saved
        if ($_REQUEST['module'] == 'AOS_Invoices') {
            $checkItemsSql = "SELECT * FROM aos_products_quotes WHERE parent_type = 'AOS_Invoices' AND parent_id = '" . $bean->parent_id . "' AND deleted = 0 and date_modified='" . $bean->date_modified . "'";
            $checkItemsResult = $GLOBALS['db']->query($checkItemsSql, false);
            $savedCount = $GLOBALS['db']->getRowCount($checkItemsResult);
            if ($savedCount != count($_REQUEST['product_product_id'])) {
                $goAhead = false;
            } else {
                $bean = BeanFactory::getBean('AOS_Invoices', $bean->parent_id); // on calling hook from productQuotes, here updating bean with Invoice
            }
        }

        if ($goAhead) {
            $GLOBALS['log']->debug('BfsSuitetoXeroHook befor calling');
            require_once('./custom/entrypoints/XeroOAuth-PHP-master/BfsSuitetoXeroHook.php');
            $bfsSuitetoXeroHook = new BfsSuitetoXeroHook();
            $bfsSuitetoXeroHook->writeLogger(('befr syncToXero'));
            $bfsSuitetoXeroHook->syncToXero($bean, $event, $arguments);
        }
    }
}
