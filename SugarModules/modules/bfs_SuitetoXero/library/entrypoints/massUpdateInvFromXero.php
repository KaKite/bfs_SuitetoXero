<?php

/********************************

Update all Invoices selected using details FROM XERO

Copyright 2018 Business Fundamentals

 *************************************/

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
if (isset($_REQUEST)) {
	if (!isset($_REQUEST['where'])) $_REQUEST['where'] = "";
}

if (isset($_REQUEST['wipe'])) {
	session_destroy();
	header("Location: {$here}");

	// already got some credentials stored?
} elseif (isset($_REQUEST['refresh'])) {
	$response = $XeroOAuth->refreshToken($oauthSession['oauth_token'], $oauthSession['oauth_session_handle']);
	if ($XeroOAuth->response['code'] == 200) {
		$session = persistSession($response);
		$oauthSession = retrieveSession();
	} else {
		outputError($XeroOAuth);
		if ($XeroOAuth->response['helper'] == "TokenExpired") $XeroOAuth->refreshToken($oauthSession['oauth_token'], $oauthSession['session_handle']);
	}
} elseif (isset($oauthSession['oauth_token']) && isset($_REQUEST)) {

	$XeroOAuth->config['access_token']  = $oauthSession['oauth_token'];
	$XeroOAuth->config['access_token_secret'] = $oauthSession['oauth_token_secret'];
	$XeroOAuth->config['session_handle'] = $oauthSession['oauth_session_handle'];

	// get the selected Invoice ID's
	$UIDs = $_REQUEST['uid'];
	$IDArr = explode(',', $UIDs);

	$count_inv = 0;
	$count_array = count($IDArr);
	$CountNoXero = 0;

	$this->request_params = array();
	$this->params = array();
	$this->headers = array();
	$this->auto_fixed_time = false;
	$this->buffer = null;
	$this->xml = null;

	// load the invoice records
	foreach ($IDArr as $Id) {
		$invID = $Id;
		$invoiceobj = BeanFactory::getBean('AOS_Invoices', $invID);
		$xeroID = $invoiceobj->xero_id_c;
		$account_id = $invoiceobj->billing_account_id;
		$contact_id = $invoiceobj->billing_contact_id;
		// no contact or account record in this invoice, skip to next record
		if ($account_id == '' && $contact_id == '') {
			continue;
		}
		// no xero id in the invoice, skip to the next record
		if ($xeroID == '' || !isset($xeroID)) {
			$CountNoXero++;
			continue;
		}
		if (isset($account_id) && $account_id != '') {
			$account = BeanFactory::getBean('Accounts', $account_id);
			$childcontacts = $account->get_linked_beans('contacts', 'Contact', 'last_name ASC,first_name ASC');
			$childContactID = '';
			foreach ($childcontacts as $childContact) {
				$childContactID = $childContact->id;
				break;
			}
		}
		if (isset($contact_id) && $contact_id != '') {
			$contact = BeanFactory::getBean('Contacts', $contact_id);
		}

		// get the invoice in Xero
		$response = $XeroOAuth->request('GET', $XeroOAuth->url('Invoices', 'core'), array('page' => 0, 'Where' => 'InvoiceID=GUID("' . $xeroID . '")'));

		if ($XeroOAuth->response['code'] == 200) { // found an invoice in Xero that matches the Xero ID field
			$Invoices = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);

			//Fetch invoices:
			$Invoicebean = BeanFactory::newBean('AOS_Invoices');
			if (isset($Invoices->Invoices)) {
				foreach ($Invoices->Invoices->Invoice as $Invoice) {
					$Date							=			'';
					$Date							=			$Invoice->Date;
					$DueDate						=			'';
					if (isset($Invoice->DueDate) && $Invoice->DueDate != '')
						$DueDate 					=			$Invoice->DueDate;
					$Status							=			'';
					$Status							=			ucfirst(strtolower($Invoice->Status));
					$LineAmountTypes				=			'';
					$LineAmountTypes				=			$Invoice->LineAmountTypes;
					$SubTotal						=			'';
					$SubTotal						=			$Invoice->SubTotal;
					$TotalTax						=			'';
					$TotalTax						=			$Invoice->TotalTax;
					$Total							=			'';
					$Total							=			$Invoice->Total;
					$Type							=			'';
					$Type							=			$Invoice->Type;
					$ExpCode						=			'';
					$ExpCode						=    		$Invoice->LineItems->LineItem->AccountCode;
					$XeroInvoiceID					=			'';
					$XeroInvoiceID					=    		$Invoice->InvoiceID;
					$InvoiceNumber					=			'';
					$InvoiceNumber					=			$Invoice->InvoiceNumber;
					$UpdatedDateUTC					=			'';
					$UpdatedDateUTC					=			$Invoice->UpdatedDateUTC;
					$CurrencyCode					=			'';
					$CurrencyCode					=			$Invoice->CurrencyCode;
					//$AmountDue						=			$Invoice->AmountDue;
					//$AmountPaid						=			$Invoice->AmountPaid;
					//$AmountCredited					=			$Invoice->AmountCredited;
					//$CurrencyRate						=			$Invoice->CurrencyRate;
					//$HasAttachments					=			$Invoice->HasAttachments;
					/*************************************************************************************************/

					//Assign variables in Invoice Object
					/* Get the currency ID from the currencies table CurrencyCode = ISO4217 in currencies*/
					$Currency = BeanFactory::getBean('Currencies')->retrieve_by_string_fields(array('iso4217' => $CurrencyCode,));
					if ($Currency != '') {
						$currencyID = $Currency->id;
						$Invoicebean->currency_id	=			$currencyID;
					} else {
						$currencyID = '';
						$Invoicebean->currency_id	=			$currencyID;
					}
					/* end of currency iso4217 code */
					$Invoicebean->invoice_date		=    		date("Y-m-d", strtotime($Date));
					if (isset($DueDate) && $DueDate != '')
						$Invoicebean->due_date			=    		date("Y-m-d", strtotime($DueDate));
					$Invoicebean->status			=    		$Status;
					if ($Type == 'ACCREC') {
						$Invoicebean->type_c		=    		'ACCREC';
						$Invoicebean->xero_expense_codes_c =    'ACCREC_'.$ExpCode;
						$Invoicebean->name			=    		$InvoiceNumber;
						$stat						=			'AccountsReceivable';
					}
					if ($Type == 'ACCPAY') {
						$Invoicebean->type_c		=    		'ACCPAY';
						$Invoicebean->xero_expense_codes_c =    'ACCPAY_'.$ExpCode;
						$stat						=			'AccountsPayable';
					}
					$Invoicebean->total_amt			=			$SubTotal;
					$Invoicebean->subtotal_amount	=			$SubTotal;
					$Invoicebean->tax_amount		=			$TotalTax;
					$Invoicebean->total_amount		=			$Total;
					$Invoicebean->xeroutc_c			=			$UpdatedDateUTC;
					$Invoicebean->xero_id_c			=			$XeroInvoiceID;
					$Invoicebean->xero_link_c		=			"https://go.xero.com/" . $stat . "/Edit.aspx?invoiceid=" . $XeroInvoiceID;
					if (isset($accountID) && $accountID != '') {
						$Invoicebean->billing_account_id				=		$account->id;
						$Invoicebean->billing_contact_id				=		$childContactID;
						if ($account->shipping_address_street != '') {
							$Invoicebean->billing_address_street		=		$account->billing_address_street;
							$Invoicebean->billing_address_city			=		$account->billing_address_city;
							$Invoicebean->billing_address_state			=		$account->billing_address_state;
							$Invoicebean->billing_address_postalcode	=		$account->billing_address_postalcode;
							$Invoicebean->billing_address_country		=		$account->billing_address_country;

							$Invoicebean->shipping_address_street		=		$account->shipping_address_street;
							$Invoicebean->shipping_address_city			=		$account->shipping_address_city;
							$Invoicebean->shipping_address_state		=		$account->shipping_address_state;
							$Invoicebean->shipping_address_postalcode	=		$account->shipping_address_postalcode;
							$Invoicebean->shipping_address_country		=		$account->shipping_address_country;
						} else {
							$Invoicebean->billing_address_street		=		$account->billing_address_street;
							$Invoicebean->billing_address_city			=		$account->billing_address_city;
							$Invoicebean->billing_address_state			=		$account->billing_address_state;
							$Invoicebean->billing_address_postalcode	=		$account->billing_address_postalcode;
							$Invoicebean->billing_address_country		=		$account->billing_address_country;

							$Invoicebean->shipping_address_street		=		$account->billing_address_street;
							$Invoicebean->shipping_address_city			=		$account->billing_address_city;
							$Invoicebean->shipping_address_state		=		$account->billing_address_state;
							$Invoicebean->shipping_address_postalcode	=		$account->billing_address_postalcode;
							$Invoicebean->shipping_address_country		=		$account->billing_address_country;
						}
					} else if (!isset($account_id) && isset($contact_id)) {
						$Invoicebean->billing_contact_id						=		$contact->id;
						if ($contact->alt_address_street != '') {
							$Invoicebean->shipping_address_street 				= 		$contact->alt_address_street;
							$Invoicebean->shipping_address_city 				= 		$contact->alt_address_city;
							$Invoicebean->shipping_address_state 				= 		$contact->alt_address_state;
							$Invoicebean->shipping_address_postalcode 			= 		$contact->alt_address_postalcode;
							$Invoicebean->shipping_address_country 				= 		$contact->alt_address_country;

							$Invoicebean->billing_address_street 				= 		$contact->primary_address_street;
							$Invoicebean->billing_address_city 					= 		$contact->primary_address_city;
							$Invoicebean->billing_address_state 				= 		$contact->primary_address_state;
							$Invoicebean->billing_address_postalcode 			= 		$contact->primary_address_postalcode;
							$Invoicebean->billing_address_country 				= 		$contact->primary_address_country;
						} else {
							$Invoicebean->shipping_address_street 				= 		$contact->primary_address_street;
							$Invoicebean->shipping_address_city 				= 		$contact->primary_address_city;
							$Invoicebean->shipping_address_state 				= 		$contact->primary_address_state;
							$Invoicebean->shipping_addresst_postalcode 			= 		$contact->primary_address_postalcode;
							$Invoicebean->shipping_address_country 				= 		$contact->primary_address_country;

							$Invoicebean->billing_address_street 				= 		$contact->primary_address_street;
							$Invoicebean->billing_address_city 					= 		$contact->primary_address_city;
							$Invoicebean->billing_address_state 				= 		$contact->primary_address_state;
							$Invoicebean->billing_address_postalcode 			= 		$contact->primary_address_postalcode;
							$Invoicebean->billing_address_country 				= 		$contact->primary_address_country;
						}
					}
					$invoiceID													=			'';
					$Invoicebean->id											= 			'';
					$Invoicebean->name;
					$checkInvoices = "Select * from aos_invoices_cstm where xero_id_c='" . $Invoicebean->xero_id_c . "' and id_c in(select id from aos_invoices where deleted=0)";
					$Invoice_result = $GLOBALS['db']->query($checkInvoices);
					if ($GLOBALS['db']->getRowCount($Invoice_result) > 0) {
						$invoiceRow = $GLOBALS['db']->fetchByAssoc($Invoice_result);
						$invoiceID = $invoiceRow['id_c'];
						$Invoicebean->id = $invoiceID;
						$Invoicebean->id;
					}

					global $timedate;
					$Invoicebean->dtime_synched_c = $timedate->getInstance()->nowDb();
					$Invoicebean->save();
					$count_inv++;
					//Entry for Group
					$checkGroup = "Select * from aos_line_item_groups where name='Xero Invoices' and parent_id='" . $Invoicebean->id . "' and parent_type='AOS_Invoices' and deleted=0";
					$Group_result = $GLOBALS['db']->query($checkGroup);
					if ($GLOBALS['db']->getRowCount($Group_result) > 0) {
						$Grouoprow = $GLOBALS['db']->fetchByAssoc($Group_result);
						$updateGroup = "update aos_line_item_groups set total_amt='$SubTotal' ,tax_amount='$TotalTax',subtotal_amount='$SubTotal',total_amount='$Total',number=1  where name='Xero Invoices' and parent_id='" . $Invoicebean->id . "' and parent_type='AOS_Invoices' and deleted=0 ";
						$GLOBALS['db']->query($updateGroup);
						$groupID = $Grouoprow['id'];
					} else {
						$oid = create_guid();
						$GRSQL = "INSERT INTO aos_line_item_groups 
										(id,name,date_entered,date_modified ,total_amt,tax_amount,subtotal_amount,total_amount,parent_type,parent_id,number) VALUE ('$oid','Xero Invoices',NOW(),NOW(),'$SubTotal','$TotalTax','$SubTotal','$Total','AOS_Invoices','" . $Invoicebean->id . "','1')";
						$GLOBALS['db']->query($GRSQL);
						$groupID = $oid;
					}
					//Initialise variables
					$i = 1;
					$totalPrice		=	0.00;
					$discount		= 	0.00;
					$subTotal 		= 	0.00;
					$TotalAmount	=	0.00;
					$DiscountRate	=	0.00;
					$UnitAmount		=	0.00;
					$TaxAmount		=	0.00;

					//********************Entry For Line items ***************/
					foreach ($Invoice->LineItems->LineItem as $LineItem) {
						$ItemCode 			=			$LineItem->ItemCode;
						$Description		=			$LineItem->Description;
						$UnitAmount			=			$LineItem->UnitAmount;
						$TaxType			=			$LineItem->TaxType;
						$TaxAmount			=			$LineItem->TaxAmount;
						$LineAmount			=			$LineItem->LineAmount;
						$Quantity			=			$LineItem->Quantity;
						$DiscountRate		=			$LineItem->DiscountRate;
						if (!isset($DiscountRate) && $DiscountRate == '') {
							$DiscountRate = 0.00;
						}
						$LineItemID			=			$LineItem->LineItemID;
						$totalPrice			=		    $totalPrice	 + ($UnitAmount *  $Quantity);
						$discount			=			$discount + ($UnitAmount *  $Quantity * $DiscountRate / 100);
						$subTotal			=			$subTotal + ($totalPrice - ($UnitAmount *  $Quantity * $DiscountRate / 100));
						$TotalAmount		=			$TotalAmount + ($subTotal + $TaxAmount);
						//Check For Existing Product on the basis of product Code					
						$checkProduct = "Select * from aos_products WHERE name='$Description' ||  maincode='$ItemCode' and deleted=0";
						$checkProduct_result = $GLOBALS['db']->query($checkProduct);
						if ($GLOBALS['db']->getRowCount($checkProduct_result) > 0) {
							$productIDresult = $GLOBALS['db']->fetchByAssoc($checkProduct_result);
							$productID = $productIDresult['id'];
							$updateproduct = "UPDATE aos_products SET maincode='$ItemCode' ,part_number='$ItemCode',name='$Description' ,cost='$UnitAmount' ,price='$UnitAmount'  where id='$productID' and deleted=0";
							$GLOBALS['db']->query($updateproduct);
						} else {
							$productID = create_guid();
							$prodSQLInsert = "INSERT into aos_products (id,name,maincode,part_number,type,cost,price,date_entered,date_modified) values('$productID','$Description','$ItemCode','$ItemCode','Good','$UnitAmount','$UnitAmount',NOW(),NOW())";
							$GLOBALS['db']->query($prodSQLInsert);
						}
						$checkLineItem = "Select * from aos_products_quotes where line_item_id='$LineItemID' and deleted=0";
						$Line_Item_result = $GLOBALS['db']->query($checkLineItem);
						if ($GLOBALS['db']->getRowCount($Line_Item_result) > 0) {
							$UpLineItem = "update aos_products_quotes set name='$Description',part_number='$ItemCode',item_description='$Description',product_qty='$Quantity',
						product_cost_price='$UnitAmount',product_discount='$DiscountRate',discount='Percentage',product_list_price='$UnitAmount',product_unit_price='$UnitAmount',product_total_price='$LineAmount',product_id='$productID', deleted=0 where line_item_id='$LineItemID'";
							$GLOBALS['db']->query($UpLineItem);
						} else {
							$aos_p_q_id = create_guid();
							$AOSSQL = "INSERT INTO aos_products_quotes(id,date_entered,date_modified,number,name,part_number,item_description,product_qty,product_cost_price,product_discount,discount,product_list_price,product_unit_price,product_total_price,product_id,group_id,parent_id,parent_type,line_item_id) 
							VALUES ('$aos_p_q_id',NOW(),NOW(),$i,'$Description','$ItemCode','$Description','$Quantity','$UnitAmount','$DiscountRate','Percentage','$UnitAmount','$UnitAmount','$LineAmount','$productID','$groupID','" . $Invoicebean->id . "','AOS_Invoices','$LineItemID')";

							$GLOBALS['db']->query($AOSSQL);
						}
						$i++;
					} // end of Line items
				} // end processing Invoice
			}
		} else {
			$Error = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
				$this->writeLogger('Problem with updating the invoices from Xero');
				$this->writeLogger($Error);
			} else {
				echo ("<SCRIPT LANGUAGE='JavaScript'>
				window.alert('There was a problem with updating the invoices from Xero')
				window.location.href='index.php?module=AOS_Invoices&action=index';
				</SCRIPT>");
			}
		}
	}
	$updated_inv = $count_array - $CountNoXero;
	if ($CountNoXero > 0) {
		$msg = "Of the $count_array Invoices selected $updated_inv have been successfully updated\\nwith data FROM Xero. $CountNoXero invoices had NO Xero ID data and were not";
		if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
			$this->writeLogger($msg);
		} else {
			echo ("<SCRIPT LANGUAGE='JavaScript'>
			window.alert('Of the $count_array Invoices selected $updated_inv have been successfully updated\\nwith data FROM Xero. $CountNoXero invoices had NO Xero ID data and were not')
			window.location.href='index.php?module=AOS_Invoices&action=index';
			</SCRIPT>");
		}
	} else {
		$msg = "$count_array Invoices have been successfully updated with data FROM Xero";
		if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
			$this->writeLogger($msg);
		} else {
			echo ("<SCRIPT LANGUAGE='JavaScript'>
			window.alert('" . $msg . "')
			window.location.href='index.php?module=AOS_Invoices&action=index';
			</SCRIPT>");
		}
	}
}
