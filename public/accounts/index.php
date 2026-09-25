<?php
chdir(dirname(__DIR__));
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header('Location: ../auth/sign-in.php');
    exit;
}
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/csrf_helper.php';

function accountEsc($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
function validBirthday(string $value): bool {
    if ($value === '') return true;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year) && $value <= date('Y-m-d');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    $action = $_POST['action'] ?? '';
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $back = $action === 'edit' && $id ? 'accounts/index.php?action=edit&id=' . $id : 'accounts/index.php';
    try {
        if ($action === 'delete') {
            if (!$id) throw new RuntimeException('Tài khoản không hợp lệ.');
            if ($id === (int) $_SESSION['user_id']) throw new RuntimeException('Bạn không thể xóa tài khoản đang đăng nhập.');
            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $current = $stmt->fetch();
            if (!$current) throw new RuntimeException('Không tìm thấy tài khoản.');
            if ($current['role'] === 'Admin' && (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Admin'")->fetchColumn() <= 1) {
                throw new RuntimeException('Cần giữ ít nhất một tài khoản Admin.');
            }
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['account_notice'] = ['success', 'Đã xóa tài khoản.'];
        } elseif ($action === 'add' || $action === 'edit') {
            if ($action === 'edit' && !$id) throw new RuntimeException('Tài khoản không hợp lệ.');
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $gender = (string) ($_POST['gender'] ?? '');
            $birthday = (string) ($_POST['birthday'] ?? '');
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $address = trim((string) ($_POST['address'] ?? ''));
            $role = (string) ($_POST['role'] ?? '');
            if ($username === '' || $fullName === '' || ($action === 'add' && $password === '')) throw new RuntimeException('Vui lòng điền tên đăng nhập, họ tên và mật khẩu khi tạo mới.');
            if (strlen($username) > 50 || mb_strlen($fullName) > 100 || strlen($phone) > 15 || strlen($email) > 100 || mb_strlen($address) > 255) throw new RuntimeException('Một số thông tin vượt quá độ dài cho phép.');
            if (!in_array($gender, ['Nam', 'Nữ'], true) || !in_array($role, ['Admin', 'Thủ kho', 'Người nhận hàng'], true)) throw new RuntimeException('Giới tính hoặc vai trò không hợp lệ.');
            if (!validBirthday($birthday)) throw new RuntimeException('Ngày sinh không hợp lệ.');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Email không hợp lệ.');
            if ($password !== '' && strlen($password) < 8) throw new RuntimeException('Mật khẩu cần ít nhất 8 ký tự.');
            if ($action === 'edit') {
                $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
                $stmt->execute([$id]);
                $current = $stmt->fetch();
                if (!$current) throw new RuntimeException('Không tìm thấy tài khoản.');
                if ($id === (int) $_SESSION['user_id'] && $role !== 'Admin') throw new RuntimeException('Bạn không thể gỡ quyền Admin của tài khoản đang đăng nhập.');
                if ($current['role'] === 'Admin' && $role !== 'Admin' && (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Admin'")->fetchColumn() <= 1) throw new RuntimeException('Cần giữ ít nhất một tài khoản Admin.');
            }
            $fields = [$username, $fullName, $gender, $birthday ?: null, $phone ?: null, $email ?: null, $address ?: null, $role];
            if ($action === 'add') {
                $stmt = $pdo->prepare('INSERT INTO users (username, full_name, gender, birthday, phone, email, address, role, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([...$fields, password_hash($password, PASSWORD_DEFAULT)]);
                $_SESSION['account_notice'] = ['success', 'Đã thêm tài khoản.'];
            } else {
                $sql = 'UPDATE users SET username=?, full_name=?, gender=?, birthday=?, phone=?, email=?, address=?, role=?';
                if ($password !== '') {
                    $sql .= ', password=?';
                    $fields[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $stmt = $pdo->prepare($sql . ' WHERE id=?');
                $stmt->execute([...$fields, $id]);
                if ($id === (int) $_SESSION['user_id']) {
                    $_SESSION['username'] = $username;
                    $_SESSION['full_name'] = $fullName;
                }
                $_SESSION['account_notice'] = ['success', 'Đã cập nhật tài khoản.'];
            }
            $back = 'accounts/index.php';
        } else {
            throw new RuntimeException('Thao tác không hợp lệ.');
        }
    } catch (PDOException $e) {
        $_SESSION['account_notice'] = ['error', $e->getCode() === '23000' ? 'Tên đăng nhập đã tồn tại hoặc tài khoản đang được sử dụng.' : 'Không thể lưu thay đổi. Vui lòng thử lại.'];
        if ($action === 'add' || $action === 'edit') {
            $_SESSION['account_old_input'] = array_intersect_key($_POST, array_flip(['username', 'full_name', 'gender', 'birthday', 'phone', 'email', 'address', 'role']));
            if ($action === 'add') $back = 'accounts/index.php?add=1';
        }
    } catch (RuntimeException $e) {
        $_SESSION['account_notice'] = ['error', $e->getMessage()];
        if ($action === 'add' || $action === 'edit') {
            $_SESSION['account_old_input'] = array_intersect_key($_POST, array_flip(['username', 'full_name', 'gender', 'birthday', 'phone', 'email', 'address', 'role']));
            if ($action === 'add') $back = 'accounts/index.php?add=1';
        }
    }
    header('Location: ../' . $back);
    exit;
}

$notice = $_SESSION['account_notice'] ?? null;
unset($_SESSION['account_notice']);
$oldInput = $_SESSION['account_old_input'] ?? [];
unset($_SESSION['account_old_input']);
$editUser = null;
$editId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (($_GET['action'] ?? '') === 'edit' && $editId) {
    $stmt = $pdo->prepare('SELECT id, username, full_name, gender, birthday, phone, email, address, role FROM users WHERE id = ?');
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
    if (!$editUser) $notice = ['error', 'Không tìm thấy tài khoản cần sửa.'];
}
$formUser = array_merge($editUser ?: [], $oldInput);
$users = $pdo->query('SELECT id, username, full_name, gender, birthday, phone, email, address, role FROM users ORDER BY id DESC')->fetchAll();
$roleCounts = array_count_values(array_column($users, 'role'));
$name = accountEsc($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý tài khoản | VISHIPEL</title>
    <link rel="stylesheet" href="assets/css/dashboard/admin.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.6/css/dataTables.dataTables.min.css">
    <link rel="stylesheet" href="assets/css/accounts/index.css">
    <link rel="stylesheet" href="assets/css/shared/icons.css">
    <link rel="stylesheet" href="assets/css/shared/theme.css">
</head>
<body>
<a class="skip-link" href="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? basename(__DIR__) . '/' . basename(__FILE__), ENT_QUOTES, 'UTF-8') ?>#main-content">Đến nội dung chính</a>
<div class="dashboard">
    <aside class="sidebar" id="dashboard-sidebar">
        <a class="brand" href="dashboard/admin.php"><img src="assets/images/company-logo.png" alt="VISHIPEL" width="500" height="500"></a>
        <div class="nav-label">TỔNG QUAN</div>
        <nav class="nav" aria-label="Điều hướng chính">
            <a href="dashboard/admin.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#dashboard"></use></svg></span>Bảng điều khiển</a>
            <a href="reports/statistics.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#chart"></use></svg></span>Thống kê</a>
            <div class="nav-label">QUẢN LÝ KHO</div>
            <a href="products/index.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#package"></use></svg></span>Hàng hóa</a>
            <a href="imports/index.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#import"></use></svg></span>Phiếu nhập kho</a>
            <a href="imports/create.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#file-plus"></use></svg></span>Tạo phiếu nhập</a>
            <a href="exports/index.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#export"></use></svg></span>Phiếu xuất kho</a>
            <a href="exports/create.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#file-plus"></use></svg></span>Tạo phiếu xuất</a>
            <div class="nav-label">HỆ THỐNG</div>
            <a class="active" href="accounts/index.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#users"></use></svg></span>Tài khoản</a>
        </nav>
        <div class="sidebar-user"><span class="avatar">A</span><span><strong><?= $name ?></strong><small>Quản trị viên</small></span><a href="auth/log-out.php" title="Đăng xuất" aria-label="Đăng xuất"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#logout"></use></svg></a></div>
    </aside>
    <div class="content-shell">
        <header class="topbar"><div class="topbar-left"><button class="menu-toggle" type="button" aria-controls="dashboard-sidebar" aria-expanded="false" aria-label="Mở menu"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#menu"></use></svg></button><div><span class="breadcrumb">Hệ thống / Tài khoản</span><h1>Quản lý tài khoản</h1></div></div><div class="topbar-actions"><span><?= date('d/m/Y') ?></span><span class="top-avatar">A</span></div></header>
        <main class="main-content" id="main-content" tabindex="-1">
            <div class="page-intro"><div><h2>Quản lý tài khoản</h2><p>Thêm, cập nhật và quản lý quyền truy cập hệ thống</p></div><button type="button" class="add-account-btn" id="open-account-dialog"><span aria-hidden="true"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#plus"></use></svg></span> Thêm tài khoản</button></div>
            <?php if ($notice): ?><div class="account-notice <?= $notice[0] === 'success' ? 'success' : 'error' ?>" id="account-page-notice" role="alert"><?= accountEsc($notice[1]) ?></div><?php endif; ?>
            <section class="metrics account-metrics" aria-label="Tổng quan tài khoản">
                <article class="metric"><div class="metric-top"><span>Tổng tài khoản</span><i class="blue"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#users"></use></svg></i></div><strong><?= count($users) ?></strong><small>Toàn hệ thống</small></article>
                <article class="metric"><div class="metric-top"><span>Admin</span><i class="red"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#shield"></use></svg></i></div><strong><?= $roleCounts['Admin'] ?? 0 ?></strong><small>Quản trị viên</small></article>
                <article class="metric"><div class="metric-top"><span>Thủ kho</span><i class="green"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#warehouse"></use></svg></i></div><strong><?= $roleCounts['Thủ kho'] ?? 0 ?></strong><small>Quản lý kho</small></article>
                <article class="metric"><div class="metric-top"><span>Người nhận hàng</span><i class="orange"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#user"></use></svg></i></div><strong><?= $roleCounts['Người nhận hàng'] ?? 0 ?></strong><small>Tiếp nhận hàng hóa</small></article>
            </section>
            <dialog class="account-dialog" id="account-dialog" aria-labelledby="account-dialog-title">
            <section class="account-form-panel">
                <div class="panel-heading"><span class="dialog-icon" aria-hidden="true"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#users"></use></svg></span><div class="dialog-heading-copy"><span class="dialog-kicker">QUẢN LÝ NGƯỜI DÙNG</span><h3 id="account-dialog-title"><?= $editUser ? 'Cập nhật tài khoản' : 'Thêm tài khoản mới' ?></h3><p><?= $editUser ? 'Chỉnh sửa thông tin và quyền truy cập' : 'Tạo tài khoản để cấp quyền truy cập hệ thống' ?></p></div><button type="button" class="dialog-close" id="close-account-dialog" aria-label="Đóng cửa sổ"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#close"></use></svg></button></div>
                <?php if ($notice && $notice[0] === 'error'): ?><div class="account-notice dialog-notice" role="alert"><?= accountEsc($notice[1]) ?></div><?php endif; ?>
                <form method="post" action="accounts/index.php" class="account-form">
                    <?= csrfTokenField() ?>
                    <input type="hidden" name="action" value="<?= $editUser ? 'edit' : 'add' ?>">
                    <?php if ($editUser): ?><input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>"><?php endif; ?>
                    <div class="dialog-section"><div class="dialog-section-title"><strong>Thông tin đăng nhập</strong><span>Các trường có dấu * là bắt buộc</span></div><div class="form-grid">
                        <label>Tên đăng nhập <span>*</span><input type="text" name="username" value="<?= accountEsc($formUser['username'] ?? '') ?>" maxlength="50" autocomplete="username" placeholder="Ví dụ: nguyenvana" required></label>
                        <label><?= $editUser ? 'Mật khẩu mới' : 'Mật khẩu' ?> <?= $editUser ? '' : '<span>*</span>' ?><input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="<?= $editUser ? 'Để trống nếu không đổi' : 'Tối thiểu 8 ký tự' ?>" <?= $editUser ? '' : 'required' ?>><small><?= $editUser ? 'Chỉ nhập khi muốn đổi mật khẩu' : 'Dùng ít nhất 8 ký tự' ?></small></label>
                        <label>Vai trò <span>*</span><select name="role" required><option value="">Chọn vai trò</option><?php foreach (['Admin', 'Thủ kho', 'Người nhận hàng'] as $role): ?><option value="<?= accountEsc($role) ?>" <?= ($formUser['role'] ?? '') === $role ? 'selected' : '' ?>><?= accountEsc($role) ?></option><?php endforeach; ?></select></label>
                    </div></div>
                    <div class="dialog-section"><div class="dialog-section-title"><strong>Thông tin cá nhân</strong><span>Dùng để nhận diện và liên hệ người dùng</span></div><div class="form-grid">
                        <label>Họ và tên <span>*</span><input type="text" name="full_name" value="<?= accountEsc($formUser['full_name'] ?? '') ?>" maxlength="100" placeholder="Họ và tên đầy đủ" required></label>
                        <label>Giới tính <span>*</span><select name="gender" required><option value="">Chọn giới tính</option><?php foreach (['Nam', 'Nữ'] as $gender): ?><option value="<?= accountEsc($gender) ?>" <?= ($formUser['gender'] ?? '') === $gender ? 'selected' : '' ?>><?= accountEsc($gender) ?></option><?php endforeach; ?></select></label>
                        <label>Ngày sinh<input type="date" name="birthday" max="<?= date('Y-m-d') ?>" value="<?= accountEsc($formUser['birthday'] ?? '') ?>"></label>
                        <label>Số điện thoại<input type="tel" name="phone" value="<?= accountEsc($formUser['phone'] ?? '') ?>" maxlength="15" placeholder="Số điện thoại"></label>
                        <label>Email<input type="email" name="email" value="<?= accountEsc($formUser['email'] ?? '') ?>" maxlength="100" placeholder="ten@congty.vn"></label>
                        <label class="wide">Địa chỉ<input type="text" name="address" value="<?= accountEsc($formUser['address'] ?? '') ?>" maxlength="255" placeholder="Địa chỉ liên hệ"></label>
                    </div></div>
                    <div class="form-actions"><button type="button" class="cancel-btn" id="cancel-account-dialog">Hủy</button><button type="submit"><?= $editUser ? 'Lưu thay đổi' : 'Thêm tài khoản' ?></button></div>
                </form>
            </section>
            </dialog>
            <section class="panel account-table-panel">
                <div class="panel-heading"><div><h3>Danh sách tài khoản</h3><p><?= count($users) ?> tài khoản trong hệ thống</p></div></div>
                <div class="table-scroll"><table id="accounts-table"><thead><tr><th>Tài khoản</th><th>Họ và tên</th><th>Vai trò</th><th>Liên hệ</th><th>Ngày sinh</th><th>Thao tác</th></tr></thead><tbody>
                    <?php foreach ($users as $user): ?><tr>
                        <td><span class="account-avatar"><?= accountEsc(mb_substr($user['full_name'], 0, 1)) ?></span><strong><?= accountEsc($user['username']) ?></strong><?php if ((int) $user['id'] === (int) $_SESSION['user_id']): ?><small class="self-label">Bạn</small><?php endif; ?></td>
                        <td><?= accountEsc($user['full_name']) ?></td>
                        <td><span class="role-tag <?= $user['role'] === 'Admin' ? 'admin' : ($user['role'] === 'Thủ kho' ? 'warehouse' : 'receiving') ?>"><?= accountEsc($user['role']) ?></span></td>
                        <td><span class="contact"><?= accountEsc($user['email'] ?: ($user['phone'] ?: '—')) ?></span></td>
                        <td><?= $user['birthday'] ? accountEsc(date('d/m/Y', strtotime($user['birthday']))) : '—' ?></td>
                        <td><div class="row-actions"><a href="accounts/index.php?action=edit&id=<?= (int) $user['id'] ?>">Sửa</a><?php if ((int) $user['id'] !== (int) $_SESSION['user_id']): ?><form method="post" action="accounts/index.php" class="delete-account-form"><?= csrfTokenField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><button type="submit">Xóa</button></form><?php endif; ?></div></td>
                    </tr><?php endforeach; ?>
                </tbody></table></div>
            </section>
        </main>
    </div>
</div>
<script src="https://cdn.datatables.net/2.3.6/js/dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
const menu = document.querySelector('.menu-toggle');
menu.addEventListener('click', () => {
    const open = document.body.classList.toggle('menu-open');
    menu.setAttribute('aria-expanded', String(open));
    menu.setAttribute('aria-label', open ? 'Đóng menu' : 'Mở menu');
});
const accountDialog = document.getElementById('account-dialog');
const openAccountDialog = document.getElementById('open-account-dialog');
const closeAccountDialog = () => {
    accountDialog.close();
    if (new URLSearchParams(location.search).get('action') === 'edit') {
        history.replaceState(null, '', 'accounts/index.php');
    }
};
openAccountDialog.addEventListener('click', () => {
    if (<?= $editUser ? 'true' : 'false' ?>) {
        location.href = 'accounts/index.php';
        return;
    }
    if (!accountDialog.open) accountDialog.showModal();
});
document.getElementById('close-account-dialog').addEventListener('click', closeAccountDialog);
document.getElementById('cancel-account-dialog').addEventListener('click', closeAccountDialog);
accountDialog.addEventListener('click', (event) => {
    const bounds = accountDialog.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) closeAccountDialog();
});
accountDialog.addEventListener('close', () => {
    if (new URLSearchParams(location.search).get('action') === 'edit') {
        history.replaceState(null, '', 'accounts/index.php');
    }
});
<?php if ($editUser || isset($_GET['add'])): ?>accountDialog.showModal();<?php endif; ?>
if (window.DataTable) {
    new DataTable('#accounts-table', {
        pageLength: 10,
        order: [],
        columnDefs: [{targets: 5, orderable: false, searchable: false}],
        language: {
            search: 'Tìm tài khoản:',
            lengthMenu: 'Hiển thị _MENU_ tài khoản',
            info: 'Hiển thị _START_–_END_ trong _TOTAL_ tài khoản',
            infoEmpty: 'Chưa có tài khoản',
            zeroRecords: 'Không tìm thấy tài khoản phù hợp',
            emptyTable: 'Chưa có tài khoản nào',
            paginate: {first: 'Đầu', last: 'Cuối', next: 'Tiếp', previous: 'Trước'}
        }
    });
}
document.addEventListener('submit', async (event) => {
    if (!event.target.matches('.delete-account-form')) return;
    event.preventDefault();
    const form = event.target;
    if (window.Swal) {
        const result = await Swal.fire({
            title: 'Xóa tài khoản?',
            text: 'Tài khoản này sẽ không thể đăng nhập sau khi xóa.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Xóa tài khoản',
            cancelButtonText: 'Hủy',
            confirmButtonColor: '#c3525d'
        });
        if (result.isConfirmed) form.submit();
    } else if (confirm('Bạn có chắc muốn xóa tài khoản này?')) {
        form.submit();
    }
});
<?php if ($notice): ?>
if (window.Swal) {
    <?php if ($notice[0] === 'success'): ?>
    Swal.fire({toast: true, position: 'top-end', icon: 'success', title: <?= json_encode($notice[1], JSON_UNESCAPED_UNICODE) ?>, showConfirmButton: false, timer: 3000, timerProgressBar: true});
    document.getElementById('account-page-notice')?.remove();
    <?php elseif (!$editUser && !isset($_GET['add'])): ?>
    Swal.fire({icon: 'error', title: 'Không thể hoàn tất', text: <?= json_encode($notice[1], JSON_UNESCAPED_UNICODE) ?>, confirmButtonText: 'Đã hiểu'});
    document.getElementById('account-page-notice')?.remove();
    <?php endif; ?>
}
<?php endif; ?>
</script>
<script src="assets/js/shared/theme.js" defer></script>
</body>
</html>
