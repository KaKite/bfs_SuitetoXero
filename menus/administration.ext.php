<?php
//**********************************************************************************
//   Xero module Admin Menu File
//**********************************************************************************
$admin_option_defs=array();
$admin_option_defs['Xero']['<section key>']= array('Administration','LBL_XERO CONFIGURATION','LBL_XERO_RELEASE','./index.php?module=bfs_SuitetoXero&action=index');
$admin_group_header[]= array('LBL_XERO_TITLE','',false,$admin_option_defs, 'LBL_XERO_DESC');