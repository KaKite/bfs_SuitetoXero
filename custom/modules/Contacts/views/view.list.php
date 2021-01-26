<?php

require_once('modules/Contacts/views/view.list.php');

class CustomContactsViewList extends ContactsViewList
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
        onclick="return sListView.send_form(true, 'Contacts', 'index.php?entryPoint=xeroLink&func=massSendContToXero','Please select at least 1 record to proceed.')">
            Send to Xero</a>
		<a href='javascript:void(0)'
        onclick="return sListView.send_form(true, 'Contacts', 'index.php?entryPoint=xeroLink&func=massUpdContFromXero','Please select at least 1 record to proceed.')">
            Update FROM Xero</a>
		<a href='javascript:void(0)'
        onclick="return sListView.send_form(true, 'Contacts', 'index.php?entryPoint=xeroLink&func=massGetInvFromXero&module=Contacts','Please select at least 1 record to proceed.')">
            Get Xero Invoices</a>
EOF;
    }

}