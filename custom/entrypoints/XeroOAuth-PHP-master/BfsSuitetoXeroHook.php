<?php

class BfsSuitetoXeroHook
{
    public $config, $responseHeader, $payload;
    private $XeroOAuth;
    private $loop_time_diff = 1;

    function  __construct()
    {
    }

    /**
     *  license validation from SuiteCRM store
     * @param void
     * @return void
     */
    public function checkLicense()
    {
        require_once('modules/bfs_SuitetoXero/license/OutfittersLicense.php');
        $validate_license = OutfittersLicense::isValid('bfs_SuitetoXero');

        if ($validate_license !== true) {
            if (is_admin($current_user)) {
                $this->writeLogger('suiteToXeroScheduler ====> Your Suite to Xero License is no longer active due to the following reason: ' . $validate_license . ' Users will have limited to no access until the issue has been addressed.');
            }
            return false;
        }
        return true;
    }

    /**
     * Check for the existence of the Xero credentials
     * @param Void
     * @return Boolean
     */
    public function checkSetConfig()
    {
        global $db;
        $query_check_creds = "SELECT * FROM bfs_SuitetoXero WHERE bfs_SuitetoXero.deleted = 0 order by date_entered desc limit 1";
        $result_creds = $db->query($query_check_creds, false);
        if (($row_creds = $db->fetchByAssoc($result_creds)) != null) {

            if (empty($row_creds['refresh_token']) || empty($row_creds['tenant_id'])) {
                // message, no authenticatation refresh code or no tenant found, redirect to Config page
                $this->writeLogger('suiteToXeroScheduler ====> NO Xero connection details have been located, please Connect to Xero BEFORE attempting to send records to Xero.');
                return false;
            }

            $this->config = $row_creds; // set config variable
            return true;
        } else {
            // message, no credentials found, redirect to Config page
            $this->writeLogger('suiteToXeroScheduler ====> NO Xero credentials have been located, please create them BEFORE attempting to send records to Xero.');
            return false;
        }
    }

    /**
     * matching signature
     */
    public function checkHashSignature($payload, $signature)
    {
        $yourHash = base64_encode(hash_hmac('sha256', $payload, $this->config['webhook_key'], true));

        if ($yourHash === $signature) {
            $this->writeLogger('hash matched');
            $this->responseHeader = "HTTP/1.1 200 Ok";
        } else {
            $this->writeLogger('hash matched not');
            $this->responseHeader = "HTTP/1.1 401 Unauthorized";
        }
    }

    /**
     * call Class for write in diffrent log file
     * @param any $log
     * @return Void
     */
    public function writeLogger($log)
    {
        require_once 'BfsSuiteToXeroLogger.php';
        $myLogger = new BfsSuiteToXeroLogger();
        $myLogger->log('debug', $log);
    }

    /**
     * fetch refresh token from xero
     * @param Void
     * @return Boolean
     */
    public function getSetRefreshToken($setCookie = false)
    {
        global $db;
        $signatures = array(
            'consumer_key' => $this->config['consumer_key'],
            'access_token' => '', // added for Oauth2.0 
            'shared_secret' => $this->config['consumer_secret'],
            'access_token_secret' => $this->config['consumer_secret'],
            // API versions
            'core_version' => '2.0',
            'payroll_version' => '1.0',
            'file_version' => '1.0',
            'tenantId' => $this->config['tenant_id'], //added for Oauth2.0 
            'refresh_token' => $this->config['refresh_token'], //added for Oauth2.0 
        );

        require 'lib/XeroOAuth.php';
        $XeroOAuth = new XeroOAuth($signatures); // added for Oauth2.0
        $this->XeroOAuth = $XeroOAuth;
        $tokenCall  = true;
        if ($setCookie && isset($_COOKIE['xero_token_expire'])) {
            $tokenCall = false;
        }

        if ($tokenCall) {
            $this->XeroOAuth->requestToken($this->config['redirect_url'], null, true);
            $this->writeLogger($this->XeroOAuth->response['response']);
            $refresh_token = $this->XeroOAuth->response['response']['refresh_token'];
            if (empty($refresh_token)) {
                $this->writeLogger('suiteToXeroScheduler ====> An error has occured on fetching your Xero Refresh Token.');
                return false;
            }

            if ($setCookie) {
                // added for Oauth2.0 set cookie for check token expiry
                setcookie("xero_token_expire", $this->XeroOAuth->response['response']['expires_in'], (time() + ($this->XeroOAuth->response['response']['expires_in'] - 5)), '/');
            }

            $update_creds = "UPDATE bfs_SuitetoXero set refresh_token = '$refresh_token' WHERE bfs_SuitetoXero.deleted = 0 order by date_entered desc limit 1";
            $db->query($update_creds, false);
            $this->updateSession($this->XeroOAuth->response['response']);
        }

        $this->retrieveSession();
        return true;
    }

    /**
     * 
     */
    public function nowDb()
    {
        global $timedate;
        return $timedate->getInstance()->nowDb();
    }

    public function syncFromXero()
    {
        if (!($this->config['synch_with_xero'] == 'from_xero' || $this->config['synch_with_xero'] == 'both_ways')) { // if not suite from xero
            return true;
        }
        $this->writeLogger('Start syncFromXero');

        if (
            $this->payload['eventCategory'] == 'CONTACT' &&
            ($this->config['create_accounts'] == 1 || $this->config['synch_accounts'] == 1 || $this->config['delete_accounts'] == 1 ||
                $this->config['create_contacts'] == 1 || $this->config['synch_contacts'] == 1 || $this->config['delete_contacts'] == 1)
        ) {
            $this->writeLogger('calling --> syncXeroContact');
            $this->syncXeroContact();
        }

        if (
            $this->payload['eventCategory'] == 'INVOICE' &&
            ($this->config['create_invoices'] == 1 || $this->config['synch_invoices'] == 1 || $this->config['delete_invoices'] == 1)
        ) {
            $this->writeLogger('calling --> syncXeroInvoice');
            $this->syncXeroInvoice();
        }
        $this->writeLogger('End syncFromXero');
    }

