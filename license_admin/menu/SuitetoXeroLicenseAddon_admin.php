<?php

global $sugar_version;

$admin_option_defs=array();

if(preg_match( "/^6.*/", $sugar_version) ) {
    $admin_option_defs['Administration']['suitetoxerolicenseaddon_info']= array('helpInline','LBL_SUITETOXERO_LICENSE_TITLE','LBL_SUITETOXERO_LICENSE','./index.php?module=bfs_SuitetoXero&action=license');
} else {
    $admin_option_defs['Administration']['suitetoxerolicenseaddon_info']= array('helpInline','LBL_SUITETOXERO_LICENSE_TITLE','LBL_SUITETOXERO_LICENSE','javascript:parent.SUGAR.App.router.navigate("#bwc/index.php?module=bfs_SuitetoXero&action=license", {trigger: true});');
}

$admin_group_header[]= array('LBL_SUITETOXEROADDON','',false,$admin_option_defs, '');
