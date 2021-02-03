<?php

/********************************

Create a Xero Invoice from a Quote

Copyright 2018 Business Fundamentals

 *************************************/

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

$quoteID = $_REQUEST['quoteID'];
if ($_REQUEST['accountID'] == '' && $_REQUEST['contactID'] == '') {
	echo ("<SCRIPT LANGUAGE='JavaScript'>
	window.alert('No Account Or Contact has been related to this Quote.\\n Please firstly link this Quote to a Contact/Account record\\nand then create the Invoice in Xero.')
	window.location.href='index.php?module=AOS_Quotes&action=EditView&record=" . $quoteID . "';
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


	// if the quote has a xero id, update it, otherwise create it
	$quoteobj = BeanFactory::getBean('AOS_Quotes', $quoteID);
	if ($quoteobj->xero_id_c != '') {
		$xeroQuteID = $quoteobj->xero_id_c;

		$response = $XeroOAuth->request('GET', $XeroOAuth->url('Invoices', 'core'), array('page' => 0, 'Where' => 'InvoiceID=GUID("' . $xeroQuteID . '")'));
		if ($XeroOAuth->response['code'] == 200) {
			$InvoicesQuote = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			$Invoice = $InvoicesQuote->Invoices->Invoice;
			// get the account or contact id for the invoice
			if ($_REQUEST['accountID'] != '') {
				$account_id = $_REQUEST['accountID'];
				$accountobj = BeanFactory::getBean('Accounts', $account_id);
				$xeroID = '';
				$xeroID = $accountobj->xero_id_c;
			} else {
				$contact_id = $_REQUEST['contactID'];
				$contactobj = BeanFactory::getBean('Contacts', $contact_id);
				$xeroID = '';
				$xeroID = $contact->xero_id_c;
			}

			// update the invoice in xero
			$quoteID = $_REQUEST['quoteID'];
			$quoteobj = BeanFactory::getBean('AOS_Quotes', $quoteID);
			$Type = 'ACCREC';
			$dateDBquote = date('Y-m-d');
			$SubTotal 				=		$quoteobj->total_amt;
			$TotalTax 				=		$quoteobj->tax_amount;
			$Total 					=		$quoteobj->total_amount;

			// insert Line Items 
			$sql = "SELECT * FROM aos_products_quotes WHERE parent_type = 'AOS_Quotes' AND parent_id = '" . $quoteobj->id . "' AND deleted = 0";
			$result = $GLOBALS['db']->query($sql);
			$xmlChild = '';
			while ($row = $GLOBALS['db']->fetchByAssoc($result)) {
				$productCostPrice = $row['product_cost_price'];
				$productListPrice = $row['product_list_price'];
				$productDiscount = $row['product_discount'];
				$productDiscountAmount = $row['product_discount'];
				$productUnitPrice = $row['product_unit_price'];
				$productVatAmount = $row['vat_amt'];
				$productTotalPrice = $row['product_total_price'];
				$productQty = $row['product_qty'];
				$productName = $row['name'];
				$prosql = "SELECT * FROM aos_products WHERE name = '" . $productName . "'   AND deleted = 0";
				$proresult = $GLOBALS['db']->query($prosql);
				$prorow = $GLOBALS['db']->fetchByAssoc($proresult);

				// check for existence of producst in xero
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
										<AccountCode>200</AccountCode>
									  </PurchaseDetails>
									  <SalesDetails>
										<UnitPrice>" . $prorow['price'] . "</UnitPrice>
										<AccountCode>200</AccountCode>
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
								<UnitAmount>" . $productListPrice . "</UnitAmount>
								<DiscountRate>" . $productDiscount . "</DiscountRate>
								<TaxType>NONE</TaxType>
								<TaxAmount>" . $productVatAmount . "</TaxAmount>
								<AccountCode>200</AccountCode>
								<Quantity>" . $productQty . "</Quantity>
								</LineItem>
						";
			}
			$xml = "<Invoice>
					  <Type>ACCREC</Type>
					  <Contact>
						<ContactID>" . $xeroID . "</ContactID>
					  </Contact>
					  <Date>" . $dateDBquote . "</Date>
					  <DueDate>" . $dateDBquote . "</DueDate>
					  <LineAmountTypes>Exclusive</LineAmountTypes>
					  <Status>DRAFT</Status>
					 <SubTotal>" . $SubTotal . "</SubTotal>
					<TotalTax>" . $TotalTax . "</TotalTax>
					<Total>" . $Total . "</Total>
					  <LineItems>";
			$xml .= $xmlChild;
			$xml .= " </LineItems>
					 <InvoiceID>" . $Invoice->InvoiceID . "</InvoiceID>
					</Invoice>";
			$response = $XeroOAuth->request('POST', $XeroOAuth->url('Invoices', 'core'), array(), $xml);
			if ($XeroOAuth->response['code'] == 200) {
				echo ("<SCRIPT LANGUAGE='JavaScript'>
				    alert('This invoice has been updated successfully in Xero');
					window.location.href='index.php?module=AOS_Quotes&action=DetailView&record=" . $quoteobj->id . "';
				</SCRIPT>");
			} else {
				$validationError = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
				// echo"<pre>";print_r($validationError);die;
				echo ("<SCRIPT LANGUAGE='JavaScript'>
				    alert('There was a problem with updating this invoice in Xero');
					window.location.href='index.php?module=AOS_Quotes&action=DetailView&record=" . $quoteobj->id . "';
				</SCRIPT>");
			}
		} else {
			$validationError = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			//echo"<pre>";print_r($validationError);die;
		}
	} else {
		// check for the existence of an account id, if there is NONE AND there is no contact either, give the option to create one first    

		if ($_REQUEST['accountID'] != '') {
			$account_id = $_REQUEST['accountID'];
			$accountobj = BeanFactory::getBean('Accounts', $account_id);
			$xeroID = '';
			$xeroID = $accountobj->xero_id_c;
			if ($xeroID == '') {
				echo ("<SCRIPT LANGUAGE='JavaScript'>
			if(!confirm('No contact record was found in Xero\\nWould you like to create them? If NO, the\\ninvoice can NOT be created')){
				window.location.href='index.php?module=AOS_Quotes&action=DetailView&record=" . $quoteID . "';
			}
				</SCRIPT>");
				$account_name = $accountobj->name;
				$account_name = htmlentities($account_name, ENT_QUOTES, 'UTF-8');
				if ($accountobj->shipping_address_street != '') {
					$shipping_account_street = $accountobj->shipping_address_street;
					$shipping_account_city = $accountobj->shipping_address_city;
					$shipping_account_state = $accountobj->shipping_address_state;
					$shipping_account_postalcode = $accountobj->shipping_address_postalcode;
					$shipping_account_country = $accountobj->shipping_address_country;

					$account_street = $accountobj->billing_address_street;
					$account_city = $accountobj->billing_address_city;
					$account_state = $accountobj->billing_address_state;
					$account_postalcode = $accountobj->billing_address_postalcode;
					$account_country = $accountobj->billing_address_country;
				} else {
					$account_street = $accountobj->billing_address_street;
					$account_city = $accountobj->billing_address_city;
					$account_state = $accountobj->billing_address_state;
					$account_postalcode = $accountobj->billing_address_postalcode;
					$account_country = $accountobj->billing_address_country;

					$shipping_account_street = $accountobj->billing_address_street;
					$shipping_account_city = $accountobj->billing_address_city;
					$shipping_account_state = $accountobj->billing_address_state;
					$shipping_account_postalcode = $accountobj->billing_address_postalcode;
					$shipping_account_country = $accountobj->billing_address_country;
				}

				$account_phone = $accountobj->phone_office;
				$phone_fax = $accountobj->phone_fax;
				$contacts = $accountobj->get_linked_beans('contacts', 'Contact', 'xero_primary_contact_c DESC, last_name ASC,first_name ASC');

				// xmlchild var only have child contacts not primary contact, for primary contact var xmlPrimaryContact will have value
				$xmlchild = '<ContactPersons>';
				foreach ($contacts as $key => $contact) {
					if ($key == 0 && $contact->xero_primary_contact_c == 1) {
						$primaryContact = $contacts[0];
						$xmlPrimaryContact = " <FirstName>" . $primaryContact->first_name . "</FirstName>
						<LastName>" . $primaryContact->last_name . "</LastName>
						<EmailAddress>" . $primaryContact->email1 . "</EmailAddress>";
					} else {
						$xmlchild .= "<ContactPerson>
						<FirstName>" . $contact->first_name . "</FirstName>
						<LastName>" . $contact->last_name . "</LastName>
						<EmailAddress>" . $contact->email1 . "</EmailAddress>
					</ContactPerson>";
						if ($key == 5)
							break;
					}
				}
				$xmlchild .= "</ContactPersons>";

				//echo print_r($xmlchild);exit;
				$xml = "<Contacts>
				 <Contact>
					<ContactID>" . $account_id . "</ContactID>
				   <Name>" . $account_name . "</Name>
				   " . (isset($xmlPrimaryContact) ? $xmlPrimaryContact : '') . "
				   <AccountNumber>" . $account_id . "</AccountNumber>
				   <Website>" . $accountobj->website . "</Website>
					 <Addresses>
					   <Address>
						 <AddressType>POBOX</AddressType>
						 <AddressLine1>" . $account_street . "</AddressLine1>
						 <City>" . $account_city . "</City>
						 <Country>" . $account_country . "</Country>
						 <Region>" . $account_state . "</Region>
						 <PostalCode>" . $account_postalcode . "</PostalCode>
						 <AttentionTo>" . $account_name . "</AttentionTo>
					   </Address>
					   <Address>
						 <AddressType>STREET</AddressType>
						 <AddressLine1>" . $shipping_account_street . "</AddressLine1>
						 <City>" . $shipping_account_city . "</City>
						 <Country>" . $shipping_account_country . "</Country>
						 <Region>" . $shipping_account_state . "</Region>
						 <PostalCode>" . $shipping_account_postalcode . "</PostalCode>
						 <AttentionTo>" . $account_name . "</AttentionTo>
					   </Address>
					 </Addresses>
					 <Phones>
					<Phone>
						<PhoneType>DEFAULT</PhoneType>
						<PhoneNumber>" . $account_phone . "</PhoneNumber>
					  </Phone>
						 <Phone>
							 <PhoneType>FAX</PhoneType>
								<PhoneNumber>" . $phone_fax . "</PhoneNumber>
						   </Phone>							
					</Phones>
					";
				$xml .= $xmlchild;
				$xml .= "
				 </Contact>
			   </Contacts>
			   ";
				$response = $XeroOAuth->request('PUT', $XeroOAuth->url('Contacts', 'core'), array(), $xml);
				//echo $XeroOAuth->response['code'];
				if ($XeroOAuth->response['code'] == 200) {
					$contact = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
					if (count($contact->Contacts[0]) > 0) {
						$ContactObj = $contact->Contacts[0]->Contact;
						$XeroContactID = $ContactObj->ContactID;
						$accountobj->xero_id_c = $XeroContactID;
						$accountobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactID;
						$accountobj->save();
						
						// updating all related contacts
						foreach ($contacts as $relatedContact) {
							if ($relatedContact->id == $primaryContact->id) { // if primary contact
								$relatedContact->xero_primary_contact_c = 1;
							}
							$relatedContact->xero_id_c = $XeroContactID;
							$relatedContact->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactID;
							$relatedContact->dtime_synched_c = $CurrenrDateTime;
							$relatedContact->save();
						}
					}
					$xeroID = $XeroContactID;
				} else {
					$validationError = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
					//echo"<pre>";print_r($validationError);die;
				}
			}
		} else if ($_REQUEST['contactID'] != '') {
			$contact_id = $_REQUEST['contactID'];
			$contactobj = BeanFactory::getBean('Contacts', $contact_id);

			if ($contactobj->account_id != '') {
				// if contact is related to account then assign it to the quote and perform the invoice creation
				$quoteobj->billing_account_id = $contactobj->account_id;
				$quoteobj->save();
				$accountobj = BeanFactory::getBean('Accounts', $contactobj->account_id);
				$xeroID = '';
				$xeroID = $accountobj->xero_id_c;
				//	echo "XeroID:->".$xeroID;
				if ($xeroID == '') {
					echo ("<SCRIPT LANGUAGE='JavaScript'>
							if(!confirm('No contact record was found in Xero\\nWould you like to create them? If NO, the\\ninvoice can NOT be created')){
								window.location.href='index.php?module=AOS_Quotes&action=DetailView&record=" . $quoteID . "';
							}
								</SCRIPT>");
					$account_name = $accountobj->name;
					$account_name = htmlentities($account_name, ENT_QUOTES, 'UTF-8');
					if ($accountobj->shipping_address_street != '') {
						$shipping_account_street = $accountobj->shipping_address_street;
						$shipping_account_city = $accountobj->shipping_address_city;
						$shipping_account_state = $accountobj->shipping_address_state;
						$shipping_account_postalcode = $accountobj->shipping_address_postalcode;
						$shipping_account_country = $accountobj->shipping_address_country;

						$account_street = $accountobj->billing_address_street;
						$account_city = $accountobj->billing_address_city;
						$account_state = $accountobj->billing_address_state;
						$account_postalcode = $accountobj->billing_address_postalcode;
						$account_country = $accountobj->billing_address_country;
					} else {
						$account_street = $accountobj->billing_address_street;
						$account_city = $accountobj->billing_address_city;
						$account_state = $accountobj->billing_address_state;
						$account_postalcode = $accountobj->billing_address_postalcode;
						$account_country = $accountobj->billing_address_country;

						$shipping_account_street = $accountobj->billing_address_street;
						$shipping_account_city = $accountobj->billing_address_city;
						$shipping_account_state = $accountobj->billing_address_state;
						$shipping_account_postalcode = $accountobj->billing_address_postalcode;
						$shipping_account_country = $accountobj->billing_address_country;
					}

					$account_phone = $accountobj->phone_office;
					$phone_fax = $accountobj->phone_fax;
					$contacts = $accountobj->get_linked_beans('contacts', 'Contact', 'last_name ASC,first_name ASC');

					$i = 1;
					$xmlchild = '';
					foreach ($contacts as $contact) {
						if ($i == 1) {
							$xmlchild .= " <FirstName>" . $contact->first_name . "</FirstName>
											<LastName>" . $contact->last_name . "</LastName>
											<EmailAddress>" . $contact->email1 . "</EmailAddress>";
						} else {
							if ($i == 2) {
								$xmlchild .= "<ContactPersons>";
							}
							$xmlchild .= "<ContactPerson>
														 <FirstName>" . $contact->first_name . "</FirstName>
														<LastName>" . $contact->last_name . "</LastName>
														<EmailAddress>" . $contact->email1 . "</EmailAddress>
													</ContactPerson>
															";
						}
						$i++;
						if ($i >= 6)
							break;
					}
					if ($i > 2) {
						$xmlchild .= "</ContactPersons>";
					}
					//echo print_r($xmlchild);exit;
					$xml = "<Contacts>
								 <Contact>
									<ContactID>" . $account_id . "</ContactID>
								   <Name>" . $account_name . "</Name>
								   <AccountNumber>" . $account_id . "</AccountNumber>
								   <Website>" . $accountobj->website . "</Website>
									 <Addresses>
									   <Address>
										 <AddressType>POBOX</AddressType>
										 <AddressLine1>" . $account_street . "</AddressLine1>
										 <City>" . $account_city . "</City>
										 <Country>" . $account_country . "</Country>
										 <Region>" . $account_state . "</Region>
										 <PostalCode>" . $account_postalcode . "</PostalCode>
										 <AttentionTo>" . $account_name . "</AttentionTo>
									   </Address>
									   <Address>
										 <AddressType>STREET</AddressType>
										 <AddressLine1>" . $shipping_account_street . "</AddressLine1>
										 <City>" . $shipping_account_city . "</City>
										 <Country>" . $shipping_account_country . "</Country>
										 <Region>" . $shipping_account_state . "</Region>
										 <PostalCode>" . $shipping_account_postalcode . "</PostalCode>
										 <AttentionTo>" . $account_name . "</AttentionTo>
									   </Address>
									 </Addresses>
									 <Phones>
									<Phone>
										<PhoneType>DEFAULT</PhoneType>
										<PhoneNumber>" . $account_phone . "</PhoneNumber>
									  </Phone>
										 <Phone>
											 <PhoneType>FAX</PhoneType>
												<PhoneNumber>" . $phone_fax . "</PhoneNumber>
										   </Phone>							
									</Phones>
									";
					$xml .= $xmlchild;
					$xml .= "
								 </Contact>
							   </Contacts>
							   ";
					$response = $XeroOAuth->request('PUT', $XeroOAuth->url('Contacts', 'core'), array(), $xml);
					//echo $XeroOAuth->response['code'];
					if ($XeroOAuth->response['code'] == 200) {
						$contact = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
						if (count($contact->Contacts[0]) > 0) {
							$ContactObj = $contact->Contacts[0]->Contact;
							$XeroContactID = $ContactObj->ContactID;
							$accountobj->xero_id_c = $XeroContactID;
							$accountobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactID;
							$accountobj->save();
							foreach ($contacts as $contact) {
								$contact->xero_id_c = $XeroContactID;
								$contact->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactID;
								$contact->save();
							}
						}
						$xeroID = $XeroContactID;
					} else {
						$validationError = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
						//echo"<pre>";print_r($validationError);die;
					}
				}

				//echo $xeroID."=======";
			} else {
				$xeroID = '';
				$xeroID = $contactobj->xero_id_c;

				if ($xeroID == '') {
					$contact_name = $contactobj->first_name . ' ' . $contactobj->last_name;
					echo ("<SCRIPT LANGUAGE='JavaScript'>
					if(!confirm('No contact record was found in Xero\\nWould you like to create them? If NO, the\\ninvoice can NOT be created')){
						window.location.href='index.php?module=AOS_Quotes&action=DetailView&record=" . $quoteID . "';
					}
						</SCRIPT>");
					$contact_email = $contactobj->email1;
					$phone_mobile = $contactobj->phone_mobile;

					$shipping_account_street = $contactobj->primary_address_street;
					$shipping_account_city = $contactobj->primary_address_city;
					$shipping_account_state = $contactobj->primary_address_state;
					$shipping_account_postalcode = $contactobj->primary_address_postalcode;
					$shipping_account_country = $contactobj->primary_address_country;

					$account_street = $contactobj->alt_address_street;
					$account_city = $contactobj->alt_address_city;
					$account_state = $contactobj->alt_address_state;
					$account_postalcode = $contactobj->alt_address_postalcode;
					$account_country = $contactobj->alt_address_country;
					if ($contactobj->alt_address_street == '') {
						$shipping_account_street = $contactobj->primary_address_street;
						$shipping_account_city = $contactobj->primary_address_city;
						$shipping_account_state = $contactobj->primary_address_state;
						$shipping_account_postalcode = $contactobj->primary_address_postalcode;
						$shipping_account_country = $contactobj->primary_address_country;

						$account_street = $contactobj->primary_address_street;
						$account_city = $contactobj->primary_address_city;
						$account_state = $contactobj->primary_address_state;
						$account_postalcode = $contactobj->primary_address_postalcode;
						$account_country = $contactobj->primary_address_country;
					}
					$contact_name = htmlentities($contact_name, ENT_QUOTES, 'UTF-8');
					$xml1 = "<Contacts>
					 <Contact>
					   <Name>" . $contact_name . "</Name>
					   <AccountNumber>" . $contactobj->id . "</AccountNumber>
						 <Addresses>
						   <Address>
							 <AddressType>POBOX</AddressType>
							 <AddressLine1>" . $account_street . "</AddressLine1>
							 <City>" . $account_city . "</City>
							 <Country>" . $account_country . "</Country>
							 <Region>" . $account_state . "</Region>
							 <PostalCode>" . $account_postalcode . "</PostalCode>
							 <AttentionTo>" . $contact_name . "</AttentionTo>
						   </Address>
							<Address>
							 <AddressType>STREET</AddressType>
							 <AddressLine1>" . $shipping_account_street . "</AddressLine1>
							 <City>" . $shipping_account_city . "</City>
							 <Country>" . $shipping_account_country . "</Country>
							 <Region>" . $shipping_account_state . "</Region>
							 <PostalCode>" . $shipping_account_postalcode . "</PostalCode>
							 <AttentionTo>" . $contact_name . "</AttentionTo>
						   </Address>
						 </Addresses>
						  <FirstName>" . $contactobj->first_name . "</FirstName>
						 <LastName>" . $contactobj->last_name . "</LastName>
						 <EmailAddress>" . $contact_email . "</EmailAddress>";
					//$xml1 .= $xmlchild;
					$xml1 .= "<Phones>
						<Phone>
							<PhoneType>DEFAULT</PhoneType>
							<PhoneNumber>" . $contactobj->phone_work . "</PhoneNumber>
						  </Phone>
							 <Phone>
								 <PhoneType>FAX</PhoneType>
									<PhoneNumber>" . $contactobj->phone_fax . "</PhoneNumber>
							   </Phone>
							   <Phone>
								 <PhoneType>MOBILE</PhoneType>
									<PhoneNumber>" . $contactobj->phone_mobile . "</PhoneNumber>
							   </Phone>
						</Phones>
					 </Contact>
				   </Contacts>
				   ";

					$response = $XeroOAuth->request('PUT', $XeroOAuth->url('Contacts', 'core'), array(), $xml1);
					if ($XeroOAuth->response['code'] == 200) {
						$contact = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
						if (count($contact->Contacts[0]) > 0) {
							$ContactObj = $contact->Contacts[0]->Contact;
							$XeroContactID = $ContactObj->ContactID;
							$contactobj->xero_id_c = $XeroContactID;
							$contactobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactID;
							$contactobj->save();
						}
					}
					$xeroID = $XeroContactID;
				}
			}
		}

		// create the invoice in Xero
		$quoteID = $_REQUEST['quoteID'];
		$quoteobj = BeanFactory::getBean('AOS_Quotes', $quoteID);
		$Type = 'ACCREC';
		$dateDBquote = '';
		$dateDBquote = date('Y-m-d');

		$SubTotal 				=		$quoteobj->total_amt;
		$TotalTax 				=		$quoteobj->tax_amount;
		$Total 					=		$quoteobj->total_amount;

		// insert line items 
		$sql = "SELECT * FROM aos_products_quotes WHERE parent_type = 'AOS_Quotes' AND parent_id = '" . $quoteobj->id . "' AND deleted = 0";

		$result = $GLOBALS['db']->query($sql);
		$xmlChild = '';
		while ($row = $GLOBALS['db']->fetchByAssoc($result)) {
			$productCostPrice = $row['product_cost_price'];
			$productListPrice = $row['product_list_price'];
			$productDiscount = $row['product_discount'];
			$productDiscountAmount = $row['product_discount'];
			$productUnitPrice = $row['product_unit_price'];
			$productVatAmount = $row['vat_amt'];
			$productTotalPrice = $row['product_total_price'];
			$productQty = $row['product_qty'];
			$productName = $row['name'];
			$prosql = "SELECT * FROM aos_products WHERE name = '" . $productName . "'   AND deleted = 0";
			$proresult = $GLOBALS['db']->query($prosql);
			$prorow = $GLOBALS['db']->fetchByAssoc($proresult);
			// product checking in xero, if it doesn't exist create it
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
							    <Name>" . $prorow['name'] . "</Name>
							   
							  <Description>" . $prorow['name'] . "</Description>
							   <PurchaseDetails>
								<UnitPrice>" . $prorow['cost'] . "</UnitPrice>
								<AccountCode>200</AccountCode>
							  </PurchaseDetails>
							  <SalesDetails>
								<UnitPrice>" . $prorow['price'] . "</UnitPrice>
								<AccountCode>200</AccountCode>
							  </SalesDetails>
							</Item>";
					$response = $XeroOAuth->request('PUT', $XeroOAuth->url('Items', 'core'), array(), $xmlItem);
					if ($XeroOAuth->response['code'] == 200) {
						$addproduct = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
						$ItemCode = $prorow['part_number'];
						$name = $productName;
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
						<UnitAmount>" . $productListPrice . "</UnitAmount>
						<DiscountRate>" . $productDiscount . "</DiscountRate>
						<TaxType>NONE</TaxType>
						<TaxAmount>" . $productVatAmount . "</TaxAmount>
						<AccountCode>200</AccountCode>
						<Quantity>" . $productQty . "</Quantity>
						</LineItem>
				";
		}
		$xml = "<Invoice>
			  <Type>ACCREC</Type>
			  <Contact>
				<ContactID>" . $xeroID . "</ContactID>
			  </Contact>
			  <Date>" . $dateDBquote . "</Date>
			  <DueDate>" . $dateDBquote . "</DueDate>
			  <LineAmountTypes>Exclusive</LineAmountTypes>
			  <Status>DRAFT</Status>
			 <SubTotal>" . $SubTotal . "</SubTotal>
			<TotalTax>" . $TotalTax . "</TotalTax>
			<Total>" . $Total . "</Total>
			  <LineItems>";
		$xml .= $xmlChild;
		$xml .= " </LineItems>
			</Invoice>";
		//echo $xml;
		$response = $XeroOAuth->request('POST', $XeroOAuth->url('Invoices', 'core'), array(), $xml);
		if ($XeroOAuth->response['code'] == 200) {
			$Invoices = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			$quoteobj->xero_id_c = (string)$Invoices->Invoices->Invoice->InvoiceID;
			$quoteobj->xero_link_c = "https://go.xero.com/AccountsReceivable/Edit.aspx?invoiceid=" . $quoteobj->xero_id_c;
			$quoteobj->invoice_status = "Xero Invoiced";
			$quoteobj->save();
			echo ("<SCRIPT LANGUAGE='JavaScript'>
	  alert('An invoice from this Quote was successfully created in Xero');
				window.location.href='index.php?module=AOS_Quotes&action=DetailView&record=" . $quoteobj->id . "';
		</SCRIPT>");
		} else {
			$validationError = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			print_r($validationError);
			echo ("<SCRIPT LANGUAGE='JavaScript'>
					alert('There was an error creating the invoice in Xero');
				window.location.href='index.php?module=AOS_Quotes&action=DetailView&record=" . $quoteobj->id . "';
				</SCRIPT>");
		}
	}
	if ($XeroOAuth->response['helper'] == 'TokenFatal') {
		$helper = $XeroOAuth->response['helper'];
		$problem = 'Token Rejected: the organisation for this token is NOT active';
	} else {
		$problem = 'Please check your Xero connector settings and organisation settings in Xero\\n\\nXERO PROBLEM: ".$helper."';
	}
	echo ("<SCRIPT LANGUAGE='JavaScript'>
			window.alert('There has been a problem with your Xero connection\\n\\nXERO PROBLEM:\\n\\n" . $problem . "')
			window.location.href='index.php?module=Contacts&action=index';
			</SCRIPT>");
}
