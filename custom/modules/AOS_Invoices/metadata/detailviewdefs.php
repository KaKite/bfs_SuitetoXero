<?php
$module_name = 'AOS_Invoices';
$_object_name = 'aos_invoices';
$viewdefs[$module_name] =
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
              'customCode' => '<input type="button" class="button" onClick="showPopup(\'pdf\');" value="{$MOD.LBL_PRINT_AS_PDF}">',
            ),
            5 =>
            array(
              'customCode' => '<input type="button" class="button" onClick="showPopup(\'emailpdf\');" value="{$MOD.LBL_EMAIL_PDF}">',
            ),
            6 =>
            array(
              'customCode' => '<input type="button" class="button" onClick="showPopup(\'email\');" value="{$MOD.LBL_EMAIL_INVOICE}">',
            ),
            /**************** xero custom button code, cut and paste as required *****************/
            7 =>
            array(
              'customCode' => '{if $fields.xero_id_c.value == "" }<input type="button" class="button" id="invFromInvoice" onClick="window.location=\'index.php?entryPoint=xeroLink&func=sendInvToXero&accountID={$fields.billing_account_id.value}&contactID={$fields.billing_contact_id.value}&invID={$fields.id.value}&xeroID={$fields.xero_id_c.value}\'" value="Create an Invoice in Xero">{/if}',
            ),
            8 =>
            array(
              'customCode' => '{if $fields.xero_id_c.value != "" }<input type="button" class="button" id="updateInvoiceFromXero" onClick="window.location=\'index.php?entryPoint=xeroLink&func=updateInvToXero&accountID={$fields.billing_account_id.value}&contactID={$fields.billing_contact_id.value}&invID={$fields.id.value}&xeroID={$fields.xero_id_c.value}\'" value="Update this Invoice TO Xero">{/if}',
            ),
            9 =>
            array(
              'customCode' => '{if $fields.xero_id_c.value != "" }<input type="button" class="button" id="updateInvoiceToXero" onClick="window.location=\'index.php?entryPoint=xeroLink&func=updateInvFromXero&accountID={$fields.billing_account_id.value}&contactID={$fields.billing_contact_id.value}&invID={$fields.id.value}&xeroID={$fields.xero_id_c.value}\'" value="Update this Invoice FROM Xero">{/if}',
            ),
            /*********** end of xero code ************/
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
        'useTabs' => false,
        'tabDefs' =>
        array(
          'LBL_PANEL_OVERVIEW' =>
          array(
            'newTab' => false,
            'panelDefault' => 'expanded',
          ),
          'LBL_INVOICE_TO' =>
          array(
            'newTab' => false,
            'panelDefault' => 'expanded',
          ),
          'LBL_LINE_ITEMS' =>
          array(
            'newTab' => false,
            'panelDefault' => 'expanded',
          ),
        ),
        'syncDetailEditViews' => true,
      ),
      'panels' =>
      array(
        'LBL_PANEL_OVERVIEW' =>
        array(
          0 =>
          array(
            0 =>
            array(
              'name' => 'name',
              'label' => 'LBL_NAME',
            ),
            1 =>
            array(
              'name' => 'number',
              'label' => 'LBL_INVOICE_NUMBER',
            ),
          ),
          1 =>
          array(
            0 =>
            array(
              'name' => 'quote_number',
              'label' => 'LBL_QUOTE_NUMBER',
            ),
            1 =>
            array(
              'name' => 'quote_date',
              'label' => 'LBL_QUOTE_DATE',
            ),
          ),
          2 =>
          array(
            0 =>
            array(
              'name' => 'due_date',
              'label' => 'LBL_DUE_DATE',
            ),
            1 =>
            array(
              'name' => 'invoice_date',
              'label' => 'LBL_INVOICE_DATE',
            ),
          ),
          3 =>
          array(
            0 =>
            array(
              'name' => 'status',
              'label' => 'LBL_STATUS',
            ),
            1 =>
            array(
              'name' => 'type_c',
              'studio' => 'visible',
              'label' => 'LBL_TYPE',
            ),
          ),
          4 =>
          array(
            0 =>
            array(
              'name' => 'xero_synch_c',
			  'label' => 'LBL_XERO_SYNCH',
            ),
            1 =>
            array(
              'name' => 'xero_expense_codes_c',
              'label' => 'LBL_XERO_EXPENSE_CODES',
            ),
          ),
          5 =>
          array(
            0 =>
            array(
              'name' => 'assigned_user_name',
              'label' => 'LBL_ASSIGNED_TO_NAME',
            ),
            1 =>
            array(
              'name' => 'xero_link_c',
              'label' => 'LBL_XERO_LINK',
              'customCode' => '{if $fields.xero_link_c.value != "" }<a target="_blank" href="{$fields.xero_link_c.value}"><img style="height:25px;" src="custom/logo-xero-blue.svg" alt="Xero Link" title="Go to Xero record"/></a>{/if}'
            ),
          ),
          6 =>
          array(
            0 =>
            array(
              'name' => 'description',
              'label' => 'LBL_DESCRIPTION',
            ),
          ),
        ),
        'LBL_INVOICE_TO' =>
        array(
          0 =>
          array(
            0 =>
            array(
              'name' => 'billing_account',
              'label' => 'LBL_BILLING_ACCOUNT',
            ),
            1 => '',
          ),
          1 =>
          array(
            0 =>
            array(
              'name' => 'billing_contact',
              'label' => 'LBL_BILLING_CONTACT',
            ),
            1 => '',
          ),
          2 =>
          array(
            0 =>
            array(
              'name' => 'billing_address_street',
              'label' => 'LBL_BILLING_ADDRESS',
              'type' => 'address',
              'displayParams' =>
              array(
                'key' => 'billing',
              ),
            ),
            1 =>
            array(
              'name' => 'shipping_address_street',
              'label' => 'LBL_SHIPPING_ADDRESS',
              'type' => 'address',
              'displayParams' =>
              array(
                'key' => 'shipping',
              ),
            ),
          ),
        ),
        'lbl_line_items' =>
        array(
          0 =>
          array(
            0 =>
            array(
              'name' => 'currency_id',
              'studio' => 'visible',
              'label' => 'LBL_CURRENCY',
            ),
          ),
          1 =>
          array(
            0 =>
            array(
              'name' => 'line_items',
              'label' => 'LBL_LINE_ITEMS',
            ),
          ),
          2 =>
          array(
            0 => '',
          ),
          3 =>
          array(
            0 =>
            array(
              'name' => 'total_amt',
              'label' => 'LBL_TOTAL_AMT',
            ),
          ),
          4 =>
          array(
            0 =>
            array(
              'name' => 'discount_amount',
              'label' => 'LBL_DISCOUNT_AMOUNT',
            ),
          ),
          5 =>
          array(
            0 =>
            array(
              'name' => 'subtotal_amount',
              'label' => 'LBL_SUBTOTAL_AMOUNT',
            ),
          ),
          6 =>
          array(
            0 =>
            array(
              'name' => 'shipping_amount',
              'label' => 'LBL_SHIPPING_AMOUNT',
            ),
          ),
          7 =>
          array(
            0 =>
            array(
              'name' => 'shipping_tax_amt',
              'label' => 'LBL_SHIPPING_TAX_AMT',
            ),
          ),
          8 =>
          array(
            0 =>
            array(
              'name' => 'tax_amount',
              'label' => 'LBL_TAX_AMOUNT',
            ),
          ),
          9 =>
          array(
            0 =>
            array(
              'name' => 'total_amount',
              'label' => 'LBL_GRAND_TOTAL',
            ),
          ),
        ),
      ),
    ),
  );
