<?php

/********************************

Send multiple Contacts or Accounts to Xero from Suite list view

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

	$UIDs = $_REQUEST['uid'];
	$IDArr = explode(',', $UIDs);
	$GLOBALS['log']->debug('IDArr');
	$GLOBALS['log']->debug($IDArr);
	$count = 0;
	$ContactUpdated = '';
	$contactError = [];
	foreach ($IDArr as $Id) {
		//print_r($Id);die;
		// load the account record
		$account_id = $Id;
		$accountobj = BeanFactory::getBean('Accounts', $account_id);

		// load variables for Xero
		$account_name = $accountobj->name;
		$account_name = htmlentities($account_name, ENT_QUOTES, 'UTF-8');
		//$account_email = $accountobj->email1;

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
		// check for duplicate, pre-existing contact
		$response = $XeroOAuth->request('GET', $XeroOAuth->url('Contacts', 'core'), array('page' => 0, 'Where' => 'Name="' . $account_name . '" OR AccountNumber="' . $account_id . '"'));

		global $timedate;
		$CurrenrDateTime = $timedate->getInstance()->nowDb();
		// echo "<pre>"; print_r($XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format'])); die;
		if ($XeroOAuth->response['code'] == 200) {
			$duplicatecontact = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			
			// echo"<pre>";print_r( $XeroContactobj);echo count($duplicatecontact->Contacts[0]);
			if (isset($duplicatecontact->Contacts) && count($duplicatecontact->Contacts) > 0 && ($duplicatecontact->Contacts[0]->Contact->ContactID != '')) {
				$XeroContactobj = $duplicatecontact->Contacts[0]->Contact;
				$accountobj->xero_id_c = $XeroContactobj->ContactID;
				$accountobj->dtime_synched_c = $CurrenrDateTime;
				$accountobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactobj->ContactID;
				$accountobj->save();
				foreach ($contacts as $contact) {
					$contact->xero_id_c = $XeroContactobj->ContactID;
					$contact->dtime_synched_c = $CurrenrDateTime;
					$contact->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactobj->ContactID;
					$contact->save();
				}
				if ($ContactUpdated != '')
					$ContactUpdated = $ContactUpdated . '\\n' . $accountobj->name;
				else
					$ContactUpdated = $accountobj->name;
			} else {
				// create new Account				  
				$response = $XeroOAuth->request('PUT', $XeroOAuth->url('Contacts', 'core'), array(), $xml);
				//echo"<pre>";print_r($response);
				if ($XeroOAuth->response['code'] == 200) {
					$contact = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
					if (count($contact->Contacts[0]) > 0) {
						$ContactObj = $contact->Contacts[0]->Contact;
						$XeroContactID = $ContactObj->ContactID;
						$accountobj->xero_id_c = $XeroContactID;
						$accountobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactID;
						$accountobj->dtime_synched_c = $CurrenrDateTime;
						$accountobj->save();
						foreach ($contacts as $contact) {
							$contact->xero_id_c = $XeroContactID;
							$contact->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactID;
							$contact->dtime_synched_c = $CurrenrDateTime;
							$contact->save();
						}
						$count++;
					}
				} else {

					if ($XeroOAuth->response['code'] == 400) {
						$validateErr = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);

						$contactError[] = $accountobj->name . ' Error:- ' . $validateErr->Elements->DataContractBase->ValidationErrors->ValidationError->Message;
						// echo ("<SCRIPT LANGUAGE='JavaScript'>
						// 				window.alert('There was an error in creating Contact in Xero :" . $accountobj->name . "')
						// 				window.location.href='index.php?module=Accounts&action=index';
						// 				</SCRIPT>");
					}
				}
			}
		}
	}
	// end Foreachloop for Account Ids			
	if ($ContactUpdated != '') {
		$msg = $count . ' new Contacts have been added to Xero from Accounts. The following Contacts were already in Xero:\\n\\n' . $ContactUpdated;

		if (count($contactError) > 0) {
			$msg .= '\\nThe following Contacts facing error:\\n' . implode('\n', $contactError);
		}

		if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
			$this->writeLogger("$count new Contacts have been added to Xero from Accounts. The following Contacts were already in Xero:\n\n$ContactUpdated.");
			$this->alertMsg[] = $msg;
		} else {
			echo ("<SCRIPT LANGUAGE='JavaScript'>
				window.alert('$msg')
				window.location.href='index.php?module=Accounts&action=index';
				</SCRIPT>");
		}
	} else {
		if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
			$this->writeLogger("$count new Contacts have been added to Xero from Accounts.");
			$this->alertMsg[] = "$count new Contacts have been added to Xero from Accounts.";
		} else {
			echo ("<SCRIPT LANGUAGE='JavaScript'>
				window.alert('" . $count . " new Contacts have been added to Xero from Accounts.')
				window.location.href='index.php?module=Accounts&action=index';
				</SCRIPT>");
		}
	}
}
