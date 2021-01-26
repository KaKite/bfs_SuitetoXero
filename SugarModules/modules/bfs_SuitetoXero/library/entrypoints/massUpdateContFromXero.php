<?php

/********************************

Mass update Suite Contacts in List view from Xero

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

	// load the account records array from list view
	$UIDs = $_REQUEST['uid'];
	$IDArr = explode(',', $UIDs);
	$GLOBALS['log']->debug('IDArr');
	$GLOBALS['log']->debug($IDArr);
	$ContactsSelected = count($IDArr);
	$ContactsUpdated = 0;
	$ContactsNoXeroID = 0;

	foreach ($IDArr as $Id) {
		$contact_id = $Id;
		$contactobj = BeanFactory::getBean('Contacts', $contact_id);
		$xero_id = $contactobj->xero_id_c;
		if (!isset($xero_id) || strlen($xero_id) != 36) {
			$ContactsNoXeroID = $ContactsNoXeroID + 1;
			continue;
		}

		// get the account record from Xero
		$response = $XeroOAuth->request('GET', $XeroOAuth->url('Contacts', 'core'), array('page' => 0, 'Where' => 'ContactID=GUID("' . $xero_id . '")'));

		if ($XeroOAuth->response['code'] == 200) {
			$Contactr = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			if (isset($Contactr->Contacts)) {
				foreach ($Contactr->Contacts->Contact as $Contact) {
					// save Xero data to the contact record
					$contactobj->first_name = $Contact->FirstName;
					$contactobj->last_name = $Contact->LastName;
					$contactobj->email = $Contact->EmailAddress;
					$contactobj->phone_work = $Contact->Phones->Phone[1]->PhoneNumber;
					$contactobj->phone_fax = $Contact->Phones->Phone[2]->PhoneFax;
					$contactobj->phone_mobile = $Contact->Phones->Phone[3]->PhoneNumber;
					$contactobj->xero_id_c = $Contact->ContactID;
					$contactobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $Contact->ContactID;

					$contactobj->primary_address_street = $Contact->Addresses->Address[0]->AddressLine1;
					$contactobj->primary_address_city = $Contact->Addresses->Address[0]->City;
					$contactobj->primary_address_state = $Contact->Addresses->Address[0]->Region;
					$contactobj->primary_address_postalcode = $Contact->Addresses->Address[0]->PostalCode;
					$contactobj->primary_address_country = $Contact->Addresses->Address[0]->Country;

					$contactobj->alt_address_street = $Contact->Addresses->Address[1]->AddressLine1;
					$contactobj->alt_address_city = $Contact->Addresses->Address[1]->City;
					$contactobj->alt_address_state = $Contact->Addresses->Address[1]->Region;
					$contactobj->alt_address_postalcode = $Contact->Addresses->Address[1]->PostalCode;
					$contactobj->alt_address_country = $Contact->Addresses->Address[1]->Country;
				}
			}
			$ContactsUpdated = $ContactsUpdated + 1;
			$contactobj->save();
		} else {
			// there has been an error in the process, log to file and set JS error
			// echo ("<SCRIPT LANGUAGE='JavaScript'>
			// 					window.alert('There was a problem with connecting to Xero.\\nCheck the log file for details')
			// 					window.location.href='index.php?module=Contacts&action=ListView';
			// 					</SCRIPT>");
		}
	}
	$AccountsNoXeroID = $AccountsSelected - $AccountsUpdated;
	$msg = "Of the " . $ContactsSelected . " records selected " . $ContactsUpdated . " was/were updated successfully\\n" . $ContactsNoXeroID . " had NO Xero ID in Suite and were NOT updated";

	if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
		$this->writeLogger($msg);
		$this->alertMsg[] = $msg;
	} else {
		echo ("<SCRIPT LANGUAGE='JavaScript'>
								window.alert('" . $msg . "')
								window.location.href='index.php?module=Contacts&action=ListView';
								</SCRIPT>");
	}
}
