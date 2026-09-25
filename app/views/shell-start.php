<?php
// Shared navigation for pages migrated from the original header layout.
$shellEscape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$shellRole = $_SESSION['role'] ?? '';
$shellName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? '';
$shellHome = $shellRole === 'Admin' ? 'dashboard/admin.php' : ($shellRole === 'Thủ kho' ? 'dashboard/warehouse.php' : 'dashboard/receiving.php');
$shellLinks = $shellRole === 'Người nhận hàng' ? [
    ['dashboard/receiving.php', 'Hàng đã nhận', 'package'],
] : [
    [$shellHome, 'Bảng điều khiển', 'dashboard'],
    ['reports/statistics.php', 'Thống kê', 'chart'],
    ['products/index.php', 'Hàng hóa', 'package'],
    ['imports/index.php', 'Phiếu nhập kho', 'import'],
    ['imports/create.php', 'Tạo phiếu nhập', 'file-plus'],
    ['exports/index.php', 'Phiếu xuất kho', 'export'],
    ['exports/create.php', 'Tạo phiếu xuất', 'file-plus'],
];
if ($shellRole === 'Admin') $shellLinks[] = ['accounts/index.php', 'Tài khoản', 'users'];
?>
<div class="dashboard">
    <aside class="sidebar" id="dashboard-sidebar">
        <a class="brand" href="<?= $shellHome ?>"><img src="assets/images/company-logo.png" alt="VISHIPEL" width="500" height="500"></a>
        <div class="nav-label">QUẢN LÝ VẬT TƯ</div>
        <nav class="nav" aria-label="Điều hướng chính">
            <?php foreach ($shellLinks as [$shellUrl, $shellLabel, $shellIcon]): ?>
            <a href="<?= $shellUrl ?>" <?= $shellActive === $shellUrl ? 'class="active" aria-current="page"' : '' ?>><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#<?= $shellIcon ?>"></use></svg></span><?= $shellLabel ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-user"><span class="avatar" aria-hidden="true"><?= $shellEscape(mb_substr($shellName, 0, 1)) ?></span><span><strong><?= $shellEscape($shellName) ?></strong><small><?= $shellEscape($shellRole) ?></small></span><a href="auth/log-out.php" aria-label="Đăng xuất" title="Đăng xuất"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#logout"></use></svg></a></div>
    </aside>
    <div class="content-shell">
        <header class="topbar"><div class="topbar-left"><button class="menu-toggle" type="button" aria-controls="dashboard-sidebar" aria-expanded="false" aria-label="Mở menu"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#menu"></use></svg></button><div><span class="breadcrumb">Quản lý vật tư / <?= $shellEscape($shellTitle) ?></span><h1><?= $shellEscape($shellTitle) ?></h1></div></div><div class="topbar-actions"><span><?= date('d/m/Y') ?></span><span class="top-avatar" aria-hidden="true"><?= $shellEscape(mb_substr($shellName, 0, 1)) ?></span></div></header>
        <div class="main-content" id="main-content" role="main" tabindex="-1">
