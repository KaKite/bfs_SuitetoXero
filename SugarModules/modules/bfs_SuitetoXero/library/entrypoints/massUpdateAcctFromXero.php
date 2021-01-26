<?php

/********************************

Mass update Suite Accounts in List view from Xero

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
	$AccountsSelected = count($IDArr);
	$AccountsUpdated = 0;
	$AccountsNoXeroID = 0;

	foreach ($IDArr as $Id) {
		$account_id = $Id;
		$accountobj = BeanFactory::getBean('Accounts', $account_id);
		$xero_id = $accountobj->xero_id_c;
		if (!isset($xero_id) || strlen($xero_id) != 36) {
			$AccountsNoXeroID = $AccountsNoXeroID + 1;
			continue;
		}

		// get the account record from Xero
		$response = $XeroOAuth->request('GET', $XeroOAuth->url('Contacts', 'core'), array('page' => 0, 'Where' => 'ContactID=GUID("' . $xero_id . '")'));

		if ($XeroOAuth->response['code'] == 200) {
			$Account = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			if (isset($Account->Contacts)) {
				foreach ($Account->Contacts->Contact as $Contact) {
					// save Xero data to the contact record
					$accountobj->name = $Contact->Name;
					$accountobj->email = $Contact->EmailAddress;
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
					$AccountsUpdated = $AccountsUpdated + 1;
				}
			}
			$accountobj->save();
		} else {
			// there has been an error in the process, log to file and set JS error
			echo ("<SCRIPT LANGUAGE='JavaScript'>
								window.alert('There was a problem with connecting to Xero.\\nCheck the log file for details')
								window.location.href='index.php?module=Accounts&action=ListView';
								</SCRIPT>");
		}
	}
	$AccountsNoXeroID = $AccountsSelected - $AccountsUpdated;
	$msg = "Of the $AccountsSelected records selected $AccountsUpdated was/were updated successfully\\n$AccountsNoXeroID had NO Xero ID in Suite and were NOT updated";

	if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
		$this->writeLogger($msg);
		$this->alertMsg[] = $msg;
	} else {
		echo ("<SCRIPT LANGUAGE='JavaScript'>
								window.alert('" . $msg . "')
								window.location.href='index.php?module=Accounts&action=ListView';
								</SCRIPT>");
	}
}
