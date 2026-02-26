<?php

declare(strict_types=1);

defined("WHMCS") or die("Access Denied");

use Illuminate\Database\Capsule\Manager as Capsule;

if (!defined('CLIENTVERIFY_DIR')) {
    define('CLIENTVERIFY_DIR', __DIR__);
}

require_once __DIR__ . '/lib/VerificationService.php';
require_once __DIR__ . '/lib/AjaxHandler.php';

/**
 * Module configuration for WHMCS.
 *
 * @return array<string, mixed>
 */
function clientverify_config(): array
{
    return [
        'name' => 'Client Verification',
        'description' => 'Manual client identity verification with document upload, admin approval workflow, and per-gateway checkout enforcement.',
        'version' => '1.0.3',
        'author' => '<a href="https://github.com/torikulislamrijon" target="_blank">Torikul Islam Rijon</a>',
        'language' => 'english',
        'fields' => [
            'adminUsername' => [
                'FriendlyName' => 'Admin Username',
                'Type' => 'text',
                'Size' => '30',
                'Default' => 'admin',
                'Description' => 'WHMCS admin username for internal localAPI calls.',
            ],
            'docTypes' => [
                'FriendlyName' => 'Document Types',
                'Type' => 'text',
                'Size' => '80',
                'Default' => 'Passport,National ID,Driver License,Utility Bill',
                'Description' => 'Comma-separated list of document types clients can upload.',
            ],
            'storagePath' => [
                'FriendlyName' => 'Storage Path',
                'Type' => 'text',
                'Size' => '80',
                'Default' => '/tmp/clientverify',
                'Description' => 'Absolute path where client documents will be stored.',
            ],
            'maxFileSize' => [
                'FriendlyName' => 'Max File Size (MB)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '10',
                'Description' => 'Maximum allowed size per uploaded file in megabytes.',
            ],
            'allowedExtensions' => [
                'FriendlyName' => 'Allowed Extensions',
                'Type' => 'text',
                'Size' => '50',
                'Default' => 'jpg,jpeg,png,gif,pdf',
                'Description' => 'Comma-separated list of allowed file extensions.',
            ],
        ],
    ];
}

/**
 * Module activation — creates database tables.
 *
 * @return array{status: string, description: string}
 */
function clientverify_activate(): array
{
    try {
        if (!Capsule::schema()->hasTable('mod_clientverify_clients')) {
            Capsule::schema()->create('mod_clientverify_clients', function ($table) {
                /** @var \Illuminate\Database\Schema\Blueprint $table */
                $table->increments('id');
                $table->unsignedInteger('client_id')->unique();
                $table->boolean('is_verified')->default(false);
                $table->timestamps();
            });
        }

        if (!Capsule::schema()->hasTable('mod_clientverify_documents')) {
            Capsule::schema()->create('mod_clientverify_documents', function ($table) {
                /** @var \Illuminate\Database\Schema\Blueprint $table */
                $table->increments('id');
                $table->unsignedInteger('client_id')->index();
                $table->string('file_hash', 64)->unique();
                $table->string('file_name', 255);
                $table->string('doc_type', 100)->default('Unknown');
                $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Capsule::schema()->hasTable('mod_clientverify_gateways')) {
            Capsule::schema()->create('mod_clientverify_gateways', function ($table) {
                /** @var \Illuminate\Database\Schema\Blueprint $table */
                $table->increments('id');
                $table->string('gateway', 100)->unique();
                $table->boolean('enforce')->default(true);
            });
        }

        logActivity('Client Verification Module: Activated successfully.');

        return [
            'status' => 'success',
            'description' => 'Module activated successfully. Please configure settings above.',
        ];
    } catch (\Exception $e) {
        logActivity('Client Verification Module: Activation failed — ' . $e->getMessage());
        return [
            'status' => 'error',
            'description' => 'Failed to create database tables: ' . $e->getMessage(),
        ];
    }
}

/**
 * Module deactivation — drops database tables.
 *
 * @return array{status: string, description: string}
 */
function clientverify_deactivate(): array
{
    try {
        Capsule::schema()->dropIfExists('mod_clientverify_documents');
        Capsule::schema()->dropIfExists('mod_clientverify_gateways');
        Capsule::schema()->dropIfExists('mod_clientverify_clients');

        logActivity('Client Verification Module: Deactivated and tables removed.');

        return [
            'status' => 'success',
            'description' => 'Module deactivated. All module data has been removed.',
        ];
    } catch (\Exception $e) {
        logActivity('Client Verification Module: Deactivation failed — ' . $e->getMessage());
        return [
            'status' => 'error',
            'description' => 'Failed to remove database tables: ' . $e->getMessage(),
        ];
    }
}

/**
 * Module upgrade handler for schema migrations.
 *
 * @param array<string, mixed> $vars
 */
function clientverify_upgrade(array $vars): void
{
    $currentVersion = $vars['version'];

    // Future upgrades go here:
    // if (version_compare($currentVersion, '1.1.0', '<')) { ... }
}

/**
 * Admin area output handler.
 *
 * @param array<string, mixed> $vars
 */
function clientverify_output(array $vars): void
{
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Handle AJAX requests
    if ($action !== '') {
        $handler = new \ClientVerify\AjaxHandler($vars, 'admin');
        $handler->handle($action);
        return;
    }

    // Render admin dashboard template
    $moduleLink = $vars['modulelink'];
    $LANG = $vars['_lang'] ?? [];

    // Load CSS and JS
    $assetBase = '../modules/addons/clientverify/assets';
    echo '<link rel="stylesheet" href="' . $assetBase . '/css/module.css?v=' . time() . '">';
    echo '<script src="' . $assetBase . '/js/admin.js?v=' . time() . '"></script>';

    // Render Smarty template
    try {
        $smarty = new Smarty();
        $smarty->setCompileDir($GLOBALS['templates_compiledir']);
        $smarty->setTemplateDir(__DIR__ . '/templates/admin');
        $smarty->assign('moduleLink', $moduleLink);
        $smarty->assign('LANG', $LANG);
        $smarty->assign('version', $vars['version']);
        $smarty->assign('docTypes', $vars['docTypes'] ?? 'Passport,National ID,Driver License,Utility Bill');
        $smarty->display('dashboard.tpl');
    } catch (\Exception $e) {
        logActivity('Client Verification Module: Admin render error — ' . $e->getMessage());
        echo '<div class="alert alert-danger">Error loading module interface: '
            . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Client area output handler.
 *
 * @param array<string, mixed> $vars
 * @return array<string, mixed>
 */
function clientverify_clientarea(array $vars): array
{

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Handle AJAX requests
    if ($action !== '') {
        $handler = new \ClientVerify\AjaxHandler($vars, 'client');
        $handler->handle($action);
        exit;
    }

    $LANG = $vars['_lang'] ?? [];

    return [
        'pagetitle' => $LANG['page_title'] ?? 'Account Verification',
        'breadcrumb' => [
            'index.php?m=clientverify' => $LANG['page_title'] ?? 'Account Verification',
        ],
        'templatefile' => 'clientarea',
        'requirelogin' => true,
        'forcessl' => false,
        'vars' => [
            'moduleLink' => $vars['modulelink'],
            'version' => $vars['version'],
            'docTypes' => $vars['docTypes'] ?? 'Passport,National ID,Driver License,Utility Bill',
            'allowedExtensions' => $vars['allowedExtensions'] ?? 'jpg,jpeg,png,gif,pdf',
            'maxFileSize' => $vars['maxFileSize'] ?? '10',
            'LANG' => $LANG,
        ],
    ];
}
