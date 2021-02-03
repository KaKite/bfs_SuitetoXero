<?php

/********************************

Create multiple Invoices in Xero from the Invoice list view in Suite

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
	$invcnt = 0;
	$xeroidcnt = 0;
	$problemscnt = 0;
	$noLinkedContact = 0;
	$invcreated = 0;
	$invProblemNumber = '';
	$itemCodeMismatch = [];

	// load the invoice records
	foreach ($IDArr as $Id) {
		$this->request_params = array();
		$this->params = array();
		$this->headers = array();
		$this->auto_fixed_time = false;
		$this->buffer = null;
		$this->xml = null;
		$invoiceID = $Id;
		$invoiceobj = BeanFactory::getBean('AOS_Invoices', $invoiceID);
		// check for a xero id in the invoice, if there is one, skip to next record
		$xeroinvid = $invoiceobj->xero_id_c;
		if ($xeroinvid != '') {
			$invcnt++;
			$xeroidcnt++;
			continue;
		}
		// get the associated account and/or contact ids for the invoice update process
		$account_id = '';
		$account_id = $invoiceobj->billing_account_id;
		$contact_id = '';
		$contact_id = $invoiceobj->billing_contact_id;
		// if there is no account or contact record associated with the invoice, skip it	
		if ($invoiceobj->billing_account_id == '' && $invoiceobj->billing_contact_id == '') {
			$invcnt++;
			$noLinkedContact++;
			continue;
		}

		// add the Xero Invoices name to all groups associated to this invoice
		$checkGroup = "Select * from aos_line_item_groups where parent_id='" . $invoiceobj->id . "' and parent_type='AOS_Invoices' and deleted=0";
		$Group_result = $GLOBALS['db']->query($checkGroup);
		if ($GLOBALS['db']->getRowCount($Group_result) > 0) {
			$Grouoprow = $GLOBALS['db']->fetchByAssoc($Group_result);
			$updateGroup = "update aos_line_item_groups set name='Xero Invoices' where name='' and parent_id='" . $invoiceobj->id . "' and parent_type='AOS_Invoices' and deleted=0 ";
			$GLOBALS['db']->query($updateGroup);
		}

		// check for a related account
		if ($invoiceobj->billing_account_id != '') {
			$account_id = $invoiceobj->billing_account_id;
			$accountobj = BeanFactory::getBean('Accounts', $account_id);
			$xeroID = '';
			$xeroID = $accountobj->xero_id_c;
			// check for xero id, if none ask to create the account in xero first 
			if ($xeroID == '') {
				echo ("<SCRIPT LANGUAGE='JavaScript'>
				if(!confirm('No contact record was found in Xero\\nWould you like to create them? If NO, the\\ninvoice can NOT be created')){window.location.href='index.php?module=AOS_Invoices&action=DetailView&record=" . $invoiceID . "';}</SCRIPT>");
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
					$xero_error = $validationError->Elements->DataContractBase->ValidationErrors->ValidationError->Message;
					echo ("<SCRIPT LANGUAGE='JavaScript'>
				    alert('There was a problem in Xero with creating the Contact record\\n\\nXERO ERROR RETURNED:\\n$xero_error');
					window.location.href='index.php?module=AOS_Invoices&action=DetailView&record=" . $invoiceobj->id . "';
				</SCRIPT>");
				}
			}
		} else if ($invoiceobj->billing_contact_id != '') {
			$contact_id = $invoiceobj->billing_contact_id;
			$contactobj = BeanFactory::getBean('Contacts', $contact_id);
			if ($contactobj->account_id != '') {
				// there was no Account in the invoice, but the contact in the invoice has a related account  
				$invoiceobj->billing_account_id = $contactobj->account_id;
				$invoiceobj->save(); // save the account record id to the invoice
				$accountobj = BeanFactory::getBean('Accounts', $contactobj->account_id);
				$xeroID = '';
				$xeroID = $accountobj->xero_id_c;
				//	ask to create a new contact record in xero using contact details from suite
				if ($xeroID == '') {
					echo ("<SCRIPT LANGUAGE='JavaScript'>
			if(!confirm('No contact record was found in Xero\\nWould you like to create them? If NO, the\\ninvoice can NOT be created')){window.location.href='index.php?module=AOS_Invoices&action=DetailView&record=" . $invoiceID . "';}</SCRIPT>");
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
						$xero_error = $validationError->Elements->DataContractBase->ValidationErrors->ValidationError->Message;
						echo ("<SCRIPT LANGUAGE='JavaScript'>alert('There was a problem in Xero with creating the Contact record\\n\\nXERO ERROR RETURNED:\\n$xero_error');window.location.href='index.php?module=AOS_Invoices&action=DetailView&record=" . $invoiceobj->id . "';	</SCRIPT>");
						//echo"<pre>";print_r($validationError);die;
					}
				}
			} else {
				$xeroID = '';
				$xeroID = $contactobj->xero_id_c;
				// does the contact record exist in Xero? If NO create them and then the invoice			
				if ($xeroID == '') {
					$contact_name = $contactobj->first_name . ' ' . $contactobj->last_name;
					$contact_name = htmlentities($contact_name, ENT_QUOTES, 'UTF-8');
					echo ("<SCRIPT LANGUAGE='JavaScript'>if(!confirm('No contact record was found in Xero\\nWould you like to create them? If NO, the\\ninvoice can NOT be created')){		window.location.href='index.php?module=AOS_Invoices&action=DetailView&record=" . $invoiceID . "';}</SCRIPT>");
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

		// create the invoice in xero
		$invoiceID = $invoiceID;
		$invoiceobj = BeanFactory::getBean('AOS_Invoices', $invoiceID);
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
			$productName = $row['name'];
			$prosql = "SELECT * FROM aos_products WHERE name = '" . $productName . "'   AND deleted = 0";
			$proresult = $GLOBALS['db']->query($prosql);
			$prorow = $GLOBALS['db']->fetchByAssoc($proresult);
			// check the product in Xero, if it doesn't exist, create it
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
								<AccountCode>" . $ExpCode . "</AccountCode>
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
						$xero_error = $validationError->Elements->DataContractBase->ValidationErrors->ValidationError->Message;
						echo ("<SCRIPT LANGUAGE='JavaScript'>
				    alert('There was a problem in Xero with creating the Product\\n\\nXERO ERROR RETURNED:\\n$xero_error');
					window.location.href='index.php?module=AOS_Invoices&action=DetailView&record=" . $invoiceobj->id . "';
				</SCRIPT>");
					}
				}
			}

			if ($prorow['part_number'] != $ItemCode) { // if item code doesn't match
				$itemCodeMismatch[] = "Invoice $invoiceobj->invoice_number has mismatch item:- $name";
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
			  <LineAmountTypes>Exclusive</LineAmountTypes>
			  <Status>" . $Status . "</Status>
			 <SubTotal>" . $SubTotal . "</SubTotal>
			<TotalTax>" . $TotalTax . "</TotalTax>
			<Total>" . $Total . "</Total>
			  <LineItems>";
		$xml .= $xmlChild;
		$xml .= " </LineItems>";
		if ($Type == 'ACCPAY') {
			$xml .= "<InvoiceNumber>" . $Reference . "</InvoiceNumber>";
		}
		$xml .= "</Invoice>";
		// echo $xml;
		$response = $XeroOAuth->request('POST', $XeroOAuth->url('Invoices', 'core'), array(), $xml);
		if ($XeroOAuth->response['code'] == 200) {

			global $timedate;
			$CurrenrDateTime = $timedate->getInstance()->nowDb();

			$Invoices = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			$invoiceobj->xero_id_c = (string) $Invoices->Invoices->Invoice->InvoiceID;
			$invoiceobj->xero_link_c = "https://go.xero.com/AccountsReceivable/Edit.aspx?invoiceid=" . $invoiceobj->xero_id_c;
			$invoiceobj->dtime_synched_c = $CurrenrDateTime;
			$invoiceobj->save();
			// updating line item start
			foreach ($Invoices->Invoices->Invoice->LineItems->LineItem as $lineItem) {

				$updateLineItem = "UPDATE aos_products_quotes set line_item_id='" . $lineItem->LineItemID . "', date_modified='$CurrenrDateTime' 
				WHERE name = '" . $lineItem->Description . "' AND parent_type = 'AOS_Invoices' AND parent_id = '" . $invoiceobj->id . "' AND (line_item_id = '' OR line_item_id IS NULL) AND 
				((select part_number from aos_products where id = product_id) = '" . $lineItem->ItemCode . "' OR 
				(select maincode from aos_products where id = product_id) = '" . $lineItem->ItemCode . "')  LIMIT 1";
				$dates_result = $GLOBALS['db']->query($updateLineItem);
			}
			// updating line item start
			$invcnt++;
			$invcreated++;
		} else {
			$validationError = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			$problemscnt++;
			$invProblemNumber .= "\\n\\n($validationError->Message)";
			if (isset($validationError->Message)) {
				$xero_error = $validationError->Message;
			} else {
				$xero_error = $validationError->Elements->DataContractBase->ValidationErrors->ValidationError->Message;
			}
		}
	} // end of for each loop
	// show all count data

	$alertMsg = "Of the $invcnt Invoices selected.";
	if ($xeroidcnt > 0) {
		$alertMsg .= "\\n\\n$xeroidcnt already existed in Xero.";
	}
	if ($noLinkedContact > 0) {
		$alertMsg .= "\\n\\n$noLinkedContact Invoice has no linked Account/Contact.";
	}
	$alertMsg .= "\\n\\n$invcreated Invoices were created.";

	if (($xeroidcnt + $noLinkedContact) > 0) {
		$alertMsg .= "\\n\\n" . ($xeroidcnt + $noLinkedContact) . " were skipped.";
	}

	if ($invProblemNumber != '') {
		$alertMsg .= "\\n\\nFollowing inovices facing issue on creating Invoice into Xero:-$invProblemNumber";
	}

	if (count($itemCodeMismatch) > 0) {
		$alertMsg .= "\\n\\n" . count($itemCodeMismatch) . ' Items mismatch, Check log file fo details.';
		if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
			$this->writeLogger($itemCodeMismatch);
		} else {
			writeLogger($itemCodeMismatch);
		}
	}

	if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
		$alertMsg = explode("\\n\\n", $alertMsg);
		$this->writeLogger("Mass Invoices To Xero ==>>" . implode("\n", $alertMsg));
		$this->alertMsg[] = $invcreated . 'Invoices were created';
	} else {
		echo ("<SCRIPT LANGUAGE='JavaScript'>
	  alert('$alertMsg');
				window.location.href='index.php?module=AOS_Invoices&action=index';
		</SCRIPT>");
	}
}
