<?php
chdir(dirname(__DIR__));
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../auth/sign-in.php');
    exit;
}

require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/csrf_helper.php';

// Xử lý thêm tài khoản mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRFToken(); // Validate CSRF token
    if ($_POST['action'] === 'add') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        $gender = $_POST['gender'];
        $birthday = $_POST['birthday'];
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $role = $_POST['role'];

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, gender, birthday, phone, email, address, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $password, $full_name, $gender, $birthday, $phone, $email, $address, $role]);
            $success_message = "Thêm tài khoản thành công!";
        } catch (PDOException $e) {
            $error_message = "Lỗi: " . $e->getMessage();
        }
    }
    
    // Xử lý sửa tài khoản
    if ($_POST['action'] === 'edit') {
        $id = $_POST['id'];
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        $gender = $_POST['gender'];
        $birthday = $_POST['birthday'];
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $role = $_POST['role'];

        try {
            if (!empty($password)) {
                $stmt = $pdo->prepare("UPDATE users SET username=?, password=?, full_name=?, gender=?, birthday=?, phone=?, email=?, address=?, role=? WHERE id=?");
                $stmt->execute([$username, $password, $full_name, $gender, $birthday, $phone, $email, $address, $role, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=?, gender=?, birthday=?, phone=?, email=?, address=?, role=? WHERE id=?");
                $stmt->execute([$username, $full_name, $gender, $birthday, $phone, $email, $address, $role, $id]);
            }
            $success_message = "Cập nhật tài khoản thành công!";
        } catch (PDOException $e) {
            $error_message = "Lỗi: " . $e->getMessage();
        }
    }
    
    // Xử lý xóa tài khoản
    if ($_POST['action'] === 'delete') {
        $id = $_POST['id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $success_message = "Xóa tài khoản thành công!";
        } catch (PDOException $e) {
            $error_message = "Lỗi: " . $e->getMessage();
        }
    }
}

// Lấy thông tin tài khoản để edit (nếu có)
$edit_user = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $edit_user = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = "Lỗi khi lấy thông tin tài khoản: " . $e->getMessage();
    }
}

