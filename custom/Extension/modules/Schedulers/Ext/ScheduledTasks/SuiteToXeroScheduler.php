<?php

$job_strings[] = 'suiteToXeroScheduler';

/**
 * @return bool
 */
function suiteToXeroScheduler()
{
	require('custom/entrypoints/XeroOAuth-PHP-master/BfsSuitetoXero.php');
	$bfsSuitetoXero = new BfsSuitetoXero();
	if (!$bfsSuitetoXero->checkLicense()) {
		return false;
	}
	if (!$bfsSuitetoXero->checkSetConfig()) {
		return false;
	}
	if (!$bfsSuitetoXero->getSetRefreshToken()) {
		return false;
	}

	$bfsSuitetoXero->setExecuteTime(); // set this var so in running cron all fetched records should be created/updated before this tie

	if ($bfsSuitetoXero->config['synch_with_xero'] == 'to_xero') {
		$bfsSuitetoXero->syncToXero();
	}

	if ($bfsSuitetoXero->config['synch_with_xero'] == 'from_xero') {
		$bfsSuitetoXero->syncFromXero();
	}
	return true;
}
