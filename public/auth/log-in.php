<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        header('Location: ../auth/sign-in.php?error=empty');
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && $password === $user['password']) {
            // Đăng nhập thành công
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            // Chuyển hướng theo role
            switch ($user['role']) {
                case 'Admin':
                    header('Location: ../dashboard/admin.php');
                    break;
                case 'Thủ kho':
                    header('Location: ../dashboard/warehouse.php');
                    break;
                case 'Người nhận hàng':
                    header('Location: ../dashboard/receiving.php');
                    break;
                default:
                    header('Location: ../auth/sign-in.php?error=role');
                    break;
            }
            exit;
        } else {
            header('Location: ../auth/sign-in.php?error=invalid');
            exit;
        }
    } catch (PDOException $e) {
        header('Location: ../auth/sign-in.php?error=system');
        exit;
    }
}
?> 