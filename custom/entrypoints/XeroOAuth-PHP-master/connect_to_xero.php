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
	$redirect_url = $row_creds['redirect_url'];

	if (empty($con_key) || empty($con_secret)) {
		// message, no Consumer Key OR Consumer Secret found.
		echo ("<SCRIPT LANGUAGE='JavaScript'>
	window.alert('NO Xero connection details have been located, please Connect to Xero BEFORE attempting to create records.')
	window.location.href='index.php?module=bfs_SuitetoXero&action=index';
	</SCRIPT>");
		exit;
	}

	if (empty($redirect_url)) {
		echo ("<SCRIPT LANGUAGE='JavaScript'>
	window.alert('NO Redirect URL has been located, please update your redirect url to match that entered in your Xero App.')
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

// remove connection
if ($_REQUEST && isset($_REQUEST['type']) && $_REQUEST['type'] == 'remove') {
	$update_creds = "UPDATE bfs_SuitetoXero set refresh_token = '', tenant_id='' WHERE bfs_SuitetoXero.deleted = 0 order by date_entered desc limit 1";
	$db->query($update_creds, false);
	unset($_SESSION['access_token']);
	echo ("<SCRIPT LANGUAGE='JavaScript'>
		window.alert('Xero app removed successfully.')
		window.location.href='index.php?module=bfs_SuitetoXero&action=index';
		</SCRIPT>");
}

if ($_REQUEST && isset($_REQUEST['type']) && $_REQUEST['type'] == 'connect') {
	$state = uniqid();
	$url = "https://login.xero.com/identity/connect/authorize?response_type=code&client_id=$con_key&redirect_uri=$redirect_url&scope=openid email profile assets projects accounting.settings accounting.transactions accounting.contacts accounting.journals.read accounting.reports.read accounting.attachments offline_access&state=$state";
	header("Location: $url");
}

require 'lib/XeroOAuth.php';
$signatures = array(
	'consumer_key' => $con_key,
	'shared_secret' => $con_secret,
	// API versions
	'core_version' => '2.0',
	'payroll_version' => '1.0',
	'file_version' => '1.0',
);
$XeroOAuth = new XeroOAuth($signatures);

/* added for Oauth2.0 
@ when callback hit from xero witch access_token
@ below code will fetch other info. and set as required
*/
if ($_REQUEST && isset($_REQUEST['entryPoint']) && $_REQUEST['entryPoint'] == 'XeroCallBack' && isset($_REQUEST['code'])) {
	// fetch token
	$response = $XeroOAuth->requestToken($redirect_url, $_REQUEST['code']);

	if ($response != 200) {
		echo ("<SCRIPT LANGUAGE='JavaScript'>
							window.alert('An error has occured in fetching your Xero token, Please try again.')
							window.location.href='index.php?module=bfs_SuitetoXero&action=index';
							</SCRIPT>");
	}
	$XeroOAuth->config['access_token'] = $XeroOAuth->response['response']['access_token'];
	$XeroOAuth->config['access_token_secret'] = $con_secret;
	$access_token = $XeroOAuth->response['response']['access_token'];
	$oauth_session_handle = $XeroOAuth->response['response']['refresh_token'];

	// fetch tenants
	$XeroOAuth->request('GET', $XeroOAuth->url('connections', ''), [], '', 'json');

	if ($XeroOAuth->response['code'] != 200) {
		echo ("<SCRIPT LANGUAGE='JavaScript'>
							window.alert('An error has occured in fetching Xero tenants/Organizations, Please try again.')
							window.location.href='index.php?module=bfs_SuitetoXero&action=index';
							</SCRIPT>");
	}

	$tenants = (array) (json_decode($XeroOAuth->response['response']));
	$tenants = (array) ($tenants[0]);

	$update_creds = "UPDATE bfs_SuitetoXero set refresh_token = '$oauth_session_handle', tenant_id='" . $tenants['tenantId'] . "' WHERE bfs_SuitetoXero.deleted = 0 order by date_entered desc limit 1";
	$result_creds = $db->query($update_creds, false);
	setcookie("xero_token_expire", "", time() - 3600, '/');

	echo ("<SCRIPT LANGUAGE='JavaScript'>
		window.alert('Xero app connected successfully.')
		window.location.href='index.php?module=bfs_SuitetoXero&action=index';
		</SCRIPT>");
}
exit;
