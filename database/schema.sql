-- =============================================================
-- SLC AI Sales Agent — Database schema (slc_ai_sales ONLY)
-- -------------------------------------------------------------
-- This file is the SINGLE SOURCE OF TRUTH for the schema.
-- The PHP Installer (database/Installer.php) executes this file.
-- Idempotent: every object uses IF NOT EXISTS. Re-running is safe
-- and NEVER destroys existing data. NEVER connects to the ERP DB.
-- Engine: InnoDB / Charset: utf8mb4 / Collation: utf8mb4_unicode_ci
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------- 1. USERS ----------
CREATE TABLE IF NOT EXISTS `slc_users` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(150) NOT NULL,
    `email`          VARCHAR(190) NOT NULL,
    `password_hash`  VARCHAR(255) NOT NULL,
    `role`           VARCHAR(50)  NOT NULL DEFAULT 'admin',
    `permissions`    JSON         NULL,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `last_login_at`  DATETIME     NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     DATETIME     NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_users_email` (`email`),
    KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 2. COMPANIES ----------
CREATE TABLE IF NOT EXISTS `slc_companies` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(200) NOT NULL,
    `industry`       VARCHAR(120) NULL,
    `sub_industry`   VARCHAR(120) NULL,
    `city`           VARCHAR(120) NULL,
    `state`          VARCHAR(120) NULL,
    `country`        VARCHAR(120) NULL,
    `website`        VARCHAR(255) NULL,
    `phone`          VARCHAR(60)  NULL,
    `email`          VARCHAR(190) NULL,
    `employee_count` VARCHAR(40)  NULL,
    `description`    TEXT         NULL,
    `ai_score`       TINYINT UNSIGNED NULL DEFAULT NULL,
    `ai_priority`    VARCHAR(10)  NULL,
    `source`         VARCHAR(80)  NULL,
    `apollo_account_id` VARCHAR(100) NULL,
    `raw_data`       JSON         NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     DATETIME     NULL,
    PRIMARY KEY (`id`),
    KEY `idx_companies_name` (`name`),
    KEY `idx_companies_industry` (`industry`),
    KEY `idx_companies_city` (`city`),
    KEY `idx_companies_ai_score` (`ai_score`),
    KEY `idx_companies_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 3. CONTACTS ----------
CREATE TABLE IF NOT EXISTS `slc_contacts` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`        INT UNSIGNED NOT NULL,
    `name`              VARCHAR(150) NOT NULL,
    `designation`       VARCHAR(150) NULL,
    `department`        VARCHAR(120) NULL,
    `email`             VARCHAR(190) NULL,
    `phone`             VARCHAR(60)  NULL,
    `mobile`            VARCHAR(60)  NULL,
    `linkedin_url`      VARCHAR(255) NULL,
    `is_decision_maker` TINYINT(1)   NOT NULL DEFAULT 0,
    `is_primary`        TINYINT(1)   NOT NULL DEFAULT 0,
    `importance`        VARCHAR(20)  NULL DEFAULT 'Medium',
    `notes`             TEXT         NULL,
    `source`            VARCHAR(80)  NULL,
    `apollo_contact_id` VARCHAR(100) NULL,
    `raw_data`          JSON         NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        DATETIME     NULL,
    PRIMARY KEY (`id`),
    KEY `idx_contacts_company` (`company_id`),
    KEY `idx_contacts_email` (`email`),
    CONSTRAINT `fk_contacts_company` FOREIGN KEY (`company_id`)
        REFERENCES `slc_companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 4. LEADS ----------
