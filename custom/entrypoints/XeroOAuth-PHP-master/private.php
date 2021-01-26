<?php
// license validation from SuiteCRM store
require_once('modules/bfs_SuitetoXero/license/OutfittersLicense.php');
$validate_license = OutfittersLicense::isValid('bfs_SuitetoXero');
if ($validate_license !== true) {
	if (is_admin($current_user)) {
		SugarApplication::appendErrorMessage('Your Suite to Xero License is no longer active due to the following reason: ' . $validate_license . ' Users will have limited to no access until the issue has been addressed.');
	}
	echo ("<SCRIPT LANGUAGE='JavaScript'>
				window.alert('Your Suite to Xero license is no longer active. Please either renew your license subscription or check your license configuration')
			window.location.href='index.php?module=bfs_SuitetoXero&action=license';
				</SCRIPT>");
	//functionality may be altered here in response to the key failing to validate
	exit;
}

require 'lib/XeroOAuth.php';

define('BASE_PATH', dirname(__FILE__));
global $db;
// Check for the existence of the Xero credentials
$query_check_creds = "SELECT * FROM bfs_SuitetoXero WHERE bfs_SuitetoXero.deleted = 0 order by date_entered desc limit 1";
$result_creds = $db->query($query_check_creds, false);
if (($row_creds = $db->fetchByAssoc($result_creds)) != null) {
	// assign values stored in the custom module
	$useragent = $row_creds['name'];
	$con_key = $row_creds['consumer_key'];
	$con_secret = $row_creds['consumer_secret'];
	$redirect_url = $row_creds['redirect_url']; // added for Oauth2.0
	$refresh_token = $row_creds['refresh_token']; // added for Oauth2.0
	$tenantId = $row_creds['tenant_id']; // added for Oauth2.0

	if (empty($refresh_token) || empty($tenantId)) { // added for Oauth2.0
		// message, no authenticatation refresh code or no tenant found, redirect to Config page
		echo ("<SCRIPT LANGUAGE='JavaScript'>
	window.alert('NO Xero connection details have been located, please Connect to Xero BEFORE attempting to send records to Xero.')
	window.location.href='index.php?module=bfs_SuitetoXero&action=index';
	</SCRIPT>");
		exit;
	}
} else {
	// message, no credentials found, redirect to Config page
	echo ("<SCRIPT LANGUAGE='JavaScript'>
							window.alert('NO Xero credentials have been located, please create them BEFORE attempting to send records to Xero.')
							window.location.href='index.php?module=bfs_SuitetoXero&action=index';
							</SCRIPT>");
	exit;
}

/*
@ added for Oauth2.0 
@ included testRunner here and retrieveSession
*/
include_once 'tests/testRunner.php';
$oauthSession = retrieveSession();

$signatures = array(
	'consumer_key' => $con_key,
	'access_token' => ($oauthSession && !empty($oauthSession['oauth_token'])) ? $oauthSession['oauth_token'] : '', // added for Oauth2.0 
	'shared_secret' => $con_secret,
	// API versions
	'core_version' => '2.0',
	'payroll_version' => '1.0',
	'file_version' => '1.0',
	'tenantId' => $tenantId, //added for Oauth2.0 
	'refresh_token' => $refresh_token, //added for Oauth2.0 
);

$XeroOAuth = new XeroOAuth($signatures); // added for Oauth2.0

if (!$oauthSession || !isset($_COOKIE['xero_token_expire'])) {
	$XeroOAuth->requestToken($redirect_url, null, true);
	$refresh_token = $XeroOAuth->response['response']['refresh_token'];
	// echo $refresh_token; die;

	if (empty($refresh_token)) {
		echo ("<SCRIPT LANGUAGE='JavaScript'>
							window.alert('Some error has been occured. Please try again.')
							window.history.back();
							</SCRIPT>");
		exit;
	}

	// added for Oauth2.0 set cookie for check token expiry
	setcookie("xero_token_expire", $XeroOAuth->response['response']['expires_in'], (time() + ($XeroOAuth->response['response']['expires_in'] - 5)), '/');

	$update_creds = "UPDATE bfs_SuitetoXero set refresh_token = '$refresh_token' WHERE bfs_SuitetoXero.deleted = 0 order by date_entered desc limit 1";
	$db->query($update_creds, false);

	$session = persistSession(array(
		'oauth_token' => $XeroOAuth->response['response']['access_token'],
		'tenantId' => $tenantId,
		'oauth_token_secret' => $con_secret,
		'oauth_session_handle' => $refresh_token
	));
	$oauthSession = retrieveSession();
}

if (isset($oauthSession['oauth_token']) && isset($oauthSession['tenantId'])) { // added for Oauth2.0

	$XeroOAuth->config['access_token'] = $oauthSession['oauth_token'];
	$XeroOAuth->config['tenantId'] = $oauthSession['tenantId']; // added for Oauth2.0
	$XeroOAuth->config['refresh_token'] = $oauthSession['oauth_session_handle']; // added for Oauth2.0
	$XeroOAuth->config['access_token_secret'] = $oauthSession['oauth_token_secret'];

	//Available functions/added on for the Xero Connector
	/******** ACCOUNTS and CONTACTS FUNCTIONS  *************/
	// send an account to Xero
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "sendAcctToXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/sendAccounttoXero.php';
	}
	// update an account to xero
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "updAcctToXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/updateXero.php';
	}
	// update an account from Xero
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "updateAcctFromXero" && $_REQUEST['module'] == "Accounts") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/updateAcctFromXero.php';
	}
	// mass update accounts from Xero
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "massUpdAcctFromXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/massUpdateAcctFromXero.php';
	}
	// send multiple accounts from list view to Xero
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "massSendAcctToXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/massSendToXero.php';
	}

	/******** CONTACTS FUNCTIONS  *************/
	// send a contact to Xero
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "sendContToXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/sendContacttoXero.php';
	}
	// update a contact to Xero	
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "updateContToXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/updateXero.php';
	}
	// update a contact from Xero
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "updateContFromXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/updateContactFromXero.php';
	}
	// mass update contacts from Xero
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "massUpdContFromXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/massUpdateContFromXero.php';
	}
	// create multiple contacts from list view
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "massSendContToXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/massSendContactToXero.php';
	}

	/******** INVOICE FUNCTIONS  *************/
	// create an invoice from a quote
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "sendQuoToXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/invFromQuote.php';
	}
	// create an invoice from an invoice
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "sendInvToXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/invFromInvoice.php';
	}
	// update an invoice in Xero using the details in the Suite invoice listing
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "updateInvToXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/UpdateInvToXero.php';
	}
	// update an invoice in Suite using the details from the matching Xero invoice
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "updateInvFromXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/UpdateInvFromXero.php';
	}
	// get Invoices from detail view of Accounts or Contacts
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "getInvFromXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/getXeroInvoices.php';
	}
	// multiple invoices from accounts list view
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "massGetInvFromXero" && $_REQUEST['module'] == "Accounts") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/massGetXeroInvoices.php';
	}
	// multiple invoices from contacts list view
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "massGetInvFromXero" && $_REQUEST['module'] == "Contacts") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/massGetContactXeroInvoices.php';
	}
	// update multiple invoices from invoice list view, details come FROM Xero
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "massUpdInvFromXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/massUpdateInvFromXero.php';
	}
	// update multiple invoices from invoice list view, details go TO Xero
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "massUpdInvToXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/massUpdateInvToXero.php';
	}
	// create multiple invoices in Xero from invoice list view, details go TO Xero
	if (isset($_REQUEST['func']) && $_REQUEST['func'] == "massSendInvToXero") {
		include_once 'modules/bfs_SuitetoXero/library/entrypoints/massInvoicesToXero.php';
	}
}
