<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use PostFinanceCheckout\PluginCore\Sdk\SdkProvider;
use PostFinanceCheckout\PluginCore\Settings\Settings;
use PostFinanceCheckout\PluginCore\Examples\Common\SimpleLogger;
use PostFinanceCheckout\PluginCore\Examples\Common\EnvSettingsProvider;
use PostFinanceCheckout\PluginCore\Examples\Common\FilePersistence;
use PostFinanceCheckout\PluginCore\Examples\Common\TransactionIdLoader;

// Load helpers
require_once __DIR__ . '/SimpleLogger.php';
require_once __DIR__ . '/EnvSettingsProvider.php';
require_once __DIR__ . '/FilePersistence.php';
require_once __DIR__ . '/TransactionIdLoader.php';

// 1. Credentials Check
$spaceId = getenv('PLUGINCORE_DEMO_SPACE_ID');
$userId = getenv('PLUGINCORE_DEMO_USER_ID');
$apiSecret = getenv('PLUGINCORE_DEMO_API_SECRET');

if (!$spaceId || !$userId || !$apiSecret) {
    echo "ERROR: Missing Credentials.\n";
    echo "Please set PLUGINCORE_DEMO_SPACE_ID, PLUGINCORE_DEMO_USER_ID, and PLUGINCORE_DEMO_API_SECRET.\n";
    exit(1);
}

// 2. Initialize Services
$logger = new SimpleLogger();

$settingsProvider = new EnvSettingsProvider();
$settings = new Settings($settingsProvider);

$sdkProvider = new SdkProvider($settings);

// Return initialized components
return [
    'spaceId' => (int)$spaceId,
    'userId' => (int)$userId,
    'apiSecret' => $apiSecret,
    'logger' => $logger,
    'settings' => $settings,
    'sdkProvider' => $sdkProvider,
    'sdk_provider' => $sdkProvider, // alias
    'settings_provider' => $settingsProvider
];
