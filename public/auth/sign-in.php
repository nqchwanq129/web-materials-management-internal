<?php
$messages = [
    'empty' => 'Vui lòng nhập tài khoản và mật khẩu.',
    'invalid' => 'Tài khoản hoặc mật khẩu chưa đúng. Vui lòng thử lại.',
    'role' => 'Tài khoản chưa được cấp quyền truy cập. Vui lòng liên hệ quản trị viên.',
    'system' => 'Hệ thống đang gặp sự cố. Vui lòng thử lại sau.',
];
$error = $messages[$_GET['error'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f5fa">
    <title>Đăng nhập | Quản lý vật tư VISHIPEL</title>
    <link rel="stylesheet" href="assets/css/auth/sign-in.css">
    <link rel="stylesheet" href="assets/css/shared/icons.css">
    <link rel="stylesheet" href="assets/css/shared/theme.css">
</head>
<body>
<main class="page-shell">
    <section class="welcome-panel" aria-label="Giới thiệu hệ thống">
        <div class="brand"><img src="assets/images/company-logo.png" alt="VISHIPEL"></div>
        <div class="welcome-copy">
            <span class="eyebrow">HỆ THỐNG NỘI BỘ</span>
            <h1>Quản lý vật tư<br><span>gọn gàng, hiệu quả.</span></h1>
            <p>Theo dõi hàng hóa, phiếu nhập xuất và báo cáo trên cùng một hệ thống.</p>
        </div>
        <div class="scene" aria-hidden="true">
            <div class="scene-orbit orbit-one"></div><div class="scene-orbit orbit-two"></div>
            <div class="scene-card card-back"><i></i><i></i><i></i></div>
            <div class="scene-card card-front"><b><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#check"></use></svg></b><i></i><i></i><i></i></div>
            <div class="scene-box box-one"></div><div class="scene-box box-two"></div>
        </div>
        <div class="panel-footer">VISHIPEL · Quản lý vật tư</div>
    </section>
    <section class="form-panel" aria-labelledby="sign-in-title">
        <div class="mobile-brand"><img src="assets/images/company-logo.png" alt="VISHIPEL"></div>
        <div class="form-wrap">
            <div class="form-icon" aria-hidden="true">
                <svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#package"></use></svg>
            </div>
            <p class="form-kicker">CHÀO MỪNG TRỞ LẠI</p>
            <h2 id="sign-in-title">Đăng nhập</h2>
            <p class="form-description">Nhập thông tin tài khoản để tiếp tục công việc.</p>
            <?php if ($error): ?>
                <div class="alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form action="auth/log-in.php" method="post">
                <div class="form-group">
                    <label for="username">Tên đăng nhập</label>
                    <div class="input-wrap">
                        <svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#user"></use></svg>
                        <input type="text" id="username" name="username" placeholder="Nhập tên đăng nhập" autocomplete="username" spellcheck="false" required autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Mật khẩu</label>
                    <div class="input-wrap">
                        <svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#lock"></use></svg>
                        <input type="password" id="password" name="password" placeholder="Nhập mật khẩu" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" aria-label="Hiện mật khẩu" aria-pressed="false" aria-controls="password" title="Hiện mật khẩu">
                            <svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#eye"></use></svg>
                        </button>
                    </div>
                </div>
                <button class="login-btn" type="submit">Đăng nhập <span aria-hidden="true"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#arrow-right"></use></svg></span></button>
            </form>
            <p class="support-note">Bạn gặp khó khăn khi đăng nhập?<br>Liên hệ quản trị viên để được hỗ trợ.</p>
        </div>
        <p class="form-footer">© <?= date('Y') ?> VISHIPEL</p>
    </section>
</main>
<script>
    const toggle = document.querySelector('.password-toggle');
    const password = document.getElementById('password');
    toggle.addEventListener('click', () => {
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(visible));
        toggle.setAttribute('aria-label', visible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
        toggle.title = visible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu';
    });
</script>
</body>
</html>
