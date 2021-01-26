<?php
$module_name = 'bfs_SuitetoXero';
$listViewDefs [$module_name] = 
array (
  'NAME' => 
  array (
    'width' => '32%',
    'label' => 'LBL_NAME',
    'default' => true,
    'link' => true,
  ),
  'CONSUMER_KEY' => 
  array (
    'type' => 'varchar',
    'label' => 'LBL_CONSUMER_KEY',
    'width' => '10%',
    'default' => true,
  ),
  'CONSUMER_SECRET' => 
  array (
    'type' => 'varchar',
    'label' => 'LBL_CONSUMER_SECRET',
    'width' => '10%',
    'default' => true,
  ),
  'PEM_FILE_NAME' => 
  array (
    'type' => 'varchar',
    'default' => false,
    'label' => 'LBL_PEM_FILE_NAME',
    'width' => '10%',
  ),
  'CER_FILE_NAME' => 
  array (
    'type' => 'varchar',
    'label' => 'LBL_CER_FILE_NAME',
    'width' => '10%',
    'default' => false,
  ),
  'ASSIGNED_USER_NAME' => 
  array (
    'width' => '9%',
    'label' => 'LBL_ASSIGNED_TO_NAME',
    'module' => 'Employees',
    'id' => 'ASSIGNED_USER_ID',
    'default' => false,
  ),
);
?>
