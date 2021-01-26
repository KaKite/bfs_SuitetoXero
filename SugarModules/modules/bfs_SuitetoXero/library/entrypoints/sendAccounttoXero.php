<?php

/********************************

Create an Account in Xero from an Account in Suite

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


	// load the account record
	$account_id = $_REQUEST['accountID'];

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
	if (count($contacts) == 1) {
		$contact = $contacts[0];
		// echo "<pre>"; print_r($contact); die;
		$xmlchild .= " <FirstName>" . $contact->first_name . "</FirstName>
				<LastName>" . $contact->last_name . "</LastName>
				<EmailAddress>" . $contact->email1 . "</EmailAddress>";
	} else if (count($contacts) > 1) {
		$synchTrueContacts = $accountobj->get_linked_beans('contacts', 'Contact', 'last_name ASC,first_name ASC', 0, -1, 0, 'xero_synch_c=1');
		if (count($synchTrueContacts) == 1) {
			$synchTrueContact = $synchTrueContacts[0];

			$xmlchild .= " <FirstName>" . $synchTrueContact->first_name . "</FirstName>
					<LastName>" . $synchTrueContact->last_name . "</LastName>
					<EmailAddress>" . $synchTrueContact->email1 . "</EmailAddress>";
		} else {
			$synchTrueContact = $contacts[0];

			$xmlchild .= " <FirstName>" . $synchTrueContact->first_name . "</FirstName>
					<LastName>" . $synchTrueContact->last_name . "</LastName>
					<EmailAddress>" . $synchTrueContact->email1 . "</EmailAddress>";
		}
		$xmlchild .= "<ContactPersons>";
		foreach ($contacts as $contact) {
			// if ($i == 1) {
			// 	$xmlchild .= " <FirstName>" . $contact->first_name . "</FirstName>
			// 	<LastName>" . $contact->last_name . "</LastName>
			// 	<EmailAddress>" . $contact->email1 . "</EmailAddress>";
			// } else {
			// if ($i == 2) {

			// }
			if ($synchTrueContact->first_name == $contact->first_name && $synchTrueContact->last_name == $contact->last_name) {
				continue;
			}
			$xmlchild .= "<ContactPerson>
								 <FirstName>" . $contact->first_name . "</FirstName>
								<LastName>" . $contact->last_name . "</LastName>
								<EmailAddress>" . $contact->email1 . "</EmailAddress>
							</ContactPerson>
									";
			// }
			$i++;
			if ($i >= 6)
				break;
		}
		// if ($i > 2) {
		$xmlchild .= "</ContactPersons>";
		// }
	}
	// echo print_r($xmlchild);exit;
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

	global $timedate;
	$CurrenrDateTime = $timedate->getInstance()->nowDb();
	// check for duplicate Contact in Xero
	$response = $XeroOAuth->request('GET', $XeroOAuth->url('Contacts', 'core'), array('page' => 0, 'Where' => 'Name="' . $account_name . '"'));
	if ($XeroOAuth->response['code'] == 200) {
		$duplicatecontact = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
		$XeroContactobj = $duplicatecontact->Contacts[0]->Contact;
		if (count($duplicatecontact->Contacts[0] > 0) && ($XeroContactobj->ContactID != '')) {

			if (isset($_REQUEST['hook_called']) && $_REQUEST['hook_called']) {
				$fieldsToSave = [
					'id_c' => $account_id,
					'xero_id_c' => $XeroContactobj->ContactID,
					'xero_link_c' => "https://go.xero.com/Contacts/View/" . $XeroContactobj->ContactID,
					'dtime_synched_c' => $CurrenrDateTime
				];
				$this->updateCstmTable('accounts_cstm', $fieldsToSave); // on creating facing issue on re save fields
			} else {
				$accountobj->xero_id_c = $XeroContactobj->ContactID;
				$accountobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactobj->ContactID;
				$accountobj->dtime_synched_c = $CurrenrDateTime;
				$accountobj->save();
			}
			foreach ($contacts as $contact) {
				$contact->xero_id_c = $XeroContactobj->ContactID;
				$contact->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactobj->ContactID;
				$contact->save();
			}

			$queryParams = array(
				'module' => 'Accounts',
				'action' => 'DetailView',
				'record' => $account_id,
			);

			if (isset($_REQUEST['hook_called']) && $_REQUEST['hook_called']) {
				$this->writeLogger('A contact with this name ALREADY exists in Xero.\\nDuplicate names are NOT allowed.\\nThe Xero ID and Xero Link for this\\ncontact have been updated');
			} else {
				echo ("<SCRIPT LANGUAGE='JavaScript'>
							window.alert('A contact with this name ALREADY exists in Xero.\\nDuplicate names are NOT allowed.\\nThe Xero ID and Xero Link for this\\ncontact have been updated')
							window.location.href='index.php?module=Accounts&action=DetailView&record=" . $account_id . "';
							</SCRIPT>");
			}
		} else {
			// create a new Account in Xero		  

			$response = $XeroOAuth->request('PUT', $XeroOAuth->url('Contacts', 'core'), array(), $xml);

			if ($XeroOAuth->response['code'] == 200) {
				$contact = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
				if (count($contact->Contacts[0]) > 0) {
					$ContactObj = $contact->Contacts[0]->Contact;
					$XeroContactID = $ContactObj->ContactID;

					if (isset($_REQUEST['hook_called']) && $_REQUEST['hook_called']) {
						$fieldsToSave = [
							'id_c' => $account_id,
							'xero_id_c' => $XeroContactID,
							'xero_link_c' => "https://go.xero.com/Contacts/View/" . $XeroContactID,
							'dtime_synched_c' => $CurrenrDateTime
						];
						$this->updateCstmTable('accounts_cstm', $fieldsToSave); // on creating facing issue on record save fields
					} else {
						$accountobj->xero_id_c = $XeroContactID;
						$accountobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactID;
						$accountobj->dtime_synched_c = $CurrenrDateTime;
						$accountobj->save();
					}
					// foreach ($contacts as $contact) {
					// 	$contact->xero_id_c = $XeroContactID;
					// 	$contact->dtime_synched_c = $CurrenrDateTime;
					// 	$contact->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactID;
					// 	$contact->save();
					// }

					$queryParams = array(
						'module' => 'Accounts',
						'action' => 'DetailView',
						'record' => $account_id,
					);
					if (isset($_REQUEST['hook_called']) && $_REQUEST['hook_called']) {
						$this->writeLogger('The account has been successfully added to Xero.');
					} else {
						echo ("<SCRIPT LANGUAGE='JavaScript'>
								window.alert('The account has been successfully added to Xero.')
								window.location.href='index.php?module=Accounts&action=DetailView&record=" . $account_id . "';
								</SCRIPT>");
					}
				}
			} else {
				if ($XeroOAuth->response['code'] == 400) {
					if (isset($_REQUEST['hook_called']) && $_REQUEST['hook_called']) {
						$this->writeLogger("Error " . $XeroOAuth->response['code'] . " The contact person can not updated");
					} else {
						echo ("<SCRIPT LANGUAGE='JavaScript'>
								window.alert('Error " . $XeroOAuth->response['code'] . " The contact person can not updated')
								window.location.href='index.php?module=Accounts&action=DetailView&record=" . $account_id . "';
								</SCRIPT>");
					}
				}
			}
		}
	}
}
