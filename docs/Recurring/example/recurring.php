<?php

namespace MyPlugin\ExampleRecurringImplementation;

/**
 * Recurring Payment Example
 *
 * This script demonstrates how to trigger a recurring payment (MIT) on an existing transaction.
 *
 * USAGE:
 * php recurring.php [transaction_id]
 */

use PostFinanceCheckout\PluginCore\Examples\Common\TransactionIdLoader;
use PostFinanceCheckout\PluginCore\LineItem\LineItemConsistencyService;
use PostFinanceCheckout\PluginCore\Sdk\SdkV1\RecurringTransactionGateway;
use PostFinanceCheckout\PluginCore\Sdk\SdkV1\TokenGateway;
use PostFinanceCheckout\PluginCore\Sdk\SdkV1\TransactionGateway;
use PostFinanceCheckout\PluginCore\Token\TokenService;
use PostFinanceCheckout\PluginCore\Transaction\RecurringTransactionService;
use PostFinanceCheckout\PluginCore\Transaction\TransactionService;

error_reporting(E_ALL & ~E_DEPRECATED);

/** @var array $common */
$common = require __DIR__ . '/../../examples/Common/bootstrap.php';

$spaceId = $common['spaceId'];
$sdkProvider = $common['sdkProvider'];
$logger = $common['logger'];
$settings = $common['settings'];
// 1. Load Transaction ID
try {
    $transactionId = TransactionIdLoader::load($argv);
} catch (\Exception $e) {
    exit($e->getMessage());
}

// 2. Setup Services
$transactionGateway = new TransactionGateway($sdkProvider, $logger, $settings);
$recurringGateway = new RecurringTransactionGateway($sdkProvider, $logger);
$consistencyService = new LineItemConsistencyService($settings, $logger);

$transactionService = new TransactionService($transactionGateway, $consistencyService, $logger);
$tokenService = new TokenService(new TokenGateway($sdkProvider, $logger), $logger);

$recurringService = new RecurringTransactionService(
    $transactionService,
    $recurringGateway,
    $tokenService,
    $logger
);

echo "Attempting to Process Recurring Payment for Transaction ID: $transactionId\n";

// 3. Execute Recurring Payment
try {
    $newTransaction = $recurringService->processRecurringPayment((int)$spaceId, $transactionId);

    echo "---------------------------------------------------\n";
    echo "RECURRING PAYMENT PROCESSED\n";
    echo "---------------------------------------------------\n";
    echo "New Transaction ID: " . $newTransaction->id . "\n";
    echo "New State:          " . $newTransaction->state->value . "\n";
    echo "---------------------------------------------------\n";
} catch (\Throwable $e) {
    echo "---------------------------------------------------\n";
    echo "RECURRING PAYMENT FAILED\n";
    echo "---------------------------------------------------\n";
    echo "Reason: " . $e->getMessage() . "\n";
    echo "Hint: Ensure the original transaction was successful and has a valid token.\n";
    echo "---------------------------------------------------\n";
    exit(1);
}