CREATE TABLE IF NOT EXISTS `slc_leads` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`      INT UNSIGNED NOT NULL,
    `contact_id`      INT UNSIGNED NULL,
    `title`           VARCHAR(200) NULL,
    `industry`        VARCHAR(120) NULL,
    `location`        VARCHAR(150) NULL,
    `status`          VARCHAR(30)  NOT NULL DEFAULT 'New',
    `priority`        VARCHAR(10)  NOT NULL DEFAULT 'Medium',
    `ai_score`        TINYINT UNSIGNED NULL DEFAULT NULL,
    `estimated_value` DECIMAL(14,2) NULL DEFAULT 0.00,
    `source`          VARCHAR(80)  NULL,
    `notes`           TEXT         NULL,
    `import_batch_id` VARCHAR(64)  NULL,
    `raw_data`        JSON         NULL,
    `next_followup_at` DATETIME    NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`      DATETIME     NULL,
    PRIMARY KEY (`id`),
    KEY `idx_leads_company` (`company_id`),
    KEY `idx_leads_contact` (`contact_id`),
    KEY `idx_leads_status` (`status`),
    KEY `idx_leads_priority` (`priority`),
    KEY `idx_leads_source` (`source`),
    CONSTRAINT `fk_leads_company` FOREIGN KEY (`company_id`)
        REFERENCES `slc_companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_leads_contact` FOREIGN KEY (`contact_id`)
        REFERENCES `slc_contacts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 5. CAMPAIGNS ----------
CREATE TABLE IF NOT EXISTS `slc_campaigns` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(200) NOT NULL,
    `description`      TEXT         NULL,
    `objective`        VARCHAR(255) NULL,
    `status`           VARCHAR(20)  NOT NULL DEFAULT 'Draft',
    `audience_industry` VARCHAR(150) NULL,
    `audience_location` VARCHAR(150) NULL,
    `start_date`       DATE         NULL,
    `end_date`         DATE         NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`       DATETIME     NULL,
    PRIMARY KEY (`id`),
    KEY `idx_campaigns_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 6. CAMPAIGN_LEADS (pivot) ----------
CREATE TABLE IF NOT EXISTS `slc_campaign_leads` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id` INT UNSIGNED NOT NULL,
    `lead_id`     INT UNSIGNED NOT NULL,
    `status`      VARCHAR(30)  NOT NULL DEFAULT 'Added',
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_campaign_lead` (`campaign_id`, `lead_id`),
    KEY `idx_cl_campaign` (`campaign_id`),
    KEY `idx_cl_lead` (`lead_id`),
    CONSTRAINT `fk_cl_campaign` FOREIGN KEY (`campaign_id`)
        REFERENCES `slc_campaigns` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cl_lead` FOREIGN KEY (`lead_id`)
        REFERENCES `slc_leads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 7. FOLLOWUPS ----------
CREATE TABLE IF NOT EXISTS `slc_followups` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`      INT UNSIGNED NULL,
    `company_id`   INT UNSIGNED NULL,
    `contact_id`   INT UNSIGNED NULL,
    `type`         VARCHAR(50)  NOT NULL DEFAULT 'Call',
    `scheduled_at` DATETIME     NOT NULL,
    `completed_at` DATETIME     NULL,
    `notes`        TEXT         NULL,
    `status`       VARCHAR(20)  NOT NULL DEFAULT 'Pending',
    `created_by`   INT UNSIGNED NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_followups_lead` (`lead_id`),
    KEY `idx_followups_company` (`company_id`),
    KEY `idx_followups_status` (`status`),
    KEY `idx_followups_scheduled` (`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 8. OPPORTUNITIES ----------
CREATE TABLE IF NOT EXISTS `slc_opportunities` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`           INT UNSIGNED NULL,
    `company_id`        INT UNSIGNED NOT NULL,
    `contact_id`        INT UNSIGNED NULL,
    `title`             VARCHAR(200) NOT NULL,
    `amount`            DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `stage`             VARCHAR(30)  NOT NULL DEFAULT 'Prospecting',
    `probability`       TINYINT UNSIGNED NOT NULL DEFAULT 10,
    `expected_close_date` DATE NULL,
    `notes`             TEXT         NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        DATETIME     NULL,
    PRIMARY KEY (`id`),
    KEY `idx_opp_company` (`company_id`),
    KEY `idx_opp_lead` (`lead_id`),
    KEY `idx_opp_stage` (`stage`),
    CONSTRAINT `fk_opp_company` FOREIGN KEY (`company_id`)
        REFERENCES `slc_companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 9. ACTIVITIES (audit log) ----------
