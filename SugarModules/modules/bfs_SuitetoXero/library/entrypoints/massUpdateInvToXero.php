<?php

/********************************

MASS update invoices in Xero from Suite Invoices list view - data TO Xero from Suite

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
	$count = 0;
	$CountNoXero = 0;
	$no_account = 0;
	$problems = 0;

	// load the invoice records
	foreach ($IDArr as $Id) {
		$this->request_params = array();
		$this->params = array();
		$this->headers = array();
		$this->auto_fixed_time = false;
		$this->buffer = null;
		$this->xml = null;

		$invoiceobj = BeanFactory::getBean('AOS_Invoices', $Id);
		$dateDBinvoice = $invoiceobj->invoice_date;
		$xeroInvID = $invoiceobj->xero_id_c;
		// if there is no xero ID in the invoice, nothing to update to, skip the record	
		if ($xeroInvID == '') {
			$CountNoXero++;
			continue;
		}
		// get the associated account and/or contact ids for the invoice update process
		$account_id = '';
		$account_id = $invoiceobj->billing_account_id;
		$contact_id = '';
		$contact_id = $invoiceobj->billing_contact_id;
		// if there is no account or contact record associated with the invoice, skip it	
		if ($account_id == '' && $contact_id == '') {
			$no_account++;
			continue;
		}
		if (!empty($account_id)) {
			$accountobj = BeanFactory::getBean('Accounts', $account_id);
			$xeroID = '';
			$xeroID = $accountobj->xero_id_c;
		} else {
			$contactobj = BeanFactory::getBean('Contacts', $contact_id);
			$xeroID = '';
			$xeroID = $contactobj->xero_id_c;
		}

		$response = $XeroOAuth->request('GET', $XeroOAuth->url('Invoices', 'core'), array('page' => 0, 'Where' => 'InvoiceID=GUID("' . $xeroInvID . '")'));
		if ($XeroOAuth->response['code'] == 200) {
			$InvoicesInvoice = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			$Invoice = $InvoicesInvoice->Invoices->Invoice;
			// update the invoice in Xero
			$currencyID = $invoiceobj->currency_id;
			if ($currencyID == '') {
				$currencyID = -99;
			}

			/* Get the currency name to send to Xero */
			$Currency = BeanFactory::getBean('Currencies', $currencyID);
			if ($Currency != '') {
				$currency_name 				= 			$Currency->iso4217;
			}
			/* end of currency iso4217 code */
			$ExpCode = substr($invoiceobj->xero_expense_codes_c, -3);
			$Reference = $invoiceobj->name;
			$Status = $invoiceobj->status;
			if ($Status == '') {
				$Status = 'DRAFT';
			}
			// get dates directly from database, no date format required	
			$date_sql = "SELECT due_date, invoice_date FROM aos_invoices WHERE id = '" . $invoiceobj->id . "' AND deleted = 0";
			$dates_result = $GLOBALS['db']->query($date_sql);
			$date_row = $GLOBALS['db']->fetchByAssoc($dates_result);
			$dateDBinvoice = '';
			if ($date_row['invoice_date'] != '') {
				$dateDBinvoice = $date_row['invoice_date'];
			} else {
				$dateDBinvoice = date('Y-m-d');
			}
			$dateDBdueDate = '';
			if ($date_row['due_date'] != '') {
				$dateDBdueDate = $date_row['due_date'];
			} else {
				$dateDBdueDate = date('Y-m-d');
			}
			$SubTotal 				=		$invoiceobj->total_amt;
			$TotalTax 				=		$invoiceobj->tax_amount;
			$Total 					=		$invoiceobj->total_amount;

			//Insert Line Items 
			$sql = "SELECT * FROM aos_products_quotes WHERE parent_type = 'AOS_Invoices' AND parent_id = '" . $invoiceobj->id . "' AND deleted = 0";
			$result = $GLOBALS['db']->query($sql);
			$xmlChild = '';
			while ($row = $GLOBALS['db']->fetchByAssoc($result)) {
				$productCostPrice = $row['product_cost_price'];
				$productListPrice = $row['product_list_price'];
				$productDiscount = $row['product_discount'];
				$productDiscountAmount = $row['product_discount'];
				$productUnitPrice = $row['product_unit_price'];
				$productVatAmount = $row['vat_amt'];
				if ($productVatAmount == '') {
					$productVatAmount = 0;
				}
				$productTotalPrice = $row['product_total_price'];
				$productQty = $row['product_qty'];
				$productName = addslashes($row['name']);
				$prosql = "SELECT * FROM aos_products WHERE name = '" . $productName . "' AND deleted = 0";

				$proresult = $GLOBALS['db']->query($prosql);
				$prorow = $GLOBALS['db']->fetchByAssoc($proresult);

				// check the product in Xero,if it doesn't exist, create it
				$response = $XeroOAuth->request('GET', $XeroOAuth->url('Items', 'core'), array('page' => 0, 'Where' => 'name="' . $productName . '" OR code="' . $prorow['part_number'] . '"'));
				$ItemCode = '';
				if ($XeroOAuth->response['code'] == 200) {
					$product = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
					if (count($product->Items) > 0) {
						$ItemCode = $product->Items->Item->Code;
						$ItemCode = htmlentities($ItemCode, ENT_QUOTES, 'UTF-8');
						$name = $product->Items->Item->Description;
					} else {
						$xmlItem = "<Item>
									   <Code>" . $prorow['part_number'] . "</Code>
									  <Description>" . $prorow['name'] . "</Description>
									   <PurchaseDetails>
										<UnitPrice>" . $prorow['cost'] . "</UnitPrice>
										<AccountCode>" . $ExpCode . "</AccountCode>
									  </PurchaseDetails>
									  <SalesDetails>
										<UnitPrice>" . $prorow['price'] . "</UnitPrice>
										<AccountCode>" . $ExpCode . "</AccountCode>
									  </SalesDetails>
									</Item>";

						$response = $XeroOAuth->request('PUT', $XeroOAuth->url('Items', 'core'), array(), $xmlItem);
						if ($XeroOAuth->response['code'] == 200) {
							$addproduct = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
						} else {
							$validationError = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
							// echo "xmlItem:" . $xmlItem;

							echo "Validation problem line 173:<br>";
							print_r($validationError);
							die;
						}
					}
				}
				$xmlChild .= "<LineItem>
								<ItemCode>" . $ItemCode . "</ItemCode>
								<Description>" . $name . "</Description>
								<UnitAmount>" . $productListPrice . "</UnitAmount>";
				if ($Type == 'ACCREC') {
					$xmlChild .= "<DiscountRate>" . $productDiscount . "</DiscountRate>";
				}
				$xmlChild .= "<TaxType>NONE</TaxType>
								<TaxAmount>" . $productVatAmount . "</TaxAmount>
								<AccountCode>" . $ExpCode . "</AccountCode>
								<Quantity>" . $productQty . "</Quantity>
								</LineItem>
						";
			}
			$xml = "<Invoice>
					  <Type>" . $Type . "</Type>
					  <Contact>
						<ContactID>" . $xeroID . "</ContactID>
					  </Contact>
					  <Date>" . $dateDBinvoice . "</Date>
					  <DueDate>" . $dateDBdueDate . "</DueDate>
					  <CurrencyCode>" . $currency_name . "</CurrencyCode>
					  <LineAmountTypes>Exclusive</LineAmountTypes>
					  <Status>" . $Status . "</Status>
					 <SubTotal>" . $SubTotal . "</SubTotal>
					<TotalTax>" . $TotalTax . "</TotalTax>
					<Total>" . $Total . "</Total>
					  <LineItems>";
			$xml .= $xmlChild;
			$xml .= " </LineItems>
					 <InvoiceID>" . $Invoice->InvoiceID . "</InvoiceID>";
			if ($Type == 'ACCPAY') {
				$xml .= "<InvoiceNumber>" . $Reference . "</InvoiceNumber>";
			}
			$xml .= "</Invoice>";
			$response = $XeroOAuth->request('POST', $XeroOAuth->url('Invoices', 'core'), array(), $xml);
			// check to see if the invoice was updated successfully				
			if ($XeroOAuth->response['code'] == 200) {

				global $timedate;
				$CurrenrDateTime = $timedate->getInstance()->nowDb();

				$Invoices = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
				$invoiceobj->xero_id_c = (string) $Invoices->Invoices->Invoice->InvoiceID;
				$invoiceobj->xero_link_c = "https://go.xero.com/AccountsReceivable/Edit.aspx?invoiceid=" . $invoiceobj->xero_id_c;
				$invoiceobj->dtime_synched_c = $CurrenrDateTime;
				$invoiceobj->save();

				// updating line item start
				global $timedate;
				$CurrenrDateTime = $timedate->getInstance()->nowDb();
				foreach ($Invoices->Invoices->Invoice->LineItems->LineItem as $lineItem) {

					$updateLineItem = "UPDATE aos_products_quotes set line_item_id='" . $lineItem->LineItemID . "', date_modified='$CurrenrDateTime' 
							WHERE name = '" . $lineItem->Description . "' AND parent_type = 'AOS_Invoices' AND parent_id = '" . $invoiceobj->id . "' AND (line_item_id = '' OR line_item_id IS NULL) AND 
							((select part_number from aos_products where id = product_id) = '" . $lineItem->ItemCode . "' OR 
							(select maincode from aos_products where id = product_id) = '" . $lineItem->ItemCode . "')  LIMIT 1";

					$dates_result = $GLOBALS['db']->query($updateLineItem);
				}
				// updating line item start
				$count++;
			} else {
				$validationError = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
				$response = $XeroOAuth->response;
				$problems++;
			} // end of for each
		} else { // validation error from line
			$problems++;								
		}
	}

	if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
		$this->writeLogger("$count invoices were successfully updated TO Xero\\n\\nThere were $problems problems with updates");
		$this->alertMsg[] = $count . ' invoices were successfully updated TO Xero\nThere were ' . $problems . ' problems with updates';
	} else {
		echo ("<SCRIPT LANGUAGE='JavaScript'>
				    alert('$count invoices were successfully updated TO Xero\\n\\nThere were $problems problems with updates\\n\\n$CountNoXero records had NO Xero Invoice ID data');
					window.location.href='index.php?module=AOS_Invoices&action=index';
				</SCRIPT>");
	}
}
