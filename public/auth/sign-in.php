<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Phần mềm Quản Lý Kho</title>
    <link rel="stylesheet" href="assets/css/auth/sign-in.css">
    <style>
        .password-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 18px;
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: #333;
        }
        
        .password-input {
            padding-right: 45px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1 class="login-title">Phần mềm Quản Lý Kho</h1>
        
        <form action="auth/log-in.php" method="POST">
            <div class="form-group">
                <label for="username">Tài khoản:</label>
                <input type="text" id="username" name="username" placeholder="Tên đăng nhập" required>
            </div>
            
            <div class="form-group">
                <label for="password">Mật khẩu:</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" placeholder="Mật khẩu" required class="password-input">
                    <button type="button" class="password-toggle" onclick="togglePassword()">👁️</button>
                </div>
            </div>
            
            <button type="submit" class="login-btn">Đăng nhập</button>
        </form>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleButton.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleButton.textContent = '👁️';
            }
        }
    </script>
</body>
</html>
