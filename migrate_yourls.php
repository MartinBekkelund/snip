<?php
/**
 * Snip - URL Shortener
 * YOURLS Migration Script
 * 
 * Migrates data from YOURLS to Snip.
 * 
 * @package    Snip
 * @version    1.0.0
 * @author     Martin Bekkelund
 * @copyright  2025 Martin Bekkelund
 * @license    GPL-3.0-or-later
 * @link       https://github.com/MartinBekkelund/snip
 * 
 * This file is part of Snip.
 * 
 * Snip is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Snip is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Snip. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Usage:
 *   1. Place this script in the Snip folder
 *   2. Place your YOURLS SQL dump in the same folder
 *   3. Update the configuration below
 *   4. Run: php migrate_yourls.php
 *   
 * The script will:
 *   - Read YOURLS SQL dump
 *   - Map data to Snip format
 *   - Import to Snip database
 *   - Generate migration report
 */

// ============================================
// CONFIGURATION - Update these values
// ============================================

// SN/P database (after install.php has been run)
$snip_config = [
    'host' => 'localhost',
    'name' => 'snp_database',    // Change to your SN/P database name
    'user' => 'snp_user',        // Change to your database user
    'pass' => '',                // Change to your database password
];

// YOURLS SQL dump file (place in same directory)
$yourls_dump_file = __DIR__ . '/yourls_export.sql';

// Run in test mode first? (no data is written)
$dry_run = true;  // Set to false to actually migrate

// ============================================
// SCRIPT START
// ============================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║            YOURLS → SN/P Migration Script                   ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

if ($dry_run) {
    echo "⚠️  RUNNING IN TEST MODE (dry run) - no data is written\n";
    echo "   Set \$dry_run = false to perform the migration\n\n";
}

// Statistikk
$stats = [
    'total' => 0,
    'migrated' => 0,
    'skipped' => 0,
    'errors' => 0,
    'total_clicks' => 0,
];

$skipped_reasons = [];
$errors = [];
$migrated_urls = [];

// ============================================
// STEG 1: Les YOURLS SQL-dump
// ============================================

echo "📂 Steg 1: Leser YOURLS SQL-dump...\n";

if (!file_exists($yourls_dump_file)) {
    die("   ❌ Finner ikke fil: $yourls_dump_file\n");
}

$sql_content = file_get_contents($yourls_dump_file);
echo "   ✓ Lest " . number_format(strlen($sql_content)) . " bytes\n";

// Parse INSERT statements
$yourls_data = [];
preg_match_all(
    "/INSERT INTO `yourls_url`.*?VALUES\s*(.*?);/is",
    $sql_content,
    $insert_matches
);

foreach ($insert_matches[1] as $values_block) {
    // Match each row of values
    preg_match_all(
        "/\('([^']*)',\s*'([^']*)',\s*(?:'([^']*)'|NULL),\s*'([^']*)',\s*'([^']*)',\s*(\d+)\)/",
        $values_block,
        $row_matches,
        PREG_SET_ORDER
    );
    
    foreach ($row_matches as $row) {
        $yourls_data[] = [
            'keyword' => $row[1],
            'url' => $row[2],
            'title' => $row[3] ?? null,
            'timestamp' => $row[4],
            'ip' => $row[5],
            'clicks' => (int)$row[6],
        ];
    }
}

$stats['total'] = count($yourls_data);
echo "   ✓ Funnet {$stats['total']} URLer i YOURLS-dump\n\n";

// ============================================
// STEG 2: Koble til SN/P database
// ============================================

echo "🔌 Steg 2: Kobler til SN/P database...\n";

