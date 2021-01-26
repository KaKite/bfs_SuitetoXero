<?php
class AcctOrContLogicHook
{
    public function accntorcont(SugarBean $bean, $event, $arguments)
    {
        if ($bean->synch_with_xero == "from_xero") {
            $synch_contacts = $bean->synch_contacts + $bean->create_contacts + $bean->delete_contacts;
            $synch_accounts = $bean->synch_accounts + $bean->create_accounts + $bean->delete_accounts;
            if ($synch_accounts > 0 && $synch_contacts > 0) {
                SugarApplication::appendErrorMessage('<h2>***SELECTION ERROR***</h2><p style="font-size:1.5rem;">When synching records <b>FROM</b> your Xero account you are selecting whether to create <b>ALL</b> of those contacts as <b>EITHER</b> Accounts <b>OR</b> Contacts in Suite. Because of the difficulty in identifying if the Xero contact record should be considered an Account or Contact in Suite we <b>CANNOT</b> guarantee that your records would be created correctly here in Suite (as either an Account or Contact).</br></br><b>PLEASE</b> select <b>EITHER</b> Accounts <b>OR</b> Contacts when selecting the Synch from Xero option, <b>NOT</b> both</p>');
                $params = array(
                    'module' => 'bfs_SuitetoXero',
                    'action' => 'EditView',
                    'record' => $bean->id,
                );
                $bean->synch_contacts = 0;
                $bean->create_contacts = 0;
                $bean->delete_contacts = 0;
                SugarApplication::redirect('index.php?' . http_build_query($params));
            }
        }
    }
}
