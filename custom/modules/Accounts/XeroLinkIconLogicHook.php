<?php

class XeroLinkLogicHook
{
    public function xerolink(SugarBean $bean, $event, $arguments)
    {
        if (strlen($bean->xero_link_c) != "") {
            $bean->xero_link_c = "<a style='border:none!important'; target='_blank' href=$bean->xero_link_c><img style='height:25px; padding-left:35%;' src='custom/logo-xero-blue.svg' alt='Link to Xero'/></a>";
        }
    }
}
