<?php
require __DIR__ . '/classes/Database.php';

try {
    $pdo = Database::connection();
    $sql = file_get_contents(__DIR__ . '/database/loan_applications_migration.sql');
    $pdo->exec($sql);
    echo "MIGRATION_SUCCESSFUL\n";
} catch (Throwable $e) {
    echo "MIGRATION_FAILED: " . $e->getMessage() . "\n";
}