// Lấy danh sách tài khoản
try {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY id");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Lỗi khi lấy danh sách tài khoản: " . $e->getMessage();
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý tài khoản - Admin</title>
    <link rel="stylesheet" href="assets/css/shared/layout.css">
    <link rel="stylesheet" href="assets/css/accounts/index.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
    <button class="sidebar-toggle" id="sidebarToggle">☰</button>
    <div class="header">
        <div class="logo">
            <img src="assets/images/company-logo.png" alt="Vishipel Logo">
            <div class="logo-text">
                <h1>PHẦN MỀM QUẢN LÝ KHO VISHIPEL</h1>
                <p>CÔNG TY TNHH MTV THÔNG TIN ĐIỆN TỬ HÀNG HẢI VIỆT NAM</p>
            </div>
        </div>
        <div class="user-info">
            <span class="greeting">Xin chào <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="auth/log-out.php" class="logout-btn">Đăng xuất</a>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <ul class="menu">
                <li><a href="reports/statistics.php">Số liệu thống kê</a></li>
                <?php if ($_SESSION['role'] === 'Admin'): ?>
                <li><a href="accounts/index.php">Quản lý tài khoản</a></li>
                <?php endif; ?>
                <li>Nhập hàng hóa
                    <ul>
                        <li><a href="imports/create.php">Nhập hóa đơn</a></li>
                        <li><a href="imports/index.php">DS phiếu nhập kho</a></li>
                    </ul>
                </li>
                <li>Xuất hàng hóa
                    <ul>
                        <li><a href="exports/create.php">Xuất hóa đơn</a></li>
                        <li><a href="exports/index.php">DS phiếu xuất kho</a></li>
                    </ul>
                </li>
                <li>Danh Sách Hàng Hóa
                    <ul>
                        <li><a href="products/index.php">Tất Cả Hàng Hóa</a></li>
                        <li><a href="products/index.php?type=cong-cu">Công Cụ Dụng Cụ</a></li>
                        <li><a href="products/index.php?type=vat-tu">Vật Tư</a></li>
                        <li><a href="products/index.php?type=tai-san">Tài Sản Cố Định</a></li>
                        <li><a href="products/index.php?type=phu-tung">Phụ Tùng Thay Thế</a></li>
                        <li><a href="products/index.php?type=khac">Khác</a></li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="main-content">
            <h2>Quản lý tài khoản</h2>
            
            <?php if (isset($success_message)): ?>
                <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Form thêm tài khoản mới -->
            <div class="form-container">
                <h3>Thêm tài khoản mới</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <?php echo csrfTokenField(); ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Tên đăng nhập:</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Mật khẩu:</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="full_name">Họ và tên:</label>
                            <input type="text" id="full_name" name="full_name" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="gender">Giới tính:</label>
                            <select id="gender" name="gender" required>
                                <option value="">Chọn giới tính</option>
                                <option value="Nam">Nam</option>
                                <option value="Nữ">Nữ</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="birthday">Ngày sinh:</label>
                            <input type="text" id="birthday" name="birthday" class="date-picker" placeholder="dd/mm/yyyy">
                        </div>
                        <div class="form-group">
                            <label for="phone">Số điện thoại:</label>
                            <input type="text" id="phone" name="phone">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email">
                        </div>
                        <div class="form-group">
                            <label for="address">Địa chỉ:</label>
                            <input type="text" id="address" name="address">
                        </div>
                        <div class="form-group">
                            <label for="role">Vai trò:</label>
                            <select id="role" name="role" required>
                                <option value="">Chọn vai trò</option>
                                <option value="Admin">Admin</option>
                                <option value="Thủ kho">Thủ kho</option>
                                <option value="Người nhận hàng">Người nhận hàng</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Thêm tài khoản</button>
                </form>
            </div>

            <!-- Bảng danh sách tài khoản -->
            <div class="table-container">
                <h3>Danh sách tài khoản</h3>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên đăng nhập</th>
                            <th>Họ và tên</th>
                            <th>Giới tính</th>
                            <th>Ngày sinh</th>
                            <th>Số điện thoại</th>
                            <th>Email</th>
                            <th>Địa chỉ</th>
                            <th>Vai trò</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['gender']); ?></td>
                            <td><?php echo $user['birthday'] ? htmlspecialchars($user['birthday']) : '-'; ?></td>
                            <td><?php echo htmlspecialchars($user['phone'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['email'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['address'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td class="action-buttons">
                                <a href="accounts/index.php?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm">Sửa</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Bạn có chắc muốn xóa tài khoản này?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                    <?php echo csrfTokenField(); ?>
                                    <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($edit_user): ?>
    <!-- Form sửa tài khoản -->
    <div class="section">
        <div class="section-header">
            <h3>Sửa tài khoản</h3>
            <a href="accounts/index.php" class="btn btn-secondary">Hủy</a>
        </div>
        <div class="section-content">
            <form method="POST" class="user-form">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo $edit_user['id']; ?>">
                <?php echo csrfTokenField(); ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_username">Tên đăng nhập:</label>
                        <input type="text" id="edit_username" name="username" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_password">Mật khẩu mới (để trống nếu không đổi):</label>
                        <input type="password" id="edit_password" name="password">
                    </div>
                    <div class="form-group">
                        <label for="edit_full_name">Họ và tên:</label>
                        <input type="text" id="edit_full_name" name="full_name" value="<?php echo htmlspecialchars($edit_user['full_name']); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_gender">Giới tính:</label>
                        <select id="edit_gender" name="gender" required>
                            <option value="Nam" <?php echo $edit_user['gender'] === 'Nam' ? 'selected' : ''; ?>>Nam</option>
                            <option value="Nữ" <?php echo $edit_user['gender'] === 'Nữ' ? 'selected' : ''; ?>>Nữ</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_birthday">Ngày sinh:</label>
                        <input type="text" id="edit_birthday" name="birthday" class="date-picker" placeholder="dd/mm/yyyy" value="<?php echo $edit_user['birthday'] ?: ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="edit_phone">Số điện thoại:</label>
                        <input type="text" id="edit_phone" name="phone" value="<?php echo htmlspecialchars($edit_user['phone'] ?: ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_email">Email:</label>
                        <input type="email" id="edit_email" name="email" value="<?php echo htmlspecialchars($edit_user['email'] ?: ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="edit_address">Địa chỉ:</label>
                        <input type="text" id="edit_address" name="address" value="<?php echo htmlspecialchars($edit_user['address'] ?: ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="edit_role">Vai trò:</label>
                        <select id="edit_role" name="role" required>
                            <option value="Admin" <?php echo $edit_user['role'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="Thủ kho" <?php echo $edit_user['role'] === 'Thủ kho' ? 'selected' : ''; ?>>Thủ kho</option>
                            <option value="Người nhận hàng" <?php echo $edit_user['role'] === 'Người nhận hàng' ? 'selected' : ''; ?>>Người nhận hàng</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-success">Cập nhật</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/vn.js"></script>
    <script>
      (function(){
        if (window.flatpickr) {
          flatpickr('#birthday', {
            locale: flatpickr.l10ns.vn,
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            allowInput: true
          });
          flatpickr('#edit_birthday', {
            locale: flatpickr.l10ns.vn,
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            allowInput: true
          });
        }
      })();
    </script>
<script src="assets/js/shared/sidebar-toggle.js" defer></script>
</body>
</html>