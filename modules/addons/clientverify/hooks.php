<?php

declare(strict_types=1);

defined("WHMCS") or die("Access Denied");

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Hook: Block checkout if selected gateway requires verification
 * and the client is not verified.
 */
add_hook('ShoppingCartValidateCheckout', 1, function (array $vars): array {
    $clientId = (int) ($vars['userid'] ?? 0);
    $paymentMethod = $vars['paymentmethod'] ?? '';

    if ($clientId === 0 || $paymentMethod === '') {
        return [];
    }

    // Check if this gateway requires verification
    $gateway = Capsule::table('mod_clientverify_gateways')
        ->where('gateway', $paymentMethod)
        ->where('enforce', true)
        ->first();

    if (!$gateway) {
        return [];
    }

    // Check if client is verified
    $client = Capsule::table('mod_clientverify_clients')
        ->where('client_id', $clientId)
        ->where('is_verified', true)
        ->first();

    if ($client) {
        return [];
    }

    return [
        'Your account must be verified before using this payment method.',
        'Please navigate to Account → Account Verification to start the verification process.',
        'Alternatively, select a different payment method.',
    ];
});

/**
 * Hook: Add "Account Verification" link to the client area
 * Account dropdown for unverified clients.
 */
add_hook('ClientAreaNavbars', 1, function (): void {
    $client = Menu::context('client');

    if (is_null($client)) {
        return;
    }

    $secondaryNavbar = Menu::secondaryNavbar();

    if (is_null($secondaryNavbar)) {
        return;
    }

    $accountMenu = $secondaryNavbar->getChild('Account');

    if (is_null($accountMenu)) {
        return;
    }

    $isVerified = Capsule::table('mod_clientverify_clients')
        ->where('client_id', $client->id)
        ->value('is_verified');

    // Show link only for unverified clients
    if (!$isVerified) {
        $accountMenu->addChild('clientverify', [
            'label' => 'Account Verification',
            'uri' => '/index.php?m=clientverify',
            'order' => 1,
        ]);
    }
});

/**
 * Hook: Inject module CSS and JS variables into client area pages.
 */
add_hook('ClientAreaHeadOutput', 1, function (array $vars): string {
    $cssPath = '../modules/addons/clientverify/assets/css/module.css';
    $jsPath = '../modules/addons/clientverify/assets/js/client.js';

    // Get module settings to pass to JS
    $docTypes = Capsule::table('tbladdonmodules')->where('module', 'clientverify')->where('setting', 'docTypes')->value('value') ?: 'Passport,National ID,Driver License';
    $maxFileSize = Capsule::table('tbladdonmodules')->where('module', 'clientverify')->where('setting', 'maxFileSize')->value('value') ?: '10';
    $allowedExt = Capsule::table('tbladdonmodules')->where('module', 'clientverify')->where('setting', 'allowedExtensions')->value('value') ?: 'jpg,jpeg,png,pdf';

    // Format for JS
    $maxBytes = ((int) $maxFileSize) * 1024 * 1024;
    $jsDocTypes = json_encode(array_map('trim', explode(',', $docTypes)));
    $jsAllowedExt = json_encode($allowedExt);

    $scriptVars = <<<HTML
<script>
    window.cvModuleLink = 'index.php?m=clientverify';
    window.cvDocTypes = {$jsDocTypes};
    window.cvAllowedExtensions = {$jsAllowedExt};
    window.cvMaxFileSize = {$maxBytes};
</script>
HTML;

    return '<link rel="stylesheet" href="' . $cssPath . '">' . "\n"
        . $scriptVars . "\n"
        . '<script defer src="' . $jsPath . '"></script>';
});
