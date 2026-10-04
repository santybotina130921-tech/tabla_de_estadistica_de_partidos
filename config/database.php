<?php
// config/database.php
require_once __DIR__ . '/env.php';

$host     = getenv('SUPABASE_DB_HOST');
$port     = getenv('SUPABASE_DB_PORT') ?: '5432';
$db_name  = getenv('SUPABASE_DB_NAME') ?: 'postgres';
$username = getenv('SUPABASE_DB_USER');
$password = getenv('SUPABASE_DB_PASSWORD');

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db_name;sslmode=require";
    $conexion = new PDO($dsn, $username, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $exception) {
    error_log('Error de conexión a Supabase: ' . $exception->getMessage());
    http_response_code(500);
    die('No fue posible conectar con la base de datos. Intenta más tarde.');
}