CREATE TABLE IF NOT EXISTS `slc_activities` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NULL,
    `company_id`  INT UNSIGNED NULL,
    `lead_id`     INT UNSIGNED NULL,
    `type`        VARCHAR(50)  NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `meta`        JSON         NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activities_company` (`company_id`),
    KEY `idx_activities_lead` (`lead_id`),
    KEY `idx_activities_type` (`type`),
    KEY `idx_activities_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 10. EMAIL TEMPLATES ----------
CREATE TABLE IF NOT EXISTS `slc_email_templates` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(150) NOT NULL,
    `subject`    VARCHAR(255) NULL,
    `body`       MEDIUMTEXT   NULL,
    `category`   VARCHAR(80)  NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 11. EMAIL MESSAGES (DRAFT ONLY — NEVER SENT) ----------
CREATE TABLE IF NOT EXISTS `slc_email_messages` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`   INT UNSIGNED NULL,
    `contact_id`   INT UNSIGNED NULL,
    `lead_id`      INT UNSIGNED NULL,
    `subject`      VARCHAR(255) NULL,
    `body`         MEDIUMTEXT   NULL,
    `status`       VARCHAR(20)  NOT NULL DEFAULT 'draft',
    `ai_generated` TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_emails_company` (`company_id`),
    KEY `idx_emails_lead` (`lead_id`),
    KEY `idx_emails_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 12. AI SETTINGS (key/value; secrets encrypted at rest) ----------
CREATE TABLE IF NOT EXISTS `slc_ai_settings` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100) NOT NULL,
    `setting_value` TEXT         NULL,
    `is_secret`     TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ai_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 13. INTEGRATIONS (truthful statuses only) ----------
CREATE TABLE IF NOT EXISTS `slc_integrations` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(120) NOT NULL,
    `slug`         VARCHAR(80)  NOT NULL,
    `status`       VARCHAR(30)  NOT NULL DEFAULT 'Not Connected',
    `description`  VARCHAR(255) NULL,
    `configured_at` DATETIME    NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_integration_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 14. NOTIFICATIONS ----------
CREATE TABLE IF NOT EXISTS `slc_notifications` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NULL,
    `type`       VARCHAR(50)  NOT NULL,
    `title`      VARCHAR(200) NOT NULL,
    `message`    VARCHAR(500) NULL,
    `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
    `link`       VARCHAR(255) NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notif_user` (`user_id`),
    KEY `idx_notif_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 15. RESEARCH REPORTS ----------
CREATE TABLE IF NOT EXISTS `slc_research_reports` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`         INT UNSIGNED NOT NULL,
    `overview`           MEDIUMTEXT   NULL,
    `industry`           VARCHAR(150) NULL,
    `products`           MEDIUMTEXT   NULL,
    `locations`          VARCHAR(255) NULL,
    `relevance`          MEDIUMTEXT   NULL,
    `label_requirements` MEDIUMTEXT   NULL,
    `suggested_department` VARCHAR(150) NULL,
    `outreach_angle`     MEDIUMTEXT   NULL,
    `why_relevant`       MEDIUMTEXT   NULL,
    `confidence_score`   TINYINT UNSIGNED NULL,
    `sources`            JSON         NULL,
    `full_report`        LONGTEXT     NULL,
    `model`              VARCHAR(80)  NULL,
    `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_reports_company` (`company_id`),
    CONSTRAINT `fk_reports_company` FOREIGN KEY (`company_id`)
        REFERENCES `slc_companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 16. AI REQUESTS (audit log of Gemini calls) ----------
