<?php

class BfsSuitetoXero
{
    public $config;
    private $XeroOAuth;
    public $alertMsg = [];

    function  __construct()
    {
    }

    public function redirectToModule($msg = null)
    {
        echo ("<SCRIPT LANGUAGE='JavaScript'>
                        window.alert('$msg')
                        window.location.href='index.php?module=bfs_SuitetoXero&action=index';
            </SCRIPT>");
        exit;
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
                $msg = 'suiteToXeroScheduler ====> Your Suite to Xero License is no longer active due to the following reason: ' . $validate_license . ' Users will have limited to no access until the issue has been addressed.';
                $this->redirectToModule($msg);
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
                $this->redirectToModule('NO Xero connection details have been located, please Connect to Xero BEFORE attempting to send records to Xero.');
                return false;
            }

            $this->config = $row_creds; // set config variable
            return true;
        } else {
            // message, no credentials found, redirect to Config page
            $this->redirectToModule('NO Xero credentials have been located, please create them BEFORE attempting to send records to Xero.');
            return false;
        }
    }

    /**
     * fetch refresh token from xero
     * @param Void
     * @return Boolean
     */
    public function getSetRefreshToken()
    {
        global $db;
        $signatures = array(
            'consumer_key' => $this->config['consumer_key'],
            'access_token' => '', // added for Oauth2.0 
            'shared_secret' => $this->config['consumer_secret'],
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

        $this->XeroOAuth->requestToken($this->config['redirect_url'], null, true);
        $refresh_token = $this->XeroOAuth->response['response']['refresh_token'];
        if (empty($refresh_token)) {
            $this->redirectToModule('Some error has been occured on fetching Refresh Token.');
            return false;
        }

        $update_creds = "UPDATE bfs_SuitetoXero set refresh_token = '$refresh_token' WHERE bfs_SuitetoXero.deleted = 0 order by date_entered desc limit 1";
        $db->query($update_creds, false);
        $this->updateSession($this->XeroOAuth->response['response']);
        return true;
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
     * Set config var execute_time updated record wouldn't call again
     */
    public function setExecuteTime()
    {
        global $db;
        $currentCronQuery = "SELECT execute_time from job_queue where target='function::suiteToXeroScheduler' ORDER by execute_time DESC limit 1";
        $currentCronResult = $db->query($currentCronQuery, false);
        $currentCron = $db->fetchByAssoc($currentCronResult);
        $this->config['execute_time'] = $currentCron['execute_time'];
    }

    /**
     * check is Cron running
     * @param Void
     * @return Boolean
     */
    public function isCronRunning()
    {
        global $db;
        $currentCronQuery = "SELECT status from job_queue where target='function::suiteToXeroScheduler' ORDER by execute_time DESC limit 1";
        $currentCronResult = $db->query($currentCronQuery, false);
        $currentCron = $db->fetchByAssoc($currentCronResult);
        if ($currentCron['status'] == 'running' || $currentCron['status'] == 'queued') {
            return true;
        }
        return false;
    }

    /**
     * When user set configuration as syncToXero called this function
     * It calles execute all process which uset set true
     */
    public function syncToXero()
    {
        $this->writeLogger('Start syncToXero');
        // if ($this->config['create_accounts'] == 1) {
        $this->writeLogger('calling --> createRecordsToXero  --> accounts');
        $this->createRecordsToXero('accounts');
        // }
        // if ($this->config['synch_accounts'] == 1) {
        // $this->writeLogger('calling --> syncContactToXero  --> accounts');
        // $this->syncContactToXero('accounts');
        // }
        // if ($this->config['delete_accounts'] == 1) {
        //     $this->writeLogger('calling --> updateDeletedRecords  --> accounts');
        //     $this->updateDeletedRecords('accounts');
        // }

        // if ($this->config['create_contacts'] == 1) {
        $this->writeLogger('calling --> createRecordsToXero  --> contacts');
        $this->createRecordsToXero('contacts');
        // }
        // if ($this->config['synch_contacts'] == 1) {
        // $this->writeLogger('calling --> syncContactToXero  --> contacts');
        // $this->syncContactToXero('contacts');
        // }
        // if ($this->config['delete_contacts'] == 1) {
        //     $this->writeLogger('calling --> updateDeletedRecords  --> contacts');
        //     $this->updateDeletedRecords('contacts');
        // }

        // if ($this->config['create_invoices'] == 1) {
        $this->writeLogger('calling --> createRecordsToXero --> aos_invoices');
        $this->createRecordsToXero('aos_invoices');
        // }
        // if ($this->config['synch_invoices'] == 1) {
        // $this->writeLogger('calling --> syncRecordsToXero  --> aos_invoices');
        // $this->syncInvoiceToXero('aos_invoices');
        // }
        // if ($this->config['delete_invoices'] == 1) {
        //     $this->writeLogger('calling --> updateDeletedRecords  --> aos_invoices');
        //     $this->updateDeletedRecords('aos_invoices');
        // }
        $this->writeLogger('End syncToXero');
    }

    /**
     * @param string $type (create/update/delete)
     * @param string $table
     * @return array $ids
     */
    private function getRecords($type, $table)
    {
        global $db;
        $where = '';
        $fields = "$table.id, cstm.xero_id_c";
        // $where = "where DATE($table.date_modified) >= '" . $this->config['update_records_from'] . "'";
        // $where = "where DATE($table.date_modified) >= '" . $this->config['update_records_from'] . "'";
        // $acJoin = "INNER JOIN " . $table . "_cstm cstm on cstm.id_c = $table.id AND (cstm.dtime_synched_c < '" . $this->config['execute_time'] . "' OR cstm.dtime_synched_c IS NULL)";
        $acJoin = "LEFT JOIN " . $table . "_cstm cstm on cstm.id_c = $table.id";

        // if ($type == 'delete') {
        //     $where .= " AND $table.deleted = 1";
        //     $acJoin .= " AND cstm.deleted_synched_c = 0";
        // } else {
        $where .= "where $table.deleted = 0";
        // }

        if ($type == 'create') { // create only those record who doesn't have xero id
            $acJoin .= ' AND (cstm.xero_id_c = "" OR cstm.xero_id_c IS NULL)';
        } else if ($type == 'update' || $type == 'delete') { // update only those record who has xero id
            $acJoin .= ' AND cstm.xero_id_c != ""';
        }

        if ($table == 'contacts') { // Contact record only update, If can contact doesn;t link with account
            $where .= " AND ((select count(id) from accounts_contacts where contact_id = contacts.id AND deleted=0) = 0)";
        }

        if ($table == 'aos_invoices') {
            $where .= " AND (
                (select xero_id_c  from accounts_cstm where id_c = aos_invoices.billing_account_id) != '' 
                || 
                (select xero_id_c  from contacts_cstm where id_c = aos_invoices.billing_contact_id) != '')";

            if ($type != 'delete') {
                $where .= " AND (select count(id) from aos_products_quotes where parent_id=$table.id AND parent_type='AOS_Invoices' AND deleted=0) > 0";
            }
        }


        // if ($this->config['which_records'] == 'ALL_Records') {
        //     // do nothing
        // } else if ($this->config['which_records'] == 'Selected_Records_ONLY') {
        //     $acJoin .= ' AND cstm.xero_synch_c = 1';
        // } else {
        //     return [];
        // }


        $ids = [];
        $query = "SELECT $fields from $table $acJoin $where";
        $result = $db->query($query, false);
        while (($row = $db->fetchByAssoc($result)) != null) {
            $ids[] = ['xeroID' => $row['xero_id_c'], 'id' => $row['id']];
        }
        return $ids;
    }

    /**
     * This function will call files and create new records into xero
     * @param String $table
     */
    private function createRecordsToXero($table)
    {
        $records = $this->getRecords('create', $table);
        if (count($records) <= 0) {
            return;
        }
        $_REQUEST['CRON'] = true;
        $ids = [];
        foreach ($records as $row) {
            $ids[] = $row['id'];
        }
        $_REQUEST['uid'] = implode(',', $ids);

        // variable setup for include files
        $oauthSession = $this->retrieveSession();
        $XeroOAuth = $this->XeroOAuth;
        if ($table == 'accounts') {
            include_once 'modules/bfs_SuitetoXero/library/entrypoints/massSendToXero.php';
        } else if ($table == 'aos_invoices') {
            include_once 'modules/bfs_SuitetoXero/library/entrypoints/massInvoicesToXero.php';
        } else {
            include_once 'modules/bfs_SuitetoXero/library/entrypoints/massSendContactToXero.php';
        }
    }

    private function syncContactToXero($table)
    {
        $ids = $this->getRecords('update', $table);
        if (count($ids) <= 0) {
            return;
        }

        // variable setup for include files
        $oauthSession = $this->retrieveSession();
        $XeroOAuth = $this->XeroOAuth;
        $_REQUEST['CRON'] = true;
        if ($table == 'accounts') {
            $_REQUEST['module'] = 'Accounts';
        } else {
            $_REQUEST['module'] = 'Contacts';
        }
        $syncSuccess = []; // var will have value after running updateXero.php file
        $syncFailur = []; // var will have value after running updateXero.php file
        foreach ($ids as $values) {
            $_REQUEST['xeroID'] = $values['xeroID'];
            if ($table == 'accounts') {
                $_REQUEST['accountID'] = $values['id'];
            } else {
                $_REQUEST['contactID'] = $values['id'];
            }
            include 'modules/bfs_SuitetoXero/library/entrypoints/updateXero.php';
        }
        $this->writeLogger('Sync Account Success:-');
        $this->writeLogger($syncSuccess);
        $this->writeLogger('Sync Account Failur:-');
        $this->writeLogger($syncFailur);
        $this->alertMsg[] = count($syncSuccess) . ' ' . $_REQUEST['module'] . " successfully update.";
        $this->alertMsg[] = count($syncFailur) . ' ' . $_REQUEST['module'] . " have some error on update.";
    }

    private function updateDeletedRecords($table)
    {
        $ids = $this->getRecords('delete', $table);
        if (count($ids) <= 0) {
            return;
        }

        $oauthSession = $this->retrieveSession();

        $idArray = [];
        if ($table == 'aos_invoices') {
            $xml = "<Invoices>";
            foreach ($ids as $id) {
                $idArray[] = $id['id'];
                $xml .= "<Invoice>
                            <InvoiceID>" . $id['xeroID'] . "</InvoiceID>
                            <Status>DELETED</Status>
                        </Invoice>";
            }
            $xml .= "</Invoices>";
            $response = $this->XeroOAuth->request('POST', $this->XeroOAuth->url('Invoices', 'core'), array(), $xml);
        } else {
            $xml = "<Contacts>";
            foreach ($ids as $id) {
                $idArray[] = $id['id'];
                $xml .= "<Contact>
                            <ContactID>" . $id['xeroID'] . "</ContactID>
                            <ContactStatus>ARCHIVED</ContactStatus>
                        </Contact>";
            }
            $xml .= "</Contacts>";
            $response = $this->XeroOAuth->request('POST', $this->XeroOAuth->url('Contacts', 'core'), array(), $xml);
        }
        if ($this->XeroOAuth->response['code'] == 200) {
            global $timedate, $db;
            $CurrenrDateTime = $timedate->getInstance()->nowDb();

            $query = "UPDATE " . $table . "_cstm set dtime_synched_c = '$CurrenrDateTime', deleted_synched_c = 1 WHERE id_c IN('" . implode("', '", $idArray) . "')";
            $result = $db->query($query, false);
        } else {
            $error = $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], '');
            $this->writeLogger('Error On delete:');
            $this->writeLogger($error);
        }
        return true;
    }

    private function syncInvoiceToXero($table)
    {
        $records = $this->getRecords('update', $table);
        if (count($records) <= 0) {
            return;
        }
        $ids = [];
        foreach ($records as $row) {
            $ids[] = $row['id'];
        }
        $_REQUEST['uid'] = implode(',', $ids);

        // variable setup for include files
        $oauthSession = $this->retrieveSession();
        $XeroOAuth = $this->XeroOAuth;
        $_REQUEST['CRON'] = true;

        include_once 'modules/bfs_SuitetoXero/library/entrypoints/massUpdateInvToXero.php';
    }

    /**
     * This function only call for get new records
     * Updating exist records from xero commented
     */
    public function syncFromXero()
    {
        $this->writeLogger('Start syncFromXero');
        // Fro creating records from Xero, following this step, no need to make different-2 procss
        // if ($this->config['create_accounts'] == 1 || $this->config['create_contacts'] == 1) {
        $this->writeLogger('calling --> createRecordsFromXeroContacts');
        $page = 1;
        $callApi = true;
        while ($callApi) {
            $newContacts =  $this->getContactsFromXero('create', $page);
            $this->createRecordsFromXeroContacts($newContacts);
            if (count($newContacts) < 100) { // if found less records do not call API again  
                $callApi = false;
            }
            $page++;
        }
        // }

        // if ($this->config['synch_accounts'] == 1) {
        //     $this->writeLogger('calling --> syncContactFromXero  --> accounts');
        //     $this->syncContactFromXero('accounts');
        // }

        // if ($this->config['synch_contacts'] == 1) {
        //     $this->writeLogger('calling --> syncContactFromXero  --> contacts');
        //     $this->syncContactFromXero('contacts');
        // }

        // not able to fetch deleted records from xero API
        // if ($this->config['delete_accounts'] == 1 || $this->config['delete_contacts'] == 1) {
        //     $this->writeLogger('calling --> updateDeletedFromXero');
        //     $deletedContacts =  $this->getContactsFromXero('delete');
        //     $this->updateDeletedFromXeroContacts();
        // }

        // if ($this->config['create_invoices'] == 1) {
        $this->writeLogger('calling --> createInvoicesFromXero');
        $this->createInvoicesFromXero();
        // }
        // if ($this->config['synch_invoices'] == 1) {
        //     $this->writeLogger('calling --> syncInvoiceFromXero  --> aos_invoices');
        //     $this->syncInvoiceFromXero('aos_invoices');
        // }
        // if ($this->config['delete_invoices'] == 1) {
        //     $this->writeLogger('calling --> updateDeletedInvoices');
        //     $this->updateDeletedInvoices();
        // }
        $this->writeLogger('End syncFromXero');
    }

    /**
     * function used to fecth record from Xero
     * @param String $type
     * @return Array/Void
     */
    public function getContactsFromXero($type, $page = 1)
    {
        $oauthSession = $this->retrieveSession();
        $this->writeLogger('in --> getContactsFromXero');
        if ($type == 'create') {
            // $where = 'AccountNumber="" OR AccountNumber=NULL OR AccountNumber="NULL"';
            $where = '';
        } else {
            $where = 'ContactStatus!=null&&ContactStatus!="ACTIVE"';
        }

        $this->XeroOAuth->request('GET', $this->XeroOAuth->url('Contacts', 'core'), array('page' => $page, 'Where' => $where));
        if ($this->XeroOAuth->response['code'] == 200) {
            $response =  $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], '');
            return $response->Contacts->Contact;
        } else {
            $error = $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], '');
            $this->writeLogger('Found error in --> getContactsFromXero');
            $this->writeLogger($error);
        }
    }

    public function createRecordsFromXeroContacts($newContacts)
    {
        $this->writeLogger('in ==> createRecordsFromXeroContacts ' . count($newContacts));
        $createdRecords = [];
        foreach ($newContacts as $contact) {

            // if ($this->config['create_accounts'] == 1 && $this->config['create_contacts'] != 1) {
            //     $createdRecords[] = $this->createAccountFromXero($contact);
            // } else if ($this->config['create_accounts'] != 1 && $this->config['create_contacts'] == 1) {
            //     $createdRecords[] = $this->createContactFromXero($contact);
            // } else {
            $createdRecords[] = $this->xeroContactCheckCreate($contact);
            // }
        }
        if (count($createdRecords) == 0) {
            $this->alertMsg[] = 'No new Contacts created.';
            return;
        }

        $this->updateXeroAccountNumber($createdRecords);
    }

    /**
     * @param Object $contact contact record from xero
     * @param Boolean $checkExist passes to next function 
     * @return Array from function create Account/Contact for suite 
     */
    public function xeroContactCheckCreate($contact, $checkExist = true)
    {
        $this->writeLogger(' in =>>> xeroContactCheckCreate');
        if ($contact->Name == ($contact->FirstName . ' ' . $contact->LastName)) {
            return $this->createContactFromXero($contact, $checkExist);
        } else {
            return $this->createAccountFromXero($contact, $checkExist);
        }
    }

    /**
     * @param Object $Contact
     * @param Boolean $checkExist (if true it will check exist record)
     * @return Axrray()
     */
    public function createAccountFromXero($Contact, $checkExist = true)
    {
        $this->writeLogger(' in =>>> createAccountFromXero');
        $existID = '';
        if ($checkExist) {
            global $db;
            $accountExistQuery = "SELECT id, xero_id_c from accounts 
                    INNER JOIN accounts_cstm cstm on cstm.id_c = accounts.id
                    where name='" . (string) $Contact->Name . "' and deleted=0";
            $result = $db->query($accountExistQuery, false);

            if ($db->getRowCount($result) > 0) {
                $row = $db->fetchByAssoc($result);
                // if (!empty($row['xero_id_c'])) {
                //     return ['id' => $row['id'], 'xeroID' => (string) $Contact->ContactID];
                // }
                $existID = $row['id'];
            }
            // die('end560');
        }
        // die('end561');

        if (!empty($existID)) {
            $accountobj = BeanFactory::getBean('Accounts', $existID);
        } else {
            $accountobj = BeanFactory::newBean('Accounts');   //Create bean  using module name 
        }
        // echo "<pre>"; print_r($accountobj); die;
        $accountobj->name = $Contact->Name;
        // $accountobj->email = $Contact->EmailAddress;
        $accountobj->website = $Contact->Website;
        $accountobj->phone_office = $Contact->Phones->Phone[1]->PhoneNumber;
        $accountobj->phone_fax = $Contact->Phones->Phone[2]->PhoneNumber;
        $accountobj->phone_alternate = $Contact->Phones->Phone[3]->PhoneNumber;
        $accountobj->xero_id_c = $Contact->ContactID;
        $accountobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $Contact->ContactID;
        $accountobj->dtime_synched_c = date('Y-m-d H:i:s');

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

        $sea = new SugarEmailAddress;
        $sea->addAddress($Contact->EmailAddress, true);
        $sea->save($accountobj->id, "Accounts");

        $this->writeLogger(' AccountSaved successfully after save line 586');
        
        require_once 'custom/entrypoints/XeroOAuth-PHP-master/LinkedContact.php';
        $LinkedContact = new LinkedContact();

        $LinkedContact->addLinkedContacts($Contact, $accountobj->id);
        return ['module' => 'Accounts', 'id' => $accountobj->id, 'xeroID' => (string) $Contact->ContactID];
    }

    /**
     * @param Object $Contact
     * @param Boolean $checkExist (if true it will check exist record)
     * @return Array()
     */
    public function createContactFromXero($Contact, $checkExist = true)
    {
        $existID = '';
        if ($checkExist) {
            global $db;
            $accountExistQuery = "SELECT id, xero_id_c from contacts 
                INNER JOIN contacts_cstm cstm on cstm.id_c = contacts.id 
                where CONCAT(first_name, ' ', last_name)='" . $Contact->Name . "' and deleted=0";
            $result = $db->query($accountExistQuery, false);

            $row = $db->fetchByAssoc($result);
            if ($db->getRowCount($result) > 0) {
                $row = $db->fetchByAssoc($result);
                // if (!empty($row['xero_id_c'])) {
                //     return ['id' => $row['id'], 'xeroID' => (string) $Contact->ContactID];
                // }
                $existID = $row['id'];
            }
        }
        if (!empty($existID)) {
            $contactobj = BeanFactory::getBean('Contacts', $existID);
        } else {
            $contactobj = BeanFactory::newBean('Contacts');   //Create bean  using module name 
        }

        $contactobj->first_name = $Contact->FirstName;
        $contactobj->last_name = $Contact->LastName;
        // $contactobj->email = $Contact->EmailAddress;
        $contactobj->phone_work = $Contact->Phones->Phone[1]->PhoneNumber;
        $contactobj->phone_fax = $Contact->Phones->Phone[2]->PhoneFax;
        $contactobj->phone_mobile = $Contact->Phones->Phone[3]->PhoneNumber;
        $contactobj->xero_id_c = $Contact->ContactID;
        $contactobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $Contact->ContactID;
        $contactobj->dtime_synched_c = date('Y-m-d H:i:s');

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
        return ['module' => 'Contacts', 'id' => $contactobj->id, 'xeroID' => (string) $Contact->ContactID[0]];
    }

    /**
     * function used for update AccountNumber on Xero
     * @param $contacts array of created contacts
     * @return void()
     */
    public function updateXeroAccountNumber($contacts)
    {
        $xml = "<Contacts>";
        foreach ($contacts as $contact) {
            $xml .= "<Contact>
                        <ContactID>" . $contact['xeroID'] . "</ContactID>
                        <AccountNumber>" . $contact['id'] . "</AccountNumber>
                    </Contact>";
        }
        $xml .= "</Contacts>";

        $oauthSession = $this->retrieveSession();

        $response = $this->XeroOAuth->request('POST', $this->XeroOAuth->url('Contacts', 'core'), array(), $xml);

        if ($this->XeroOAuth->response['code'] == 200) {
            $this->writeLogger(count($contacts) . ' Accounts created/update from Xero successfully.');
            $this->alertMsg[] = count($contacts) . ' Accounts created/update from Xero successfully.';
        } else {
            $error = (array) $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], '');
            $this->writeLogger('Error On Update AccountNumber:');
            $this->alertMsg[] = 'Error On Update AccountNumber: Please check Log file';
            $this->writeLogger($error);
        }
    }

    private function syncContactFromXero($table)
    {
        $records = $this->getRecords('update', $table);
        if (count($records) <= 0) {
            return;
        }

        $ids = [];
        foreach ($records as $row) {
            $ids[] = $row['id'];
        }
        $_REQUEST['uid'] = implode(',', $ids);

        // variable setup for include files
        $oauthSession = $this->retrieveSession();
        $XeroOAuth = $this->XeroOAuth;
        $_REQUEST['CRON'] = true;
        if ($table == 'contacts') {
            include_once 'modules/bfs_SuitetoXero/library/entrypoints/massUpdateContFromXero.php';
        } else {
            include_once 'modules/bfs_SuitetoXero/library/entrypoints/massUpdateAcctFromXero.php';
        }
    }

    public function createInvoicesFromXero()
    {
        global $db;
        $oauthSession = $this->retrieveSession();
        $page = 1;
        $callApi = true;
        $createdInvoice = [];
        while ($callApi) {
            $response = $this->XeroOAuth->request('GET', $this->XeroOAuth->url('Invoices', 'core'), array('page' => $page, 'Where' => 'Status!="DELETED" && Status!="VOIDED"'));
            $parseResponse = $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], '');

            foreach ($parseResponse->Invoices->Invoice as $invoice) {
                $existInvoiceQuery = "SELECT id_c from aos_invoices_cstm where xero_id_c='" . (string) $invoice->InvoiceID . "'";
                $result = $db->query($existInvoiceQuery);
                if ($db->getRowCount($result) > 0) {
                    $this->writeLogger((string) $invoice->InvoiceID . ' found skiping.');
                    continue;
                }
                $createdInvoice[] = $this->createInvoiceRecord($invoice);
            }

            if (count($parseResponse->Invoices->Invoice) < 100) { // if found less records do not call API again  
                $callApi = false;
            }
            $page++;
        }
        $this->writeLogger('Created invoices below:');
        $this->writeLogger($createdInvoice);
        $this->alertMsg[] = count($createdInvoice) . ' Invoice has been created.';
    }

    public function createInvoiceRecord($Invoice)
    {
        $Invoicebean = BeanFactory::newBean('AOS_Invoices');
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
            $Invoicebean->xero_expense_codes_c =    '';
            $stat                        =            'AccountsReceivable';
        }
        if ($Type == 'ACCPAY') {
            $Invoicebean->type_c        =            'ACCPAY';
            $Invoicebean->xero_expense_codes_c =    $ExpCode;
            $stat                        =            'AccountsPayable';
        }
        $Invoicebean->total_amt            =            $SubTotal;
        $Invoicebean->subtotal_amount    =            $SubTotal;
        $Invoicebean->tax_amount        =            $TotalTax;
        $Invoicebean->total_amount        =            $Total;
        $Invoicebean->xeroutc_c            =            $UpdatedDateUTC;
        $Invoicebean->xero_id_c            =            $XeroInvoiceID;
        $Invoicebean->xero_link_c        =            "https://go.xero.com/" . $stat . "/Edit.aspx?invoiceid=" . $XeroInvoiceID;

        global $timedate, $current_user;
        $Invoicebean->dtime_synched_c = $timedate->getInstance()->nowDb();
        $Invoicebean->assigned_user_id = $current_user->id;

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

        // inserting new Group line item
        $oid = create_guid();
        $GRSQL = "INSERT INTO aos_line_item_groups 
										(id,name,date_entered,date_modified ,total_amt,tax_amount,subtotal_amount,total_amount,parent_type,parent_id,number) VALUE ('$oid','Xero Invoices',NOW(),NOW(),'$SubTotal','$TotalTax','$SubTotal','$Total','AOS_Invoices','" . $Invoicebean->id . "','1')";
        $GLOBALS['db']->query($GRSQL);
        $groupID = $oid;
        $this->createInvoiceItems($Invoice->LineItems->LineItem, $groupID, $Invoicebean->id);
        return $Invoicebean->name;
    }

    /**
     * @param Object XeroInvoice
     * @return Object Bean
     */
    private function getXeroCreateExistContactDetails($Invoice)
    {
        global $db;
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
        // session variable setup
        $this->retrieveSession();
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
        $checkAccountResult = $db->query($checkAccountQuery);;
        if ($accountRow = $db->fetchByAssoc($checkAccountResult)) {
            return $accountRow['id_c'];
        }
        return false;
    }

    private function syncInvoiceFromXero($table)
    {
        $records = $this->getRecords('update', $table);
        if (count($records) <= 0) {
            return;
        }
        $ids = [];
        foreach ($records as $row) {
            $ids[] = $row['id'];
        }
        $_REQUEST['uid'] = implode(',', $ids);

        // variable setup for include files
        $oauthSession = $this->retrieveSession();
        $XeroOAuth = $this->XeroOAuth;
        $_REQUEST['CRON'] = true;

        include_once 'modules/bfs_SuitetoXero/library/entrypoints/massUpdateInvFromXero.php';
    }

    public function updateDeletedInvoices()
    {
        global $db;
        // variable setup for include files
        $oauthSession = $this->retrieveSession();

        $response = $this->XeroOAuth->request('GET', $this->XeroOAuth->url('Invoices', 'core'), array('Statuses' => 'DELETED,VOIDED'));
        $invoices = $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], $this->XeroOAuth->response['format']);
        $invoiceXeroIDs = [];
        foreach ($invoices->Invoices->Invoice as $key => $invoice) {
            $invoiceXeroIDs[] = (string) $invoice->InvoiceID;
        }
        $query = "SELECT id_c from aos_invoices_cstm where xero_id_c IN('" . implode("','", $invoiceXeroIDs) . "')";
        $result = $db->query($query);
        while ($row = $db->fetchByAssoc($result)) {
            $invoiceObj = BeanFactory::getBean('AOS_Invoices', $row['id_c']);
            if (empty($invoiceObj)) {
                continue;
            }
            $invoiceObj->mark_deleted($row['id_c']);
            $invoiceObj->save();
        }
    }

    public function createInvoiceItems($LineItems, $groupID, $invoiceId)
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

            $aos_p_q_id = create_guid();
            $AOSSQL = "INSERT INTO aos_products_quotes(id,date_entered,date_modified,number,name,part_number,item_description,product_qty,product_cost_price,product_discount,discount,product_list_price,product_unit_price,product_total_price,product_id,group_id,parent_id,parent_type,line_item_id) 
							VALUES ('$aos_p_q_id',NOW(),NOW(),$i,'$Description','$ItemCode','$Description','$Quantity','$UnitAmount','$DiscountRate','Percentage','$UnitAmount','$UnitAmount','$LineAmount','$productID','$groupID','" . $invoiceId . "','AOS_Invoices','$LineItemID')";

            $GLOBALS['db']->query($AOSSQL);
            $i++;
        } // end of Line items
    }
}
