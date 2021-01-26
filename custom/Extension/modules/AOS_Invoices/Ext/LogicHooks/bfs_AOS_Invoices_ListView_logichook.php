<?php
$hook_version = 1; 
$hook_array = Array(); 
// position, file, function 
$hook_array['after_ui_frame'] = Array(); 
$hook_array['after_ui_frame'][] = Array(3003, 'Add Buttons to AOS_Invoices List view', 'modules/bfs_SuitetoXero/library/menuitems/InvoiceButtons_ListView.php','InvoiceButtonsList', 'add');