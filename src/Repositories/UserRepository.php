<?php
declare(strict_types=1);

namespace SLC\Repositories;

use SLC\Core\Database;
use SLC\Core\Security;
use SLC\Core\Permissions;

class UserRepository extends BaseRepository
{
    protected string $table = 'slc_users';
    protected bool $softDelete = true;

    protected function sortable(): array
    {
        return ['id', 'name', 'email', 'role', 'is_active', 'created_at', 'last_login_at'];
    }

    public function all(array $filters = [], string $orderBy = 'id', string $dir = 'ASC'): array
    {
        $rows = parent::all($filters, $orderBy, $dir);
        return array_map(fn($u) => $this->sanitize($u), $rows);
    }

    public function find(int $id): ?array
    {
        $user = parent::find($id);
        return $user ? $this->sanitize($user) : null;
    }

    public function findByEmail(string $email): ?array
    {
        $user = Database::fetch(
            "SELECT * FROM slc_users WHERE email = :email AND deleted_at IS NULL LIMIT 1",
            ['email' => strtolower(trim($email))]
        );
        return $user ? $this->sanitize($user) : null;
    }

    public function createUser(array $data): int
    {
        $insert = [
            'name'          => trim((string) ($data['name'] ?? '')),
            'email'         => strtolower(trim((string) ($data['email'] ?? ''))),
            'password_hash' => Security::hashPassword((string) ($data['password'] ?? 'password123')),
            'role'          => strtolower(trim((string) ($data['role'] ?? 'user'))),
            'is_active'     => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
            'permissions'   => isset($data['permissions']) && is_array($data['permissions'])
                ? json_encode($data['permissions'])
                : null,
        ];

        return Database::insert($this->table, $insert);
    }

    public function updateUser(int $id, array $data): bool
    {
        $update = [];
        if (isset($data['name'])) {
            $update['name'] = trim((string) $data['name']);
        }
        if (isset($data['email'])) {
            $update['email'] = strtolower(trim((string) $data['email']));
        }
        if (!empty($data['password'])) {
            $update['password_hash'] = Security::hashPassword((string) $data['password']);
        }
        if (isset($data['role'])) {
            $update['role'] = strtolower(trim((string) $data['role']));
        }
        if (isset($data['is_active'])) {
            $update['is_active'] = (int) (bool) $data['is_active'];
        }
        if (array_key_exists('permissions', $data)) {
            $update['permissions'] = is_array($data['permissions']) && !empty($data['permissions'])
                ? json_encode($data['permissions'])
                : null;
        }

        if (empty($update)) {
            return true;
        }

        return Database::update($this->table, $id, $update) > 0;
    }

    private function sanitize(array $u): array
    {
        unset($u['password_hash']);
        $u['permissions_raw'] = Permissions::parseOverrides($u['permissions'] ?? null);
        $u['computed_permissions'] = Permissions::forUser($u);
        return $u;
    }
}
