<?php

/********************************

Update an Invoice in Xero from an Invoice in Suite

Copyright 2018 Business Fundamentals

 *************************************/
if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

$invoiceID = $_REQUEST['invID'];
if ($_REQUEST['accountID'] == '' && $_REQUEST['contactID'] == '') {
	echo ("<SCRIPT LANGUAGE='JavaScript'>
	window.alert('No Account Or Contact has been related to this Invoice.\\n Please firstly link this Invoice to a Contact/Account record\\nand then create the Invoice in Xero.')
	window.location.href='index.php?module=AOS_Invoices&action=EditView&record=" . $invoiceID . "';
	</SCRIPT>");
}
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
	$this->request_params = array();
	$this->params = array();
	$this->headers = array();
	$this->auto_fixed_time = false;
	$this->buffer = null;
	$this->xml = null;

	$invoiceobj = BeanFactory::getBean('AOS_Invoices', $invoiceID);
	$xeroInvID = $invoiceobj->xero_id_c;
	$response = $XeroOAuth->request('GET', $XeroOAuth->url('Invoices', 'core'), array('page' => 0, 'Where' => 'InvoiceID=GUID("' . $xeroInvID . '")'));
	if ($XeroOAuth->response['code'] == 200) {
		$InvoicesInvoice = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
		$Invoice = $InvoicesInvoice->Invoices->Invoice;
		// echo "<pre>";print_r($Invoice);die;
		//Fetch Account/Contact Xero Id For Invoice   
		if ($_REQUEST['accountID'] != '') {
			$account_id = $_REQUEST['accountID'];
			$accountobj = BeanFactory::getBean('Accounts', $account_id);
			$xeroID = '';
			$xeroID = $accountobj->xero_id_c;
		} else {
			$contact_id = $_REQUEST['contactID'];
			$contactobj = BeanFactory::getBean('Contacts', $contact_id);
			$xeroID = '';
			$xeroID = $contactobj->xero_id_c;
		}
		// update the invoice in Xero
		$invoiceID = $_REQUEST['invID'];
		$invoiceobj = BeanFactory::getBean('AOS_Invoices', $invoiceID);
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

		$Type = $invoiceobj->type_c;
		$ExpCode = substr($invoiceobj->xero_expense_codes_c, -3);
		$Reference = $invoiceobj->name;
		$Status = $invoiceobj->status;
		$XeroAccepStatus = ['DRAFT', 'SUBMITTED', 'AUTHORISED'];
		$Status = strtoupper($Status);
		if (!in_array($Status, $XeroAccepStatus)) {
			$Status = "DRAFT";
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
			// For Checking TrackingCategory(group in Suite) in Xero if it doesn't exist, create a new TrackingCategory in Xero 
			// $trackingCategoryDetails = checkTrackingCategory($row, $XeroOAuth);


			$productCostPrice = $row['product_cost_price'];
			$productListPrice = $row['product_list_price'];
			$productDiscount = $row['product_discount'];
			$productDiscountAmount = $row['product_discount'];
			$productUnitPrice = $row['product_unit_price'];
			$productVatAmount = $row['vat_amt'];
			$LineItemID = $row['line_item_id'];
			if ($productVatAmount == '') {
				$productVatAmount = 0;
			}
			$productTotalPrice = $row['product_total_price'];
			$productQty = $row['product_qty'];
			$productName = $row['name'];
			$prosql = "SELECT * FROM aos_products WHERE name = '" . $productName . "'   AND deleted = 0";
			$proresult = $GLOBALS['db']->query($prosql);
			$prorow = $GLOBALS['db']->fetchByAssoc($proresult);

			//For Checking Product in Xero if it doesn't exist, create a new product in Xero
			$response = $XeroOAuth->request('GET', $XeroOAuth->url('Items', 'core'), array('page' => 0, 'Where' => 'name="' . $productName . '" OR code="' . $prorow['part_number'] . '"'));
			$ItemCode = '';
			if ($XeroOAuth->response['code'] == 200) {
				$product = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
				if (count($product->Items) > 0) {
					$ItemCode = $product->Items->Item->Code;
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
						//print_r($addproduct);
					} else {
						$validationError = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
						//print_r($validationError);die;
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
						<Quantity>" . $productQty . "</Quantity>";
			if (!empty($LineItemID)) {
				$xmlChild .= "<LineItemID>$LineItemID</LineItemID>";
			}

			$xmlChild .= "</LineItem>";
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

		if ($XeroOAuth->response['code'] == 200) {

			$InvoicesParsedResponse = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			// updating line item start
			global $timedate;
			$CurrenrDateTime = $timedate->getInstance()->nowDb();
			foreach ($InvoicesParsedResponse->Invoices->Invoice->LineItems->LineItem as $lineItem) {
				$updateLineItem = "UPDATE aos_products_quotes set line_item_id='" . $lineItem->LineItemID . "', date_modified='$CurrenrDateTime' 
				WHERE name = '" . $lineItem->Description . "' AND parent_type = 'AOS_Invoices' AND parent_id = '$invoiceID' AND (line_item_id = '' OR line_item_id IS NULL) AND 
				((select part_number from aos_products where id = product_id) = '" . $lineItem->ItemCode . "' OR 
				(select maincode from aos_products where id = product_id) = '" . $lineItem->ItemCode . "') LIMIT 1";

				$dates_result = $GLOBALS['db']->query($updateLineItem);
			}
			// updating line item start

			$inv_name = $invoiceobj->name;
			if (isset($_REQUEST['hook_called']) && $_REQUEST['hook_called']) {
				$this->writeLogger("Invoice $inv_name has been updated successfully TO Xero");
			} else {
				echo ("<SCRIPT LANGUAGE='JavaScript'>
				    alert('Invoice $inv_name has been updated successfully TO Xero');
					window.location.href='index.php?module=AOS_Invoices&action=DetailView&record=" . $invoiceobj->id . "';
				</SCRIPT>");
			}
		} else {
			$validationError = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			//echo"<pre>";print_r($response);die;
			//$xero_error = $validationError->Elements->DataContractBase->ValidationErrors->ValidationError->Message;
			//alert('There was a problem in Xero with updating this invoice line\\n\\nXERO ERROR RETURNED:\\n$xero_error');
			if (isset($_REQUEST['hook_called']) && $_REQUEST['hook_called']) {
				$this->writeLogger('There was a problem in Xero with updating this invoice');
			} else {
				echo ("<SCRIPT LANGUAGE='JavaScript'>
				    	    alert('There was a problem in Xero with updating this invoice');
					window.location.href='index.php?module=AOS_Invoices&action=DetailView&record=" . $invoiceobj->id . "';
				</SCRIPT>");
			}
		}
	} else {
		$validationError = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
		//echo"<pre>";print_r($validationError);die;
		//$xero_error = $validationError->Elements->DataContractBase->ValidationErrors->ValidationError->Message;
		if (isset($_REQUEST['hook_called']) && $_REQUEST['hook_called']) {
			$this->writeLogger('There was a problem in Xero with updating this invoice');
		} else {
			echo ("<SCRIPT LANGUAGE='JavaScript'>
				    //alert('There was a problem in Xero with updating this invoice\\n\\nXERO ERROR RETURNED:\\n$xero_error');
				    alert('There was a problem in Xero with updating this invoice');
					window.location.href='index.php?module=AOS_Invoices&action=DetailView&record=" . $invoiceobj->id . "';
				</SCRIPT>");
		}
	}
}
