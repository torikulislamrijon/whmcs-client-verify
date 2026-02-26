<?php

declare(strict_types=1);

namespace ClientVerify;

defined("WHMCS") or die("Access Denied");

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Core business logic for client verification.
 *
 * Handles all database operations for clients, documents, and gateways.
 */
class VerificationService
{
    /**
     * Get paginated client list with verification status.
     *
     * @param int    $page
     * @param int    $limit
     * @param string $sortCol
     * @param string $sortDir
     * @return array<string, mixed>
     */
    public function getClients(int $page = 1, int $limit = 25, string $sortCol = 'id', string $sortDir = 'asc'): array
    {
        $allowedCols = ['id', 'firstname', 'lastname', 'email', 'is_verified'];
        $sortCol = in_array($sortCol, $allowedCols, true) ? $sortCol : 'id';
        $sortDir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        $query = Capsule::table('tblclients as c')
            ->leftJoin('mod_clientverify_clients as cv', 'cv.client_id', '=', 'c.id')
            ->select(
                'c.id',
                'c.firstname',
                'c.lastname',
                'c.email',
                'c.companyname',
                Capsule::raw('COALESCE(cv.is_verified, 0) as is_verified')
            );

        $totalRecords = $query->count();
        $totalPages = $totalRecords > 0 ? (int) ceil($totalRecords / $limit) : 1;

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $limit;
        if ($offset < 0) {
            $offset = 0;
        }

        $dbSortCol = match ($sortCol) {
            'is_verified' => Capsule::raw('COALESCE(cv.is_verified, 0)'),
            default => 'c.' . $sortCol,
        };

        $rows = $query
            ->orderBy($dbSortCol, $sortDir)
            ->offset($offset)
            ->limit($limit)
            ->get();

        $clients = [];
        foreach ($rows as $row) {
            $email = strtolower(trim($row->email ?? ''));
            $clients[] = [
                'id' => $row->id,
                'firstname' => $row->firstname,
                'lastname' => $row->lastname,
                'email' => $row->email,
                'companyname' => $row->companyname,
                'is_verified' => (bool) $row->is_verified,
                'gravatar' => 'https://www.gravatar.com/avatar/' . md5($email) . '?s=80&d=mp',
            ];
        }

        return [
            'page' => $page,
            'total' => $totalPages,
            'records' => $totalRecords,
            'rows' => $clients,
        ];
    }

    /**
     * Toggle client verification status.
     *
     * @param int  $clientId
     * @param bool $verified
     */
    public function setClientVerified(int $clientId, bool $verified): void
    {
        Capsule::table('mod_clientverify_clients')->updateOrInsert(
            ['client_id' => $clientId],
            [
                'is_verified' => $verified,
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );

        $status = $verified ? 'verified' : 'unverified';
        \logActivity("Client Verification: Client #{$clientId} marked as {$status}.");
    }

    /**
     * Check if a client is verified.
     *
     * @param int $clientId
     * @return bool
     */
    public function isClientVerified(int $clientId): bool
    {
        $record = Capsule::table('mod_clientverify_clients')
            ->where('client_id', $clientId)
            ->first();

        return $record ? (bool) $record->is_verified : false;
    }

    /**
     * Get documents for a specific client.
     *
     * @param int    $clientId
     * @param string $storagePath
     * @return array<int, array<string, mixed>>
     */
    public function getDocuments(int $clientId, string $storagePath): array
    {
        $docs = Capsule::table('mod_clientverify_documents')
            ->where('client_id', $clientId)
            ->orderBy('created_at', 'desc')
            ->get();

        $result = [];
        foreach ($docs as $doc) {
            $filePath = rtrim($storagePath, '/\\') . DIRECTORY_SEPARATOR
                . $clientId . DIRECTORY_SEPARATOR . $doc->file_name;

            $thumbnail = '';
            if (file_exists($filePath)) {
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
                    $data = file_get_contents($filePath);
                    $thumbnail = 'data:image/' . $ext . ';base64,' . base64_encode($data);
                }
            }

            $result[] = [
                'id' => $doc->id,
                'file_hash' => $doc->file_hash,
                'file_name' => $doc->file_name,
                'doc_type' => $doc->doc_type,
                'status' => $doc->status,
                'thumbnail' => $thumbnail,
                'created_at' => $doc->created_at,
            ];
        }

        return $result;
    }

