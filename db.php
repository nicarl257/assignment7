<?php
declare(strict_types=1);

$databaseHost = getenv('DB_HOST') ?: '127.0.0.1';
$databaseName = getenv('DB_NAME') ?: 'student_db';
$databaseUser = getenv('DB_USER') ?: 'root';
$databasePassword = getenv('DB_PASSWORD') ?: '';

$dsn = "mysql:host={$databaseHost};dbname={$databaseName};charset=utf8mb4";

return new PDO($dsn, $databaseUser, $databasePassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
