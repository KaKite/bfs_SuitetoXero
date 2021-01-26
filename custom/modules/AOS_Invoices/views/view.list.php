<?php

if (! defined('sugarEntry') || ! sugarEntry)
	die('Not A Valid Entry Point');
require_once('custom/include/CustomListViewSmarty.php');
class AOS_InvoicesViewList extends ViewList {

	function preDisplay() {

		// CustomListViewSmarty used for Massupdate text field
		$this->lv = new CustomListViewSmarty();

	}

}?>