    public function fetchXeroContactDetails()
    {
        $response = $this->XeroOAuth->request('GET', $this->payload['resourceUrl']);
        $FetchedContact = $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], $this->XeroOAuth->response['format']);

        if ($this->XeroOAuth->response['code'] == 200) {
            return $FetchedContact->Contacts->Contact;
        } else {
            $this->writeLogger('Error on fetching Conatct details:');
            $this->writeLogger($FetchedContact);
            return null;
        }
    }

    public function fetchXeroInvoiceDetails()
    {
        $response = $this->XeroOAuth->request('GET', $this->payload['resourceUrl']);
        $Invoice = $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], $this->XeroOAuth->response['format']);
        if ($this->XeroOAuth->response['code'] == 200) {
            return $Invoice->Invoices->Invoice;
        } else {
            $this->writeLogger('Error on fetching Invoice details:');
            $this->writeLogger($Invoice);
            return null;
        }
    }

    private function syncXeroContact()
    {
        $this->writeLogger('in syncXeroContact:');
        $contact = $this->fetchXeroContactDetails();
        $this->writeLogger((array) $contact);
        if (empty($contact)) {
            return false;
        }
        if ((string) $contact->ContactStatus == 'ARCHIVED') {
            if ($this->config['delete_contacts'] == 1) {
                $this->deleteXeroContact('Contacts', $contact);
            }

            if ($this->config['delete_accounts'] == 1) {
                $this->deleteXeroContact('Accounts', $contact);
            }
            return true;
        }


        if ($this->payload['eventType'] == 'CREATE' && $this->config['create_contacts'] == 1 && $this->config['create_accounts'] == 1) {
            $this->xeroContactCheckCreate($contact);
            return true;
        } elseif ($this->payload['eventType'] == 'CREATE' && $this->config['create_contacts'] == 1 && $this->config['create_accounts'] != 1) {
            $this->synchContactFromXero('CREATE', $contact);
            return true;
        } elseif ($this->payload['eventType'] == 'CREATE' && $this->config['create_contacts'] != 1 && $this->config['create_accounts'] == 1) {
            $this->synchAccountFromXero('CREATE', $contact);
            return true;
        }

        if ($this->payload['eventType'] == 'UPDATE' && $this->config['synch_contacts'] == 1) {
            $this->writeLogger('calling  synchContactFromXero:');
            $this->synchContactFromXero('UPDATE', $contact);
        }

        if ($this->payload['eventType'] == 'UPDATE' && $this->config['synch_accounts'] == 1) {
            $this->synchAccountFromXero('UPDATE', $contact);
        }
        return true;
    }

    private function syncXeroInvoice()
    {
        $invoice = $this->fetchXeroInvoiceDetails();
        if (empty($invoice)) {
            return false;
        }
        if ($this->payload['eventType'] == 'CREATE' && $this->config['create_invoices'] == 1) {
            $this->synchInvoiceRecord('CREATE', $invoice);
        }
        if ($this->payload['eventType'] == 'UPDATE' && $this->config['synch_invoices'] == 1) {
            $this->synchInvoiceRecord('UPDATE', $invoice);
        }
    }

    /**
     * @param Object $contact contact record from xero
     * @param Boolean $checkExist passes to next function 
     * @return Array from function create Account/Contact for suite 
     */
    public function xeroContactCheckCreate($contact)
    {
        if ($contact->Name == ($contact->FirstName . ' ' . $contact->LastName)) {
            return $this->synchContactFromXero('CREATE', $contact);
        } else {
            return $this->synchAccountFromXero('CREATE', $contact);
        }
    }

    /**
     * Create/update Account suite record from xero
     * @param String $type 
     * @param Object $Contact
     * @return Axrray()
     */
    public function synchAccountFromXero($type, $Contact)
    {
        $existID = '';
        global $db;
        $accountExistQuery = "SELECT id, xero_id_c from accounts 
                    INNER JOIN accounts_cstm cstm on cstm.id_c = accounts.id
                    where (name='" . $Contact->Name . "' OR cstm.xero_id_c = '" . $this->payload['resourceId'] . "') and deleted=0";
        $result = $db->query($accountExistQuery, false);

        if ($db->getRowCount($result) > 0) {
            $row = $db->fetchByAssoc($result);
            $existID = $row['id'];
        }

        if (!empty($existID) && $type == 'UPDATE') {
            $accountobj = BeanFactory::getBean('Accounts', $existID);
        } else if (!empty($existID) && $type == 'CREATE') { // this can be occrs when hook called from suite for new record
            $this->writeLogger('Xero webhook called CREATE type called for created record in suite');
            return true; // sometime xero webhook called when new created record sent to Xero 
        } else if ($type == 'CREATE') {
            $accountobj = BeanFactory::newBean('Accounts');   //Create bean  using module name 
        } else {
            return null;
        }

        $this->writeLogger('before check time in synchAccountFromXero');
        // checking last updated time
        if (
            $this->checkTimeDiff($accountobj->dtime_synched_c, $this->payload['eventDateUtc']) ||
            ($this->config['which_records'] == 'Selected_Records_ONLY' && $accountobj->xero_synch_c != 1)
        ) {
            return true;
        }



        $accountobj->name = $Contact->Name;
        // $accountobj->email = $Contact->EmailAddress;
        $accountobj->website = $Contact->Website;
        $accountobj->phone_office = $Contact->Phones->Phone[1]->PhoneNumber;
        $accountobj->phone_fax = $Contact->Phones->Phone[2]->PhoneNumber;
        $accountobj->phone_alternate = $Contact->Phones->Phone[3]->PhoneNumber;
        $accountobj->xero_id_c = $Contact->ContactID;
        $accountobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $Contact->ContactID;
        $accountobj->dtime_synched_c = $this->nowDb();

        $accountobj->billing_address_street = $Contact->Addresses->Address[0]->AddressLine1;
        $accountobj->billing_address_city = $Contact->Addresses->Address[0]->City;
        $accountobj->billing_address_state = $Contact->Addresses->Address[0]->Region;
        $accountobj->billing_address_postalcode = $Contact->Addresses->Address[0]->PostalCode;
        $accountobj->billing_address_country = $Contact->Addresses->Address[0]->Country;

        $accountobj->shipping_address_street = $Contact->Addresses->Address[1]->AddressLine1;
        $accountobj->shipping_address_city = $Contact->Addresses->Address[1]->City;
        $accountobj->shipping_address_state = $Contact->Addresses->Address[1]->Region;
        $accountobj->shipping_address_postalcode = $Contact->Addresses->Address[1]->PostalCode;
        $accountobj->shipping_address_country = $Contact->Addresses->Address[1]->Country;
        $accountobj->save();

        // save mail id
        $sea = new SugarEmailAddress;
        $sea->addAddress($Contact->EmailAddress, true);
        $sea->save($accountobj->id, "Accounts");

        $this->writeLogger($accountobj->name . ' AccountSaved successfully');

        require_once 'custom/entrypoints/XeroOAuth-PHP-master/LinkedContact.php';
        $LinkedContact = new LinkedContact();

        $LinkedContact->addLinkedContacts($Contact, $accountobj->id);
        $this->updateXeroAccountNumber(['id' => $accountobj->id, 'xeroID' => (string) $Contact->ContactID]);
        return true;
    }

    /**
     * Create/update Contact suite record from xero
     * @param String $type 
     * @param Object $Contact
     * @return Array()
     */
    public function synchContactFromXero($type, $Contact)
    {
        $existID = '';
        global $db;
        $accountExistQuery = "SELECT id, xero_id_c from contacts 
                INNER JOIN contacts_cstm cstm on cstm.id_c = contacts.id 
                where (CONCAT(first_name, ' ', last_name)='" . $Contact->Name . "' OR cstm.xero_id_c = '" . $this->payload['resourceId'] . "') and deleted=0";
        $this->writeLogger('accountExistQuery: ' . $accountExistQuery);
        $result = $db->query($accountExistQuery, false);
        if ($db->getRowCount($result) > 0) {
            $row = $db->fetchByAssoc($result);
            $existID = $row['id'];
        }

        $this->writeLogger('existID' . $existID);
        if (!empty($existID) && $type == 'UPDATE') {
            $contactobj = BeanFactory::getBean('Contacts', $existID);
        } else if (!empty($existID) && $type == 'CREATE') { // this can be occrs when hook called from suite for new record
            $this->writeLogger('Xero webhook called CREATE type called for created record in suite');
            return true; // sometime xero webhook called when new created record sent to Xero 
        } else if ($type == 'CREATE') {
            $contactobj = BeanFactory::newBean('Contacts');   //Create bean  using module name 
        } else {
            return null;
        }

        // checking last updated time
        if (
            $this->checkTimeDiff($contactobj->dtime_synched_c, $this->payload['eventDateUtc']) ||
            ($this->config['which_records'] == 'Selected_Records_ONLY' && $contactobj->xero_synch_c != 1)
        ) {
            $this->writeLogger('in checktime false');
            return true;
        }
        $this->writeLogger('in synchContactFromXero: after time check');

        $contactobj->first_name = $Contact->FirstName;
        $contactobj->last_name = $Contact->LastName;
        // $contactobj->email = $Contact->EmailAddress;
        $contactobj->phone_work = $Contact->Phones->Phone[1]->PhoneNumber;
        $contactobj->phone_fax = $Contact->Phones->Phone[2]->PhoneFax;
        $contactobj->phone_mobile = $Contact->Phones->Phone[3]->PhoneNumber;
        $contactobj->xero_id_c = $Contact->ContactID;
        $contactobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $Contact->ContactID;
        $contactobj->dtime_synched_c = $this->nowDb();

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
        $contactobj->save();

        // save mail id
        $sea = new SugarEmailAddress;
        $sea->addAddress($Contact->EmailAddress, true);
        $sea->save($contactobj->id, "Contacts");

        $this->updateXeroAccountNumber(['id' => $contactobj->id, 'xeroID' => (string) $Contact->ContactID]);
        return true;
    }

    /**
     * function used for update AccountNumber on Xero
     * @param $contacts array of created contacts
     * @return void()
     */
    public function updateXeroAccountNumber($contact)
    {
        $xml = "<Contacts>";
        $xml .= "<Contact>
                        <ContactID>" . $contact['xeroID'] . "</ContactID>
                        <AccountNumber>" . $contact['id'] . "</AccountNumber>
                    </Contact>";
        $xml .= "</Contacts>";

        $response = $this->XeroOAuth->request('POST', $this->XeroOAuth->url('Contacts', 'core'), array(), $xml);

        if ($this->XeroOAuth->response['code'] == 200) {
        } else {
            $error = $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], '');
            $this->writeLogger('Error On Update AccountNumber:');
            $this->writeLogger($error);
        }
    }

    public function synchInvoiceRecord($type, $Invoice)
    {
        $existID = '';
        global $db;
        $invoiceExistQuery = "SELECT id, xero_id_c from aos_invoices 
                INNER JOIN aos_invoices_cstm cstm on cstm.id_c = aos_invoices.id 
                where cstm.xero_id_c = '" . $this->payload['resourceId'] . "' and deleted=0";
        $result = $db->query($invoiceExistQuery, false);

        if ($db->getRowCount($result) > 0) {
            $row = $db->fetchByAssoc($result);
            $existID = $row['id'];
        }
        $this->writeLogger('in synchInvoiceRecord existID: ' . $existID);
        if (!empty($existID) && $type == 'UPDATE') {
            $Invoicebean = BeanFactory::getBean('AOS_Invoices', $existID);
        } else if (!empty($existID) && $type == 'CREATE') { // this can be occrs when hook called from suite for new record
            $this->writeLogger('Xero webhook called AOS_Invoices CREATE type called for created record in suite');
            return true; // sometime xero webhook called when new created record sent to Xero 
        } else if ($type == 'CREATE') {
            $Invoicebean = BeanFactory::newBean('AOS_Invoices');   //Create bean  using module name 
        } else {
            return null;
        }

        // checking last updated time
        if (
            $this->checkTimeDiff($Invoicebean->dtime_synched_c, $this->payload['eventDateUtc']) ||
            ($this->config['which_records'] == 'Selected_Records_ONLY' && $Invoicebean->xero_synch_c != 1)
        ) {
            return true;
        }

        if ((string) $Invoice->Status == 'DELETED') {
            $Invoicebean->deleted = 1;
            $Invoicebean->save();
            $this->writeLogger($Invoicebean->name . ' has been deleted.');
            return true;
        }

        $Date                            =            '';
        $Date                            =            $Invoice->Date;
        $DueDate                        =            '';
        if (isset($Invoice->DueDate) && $Invoice->DueDate != '')
            $DueDate                     =            $Invoice->DueDate;
        $Status                            =            '';
        $Status                            =            ucfirst(strtolower($Invoice->Status));
        $LineAmountTypes                =            '';
        $LineAmountTypes                =            $Invoice->LineAmountTypes;
        $SubTotal                        =            '';
        $SubTotal                        =            $Invoice->SubTotal;
        $TotalTax                        =            '';
        $TotalTax                        =            $Invoice->TotalTax;
        $Total                            =            '';
        $Total                            =            $Invoice->Total;
        $Type                            =            '';
        $Type                            =            $Invoice->Type;
        $ExpCode                        =            '';
        $ExpCode                        =            $Invoice->LineItems->LineItem->AccountCode;
        $XeroInvoiceID                    =            '';
        $XeroInvoiceID                    =            $Invoice->InvoiceID;
        $InvoiceNumber                    =            '';
        $InvoiceNumber                    =            $Invoice->InvoiceNumber;
        $UpdatedDateUTC                    =            '';
        $UpdatedDateUTC                    =            $Invoice->UpdatedDateUTC;
        $CurrencyCode                    =            '';
        $CurrencyCode                    =            $Invoice->CurrencyCode;
        //$AmountDue						=			$Invoice->AmountDue;
        //$AmountPaid						=			$Invoice->AmountPaid;
        //$AmountCredited					=			$Invoice->AmountCredited;
        //$CurrencyRate						=			$Invoice->CurrencyRate;
        //$HasAttachments					=			$Invoice->HasAttachments;
        /*************************************************************************************************/

        //Assign variables in Invoice Object
        /* Get the currency ID from the currencies table CurrencyCode = ISO4217 in currencies*/
        $Currency = BeanFactory::getBean('Currencies')->retrieve_by_string_fields(array('iso4217' => $CurrencyCode,));
        if ($Currency != '') {
            $currencyID = $Currency->id;
            $Invoicebean->currency_id    =            $currencyID;
        } else {
            $currencyID = '';
            $Invoicebean->currency_id    =            $currencyID;
        }
        /* end of currency iso4217 code */
        $Invoicebean->invoice_date        =            date("Y-m-d", strtotime($Date));
        if (isset($DueDate) && $DueDate != '')
            $Invoicebean->due_date            =            date("Y-m-d", strtotime($DueDate));
        $Invoicebean->status            =            $Status;
        $Invoicebean->name            =            $InvoiceNumber;
        if ($Type == 'ACCREC') {
            $Invoicebean->type_c        =            'ACCREC';
            $Invoicebean->xero_expense_codes_c =    'ACCREC_'.$ExpCode;
            $stat                        =            'AccountsReceivable';
        }
        if ($Type == 'ACCPAY') {
            $Invoicebean->type_c        =            'ACCPAY';
            $Invoicebean->xero_expense_codes_c =    'ACCPAY_'.$ExpCode;
            $stat                        =            'AccountsPayable';
        }
        $Invoicebean->total_amt            =            $SubTotal;
        $Invoicebean->subtotal_amount    =            $SubTotal;
        $Invoicebean->tax_amount        =            $TotalTax;
        $Invoicebean->total_amount        =            $Total;
        $Invoicebean->xeroutc_c            =            $UpdatedDateUTC;
        $Invoicebean->xero_id_c            =            $XeroInvoiceID;
        $Invoicebean->xero_link_c        =            "https://go.xero.com/" . $stat . "/Edit.aspx?invoiceid=" . $XeroInvoiceID;

        global $current_user;
        $Invoicebean->dtime_synched_c = $this->nowDb();
        $Invoicebean->assigned_user_id = 1; // set hard codded

        // have to update invoice contact detals (Account/Contact)
        $invoiceContactDetail = $this->getXeroCreateExistContactDetails($Invoice);
        if ($invoiceContactDetail && $invoiceContactDetail->module_name == 'Accounts') {
            $account = $invoiceContactDetail;
            $childcontacts = $account->get_linked_beans('contacts', 'Contact', 'last_name ASC,first_name ASC');
            $childContactID = '';
            foreach ($childcontacts as $childContact) {
                $childContactID = $childContact->id;
                break;
            }
            $Invoicebean->billing_account_id                =        $account->id;
            $Invoicebean->billing_contact_id                =        $childContactID;
            $Invoicebean->billing_address_street        =        $account->billing_address_street;
            $Invoicebean->billing_address_city            =        $account->billing_address_city;
            $Invoicebean->billing_address_state            =        $account->billing_address_state;
            $Invoicebean->billing_address_postalcode    =        $account->billing_address_postalcode;
            $Invoicebean->billing_address_country        =        $account->billing_address_country;
            if ($account->shipping_address_street != '') {

                $Invoicebean->shipping_address_street        =        $account->shipping_address_street;
                $Invoicebean->shipping_address_city            =        $account->shipping_address_city;
                $Invoicebean->shipping_address_state        =        $account->shipping_address_state;
                $Invoicebean->shipping_address_postalcode    =        $account->shipping_address_postalcode;
                $Invoicebean->shipping_address_country        =        $account->shipping_address_country;
            } else {

                $Invoicebean->shipping_address_street        =        $account->billing_address_street;
                $Invoicebean->shipping_address_city            =        $account->billing_address_city;
                $Invoicebean->shipping_address_state        =        $account->billing_address_state;
                $Invoicebean->shipping_address_postalcode    =        $account->billing_address_postalcode;
                $Invoicebean->shipping_address_country        =        $account->billing_address_country;
            }
        } elseif ($invoiceContactDetail && $invoiceContactDetail->module_name == 'Contacts') {
            $contact = $invoiceContactDetail;
            $Invoicebean->billing_contact_id                        =        $contact->id;
            if ($contact->alt_address_street != '') {
                $Invoicebean->shipping_address_street                 =         $contact->alt_address_street;
                $Invoicebean->shipping_address_city                 =         $contact->alt_address_city;
                $Invoicebean->shipping_address_state                 =         $contact->alt_address_state;
                $Invoicebean->shipping_address_postalcode             =         $contact->alt_address_postalcode;
                $Invoicebean->shipping_address_country                 =         $contact->alt_address_country;
            } else {
                $Invoicebean->shipping_address_street                 =         $contact->primary_address_street;
                $Invoicebean->shipping_address_city                 =         $contact->primary_address_city;
                $Invoicebean->shipping_address_state                 =         $contact->primary_address_state;
                $Invoicebean->shipping_addresst_postalcode             =         $contact->primary_address_postalcode;
                $Invoicebean->shipping_address_country                 =         $contact->primary_address_country;
            }

            $Invoicebean->billing_address_street                 =         $contact->primary_address_street;
            $Invoicebean->billing_address_city                     =         $contact->primary_address_city;
            $Invoicebean->billing_address_state                 =         $contact->primary_address_state;
            $Invoicebean->billing_address_postalcode             =         $contact->primary_address_postalcode;
            $Invoicebean->billing_address_country                 =         $contact->primary_address_country;
        }
        $Invoicebean->save();

        $this->writeLogger('saved invoice bean');
        $this->writeLogger('saving group');
        //Entry for Group
        $checkGroup = "Select * from aos_line_item_groups where name='Xero Invoices' and parent_id='" . $Invoicebean->id . "' and parent_type='AOS_Invoices' and deleted=0";
        $Group_result = $GLOBALS['db']->query($checkGroup);
        if ($GLOBALS['db']->getRowCount($Group_result) > 0) {
            $Grouoprow = $GLOBALS['db']->fetchByAssoc($Group_result);
            $updateGroup = "update aos_line_item_groups set total_amt='$SubTotal' ,tax_amount='$TotalTax',subtotal_amount='$SubTotal',total_amount='$Total',number=1  where name='Xero Invoices' and parent_id='" . $Invoicebean->id . "' and parent_type='AOS_Invoices' and deleted=0 ";
            $GLOBALS['db']->query($updateGroup);
            $groupID = $Grouoprow['id'];
        } else {
            $oid = create_guid();
            $GRSQL = "INSERT INTO aos_line_item_groups 
                (id,name,date_entered,date_modified ,total_amt,tax_amount,subtotal_amount,total_amount,parent_type,parent_id,number) VALUE ('$oid','Xero Invoices',NOW(),NOW(),'$SubTotal','$TotalTax','$SubTotal','$Total','AOS_Invoices','" . $Invoicebean->id . "','1')";
            $this->writeLogger($GRSQL);
            $GLOBALS['db']->query($GRSQL);
            $groupID = $oid;
        }
        $this->writeLogger('saving group');
        $GLOBALS['db']->query($GRSQL);
        $this->entryInvoiceItems($Invoice->LineItems->LineItem, $groupID, $Invoicebean->id);
        return $Invoicebean->name;
    }

    /**
     * @param Object XeroInvoice
     * @return Object Bean
     */
    private function getXeroCreateExistContactDetails($Invoice)
    {
        $contactId = (string) $Invoice->Contact->ContactID;
        // check Existing record;
        $account = $this->checkExistingRecord('accounts_cstm', $contactId);
        if ($account) {
            return BeanFactory::getBean('Accounts', $account);
        }

        $contact = $this->checkExistingRecord('contacts_cstm', $contactId);
        if ($contact) {
            return BeanFactory::getBean('Contacts', $contact);
        }

        // if not existing fetch from Xero
        $response = $this->XeroOAuth->request('GET', $this->XeroOAuth->url('Contacts', 'core'), array('Where' => 'ContactID=GUID("' . $contactId . '")'));

        if ($this->XeroOAuth->response['code'] == 200) {
            $FetchedContact = $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], $this->XeroOAuth->response['format']);

            // calling function it will create reords
            $createRecord = $this->xeroContactCheckCreate($FetchedContact->Contacts->Contact, false);
            $bean = BeanFactory::getBean($createRecord['module'], $createRecord['id']);
            return $bean;
        } else {
            return null;
        }
    }

    /**
     * Function used for check existing xeroId record in suite
     * @param string $table
     * @param string $XeroID
     * @return array []
     */
    public function checkExistingRecord($table, $XeroID)
    {
        global $db;
        // check Existing record;
        $checkAccountQuery = "SELECT id_c from $table where xero_id_c='$XeroID'";
        $checkAccountResult = $db->query($checkAccountQuery);
        if ($accountRow = $db->fetchByAssoc($checkAccountResult)) {
            return $accountRow['id_c'];
        }
        return false;
    }

    public function entryInvoiceItems($LineItems, $groupID, $invoiceId)
    {
        //Initialise variables
        $i = 1;
        $totalPrice        =    0.00;
        $discount        =     0.00;
        $subTotal         =     0.00;
        $TotalAmount    =    0.00;
        $DiscountRate    =    0.00;
        $UnitAmount        =    0.00;
        $TaxAmount        =    0.00;

        //********************Entry For Line items ***************/
        foreach ($LineItems as $LineItem) {
            $ItemCode             =            $LineItem->ItemCode;
            $Description        =            $LineItem->Description;
            $UnitAmount            =            $LineItem->UnitAmount;
            $TaxType            =            $LineItem->TaxType;
            $TaxAmount            =            $LineItem->TaxAmount;
            $LineAmount            =            $LineItem->LineAmount;
            $Quantity            =            $LineItem->Quantity;
            $DiscountRate        =            $LineItem->DiscountRate;
            if (!isset($DiscountRate) && $DiscountRate == '') {
                $DiscountRate = 0.00;
            }
            $LineItemID            =            $LineItem->LineItemID;
            $totalPrice            =            $totalPrice     + ($UnitAmount *  $Quantity);
            $discount            =            $discount + ($UnitAmount *  $Quantity * $DiscountRate / 100);
            $subTotal            =            $subTotal + ($totalPrice - ($UnitAmount *  $Quantity * $DiscountRate / 100));
            $TotalAmount        =            $TotalAmount + ($subTotal + $TaxAmount);
            //Check For Existing Product on the basis of product Code					
            $checkProduct = "Select * from aos_products WHERE name='$Description' ||  maincode='$ItemCode' and deleted=0";
            $checkProduct_result = $GLOBALS['db']->query($checkProduct);
            if ($GLOBALS['db']->getRowCount($checkProduct_result) > 0) {
                $productIDresult = $GLOBALS['db']->fetchByAssoc($checkProduct_result);
                $productID = $productIDresult['id'];
                $updateproduct = "UPDATE aos_products SET maincode='$ItemCode' ,part_number='$ItemCode',name='$Description' ,cost='$UnitAmount' ,price='$UnitAmount'  where id='$productID' and deleted=0";
                $GLOBALS['db']->query($updateproduct);
            } else {
                $productID = create_guid();
                $prodSQLInsert = "INSERT into aos_products (id,name,maincode,part_number,type,cost,price,date_entered,date_modified) values('$productID','$Description','$ItemCode','$ItemCode','Good','$UnitAmount','$UnitAmount',NOW(),NOW())";
                $GLOBALS['db']->query($prodSQLInsert);
            }

            $checkLineItem = "Select * from aos_products_quotes where line_item_id='$LineItemID' and deleted=0";
            $Line_Item_result = $GLOBALS['db']->query($checkLineItem);

            if ($GLOBALS['db']->getRowCount($Line_Item_result) > 0) {
                $UpLineItem = "update aos_products_quotes set name='$Description',part_number='$ItemCode',item_description='$Description',product_qty='$Quantity',
							product_cost_price='$UnitAmount',product_discount='$DiscountRate',discount='Percentage',product_list_price='$UnitAmount',product_unit_price='$UnitAmount',product_total_price='$LineAmount',product_id='$productID' where line_item_id='$LineItemID' AND deleted=0";

                $GLOBALS['db']->query($UpLineItem);
            } else {
                $aos_p_q_id = create_guid();
                $AOSSQL = "INSERT INTO aos_products_quotes(id,date_entered,date_modified,number,name,part_number,item_description,product_qty,product_cost_price,product_discount,discount,product_list_price,product_unit_price,product_total_price,product_id,group_id,parent_id,parent_type,line_item_id) 
				VALUES ('$aos_p_q_id',NOW(),NOW(),$i,'$Description','$ItemCode','$Description','$Quantity','$UnitAmount','$DiscountRate','Percentage','$UnitAmount','$UnitAmount','$LineAmount','$productID','$groupID','" . $invoiceId . "','AOS_Invoices','$LineItemID')";

                $GLOBALS['db']->query($AOSSQL);
            }
            $i++;
        } // end of Line items
        $this->writeLogger('saved line items');
    }

    public function deleteXeroContact($module, $Contact)
    {
        $contactId = (string) $Contact->ContactID;
        if ($module == 'Accounts') {
            $id = $this->checkExistingRecord('accounts_cstm', $contactId);
            $bean = BeanFactory::getBean('Accounts', $id);
        } else {
            $id = $this->checkExistingRecord('contacts_cstm', $contactId);
            $bean = BeanFactory::getBean('Contacts', $id);
        }
        $this->writeLogger('in deleteXeroContact ' . $module);
        // checking last updated time
        if (
            $this->checkTimeDiff($bean->dtime_synched_c, $this->payload['eventDateUtc']) ||
            ($this->config['which_records'] == 'Selected_Records_ONLY' && $bean->xero_synch_c != 1)
        ) {
            $this->writeLogger('in return false ' . $module);
            return true;
        }
        $this->writeLogger('Before Deleted' . $id);
        $bean->deleted = 1;
        $bean->dtime_synched_c = $this->nowDb();
        $bean->deleted_synched_c = 1;
        $bean->save();
        $this->writeLogger($bean->name . 'has been seleted');
    }

    public function syncToXero($bean, $event, $arguments)
    {

        $this->checkLicense();
        $this->checkSetConfig();
        $this->getSetRefreshToken(true); //setting up fresh token
        $this->writeLogger('Start syncToXero before condition');
        $this->writeLogger($this->config);

        if (
            ($this->config['synch_with_xero'] != 'to_xero' && $this->config['synch_with_xero'] != 'both_ways') || // if not suite to xero
            ($this->config['which_records'] == 'Selected_Records_ONLY' && $bean->xero_synch_c != 1)
        ) {
            $this->writeLogger('in false');
            return true;
        }
        $this->writeLogger('Start syncToXero');
        // set var for require files
        $XeroOAuth = $this->XeroOAuth;
        $oauthSession['oauth_token'] = $XeroOAuth->config['access_token'];
        $oauthSession['oauth_token_secret'] = $XeroOAuth->config['access_token_secret'];
        $oauthSession['oauth_session_handle'] = $XeroOAuth->config['session_handle'];
        $_REQUEST['hook_called'] = true;


        if ($bean->module_name == 'Accounts' && empty($bean->xero_id_c) && $this->config['create_accounts'] == 1) {
            // load the account record
            $_REQUEST['accountID'] = $bean->id;
            include_once 'modules/bfs_SuitetoXero/library/entrypoints/sendAccounttoXero.php';
            return true;
        }

        if ($bean->module_name == 'Accounts' && !empty($bean->xero_id_c) && $this->config['synch_accounts'] == 1) {
            // load the account record
            $_REQUEST['accountID'] = $bean->id;
            $_REQUEST['module'] = 'Accounts';
            $_REQUEST['xeroID'] = $bean->xero_id_c;
            $this->writeLogger('Accounts update');
            $this->writeLogger('before fiel call');
            include_once 'modules/bfs_SuitetoXero/library/entrypoints/updateXero.php';
            return true;
        }

        if ($bean->module_name == 'Accounts' && $bean->deleted == 1 && !empty($bean->xero_id_c) && $this->config['delete_accounts'] == 1) {
            $this->writeLogger('calling updateDeletedRecords');
            $this->updateDeletedRecords('accounts', $bean->xero_id_c);
            return true;
        }

        if ($bean->module_name == 'Contacts' && empty($bean->xero_id_c) && $this->config['create_contacts'] == 1) {
            $_REQUEST['contactID'] = $bean->id;
            $this->writeLogger('calling sendContacttoXero file');
            include_once 'modules/bfs_SuitetoXero/library/entrypoints/sendContacttoXero.php';
            return true;
        }

        if ($bean->module_name == 'Contacts' && !empty($bean->xero_id_c) && $this->config['synch_contacts'] == 1) {
            // load the account record
            $_REQUEST['contactID'] = $bean->id;
            $_REQUEST['module'] = 'Contacts';
            $_REQUEST['xeroID'] = $bean->xero_id_c;
            include_once 'modules/bfs_SuitetoXero/library/entrypoints/updateXero.php';
            return true;
        }

        if ($bean->module_name == 'Contacts' && $bean->deleted == 1 && !empty($bean->xero_id_c) && $this->config['delete_contacts'] == 1) {
            $this->updateDeletedRecords('contacts', $bean->xero_id_c);
            return true;
        }

        // for invoice module hook called from AOS_Products_Quotes, so useed Request car. 
        if ($bean->module_name == 'AOS_Invoices' && empty($bean->xero_id_c) && $this->config['create_invoices'] == 1) {
            $this->writeLogger('in create AOS_Invoices');
            $accountXeroId = $this->isXeroExist('accounts_cstm', $bean->billing_account_id);
            $this->writeLogger('bean->xero_id_c' . $bean->xero_id_c);
            $contactXeroId = $this->isXeroExist('contacts_cstm', $bean->billing_contact_id);
            if ($accountXeroId) {
                $this->writeLogger('accountXeroId' . $accountXeroId);
                $this->syncInvoiceToXero($bean, $accountXeroId);
            } else if ($contactXeroId) {
                $this->syncInvoiceToXero($bean, $contactXeroId);
            }
            $this->writeLogger('after file calln->xero_id_c' . $bean->xero_id_c);
            return true;
        }

        $this->writeLogger('bean->xero_id_c' . $bean->xero_id_c);
        // for invoice module hook called from AOS_Products_Quotes, so useed Request car.
        if ($bean->module_name == 'AOS_Invoices' && !$newInvoceCreated && !empty($bean->xero_id_c) && $this->config['synch_invoices'] == 1) {
            $this->writeLogger('in update AOS_Invoices');
            $accountXeroId = $this->isXeroExist('accounts_cstm', $bean->billing_account_id);
            // $contactId = $this->isXeroExist('contacts_cstm', $bean->billing_contact_id);
            if ($accountXeroId) {
                $_REQUEST['invID'] = $bean->id;
                $_REQUEST['accountID'] = $bean->billing_account_id;
                include_once 'modules/bfs_SuitetoXero/library/entrypoints/UpdateInvToXero.php';
            }
            return true;
        }

        if ($bean->module_name == 'AOS_Invoices' && $bean->deleted == 1 && !empty($bean->xero_id_c) && $this->config['delete_invoices'] == 1) {
            $this->updateDeletedRecords('aos_invoices', $bean->xero_id_c);
            return true;
        }
    }

    public function isXeroExist($table, $id)
    {
        global $db;
        // check Existing xero_id_c;
        $checkAccountQuery = "SELECT xero_id_c from $table where id_c='$id'";
        $this->writeLogger($checkAccountQuery);
        $checkAccountResult = $db->query($checkAccountQuery);
        if ($accountRow = $db->fetchByAssoc($checkAccountResult)) {
            $this->writeLogger($accountRow);
            return $accountRow['xero_id_c'];
        }
        return false;
    }

    public function saveLastSequence($number)
    {
        global $db;
        $update_query = "UPDATE bfs_SuitetoXero set xero_last_sequence = '$number' WHERE bfs_SuitetoXero.deleted = 0 order by date_entered desc limit 1";
        $db->query($update_query, false);
    }

    private function updateDeletedRecords($table, $xeroId)
    {
        if ($table == 'aos_invoices') {
            $xml = "<Invoices>";
            $idArray[] = $xeroId;
            $xml .= "<Invoice>
                            <InvoiceID>" . $xeroId . "</InvoiceID>
                            <Status>DELETED</Status>
                        </Invoice>";
            $xml .= "</Invoices>";
            $response = $this->XeroOAuth->request('POST', $this->XeroOAuth->url('Invoices', 'core'), array(), $xml);
        } else {
            $xml = "<Contacts>";
            $idArray[] = $xeroId;
            $xml .= "<Contact>
                            <ContactID>" . $xeroId . "</ContactID>
                            <ContactStatus>ARCHIVED</ContactStatus>
                        </Contact>";
            $xml .= "</Contacts>";
            $response = $this->XeroOAuth->request('POST', $this->XeroOAuth->url('Contacts', 'core'), array(), $xml);
        }
        if ($this->XeroOAuth->response['code'] == 200) {
            global $timedate, $db;
            $CurrenrDateTime = $timedate->getInstance()->nowDb();

            $query = "UPDATE " . $table . "_cstm set dtime_synched_c = '" . $this->nowDb . "', deleted_synched_c = 1 WHERE xero_id_c IN('" . implode("', '", $idArray) . "')";
            $result = $db->query($query, false);
        } else {
            $error = $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], '');
            $this->writeLogger('Error On delete:');
            $this->writeLogger($error);
        }
        return true;
    }

    public function checkTimeDiff($date1, $date2)
    {
        $this->writeLogger('in less date1' . $date1);
        $this->writeLogger('in less date2' . $date2);
        $date1 = strtotime($date1);
        $date2 = strtotime($date2);
        $mins = ($date2 - $date1) / 60;
        $this->writeLogger('checkTimeDiff' . $mins);
        if ($mins < $this->loop_time_diff) { // blocking for loop if synched time diff less then 2 mint
            $this->writeLogger('in less checkTimeDiff' . $mins);
            // die('end');
            return true;
        }
        return false;
    }

    /**
     * called on from include files
     */
    public function updateCstmTable($table, $fields)
    {
        global $db;
        $fieldValue = [];
        foreach ($fields as $key => $value) {
            if ($key == 'id_c') {
                $id_c = $value;
            }
            $fieldValue[] = "$key = '$value'";
        }
        // check Existing xero_id_c;
        $query = "UPDATE $table set " . implode(", ", $fieldValue) . " where id_c='$id_c'";
        $db->query($query);
    }

    /**
     * function updateSession add required details in session
     * @param Array $tokenData 
     * @return void()
     */
    private function updateSession($tokenData)
    {
        include_once 'tests/testRunner.php';
        $session = persistSession(array(
            'oauth_token' => $tokenData['access_token'],
            'tenantId' => $this->config['tenant_id'],
            'oauth_token_secret' => $this->config['consumer_secret'],
            'oauth_session_handle' => $tokenData['refresh_token']
        ));
    }

    /**
     * retrieveSession and Set session var into xeroConfig
     * @param Void
     * @return Array 
     */
    public function retrieveSession()
    {
        include_once 'tests/testRunner.php';
        $oauthSession = retrieveSession();
        $this->XeroOAuth->config['access_token']  = $oauthSession['oauth_token'];
        $this->XeroOAuth->config['access_token_secret'] = $oauthSession['oauth_token_secret'];
        $this->XeroOAuth->config['session_handle'] = $oauthSession['oauth_session_handle'];
        return $oauthSession;
    }

    public function syncInvoiceToXero($bean, $XeroContactID)
    {
        $checkItemsSql = "SELECT * FROM aos_products_quotes WHERE parent_type = 'AOS_Invoices' AND parent_id = '$bean->id' AND deleted = 0";
        $checkItemsResult = $GLOBALS['db']->query($checkItemsSql, false);
        if ($GLOBALS['db']->getRowCount($checkItemsResult) <= 0) {
            $this->writeLogger('No Line Item has been related to this Invoice. Please add line items.');
            return false;
        }

        $updateGroup = "update aos_line_item_groups set name='Xero Invoices' where name='' and parent_id='" . $bean->id . "' and parent_type='AOS_Invoices' and deleted=0 ";
        $GLOBALS['db']->query($updateGroup);

        $Type = $bean->type_c;
        $currencyID = $bean->currency_id;
        if ($currencyID == '') {
            $currencyID = -99;
        }
        /* Get the currency name to send to Xero */
        $Currency = BeanFactory::getBean('Currencies', $currencyID);
        if ($Currency != '') {
            $currency_name = $Currency->iso4217;
        }
        /* end of currency iso4217 code */
		$ExpCode = substr($bean->xero_expense_codes_c, -3);
        $Reference = $bean->name;
        $Status = $bean->status;
        $XeroAccepStatus = ['DRAFT', 'SUBMITTED', 'AUTHORISED'];
		$Status = strtoupper($Status);
		if (!in_array($Status, $XeroAccepStatus)) {
			$Status = "DRAFT";
		}
        // get dates directly from database, no date format required	
        $date_sql = "SELECT due_date, invoice_date FROM aos_invoices WHERE id = '" . $bean->id . "' AND deleted = 0";
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

        $SubTotal                 =        $bean->total_amt;
        $TotalTax                 =        $bean->tax_amount;
        $Total                     =        $bean->total_amount;

        // insert Line Items 
        $sql = "SELECT * FROM aos_products_quotes WHERE parent_type = 'AOS_Invoices' AND parent_id = '" . $bean->id . "' AND deleted = 0";
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

            $xeroItem = $this->getXeroItem($row, $ExpCode); // fetching line item

            if (isset($xeroItem['ItemCode'])) {
                $xmlChild .= "<LineItem>
							<ItemCode>" . $xeroItem['ItemCode'] . "</ItemCode>
							<Description>" . $xeroItem['name'] . "</Description>
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
        }
        $xml = "<Invoice>
				  <Type>" . $Type . "</Type>
				  <Contact>
					<ContactID>" . $XeroContactID . "</ContactID>
				  </Contact>
				  <Date>" . $dateDBinvoice . "</Date>
				  <DueDate>" . $dateDBdueDate . "</DueDate>
				  <LineAmountTypes>Exclusive</LineAmountTypes>
				  <CurrencyCode>" . $currency_name . "</CurrencyCode>
				  <Status>" . $Status . "</Status>
				 <SubTotal>" . $SubTotal . "</SubTotal>
				<TotalTax>" . $TotalTax . "</TotalTax>
				<Total>" . $Total . "</Total>
				  <LineItems>";
        $xml .= $xmlChild;
        $xml .= " </LineItems>";

        if ($bean->xero_id_c) $xml .= "<InvoiceID>" . $bean->xero_id_c . "</InvoiceID>"; // add invoice id if exist

        if ($Type == 'ACCPAY') {
            $xml .= "<InvoiceNumber>" . $Reference . "</InvoiceNumber>";
        }
        $xml .= "</Invoice>";
        $this->writeLogger($xml);
        // update the invoice in Xero
        $response = $this->XeroOAuth->request('POST', $this->XeroOAuth->url('Invoices', 'core'), array(), $xml);
        if ($this->XeroOAuth->response['code'] == 200) {

            $Invoices = $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], $this->XeroOAuth->response['format']);
            $xeroInvoiceID = (string) $Invoices->Invoices->Invoice->InvoiceID;
            $fieldsToSave = [
                'id_c' => $bean->id,
                'xero_id_c' => $xeroInvoiceID,
                'dtime_synched_c' => $this->nowDb()
            ];
            if ($Type == 'ACCREC') {
                $fieldsToSave['xero_link_c'] = "https://go.xero.com/AccountsReceivable/Edit.aspx?invoiceid=" . $xeroInvoiceID;
            } else {
                $fieldsToSave['xero_link_c'] = "https://go.xero.com/AccountsPayable/Edit.aspx?invoiceid=" . $xeroInvoiceID;
            }
            $this->updateCstmTable('aos_invoices_cstm', $fieldsToSave); // on creating facing issue on record save fields

            // updating line item start
            foreach ($Invoices->Invoices->Invoice->LineItems->LineItem as $lineItem) {
                $updateLineItem = "UPDATE aos_products_quotes set line_item_id='" . $lineItem->LineItemID . "', date_modified='" . $this->nowDb() . "' 
				WHERE name = '" . $lineItem->Description . "' AND parent_type = 'AOS_Invoices' AND parent_id = '" . $bean->id . "' AND (line_item_id = '' OR line_item_id IS NULL) AND 
				((select part_number from aos_products where id = product_id) = '" . $lineItem->ItemCode . "' OR 
				(select maincode from aos_products where id = product_id) = '" . $lineItem->ItemCode . "')  LIMIT 1";

                $dates_result = $GLOBALS['db']->query($updateLineItem);
            }
            // updating line item start
            $this->writeLogger('The invoice ' . $bean->name . ' was successfully created in Xero.');

            return true;
        } else {
            $validationError = $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], $this->XeroOAuth->response['format']);
            // echo"<pre>";print_r($validationError);die;
            $xero_error = $validationError->Elements->DataContractBase->ValidationErrors->ValidationError->Message;
            $this->writeLogger('error on invoce sunch:-');
            $this->writeLogger($xero_error);
            return false;
        }
    }


    public function checkSuiteItem($productQuote)
    {
        $prosql = "SELECT * FROM aos_products WHERE (name = '" . $productQuote['name'] . "' || id='" . $productQuote['parent_id'] . "')  AND deleted = 0";
        $proresult = $GLOBALS['db']->query($prosql);
        return $GLOBALS['db']->fetchByAssoc($proresult);
    }

    public function getXeroItem($productQuote, $ExpCode)
    {
        $prorow = $this->checkSuiteItem($productQuote);
        if (empty($prorow)) return;

        $return = [];
        // check products in Xero, if they don't exist, create them
        $response = $this->XeroOAuth->request('GET', $this->XeroOAuth->url('Items', 'core'), array('page' => 0, 'Where' => 'name="' . $productQuote['name'] . '" OR code="' . $prorow['part_number'] . '"'));
        $ItemCode = '';
        if ($this->XeroOAuth->response['code'] == 200) {
            $product = $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], $this->XeroOAuth->response['format']);
            if (count($product->Items) > 0) {
                $return['ItemCode'] = $product->Items->Item->Code;
                $return['name'] = $product->Items->Item->Description;
            } else {
                $xmlItem = "<Item>
                                <Code>" . $prorow['part_number'] . "</Code>
                                <Description>" . $prorow['name'] . "</Description>
                                <PurchaseDetails>
                                <UnitPrice>" . $prorow['cost'] . "</UnitPrice>
                                <AccountCode>" . $ExpCode . "</AccountCode>
                                </PurchaseDetails>
                                <SalesDetails>
                                <UnitPrice>" . $prorow['price'] . "</UnitPrice>
                                <AccountCode>" . $ExpCode . "</AccountCode>
                                </SalesDetails>
                            </Item>";
                $response = $this->XeroOAuth->request('PUT', $this->XeroOAuth->url('Items', 'core'), array(), $xmlItem);
                if ($this->XeroOAuth->response['code'] == 200) {
                    $addproduct = $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], $this->XeroOAuth->response['format']);
                    $return['ItemCode'] = $addproduct->Items->Item->Code;
                    $return['name'] = $addproduct->Items->Item->Description;
                    //print_r($addproduct);
                } else {
                    $validationError = $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], $this->XeroOAuth->response['format']);
                    $xero_error = $validationError->Elements->DataContractBase->ValidationErrors->ValidationError->Message;
                    $this->writeLogger($xero_error);
                }
            }
        }
        return $return;
    }
}
