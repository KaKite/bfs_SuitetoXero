<?php
$module_name = 'bfs_SuitetoXero';
$viewdefs [$module_name] = 
array (
  'QuickCreate' => 
  array (
    'templateMeta' => 
    array (
      'maxColumns' => '2',
      'widths' => 
      array (
        0 => 
        array (
          'label' => '10',
          'field' => '30',
        ),
        1 => 
        array (
          'label' => '10',
          'field' => '30',
        ),
      ),
      'useTabs' => false,
      'tabDefs' => 
      array (
        'DEFAULT' => 
        array (
          'newTab' => false,
          'panelDefault' => 'expanded',
        ),
      ),
    ),
    'panels' => 
    array (
      'default' => 
      array (
        0 => 
        array (
          0 => 'name',
        ),
        1 => 
        array (
          0 => 
          array (
            'name' => 'consumer_key',
            'label' => 'LBL_CONSUMER_KEY',
          ),
          1 => 
          array (
            'name' => 'consumer_secret',
            'label' => 'LBL_CONSUMER_SECRET',
          ),
        ),
        2 => 
        array (
          0 => 
          array (
            'name' => 'cer_file_name',
            'label' => 'LBL_CER_FILE_NAME',
          ),
          1 => 
          array (
            'name' => 'pem_file_name',
            'label' => 'LBL_PEM_FILE_NAME',
          ),
        ),
      ),
    ),
  ),
);
?>
