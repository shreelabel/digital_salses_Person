<?php
declare(strict_types=1);

namespace SLC\Repositories;

use SLC\Core\Database;

class LeadRepository extends BaseRepository
{
    protected string $table = 'slc_leads';
    protected bool $softDelete = true;
    protected array $searchCols = ['title', 'industry', 'location', 'source', 'notes'];

    public const STATUSES = ['New', 'Contacted', 'Interested', 'Requirement', 'Quotation', 'Negotiation', 'Won', 'Lost'];
    public const PRIORITIES = ['High', 'Medium', 'Low'];

    protected function sortable(): array
    {
        return ['id', 'status', 'priority', 'ai_score', 'estimated_value', 'next_followup_at', 'created_at'];
    }

    protected function map(array $data, bool $forCreate = true): array
    {
        $allowed = [
            'company_id', 'contact_id', 'title', 'industry', 'location', 'status', 'priority',
            'ai_score', 'estimated_value', 'source', 'notes', 'import_batch_id', 'raw_data', 'next_followup_at', 'assigned_to', 'assigned_at',
        ];
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = $data[$k] === '' ? null : $data[$k];
            }
        }
        if (array_key_exists('estimated_value', $out) && $out['estimated_value'] !== null) {
            $out['estimated_value'] = (float) $out['estimated_value'];
        }
        if (array_key_exists('ai_score', $out) && $out['ai_score'] !== null) {
            $out['ai_score'] = max(0, min(100, (int) $out['ai_score']));
        }
        if (array_key_exists('assigned_to', $out)) {
            if ($out['assigned_to'] !== null && $out['assigned_to'] !== '') {
                $out['assigned_to'] = (int) $out['assigned_to'];
                if (empty($out['assigned_at'])) {
                    $out['assigned_at'] = date('Y-m-d H:i:s');
                }
            } else {
                $out['assigned_at'] = null;
            }
        }
        return $out;
    }

    public function buildWhere(array $filters, string $prefix = 'l'): array
    {
        [$where, $params] = parent::buildWhere($filters, $prefix);
        foreach (['status', 'priority', 'source', 'industry', 'import_batch_id'] as $f) {
            if (!empty($filters[$f])) {
                $where .= " AND {$prefix}.{$f} = :f_{$f}";
                $params['f_' . $f] = $filters[$f];
            }
        }
        if (!empty($filters['company_id'])) {
            $where .= " AND {$prefix}.company_id = :cid";
            $params['cid'] = (int) $filters['company_id'];
        }
        return [$where, $params];
    }

    /** Joined list (company name + contact name + score + assigned user) for tables. */
    public function listWithCompany(array $filters = [], int $page = 1, int $perPage = 20, string $orderBy = 'id', string $dir = 'DESC'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $orderBy = in_array($orderBy, $this->sortable(), true) ? "l.{$orderBy}" : 'l.id';

        [$where, $params] = $this->buildWhere($filters);
        $total = (int) (Database::fetchColumn(
            "SELECT COUNT(*) FROM slc_leads l
             LEFT JOIN slc_companies c ON c.id = l.company_id
             WHERE l.deleted_at IS NULL {$where}",
            $params
        ) ?? 0);
        $offset = ($page - 1) * $perPage;
        $data = Database::fetchAll(
            "SELECT l.*, c.name AS company_name, c.city, c.state, c.website as company_website,
                    ct.name as contact_name, ct.email as contact_email, ct.phone as contact_phone, ct.designation as contact_designation,
                    u.name as assigned_user_name, u.email as assigned_user_email
             FROM slc_leads l
             LEFT JOIN slc_companies c ON c.id = l.company_id
             LEFT JOIN slc_contacts ct ON ct.id = l.contact_id
             LEFT JOIN slc_users u ON u.id = l.assigned_to
             WHERE l.deleted_at IS NULL {$where}
             ORDER BY {$orderBy} {$dir} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['data' => $data, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }
}