    /**
     * Set document verification status (accept/reject).
     *
     * @param string $fileHash
     * @param string $status  'accepted' or 'rejected'
     * @return bool
     */
    public function setDocumentStatus(string $fileHash, string $status): bool
    {
        $allowed = ['pending', 'accepted', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $affected = Capsule::table('mod_clientverify_documents')
            ->where('file_hash', $fileHash)
            ->update(['status' => $status]);

        if ($affected > 0) {
            \logActivity("Client Verification: Document {$fileHash} set to {$status}.");
        }

        return $affected > 0;
    }

    /**
     * Delete a document (file + DB record).
     *
     * @param string $fileHash
     * @param string $storagePath
     * @return bool
     */
    public function deleteDocument(string $fileHash, string $storagePath): bool
    {
        $doc = Capsule::table('mod_clientverify_documents')
            ->where('file_hash', $fileHash)
            ->first();

        if (!$doc) {
            return false;
        }

        // Delete physical file
        $filePath = rtrim($storagePath, '/\\') . DIRECTORY_SEPARATOR
            . $doc->client_id . DIRECTORY_SEPARATOR . $doc->file_name;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        // Delete DB record
        Capsule::table('mod_clientverify_documents')
            ->where('file_hash', $fileHash)
            ->delete();

        \logActivity("Client Verification: Document {$doc->file_name} deleted for client #{$doc->client_id}.");

        return true;
    }

    /**
     * Upload and store a client document.
     *
     * @param int                  $clientId
     * @param array<string, mixed> $file       $_FILES['file'] entry
     * @param string               $docType
     * @param string               $storagePath
     * @param string               $allowedExt  Comma-separated extensions
     * @param int                  $maxSizeMb
     * @return array{success: bool, message: string}
     */
    public function uploadDocument(
        int $clientId,
        array $file,
        string $docType,
        string $storagePath,
        string $allowedExt,
        int $maxSizeMb
    ): array {
        // Validate file upload
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No valid file uploaded.'];
        }

        // Validate extension
        $originalName = basename($file['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = array_map('trim', explode(',', strtolower($allowedExt)));

        if (!in_array($ext, $allowed, true)) {
            return ['success' => false, 'message' => 'File type not allowed. Allowed: ' . $allowedExt];
        }

        // Validate file size
        $maxBytes = $maxSizeMb * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            return ['success' => false, 'message' => "File exceeds maximum size of {$maxSizeMb}MB."];
        }

        // Prepare storage directory
        $targetDir = rtrim($storagePath, '/\\') . DIRECTORY_SEPARATOR . $clientId;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create storage directory.'];
        }

        // Sanitize filename
        $safeName = preg_replace('/[^\w._-]+/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $safeName = $safeName . '.' . $ext;

        // Ensure unique filename
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $safeName;
        $counter = 1;
        while (file_exists($targetPath)) {
            $safeName = preg_replace('/[^\w._-]+/', '_', pathinfo($originalName, PATHINFO_FILENAME))
                . '_' . $counter . '.' . $ext;
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $safeName;
            $counter++;
        }

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'message' => 'Failed to store uploaded file.'];
        }

        // Generate hash and save to DB
        $fileHash = hash('sha256', $clientId . '_' . $safeName . '_' . microtime(true));

        try {
            Capsule::table('mod_clientverify_documents')->insert([
                'client_id' => $clientId,
                'file_hash' => $fileHash,
                'file_name' => $safeName,
                'doc_type' => $docType,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            @unlink($targetPath);
            return ['success' => false, 'message' => 'Failed to save document record: ' . $e->getMessage()];
        }

        \logActivity("Client Verification: Client #{$clientId} uploaded document '{$safeName}' ({$docType}).");

        return ['success' => true, 'message' => 'Document uploaded successfully.'];
    }

    /**
     * Get active payment gateways with enforcement status.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getGateways(): array
    {
        $rows = Capsule::table('tblpaymentgateways as pg')
            ->leftJoin('mod_clientverify_gateways as cvg', 'cvg.gateway', '=', 'pg.gateway')
            ->where('pg.setting', 'visible')
            ->where('pg.value', 'on')
            ->select(
                'pg.gateway',
                Capsule::raw('COALESCE(cvg.enforce, 0) as enforce')
            )
            ->groupBy('pg.gateway')
            ->get();

        $gateways = [];
        foreach ($rows as $row) {
            $gateways[] = [
                'gateway' => $row->gateway,
                'enforce' => (bool) $row->enforce,
            ];
        }

        return $gateways;
    }

    /**
     * Toggle gateway enforcement.
     *
     * @param string $gateway
     * @param bool   $enforce
     */
    public function setGatewayEnforcement(string $gateway, bool $enforce): void
    {
        if ($enforce) {
            Capsule::table('mod_clientverify_gateways')->updateOrInsert(
                ['gateway' => $gateway],
                ['enforce' => true]
            );
        } else {
            Capsule::table('mod_clientverify_gateways')
                ->where('gateway', $gateway)
                ->delete();
        }

        $status = $enforce ? 'enabled' : 'disabled';
        \logActivity("Client Verification: Gateway enforcement {$status} for {$gateway}.");
    }
}
