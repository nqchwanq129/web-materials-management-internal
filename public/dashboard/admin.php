<?php
chdir(dirname(__DIR__));
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header('Location: ../auth/sign-in.php');
    exit;
}
$name = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tổng quan | Quản lý vật tư VISHIPEL</title>
    <link rel="stylesheet" href="assets/css/dashboard/admin.css">
</head>
<body>
<div class="dashboard">
    <aside class="sidebar" id="dashboard-sidebar">
        <a class="brand" href="dashboard/admin.php"><span class="brand-mark">V</span><span>VISHIPEL</span></a>
        <div class="nav-label">TỔNG QUAN</div>
        <nav class="nav" aria-label="Điều hướng chính">
            <a class="active" href="dashboard/admin.php"><span>▦</span>Bảng điều khiển</a>
            <a href="reports/statistics.php"><span>▥</span>Thống kê</a>
            <div class="nav-label">QUẢN LÝ KHO</div>
            <a href="products/index.php"><span>◫</span>Hàng hóa</a>
            <a href="imports/index.php"><span>↙</span>Phiếu nhập kho</a>
            <a href="imports/create.php"><span>＋</span>Tạo phiếu nhập</a>
            <a href="exports/index.php"><span>↗</span>Phiếu xuất kho</a>
            <a href="exports/create.php"><span>＋</span>Tạo phiếu xuất</a>
            <div class="nav-label">HỆ THỐNG</div>
            <a href="accounts/index.php"><span>♙</span>Tài khoản</a>
        </nav>
        <div class="sidebar-user"><span class="avatar">A</span><span><strong><?= $name ?></strong><small>Quản trị viên</small></span><a href="auth/log-out.php" title="Đăng xuất" aria-label="Đăng xuất">⇥</a></div>
    </aside>
    <div class="content-shell">
        <header class="topbar">
            <div class="topbar-left"><button class="menu-toggle" type="button" aria-controls="dashboard-sidebar" aria-expanded="false" aria-label="Mở menu">☰</button><div><span class="breadcrumb">Tổng quan / Bảng điều khiển</span><h1>Xin chào, <?= $name ?> 👋</h1></div></div>
            <div class="topbar-actions"><span><?= date('d/m/Y') ?></span><span class="top-avatar">A</span></div>
        </header>
        <main class="main-content">
            <div class="page-intro"><div><h2>Bảng điều khiển</h2><p>Tổng quan hoạt động quản lý vật tư</p></div><span class="demo-badge">Dữ liệu minh họa</span></div>
            <section class="metrics" aria-label="Các chỉ số tổng quan">
                <article class="metric"><div class="metric-top"><span>Tổng hàng hóa</span><i class="blue">◫</i></div><strong>1.284</strong><small>Danh mục đang quản lý</small></article>
                <article class="metric"><div class="metric-top"><span>Còn trong kho</span><i class="green">✓</i></div><strong>1.102</strong><small>Hàng hóa có tồn kho</small></article>
                <article class="metric"><div class="metric-top"><span>Sắp hết hàng</span><i class="orange">!</i></div><strong>146</strong><small>Cần theo dõi thêm</small></article>
                <article class="metric"><div class="metric-top"><span>Hết hàng</span><i class="red">×</i></div><strong>36</strong><small>Cần bổ sung</small></article>
            </section>
            <div class="visual-grid">
                <section class="panel activity-panel">
                    <div class="panel-heading"><div><h3>Hoạt động nhập xuất</h3><p>Minh họa xu hướng trong 7 tháng gần đây</p></div><div class="legend"><span><i class="in"></i>Nhập</span><span><i class="out"></i>Xuất</span></div></div>
                    <div class="chart" aria-label="Biểu đồ minh họa nhập xuất">
                        <div class="bar-group"><div><i style="height:54%"></i><i style="height:38%"></i></div><span>T1</span></div>
                        <div class="bar-group"><div><i style="height:72%"></i><i style="height:52%"></i></div><span>T2</span></div>
                        <div class="bar-group"><div><i style="height:61%"></i><i style="height:44%"></i></div><span>T3</span></div>
                        <div class="bar-group"><div><i style="height:84%"></i><i style="height:63%"></i></div><span>T4</span></div>
                        <div class="bar-group"><div><i style="height:68%"></i><i style="height:48%"></i></div><span>T5</span></div>
                        <div class="bar-group"><div><i style="height:93%"></i><i style="height:74%"></i></div><span>T6</span></div>
                        <div class="bar-group"><div><i style="height:77%"></i><i style="height:57%"></i></div><span>T7</span></div>
                    </div>
                </section>
                <section class="panel status-panel">
                    <div class="panel-heading"><div><h3>Tình trạng tồn kho</h3><p>Phân bố hàng hóa hiện tại</p></div></div>
                    <div class="donut" aria-hidden="true"><span>1.284<small>hàng hóa</small></span></div>
                    <div class="status-list"><div><span><i class="green-dot"></i>Còn hàng</span><strong>1.102</strong></div><div><span><i class="orange-dot"></i>Sắp hết</span><strong>146</strong></div><div><span><i class="red-dot"></i>Hết hàng</span><strong>36</strong></div></div>
                </section>
            </div>
            <section class="panel inventory-panel">
                <div class="panel-heading"><div><h3>Danh sách hàng hóa</h3><p>Mẫu bố cục bảng hàng hóa</p></div><a class="view-all" href="products/index.php">Xem tất cả →</a></div>
                <div class="table-scroll"><table><thead><tr><th>Tên hàng hóa</th><th>Mã hàng</th><th>Loại</th><th>Vị trí</th><th>Tồn kho</th><th>Trạng thái</th></tr></thead><tbody>
                    <tr><td><span class="product-icon">◫</span>Thiết bị thông tin</td><td>VT-001</td><td>Vật tư</td><td>Kho A</td><td>245</td><td><span class="tag available">Còn hàng</span></td></tr>
                    <tr><td><span class="product-icon">◈</span>Phụ tùng thay thế</td><td>PT-002</td><td>Phụ tùng</td><td>Kho B</td><td>18</td><td><span class="tag low">Sắp hết</span></td></tr>
                    <tr><td><span class="product-icon">▣</span>Công cụ kỹ thuật</td><td>CC-003</td><td>Công cụ</td><td>Kho A</td><td>86</td><td><span class="tag available">Còn hàng</span></td></tr>
                    <tr><td><span class="product-icon">◇</span>Linh kiện điện tử</td><td>LK-004</td><td>Vật tư</td><td>Kho C</td><td>0</td><td><span class="tag empty">Hết hàng</span></td></tr>
                </tbody></table></div>
            </section>
        </main>
    </div>
</div>
<script>
const menu = document.querySelector('.menu-toggle');
menu.addEventListener('click', () => {
    const open = document.body.classList.toggle('menu-open');
    menu.setAttribute('aria-expanded', String(open));
    menu.setAttribute('aria-label', open ? 'Đóng menu' : 'Mở menu');
});
</script>
</body>
</html>
