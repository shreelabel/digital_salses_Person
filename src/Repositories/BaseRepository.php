<?php
declare(strict_types=1);

namespace SLC\Repositories;

use SLC\Core\Database;

/**
 * Generic CRUD over a single table with soft-delete support.
 * Entity repositories extend this and add custom queries.
 */
abstract class BaseRepository
{
    protected string $table;
    protected bool $softDelete = true;

    public function __construct()
    {
        if (!isset($this->table) || $this->table === '') {
            // Convention: CompanyRepository -> slc_companies
            $short = (new \ReflectionClass($this))->getShortName();
            $name = strtolower(preg_replace('/Repository$/', '', $short));
            $this->table = 'slc_' . $name . 's';
        }
    }

    public function table(): string
    {
        return $this->table;
    }

    /** Build the soft-delete WHERE clause fragment. */
    protected function scope(): string
    {
        return $this->softDelete ? 'deleted_at IS NULL' : '1=1';
    }

    public function find(int $id): ?array
    {
        $scopedUserId = \SLC\Core\Auth::scopedUserId();
        $scopeSql = $this->scope();
        $params = ['id' => $id];
        if ($scopedUserId !== null) {
            $scopeSql .= ' AND assigned_to = :_scoped_user_id';
            $params['_scoped_user_id'] = $scopedUserId;
        }
        return Database::fetch(
            "SELECT * FROM {$this->table} WHERE id = :id AND {$scopeSql} LIMIT 1",
            $params
        );
    }

    /**
     * Paginated list with filters + ordering.
     * @return array{data:array,total:int,page:int,perPage:int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20, string $orderBy = 'id', string $dir = 'DESC'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $allowed = $this->sortable();
        $orderBy = in_array($orderBy, $allowed, true) ? $orderBy : 'id';

        [$where, $params] = $this->buildWhere($filters);
        $total = (int) (Database::fetchColumn(
            "SELECT COUNT(*) FROM {$this->table} WHERE {$this->scope()} {$where}",
            $params
        ) ?? 0);
        $offset = ($page - 1) * $perPage;
        $data = Database::fetchAll(
            "SELECT * FROM {$this->table} WHERE {$this->scope()} {$where}
             ORDER BY {$orderBy} {$dir} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['data' => $data, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    public function all(array $filters = [], string $orderBy = 'id', string $dir = 'DESC'): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $allowed = $this->sortable();
        $orderBy = in_array($orderBy, $allowed, true) ? $orderBy : 'id';
        return Database::fetchAll(
            "SELECT * FROM {$this->table} WHERE {$this->scope()} {$where} ORDER BY {$orderBy} {$dir}",
            $params
        );
    }

    public function create(array $data): int
    {
        $scopedUserId = \SLC\Core\Auth::scopedUserId();
        if ($scopedUserId !== null && empty($data['assigned_to'])) {
            $data['assigned_to'] = $scopedUserId;
        }
        $data = $this->map($data);
        return Database::insert($this->table, $data);
    }

    public function update(int $id, array $data): int
    {
        $scopedUserId = \SLC\Core\Auth::scopedUserId();
        if ($scopedUserId !== null) {
            $exists = Database::fetchColumn(
                "SELECT id FROM {$this->table} WHERE id = :id AND assigned_to = :uid AND {$this->scope()} LIMIT 1",
                ['id' => $id, 'uid' => $scopedUserId]
            );
            if (!$exists) {
                return 0;
            }
        }
        return Database::update($this->table, $id, $this->map($data, false));
    }

    public function delete(int $id): bool
    {
        $scopedUserId = \SLC\Core\Auth::scopedUserId();
        if ($scopedUserId !== null) {
            $exists = Database::fetchColumn(
                "SELECT id FROM {$this->table} WHERE id = :id AND assigned_to = :uid AND {$this->scope()} LIMIT 1",
                ['id' => $id, 'uid' => $scopedUserId]
            );
            if (!$exists) {
                return false;
            }
        }
        if ($this->softDelete) {
            return Database::query(
                "UPDATE {$this->table} SET deleted_at = NOW() WHERE id = :id",
                ['id' => $id]
            )->rowCount() > 0;
        }
        return Database::query(
            "DELETE FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        )->rowCount() > 0;
    }

    public function deleteMany(array $ids): int
    {
        $cleanIds = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
        if (empty($cleanIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $scopedUserId = \SLC\Core\Auth::scopedUserId();
        $scopeExtra = '';
        $extraParams = [];
        if ($scopedUserId !== null) {
            $scopeExtra = " AND assigned_to = ?";
            $extraParams[] = $scopedUserId;
        }
        $params = array_merge($cleanIds, $extraParams);
        if ($this->softDelete) {
            return Database::query(
                "UPDATE {$this->table} SET deleted_at = NOW() WHERE id IN ({$placeholders}) AND deleted_at IS NULL {$scopeExtra}",
                $params
            )->rowCount();
        }
        return Database::query(
            "DELETE FROM {$this->table} WHERE id IN ({$placeholders}) {$scopeExtra}",
            $params
        )->rowCount();
    }

    public function bulkAssign(array $ids, int $assignedToUserId): int
    {
        $cleanIds = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
        if (empty($cleanIds) || $assignedToUserId <= 0) {
            return 0;
        }
        $now = date('Y-m-d H:i:s');
        $params = array_merge([$assignedToUserId, $now], $cleanIds);
        return Database::query(
            "UPDATE {$this->table} SET assigned_to = ?, assigned_at = ? WHERE id IN ({$placeholders}) AND {$this->scope()}",
            $params
        )->rowCount();
    }

    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        return (int) (Database::fetchColumn(
            "SELECT COUNT(*) FROM {$this->table} WHERE {$this->scope()} {$where}",
            $params
        ) ?? 0);
    }

    /** Whitelist columns that may be used for ORDER BY. */
    protected function sortable(): array
    {
        return ['id', 'created_at', 'updated_at', 'name', 'ai_score', 'status', 'priority', 'scheduled_at', 'assigned_to'];
    }

    /** Translate raw input to DB columns (whitelist filter). Override per entity. */
    protected function map(array $data, bool $forCreate = true): array
    {
        return $data;
    }

    /** Build a WHERE clause from filter params. Override for custom filters. */
    protected function buildWhere(array $filters, string $prefix = ''): array
    {
        $colPrefix = $prefix !== '' ? "{$prefix}." : '';
        $where = '';
        $params = [];
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '' && property_exists($this, 'searchCols') && !empty($this->searchCols)) {
            $like = [];
            foreach ($this->searchCols as $i => $col) {
                $key = 'sq' . $i;
                $like[] = "{$colPrefix}{$col} LIKE :{$key}";
                $params[$key] = '%' . $search . '%';
            }
            $where .= ' AND (' . implode(' OR ', $like) . ')';
        }

        $scopedUserId = \SLC\Core\Auth::scopedUserId();
        if ($scopedUserId !== null) {
            $where .= " AND {$colPrefix}assigned_to = :_scoped_uid";
            $params['_scoped_uid'] = $scopedUserId;
        } elseif (array_key_exists('assigned_to', $filters) && $filters['assigned_to'] !== null && $filters['assigned_to'] !== '') {
            $where .= " AND {$colPrefix}assigned_to = :f_assigned_to";
            $params['f_assigned_to'] = (int) $filters['assigned_to'];
        }

        return [$where, $params];
    }
}
