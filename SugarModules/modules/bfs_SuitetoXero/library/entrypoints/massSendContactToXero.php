<?php

/********************************

Send multiple Contacts to Xero from Suite Contacts list view

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
	$count = 0;
	$ContactUpdated = [];

	foreach ($IDArr as $Id) {
		// load the contact record
		$contact_id = $Id;
		$contactobj = BeanFactory::getBean('Contacts', $contact_id);

		// load variables for Xero
		$contact_name = $contactobj->first_name . ' ' . $contactobj->last_name;
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

		$account = false;
		if ($contactobj->load_relationship('accounts')) {
			$relatedBeans = $contactobj->accounts->getBeans();
			if (!empty($relatedBeans)) {
				reset($relatedBeans);
				$account = current($relatedBeans);
			}
		}

		if (!$account || ($account && $account->id == '')) {
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

			// check for duplicate contact
			$response = $XeroOAuth->request('GET', $XeroOAuth->url('Contacts', 'core'), array('page' => 0, 'Where' => 'Name="' . $contact_name . '"'));
			if ($XeroOAuth->response['code'] == 200) {
				$duplicatecontact = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);

				if (
					isset($duplicatecontact->Contacts) && $duplicatecontact->Contacts != null && count($duplicatecontact->Contacts[0]) > 0
					&& ($duplicatecontact->Contacts[0]->Contact->ContactID != '')
				) {
					$XeroContactobj = $duplicatecontact->Contacts[0]->Contact;
					$contactobj->xero_id_c = $XeroContactobj->ContactID;
					$contactobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactobj->ContactID;
					$contactobj->save();

					$ContactUpdated[] = $account->name;
				} else {
					//create new contact
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
						$count++;
					} else {
						//echo outputError($XeroOAuth);
						/*echo ("<SCRIPT LANGUAGE='JavaScript'>
									window.alert('A contact with this name ALREADY exists in Xero. Duplicate names are NOT allowed.\\nThe Xero ID and Xero Link in the contact record have been updated')								window.location.href='index.php?module=Contacts&action=DetailView&record=".$contactobj->id."';
									</SCRIPT>");*/
						continue;
					}
				}
			}
		} else {
			if ($account->xero_id_c != '') {
				$account->xero_id_c;
				$response = $XeroOAuth->request('GET', $XeroOAuth->url('Contacts', 'core'), array('page' => 0, 'Where' => 'ContactID=GUID("' . $account->xero_id_c . '")'));
				if ($XeroOAuth->response['code'] == 200) {
					$contacts = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
					$c = count($contacts->Contacts[0]->Contact->ContactPersons->ContactPerson);
					if ($c > 0) {
						$xml = "<Contacts>
									<Contact>
									<ContactID>" . $account->xero_id_c . "</ContactID>
									<Name>" . $account->name . "</Name>
									<AccountNumber>" . $account->id . "</AccountNumber>
									<FirstName>" . $contactobj->first_name . "</FirstName>
									<LastName>" . $contactobj->last_name . "</LastName>
									<EmailAddress>" . $contactobj->email1 . "</EmailAddress>
										<ContactPersons>";
						for ($i = 0; $i < $c; $i++) {
							$xml .= "<ContactPerson>
												<FirstName></FirstName>
												<LastName></LastName>
												<IncludeInEmails>false</IncludeInEmails>
											</ContactPerson>";
						}
						$xml .= "</ContactPersons>
									</Contact>
								</Contacts>";
					} else {

						$xml = "<Contacts>
										 <Contact>
											<ContactID>" . $account->xero_id_c . "</ContactID>
											<Name>" . $account->name . "</Name>	
											<AccountNumber>" . $account->id . "</AccountNumber>										 
											<FirstName>" . $contactobj->first_name . "</FirstName>
											<LastName>" . $contactobj->last_name . "</LastName>
											<EmailAddress>" . $contactobj->email1 . "</EmailAddress>
										</Contact>
								   </Contacts>
								   ";
					}

					$response = $XeroOAuth->request('POST', $XeroOAuth->url('Contacts', 'core'), array(), $xml);
					if ($XeroOAuth->response['code'] == 200) {
						$contact = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
						if (count($contact->Contacts[0]) > 0) {
							$ContactObj = $contact->Contacts[0]->Contact;
							$j = 1;
							$contacts = $account->get_linked_beans('contacts', 'Contact', 'last_name ASC,first_name ASC');
							foreach ($contacts as $contact) {
								if ($contact->id != $contactobj->id) {
									$contact->xero_id_c = $account->xero_id_c;
									$contact->xero_link_c = "https://go.xero.com/Contacts/View/" . $account->xero_id_c;
									$contact->save();
									$j++;
								}
							}
							$contactobj->xero_id_c = $account->xero_id_c;
							$contactobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $account->xero_id_c;
							$contactobj->save();

							$ContactUpdated[] = $account->name;
						}
					} else {
						if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
							$this->writeLogger($XeroOAuth->response['response']);
						} else {
							outputError($XeroOAuth);
						}
					}
				} else {
					if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
						$this->writeLogger($XeroOAuth->response['response']);
					} else {
						outputError($XeroOAuth);
					}
				}
			} else {
				$accountobj = BeanFactory::getBean('Accounts', $account->id);
				// load variables for Xero
				$account_name = $accountobj->name;
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
				$xmlchild .= "<ContactPersons>";
				foreach ($contacts as $contact) {
					if ($contact->id != $contactobj->id) {
						$xmlchild .= "<ContactPerson>
											 <FirstName>" . $contact->first_name . "</FirstName>
											<LastName>" . $contact->last_name . "</LastName>
											<EmailAddress>" . $contact->email1 . "</EmailAddress>
										</ContactPerson>
												";
						$i++;
						if ($i >= 6)
							break;
					}
				}
				$xmlchild .= "</ContactPersons>";
				$str = htmlentities($account_name, ENT_QUOTES, 'UTF-8');
				$xml1 = "<Contacts>
							 <Contact>
							   <Name>" . $str . "</Name>
							   <AccountNumber>" . $accountobj->id . "</AccountNumber>
								 <Addresses>
								   <Address>
									 <AddressType>POBOX</AddressType>
									 <AddressLine1>" . $account_street . "</AddressLine1>
									 <City>" . $account_city . "</City>
									 <Country>" . $account_country . "</Country>
									 <Region>" . $account_state . "</Region>
									 <PostalCode>" . $account_postalcode . "</PostalCode>
									 <AttentionTo>" . $str . "</AttentionTo>
								   </Address>
									<Address>
									 <AddressType>STREET</AddressType>
									 <AddressLine1>" . $shipping_account_street . "</AddressLine1>
									 <City>" . $shipping_account_city . "</City>
									 <Country>" . $shipping_account_country . "</Country>
									 <Region>" . $shipping_account_state . "</Region>
									 <PostalCode>" . $shipping_account_postalcode . "</PostalCode>
									 <AttentionTo>" . $str . "</AttentionTo>
								   </Address>
								 </Addresses>
								  <FirstName>" . $contactobj->first_name . "</FirstName>
								 <LastName>" . $contactobj->last_name . "</LastName>
								 <EmailAddress>" . $contact_email . "</EmailAddress>";
				//$xml1 .= $xmlchild;
				$xml1 .= "<Phones>
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
								<Website>" . $accountobj->website . "</Website>
							 </Contact>
						   </Contacts>
						   ";

				// duplicate Contact check
				$response = $XeroOAuth->request('GET', $XeroOAuth->url('Contacts', 'core'), array('page' => 0, 'Where' => 'Name="' . $str . '"'));

				global $timedate;
				$CurrenrDateTime = $timedate->getInstance()->nowDb();

				if ($XeroOAuth->response['code'] == 200) {
					$duplicatecontact = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);

					if (isset($duplicatecontact->Contacts) && count($duplicatecontact->Contacts) > 0 && ($duplicatecontact->Contacts[0]->Contact->ContactID != '')) {
						$XeroContactobj = $duplicatecontact->Contacts[0]->Contact;
						$accountobj->xero_id_c = $XeroContactobj->ContactID;
						$accountobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactobj->ContactID;
						$accountobj->save();
						if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
							$this->writeLogger('line: 361');
							$this->writeLogger($accountobj->name);
							$this->writeLogger($accountobj->id);
						}
						foreach ($contacts as $contact) {
							$contact->xero_id_c = $XeroContactobj->ContactID;
							$contact->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactobj->ContactID;
							$contact->save();
						}
						$contactobj->xero_id_c = $XeroContactobj->ContactID;
						$contactobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactobj->ContactID;
						$contactobj->save();

						$ContactUpdated[] = $contact_name;
					} else {
						$response = $XeroOAuth->request('PUT', $XeroOAuth->url('Contacts', 'core'), array(), $xml1);
						if ($XeroOAuth->response['code'] == 200) {
							$contact = $XeroOAuth->parseResponse($XeroOAuth->response['response'], $XeroOAuth->response['format']);
							if (count($contact->Contacts[0]) > 0) {
								$ContactObj = $contact->Contacts[0]->Contact;
								$XeroContactID = $ContactObj->ContactID;
								$accountobj->xero_id_c = $XeroContactID;
								$accountobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactID;
								$accountobj->dtime_synched_c = $CurrenrDateTime;
								$accountobj->save();
								$j = 1;
								foreach ($contacts as $contact) {
									if ($contact->id != $contactobj->id) {
										$contact->xero_id_c = $XeroContactID;
										$contact->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactID;
										$contact->save();
										$j++;
									}
								}
								$contactobj->xero_id_c = $XeroContactID;
								$contactobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $XeroContactID;
								$contactobj->save();
								$contactobj->dtime_synched_c = $CurrenrDateTime;
								$count++;
							}
						} else {
							//echo outputError($XeroOAuth);
							$ContactUpdated[] = $contact_name;
							continue;
						}
					} //else new creation
				} //If success response
			}
		}
	} //End Foreachloop for Contact Ids
	if (count($ContactUpdated) > 0) {
		if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
			$this->writeLogger($count . " new Contacts have been added to Xero. The following Contacts were already in Xero:\\n\\n" . implode("\n", $ContactUpdated));

			$this->alertMsg[] = $count . ' new Contacts have been added to Xero. The following Contacts were already in Xero:\n' . implode('\n', $ContactUpdated);
		} else {
			echo ("<SCRIPT LANGUAGE='JavaScript'>
		window.alert('" . $count . " new Contacts have been added to Xero. The following Contacts were already in Xero:\\n\\n" . implode("\n", $ContactUpdated) . "')
		window.location.href='index.php?module=Contacts&action=index';
		</SCRIPT>");
		}
	} else {
		if (isset($_REQUEST['CRON']) && $_REQUEST['CRON']) {
			$this->writeLogger($count . " new Contacts have been added to Xero.");
			$this->alertMsg[] = "$count new Contacts have been added to Xero.";
		} else {
			echo ("<SCRIPT LANGUAGE='JavaScript'>
		window.alert('" . $count . " new Contacts have been added to Xero.')
		window.location.href='index.php?module=Contacts&action=index';
		</SCRIPT>");
		}
	}
}
