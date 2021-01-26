<?php

class InvoiceButtonsList { 
	function add()
	{
		switch ($GLOBALS['app']->controller->action) 
		{
			case "listview": // Add buttons to List View
				$button_code = <<<EOQ
<script type="text/javascript">
$(document).ready(function(){
var button = $('<li><a href="javascript:void(0)" onclick="return sListView.send_form(true, \'AOS_Invoices\', \'index.php?entryPoint=xeroLink&func=massSendInvToXero&module=AOS_Invoices\',\'Please select at least 1 record to proceed.\')">Create Invoices in Xero</a></li>');
var button1 = $('<li><a href="javascript:void(0)" onclick="return sListView.send_form(true, \'AOS_Invoices\', \'index.php?entryPoint=xeroLink&func=massUpdInvToXero&module=AOS_Invoices\',\'Please select at least 1 record to proceed.\')">Update Invoices TO Xero</a></li>');
var button2 = $('<li><a href="javascript:void(0)" onclick="return sListView.send_form(true, \'AOS_Invoices\', \'index.php?entryPoint=xeroLink&func=massUpdInvFromXero&module=AOS_Invoices\',\'Please select at least 1 record to proceed.\')">Update Invoices FROM Xero</a></li>');
// Add item to "bulk actions" dropdown button on list view
$("#actionLinkTop").sugarActionMenu('addItem',{item:button});
$("#actionLinkTop").sugarActionMenu('addItem',{item:button1});
$("#actionLinkTop").sugarActionMenu('addItem',{item:button2});
});
</script>
EOQ;
				echo $button_code;
				break;
		}
	}
}