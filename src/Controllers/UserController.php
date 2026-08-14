<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Auth;
use SLC\Core\Response;
use SLC\Core\Validator;
use SLC\Core\Permissions;
use SLC\Repositories\UserRepository;

class UserController extends Controller
{
    public function __construct(private UserRepository $users = new UserRepository())
    {
    }

    /** GET /api/users — list all users (admin only) */
    public function index(): void
    {
        Auth::requirePermission('users.manage');
        $list = $this->users->all();
        Response::success([
            'users'               => $list,
            'available_permissions' => Permissions::ALL,
            'role_defaults'       => Permissions::ROLE_DEFAULTS,
        ]);
    }

    /** POST /api/users — create new user */
    public function store(): void
    {
        Auth::requirePermission('users.manage');
        $data = $this->input();

        $v = new Validator($data);
        $v->required('name')->maxLength('name', 150)
          ->required('email')->email('email')->maxLength('email', 190)
          ->required('password')->minLength('password', 6)
          ->required('role')->in('role', ['admin', 'user']);

        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }

        if ($this->users->findByEmail($data['email'])) {
            Response::error('A user with this email address already exists.', 422);
            return;
        }

        $id = $this->users->createUser($data);
        $this->activity('user_create', 'Created user account: ' . $data['email'], null, null);
        Response::success(['user' => $this->users->find($id)], 201);
    }

    /** GET /api/users/{id} */
    public function show(string $id): void
    {
        Auth::requirePermission('users.manage');
        $user = $this->users->find((int) $id);
        if (!$user) {
            Response::notFound('User not found.');
            return;
        }
        Response::success(['user' => $user]);
    }

    /** PUT /api/users/{id} */
    public function update(string $id): void
    {
        Auth::requirePermission('users.manage');
        $uid = (int) $id;
        $existing = $this->users->find($uid);
        if (!$existing) {
            Response::notFound('User not found.');
            return;
        }

        $data = $this->input();
        $v = new Validator($data);
        if (isset($data['name'])) $v->maxLength('name', 150);
        if (isset($data['email'])) $v->email('email')->maxLength('email', 190);
        if (isset($data['role'])) $v->in('role', ['admin', 'user']);
        if (!empty($data['password'])) $v->minLength('password', 6);

        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }

        // Prevent demoting the last active admin
        if (isset($data['role']) && $data['role'] !== 'admin' && $existing['role'] === 'admin') {
            $adminCount = (int) \SLC\Core\Database::fetchColumn("SELECT COUNT(*) FROM slc_users WHERE role = 'admin' AND is_active = 1 AND deleted_at IS NULL");
            if ($adminCount <= 1) {
                Response::error('Cannot change the role of the only active Administrator.', 422);
                return;
            }
        }

        $this->users->updateUser($uid, $data);
        $this->activity('user_update', 'Updated user: ' . $existing['email'], null, null);
        Response::success(['user' => $this->users->find($uid)]);
    }

    /** DELETE /api/users/{id} */
    public function destroy(string $id): void
    {
        Auth::requirePermission('users.manage');
        $uid = (int) $id;
        $existing = $this->users->find($uid);
        if (!$existing) {
            Response::notFound('User not found.');
            return;
        }

        if ($uid === Auth::id()) {
            Response::error('You cannot delete your own account.', 422);
            return;
        }

        if ($existing['role'] === 'admin') {
            $adminCount = (int) \SLC\Core\Database::fetchColumn("SELECT COUNT(*) FROM slc_users WHERE role = 'admin' AND is_active = 1 AND deleted_at IS NULL");
            if ($adminCount <= 1) {
                Response::error('Cannot delete the only active Administrator account.', 422);
                return;
            }
        }

        $this->users->delete($uid);
        $this->activity('user_delete', 'Deleted user: ' . $existing['email'], null, null);
        Response::success(['deleted' => true]);
    }

    /** POST /api/users/bulk-delete */
    public function bulkDestroy(): void
    {
        Auth::requirePermission('users.manage');
        $input = $this->input();
        $ids = $input['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            Response::error('No user IDs provided for deletion.', 422);
            return;
        }

        $myId = Auth::id();
        $cleanIds = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));

        // Prevent self deletion
        if ($myId !== null && in_array($myId, $cleanIds, true)) {
            Response::error('You cannot delete your own account.', 422);
            return;
        }

        // Check if deleting these users would leave 0 active administrators
        $totalActiveAdmins = (int) \SLC\Core\Database::fetchColumn("SELECT COUNT(*) FROM slc_users WHERE role = 'admin' AND is_active = 1 AND deleted_at IS NULL");
        if (!empty($cleanIds)) {
            $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
            $adminsInBatch = (int) \SLC\Core\Database::fetchColumn(
                "SELECT COUNT(*) FROM slc_users WHERE id IN ({$placeholders}) AND role = 'admin' AND is_active = 1 AND deleted_at IS NULL",
                $cleanIds
            );
            if ($totalActiveAdmins - $adminsInBatch < 1) {
                Response::error('Cannot delete all active Administrator accounts. At least one must remain.', 422);
                return;
            }
        }

        $count = $this->users->deleteMany($cleanIds);
        $this->activity('user_bulk_delete', "Deleted {$count} users in bulk", null, null);
        Response::success(['deleted' => true, 'count' => $count]);
    }

    /** GET /api/users/assignable — list active users for lead assignment dropdowns */
    public function assignable(): void
    {
        Auth::requireApiAuth();
        $list = \SLC\Core\Database::fetchAll(
            'SELECT id, name, email, role FROM slc_users WHERE is_active = 1 AND deleted_at IS NULL ORDER BY role ASC, name ASC'
        );
        Response::success(['users' => $list]);
    }
}
