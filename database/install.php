<?php
/**
 * FitFlow Pro — database installer.
 * Run once from CLI:  php database/install.php
 * Or open in a browser on your PHP host (delete after use).
 * Creates the database, all tables and demo seed data from database.sql.
 */
require_once __DIR__ . '/../app/bootstrap.php';

$cfg = require __DIR__ . '/../config/database.php';
$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['charset']),
        $cfg['username'],
        $cfg['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $sql = file_get_contents(__DIR__ . '/database.sql');
    if ($sql === false) {
        throw new RuntimeException('database.sql not found.');
    }
    // Strip comment-only lines, then split on statement terminators.
    $lines = [];
    foreach (explode("\n", $sql) as $line) {
        if (str_starts_with(trim($line), '--')) {
            continue;
        }
        $lines[] = $line;
    }
    $statements = array_filter(array_map('trim', explode(";\n", implode("\n", $lines))));
    $count = 0;
    foreach ($statements as $stmt) {
        if ($stmt === '') {
            continue;
        }
        $pdo->exec($stmt);
        $count++;
    }
    echo "FitFlow Pro database installed successfully ({$count} statements executed).\n";
    echo "Demo logins (password: password):\n";
    echo "  Super Admin : super@fitflow.test\n";
    echo "  Gym Admin   : admin@demo.com\n";
    echo "  Trainer     : trainer@demo.com\n";
    echo "  Member      : member@demo.com\n";
    echo "IMPORTANT: delete this installer file from production servers.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Installation failed: ' . $e->getMessage() . "\n";
}
