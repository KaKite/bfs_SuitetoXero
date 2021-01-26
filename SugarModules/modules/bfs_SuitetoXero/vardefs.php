<?php

/**
 *
 * SugarCRM Community Edition is a customer relationship management program developed by
 * SugarCRM, Inc. Copyright (C) 2004-2013 SugarCRM Inc.
 *
 * SuiteCRM is an extension to SugarCRM Community Edition developed by SalesAgility Ltd.
 * Copyright (C) 2011 - 2017 SalesAgility Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 *
 * You can contact SugarCRM, Inc. headquarters at 10050 North Wolfe Road,
 * SW2-130, Cupertino, CA 95014, USA. or at email address contact@sugarcrm.com.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * SugarCRM" logo and "Supercharged by SuiteCRM" logo. If the display of the logos is not
 * reasonably feasible for  technical reasons, the Appropriate Legal Notices must
 * display the words  "Powered by SugarCRM" and "Supercharged by SuiteCRM".
 */

$dictionary['bfs_SuitetoXero'] = array(
  'table' => 'bfs_SuitetoXero',
  'audited' => true,
  'inline_edit' => true,
  'duplicate_merge' => true,
  'fields' => array(
    'consumer_key' =>
    array(
      'required' => true,
      'name' => 'consumer_key',
      'vname' => 'LBL_CONSUMER_KEY',
      'type' => 'varchar',
      'massupdate' => 0,
      'no_default' => false,
      'comments' => '',
      'help' => 'Enter the Client ID generated in Xero',
      'importable' => 'true',
      'duplicate_merge' => 'disabled',
      'duplicate_merge_dom_value' => '0',
      'audited' => false,
      'inline_edit' => true,
      'reportable' => true,
      'unified_search' => false,
      'merge_filter' => 'disabled',
      'len' => '255',
      'size' => '20',
    ),
    'consumer_secret' =>
    array(
      'required' => true,
      'name' => 'consumer_secret',
      'vname' => 'LBL_CONSUMER_SECRET',
      'type' => 'varchar',
      'massupdate' => 0,
      'no_default' => false,
      'comments' => '',
      'help' => 'Enter the Client Secret generated in Xero',
      'importable' => 'true',
      'duplicate_merge' => 'disabled',
      'duplicate_merge_dom_value' => '0',
      'audited' => false,
      'inline_edit' => true,
      'reportable' => true,
      'unified_search' => false,
      'merge_filter' => 'disabled',
      'len' => '255',
      'size' => '20',
    ),
    'pem_file_name' =>
    array(
      'required' => true,
      'name' => 'pem_file_name',
      'vname' => 'LBL_PEM_FILE_NAME',
      'type' => 'varchar',
      'massupdate' => 0,
      'no_default' => false,
      'comments' => '',
      'help' => 'Enter the name of your PEM file generated with OpenSSL',
      'importable' => 'true',
      'duplicate_merge' => 'disabled',
      'duplicate_merge_dom_value' => '0',
      'audited' => false,
      'inline_edit' => true,
      'reportable' => true,
      'unified_search' => false,
      'merge_filter' => 'disabled',
      'len' => '255',
      'size' => '20',
      'default' => 'privatekey.pem',
    ),
    'cer_file_name' =>
    array(
      'required' => true,
      'name' => 'cer_file_name',
      'vname' => 'LBL_CER_FILE_NAME',
      'type' => 'varchar',
      'massupdate' => 0,
      'no_default' => false,
      'comments' => '',
      'help' => 'Enter the name of your CER file generated with OpenSSL',
      'importable' => 'true',
      'duplicate_merge' => 'disabled',
      'duplicate_merge_dom_value' => '0',
      'audited' => false,
      'inline_edit' => true,
      'reportable' => true,
      'unified_search' => false,
      'merge_filter' => 'disabled',
      'len' => '255',
      'size' => '20',
      'default' => 'publickey.cer',
    ),
    'redirect_url' =>
    array(
      'required' => true,
      'name' => 'redirect_url',
      'vname' => 'LBL_REDIRECT_URL',
      'type' => 'varchar',
      'massupdate' => 0,
      'default' => 'https://siteurl/index.php?entryPoint=XeroCallBack',
      'no_default' => false,
      'comments' => '',
      'help' => 'Enter the redirect url of your Xero App',
      'importable' => 'true',
      'duplicate_merge' => 'disabled',
      'duplicate_merge_dom_value' => '0',
      'audited' => false,
      'inline_edit' => true,
      'reportable' => true,
      'unified_search' => false,
      'merge_filter' => 'disabled',
      'len' => '255',
      'size' => '20',
      'default' => '',
    ),
    'refresh_token' =>
    array(
      'required' => false,
      'name' => 'refresh_token',
      'vname' => 'LBL_REFRESH_TOKEN',
      'type' => 'varchar',
      'massupdate' => 0,
      'no_default' => false,
      'comments' => '',
      'help' => '',
      'importable' => 'true',
      'duplicate_merge' => 'disabled',
      'duplicate_merge_dom_value' => '0',
      'audited' => false,
      'inline_edit' => true,
      'reportable' => true,
      'unified_search' => false,
      'merge_filter' => 'disabled',
      'len' => '255',
      'size' => '20',
      'default' => '',
    ),
    'tenant_id' =>
    array(
      'required' => false,
      'name' => 'tenant_id',
      'vname' => 'LBL_TENANT_ID',
      'type' => 'varchar',
      'massupdate' => 0,
      'no_default' => false,
      'comments' => '',
      'help' => '',
      'importable' => 'true',
      'duplicate_merge' => 'disabled',
      'duplicate_merge_dom_value' => '0',
      'audited' => false,
      'inline_edit' => true,
      'reportable' => true,
      'unified_search' => false,
      'merge_filter' => 'disabled',
      'len' => '255',
      'size' => '20',
      'default' => '',
    ),
    'name' =>
    array(
      'name' => 'name',
      'vname' => 'LBL_NAME',
      'type' => 'name',
      'link' => true,
      'dbType' => 'varchar',
      'len' => '255',
      'unified_search' => false,
      'full_text_search' =>
      array(
        'boost' => 3,
      ),
      'required' => true,
      'importable' => 'required',
      'duplicate_merge' => 'disabled',
      'merge_filter' => 'disabled',
      'massupdate' => 0,
      'default' => 'suite-xero',
      'no_default' => false,
      'comments' => '',
      'help' => 'Enter the application name generated in Xero',
      'duplicate_merge_dom_value' => '0',
      'audited' => false,
      'inline_edit' => true,
      'reportable' => true,
      'size' => '20',
    ),
    // new Xero config fields
    'synch_with_xero' => array(
      'name' => 'synch_with_xero',
      'vname' => 'LBL_SYNCH_WITH_XERO',
      'type' => 'enum',
      // 'ext1' => 'synch_with_xero_list',
      'options' => 'synch_with_xero_list',
      'default_value' => '',
      // 'mass_update' => true,
      'massupdate' => '1',
      'ext1' => true,
      'require_option' => 'optional',
      'reportable' => true,
      'audited' => false,
      'importable' => 'true',
      'duplicate_merge' => 'disabled',
      'inline_edit' => 'true',
    ),
    'which_records' => array(
      'name' => 'which_records',
      'vname' => 'LBL_WHICH_RECORDS',
      'type' => 'enum',
      // 'ext1' => 'which_records_list',
      'options' => 'which_records_list',
      'default_value' => '',
      // 'mass_update' => true,
      'massupdate' => '1',
      'require_option' => 'optional',
      'reportable' => true,
      'audited' => false,
      'importable' => 'true',
      'duplicate_merge' => false,
      'inline_edit' => 'true',
    ),
    'update_records_from' => array(
      'name' => 'update_records_from',
      'vname' => 'LBL_UPDATE_RECORDS_FROM',
      'type' => 'date',
      'display_default' => '-1 day',
      'audited' => false,
    ),
    'synch_contacts' => array(
      'name' => 'synch_contacts',
      'vname' => 'LBL_SYNCH_CONTACTS',
      'type' => 'bool',
      'default' => '0',
      'reportable' => false,
      'comment' => '',
    ),
    'synch_accounts' => array(
      'name' => 'synch_accounts',
      'vname' => 'LBL_SYNCH_ACCOUNTS',
      'type' => 'bool',
      'default' => '0',
      'reportable' => false,
      'comment' => '',
    ),
    'synch_invoices' => array(
      'name' => 'synch_invoices',
      'vname' => 'LBL_SYNCH_INVOICES',
      'type' => 'bool',
      'default' => '0',
      'reportable' => false,
      'comment' => '',
    ),
    'create_contacts' => array(
      'name' => 'create_contacts',
      'vname' => 'LBL_CREATE_CONTACTS',
      'type' => 'bool',
      'default' => '0',
      'reportable' => false,
      'comment' => '',
    ),
    'create_accounts' => array(
      'name' => 'create_accounts',
      'vname' => 'LBL_CREATE_ACCOUNTS',
      'type' => 'bool',
      'default' => '0',
      'reportable' => false,
      'comment' => '',
    ),
    'create_invoices' => array(
      'name' => 'create_invoices',
      'vname' => 'LBL_CREATE_INVOICES',
      'type' => 'bool',
      'default' => '0',
      'reportable' => false,
      'comment' => '',
    ),
    'delete_contacts' => array(
      'name' => 'delete_contacts',
      'vname' => 'LBL_DELETE_CONTACTS',
      'type' => 'bool',
      'default' => '0',
      'reportable' => false,
      'comment' => '',
    ),
    'delete_accounts' => array(
      'name' => 'delete_accounts',
      'vname' => 'LBL_DELETE_ACCOUNTS',
      'type' => 'bool',
      'default' => '0',
      'reportable' => false,
      'comment' => '',
    ),
    'delete_invoices' => array(
      'name' => 'delete_invoices',
      'vname' => 'LBL_DELETE_INVOICES',
      'type' => 'bool',
      'default' => '0',
      'reportable' => false,
      'comment' => '',
    ),
    'webhook_key' =>
    array(
      'required' => true,
      'name' => 'webhook_key',
      'vname' => 'LBL_WEBHOOK_KEY',
      'type' => 'varchar',
      'massupdate' => 0,
      'no_default' => false,
      'comments' => '',
      'help' => 'Enter the webhook key generated in Xero',
      'importable' => 'true',
      'duplicate_merge' => 'disabled',
      'duplicate_merge_dom_value' => '0',
      'audited' => false,
      'inline_edit' => true,
      'reportable' => true,
      'unified_search' => false,
      'merge_filter' => 'disabled',
      'len' => '255',
      'size' => '20',
    ),
    'xero_last_sequence' => array(
      'name' => 'xero_last_sequence',
      'vname' => 'LBL_XERO_LAST_SEQUENCE',
      'type' => 'int',
      'default' => '0',
      'reportable' => false,
      'comment' => '',
      'len' => '11',
    ),
  ),
  'relationships' => array(),
  'optimistic_locking' => true,
  'unified_search' => true,
);
if (!class_exists('VardefManager')) {
  require_once('include/SugarObjects/VardefManager.php');
}
VardefManager::createVardef('bfs_SuitetoXero', 'bfs_SuitetoXero', array('basic', 'assignable', 'security_groups'));
