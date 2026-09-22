<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'product_id invalid']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT file_path, position, alt_text FROM product_images WHERE product_id = ? ORDER BY position ASC, id ASC');
    $stmt->execute([$productId]);
    $images = $stmt->fetchAll();
    echo json_encode(['success' => true, 'images' => $images]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
