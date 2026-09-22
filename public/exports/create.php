<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    header('Location: ../auth/sign-in.php');
    exit;
}

// Xử lý form xuất hàng
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver = trim($_POST['receiver']);
    // Nếu chọn "Khác..." thì lấy giá trị từ input custom
    if ($receiver === 'custom') {
        $receiver = trim($_POST['receiver_custom']);
    }
    $warehouse = trim($_POST['warehouse']);
    $export_date = trim($_POST['export_date']);
    $reason = trim($_POST['reason']);
    
        // Kiểm tra dữ liệu
    if (empty($receiver) || empty($warehouse) || empty($export_date)) {
        $message = 'Vui lòng nhập đủ Kho xuất, Người nhận, Ngày xuất';
        $message_type = 'error';
    } else {
        // Kiểm tra có hàng hóa nào được chọn không
        $has_items = false;
        if (isset($_POST['quantities']) && is_array($_POST['quantities'])) {
            foreach ($_POST['quantities'] as $product_id => $qty) {
                if (!empty($qty) && (int)$qty > 0 && isset($_POST['selected'][$product_id])) {
                    $has_items = true;
                    break;
                }
            }
        }
        
        if (!$has_items) {
            $message = 'Vui lòng nhập số lượng xuất cho ít nhất 1 hàng hóa';
            $message_type = 'error';
        } else {
            // Xử lý xuất hàng
            try {
                $pdo->beginTransaction();
                
                // Tính tổng tiền trước
                $total_amount = 0;
                if (isset($_POST['quantities']) && is_array($_POST['quantities'])) {
                    foreach ($_POST['quantities'] as $product_id => $qty) {
                        if (!empty($qty) && (int)$qty > 0 && isset($_POST['selected'][$product_id])) {
                            // Lấy thông tin sản phẩm
                            $product_stmt = $pdo->prepare("SELECT don_gia, so_luong_con_lai FROM products WHERE id = ?");
                            $product_stmt->execute([$product_id]);
                            $product = $product_stmt->fetch();
                            
                            if ($product && (int)$qty <= (int)$product['so_luong_con_lai']) {
                                $thanh_tien = (int)$qty * (int)$product['don_gia'];
                                $total_amount += $thanh_tien;
                            }
                        }
                    }
                }
                
                // Tạo phiếu xuất với tổng tiền và số HĐ
                $soHdXuat = trim($_POST['invoice_number'] ?? '');
                if ($soHdXuat === '') {
                    throw new Exception('Vui lòng nhập Số HĐ xuất.');
                }
                // Kiểm tra trùng số HĐ xuất
                $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM export_bill WHERE so_hd_xuat = ?');
                $checkStmt->execute([$soHdXuat]);
                if ((int)$checkStmt->fetchColumn() > 0) {
                    throw new Exception('Số HĐ xuất đã tồn tại. Vui lòng nhập số khác.');
                }
                $stmt = $pdo->prepare("INSERT INTO export_bill (nguoi_nhan, ten_kho_xuat, ngay_nhan, ly_do_xuat, tong_tien, so_hd_xuat) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$receiver, $warehouse, $export_date, $reason, $total_amount, $soHdXuat]);
                $export_bill_id = $pdo->lastInsertId();
                
                // Thêm chi tiết xuất hàng
                $stmt = $pdo->prepare("INSERT INTO export_bill_details (export_bill_id, product_id, so_luong_xuat, don_gia, thanh_tien) VALUES (?, ?, ?, ?, ?)");
                
                if (isset($_POST['quantities']) && is_array($_POST['quantities'])) {
                    foreach ($_POST['quantities'] as $product_id => $qty) {
                        if (!empty($qty) && (int)$qty > 0 && isset($_POST['selected'][$product_id])) {
                            // Lấy thông tin sản phẩm
                            $product_stmt = $pdo->prepare("SELECT don_gia, so_luong_con_lai FROM products WHERE id = ?");
                            $product_stmt->execute([$product_id]);
                            $product = $product_stmt->fetch();
                            
                            if ($product && (int)$qty <= (int)$product['so_luong_con_lai']) {
                                $thanh_tien = (int)$qty * (int)$product['don_gia'];
                                
                                // Thêm chi tiết xuất
                                $stmt->execute([$export_bill_id, $product_id, $qty, $product['don_gia'], $thanh_tien]);
                                
                                // Cập nhật số lượng còn lại
                                $update_stmt = $pdo->prepare("UPDATE products SET so_luong_con_lai = so_luong_con_lai - ? WHERE id = ?");
                                $update_stmt->execute([$qty, $product_id]);
                            }
                        }
                    }
                }
                
                $pdo->commit();
                $message = 'Xuất hàng thành công!';
                $message_type = 'success';
                
                // Reload trang để cập nhật danh sách
                header('Location: ../exports/create.php?success=1');
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Có lỗi xảy ra khi xuất hàng: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Lấy danh sách hàng hóa còn tồn kèm số hóa đơn nhập (join import_bill)
$stmt = $pdo->query("\n    SELECT p.id, p.ten_san_pham, p.loai, p.don_vi, p.ngay_nhap, p.don_gia, p.so_luong_con_lai, ib.so_hoa_don\n    FROM products p\n    LEFT JOIN import_bill_details ibd ON ibd.product_id = p.id\n    LEFT JOIN import_bill ib ON ib.id = ibd.import_bill_id\n    WHERE p.so_luong_con_lai > 0\n    ORDER BY p.ngay_nhap DESC, p.ten_san_pham ASC\n");
$products = $stmt->fetchAll();

// Kiểm tra thông báo thành công
if (isset($_GET['success'])) {
    $message = 'Xuất hàng thành công!';
    $message_type = 'success';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Xuất Hàng Hóa - Admin</title>
	<link rel="stylesheet" href="assets/css/shared/layout.css">
	<link rel="stylesheet" href="assets/css/exports/create.css">
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
				<li class="active">Xuất hàng hóa
					<ul>
						<li class="active"><a href="exports/create.php">Xuất hóa đơn</a></li>
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
			<h2>Xuất Hàng Hóa</h2>

			<?php if ($message): ?>
				<div class="message <?php echo $message_type; ?>">
					<?php echo htmlspecialchars($message); ?>
				</div>
			<?php endif; ?>

			<form class="invoice-form" method="POST">
				<!-- Thông tin xuất hàng -->
				<div class="section">
					<div class="section-header">
						<h3>Thông tin xuất hàng</h3>
					</div>
					<div class="section-content">
						<div class="form-row">
							<div class="form-column">
								<div class="form-group">
									<label for="warehouse">Kho xuất:</label>
									<select id="warehouse" name="warehouse" required>
										<option value="Đài TTXLTTH Hà Nội - 34 ngõ 60 Dương Khuê">Đài TTXLTTH Hà Nội - 34 ngõ 60 Dương Khuê</option>
									</select>
								</div>
								<div class="form-group">
									<label for="receiver">Người nhận:</label>
									<select id="receiver" name="receiver" required onchange="handleReceiverChange()">
										<option value="Vishipel - Cục Hàng Hải Việt Nam">Vishipel - Cục Hàng Hải Việt Nam</option>
										<option value="Nguyễn Văn Dũng">Nguyễn Văn Dũng</option>
										<option value="Nguyễn Thị Hảo">Nguyễn Thị Hảo</option>
										<option value="Dương Mạnh Tuấn">Dương Mạnh Tuấn</option>
										<option value="custom">Khác...</option>
									</select>
									<input type="text" id="receiver-custom" name="receiver_custom" placeholder="Nhập tên người nhận" style="display: none; margin-top: 5px;">
								</div>
							</div>
							<div class="form-column">
								<div class="form-group">
									<label for="export-date">Ngày xuất:</label>
									<input type="text" id="export-date" name="export_date" class="date-picker" placeholder="dd/mm/yyyy" value="<?php echo date('Y-m-d'); ?>" required>
								</div>
                        <div class="form-group">
									<label for="reason">Lý do xuất:</label>
									<input type="text" id="reason" name="reason" placeholder="Nhập lý do xuất hàng" value="Phục vụ sản xuất kinh doanh">
								</div>
                        <div class="form-group">
                            <label for="invoice-number">Số HĐ xuất:</label>
                            <input type="text" id="invoice-number" name="invoice_number" placeholder="Nhập số hóa đơn xuất" required>
                        </div>
							</div>
						</div>
					</div>
				</div>

				<!-- Danh sách hàng hóa -->
				<div class="section">
					<div class="section-header">
						<h3>Danh sách hàng hóa</h3>
					</div>
					<div class="section-content">
						<div class="table-container">
							<table class="goods-table">
                                <thead>
                                    <tr>
                                        <th class="center">Chọn</th>
                                        <th class="center-header">Hàng hóa</th>
										<th class="center">Nhóm hàng</th>
										<th class="center">Đơn vị tính</th>
                                        <th class="center">Ngày nhập</th>
                                        <th class="center">Số HĐ</th>
										<th class="amount">Đơn giá (VNĐ)</th>
										<th class="center">SL còn lại</th>
										<th class="center">SL xuất</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($products as $p): ?>
										<tr>
                                            <td class="center">
                                                <input type="checkbox" name="selected[<?php echo $p['id']; ?>]" value="1">
                                            </td>
											<td><?php echo htmlspecialchars($p['ten_san_pham']); ?></td>
											<td class="center"><?php echo htmlspecialchars($p['loai']); ?></td>
											<td class="center"><?php echo htmlspecialchars($p['don_vi']); ?></td>
                                            <td class="center"><?php echo date('d/m/Y', strtotime($p['ngay_nhap'])); ?></td>
                                            <td class="center"><?php echo htmlspecialchars($p['so_hoa_don'] ?? ''); ?></td>
											<td class="amount"><?php echo number_format($p['don_gia'], 0, ',', '.'); ?></td>
											<td class="center"><?php echo (int)$p['so_luong_con_lai']; ?></td>
											<td class="center">
												<input type="number" name="quantities[<?php echo $p['id']; ?>]" class="qty-input" min="0" max="<?php echo $p['so_luong_con_lai']; ?>" step="1" value="<?php echo $p['so_luong_con_lai']; ?>">
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<div class="form-actions">
					<button type="submit" class="btn btn-primary">Xuất hàng hóa</button>
				</div>
			</form>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
	<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/vn.js"></script>
    <script src="assets/js/shared/sidebar-toggle.js" defer></script>
    <script>
        // Toggle sidebar
        (function(){
            var btn = document.getElementById('sidebarToggle');
            if (btn) {
                btn.addEventListener('click', function(){
                    document.body.classList.toggle('sidebar-collapsed');
                });
            }
        })();
		// Xử lý thay đổi người nhận
		function handleReceiverChange() {
			const receiverSelect = document.getElementById('receiver');
			const customInput = document.getElementById('receiver-custom');
			
			if (receiverSelect.value === 'custom') {
				customInput.style.display = 'block';
				customInput.required = true;
				customInput.focus();
			} else {
				customInput.style.display = 'none';
				customInput.required = false;
				customInput.value = '';
			}
		}
		
		// Khởi tạo Flatpickr tiếng Việt cho ô Ngày xuất
		(function(){
			if (window.flatpickr) {
				flatpickr('#export-date', {
					locale: flatpickr.l10ns.vn,
					dateFormat: 'Y-m-d',
					altInput: true,
					altFormat: 'd/m/Y',
					allowInput: true
				});
			}
		})();
		// Hàm format số với dấu chấm để dễ đọc
		function formatNumber(num) {
			if (num === 0) return '0';
			return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
		}

		// Hàm parse số từ format 1.000 về số
		function parseNumberValue(value) {
			return parseInt(value.replace(/[^\d]/g, '')) || 0;
		}

		// Thêm event listener cho tất cả các trường nhập số lượng
		document.addEventListener('DOMContentLoaded', function() {
			const quantityInputs = document.querySelectorAll('.qty-input');
			
			quantityInputs.forEach(input => {
				// Khi đang nhập: giữ nguyên số
				input.addEventListener('input', function() {
					const rawValue = this.value.replace(/[^\d]/g, '');
					if (rawValue) {
						this.value = rawValue;
					}
				});
				
				// Khi bấm ra ngoài: format số
				input.addEventListener('blur', function() {
					const rawValue = this.value.replace(/[^\d]/g, '');
					if (rawValue && parseInt(rawValue) > 0) {
						this.value = formatNumber(parseInt(rawValue));
					}
				});
			});
		});
	</script>
</body>
</html>