if (!$dry_run) {
    try {
        $pdo = new PDO(
            "mysql:host={$snip_config['host']};dbname={$snip_config['name']};charset=utf8mb4",
            $snip_config['user'],
            $snip_config['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        echo "   ✓ Koblet til database: {$snip_config['name']}\n\n";
    } catch (PDOException $e) {
        die("   ❌ Kunne ikke koble til database: " . $e->getMessage() . "\n");
    }
} else {
    echo "   ⏭️  Hopper over (dry run)\n\n";
}

// ============================================
// STEG 3: Valider og transformer data
// ============================================

echo "🔍 Steg 3: Validerer og transformerer data...\n";

// Reserverte kortkoder i SN/P
$reserved_codes = ['admin', 'api', 'stats', 'login', 'logout', 'install', 'index', 'redirect'];

$prepared_data = [];

foreach ($yourls_data as $row) {
    $keyword = trim($row['keyword']);
    $url = trim($row['url']);
    
    // Sjekk reserverte kortkoder
    if (in_array(strtolower($keyword), $reserved_codes)) {
        $skipped_reasons[] = "'{$keyword}' er reservert i SN/P";
        $stats['skipped']++;
        continue;
    }
    
    // Valider URL
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        $skipped_reasons[] = "'{$keyword}' har ugyldig URL: {$url}";
        $stats['skipped']++;
        continue;
    }
    
    // Valider kortkode (tillat alle tegn fra YOURLS for bakoverkompatibilitet)
    if (empty($keyword)) {
        $skipped_reasons[] = "Tom kortkode for URL: {$url}";
        $stats['skipped']++;
        continue;
    }
    
    // Legg til i prepared data
    $prepared_data[] = [
        'short_code' => $keyword,
        'original_url' => $url,
        'created_at' => $row['timestamp'],
        'ip_address' => $row['ip'],
        'click_count' => $row['clicks'],
        'is_active' => 1,
    ];
    
    $stats['total_clicks'] += $row['clicks'];
}

echo "   ✓ {$stats['total']} URLer analysert\n";
echo "   ✓ " . count($prepared_data) . " klare for migrering\n";
echo "   ✓ {$stats['skipped']} hoppet over\n\n";

// ============================================
// STEG 4: Importer til SN/P
// ============================================

echo "💾 Steg 4: Importerer til SN/P database...\n";

if (!$dry_run) {
    $insert_sql = "
        INSERT INTO urls (short_code, original_url, created_at, ip_address, click_count, is_active)
        VALUES (:short_code, :original_url, :created_at, :ip_address, :click_count, :is_active)
        ON DUPLICATE KEY UPDATE
            original_url = VALUES(original_url),
            click_count = VALUES(click_count)
    ";
    
    $stmt = $pdo->prepare($insert_sql);
    
    foreach ($prepared_data as $data) {
        try {
            $stmt->execute($data);
            $stats['migrated']++;
            $migrated_urls[] = $data['short_code'];
        } catch (PDOException $e) {
            $errors[] = "Feil ved '{$data['short_code']}': " . $e->getMessage();
            $stats['errors']++;
        }
    }
    
    echo "   ✓ {$stats['migrated']} URLer importert\n";
    if ($stats['errors'] > 0) {
        echo "   ⚠️  {$stats['errors']} feil oppstod\n";
    }
} else {
    $stats['migrated'] = count($prepared_data);
    echo "   ⏭️  Hopper over (dry run) - ville importert {$stats['migrated']} URLer\n";
}

echo "\n";

// ============================================
// STEG 5: Generer rapport
// ============================================

echo "📊 MIGRASJONSRAPPORT\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "Oppsummering:\n";
echo "  • Totalt i YOURLS:     {$stats['total']} URLer\n";
echo "  • Migrert til SN/P:    {$stats['migrated']} URLer\n";
echo "  • Hoppet over:         {$stats['skipped']} URLer\n";
echo "  • Feil:                {$stats['errors']}\n";
echo "  • Totalt antall klikk: " . number_format($stats['total_clicks']) . "\n\n";

// Vis kortkode-statistikk
$code_lengths = [];
foreach ($prepared_data as $data) {
    $len = strlen($data['short_code']);
    $code_lengths[$len] = ($code_lengths[$len] ?? 0) + 1;
}
ksort($code_lengths);

echo "Kortkode-lengder:\n";
foreach ($code_lengths as $len => $count) {
    echo "  • {$len} tegn: {$count} URLer\n";
}
echo "\n";

// Topp 10 mest klikkede
usort($prepared_data, fn($a, $b) => $b['click_count'] - $a['click_count']);
echo "Topp 10 mest klikkede URLer:\n";
echo "  ┌──────────────────┬────────────┬─────────────────────────────────────────┐\n";
echo "  │ Kortkode         │ Klikk      │ URL                                     │\n";
echo "  ├──────────────────┼────────────┼─────────────────────────────────────────┤\n";
for ($i = 0; $i < min(10, count($prepared_data)); $i++) {
    $d = $prepared_data[$i];
    $code = str_pad(substr($d['short_code'], 0, 16), 16);
    $clicks = str_pad(number_format($d['click_count']), 10);
    $url = substr($d['original_url'], 0, 39);
    echo "  │ {$code} │ {$clicks} │ {$url} │\n";
}
echo "  └──────────────────┴────────────┴─────────────────────────────────────────┘\n\n";

// Vis eventuelle feil
if (!empty($skipped_reasons)) {
    echo "Hoppet over (årsaker):\n";
    foreach (array_slice($skipped_reasons, 0, 10) as $reason) {
        echo "  • {$reason}\n";
    }
    if (count($skipped_reasons) > 10) {
        echo "  • ... og " . (count($skipped_reasons) - 10) . " flere\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "Feil under import:\n";
    foreach ($errors as $error) {
        echo "  • {$error}\n";
    }
    echo "\n";
}

// Neste steg
echo "═══════════════════════════════════════════════════════════════\n";
if ($dry_run) {
    echo "\n🔄 NESTE STEG:\n";
    echo "   1. Sjekk rapporten ovenfor\n";
    echo "   2. Hvis alt ser bra ut, åpne dette scriptet\n";
    echo "   3. Sett \$dry_run = false på linje 31\n";
    echo "   4. Kjør scriptet på nytt: php migrate_yourls.php\n\n";
} else {
    echo "\n✅ MIGRERING FULLFØRT!\n";
    echo "   • Test that old short codes work: https://your-domain.com/test-code\n";
    echo "   • Open admin panel: https://your-domain.com/admin.html\n";
    echo "   • Slett dette scriptet når alt fungerer\n\n";
}

echo "═══════════════════════════════════════════════════════════════\n\n";
