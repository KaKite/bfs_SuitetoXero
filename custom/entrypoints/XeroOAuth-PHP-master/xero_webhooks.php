<?php
$payload = file_get_contents("php://input");

require_once('./custom/entrypoints/XeroOAuth-PHP-master/BfsSuitetoXeroHook.php');
$bfsSuitetoXeroHook = new BfsSuitetoXeroHook();

$bfsSuitetoXeroHook->checkLicense();
$bfsSuitetoXeroHook->checkSetConfig();
$bfsSuitetoXeroHook->writeLogger($_SERVER['HTTP_X_XERO_SIGNATURE']);
$bfsSuitetoXeroHook->checkHashSignature($payload, $_SERVER['HTTP_X_XERO_SIGNATURE']);

$bfsSuitetoXeroHook->writeLogger($payload);
$payloadData = json_decode($payload, true);
$bfsSuitetoXeroHook->writeLogger($payloadData);
if (count($payloadData['events']) > 0 && $payloadData['lastEventSequence'] != $bfsSuitetoXeroHook->config['xero_last_sequence']) {
    if ($bfsSuitetoXeroHook->getSetRefreshToken()) { //setting up fresh token
        foreach ($payloadData['events'] as $event) {
            $bfsSuitetoXeroHook->payload = $event;
            $bfsSuitetoXeroHook->syncFromXero();
        }
        $bfsSuitetoXeroHook->saveLastSequence($payloadData['lastEventSequence']);
    }
}

header($bfsSuitetoXeroHook->responseHeader);
