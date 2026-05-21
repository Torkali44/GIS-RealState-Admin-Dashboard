<?php
// ارفعه في: public_html/app/myApp/public/diag2.php
// افتحه: https://gisbahrain.com/app/myApp/public/diag2.php?key=gis2026
// أو حسب الـ domain الحقيقي للمشروع

$secret = 'gis2026';
if (($_GET['key'] ?? '') !== $secret) { die('403'); }

header('Content-Type: text/plain; charset=utf-8');

// ===== إيجاد Laravel root =====
// الملف ده في public/ — Laravel root هو المجلد اللي فوقيه
$laravelRoot = dirname(__DIR__); // /home/gisbjquz/public_html/app/myApp

echo "=== PATH DETECTION ===\n";
echo "This file: " . __FILE__ . "\n";
echo "Laravel root (detected): " . $laravelRoot . "\n";
echo "vendor path: " . $laravelRoot . "/vendor\n";
echo "vendor exists: " . (is_dir($laravelRoot . "/vendor") ? "YES ✓" : "NO ✗") . "\n";
echo "autoload.php exists: " . (file_exists($laravelRoot . "/vendor/autoload.php") ? "YES ✓" : "NO ✗") . "\n";
echo "\n";

// ===== تحميل autoloader =====
$autoloadPath = $laravelRoot . "/vendor/autoload.php";
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    echo "autoload.php: LOADED ✓\n";
} else {
    echo "autoload.php: NOT FOUND ✗ — composer install لازم يتشغل\n";
}
echo "\n";

// ===== FPDI =====
echo "=== FPDI ===\n";
$fpdiDir = $laravelRoot . "/vendor/setasign/fpdi";
echo "setasign/fpdi dir: " . (is_dir($fpdiDir) ? "EXISTS ✓" : "NOT FOUND ✗") . "\n";
echo "FPDI class (after autoload): " . (class_exists('setasign\\Fpdi\\Fpdi') ? "AVAILABLE ✓" : "NOT FOUND ✗") . "\n";

// Manual check of FPDI files
if (is_dir($fpdiDir)) {
    $fpdiMain = $fpdiDir . "/src/Fpdi.php";
    echo "Fpdi.php: " . (file_exists($fpdiMain) ? "EXISTS ✓" : "NOT FOUND ✗") . "\n";
    
    // Try manual load if autoload failed
    if (!class_exists('setasign\\Fpdi\\Fpdi') && file_exists($fpdiMain)) {
        echo "Trying manual include...\n";
        try {
            // FPDI needs its dependencies - check structure
            $files = glob($fpdiDir . "/src/*.php");
            echo "Files in src/: " . count($files) . "\n";
            foreach ($files as $f) echo "  " . basename($f) . "\n";
        } catch (Throwable $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}
echo "\n";

// ===== TCPDF =====
echo "=== TCPDF ===\n";
$tcpdfDir = $laravelRoot . "/vendor/tecnickcom/tcpdf";
echo "tecnickcom/tcpdf dir: " . (is_dir($tcpdfDir) ? "EXISTS ✓" : "NOT FOUND ✗") . "\n";
echo "TCPDF class: " . (class_exists('TCPDF') ? "AVAILABLE ✓" : "NOT FOUND ✗") . "\n";
echo "\n";

// ===== InspectionReportPdfGenerator =====
echo "=== Generator File ===\n";
$genFile = $laravelRoot . "/app/Services/InspectionReportPdfGenerator.php";
echo "File exists: " . (file_exists($genFile) ? "YES ✓" : "NO ✗") . "\n";
if (file_exists($genFile)) {
    echo "File size: " . filesize($genFile) . " bytes\n";
    echo "Last modified: " . date('Y-m-d H:i:s', filemtime($genFile)) . "\n";
    $content = file_get_contents($genFile);
    preg_match('/SECTION_BUILD_PHOTO_THRESHOLD\s*=\s*(\d+)/', $content, $m);
    echo "SECTION_BUILD_PHOTO_THRESHOLD: " . ($m[1] ?? 'NOT FOUND') . "\n";
    echo "prepareImageForPdf (new): " . (str_contains($content, 'prepareImageForPdf') ? 'YES ✓ NEW FILE' : 'NO ✗ OLD FILE') . "\n";
}
echo "\n";

// ===== Storage paths =====
echo "=== Storage ===\n";
$storageDirs = [
    $laravelRoot . "/storage/app/reports",
    $laravelRoot . "/storage/app/reports/tmp",
    $laravelRoot . "/storage/app/reports/sections",
    $laravelRoot . "/storage/app/public/reports",
];
foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    echo basename(dirname($dir)) . "/" . basename($dir) . ": " . (is_writable($dir) ? "WRITABLE ✓" : (is_dir($dir) ? "NOT WRITABLE ✗" : "NOT EXISTS ✗")) . "\n";
}
echo "\n";

// ===== Ghostscript =====
echo "=== Ghostscript ===\n";
$gs = null;
foreach (['/bin/gs', '/usr/bin/gs', '/usr/local/bin/gs', 'gs'] as $bin) {
    if (is_executable($bin)) { $gs = $bin; break; }
    $w = @shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null');
    if ($w && trim($w)) { $gs = trim($w); break; }
}
echo "Ghostscript: " . ($gs ? "FOUND at $gs ✓" : "NOT FOUND ✗") . "\n";
echo "\n";

// ===== GD =====
echo "=== GD ===\n";
echo "GD: " . (extension_loaded('gd') ? "YES ✓" : "NO ✗") . "\n";
if (extension_loaded('gd')) {
    $i = gd_info();
    echo "JPEG: " . ($i['JPEG Support'] ? "YES ✓" : "NO ✗") . "\n";
    echo "WebP: " . (($i['WebP Support'] ?? false) ? "YES ✓" : "NO ✗") . "\n";
}
echo "\n";

echo "=== memory_limit: " . ini_get('memory_limit') . " ===\n";
echo "=== max_execution_time: " . ini_get('max_execution_time') . " ===\n";
echo "\nDelete this file now!\n";
