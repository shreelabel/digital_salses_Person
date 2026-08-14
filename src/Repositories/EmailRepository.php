<?php
declare(strict_types=1);

namespace SLC\Repositories;

use SLC\Core\Database;

/**
 * Handles BOTH email templates and email messages (draft-only).
 * IMPORTANT: messages are never sent — status is always 'draft'.
 */
class EmailRepository
{
    // ---- Templates ----
    public function allTemplates(): array
    {
        return Database::fetchAll('SELECT * FROM slc_email_templates ORDER BY id DESC');
    }

    public function findTemplate(int $id): ?array
    {
        return Database::fetch('SELECT * FROM slc_email_templates WHERE id = :id', ['id' => $id]);
    }

    public function createTemplate(array $data): int
    {
        return Database::insert('slc_email_templates', $this->templateMap($data));
    }

    public function deleteTemplate(int $id): bool
    {
        return Database::query('DELETE FROM slc_email_templates WHERE id = :id', ['id' => $id])->rowCount() > 0;
    }

    private function templateMap(array $data): array
    {
        $allowed = ['name', 'subject', 'body', 'category'];
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = $data[$k];
            }
        }
        return $out;
    }

    // ---- Messages (draft only) ----
    public function allMessages(array $filters = []): array
    {
        $where = '';
        $params = [];
        if (!empty($filters['company_id'])) {
            $where = 'WHERE company_id = :cid';
            $params['cid'] = (int) $filters['company_id'];
        }
        return Database::fetchAll(
            "SELECT m.*, c.name AS company_name
             FROM slc_email_messages m
             LEFT JOIN slc_companies c ON c.id = m.company_id
             {$where} ORDER BY m.id DESC",
            $params
        );
    }

    public function findMessage(int $id): ?array
    {
        return Database::fetch('SELECT * FROM slc_email_messages WHERE id = :id', ['id' => $id]);
    }

    public function createMessage(array $data): int
    {
        $data = $this->messageMap($data);
        // force draft — no sending ever occurs
        $data['status'] = 'draft';
        return Database::insert('slc_email_messages', $data);
    }

    public function updateMessage(int $id, array $data): int
    {
        $data = $this->messageMap($data);
        unset($data['status']); // never change away from draft via update
        return Database::update('slc_email_messages', $id, $data);
    }

    public function deleteMessage(int $id): bool
    {
        return Database::query('DELETE FROM slc_email_messages WHERE id = :id', ['id' => $id])->rowCount() > 0;
    }

    private function messageMap(array $data): array
    {
        $allowed = ['company_id', 'contact_id', 'lead_id', 'subject', 'body', 'status', 'ai_generated'];
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = $data[$k] === '' ? null : $data[$k];
            }
        }
        if (array_key_exists('ai_generated', $out)) {
            $out['ai_generated'] = in_array((string) $out['ai_generated'], ['1', 'true'], true) ? 1 : 0;
        }
        return $out;
    }
}
