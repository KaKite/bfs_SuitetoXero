<?php
$popupMeta = array (
    'moduleMain' => 'bfs_SuitetoXero',
    'varName' => 'bfs_SuitetoXero',
    'orderBy' => 'bfs_SuitetoXero.name',
    'whereClauses' => array (
  'consumer_secret' => 'bfs_SuitetoXero.consumer_secret',
  'consumer_key' => 'bfs_SuitetoXero.consumer_key',
),
    'searchInputs' => array (
  4 => 'consumer_secret',
  5 => 'consumer_key',
),
    'searchdefs' => array (
  'consumer_secret' => 
  array (
    'type' => 'varchar',
    'label' => 'LBL_CONSUMER_SECRET',
    'width' => '10%',
    'name' => 'consumer_secret',
  ),
  'consumer_key' => 
  array (
    'type' => 'varchar',
    'label' => 'LBL_CONSUMER_KEY',
    'width' => '10%',
    'name' => 'consumer_key',
  ),
),
    'listviewdefs' => array (
  'NAME' => 
  array (
    'type' => 'name',
    'link' => true,
    'default' => true,
    'label' => 'LBL_NAME',
    'width' => '10%',
  ),
  'DATE_ENTERED' => 
  array (
    'type' => 'datetime',
    'label' => 'LBL_DATE_ENTERED',
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
  'CONSUMER_KEY' => 
  array (
    'type' => 'varchar',
    'label' => 'LBL_CONSUMER_KEY',
    'width' => '10%',
    'default' => true,
  ),
),
);
