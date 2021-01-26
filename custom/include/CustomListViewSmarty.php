<?php
// Extension of class ListViewSmarty to allow MassUpdate of field of type TextArea

if (! defined('sugarEntry') || ! sugarEntry)
    die('Not A Valid Entry Point');

require_once('include/ListView/ListViewSmarty.php');
require_once('custom/include/CustomMassUpdate.php');

class CustomListViewSmarty extends ListViewSmarty {

    /**
     * @return MassUpdate instance
     */
    protected function getMassUpdate() {
        return new CustomMassUpdate();
    }

}
?>