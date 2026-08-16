<?php
declare(strict_types=1);

/**
 * Script to generate a clean, 1-click Production Deployment Package
 * for Hostinger & GitHub repository deployment.
 */

$today = date('Y-m-d');
$packageName = "shree_label_digital_sales_person_{$today}";
$sourceDir = dirname(__DIR__);
$targetDir = $sourceDir . '/' . $packageName;

echo "=== BUILDING PRODUCTION DEPLOYMENT PACKAGE ===\n";
echo "Source: {$sourceDir}\n";
echo "Target: {$targetDir}\n\n";

if (is_dir($targetDir)) {
    echo "Cleaning existing target directory...\n";
    deleteDirectoryRecursive($targetDir);
}

mkdir($targetDir, 0755, true);

// Files and directories to copy
$itemsToCopy = [
    '.htaccess',
    'index.php',
    'login.php',
    'setup.php',
    'SKILL.md',
    'README.md',
    'database',
    'src',
    'templates',
    'routes',
    'public',
    'lead_generation',
    'shree_label_backup_latest.json',
];

foreach ($itemsToCopy as $item) {
    $srcPath = $sourceDir . '/' . $item;
    $dstPath = $targetDir . '/' . $item;

    if (!file_exists($srcPath)) {
        continue;
    }

    if (is_dir($srcPath)) {
        copyDirectoryRecursive($srcPath, $dstPath);
    } else {
        copy($srcPath, $dstPath);
    }
}

// Write Hostinger Deployment Guide inside package
$deploymentGuide = <<<MARKDOWN
# Hostinger & GitHub Deployment Guide
**Project:** Shree Label Digital Sales Person  
**Package Date:** {$today}  
**Developed by:** Mriganka Bhusan Debnath  

---

## 🚀 Step-by-Step Hostinger Live Server Deployment

1. **Upload Files:**
   - Upload all files from this folder (`{$packageName}`) to your Hostinger `public_html` directory (or subdirectory).

2. **Create MySQL Database on Hostinger:**
   - In Hostinger hPanel, go to **Databases → Management**.
   - Create a new MySQL Database (e.g., `u123456789_slc_sales`).
   - Create a Database User & Password.

3. **Run 1-Click Installer:**
   - Open your browser and navigate to:  
     `https://yourdomain.com/setup.php`
   - Enter your Hostinger MySQL Host (`localhost`), Database Name, Database User, and Password.
   - Click **Install / Setup Database**.
   - Your database tables and initial admin account (`gm.shreelabel@gmail.com`) will be created automatically!

4. **Import Data / Backup Package (Optional):**
   - Log in at `https://yourdomain.com/login.php` using email `gm.shreelabel@gmail.com` and password `gm.shreelabel@gmail.com`.
   - Go to **AI Settings** page.
   - Click **📤 Import Backup Package** to restore your saved JSON backup in 1-click!

---

## 🐙 Pushing to GitHub (Clean Blank Install Method)

If pushing directly via API gave errors previously:

1. Create a new repository on GitHub (e.g., `shree-label-digital-sales-person`).
2. In your terminal inside this folder, run:
   ```bash
   git init
   git add .
   git commit -m "Initial release - Shree Label Digital Sales Person ({$today})"
   git branch -M main
   git remote add origin https://github.com/YOUR_USERNAME/shree-label-digital-sales-person.git
   git push -u origin main
   ```

---

*Generated automatically by Shree Label Digital Sales Person Package Builder.*
MARKDOWN;

file_put_contents($targetDir . '/README_DEPLOYMENT.md', $deploymentGuide);

echo "✅ Production Package Created Successfully!\n";
echo "Folder Path: " . $targetDir . "\n\n";

function copyDirectoryRecursive(string $src, string $dst): void
{
    @mkdir($dst, 0755, true);
    $dir = opendir($src);
    while (false !== ($file = readdir($dir))) {
        if ($file === '.' || $file === '..' || $file === '.git' || $file === 'scratch') {
            continue;
        }
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        if (is_dir($srcPath)) {
            copyDirectoryRecursive($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
    closedir($dir);
}

function deleteDirectoryRecursive(string $dir): bool
{
    if (!is_dir($dir)) {
        return false;
    }
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            deleteDirectoryRecursive($path);
        } else {
            @chmod($path, 0777);
            @unlink($path);
        }
    }
    return @rmdir($dir);
}
