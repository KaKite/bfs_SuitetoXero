<?php

/**
 * Created by Business Fundamentals - Copyright 2017
 * Website: www.business-fundamentals.biz
 * Mail: support@business-fundamentals.biz
 * Created with Taction Software
 * Website: www.tactionsoftware.com/
 * File manifest.php
 *
 * Includes the SuiteCRM Store licensing files
 * Major update: 29/12/2020
 */

$manifest = array(
	0 =>
	array(
		'acceptable_sugar_versions' =>
		array(),
	),
	1 =>
	array(
		'acceptable_sugar_flavors' =>
		array(
			0 => 'CE',
			1 => 'PRO',
			2 => 'ENT',
		),
	),
	'readme' => 'This module stores the data required to connect your SuiteCRM installation to Xero. For further instructions, please see the Readme.md file',
	'key' => 'bfs_',
	'author' => 'Business Fundamentals',
	'description' => 'Module to store the Xero configuration/connection settings',
	'icon' => '',
	'is_uninstallable' => true,
	'name' => 'bfs_SuitetoXero',
	'published_date' => '2020-12-29 22:59:04',
	'type' => 'module',
	'version' => 1508194555,
	'remove_tables' => 'prompt',
);


$installdefs = array(
	'id' => 'bfs_SuitetoXero',
	'beans' =>
	array(
		0 =>
		array(
			'module' => 'bfs_SuitetoXero',
			'class' => 'bfs_SuitetoXero',
			'path' => 'modules/bfs_SuitetoXero/bfs_SuitetoXero.php',
			'tab' => true,
		),
	),
	'layoutdefs' =>
	array(),
	'relationships' =>
	array(),
	'image_dir' => '<basepath>/icons',
	'copy' => array(
		array(
			'from' => '<basepath>/SugarModules/modules/bfs_SuitetoXero',
			'to' => 'modules/bfs_SuitetoXero',
		),
		array(
			'from' => '<basepath>/license',
			'to' => 'modules/bfs_SuitetoXero',
		),
		array(
			'from' => '<basepath>/custom',
			'to' => 'custom',
		),
	),

	'language' => array(
		array(
			'from' => '<basepath>/SugarModules/language/application/en_us.lang.php',
			'to_module' => 'application',
			'language' => 'en_us',
		),
		array(
			'from' => '<basepath>/language/Administration/mod_strings_en_us.php',
			'to_module' => 'Administration',
			'language' => 'en_us'
		),
		array(
			'from' => '<basepath>/license_admin/language/en_us.SuitetoXeroLicenseAddon.php',
			'to_module' => 'Administration',
			'language' => 'en_us'
		),
	),
	'administration' => array(
		array(
			'from' => '<basepath>/menus/administration.ext.php',
		),
		array(
			'from' => '<basepath>/license_admin/menu/SuitetoXeroLicenseAddon_admin.php',
			'to' => 'modules/Administration/SuitetoXeroLicenseAddon_admin.php',
		),
	),
	'custom_fields' => array(
		array(
			'name' => 'xero_id_c',
			'label' => 'LBL_XERO_ID',
			'type' => 'varchar',
			'max_size' => 255,
			'module' => 'Contacts',
			'require_option' => 'optional',
			'default_value' => '',
			'ext1' => '',
			'ext2' => '',
			'ext3' => '',
			'audited' => false,
			'mass_update' => true,
			'duplicate_merge' => false,
			'reportable' => true,
			'importable' => 'true',
			'inline_edit' => 'true',
		),
		array(
			'name' => 'xero_link_c',
			'label' => 'LBL_XERO_LINK',
			'type' => 'varchar',
			'max_size' => 255,
			'module' => 'Contacts',
			'require_option' => 'optional',
			'required' => false,
			'default_value' => '',
			'ext1' => '',
			'ext2' => '',
			'ext3' => '',
			'audited' => false,
			'mass_update' => true,
			'duplicate_merge' => false,
			'reportable' => true,
			'importable' => 'true',
			'inline_edit' => 'true',
		),
		array(
			'name' => 'xero_id_c',
			'label' => 'LBL_XERO_ID',
			'type' => 'varchar',
			'max_size' => 255,
			'module' => 'Accounts',
			'require_option' => 'optional',
			'default_value' => '',
			'ext1' => '',
			'ext2' => '',
			'ext3' => '',
			'audited' => false,
			'mass_update' => true,
			'duplicate_merge' => false,
			'reportable' => true,
			'importable' => 'true',
			'inline_edit' => 'true',
		),
		array(
			'name' => 'xero_link_c',
			'label' => 'LBL_XERO_LINK',
			'type' => 'varchar',
			'max_size' => 255,
			'module' => 'Accounts',
			'require_option' => 'optional',
			'required' => false,
			'default_value' => '',
			'ext1' => '',
			'ext2' => '',
			'ext3' => '',
			'audited' => false,
			'mass_update' => true,
			'duplicate_merge' => false,
			'reportable' => true,
			'importable' => 'true',
			'inline_edit' => 'true',
		),
		array(
			'name' => 'xero_id_c',
			'label' => 'LBL_XERO_ID',
			'type' => 'varchar',
			'max_size' => 255,
			'module' => 'AOS_Quotes',
			'require_option' => 'optional',
			'default_value' => '',
			'ext1' => '',
			'ext2' => '',
			'ext3' => '',
			'audited' => false,
			'mass_update' => true,
			'duplicate_merge' => false,
			'reportable' => true,
			'importable' => 'true',
			'inline_edit' => 'true',
		),
		array(
			'name' => 'xero_link_c',
			'label' => 'LBL_XERO_LINK',
			'type' => 'varchar',
			'max_size' => 255,
			'module' => 'AOS_Quotes',
			'require_option' => 'optional',
			'required' => false,
			'default_value' => '',
			'ext1' => '',
			'ext2' => '',
			'ext3' => '',
			'audited' => false,
			'mass_update' => true,
			'duplicate_merge' => false,
			'reportable' => true,
			'importable' => 'true',
			'inline_edit' => 'true',
		),
		array(
			'name' => 'xeroutc_c',
			'label' => 'LBL_XEROUTC',
			'type' => 'varchar',
			'max_size' => 255,
			'module' => 'AOS_Invoices',
			'require_option' => 'optional',
			'default_value' => '',
			'ext1' => '',
			'ext2' => '',
			'ext3' => '',
			'audited' => false,
			'mass_update' => true,
			'duplicate_merge' => false,
			'reportable' => true,
			'importable' => 'true',
			'inline_edit' => 'true',
		),
		array(
			'name' => 'xero_id_c',
			'label' => 'LBL_XERO_ID',
			'type' => 'varchar',
			'max_size' => 255,
			'module' => 'AOS_Invoices',
			'require_option' => 'optional',
			'default_value' => '',
			'ext1' => '',
			'ext2' => '',
			'ext3' => '',
			'audited' => false,
			'mass_update' => true,
			'duplicate_merge' => false,
			'reportable' => true,
			'importable' => 'true',
			'inline_edit' => 'true',
		),
		array(
			'name' => 'xero_link_c',
			'label' => 'LBL_XERO_LINK',
			'type' => 'varchar',
			'max_size' => 255,
			'module' => 'AOS_Invoices',
			'require_option' => 'optional',
			'required' => false,
			'default_value' => '',
			'ext1' => '',
			'ext2' => '',
			'ext3' => '',
			'audited' => false,
			'mass_update' => true,
			'duplicate_merge' => false,
			'reportable' => true,
			'importable' => 'true',
			'inline_edit' => 'true',
		),
		array(
			'name' => 'type_c',
			'label' => 'LBL_TYPE',
			'type' => 'enum',
			'module' => 'AOS_Invoices',
			'help' => '',
			'comment' => '',
			'ext1' => 'type_list',
			'default_value' => 'ACCREC',
			'mass_update' => true,
			'require_option' => 'required',
			'required' => true,
			'reportable' => true,
			'audited' => false,
			'importable' => 'true',
			'duplicate_merge' => false,
			'inline_edit' => 'true',
		),
		array(
			'name' => 'xero_expense_codes_c',
			'label' => 'LBL_XERO_EXPENSE_CODES',
			'type' => 'dynamicenum',
			'module' => 'AOS_Invoices',
			'help' => 'Update this list as required',
			'comment' => '',
			'ext1' => 'xero_expense_codes_list',
			'parentenum' => 'type_c',
			'default_value' => '200',
			'mass_update' => true,
			'require_option' => 'required',
			'required' => true,
			'reportable' => true,
			'audited' => false,
			'importable' => 'true',
			'duplicate_merge' => false,
			'inline_edit' => 'true',
		),
		array(
			'name' => 'xero_synch_c',
			'require_option' => 'optional',
			'vname' => 'LBL_XERO_SYNCH',
			'module' => 'Contacts',
			'type' => 'enum',
			'massupdate' => '1',
			'default' => '0',
			'reportable' => false,
			'comment' => '',
			'audited' => false,
			'inline_edit' => true,
			'merge_filter' => 'disabled',
			'len' => 100,
			'size' => '20',
			'ext1' => 'xero_checkbox_dom',
			'studio' => 'visible',
			'dependency' => false,
		),
		array(
			'name' => 'xero_synch_c',
			'require_option' => 'optional',
			'vname' => 'LBL_XERO_SYNCH',
			'module' => 'Accounts',
			'type' => 'enum',
			'massupdate' => '1',
			'default' => '0',
			'reportable' => false,
			'comment' => '',
			'audited' => false,
			'inline_edit' => true,
			'merge_filter' => 'disabled',
			'len' => 100,
			'size' => '20',
			'ext1' => 'xero_checkbox_dom',
			'studio' => 'visible',
			'dependency' => false,
		),
		array(
			'name' => 'xero_synch_c',
			'require_option' => 'optional',
			'vname' => 'LBL_XERO_SYNCH',
			'module' => 'AOS_Invoices',
			'type' => 'enum',
			'massupdate' => '1',
			'default' => '0',
			'reportable' => false,
			'comment' => '',
			'audited' => false,
			'inline_edit' => true,
			'merge_filter' => 'disabled',
			'len' => 100,
			'size' => '20',
			'ext1' => 'xero_checkbox_dom',
			'studio' => 'visible',
			'dependency' => false,
		),
		array(
			'name' => 'dtime_synched_c',
			'vname' => 'LBL_DTIME_SYNCHED',
			'module' => 'Contacts',
			'type' => 'datetime',
			'display_default' => '-1 day',
			'reportable' => false,
			'comment' => '',
		),
		array(
			'name' => 'dtime_synched_c',
			'vname' => 'LBL_DTIME_SYNCHED',
			'module' => 'Accounts',
			'type' => 'datetime',
			'display_default' => '-1 day',
			'reportable' => false,
			'comment' => '',
		),
		array(
			'name' => 'dtime_synched_c',
			'vname' => 'LBL_DTIME_SYNCHED',
			'module' => 'AOS_Invoices',
			'type' => 'datetime',
			'display_default' => '-1 day',
			'reportable' => false,
			'comment' => '',
		),
		array(
			'name' => 'deleted_synched_c',
			'vname' => 'LBL_DELETED_SYNCHED_C',
			'module' => 'Contacts',
			'type' => 'bool',
			'default' => '0',
			'reportable' => false,
			'comment' => '',
		),
		array(
			'name' => 'xero_primary_contact_c',
			'vname' => 'LBL_XEROXPP_C',
			'module' => 'Contacts',
			'type' => 'bool',
			'default' => '0',
			'reportable' => false,
			'comment' => '',
		),
		array(
			'name' => 'deleted_synched_c',
			'vname' => 'LBL_DELETED_SYNCHED_C',
			'module' => 'Accounts',
			'type' => 'bool',
			'default' => '0',
			'reportable' => false,
			'comment' => '',
		),
		array(
			'name' => 'deleted_synched_c',
			'vname' => 'LBL_DELETED_SYNCHED_C',
			'module' => 'AOS_Invoices',
			'type' => 'bool',
			'default' => '0',
			'reportable' => false,
			'comment' => '',
		),
	),

	'action_view_map' =>
	array(
		array(
			'from' => '<basepath>/license_admin/actionviewmap/SuitetoXeroLicenseAddon_actionviewmap.php',
			'to_module' => 'bfs_SuitetoXero',
		),
	),
);
