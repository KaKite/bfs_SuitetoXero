<?php

/********************************

Update the related Contact or Account record in Xero

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
	// Account Update - load the account record
	if ($_REQUEST['module'] == 'Accounts' &&  $_REQUEST['xeroID'] != '') {
		$xeroID = $_REQUEST['xeroID'];
		$account_id = $_REQUEST['accountID'];
		$GLOBALS['log']->debug('xeroID: ' . $xeroID);
		$GLOBALS['log']->debug('account_id: ' . $account_id);
		$accountobj = BeanFactory::getBean('Accounts', $account_id);

		// load variables for Xero
		$account_name = htmlentities($accountobj->name, ENT_QUOTES, 'UTF-8');
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
		$website = $accountobj->website;

		// get Contact Details to check prefered Contact Exist OR Not
		$response = $XeroOAuth->request('GET', $XeroOAuth->url('Contacts', 'core') . '/' . $xeroID);
		if ($XeroOAuth->response['code'] == 200) {
			$fetchedContacts = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
		} else {
			if ((isset($_REQUEST['CRON']) && $_REQUEST['CRON']) || (isset($_REQUEST['hook_called']) && $_REQUEST['hook_called'])) {
				$this->writeLogger('Error on Updating Account: ' . $account_name);
				$syncFailur[] = $account_name;
			} else {
				echo ("<SCRIPT LANGUAGE='JavaScript'>
						window.alert('Some Error has been occured. Please try again.');
						window.location.href='index.php?module=Contacts&action=DetailView&record=" . $accountobj->id . "';
						</SCRIPT>");
			}
		}

		$contacts = $accountobj->get_linked_beans('contacts', 'Contact', 'last_name ASC,first_name ASC');
		$i = 1;
		$xmlchild = '';
		if (count($contacts) == 1) {
			$contact = $contacts[0];

			if ((string) $fetchedContacts->Contacts->Contact->FirstName == '' && (string) $fetchedContacts->Contacts->Contact->LastName == '' && !empty($contact->email1)) {
				$xmlchild .= " <FirstName>" . $contact->first_name . "</FirstName>
					<LastName>" . $contact->last_name . "</LastName>
					<EmailAddress>" . $contact->email1 . "</EmailAddress>";
			}
		} else if (count($contacts) > 1) {
			foreach ($contacts as $contact) {
				if ($i == 1 && (string) $fetchedContacts->Contacts->Contact->FirstName == '' && (string) $fetchedContacts->Contacts->Contact->LastName == '' && !empty($contact->email1)) {
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
		}
		$xml = "<Contacts>
			 <Contact>
				<ContactID>" . $xeroID . "</ContactID>
			   <Name>" . $account_name . "</Name>
			   <AccountNumber>" . $accountobj->id . "</AccountNumber>
			   <Website>" . $website . "</Website>
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

		if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
			$this->writeLogger($xml);
		} else {
			writeLogger($xml);
		}
		$response = $XeroOAuth->request('POST', $XeroOAuth->url('Contacts', 'core'), array(), $xml);
		global $timedate;
		$CurrenrDateTime = $timedate->getInstance()->nowDb();

		if ($XeroOAuth->response['code'] == 200) {
			$contact = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);

			if (count($contact->Contacts[0]) > 0) {
				$ContactObj = $contact->Contacts[0]->Contact;
				$XeroContactID = $ContactObj->ContactID;
				$accountobj->xero_id_c = $XeroContactID;
				$accountobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactID;
				$accountobj->dtime_synched_c = $CurrenrDateTime;
				$GLOBALS['log']->debug('hook_called' . $_REQUEST['hook_called']);
				$accountobj->save();

				// commented by Rupendra ass don't found $contacts var 
				// foreach ($contacts as $contact) {
				// 	$contact->xero_id_c = $XeroContactID;
				// 	$contact->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactID;
				// 	$contact->dtime_synched_c = $CurrenrDateTime;
				// 	$contact->save();
				// }

				$queryParams = array(
					'module' => 'Accounts',
					'action' => 'DetailView',
					'record' => $account_id,
				);
				if ((isset($_REQUEST['CRON']) && $_REQUEST['CRON']) || (isset($_REQUEST['hook_called']) && $_REQUEST['hook_called'])) {
					$this->writeLogger('Account "' . $account_name . '" details have been successfully updated to Xero.');
					$syncSuccess[] = $account_name;
				} else {
					echo ("<SCRIPT LANGUAGE='JavaScript'>
					window.alert('Account details have been successfully updated to Xero.')
					window.location.href='index.php?module=Accounts&action=DetailView&record=" . $account_id . "';
					</SCRIPT>");
				}
			}
		} else {

			$Error = (array) $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			if ((isset($_REQUEST['CRON']) && $_REQUEST['CRON']) || (isset($_REQUEST['hook_called']) && $_REQUEST['hook_called'])) {
				$this->writeLogger('Error on Updating Account: ' . $account_name);
				$this->writeLogger($Error);
				$syncFailur[] = $account_name;
			} else {
				outputError($XeroOAuth);
			}
		}
	}
	//Account Update Ends Here

	//Contact Update - this has NO account attached to it in CRM

	if ($_REQUEST['module'] == 'Contacts' &&  $_REQUEST['xeroID'] != '') {
		// load the contact record
		$xeroID = $_REQUEST['xeroID'];
		$contact_id = $_REQUEST['contactID'];
		$contactobj = BeanFactory::getBean('Contacts', $contact_id);

		// contact details to send to Xero
		$contact_name = htmlentities(($contactobj->first_name . ' ' . $contactobj->last_name), ENT_QUOTES, 'UTF-8');
		$contact_email = $contactobj->email1;
		$account_phone = $contactobj->phone_work;
		$phone_fax = $contactobj->phone_fax;
		$phone_mobile = $contactobj->phone_mobile;

		// use the primary address details in both sections of Xero if NOT in Suite
		if ($contactobj->alt_address_street != '') {
			$shipping_account_street = $contactobj->alt_address_street;
			$shipping_account_city = $contactobj->alt_address_city;
			$shipping_account_state = $contactobj->alt_address_state;
			$shipping_account_postalcode = $contactobj->alt_address_postalcode;
			$shipping_account_country = $contactobj->alt_address_country;

			$account_street = $contactobj->primary_address_street;
			$account_city = $contactobj->primary_address_city;
			$account_state = $contactobj->primary_address_state;
			$account_postalcode = $contactobj->primary_address_postalcode;
			$account_country = $contactobj->primary_address_country;
		} else {
			$account_street = $contactobj->primary_address_street;
			$account_city = $contactobj->primary_address_city;
			$account_state = $contactobj->primary_address_state;
			$account_postalcode = $contactobj->primary_address_postalcode;
			$account_country = $contactobj->primary_address_country;

			$shipping_account_street = $contactobj->primary_address_street;
			$shipping_account_city = $contactobj->primary_address_city;
			$shipping_account_state = $contactobj->primary_address_state;
			$shipping_account_postalcode = $contactobj->primary_address_postalcode;
			$shipping_account_country = $contactobj->primary_address_country;
		}
		$response = $XeroOAuth->request('GET', $XeroOAuth->url('Contacts', 'core'), array('page' => 0, 'Where' => 'ContactID=GUID("' . $contactobj->xero_id_c . '")'));
		if ($XeroOAuth->response['code'] == 200) {
			$contacts = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
			"There are " . count($contacts->Contacts) . " contacts in this Xero organisation, the first one is: </br>";
			$c = count($contacts->Contacts[0]->Contact->ContactPersons->ContactPerson);
			$xml = "<Contacts>
			 <Contact>
				<ContactID>" . $xeroID . "</ContactID>
			   <Name>" . $contact_name . "</Name>
			   <FirstName>" . $contactobj->first_name . "</FirstName>
				<LastName>" . $contactobj->last_name . "</LastName>
				<EmailAddress>" . $contact->email1 . "</EmailAddress>
				 <Addresses>
				   <Address>
					 <AddressType>POBOX</AddressType>
					 <AddressLine1>" . $shipping_account_street . "</AddressLine1>
					 <City>" . $shipping_account_city . "</City>
					 <Country>" . $shipping_account_country . "</Country>
					 <Region>" . $shipping_account_state . "</Region>
					 <PostalCode>" . $shipping_account_postalcode . "</PostalCode>
					 <AttentionTo>" . $contact_name . "</AttentionTo>
				   </Address>
				   <Address>
					 <AddressType>STREET</AddressType>
					 <AddressLine1>" . $account_street . "</AddressLine1>
					 <City>" . $account_city . "</City>
					 <Country>" . $account_country . "</Country>
					 <Region>" . $account_state . "</Region>
					 <PostalCode>" . $account_postalcode . "</PostalCode>
					 <AttentionTo>" . $contact_name . "</AttentionTo>
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
					   <Phone>
						 <PhoneType>MOBILE</PhoneType>
							<PhoneNumber>" . $phone_mobile . "</PhoneNumber>
					   </Phone>
				</Phones>
				<AccountNumber>" . $contactobj->id . "</AccountNumber>				
				";
			$xml .= "
			 </Contact>
		   </Contacts>
		   ";
			//	echo $xml;
			$response = $XeroOAuth->request('POST', $XeroOAuth->url('Contacts', 'core'), array(), $xml);

			if ($XeroOAuth->response['code'] == 200) {
				$contact = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);

				$contactobj->dtime_synched_c = $CurrenrDateTime;
				$contactobj->save();

				$queryParams = array(
					'module' => 'Contacts',
					'action' => 'DetailView',
					'record' => $contactobj->id,
				);

				if ((isset($_REQUEST['CRON']) && $_REQUEST['CRON']) || (isset($_REQUEST['hook_called']) && $_REQUEST['hook_called'])) {
					$this->writeLogger('Contact "' . $contact_name . '" details have been successfully updated to Xero.');
					$syncSuccess[] = $contact_name;
				} else {
					echo ("<SCRIPT LANGUAGE='JavaScript'>
						window.alert('Contact details have been successfully updated to Xero.')
						window.location.href='index.php?module=Contacts&action=DetailView&record=" . $contactobj->id . "';
						</SCRIPT>");
				}
			} else {
				if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
					$syncFailur[] = $contact_name;
				} else {
					outputError($XeroOAuth);
				}
			}
		} else {
			if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
				$syncFailur[] = $contact_name;
			} else {
				outputError($XeroOAuth);
			}
		}
	}

	//Contact update Ends Here         
}
