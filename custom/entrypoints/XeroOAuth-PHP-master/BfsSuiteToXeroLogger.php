<?php

require_once 'include/SugarLogger/SugarLogger.php';

class BfsSuiteToXeroLogger extends SugarLogger
{
    protected $logfile = 'xero';
    protected $ext = '.log';
    protected $dateFormat = '%c';
    protected $logSize = '10MB';
    protected $maxLogs = 10;
    protected $filesuffix = "";
    protected $date_suffix = "";
    protected $log_dir = './';

    public function __construct()
    {
        $this->_doInitialization();
    }
}