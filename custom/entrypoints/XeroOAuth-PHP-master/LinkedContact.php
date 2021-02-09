<?php
class LinkedContact
{
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
     * 
     */
    public function nowDb()
    {
        global $timedate;
        return $timedate->getInstance()->nowDb();
    }

    public function relationshipExist($contact_id, $account_id)
    {
        global $db;
        $LinkedQuery = "SELECT id from accounts_contacts where contact_id='$contact_id' and account_id='$account_id'";
        $this->writeLogger($LinkedQuery);
        $result = $db->query($LinkedQuery);
        if ($db->getRowCount($result) > 0) {
            return true;
        }
        return false;
    }

    /**
     * 
     */
    public function addLinkedContacts($Contact, $account_id)
    {
        global $db;
        $LinkedValues = [];
        if (!empty(trim($Contact->FirstName)) ||  !empty(trim($Contact->LastName))) {
            $primaryContactID = $this->contactNameExist($Contact->FirstName . ' ' . $Contact->LastName);
            $this->writeLogger("primaryContactID: " . $primaryContactID);
            if ($primaryContactID) {
                $contactobj = BeanFactory::getBean('Contacts', $primaryContactID);
            } else {
                $contactobj = BeanFactory::newBean('Contacts');   //Create bean  using module name
            }

            $contactobj->first_name = $Contact->FirstName;
            $contactobj->last_name = $Contact->LastName;
			$contactobj->xero_primary_contact_c = 1;
			$contactobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $Contact->ContactID;            
            $contactobj->dtime_synched_c = $this->nowDb();
            $contactobj->xero_synch_c = 1;
            $contactobj->save();

            // save mail id
            $sea = new SugarEmailAddress;
            $sea->addAddress($Contact->EmailAddress, true);
            $sea->save($contactobj->id, "Contacts");

            $newID = create_guid();
            if (!$this->relationshipExist($contactobj->id, $account_id)) {
                $LinkedValues[] = "('$newID', '" . $contactobj->id . "', '$account_id', '" . $this->nowDb() . "')";
            }
        }

        foreach ($Contact->ContactPersons->ContactPerson as $ContactPerson) {
            if (!empty(trim($ContactPerson->FirstName)) ||  !empty(trim($ContactPerson->LastName))) {
                $ContactPersonID = $this->contactNameExist($ContactPerson->FirstName . ' ' . $ContactPerson->LastName);
                if ($ContactPersonID) {
                    $contactobj = BeanFactory::getBean('Contacts', $ContactPersonID);
                } else {
                    $contactobj = BeanFactory::newBean('Contacts');   //Create bean  using module name
                }
                $contactobj->first_name = $ContactPerson->FirstName;
                $contactobj->last_name = $ContactPerson->LastName;
				$contactobj->xero_link_c = "https://go.xero.com/Contacts/View/" . $Contact->ContactID;
                // $contactobj->email = (string) $ContactPerson->EmailAddress;
                $contactobj->dtime_synched_c = $this->nowDb();
                $contactobj->save();

                $sea = new SugarEmailAddress;
                $sea->addAddress($ContactPerson->EmailAddress, true);
                $sea->save($contactobj->id, "Contacts");

                $newID = create_guid();
                if (!$this->relationshipExist($contactobj->id, $account_id)) {
                    $LinkedValues[] = "('$newID', '" . $contactobj->id . "', '$account_id', '" . $this->nowDb() . "')";
                }
            }
        }
        if (count($LinkedValues) > 0) {
            $LinkedQuery = "INSERT into accounts_contacts (id, contact_id, account_id, date_modified) values" . implode(', ', $LinkedValues) . "";
            $this->writeLogger($LinkedQuery);
            $linkedResult = $db->query($LinkedQuery);
        }
    }

    /**
     * 
     */
    public function contactNameExist($name)
    {
        global $db;

        $accountExistQuery = "SELECT id from contacts 
                where CONCAT(first_name, ' ', last_name)='" . $name . "' and deleted=0";
        $this->writeLogger($accountExistQuery);
        $result = $db->query($accountExistQuery, false);
        if ($db->getRowCount($result) > 0) {
            $row = $db->fetchByAssoc($result);
            return $row['id'];
        }
        return false;
    }
}
