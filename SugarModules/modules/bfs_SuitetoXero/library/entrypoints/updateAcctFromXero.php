<?php

/********************************

Update an Account from Xero

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

	$this->request_params = array();
	$this->params = array();
	$this->headers = array();
	$this->auto_fixed_time = false;
	$this->buffer = null;
	$this->xml = null;

	// load the account record
	$account_id = $_REQUEST['accountID'];
	$xero_id = $_REQUEST['xeroID'];
	$accountobj = BeanFactory::getBean('Accounts', $account_id);

	// get the account record from Xero
	$response = $XeroOAuth->request('GET', $XeroOAuth->url('Contacts', 'core'), array('page' => 0, 'Where' => 'ContactID=GUID("' . $xero_id . '")'));

	if ($XeroOAuth->response['code'] == 200) {
		$Account = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
		//print 'Number of contacts: '. count($Account->Contacts->Contact->ContactPersons);
		//echo '</pre>';
		if (isset($Account->Contacts)) {
			$Contact = $Account->Contacts->Contact;
			// save Xero data to the contact record
			$accountobj->name = $Contact->Name;
			// $accountobj->email = $Contact->EmailAddress;
			$accountobj->website = $Contact->Website;
			$accountobj->phone_office = $Contact->Phones->Phone[1]->PhoneNumber;
			$accountobj->phone_fax = $Contact->Phones->Phone[2]->PhoneNumber;
			$accountobj->phone_alternate = $Contact->Phones->Phone[3]->PhoneNumber;
			$accountobj->xero_id_c = $Contact->ContactID;
			$accountobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $Contact->ContactID;

			$accountobj->shipping_address_street = $Contact->Addresses->Address[0]->AddressLine1;
			$accountobj->shipping_address_city = $Contact->Addresses->Address[0]->City;
			$accountobj->shipping_address_state = $Contact->Addresses->Address[0]->Region;
			$accountobj->shipping_address_postalcode = $Contact->Addresses->Address[0]->PostalCode;
			$accountobj->shipping_address_country = $Contact->Addresses->Address[0]->Country;
			$accountobj->billing_address_street = $Contact->Addresses->Address[1]->AddressLine1;
			$accountobj->billing_address_city = $Contact->Addresses->Address[1]->City;
			$accountobj->billing_address_state = $Contact->Addresses->Address[1]->Region;
			$accountobj->billing_address_postalcode = $Contact->Addresses->Address[1]->PostalCode;
			$accountobj->billing_address_country = $Contact->Addresses->Address[1]->Country;
			$accountobj->save();

			// save mail id
			$sea = new SugarEmailAddress;
			$sea->addAddress($Contact->EmailAddress, true);
			$sea->save($accountobj->id, "Accounts");

			// update related contacts if any
			//$contacts = $accountobj->get_linked_beans('contacts', 'Contact', 'last_name ASC,first_name ASC');
			require_once 'custom/entrypoints/XeroOAuth-PHP-master/LinkedContact.php';
			$LinkedContact = new LinkedContact();

			$LinkedContact->addLinkedContacts($Contact, $accountobj->id);
		}

		echo ("<SCRIPT LANGUAGE='JavaScript'>
								window.alert('Record successfully updated FROM Xero')
								window.location.href='index.php?module=Accounts&action=DetailView&record=" . $account_id . "';
								</SCRIPT>");
	} else {
		// there has been an error in the process, log to file and set JS error
		echo ("<SCRIPT LANGUAGE='JavaScript'>
								window.alert('There was a problem with updating this record from Xero.\\nCheck the log file for details')
								window.location.href='index.php?module=Accounts&action=DetailView&record=" . $account_id . "';
								</SCRIPT>");
	}
}
