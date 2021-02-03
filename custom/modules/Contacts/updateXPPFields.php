<?php
class updateXPPFields {
function xeroprimarycontact($bean, $event, $arguments) {
	 $xppval = $bean->xero_primary_contact_c;
	 $contactid = $bean->id;
	 $accountid = $bean->account_id;
	 global $db;

// the xpp value is 1 - trigger the code to update all other related records to 0
    if ($xppval == 1 && $bean->fetched_row['xero_primary_contact_c'] <> $xppval){
// check that the record is related to an account
    if(isset($accountid) && !empty($accountid)){
// load the related account    
		$accountbean = BeanFactory::getBean('Accounts', $accountid);
// get all contacts related to this account - it includes the initiating record
		$contacts = $accountbean->get_linked_beans('contacts','Contacts');
        if(isset($contacts) && !empty($contacts)){
// set all related contacts xpp value to 0
			foreach ($contacts as $contactobj) {
			    $update_xprecord = "UPDATE contacts_cstm set xero_primary_contact_c = 0 WHERE contacts_cstm.id_c = '$contactobj->id'";
                $db->query($update_xprecord, false);
    			}    
    	    }
// set the initiating records xpp value to 1			
    	    $update_xprecord = "UPDATE contacts_cstm set xero_primary_contact_c = 1 WHERE contacts_cstm.id_c = '$contactid'";
            $db->query($update_xprecord, false);
		    }
//		$GLOBALS['log']->fatal('AFTER SAVE: LogicHook fired, contact record redirecting to is '.$bean->name);
	    }
	} 
}
