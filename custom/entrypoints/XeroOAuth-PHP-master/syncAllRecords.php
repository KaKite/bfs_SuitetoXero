<?php
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

if ($_REQUEST['type'] == 'send') {
    $bfsSuitetoXero->syncToXero();
}

if ($_REQUEST['type'] == 'get') {
    $bfsSuitetoXero->syncFromXero();
}

if (count($bfsSuitetoXero->alertMsg) == 0) {
    echo ("<SCRIPT LANGUAGE='JavaScript'>
                        window.alert('No Information updated.')
                        window.location.href='index.php?module=bfs_SuitetoXero&action=index';
            </SCRIPT>");
} else {
    echo ("<SCRIPT LANGUAGE='JavaScript'>
                        window.alert('" . implode('\n', $bfsSuitetoXero->alertMsg) . "')
                        window.location.href='index.php?module=bfs_SuitetoXero&action=index';
            </SCRIPT>");
}
