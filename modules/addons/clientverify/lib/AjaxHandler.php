<?php

declare(strict_types=1);

namespace ClientVerify;

defined("WHMCS") or die("Access Denied");

/**
 * Routes AJAX requests to VerificationService methods.
 *
 * Returns JSON responses for both admin and client contexts.
 */
class AjaxHandler
{
    /** @var array<string, mixed> */
    private array $vars;
    private string $context;
    private VerificationService $service;

    /**
     * @param array<string, mixed> $vars    Module vars from WHMCS
     * @param string               $context 'admin' or 'client'
     */
    public function __construct(array $vars, string $context = 'admin')
    {
        $this->vars = $vars;
        $this->context = $context;
        $this->service = new VerificationService();
    }

    /**
     * Handle an AJAX action.
     *
     * @param string $action
     */
    public function handle(string $action): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $result = match ($action) {
                // Admin actions
                'getClients' => $this->getClients(),
                'setClientVerified' => $this->setClientVerified(),
                'getDocuments' => $this->getDocuments(),
                'setDocumentStatus' => $this->setDocumentStatus(),
                'deleteDocument' => $this->deleteDocument(),
                'getGateways' => $this->getGateways(),
                'setGatewayEnforcement' => $this->setGatewayEnforcement(),

                // Client actions
                'getMyDocuments' => $this->getMyDocuments(),
                'getMyStatus' => $this->getMyStatus(),
                'uploadDocument' => $this->uploadDocument(),
                'deleteMyDocument' => $this->deleteMyDocument(),
                'getDocTypes' => $this->getDocTypes(),

                default => ['error' => 'Unknown action: ' . $action],
            };

            echo json_encode(['status' => 'ok', 'data' => $result]);
        } catch (\Exception $e) {
            \logActivity('Client Verification AJAX Error: ' . $e->getMessage());
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }

        exit;
    }

    // ─── Admin Actions ───────────────────────────────────────────────

    private function getClients(): array
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(500, (int) ($_GET['limit'] ?? 25)));
        $sortCol = $_GET['sort'] ?? 'id';
        $sortDir = $_GET['dir'] ?? 'asc';
        $search = trim($_GET['search'] ?? '');

        return $this->service->getClients($page, $limit, $sortCol, $sortDir, $search);
    }

    private function setClientVerified(): array
    {
        $clientId = (int) ($_POST['client_id'] ?? 0);
        $verified = filter_var($_POST['verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($clientId <= 0) {
            throw new \InvalidArgumentException('Invalid client ID.');
        }

        $this->service->setClientVerified($clientId, $verified);

        return ['client_id' => $clientId, 'is_verified' => $verified];
    }

    private function getDocuments(): array
    {
        $clientId = (int) ($_GET['client_id'] ?? 0);

        if ($clientId <= 0) {
            throw new \InvalidArgumentException('Invalid client ID.');
        }

        $storagePath = $this->vars['storagePath'] ?? '/tmp/clientverify';

        return $this->service->getDocuments($clientId, $storagePath);
    }

    private function setDocumentStatus(): array
    {
        $fileHash = $_POST['file_hash'] ?? '';
        $status = $_POST['status'] ?? '';

        if ($fileHash === '' || $status === '') {
            throw new \InvalidArgumentException('File hash and status are required.');
        }

        $success = $this->service->setDocumentStatus($fileHash, $status);

        return ['success' => $success, 'status' => $status];
    }

    private function deleteDocument(): array
    {
        $fileHash = $_POST['file_hash'] ?? $_GET['file_hash'] ?? '';

        if ($fileHash === '') {
            throw new \InvalidArgumentException('File hash is required.');
        }

        $storagePath = $this->vars['storagePath'] ?? '/tmp/clientverify';
        $success = $this->service->deleteDocument($fileHash, $storagePath);

        return ['success' => $success];
    }

    private function getGateways(): array
    {
        return $this->service->getGateways();
    }

    private function setGatewayEnforcement(): array
    {
        $gateway = $_POST['gateway'] ?? '';
        $enforce = filter_var($_POST['enforce'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($gateway === '') {
            throw new \InvalidArgumentException('Gateway name is required.');
        }

        $this->service->setGatewayEnforcement($gateway, $enforce);

        return ['gateway' => $gateway, 'enforce' => $enforce];
    }

    // ─── Client Actions ──────────────────────────────────────────────

    private function getClientId(): int
    {
        $clientId = (int) ($_SESSION['uid'] ?? 0);

        if ($clientId <= 0) {
            throw new \RuntimeException('Authentication required.');
        }

        return $clientId;
    }

    private function getMyDocuments(): array
    {
        $clientId = $this->getClientId();
        $storagePath = $this->vars['storagePath'] ?? '/tmp/clientverify';

        return $this->service->getDocuments($clientId, $storagePath);
    }

    private function getMyStatus(): array
    {
        $clientId = $this->getClientId();
        $isVerified = $this->service->isClientVerified($clientId);

        return ['is_verified' => $isVerified];
    }

    private function uploadDocument(): array
    {
        $clientId = $this->getClientId();

        if (!isset($_FILES['document'])) {
            throw new \InvalidArgumentException('No file uploaded.');
        }

        $docType = $_POST['doc_type'] ?? 'Unknown';
        $storagePath = $this->vars['storagePath'] ?? '/tmp/clientverify';
        $allowedExt = $this->vars['allowedExtensions'] ?? 'jpg,jpeg,png,gif,pdf';
        $maxSizeMb = (int) ($this->vars['maxFileSize'] ?? 10);

        return $this->service->uploadDocument(
            $clientId,
            $_FILES['document'],
            $docType,
            $storagePath,
            $allowedExt,
            $maxSizeMb
        );
    }

    private function deleteMyDocument(): array
    {
        $clientId = $this->getClientId();
        $fileHash = $_POST['file_hash'] ?? '';

        if ($fileHash === '') {
            throw new \InvalidArgumentException('File hash is required.');
        }

        // Ensure the document belongs to this client
        $doc = \Illuminate\Database\Capsule\Manager::table('mod_clientverify_documents')
            ->where('file_hash', $fileHash)
            ->where('client_id', $clientId)
            ->first();

        if (!$doc) {
            throw new \RuntimeException('Document not found or access denied.');
        }

        $storagePath = $this->vars['storagePath'] ?? '/tmp/clientverify';
        $success = $this->service->deleteDocument($fileHash, $storagePath);

        return ['success' => $success];
    }

    private function getDocTypes(): array
    {
        $docTypes = $this->vars['docTypes'] ?? 'Passport,National ID,Driver License,Utility Bill';

        return array_map('trim', explode(',', $docTypes));
    }
}
