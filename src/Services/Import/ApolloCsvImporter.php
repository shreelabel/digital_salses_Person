<?php
declare(strict_types=1);

namespace SLC\Services\Import;

use SLC\Core\Database;
use SLC\Repositories\CompanyRepository;

class ApolloCsvImporter
{
    private ApolloCsvParser $parser;
    private static string $cacheDir;

    public function __construct(?ApolloCsvParser $parser = null)
    {
        $this->parser = $parser ?? new ApolloCsvParser();
        self::$cacheDir = SLC_ROOT . '/storage/framework/cache/imports';
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0777, true);
        }
    }

    /**
     * Analyze uploaded CSV file, run duplicate checks against DB, and generate a pre-import preview.
     *
     * @return array{ok: bool, error: ?string, preview?: array}
     */
    public function preview(string $filePath, string $originalFileName): array
    {
        $validation = $this->parser->validateFile($filePath, $originalFileName);
        if (!$validation['ok']) {
            return ['ok' => false, 'error' => $validation['error']];
        }

        $parseResult = $this->parser->parse($filePath);
        $headers = $parseResult['headers'];
        $rawRows = $parseResult['rows'];
        $totalRows = count($rawRows);

        if ($totalRows === 0) {
            return ['ok' => false, 'error' => 'No data rows found in CSV file.'];
        }

        $detectedFormat = $this->detectFormat($headers);
        $mappedRows = [];
        $newCount = 0;
        $existingCount = 0;
        $inFileDupCount = 0;
        $invalidCount = 0;

        $seenInFileApolloIds = [];
        $seenInFileEmails = [];
        $seenInFileLinkedIns = [];
        $seenInFileNames = [];

        foreach ($rawRows as $idx => $rawRow) {
            $mapped = $this->mapApolloRow($rawRow, $headers);
            $mapped['row_index'] = $idx + 1;

            // Row Validation
            if (empty($mapped['company_name']) && empty($mapped['contact_name']) && empty($mapped['email'])) {
                $mapped['status'] = 'Invalid';
                $mapped['status_reason'] = 'Missing company, contact name, and email.';
                $invalidCount++;
                $mappedRows[] = $mapped;
                continue;
            }

            // Check In-File Duplicates
            $inFileReason = null;
            if (!empty($mapped['apollo_contact_id']) && isset($seenInFileApolloIds[$mapped['apollo_contact_id']])) {
                $inFileReason = 'Duplicate Apollo Contact ID in same file (row ' . $seenInFileApolloIds[$mapped['apollo_contact_id']] . ')';
            } elseif (!empty($mapped['email']) && isset($seenInFileEmails[strtolower($mapped['email'])])) {
                $inFileReason = 'Duplicate email in same file (row ' . $seenInFileEmails[strtolower($mapped['email'])] . ')';
            } elseif (!empty($mapped['linkedin_url']) && isset($seenInFileLinkedIns[$this->normalizeLinkedIn($mapped['linkedin_url'])])) {
                $inFileReason = 'Duplicate LinkedIn URL in same file';
            } elseif (!empty($mapped['contact_name']) && !empty($mapped['company_name'])) {
                $pairKey = strtolower($mapped['contact_name']) . '||' . strtolower($mapped['company_name']);
                if (isset($seenInFileNames[$pairKey])) {
                    $inFileReason = 'Duplicate Contact + Company in same file';
                }
            }

            if ($inFileReason !== null) {
                $mapped['status'] = 'Duplicate';
                $mapped['status_reason'] = $inFileReason;
                $inFileDupCount++;
                $mappedRows[] = $mapped;
                continue;
            }

            // Track seen in file
            if (!empty($mapped['apollo_contact_id'])) {
                $seenInFileApolloIds[$mapped['apollo_contact_id']] = $idx + 1;
            }
            if (!empty($mapped['email'])) {
                $seenInFileEmails[strtolower($mapped['email'])] = $idx + 1;
            }
            if (!empty($mapped['linkedin_url'])) {
                $seenInFileLinkedIns[$this->normalizeLinkedIn($mapped['linkedin_url'])] = $idx + 1;
            }
            if (!empty($mapped['contact_name']) && !empty($mapped['company_name'])) {
                $pairKey = strtolower($mapped['contact_name']) . '||' . strtolower($mapped['company_name']);
                $seenInFileNames[$pairKey] = $idx + 1;
            }

            // Check Database Duplicate with 4-Tier Priority
            $existing = $this->findExistingRecord($mapped);
            if ($existing !== null) {
                $mapped['status'] = 'Existing';
                $mapped['status_reason'] = $existing['reason'];
                $mapped['existing_lead_id'] = $existing['lead_id'] ?? null;
                $mapped['existing_company_id'] = $existing['company_id'] ?? null;
                $existingCount++;
            } else {
                $mapped['status'] = 'New';
                $mapped['status_reason'] = 'Ready to import as new lead';
                $newCount++;
            }

            $mappedRows[] = $mapped;
        }

        $fileSize = (int)(filesize($filePath) ?: 0);

        // Store parsed batch in temporary cache
        $batchToken = bin2hex(random_bytes(16));
        $cachePayload = [
            'batch_token' => $batchToken,
            'file_name' => $originalFileName,
            'file_size' => $fileSize,
            'headers' => $headers,
            'mapped_rows' => $mappedRows,
            'created_at' => time(),
        ];
        file_put_contents(self::$cacheDir . '/' . $batchToken . '.json', json_encode($cachePayload, JSON_UNESCAPED_UNICODE));

        return [
            'ok' => true,
            'error' => null,
            'preview' => [
                'batch_token' => $batchToken,
                'file_name' => $originalFileName,
                'file_size' => $fileSize,
                'file_size_formatted' => $this->formatFileSize($fileSize),
                'detected_format' => $detectedFormat,
                'total_columns' => count($headers),
                'total_rows' => $totalRows,
                'new_leads_count' => $newCount,
                'existing_leads_count' => $existingCount,
                'in_file_duplicate_count' => $inFileDupCount,
                'invalid_rows_count' => $invalidCount,
                'columns' => $headers,
                'preview_rows' => $mappedRows,
            ],
        ];
    }

    /**
     * Execute final database import from cached batch token.
     *
     * @param array{skip_duplicates?: bool, update_existing?: bool} $options
     * @return array{ok: bool, error: ?string, result?: array}
     */
    public function executeImport(string $batchToken, array $options = [], ?int $userId = null): array
    {
        $cacheFile = self::$cacheDir . '/' . preg_replace('/[^a-f0-9]/', '', $batchToken) . '.json';
        if (!file_exists($cacheFile)) {
            return ['ok' => false, 'error' => 'Import session expired or invalid. Please re-upload the CSV file.'];
        }

        $payload = json_decode(file_get_contents($cacheFile), true);
        if (!$payload || empty($payload['mapped_rows'])) {
            return ['ok' => false, 'error' => 'Failed to read staged import data.'];
        }

        $mappedRows = $payload['mapped_rows'];
        $fileName = $payload['file_name'] ?? 'apollo_import.csv';
        $fileSize = (int)($payload['file_size'] ?? 0);
        $totalRows = count($mappedRows);

        $imported = 0;
        $updated = 0;
        $duplicates = 0;
        $skipped = 0;
        $errors = 0;
        $errorLogs = [];
        $importedLeadIds = [];

        $companyRepo = new CompanyRepository();

        Database::transaction(function () use (
            $mappedRows, $fileName, $fileSize, $totalRows, $batchToken, $userId, $options, $companyRepo,
            &$imported, &$updated, &$duplicates, &$skipped, &$errors, &$errorLogs, &$importedLeadIds
        ) {
            foreach ($mappedRows as $row) {
                $rowIdx = $row['row_index'] ?? 0;

                if ($row['status'] === 'Invalid') {
                    $errors++;
                    $errorLogs[] = ['row' => $rowIdx, 'reason' => $row['status_reason'] ?? 'Invalid row data'];
                    continue;
                }

                if ($row['status'] === 'Duplicate') {
                    $duplicates++;
                    $skipped++;
                    continue;
                }

                // If marked existing or found on live check
                $existing = $this->findExistingRecord($row);
                if ($existing !== null) {
                    $duplicates++;
                    if (!empty($options['update_existing']) && !empty($existing['company_id'])) {
                        // Update company/contact if explicitly requested
                        $this->updateExistingRecord((int)$existing['company_id'], $existing['contact_id'] ?? null, $row);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                try {
                    // 1. Find or create Company
                    $companyId = $this->findOrCreateCompany($row, $companyRepo);

                    // 2. Find or create Contact
                    $contactId = $this->findOrCreateContact($companyId, $row);

                    // 3. Create Lead
                    $leadTitle = !empty($row['contact_name'])
                        ? $row['contact_name'] . ' — ' . ($row['company_name'] ?: 'Prospect')
                        : 'Apollo Lead: ' . ($row['company_name'] ?: 'New Prospect');

                    $leadLocation = trim(($row['city'] ?: '') . ', ' . ($row['state'] ?: '') . ', ' . ($row['country'] ?: ''), ', ');

                    $leadId = Database::insert('slc_leads', [
                        'company_id'      => $companyId,
                        'contact_id'      => $contactId,
                        'title'           => $leadTitle,
                        'industry'        => $row['industry'] ?: null,
                        'location'        => $leadLocation ?: null,
                        'status'          => 'New',
                        'priority'        => $this->calculatePriority($row),
                        'ai_score'        => $this->calculateInitialAiScore($row),
                        'estimated_value' => 0.00,
                        'source'          => 'Apollo CSV',
                        'notes'           => $this->buildLeadNotes($row),
                        'import_batch_id' => $batchToken,
                        'raw_data'        => json_encode([
                            'source_file' => $fileName,
                            'import_batch_id' => $batchToken,
                            'original_apollo_data' => $row['raw_apollo_data'] ?? [],
                        ], JSON_UNESCAPED_UNICODE),
                    ]);

                    // 4. Audit Log in slc_activities
                    Database::insert('slc_activities', [
                        'user_id'     => $userId,
                        'company_id'  => $companyId,
                        'lead_id'     => $leadId,
                        'type'        => 'apollo_import',
                        'description' => 'Imported lead from Apollo CSV: ' . ($row['contact_name'] ?: $row['company_name']),
                        'meta'        => json_encode([
                            'file_name' => $fileName,
                            'batch_id' => $batchToken,
                            'apollo_contact_id' => $row['apollo_contact_id'] ?? null,
                            'email' => $row['email'] ?? null,
                        ], JSON_UNESCAPED_UNICODE),
                    ]);

                    $imported++;
                    $importedLeadIds[] = $leadId;
                } catch (\Throwable $e) {
                    $errors++;
                    $errorLogs[] = [
                        'row' => $rowIdx,
                        'name' => $row['contact_name'] ?: $row['company_name'],
                        'reason' => $e->getMessage(),
                    ];
                }
            }

            // Record in slc_imports table
            Database::insert('slc_imports', [
                'batch_id'        => $batchToken,
                'source'          => 'Apollo CSV',
                'file_name'       => $fileName,
                'file_size'       => $fileSize,
                'total_rows'      => $totalRows,
                'imported_count'  => $imported,
                'updated_count'   => $updated,
                'duplicate_count' => $duplicates,
                'skipped_count'   => $skipped,
                'error_count'     => $errors,
                'error_log'       => !empty($errorLogs) ? json_encode($errorLogs, JSON_UNESCAPED_UNICODE) : null,
                'summary'         => json_encode([
                    'total_rows' => $totalRows,
                    'imported' => $imported,
                    'updated' => $updated,
                    'duplicates' => $duplicates,
                    'skipped' => $skipped,
                    'errors' => $errors,
                ], JSON_UNESCAPED_UNICODE),
                'created_by'      => $userId,
            ]);
        });

        // Clean up temporary cache file
        @unlink($cacheFile);

        return [
            'ok' => true,
            'error' => null,
            'result' => [
                'batch_id' => $batchToken,
                'file_name' => $fileName,
                'total_rows' => $totalRows,
                'imported' => $imported,
                'updated' => $updated,
                'duplicates' => $duplicates,
                'skipped' => $skipped,
                'errors' => $errors,
                'error_details' => $errorLogs,
                'imported_lead_ids' => array_slice($importedLeadIds, 0, 25),
            ],
        ];
    }

    /**
     * Map a raw CSV row (with 70+ columns) to normalized CRM fields while preserving ALL raw data.
     *
     * @param array<string, string> $raw
     * @param array<int, string> $headers
     * @return array
     */
    public function mapApolloRow(array $raw, array $headers): array
    {
        $get = function (array $possibleKeys) use ($raw): string {
            foreach ($possibleKeys as $k) {
                if (isset($raw[$k]) && trim((string)$raw[$k]) !== '') {
                    $v = trim((string)$raw[$k]);
                    return ltrim($v, "'");
                }
            }
            // Case-insensitive fallback
            $lowerRaw = [];
            foreach ($raw as $rk => $rv) {
                $lowerRaw[strtolower(trim($rk))] = ltrim(trim((string)$rv), "'");
            }
            foreach ($possibleKeys as $k) {
                $lk = strtolower(trim($k));
                if (isset($lowerRaw[$lk]) && $lowerRaw[$lk] !== '') {
                    return $lowerRaw[$lk];
                }
            }
            return '';
        };

        // Contact Emails (checked first for fallbacks)
        $email = $get(['Email', 'email', 'Primary Email', 'Work Email', 'Contact Email', 'Secondary Email', 'Tertiary Email']);
        $emailStatus = $get(['Email Status', 'email_status', 'Primary Email Status', 'Email Confidence']);
        $emailCatchAll = $get(['Primary Email Catch-all Status', 'Catch-all Status', 'Catch All']);
        $emailVerifiedAt = $get(['Primary Email Last Verified At', 'Email Last Verified At']);

        // Contact Names
        $firstName = $get(['First Name', 'First name', 'first_name', 'FirstName']);
        $lastName = $get(['Last Name', 'Last name', 'last_name', 'LastName']);
        $fullName = $get(['Name', 'Full Name', 'Contact Name', 'name', 'full_name']);
        if ($fullName === '' && ($firstName !== '' || $lastName !== '')) {
            $fullName = trim($firstName . ' ' . $lastName);
        }
        if ($fullName === '' && $email !== '') {
            $emailUser = explode('@', $email)[0] ?? '';
            $fullName = ucwords(str_replace(['.', '_', '-'], ' ', $emailUser));
        }

        // Job Title & Department
        $jobTitle = $get(['Title', 'Job Title', 'Designation', 'title', 'job_title', 'Role', 'Position', 'Headline']);
        $seniority = $get(['Seniority', 'seniority', 'Level', 'Management Level']);
        $departments = $get(['Departments', 'Department', 'department', 'departments', 'Functions']);
        $subDepartments = $get(['Sub Departments', 'sub_departments', 'Sub Department']);
        if ($departments === '' && $subDepartments !== '') {
            $departments = $subDepartments;
        }
        if ($jobTitle === '' && $seniority !== '') {
            $jobTitle = $seniority . ' Specialist';
        }

        // Company
        $companyName = $get(['Company Name', 'Company', 'Company Name for Emails', 'company_name', 'Account Name', 'Organization', 'Company / Account']);
        $companyWebsite = $get(['Website', 'Company Website', 'website', 'company_website', 'Domain', 'Company Domain Name']);
        if ($companyWebsite === '' && $email !== '') {
            $emailDomain = substr(strrchr($email, "@"), 1);
            if ($emailDomain && !in_array(strtolower($emailDomain), ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com'], true)) {
                $companyWebsite = 'https://' . $emailDomain;
            }
        }
        $industry = $get(['Industry', 'industry', 'Company Industry', 'Sector', 'Primary Industry']);
        $employeeCount = $get(['# Employees', 'Employees', 'employee_count', 'Company Size', 'Number of Employees', 'Total Employees']);
        $annualRevenue = $get(['Annual Revenue', 'Revenue', 'annual_revenue', 'Estimated Revenue', 'Company Revenue']);
        $technologies = $get(['Technologies', 'technologies', 'Tech Stack', 'Tools']);
        $keywords = $get(['Keywords', 'keywords', 'Tags', 'SEO Keywords']);

        // Phones (Sanitizing leading quotes)
        $workPhone = $get(['Work Direct Phone', 'Direct Phone', 'Corporate Phone', 'Company Phone']);
        $mobilePhone = $get(['Mobile Phone', 'Mobile', 'Cell Phone', 'Work Direct Phone', 'Corporate Phone']);
        $phone = $get(['Corporate Phone', 'Company Phone', 'Phone', 'Work Direct Phone', 'Mobile Phone', 'Other Phone']);
        if ($phone === '' && $workPhone !== '') $phone = $workPhone;
        if ($phone === '' && $mobilePhone !== '') $phone = $mobilePhone;
        if ($mobilePhone === '' && $phone !== '') $mobilePhone = $phone;

        // Locations (Person + Company fallbacks)
        $city = $get(['City', 'city', 'Company City', 'Person City']);
        $state = $get(['State', 'state', 'Company State', 'Person State', 'Region']);
        $country = $get(['Country', 'country', 'Company Country', 'Person Country']);
        $address = $get(['Company Address', 'Address', 'address', 'Street', 'Full Address']);
        if ($city === '' && $address !== '') {
            $parts = array_map('trim', explode(',', $address));
            if (count($parts) >= 2) {
                $city = $parts[count($parts) - 3] ?? $parts[0];
            }
        }

        // LinkedIn & Social
        $linkedinUrl = $get(['Person Linkedin Url', 'Person LinkedIn URL', 'LinkedIn URL', 'linkedin_url', 'Contact LinkedIn URL', 'Person Linkedin', 'LinkedIn']);
        $companyLinkedinUrl = $get(['Company Linkedin Url', 'Company LinkedIn URL', 'Company LinkedIn']);
        $facebookUrl = $get(['Facebook Url', 'Facebook URL', 'facebook_url']);
        $twitterUrl = $get(['Twitter Url', 'Twitter URL', 'twitter_url']);

        // Apollo Unique Identifiers
        $apolloContactId = $get(['Apollo Contact Id', 'Apollo Contact ID', 'apollo_contact_id', 'Apollo Record Id']);
        $apolloAccountId = $get(['Apollo Account Id', 'Apollo Account ID', 'apollo_account_id']);

        return [
            // Normalized CRM Fields
            'first_name'            => $firstName,
            'last_name'             => $lastName,
            'contact_name'          => $fullName,
            'job_title'             => $jobTitle,
            'seniority'             => $seniority,
            'department'            => $departments,
            'sub_departments'       => $subDepartments,
            'company_name'          => $companyName,
            'company_website'       => $companyWebsite,
            'industry'              => $industry,
            'employee_count'        => $employeeCount,
            'annual_revenue'        => $annualRevenue,
            'technologies'          => $technologies,
            'keywords'              => $keywords,
            'email'                 => $email,
            'email_status'          => $emailStatus,
            'email_catch_all'       => $emailCatchAll,
            'email_verified_at'     => $emailVerifiedAt,
            'phone'                 => $phone,
            'mobile'                => $mobilePhone,
            'work_phone'            => $workPhone,
            'city'                  => $city,
            'state'                 => $state,
            'country'               => $country,
            'address'               => $address,
            'linkedin_url'          => $linkedinUrl,
            'company_linkedin_url'  => $companyLinkedinUrl,
            'facebook_url'          => $facebookUrl,
            'twitter_url'           => $twitterUrl,
            'apollo_contact_id'     => $apolloContactId,
            'apollo_account_id'     => $apolloAccountId,

            // 100% Raw Original Row Preserved
            'raw_apollo_data'       => $raw,
        ];
    }

    /**
     * Strict 4-tier duplicate check against database:
     * Priority:
     * 1. Apollo Contact ID
     * 2. Email
     * 3. LinkedIn URL
     * 4. Full Name + Company Name
     *
     * @return array{company_id: int, contact_id: ?int, lead_id: ?int, reason: string}|null
     */
    public function findExistingRecord(array $mapped): ?array
    {
        // 1. Check Apollo Contact ID
        if (!empty($mapped['apollo_contact_id'])) {
            $contact = Database::fetch(
                'SELECT c.id, c.company_id, l.id as lead_id FROM slc_contacts c
                 LEFT JOIN slc_leads l ON l.contact_id = c.id
                 WHERE (c.apollo_contact_id = :aid OR JSON_UNQUOTE(JSON_EXTRACT(c.raw_data, "$.original_apollo_data.`Apollo Contact Id`")) = :aid2)
                 AND c.deleted_at IS NULL LIMIT 1',
                ['aid' => $mapped['apollo_contact_id'], 'aid2' => $mapped['apollo_contact_id']]
            );
            if ($contact) {
                return [
                    'company_id' => (int)$contact['company_id'],
                    'contact_id' => (int)$contact['id'],
                    'lead_id'    => $contact['lead_id'] ? (int)$contact['lead_id'] : null,
                    'reason'     => 'Matched by Apollo Contact ID (' . $mapped['apollo_contact_id'] . ')',
                ];
            }
        }

        // 2. Check Email (Contact or Company)
        if (!empty($mapped['email'])) {
            $cleanEmail = strtolower(trim($mapped['email']));
            $contact = Database::fetch(
                'SELECT c.id, c.company_id, l.id as lead_id FROM slc_contacts c
                 LEFT JOIN slc_leads l ON l.contact_id = c.id
                 WHERE LOWER(c.email) = :e AND c.deleted_at IS NULL LIMIT 1',
                ['e' => $cleanEmail]
            );
            if ($contact) {
                return [
                    'company_id' => (int)$contact['company_id'],
                    'contact_id' => (int)$contact['id'],
                    'lead_id'    => $contact['lead_id'] ? (int)$contact['lead_id'] : null,
                    'reason'     => 'Matched by Email (' . $cleanEmail . ')',
                ];
            }

            $comp = Database::fetch(
                'SELECT id FROM slc_companies WHERE LOWER(email) = :e AND deleted_at IS NULL LIMIT 1',
                ['e' => $cleanEmail]
            );
            if ($comp) {
                $lead = Database::fetch('SELECT id FROM slc_leads WHERE company_id = :cid AND deleted_at IS NULL LIMIT 1', ['cid' => $comp['id']]);
                return [
                    'company_id' => (int)$comp['id'],
                    'contact_id' => null,
                    'lead_id'    => $lead ? (int)$lead['id'] : null,
                    'reason'     => 'Matched by Company Email (' . $cleanEmail . ')',
                ];
            }
        }

        // 3. Check LinkedIn URL
        if (!empty($mapped['linkedin_url'])) {
            $normLinkedIn = $this->normalizeLinkedIn($mapped['linkedin_url']);
            $contact = Database::fetch(
                'SELECT c.id, c.company_id, l.id as lead_id FROM slc_contacts c
                 LEFT JOIN slc_leads l ON l.contact_id = c.id
                 WHERE (c.linkedin_url LIKE :li OR c.linkedin_url LIKE :li2)
                 AND c.deleted_at IS NULL LIMIT 1',
                ['li' => '%' . $normLinkedIn . '%', 'li2' => '%' . $mapped['linkedin_url'] . '%']
            );
            if ($contact) {
                return [
                    'company_id' => (int)$contact['company_id'],
                    'contact_id' => (int)$contact['id'],
                    'lead_id'    => $contact['lead_id'] ? (int)$contact['lead_id'] : null,
                    'reason'     => 'Matched by LinkedIn URL (' . $mapped['linkedin_url'] . ')',
                ];
            }
        }

        // 4. Check Combination of Full Name + Company Name
        if (!empty($mapped['contact_name']) && !empty($mapped['company_name'])) {
            $row = Database::fetch(
                'SELECT c.id as contact_id, c.company_id, l.id as lead_id FROM slc_contacts c
                 INNER JOIN slc_companies comp ON comp.id = c.company_id
                 LEFT JOIN slc_leads l ON l.contact_id = c.id
                 WHERE LOWER(c.name) = :cname AND LOWER(comp.name) = :compname
                 AND c.deleted_at IS NULL AND comp.deleted_at IS NULL LIMIT 1',
                ['cname' => strtolower($mapped['contact_name']), 'compname' => strtolower($mapped['company_name'])]
            );
            if ($row) {
                return [
                    'company_id' => (int)$row['company_id'],
                    'contact_id' => (int)$row['contact_id'],
                    'lead_id'    => $row['lead_id'] ? (int)$row['lead_id'] : null,
                    'reason'     => 'Matched by Name (' . $mapped['contact_name'] . ') + Company (' . $mapped['company_name'] . ')',
                ];
            }
        }

        return null;
    }

    /**
     * Find existing company by name, domain, or create new.
     */
    private function findOrCreateCompany(array $row, CompanyRepository $repo): int
    {
        $compName = $row['company_name'] ?: ($row['contact_name'] . ' Company');
        $domain = $row['company_website'] ? $this->extractDomain($row['company_website']) : null;

        $existing = $repo->findExisting($compName, $domain, $row['phone'] ?: null, null);
        if ($existing) {
            return (int)$existing['id'];
        }

        return $repo->create([
            'name'              => $compName,
            'industry'          => $row['industry'] ?: null,
            'city'              => $row['city'] ?: null,
            'state'             => $row['state'] ?: null,
            'country'           => $row['country'] ?: null,
            'website'           => $row['company_website'] ?: null,
            'phone'             => $row['phone'] ?: null,
            'employee_count'    => $row['employee_count'] ?: null,
            'description'       => $this->buildCompanyDescription($row),
            'ai_score'          => $this->calculateInitialAiScore($row),
            'ai_priority'       => $this->calculatePriority($row),
            'source'            => 'Apollo CSV',
            'apollo_account_id' => $row['apollo_account_id'] ?: null,
            'raw_data'          => json_encode($row['raw_apollo_data'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Find existing contact or create new.
     */
    private function findOrCreateContact(int $companyId, array $row): ?int
    {
        if (empty($row['contact_name']) && empty($row['email'])) {
            return null;
        }

        $contactName = $row['contact_name'] ?: 'General Contact';

        // Check if contact exists in this company
        $existing = Database::fetch(
            'SELECT id FROM slc_contacts WHERE company_id = :cid AND (LOWER(name) = :n OR (email IS NOT NULL AND LOWER(email) = :e)) AND deleted_at IS NULL LIMIT 1',
            ['cid' => $companyId, 'n' => strtolower($contactName), 'e' => strtolower($row['email'] ?: '___none___')]
        );

        if ($existing) {
            return (int)$existing['id'];
        }

        return Database::insert('slc_contacts', [
            'company_id'        => $companyId,
            'name'              => $contactName,
            'designation'       => $row['job_title'] ?: ($row['seniority'] ?: null),
            'department'        => $row['department'] ?: null,
            'email'             => $row['email'] ?: null,
            'phone'             => $row['phone'] ?: null,
            'mobile'            => $row['mobile'] ?: null,
            'linkedin_url'      => $row['linkedin_url'] ?: null,
            'is_decision_maker' => $this->isDecisionMaker($row),
            'is_primary'        => 1,
            'importance'        => $this->calculateImportance($row),
            'notes'             => $this->buildContactNotes($row),
            'source'            => 'Apollo CSV',
            'apollo_contact_id' => $row['apollo_contact_id'] ?: null,
            'raw_data'          => json_encode($row['raw_apollo_data'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Update existing company and contact with fresh Apollo data if fields were empty.
     */
    private function updateExistingRecord(int $companyId, ?int $contactId, array $row): void
    {
        $comp = Database::fetch('SELECT * FROM slc_companies WHERE id = :id', ['id' => $companyId]);
        if ($comp) {
            $updates = [];
            if (empty($comp['website']) && !empty($row['company_website'])) $updates['website'] = $row['company_website'];
            if (empty($comp['industry']) && !empty($row['industry'])) $updates['industry'] = $row['industry'];
            if (empty($comp['city']) && !empty($row['city'])) $updates['city'] = $row['city'];
            if (empty($comp['state']) && !empty($row['state'])) $updates['state'] = $row['state'];
            if (empty($comp['phone']) && !empty($row['phone'])) $updates['phone'] = $row['phone'];
            if (empty($comp['employee_count']) && !empty($row['employee_count'])) $updates['employee_count'] = $row['employee_count'];
            if (empty($comp['apollo_account_id']) && !empty($row['apollo_account_id'])) $updates['apollo_account_id'] = $row['apollo_account_id'];

            if (!empty($updates)) {
                Database::update('slc_companies', $companyId, $updates);
            }
        }

        if ($contactId) {
            $contact = Database::fetch('SELECT * FROM slc_contacts WHERE id = :id', ['id' => $contactId]);
            if ($contact) {
                $cUpdates = [];
                if (empty($contact['email']) && !empty($row['email'])) $cUpdates['email'] = $row['email'];
                if (empty($contact['phone']) && !empty($row['phone'])) $cUpdates['phone'] = $row['phone'];
                if (empty($contact['linkedin_url']) && !empty($row['linkedin_url'])) $cUpdates['linkedin_url'] = $row['linkedin_url'];
                if (empty($contact['designation']) && !empty($row['job_title'])) $cUpdates['designation'] = $row['job_title'];
                if (empty($contact['apollo_contact_id']) && !empty($row['apollo_contact_id'])) $cUpdates['apollo_contact_id'] = $row['apollo_contact_id'];

                if (!empty($cUpdates)) {
                    Database::update('slc_contacts', $contactId, $cUpdates);
                }
            }
        }
    }

    private function detectFormat(array $headers): string
    {
        $hStr = strtolower(implode('|', $headers));
        if (str_contains($hStr, 'apollo') || (str_contains($hStr, 'person linkedin') && str_contains($hStr, 'company linkedin'))) {
            return 'Apollo Contacts Export CSV';
        }
        return 'Standard CSV Contacts';
    }

    private function isDecisionMaker(array $row): int
    {
        $title = strtolower($row['job_title'] . ' ' . $row['seniority']);
        $keywords = ['purchas', 'procurement', 'director', 'manager', 'head', 'owner', 'founder', 'vp', 'chief', 'partner', 'lead', 'executive'];
        foreach ($keywords as $k) {
            if (str_contains($title, $k)) {
                return 1;
            }
        }
        return 0;
    }

    private function calculatePriority(array $row): string
    {
        $emp = (int)preg_replace('/\D/', '', (string)$row['employee_count']);
        $title = strtolower($row['job_title'] . ' ' . $row['seniority']);

        if (str_contains($title, 'head') || str_contains($title, 'director') || str_contains($title, 'purchas') || $emp >= 200) {
            return 'High';
        }
        if ($emp >= 50 || str_contains($title, 'manager') || str_contains($title, 'lead')) {
            return 'Medium';
        }
        return 'Low';
    }

    private function calculateInitialAiScore(array $row): int
    {
        $score = 70; // baseline for verified Apollo contact

        if ($row['email_status'] === 'Verified') $score += 10;
        if (!empty($row['linkedin_url'])) $score += 5;
        if (!empty($row['phone'])) $score += 5;

        $title = strtolower($row['job_title']);
        if (str_contains($title, 'purchas') || str_contains($title, 'procure') || str_contains($title, 'packag')) {
            $score += 10;
        }

        return min(98, max(50, $score));
    }

    private function calculateImportance(array $row): string
    {
        $prio = $this->calculatePriority($row);
        return $prio === 'High' ? 'High' : ($prio === 'Medium' ? 'Medium' : 'Low');
    }

    private function buildCompanyDescription(array $row): string
    {
        $parts = [];
        if (!empty($row['keywords'])) $parts[] = 'Keywords: ' . substr($row['keywords'], 0, 300);
        if (!empty($row['technologies'])) $parts[] = 'Technologies: ' . substr($row['technologies'], 0, 300);
        if (!empty($row['annual_revenue'])) $parts[] = 'Annual Revenue: ' . $row['annual_revenue'];
        return implode("\n\n", $parts);
    }

    private function buildContactNotes(array $row): string
    {
        $notes = [];
        if (!empty($row['email_status'])) $notes[] = 'Email Status: ' . $row['email_status'];
        if (!empty($row['email_catch_all'])) $notes[] = 'Catch-all: ' . $row['email_catch_all'];
        if (!empty($row['seniority'])) $notes[] = 'Seniority: ' . $row['seniority'];
        if (!empty($row['sub_departments'])) $notes[] = 'Sub-department: ' . $row['sub_departments'];
        return implode(" | ", $notes);
    }

    private function buildLeadNotes(array $row): string
    {
        $lines = [];
        $lines[] = 'Imported from Apollo.io Dashboard Export';
        if (!empty($row['job_title'])) $lines[] = 'Role: ' . $row['job_title'];
        if (!empty($row['department'])) $lines[] = 'Department: ' . $row['department'];
        if (!empty($row['email_status'])) $lines[] = 'Email Status: ' . $row['email_status'];
        if (!empty($row['keywords'])) $lines[] = 'Tags: ' . substr($row['keywords'], 0, 160) . '...';
        return implode("\n", $lines);
    }

    private function normalizeLinkedIn(string $url): string
    {
        $url = strtolower(trim($url));
        $url = preg_replace('#^https?://(www\.)?linkedin\.com/#', '', $url);
        return rtrim($url, '/');
    }

    private function extractDomain(?string $url): ?string
    {
        if (!$url) return null;
        $h = parse_url($url, PHP_URL_HOST) ?: parse_url('https://' . $url, PHP_URL_HOST);
        if (!$h) return null;
        return preg_replace('/^www\./', '', strtolower($h));
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
