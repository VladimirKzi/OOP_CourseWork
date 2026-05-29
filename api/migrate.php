<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Database.php';
use App\Database;
echo '<pre>';
echo "Running migrations...\n";
try {
    Database::get()->exec(file_get_contents(__DIR__ . '/migrations/001_init.sql'));
    echo "✅ Done! Tables created.\n";
} catch (Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
}
echo '</pre>';
