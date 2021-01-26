<?php
// Extension of class MassUpdate to allow MassUpdate of field of type VarChar (text field)

if (! defined('sugarEntry') || ! sugarEntry)
    die('Not A Valid Entry Point');

require_once('include/MassUpdate.php');

class CustomMassUpdate extends MassUpdate {

    /**
     * Override of this method to allow MassUpdate of field of type VarChar (text field)
     * @param string $displayname field label
     * @param string $field field name
     * @param bool $even even or odd
     * @return string html field data
     */
    protected function addDefault($displayname, $field, &$even) {       

        if ($field["type"] == 'varchar' && isset($field["massupdate"]) && $field["massupdate"]) {
            $even = ! $even;
            $varname = $field["name"];
            $displayname = addslashes($displayname);
            $html = <<<EOQ
    <td scope="row" width="20%">$displayname</td>
    <td class="dataField" width="30%"><input type="text" name="$varname" id="mass_{$varname}"/></td>
EOQ;
            return $html;
        }
        else
            return '';
    }

}
?>
