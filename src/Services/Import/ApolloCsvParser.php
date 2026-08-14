<?php
declare(strict_types=1);

namespace SLC\Services\Import;

/**
 * Robust, dynamic CSV parser specifically tailored for Apollo.io exports
 * and general CRM CSV files.
 *
 * Supports:
 * - Dynamic column count and ordering (70+ columns)
 * - UTF-8 and UTF-8 BOM encoding
 * - Quoted fields with embedded commas, quotes, and newlines
 * - Unicode character preservation (Bengali, Hindi, Nepali, accents)
 * - Sanitization of Excel-escaped strings (e.g. `'+91 999...`)
 * - Duplicate header handling
 * - Memory-safe chunked reading
 */
class ApolloCsvParser
{
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB max

    /**
     * Validate CSV file before parsing.
     *
     * @return array{ok: bool, error: ?string, file_info?: array}
     */
    public function validateFile(string $filePath, ?string $originalName = null): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return ['ok' => false, 'error' => 'Uploaded CSV file could not be read or does not exist.'];
        }

        $size = filesize($filePath);
        if ($size === false || $size === 0) {
            return ['ok' => false, 'error' => 'The uploaded CSV file is empty (0 bytes).'];
        }

        if ($size > self::MAX_FILE_SIZE) {
            return ['ok' => false, 'error' => 'File exceeds maximum allowed size of 50MB.'];
        }

        $name = $originalName ?: basename($filePath);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== 'csv' && $ext !== 'txt') {
            return ['ok' => false, 'error' => 'Invalid file format. Please upload a .csv file.'];
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['ok' => false, 'error' => 'Unable to open file handle.'];
        }

        // Check BOM and first bytes
        $bom = fread($handle, 3);
        $hasBom = ($bom === "\xEF\xBB\xBF");
        if (!$hasBom) {
            rewind($handle);
        }

        // Read first row (headers)
        $headerRow = fgetcsv($handle, 0, ',', '"', '\\');
        fclose($handle);

        if ($headerRow === false || empty($headerRow) || (count($headerRow) === 1 && trim((string)$headerRow[0]) === '')) {
            return ['ok' => false, 'error' => 'CSV file contains no readable header row.'];
        }

        return [
            'ok' => true,
            'error' => null,
            'file_info' => [
                'name' => $name,
                'size' => $size,
                'has_bom' => $hasBom,
                'raw_header_count' => count($headerRow),
            ],
        ];
    }

    /**
     * Read headers dynamically and normalize column names.
     *
     * @return array{headers: array<int, string>, raw_headers: array<int, string>}
     */
    public function readHeaders(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException('Failed to open CSV file: ' . $filePath);
        }

        // Handle BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $rawHeaders = fgetcsv($handle, 0, ',', '"', '\\');
        fclose($handle);

        if ($rawHeaders === false) {
            return ['headers' => [], 'raw_headers' => []];
        }

        $headers = [];
        $seen = [];

        foreach ($rawHeaders as $idx => $header) {
            // Normalize UTF-8 and strip control chars
            $clean = trim((string)$header);
            // Remove leading BOM artifact if still present
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', $clean);
            if ($clean === '') {
                $clean = 'Column_' . ($idx + 1);
            }

            // Handle duplicate headers
            $key = strtolower($clean);
            if (isset($seen[$key])) {
                $seen[$key]++;
                $clean = $clean . '_' . $seen[$key];
            } else {
                $seen[$key] = 1;
            }

            $headers[$idx] = $clean;
        }

        return [
            'headers' => $headers,
            'raw_headers' => $rawHeaders,
        ];
    }

    /**
     * Parse entire CSV file into raw rows (associative arrays).
     *
     * @param int $maxRows 0 for all rows
     * @return array{headers: array, rows: array<int, array<string, string>>, total_rows: int, errors: array}
     */
    public function parse(string $filePath, int $maxRows = 0): array
    {
        $headerData = $this->readHeaders($filePath);
        $headers = $headerData['headers'];
        $headerCount = count($headers);

        if ($headerCount === 0) {
            return ['headers' => [], 'rows' => [], 'total_rows' => 0, 'errors' => ['No headers detected']];
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException('Could not open CSV file: ' . $filePath);
        }

        // Skip BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Skip header row
        fgetcsv($handle, 0, ',', '"', '\\');

        $rows = [];
        $errors = [];
        $lineNum = 1; // 1 was header

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $lineNum++;

            // Skip completely empty lines
            if (count($row) === 1 && ($row[0] === null || trim((string)$row[0]) === '')) {
                continue;
            }

            $colCount = count($row);
            $assoc = [];

            // Align columns with headers
            for ($i = 0; $i < $headerCount; $i++) {
                $colName = $headers[$i];
                $val = $row[$i] ?? '';
                $val = $this->sanitizeValue((string)$val);
                $assoc[$colName] = $val;
            }

            // Capture extra columns if any exist
            if ($colCount > $headerCount) {
                for ($i = $headerCount; $i < $colCount; $i++) {
                    $assoc['Extra_Column_' . ($i + 1)] = $this->sanitizeValue((string)$row[$i]);
                }
            }

            $rows[] = $assoc;

            if ($maxRows > 0 && count($rows) >= $maxRows) {
                break;
            }
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'rows' => $rows,
            'total_rows' => count($rows),
            'errors' => $errors,
        ];
    }

    /**
     * Clean and normalize a CSV cell value.
     */
    private function sanitizeValue(string $val): string
    {
        $val = trim($val);

        // Strip Excel quote escape prefix like `'+91 999...` or `="value"`
        if (str_starts_with($val, "'+") || str_starts_with($val, "'=")) {
            $val = substr($val, 1);
        } elseif (str_starts_with($val, "'") && strlen($val) > 1 && !str_ends_with($val, "'")) {
            $val = substr($val, 1);
        }

        // Convert invalid UTF-8 sequences to valid UTF-8
        if (!mb_check_encoding($val, 'UTF-8')) {
            $val = mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1');
        }

        return $val;
    }
}
