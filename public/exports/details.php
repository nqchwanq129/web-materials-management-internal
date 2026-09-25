<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/csrf_helper.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    header('Location: ../auth/sign-in.php');
    exit;
}

$id = $_GET['id'] ?? '';
if ($id === '') { header('Location: ../exports/index.php'); exit; }

// Xử lý lưu hóa đơn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    requireCSRFToken(); // Validate CSRF token
    try {
        $pdo->beginTransaction();
        
        // Cập nhật thông tin hóa đơn xuất
        // Kiểm tra Số HĐ xuất bắt buộc và không trùng
        $soHdXuat = trim($_POST['so_hd_xuat'] ?? '');
        if ($soHdXuat === '') {
            throw new Exception('Vui lòng nhập Số HĐ xuất.');
        }
        $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM export_bill WHERE so_hd_xuat = ? AND id <> ?');
        $checkStmt->execute([$soHdXuat, $id]);
        if ((int)$checkStmt->fetchColumn() > 0) {
            throw new Exception('Số HĐ xuất đã tồn tại. Vui lòng nhập số khác.');
        }

        // Lấy ngày xuất và validate
        $ngayXuat = trim($_POST['ngay_xuat'] ?? '');
        if (empty($ngayXuat)) {
            throw new Exception('Vui lòng nhập ngày xuất.');
        }
        // Validate định dạng ngày (Y-m-d)
        $dateParts = explode('-', $ngayXuat);
        if (count($dateParts) !== 3 || !checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])) {
            throw new Exception('Ngày xuất không hợp lệ. Vui lòng nhập lại.');
        }

        $stmt = $pdo->prepare("UPDATE export_bill SET 
            nguoi_nhan = ?, 
            ly_do_xuat = ?, 
            ten_kho_xuat = ?, 
            ngay_nhan = ?, 
            tong_tien = ?,
            so_hd_xuat = ?
            WHERE id = ?");
        
        $stmt->execute([
            $_POST['nguoi_nhan'] ?? '',
            $_POST['ly_do_xuat'] ?? '',
            $_POST['ten_kho_xuat'] ?? '',
            $ngayXuat,
            str_replace(['.', ','], '', $_POST['tong_tien'] ?? '0'),
            $soHdXuat,
            $id
        ]);
        
        // Cập nhật thông tin hàng hóa nếu có
        if (isset($_POST['products']) && is_array($_POST['products'])) {
            $totalAmount = 0;
            
            foreach ($_POST['products'] as $productId => $productData) {
                // Tính lại thành tiền dựa trên số lượng và đơn giá
                $soLuong = (int)($productData['so_luong_xuat'] ?? 0);
                $donGia = (int)str_replace(['.', ','], '', $productData['don_gia'] ?? '0');
                $thanhTien = $soLuong * $donGia;
                
                // Cập nhật chi tiết xuất kho
                $stmt = $pdo->prepare("UPDATE export_bill_details SET 
                    so_luong_xuat = ?, 
                    don_gia = ?, 
                    thanh_tien = ?
                    WHERE product_id = ? AND export_bill_id = ?");
                
                $stmt->execute([
                    $soLuong,
                    $donGia,
                    $thanhTien,
                    $productId,
                    $id
                ]);
                
                // Cập nhật thông tin sản phẩm
                $stmt = $pdo->prepare("UPDATE products SET 
                    ten_san_pham = ?, 
                    loai = ?, 
                    don_vi = ?
                    WHERE id = ?");
                
                $stmt->execute([
                    $productData['ten_san_pham'] ?? '',
                    $productData['loai'] ?? '',
                    $productData['don_vi'] ?? '',
                    $productId
                ]);
                
                // Cập nhật số lượng đã xuất trong bảng products
                $stmt = $pdo->prepare("UPDATE products SET so_luong_da_xuat = (
                    SELECT COALESCE(SUM(so_luong_xuat), 0) 
                    FROM export_bill_details 
                    WHERE product_id = ?
                ) WHERE id = ?");
                $stmt->execute([$productId, $productId]);
                
                // Cập nhật số lượng còn lại
                $stmt = $pdo->prepare("UPDATE products SET so_luong_con_lai = so_luong_nhap - so_luong_da_xuat WHERE id = ?");
                $stmt->execute([$productId]);
                
                // Cộng dồn tổng tiền
                $totalAmount += $thanhTien;
            }
            
            // Cập nhật lại tổng tiền của hóa đơn
            $stmt = $pdo->prepare("UPDATE export_bill SET tong_tien = ? WHERE id = ?");
            $stmt->execute([$totalAmount, $id]);
        }
        
        $pdo->commit();
        $success_message = "Cập nhật hóa đơn xuất thành công!";
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => $success_message]);
            exit;
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Export update failed: ' . $e->getMessage());
        $error_message = $e instanceof PDOException ? 'Chưa thể lưu phiếu xuất. Vui lòng thử lại.' : $e->getMessage();
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit;
        }
    }
}

