<?php
$viewdefs['Contacts'] =
  array(
    'DetailView' =>
    array(
      'templateMeta' =>
      array(
        'form' =>
        array(
          'buttons' =>
          array(
            0 => 'EDIT',
            1 => 'DUPLICATE',
            2 => 'DELETE',
            3 => 'FIND_DUPLICATES',
            4 =>
            array(
              'customCode' => '<input type="submit" class="button" title="{$APP.LBL_MANAGE_SUBSCRIPTIONS}" onclick="this.form.return_module.value=\'Contacts\'; this.form.return_action.value=\'DetailView\'; this.form.return_id.value=\'{$fields.id.value}\'; this.form.action.value=\'Subscriptions\'; this.form.module.value=\'Campaigns\'; this.form.module_tab.value=\'Contacts\';" name="Manage Subscriptions" value="{$APP.LBL_MANAGE_SUBSCRIPTIONS}"/>',
              'sugar_html' =>
              array(
                'type' => 'submit',
                'value' => '{$APP.LBL_MANAGE_SUBSCRIPTIONS}',
                'htmlOptions' =>
                array(
                  'class' => 'button',
                  'id' => 'manage_subscriptions_button',
                  'title' => '{$APP.LBL_MANAGE_SUBSCRIPTIONS}',
                  'onclick' => 'this.form.return_module.value=\'Contacts\'; this.form.return_action.value=\'DetailView\'; this.form.return_id.value=\'{$fields.id.value}\'; this.form.action.value=\'Subscriptions\'; this.form.module.value=\'Campaigns\'; this.form.module_tab.value=\'Contacts\';',
                  'name' => 'Manage Subscriptions',
                ),
              ),
            ),
            /************ custom xero button code, cut and paste as required *************/
            'SEND_TO_XERO' =>
            array(
              'customCode' => '{if $fields.xero_id_c.value == "" }<input type="button" class="button" id="SendtoXero" onClick="window.location=\'index.php?entryPoint=xeroLink&func=sendContToXero&module=Contacts&contactID={$fields.id.value}\'" value="Send to Xero">{/if}',
            ),
            'UPDATE_XERO' =>
            array(
              'customCode' => '{if $fields.xero_id_c.value != "" }{if $fields.account_name.value == "" }<input type="button" class="button" id="UpdateXero" onClick="window.location=\'index.php?entryPoint=xeroLink&func=updateContToXero&module=Contacts&contactID={$fields.id.value}&xeroID={$fields.xero_id_c.value}\'" value="Update Xero">{/if}{/if}',
            ),
            'UPDATE_FROM_XERO' =>
            array(
              'customCode' => '{if $fields.xero_id_c.value != "" }{if $fields.account_name.value == "" }<input type="button" class="button" id="UpdateFromXero" onClick="window.location=\'index.php?entryPoint=xeroLink&func=updateContFromXero&module=Contacts&contactID={$fields.id.value}&xeroID={$fields.xero_id_c.value}\'" value="Update FROM Xero">{/if}{/if}',
            ),
            'GET_XERO_INVOICES' =>
            array(
              'customCode' => '{if $fields.xero_id_c.value != "" }{if $fields.account_name.value == "" }<input type="button" class="button" id="GetXeroInvoices" onClick="window.location=\'index.php?entryPoint=xeroLink&func=getInvFromXero&module=Contacts&contactID={$fields.id.value}&xeroID={$fields.xero_id_c.value}\'" value="Get Xero Invoices">{/if}{/if}',
            ),
            /*********** end of custom xero code *************/
            'AOS_GENLET' =>
            array(
              'customCode' => '<input type="button" class="button" onClick="showPopup();" value="{$APP.LBL_PRINT_AS_PDF}">',
            ),
            'AOP_CREATE' =>
            array(
              'customCode' => '{if !$fields.joomla_account_id.value && $AOP_PORTAL_ENABLED}<input type="submit" class="button" onClick="this.form.action.value=\'createPortalUser\';" value="{$MOD.LBL_CREATE_PORTAL_USER}"> {/if}',
              'sugar_html' =>
              array(
                'type' => 'submit',
                'value' => '{$MOD.LBL_CREATE_PORTAL_USER}',
                'htmlOptions' =>
                array(
                  'title' => '{$MOD.LBL_CREATE_PORTAL_USER}',
                  'class' => 'button',
                  'onclick' => 'this.form.action.value=\'createPortalUser\';',
                  'name' => 'buttonCreatePortalUser',
                  'id' => 'createPortalUser_button',
                ),
                'template' => '{if !$fields.joomla_account_id.value && $AOP_PORTAL_ENABLED}[CONTENT]{/if}',
              ),
            ),
            'AOP_DISABLE' =>
            array(
              'customCode' => '{if $fields.joomla_account_id.value && !$fields.portal_account_disabled.value && $AOP_PORTAL_ENABLED}<input type="submit" class="button" onClick="this.form.action.value=\'disablePortalUser\';" value="{$MOD.LBL_DISABLE_PORTAL_USER}"> {/if}',
              'sugar_html' =>
              array(
                'type' => 'submit',
                'value' => '{$MOD.LBL_DISABLE_PORTAL_USER}',
                'htmlOptions' =>
                array(
                  'title' => '{$MOD.LBL_DISABLE_PORTAL_USER}',
                  'class' => 'button',
                  'onclick' => 'this.form.action.value=\'disablePortalUser\';',
                  'name' => 'buttonDisablePortalUser',
                  'id' => 'disablePortalUser_button',
                ),
                'template' => '{if $fields.joomla_account_id.value && !$fields.portal_account_disabled.value && $AOP_PORTAL_ENABLED}[CONTENT]{/if}',
              ),
            ),
            'AOP_ENABLE' =>
            array(
              'customCode' => '{if $fields.joomla_account_id.value && $fields.portal_account_disabled.value && $AOP_PORTAL_ENABLED}<input type="submit" class="button" onClick="this.form.action.value=\'enablePortalUser\';" value="{$MOD.LBL_ENABLE_PORTAL_USER}"> {/if}',
              'sugar_html' =>
              array(
                'type' => 'submit',
                'value' => '{$MOD.LBL_ENABLE_PORTAL_USER}',
                'htmlOptions' =>
                array(
                  'title' => '{$MOD.LBL_ENABLE_PORTAL_USER}',
                  'class' => 'button',
                  'onclick' => 'this.form.action.value=\'enablePortalUser\';',
                  'name' => 'buttonENablePortalUser',
                  'id' => 'enablePortalUser_button',
                ),
                'template' => '{if $fields.joomla_account_id.value && $fields.portal_account_disabled.value && $AOP_PORTAL_ENABLED}[CONTENT]{/if}',
              ),
            ),
          ),
        ),
        'maxColumns' => '2',
        'widths' =>
        array(
          0 =>
          array(
            'label' => '10',
            'field' => '30',
          ),
          1 =>
          array(
            'label' => '10',
            'field' => '30',
          ),
        ),

        'includes' =>
        array(
          0 =>
          array(
            'file' => 'modules/Leads/Lead.js',
          ),
        ),
        'useTabs' => true,
        'tabDefs' =>
        array(
          'LBL_CONTACT_INFORMATION' =>
          array(
            'newTab' => true,
            'panelDefault' => 'expanded',
          ),
          'LBL_PANEL_ADVANCED' =>
          array(
            'newTab' => true,
            'panelDefault' => 'expanded',
          ),
          'LBL_PANEL_ASSIGNMENT' =>
          array(
            'newTab' => true,
            'panelDefault' => 'expanded',
          ),
        ),
      ),
      'panels' =>
      array(
        'lbl_contact_information' =>
        array(
          0 =>
          array(
            0 =>
            array(
              'name' => 'first_name',
              'comment' => 'First name of the contact',
              'label' => 'LBL_FIRST_NAME',
            ),
            1 =>
            array(
              'name' => 'last_name',
              'comment' => 'Last name of the contact',
              'label' => 'LBL_LAST_NAME',
            ),
          ),
          1 =>
          array(
            0 =>
            array(
              'name' => 'phone_work',
              'label' => 'LBL_OFFICE_PHONE',
            ),
            1 =>
            array(
              'name' => 'phone_mobile',
              'label' => 'LBL_MOBILE_PHONE',
            ),
          ),
          2 =>
          array(
            0 =>
            array(
              'name' => 'title',
              'comment' => 'The title of the contact',
              'label' => 'LBL_TITLE',
            ),
            1 =>
            array(
              'name' => 'department',
              'label' => 'LBL_DEPARTMENT',
            ),
          ),
          3 =>
          array(
            0 =>
            array(
              'name' => 'account_name',
              'label' => 'LBL_ACCOUNT_NAME',
            ),
            1 =>
            array(
              'name' => 'phone_fax',
              'label' => 'LBL_FAX_PHONE',
            ),
          ),
          4 =>
          array(
            0 =>
            array(
              'name' => 'email1',
              'studio' => 'false',
              'label' => 'LBL_EMAIL_ADDRESS',
            ),
          ),
          5 =>
          array(
            0 =>
            array(
              'name' => 'primary_address_street',
              'label' => 'LBL_PRIMARY_ADDRESS',
              'type' => 'address',
              'displayParams' =>
              array(
                'key' => 'primary',
              ),
            ),
            1 =>
            array(
              'name' => 'alt_address_street',
              'label' => 'LBL_ALTERNATE_ADDRESS',
              'type' => 'address',
              'displayParams' =>
              array(
                'key' => 'alt',
              ),
            ),
          ),
          6 =>
          array(
            0 =>
            array(
              'name' => 'xero_synch_c',
            ),
            1 =>
            array(
              'name' => 'xero_link_c',
              'label' => 'LBL_XERO_LINK',
              'customCode' => '{if $fields.xero_link_c.value != "" }<a target="_blank" href="{$fields.xero_link_c.value}"><img style="height:25px;" src="custom/logo-xero-blue.svg" alt="Xero Link" title="Go to Xero record"/></a>{/if}'
            ),
          ),
          7 =>
          array(
            0 =>
            array(
              'name' => 'assigned_user_name',
              'label' => 'LBL_ASSIGNED_TO_NAME',
            ),
            1 =>
            array(
              'name' => 'description',
              'comment' => 'Full text of the note',
              'label' => 'LBL_DESCRIPTION',
            ),
          ),
        ),
        'LBL_PANEL_ADVANCED' =>
        array(
          0 =>
          array(
            0 =>
            array(
              'name' => 'lead_source',
              'comment' => 'How did the contact come about',
              'label' => 'LBL_LEAD_SOURCE',
            ),
          ),
          1 =>
          array(
            0 =>
            array(
              'name' => 'report_to_name',
              'label' => 'LBL_REPORTS_TO',
            ),
            1 =>
            array(
              'name' => 'campaign_name',
              'label' => 'LBL_CAMPAIGN',
            ),
          ),
        ),
        'LBL_PANEL_ASSIGNMENT' =>
        array(
          0 =>
          array(
            0 =>
            array(
              'name' => 'date_entered',
              'customCode' => '{$fields.date_entered.value} {$APP.LBL_BY} {$fields.created_by_name.value}',
              'label' => 'LBL_DATE_ENTERED',
            ),
            1 =>
            array(
              'name' => 'date_modified',
              'customCode' => '{$fields.date_modified.value} {$APP.LBL_BY} {$fields.modified_by_name.value}',
              'label' => 'LBL_DATE_MODIFIED',
            ),
          ),
        ),
      ),
    ),
  );