CREATE TABLE IF NOT EXISTS `slc_ai_requests` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`           VARCHAR(60)  NOT NULL,
    `endpoint`       VARCHAR(255) NULL,
    `model`          VARCHAR(80)  NULL,
    `status`         VARCHAR(30)  NOT NULL,
    `latency_ms`     INT UNSIGNED NULL,
    `prompt_summary` VARCHAR(500) NULL,
    `response_summary` VARCHAR(500) NULL,
    `error`          VARCHAR(500) NULL,
    `user_id`        INT UNSIGNED NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_aireq_type` (`type`),
    KEY `idx_aireq_status` (`status`),
    KEY `idx_aireq_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 17. SESSIONS (login audit) ----------
CREATE TABLE IF NOT EXISTS `slc_sessions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED NOT NULL,
    `ip_address`    VARCHAR(45)  NULL,
    `user_agent`    VARCHAR(255) NULL,
    `token`         VARCHAR(100) NULL,
    `last_activity` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sessions_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- MULTI-PROVIDER LAYER (free-first: Hunter / Apollo / FreeLLMAPI
-- / 9Router, with Gemini OPTIONAL). Added additively — does not
-- alter any existing table.
-- =============================================================

-- ---------- 18. PROVIDER CONFIG ----------
CREATE TABLE IF NOT EXISTS `slc_provider_config` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`           VARCHAR(60)  NOT NULL,
    `name`           VARCHAR(120) NOT NULL,
    `role`           VARCHAR(30)  NOT NULL,
    `enabled`        TINYINT(1)   NOT NULL DEFAULT 0,
    `api_key_enc`    TEXT         NULL,
    `base_url`       VARCHAR(255) NULL,
    `model`          VARCHAR(120) NULL,
    `priority`       INT          NOT NULL DEFAULT 99,
    `last_status`    VARCHAR(40)  NOT NULL DEFAULT 'Not Connected',
    `last_tested_at` DATETIME     NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_provider_slug` (`slug`),
    KEY `idx_provider_role` (`role`),
    KEY `idx_provider_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 19. PROVIDER USAGE / AUDIT (cost & credit protection) ----------
CREATE TABLE IF NOT EXISTS `slc_provider_usage` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider`      VARCHAR(60)  NOT NULL,
    `operation`     VARCHAR(80)  NOT NULL,
    `cache_hit`     TINYINT(1)   NOT NULL DEFAULT 0,
    `status`        VARCHAR(30)  NOT NULL,
    `http_status`   INT          NULL,
    `latency_ms`    INT UNSIGNED NULL,
    `credit_used`   DECIMAL(10,3) NULL,
    `rate_remaining` INT         NULL,
    `request_summary` VARCHAR(255) NULL,
    `error`         VARCHAR(500) NULL,
    `user_id`       INT UNSIGNED NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pu_provider` (`provider`),
    KEY `idx_pu_operation` (`operation`),
    KEY `idx_pu_status` (`status`),
    KEY `idx_pu_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 20. PROVIDER CACHE (never repeat the same lookup) ----------
CREATE TABLE IF NOT EXISTS `slc_provider_cache` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider`   VARCHAR(60)  NOT NULL,
    `operation`  VARCHAR(80)  NOT NULL,
    `cache_key`  VARCHAR(255) NOT NULL,
    `payload`    LONGTEXT     NOT NULL,
    `hits`       INT UNSIGNED NOT NULL DEFAULT 0,
    `expires_at` DATETIME     NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_provider_cache` (`provider`, `operation`, `cache_key`),
    KEY `idx_pc_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 21. IMPORTS (CSV / Batch import history) ----------
CREATE TABLE IF NOT EXISTS `slc_imports` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `batch_id`        VARCHAR(64)  NOT NULL,
    `source`          VARCHAR(80)  NOT NULL DEFAULT 'Apollo CSV',
    `file_name`       VARCHAR(255) NOT NULL,
    `file_size`       INT UNSIGNED NOT NULL DEFAULT 0,
    `total_rows`      INT UNSIGNED NOT NULL DEFAULT 0,
    `imported_count`  INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `duplicate_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `skipped_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `error_count`     INT UNSIGNED NOT NULL DEFAULT 0,
    `error_log`       JSON         NULL,
    `summary`         JSON         NULL,
    `created_by`      INT UNSIGNED NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_import_batch` (`batch_id`),
    KEY `idx_import_source` (`source`),
    KEY `idx_import_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
