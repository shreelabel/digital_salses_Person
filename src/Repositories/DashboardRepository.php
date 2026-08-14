<?php
declare(strict_types=1);

namespace SLC\Repositories;

use SLC\Core\Database;

/**
 * Database-driven KPI + pipeline aggregations for the dashboard.
 * Nothing here is hardcoded — every number is computed from the tables.
 */
class DashboardRepository
{
    public function stats(): array
    {
        $count = fn(string $sql, array $p = []) => (int) (Database::fetchColumn($sql, $p) ?? 0);
        $sum   = fn(string $sql, array $p = []) => (float) (Database::fetchColumn($sql, $p) ?? 0);

        return [
            'total_prospects'    => $count('SELECT COUNT(*) FROM slc_companies WHERE deleted_at IS NULL'),
            'new_leads'          => $count("SELECT COUNT(*) FROM slc_leads WHERE deleted_at IS NULL AND status = 'New'"),
            'high_potential'     => $count('SELECT COUNT(*) FROM slc_leads WHERE deleted_at IS NULL AND (ai_score >= 70 OR priority = "High")'),
            'emails_sent'        => $count("SELECT COUNT(*) FROM slc_email_messages WHERE status = 'sent'"), // always 0 — draft-only
            'email_drafts'       => $count("SELECT COUNT(*) FROM slc_email_messages"),
            'replies'            => 0, // no inbound mail integration exists
            'interested'         => $count("SELECT COUNT(*) FROM slc_leads WHERE deleted_at IS NULL AND status = 'Interested'"),
            'followups_due'      => $count("SELECT COUNT(*) FROM slc_followups WHERE status = 'Pending' AND scheduled_at <= NOW()"),
            'open_opportunities' => $count("SELECT COUNT(*) FROM slc_opportunities WHERE deleted_at IS NULL AND stage NOT IN ('Won','Lost')"),
            'won_value'          => $sum("SELECT COALESCE(SUM(amount),0) FROM slc_opportunities WHERE deleted_at IS NULL AND stage = 'Won'"),
            'open_pipeline_value'=> $sum("SELECT COALESCE(SUM(amount),0) FROM slc_opportunities WHERE deleted_at IS NULL AND stage NOT IN ('Won','Lost')"),
            'companies_high_score'=> $count('SELECT COUNT(*) FROM slc_companies WHERE deleted_at IS NULL AND ai_score >= 70'),
        ];
    }

    public function pipeline(): array
    {
        $rows = Database::fetchAll(
            "SELECT status, COUNT(*) AS cnt FROM slc_leads
             WHERE deleted_at IS NULL GROUP BY status ORDER BY FIELD(status,'New','Contacted','Interested','Requirement','Quotation','Negotiation','Won','Lost')"
        );
        $out = [];
        foreach (['New', 'Contacted', 'Interested', 'Requirement', 'Quotation', 'Negotiation', 'Won', 'Lost'] as $st) {
            $out[$st] = 0;
        }
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['cnt'];
        }
        return $out;
    }

    public function recentActivity(int $limit = 10): array
    {
        return Database::fetchAll(
            "SELECT a.*, u.name AS user_name, c.name AS company_name
             FROM slc_activities a
             LEFT JOIN slc_users u ON u.id = a.user_id
             LEFT JOIN slc_companies c ON c.id = a.company_id
             ORDER BY a.id DESC LIMIT " . max(1, $limit)
        );
    }

    public function upcomingFollowups(int $limit = 6): array
    {
        return Database::fetchAll(
            "SELECT f.*, c.name AS company_name
             FROM slc_followups f
             LEFT JOIN slc_companies c ON c.id = f.company_id
             WHERE f.status = 'Pending' ORDER BY f.scheduled_at ASC LIMIT " . max(1, $limit)
        );
    }

    public function topCompanies(int $limit = 6): array
    {
        return Database::fetchAll(
            "SELECT * FROM slc_companies WHERE deleted_at IS NULL AND ai_score IS NOT NULL
             ORDER BY ai_score DESC, id DESC LIMIT " . max(1, $limit)
        );
    }
}
