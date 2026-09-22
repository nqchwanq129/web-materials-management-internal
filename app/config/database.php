<?php
$envFile = dirname(__DIR__, 2) . '/.env';
if (!is_readable($envFile)) {
    throw new RuntimeException('Missing .env file. Copy .env.example to .env and set database credentials.');
}

$config = parse_ini_file($envFile, false, INI_SCANNER_RAW);
if ($config === false) {
    throw new RuntimeException('Unable to read .env file.');
}

foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $key) {
    if (!array_key_exists($key, $config)) {
        throw new RuntimeException("Missing $key in .env file.");
    }
}

$host = $config['DB_HOST'];
$dbname = $config['DB_NAME'];
$username = $config['DB_USER'];
$password = $config['DB_PASSWORD'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Lỗi kết nối database: " . $e->getMessage());
}
?> 
