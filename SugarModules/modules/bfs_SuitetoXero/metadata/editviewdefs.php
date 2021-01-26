<?php
$module_name = 'bfs_SuitetoXero';
$viewdefs [$module_name] = 
array (
  'EditView' => 
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
        'LBL_EDITVIEW_PANEL1' => 
        array (
          'newTab' => false,
          'panelDefault' => 'expanded',
        ),
      ),
      'syncDetailEditViews' => true,
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
            'name' => 'redirect_url',
            'label' => 'LBL_REDIRECT_URL',
          ),
        ),
        3 => 
        array (
          0 => 
          array (
            'name' => 'webhook_key',
            'label' => 'LBL_WEBHOOK_KEY',
          ),
        ),
      ),
      'lbl_editview_panel1' => 
      array (
        0 => 
        array (
          0 => 
          array (
            'name' => 'synch_with_xero',
			'ext1' => 'synch_with_xero_list',
            'studio' => 'visible',
            'label' => 'LBL_SYNCH_WITH_XERO',
          ),
        ),
        1 => 
        array (
          0 => 
          array (
            'name' => 'which_records',
			'ext1' => 'which_records_list',
            'studio' => 'visible',
            'label' => 'LBL_WHICH_RECORDS',
          ),
          1 => 
          array (
            'name' => 'update_records_from',
            'label' => 'LBL_UPDATE_RECORDS_FROM',
          ),
        ),
        2 => 
        array (
          0 => 
          array (
			'name' => 'synch_accounts',
            'label' => 'LBL_SYNCH_ACCOUNTS',
          ),
          1 => 
          array (
            'name' => 'synch_contacts',
            'label' => 'LBL_SYNCH_CONTACTS',
          ),
        ),
        3 => 
        array (
          0 => 
          array (
			'name' => 'create_accounts',
            'label' => 'LBL_CREATE_ACCOUNTS',
          ),
          1 => 
          array (
            'name' => 'create_contacts',
            'label' => 'LBL_CREATE_CONTACTS',
          ),
        ),
        4 => 
        array (
          0 => 
          array (
			'name' => 'delete_accounts',
            'label' => 'LBL_DELETE_ACCOUNTS',
          ),
          1 => 
          array (
            'name' => 'delete_contacts',
            'label' => 'LBL_DELETE_CONTACTS',
          ),
        ),
        5 => 
        array (
          0 => 
          array (
            'name' => 'synch_invoices',
            'label' => 'LBL_SYNCH_INVOICES',
          ),
          1 => '',
        ),
        6 => 
        array (
          0 => 
          array (
            'name' => 'create_invoices',
            'label' => 'LBL_CREATE_INVOICES',
          ),
          1 => '',
        ),
		7 => 
        array (
          0 => 
          array (
            'name' => 'delete_invoices',
            'label' => 'LBL_DELETE_INVOICES',
          ),
          1 => '',
        ),
      ),
    ),
  ),
);
;
