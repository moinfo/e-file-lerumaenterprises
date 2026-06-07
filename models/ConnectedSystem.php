<?php
class ConnectedSystem {
    private DB $db;

    public function __construct() {
        $this->db = new DB();
    }

    // ── System registry ────────────────────────────────────────────────

    public function all(): array {
        $rows = $this->db->query(
            "SELECT cs.*, COUNT(efr.id) AS file_count
             FROM connected_systems cs
             LEFT JOIN external_file_refs efr ON efr.connected_system_id = cs.id
                 AND efr.status = 'active'
                 AND EXISTS (SELECT 1 FROM archives a WHERE a.id = efr.archive_id AND a.completed = 0)
             GROUP BY cs.id ORDER BY cs.name",
            'SELECT'
        );
        return is_array($rows) ? $rows : [];
    }

    public function find(int $id): ?array {
        $row = $this->db->query(
            "SELECT * FROM connected_systems WHERE id = ?",
            'SELECT', true, [$id]
        );
        return $row ?: null;
    }

    public function findByApiKey(string $key): ?array {
        $row = $this->db->query(
            "SELECT * FROM connected_systems WHERE api_key = ? AND is_active = 1",
            'SELECT', true, [$key]
        );
        return $row ?: null;
    }

    public function create(string $name, string $description, ?int $subFolderId, ?int $docTypeId, string $extensions, int $maxMb): array {
        $apiKey = bin2hex(random_bytes(32));
        $slug   = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($name)));
        $id = (int) $this->db->query(
            "INSERT INTO connected_systems (name, slug, description, api_key, default_sub_folder_id, default_document_type_id, allowed_extensions, max_file_size_mb) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            'INSERT', null,
            [$name, $slug, $description, $apiKey, $subFolderId, $docTypeId, $extensions, $maxMb]
        );
        return ['id' => $id, 'api_key' => $apiKey];
    }

    public function update(int $id, string $name, string $description, ?int $subFolderId, ?int $docTypeId, string $extensions, int $maxMb, ?string $callbackUrl = null): void {
        $this->db->query(
            "UPDATE connected_systems SET name=?, description=?, default_sub_folder_id=?, default_document_type_id=?, allowed_extensions=?, max_file_size_mb=?, callback_url=? WHERE id=?",
            'UPDATE', null,
            [$name, $description, $subFolderId, $docTypeId, $extensions, $maxMb, $callbackUrl, $id]
        );
    }

    public function rotateKey(int $id): string {
        $newKey = bin2hex(random_bytes(32));
        $this->db->query(
            "UPDATE connected_systems SET api_key = ? WHERE id = ?",
            'UPDATE', null, [$newKey, $id]
        );
        return $newKey;
    }

    public function toggle(int $id, bool $active): void {
        $this->db->query(
            "UPDATE connected_systems SET is_active = ? WHERE id = ?",
            'UPDATE', null, [(int) $active, $id]
        );
    }

    public function delete(int $id): void {
        // Delete refs first (no FK cascade for hosting compatibility)
        $this->db->query(
            "DELETE FROM external_file_refs WHERE connected_system_id = ?",
            'DELETE', null, [$id]
        );
        $this->db->query(
            "DELETE FROM connected_systems WHERE id = ?",
            'DELETE', null, [$id]
        );
    }

    // ── External file references ────────────────────────────────────────

    public function refExists(int $systemId, string $extRefId): bool {
        $row = $this->db->query(
            "SELECT id FROM external_file_refs WHERE connected_system_id = ? AND external_ref_id = ?",
            'SELECT', true, [$systemId, $extRefId]
        );
        return (bool) $row;
    }

    public function createRef(int $systemId, string $extRefId, int $archiveId, ?string $localId = null): int {
        return (int) $this->db->query(
            "INSERT INTO external_file_refs (connected_system_id, external_ref_id, local_id, archive_id, status) VALUES (?, ?, ?, ?, 'active')",
            'INSERT', null, [$systemId, $extRefId, $localId, $archiveId]
        );
    }

    public function findRef(int $systemId, string $extRefId): ?array {
        $row = $this->db->query(
            "SELECT efr.*, a.name AS archive_name, a.path, a.completed, a.description, a.document_date,
                    cs.callback_url, cs.api_key AS system_api_key
             FROM external_file_refs efr
             JOIN archives a ON a.id = efr.archive_id
             JOIN connected_systems cs ON cs.id = efr.connected_system_id
             WHERE efr.connected_system_id = ? AND efr.external_ref_id = ?",
            'SELECT', true, [$systemId, $extRefId]
        );
        return $row ?: null;
    }

    public function softDeleteRef(int $systemId, string $extRefId): bool {
        $affected = (int) $this->db->query(
            "UPDATE external_file_refs SET status = 'deleted_by_source', updated_at = NOW()
             WHERE connected_system_id = ? AND external_ref_id = ? AND status = 'active'",
            'UPDATE', null, [$systemId, $extRefId]
        );
        return $affected > 0;
    }

    public function recentRefs(int $limit = 50, ?int $systemId = null): array {
        if ($systemId) {
            $rows = $this->db->query(
                "SELECT efr.*, a.name AS archive_name, a.completed, cs.name AS system_name
                 FROM external_file_refs efr
                 JOIN archives a ON a.id = efr.archive_id
                 JOIN connected_systems cs ON cs.id = efr.connected_system_id
                 WHERE efr.connected_system_id = ?
                 ORDER BY efr.created_at DESC LIMIT ?",
                'SELECT', false, [$systemId, $limit]
            );
        } else {
            $rows = $this->db->query(
                "SELECT efr.*, a.name AS archive_name, a.completed, cs.name AS system_name
                 FROM external_file_refs efr
                 JOIN archives a ON a.id = efr.archive_id
                 JOIN connected_systems cs ON cs.id = efr.connected_system_id
                 ORDER BY efr.created_at DESC LIMIT {$limit}",
                'SELECT'
            );
        }
        return is_array($rows) ? $rows : [];
    }
}
