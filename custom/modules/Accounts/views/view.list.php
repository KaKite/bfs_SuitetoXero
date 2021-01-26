<?php

require_once('modules/Accounts/views/view.list.php');

class CustomAccountsViewList extends AccountsViewList
{
	 public function preDisplay(){
        parent::preDisplay();
        $this->lv->actionsMenuExtraItems[] = $this->buildMyMenuItem();
    }
        
    public function buildMyMenuItem()
    {
        global $app_strings;
    
      
 return <<<EOF
        <a href='javascript:void(0)'
        onclick="return sListView.send_form(true, 'Accounts', 'index.php?entryPoint=xeroLink&func=massSendAcctToXero','Please select at least 1 record to proceed.')">
            Send to Xero</a>
		<a href='javascript:void(0)'
        onclick="return sListView.send_form(true, 'Accounts', 'index.php?entryPoint=xeroLink&func=massUpdAcctFromXero','Please select at least 1 record to proceed.')">
            Update FROM Xero</a>
            <a href='javascript:void(0)'
        onclick="return sListView.send_form(true, 'Accounts', 'index.php?entryPoint=xeroLink&func=massGetInvFromXero&module=Accounts','Please select at least 1 record to proceed.')">
            Get Xero Invoices</a>
EOF;
    }

}