// Lấy thông tin hóa đơn
$stmt = $pdo->prepare('SELECT * FROM export_bill WHERE id = ?');
$stmt->execute([$id]);
$bill = $stmt->fetch();
if (!$bill) { header('Location: ../exports/index.php'); exit; }

// Lấy danh sách hàng hóa xuất
$details = [];
try {
    $st = $pdo->prepare('SELECT ebd.*, p.ten_san_pham, p.loai, p.don_vi FROM export_bill_details ebd JOIN products p ON p.id = ebd.product_id WHERE ebd.export_bill_id = ? ORDER BY ebd.id ASC');
    $st->execute([$id]);
    $details = $st->fetchAll();
} catch (Exception $e) {
    // fallback rỗng
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phiếu xuất kho số <?php echo htmlspecialchars($bill['id']); ?></title>
    <link rel="stylesheet" href="assets/css/dashboard/admin.css">
    <link rel="stylesheet" href="assets/css/exports/details.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="assets/css/shared/icons.css">
    <link rel="stylesheet" href="assets/css/shared/theme.css">
</head>
<body class="migrated-page">
<a class="skip-link" href="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? basename(__DIR__) . '/' . basename(__FILE__), ENT_QUOTES, 'UTF-8') ?>#main-content">Đến nội dung chính</a>
<?php $shellTitle = 'Chi tiết phiếu xuất'; $shellActive = 'exports/index.php'; require __DIR__ . '/../../app/views/shell-start.php'; ?>

            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                <h2>Phiếu xuất kho số <?php echo htmlspecialchars($bill['id']); ?></h2>
                <div>
                    <a href="exports/export-excel.php?id=<?php echo htmlspecialchars($bill['id']); ?>" class="btn btn-success"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#spreadsheet"></use></svg> Xuất Excel</a>
                </div>
            </div>

            <!-- Hiển thị thông báo -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="section">
                <div class="section-header"><h3>Thông Tin Hóa Đơn</h3></div>
                <div class="section-content">
                    <div class="invoice-form">
                        <div class="form-row">
                            <div class="form-column">
                                <div class="form-group">
                                    <label for="nguoi_nhan">Người Nhận:</label>
                                    <input type="text" id="nguoi_nhan" name="nguoi_nhan" value="<?php echo htmlspecialchars($bill['nguoi_nhan'] ?? ''); ?>" class="form-input">
                                </div>
                                <div class="form-group">
                                    <label for="ly_do_xuat">Lý Do Xuất:</label>
                                    <input type="text" id="ly_do_xuat" name="ly_do_xuat" value="<?php echo htmlspecialchars($bill['ly_do_xuat'] ?? ''); ?>" class="form-input">
                                </div>
                                <div class="form-group">
                                    <label for="ten_kho_xuat">Kho Xuất:</label>
                                    <input type="text" id="ten_kho_xuat" name="ten_kho_xuat" value="<?php echo htmlspecialchars($bill['ten_kho_xuat'] ?? ''); ?>" class="form-input">
                                </div>
                                <div class="form-group">
                                    <label for="ngay_xuat">Ngày Xuất:</label>
                                    <div class="date-input-container">
                                        <input type="text" id="ngay_xuat" name="ngay_xuat" class="date-picker" placeholder="dd/mm/yyyy"
                                               value="<?php echo isset($bill['ngay_nhan']) ? date('Y-m-d', strtotime($bill['ngay_nhan'])) : date('Y-m-d'); ?>" required>
                                        <span class="calendar-icon"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#calendar"></use></svg></span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="tong_tien">Tổng Tiền (VNĐ):</label>
                                    <input type="text" id="tong_tien" name="tong_tien" value="<?php echo number_format((int)($bill['tong_tien'] ?? 0),0,',','.'); ?>" class="form-input amount" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="so_hd_xuat">Số HĐ xuất:</label>
                                    <input type="text" id="so_hd_xuat" name="so_hd_xuat" value="<?php echo htmlspecialchars($bill['so_hd_xuat'] ?? ''); ?>" class="form-input" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="section-header"><h3>Danh Sách Hàng Hóa</h3></div>
                <div class="section-content">
                    <?php if (empty($details)): ?>
                        <div class="no-data"><p>Không có chi tiết hàng hóa</p></div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="goods-table">
                                <thead>
                                    <tr>
                                        <th>Hàng Hóa</th>
                                        <th>Nhóm hàng</th>
                                        <th>Đơn vị tính</th>
                                        <th>Số Lượng</th>
                                        <th>Đơn Giá (VNĐ)</th>
                                        <th>Thành Tiền (VNĐ)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($details as $d): ?>
                                        <tr>
                                            <td>
                                                <input type="text" name="products[<?php echo $d['product_id']; ?>][ten_san_pham]" 
                                                       value="<?php echo htmlspecialchars($d['ten_san_pham'] ?? ''); ?>" 
                                                       class="form-input">
                                            </td>
                                            <td>
                                                <select name="products[<?php echo $d['product_id']; ?>][loai]" class="form-input">
                                                    <option value="Công cụ dụng cụ" <?php echo ($d['loai'] ?? '') === 'Công cụ dụng cụ' ? 'selected' : ''; ?>>Công cụ dụng cụ</option>
                                                    <option value="Vật tư" <?php echo ($d['loai'] ?? '') === 'Vật tư' ? 'selected' : ''; ?>>Vật tư</option>
                                                    <option value="Tài sản cố định" <?php echo ($d['loai'] ?? '') === 'Tài sản cố định' ? 'selected' : ''; ?>>Tài sản cố định</option>
                                                    <option value="Phụ tùng thay thế" <?php echo ($d['loai'] ?? '') === 'Phụ tùng thay thế' ? 'selected' : ''; ?>>Phụ tùng thay thế</option>
                                                    <option value="Khác" <?php echo ($d['loai'] ?? '') === 'Khác' ? 'selected' : ''; ?>>Khác</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="products[<?php echo $d['product_id']; ?>][don_vi]" 
                                                       value="<?php echo htmlspecialchars($d['don_vi'] ?? ''); ?>" 
                                                       class="form-input">
                                            </td>
                                            <td>
                                                <input type="number" name="products[<?php echo $d['product_id']; ?>][so_luong_xuat]" 
                                                       value="<?php echo (int)($d['so_luong_xuat'] ?? 0); ?>" 
                                                       class="form-input center" min="0">
                                            </td>
                                            <td>
                                                <input type="text" name="products[<?php echo $d['product_id']; ?>][don_gia]" 
                                                       value="<?php echo number_format((int)($d['don_gia'] ?? 0),0,',','.'); ?>" 
                                                       class="form-input amount">
                                            </td>
                                            <td class="amount"><?php echo number_format((int)($d['thanh_tien'] ?? 0),0,',','.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Nút Lưu Hóa Đơn và Thu hồi -->
            <div class="action-section">
                <form id="save-form" method="POST" style="display: inline-block; margin-right: 15px;">
                    <input type="hidden" name="action" value="save">
                    <?php echo csrfTokenField(); ?>
                    <button type="submit" class="btn btn-primary"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#save"></use></svg> Lưu Hóa Đơn</button>
                </form>
                
                <button type="button" class="btn btn-danger" id="btn-recall"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#undo"></use></svg> Thu hồi</button>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/vn.js"></script>
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
        // Khởi tạo Flatpickr tiếng Việt cho ô Ngày xuất
        let flatpickrInstance = null;
        (function(){
            if (window.flatpickr) {
                flatpickrInstance = flatpickr('#ngay_xuat', {
                    locale: flatpickr.l10ns.vn,
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'd/m/Y',
                    allowInput: true
                });
            }
        })();

        // Format số tiền khi nhập
        document.getElementById('tong_tien').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d]/g, '');
            if (value) {
                value = parseInt(value).toLocaleString('vi-VN');
                e.target.value = value;
            }
        });

        // Format số tiền cho các input trong bảng hàng hóa
        document.querySelectorAll('input[name*="[don_gia]"]').forEach(input => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/[^\d]/g, '');
                if (value) {
                    value = parseInt(value).toLocaleString('vi-VN');
                    e.target.value = value;
                }
            });
        });

        // Tự động tính toán thành tiền khi thay đổi số lượng hoặc đơn giá
        document.querySelectorAll('input[name*="[so_luong_xuat]"], input[name*="[don_gia]"]').forEach(input => {
            input.addEventListener('input', function() {
                const row = this.closest('tr');
                const soLuongInput = row.querySelector('input[name*="[so_luong_xuat]"]');
                const donGiaInput = row.querySelector('input[name*="[don_gia]"]');
                
                if (soLuongInput && donGiaInput) {
                    const soLuong = parseInt(soLuongInput.value) || 0;
                    const donGia = parseInt(donGiaInput.value.replace(/[^\d]/g, '')) || 0;
                    const thanhTien = soLuong * donGia;
                    
                    // Cập nhật ô thành tiền
                    const thanhTienCell = row.querySelector('td:nth-child(6)');
                    if (thanhTienCell) {
                        thanhTienCell.textContent = thanhTien.toLocaleString('vi-VN');
                    }
                    
                    // Cập nhật tổng tiền
                    updateTotalAmount();
                }
            });
        });

        function updateTotalAmount() {
            let totalAmount = 0;
            document.querySelectorAll('tbody tr').forEach(row => {
                const thanhTienCell = row.querySelector('td:nth-child(6)');
                if (thanhTienCell) {
                    const thanhTien = parseInt(thanhTienCell.textContent.replace(/[^\d]/g, '')) || 0;
                    totalAmount += thanhTien;
                }
            });
            
            const totalInput = document.getElementById('tong_tien');
            if (totalInput) {
                totalInput.value = totalAmount.toLocaleString('vi-VN');
            }
        }

        // Xử lý lưu hóa đơn
        const saveForm = document.getElementById('save-form');
        if (saveForm) {
            saveForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Hiển thị dialog xác nhận
                if (!confirm('Bạn có chắc chắn muốn cập nhật hóa đơn này?')) {
                    return; // Dừng lại nếu user bấm Cancel
                }
                
                // Thu thập dữ liệu từ form
                const formData = new FormData();
                formData.append('action', 'save');
                
                // Thêm CSRF token
                const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                formData.append('csrf_token', csrfToken);
                
                // Thông tin hóa đơn
                formData.append('nguoi_nhan', document.getElementById('nguoi_nhan').value);
                formData.append('ly_do_xuat', document.getElementById('ly_do_xuat').value);
                formData.append('ten_kho_xuat', document.getElementById('ten_kho_xuat').value);
                // Lấy giá trị ngày từ Flatpickr instance (đảm bảo đúng format Y-m-d)
                let ngayXuatValue = '';
                if (flatpickrInstance) {
                    // Ưu tiên lấy từ selectedDates (chắc chắn nhất)
                    if (flatpickrInstance.selectedDates && flatpickrInstance.selectedDates.length > 0) {
                        const date = flatpickrInstance.selectedDates[0];
                        ngayXuatValue = date.getFullYear() + '-' + 
                                       String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                                       String(date.getDate()).padStart(2, '0');
                    } else {
                        // Nếu không có selectedDates, parse từ altInput (d/m/Y) hoặc input gốc (Y-m-d)
                        let dateStr = '';
                        if (flatpickrInstance.altInput && flatpickrInstance.altInput.value) {
                            dateStr = flatpickrInstance.altInput.value; // d/m/Y
                        } else if (flatpickrInstance.input && flatpickrInstance.input.value) {
                            dateStr = flatpickrInstance.input.value; // Y-m-d
                        }
                        
                        if (dateStr) {
                            // Nếu là định dạng d/m/Y, parse thủ công
                            if (dateStr.includes('/')) {
                                const parts = dateStr.split('/');
                                if (parts.length === 3) {
                                    ngayXuatValue = parts[2] + '-' + parts[1].padStart(2, '0') + '-' + parts[0].padStart(2, '0');
                                } else {
                                    ngayXuatValue = dateStr; // Giữ nguyên nếu không parse được
                                }
                            } else {
                                ngayXuatValue = dateStr; // Đã là Y-m-d
                            }
                        } else {
                            ngayXuatValue = document.getElementById('ngay_xuat').value;
                        }
                    }
                } else {
                    ngayXuatValue = document.getElementById('ngay_xuat').value;
                }
                formData.append('ngay_xuat', ngayXuatValue);
                formData.append('tong_tien', document.getElementById('tong_tien').value);
                formData.append('so_hd_xuat', document.getElementById('so_hd_xuat').value);
                
                // Thông tin hàng hóa
                const productRows = document.querySelectorAll('tbody tr');
                productRows.forEach((row, index) => {
                    const productId = row.querySelector('input[name*="[so_luong_xuat]"]')?.name.match(/\[(\d+)\]/)?.[1];
                    if (productId) {
                        formData.append(`products[${productId}][ten_san_pham]`, row.querySelector('input[name*="[ten_san_pham]"]')?.value || '');
                        formData.append(`products[${productId}][loai]`, row.querySelector('select[name*="[loai]"]')?.value || '');
                        formData.append(`products[${productId}][don_vi]`, row.querySelector('input[name*="[don_vi]"]')?.value || '');
                        formData.append(`products[${productId}][so_luong_xuat]`, row.querySelector('input[name*="[so_luong_xuat]"]')?.value || '0');
                        formData.append(`products[${productId}][don_gia]`, row.querySelector('input[name*="[don_gia]"]')?.value || '0');
                    }
                });
                
                // Gửi dữ liệu
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Cập nhật hóa đơn xuất thành công!');
                        location.reload();
                    } else {
                        alert(result.message || 'Chưa thể lưu phiếu xuất. Vui lòng thử lại.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Chưa thể kết nối để lưu phiếu xuất. Vui lòng thử lại.');
                });
            });
        }

        document.getElementById('btn-recall').addEventListener('click', function(){
            if (!confirm('Bạn chắc chắn muốn thu hồi phiếu xuất này? Tồn kho sẽ được cộng trả và phiếu sẽ bị xóa.')) return;
            fetch('exports/cancel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({id: <?= json_encode((int)$bill['id']) ?>, csrf_token: document.querySelector('input[name="csrf_token"]').value})
            }).then(r=>r.json()).then(res=>{
                if (res.success){
                    alert('Đã thu hồi phiếu xuất thành công');
                    window.location.href = 'exports/index.php';
                } else {
                    alert('Lỗi: ' + res.message);
                }
            }).catch(e=>{ alert('Có lỗi xảy ra khi thu hồi'); console.error(e); });
        });

        // nothing
    </script>
<script src="assets/js/shared/theme.js" defer></script>
</body>
</html>

