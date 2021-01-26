<?php
if (! defined('sugarEntry') || ! sugarEntry) die('Not A Valid Entry Point');

// check for existence of detailviewdefs.php and view.list files - notify of backup
function pre_install() {
	$msg = '';
if (file_exists('custom/modules/Accounts/metadata/detailviewdefs.php')||file_exists('custom/modules/Accounts/views/view.list.php')||file_exists('custom/modules/Contacts/metadata/detailviewdefs.php')||file_exists('custom/modules/Contacts/views/view.list.php')||file_exists('custom/modules/AOS_Invoices/metadata/detailviewdefs.php')||file_exists('custom/modules/AOS_Quotes/metadata/detailviewdefs.php')){
	$t = time();
	//Accounts	
		if (file_exists('custom/modules/Accounts/metadata/detailviewdefs.php')){
			// backup and create message
			rename('custom/modules/Accounts/metadata/detailviewdefs.php','custom/modules/Accounts/metadata/detailviewdefs.php.BACKUP-'.$t.'');
			$msg .= '\\ncustom/modules/Accounts/metadata/\\n';
		}
		if (file_exists('custom/modules/Accounts/views/view.list.php')){
			// backup and create message
			rename('custom/modules/Accounts/views/view.list.php','custom/modules/Accounts/views/view.list.php.BACKUP-'.$t.'');
			$msg .= '\\ncustom/modules/Accounts/views/\\n';
		}
	//Contacts
		if (file_exists('custom/modules/Contacts/metadata/detailviewdefs.php')){
			// backup and create message
			rename('custom/modules/Contacts/metadata/detailviewdefs.php','custom/modules/Contacts/metadata/detailviewdefs.php.BACKUP-'.$t.'');
			$msg .= 'custom/modules/Contacts/metadata/\\n';
		}
		if (file_exists('custom/modules/Contacts/views/view.list.php')){
			// backup and create message
			rename('custom/modules/Contacts/views/view.list.php','custom/modules/Contacts/views/view.list.php.BACKUP-'.$t.'');
			$msg .= 'custom/modules/Contacts/views/\\n';
		} 
	//AOS_Invoices
		if (file_exists('custom/modules/AOS_Invoices/metadata/detailviewdefs.php')){
			// backup and create message
			rename('custom/modules/AOS_Invoices/metadata/detailviewdefs.php','custom/modules/AOS_Invoices/metadata/detailviewdefs.php.BACKUP-'.$t.'');
			$msg .= 'custom/modules/AOS_Invoices/metadata/\\n';
		}
	//AOS_Quotes
		if (file_exists('custom/modules/AOS_Quotes/metadata/detailviewdefs.php')){
			// backup and create message
			rename('custom/modules/AOS_Quotes/metadata/detailviewdefs.php','custom/modules/AOS_Quotes/metadata/detailviewdefs.php.BACKUP-'.$t.'');
			$msg .= 'custom/modules/AOS_Quotes/metadata/\\n';
		}
	echo ("<SCRIPT LANGUAGE='JavaScript'>
			window.alert('Custom detail view files were detected in the following locations\\n$msg \\nBackups can be recovered from detailveiwdefs.php.BACKUP in those directories\\nPlease see our FAQ for details')
			</SCRIPT>");
	}
}
?>