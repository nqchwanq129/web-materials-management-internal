# QLVT

Ứng dụng quản lý vật tư chạy bằng PHP trên Apache/XAMPP và MySQL/MariaDB. Bản này sắp xếp lại tên tệp và thư mục, giữ nguyên dữ liệu và logic nghiệp vụ.

## Cấu trúc

- public/index.php: chuyển tới trang đăng nhập.
- public/auth/: đăng nhập và đăng xuất.
- public/dashboard/: trang theo vai trò.
- public/products/: danh sách, chi tiết, sửa và ảnh hàng hóa.
- public/imports/: phiếu nhập và tệp đính kèm.
- public/exports/: phiếu xuất.
- public/reports/: thống kê và xuất báo cáo Excel.
- public/accounts/: quản lý tài khoản.
- public/assets/: CSS, JavaScript và logo, chia theo chức năng.
- public/uploads/: ảnh và PDF đang được cơ sở dữ liệu tham chiếu.
- app/config/, app/helpers/: cấu hình và hàm dùng chung.
- resources/excel/: mẫu Excel.
- database/: schema.sql và reset.sql.
- vendor/: thư viện do Composer quản lý.

Tên các tệp trong public/uploads/, bảng/cột cơ sở dữ liệu và mã thư viện trong vendor/ được giữ nguyên để không làm sai đường dẫn đã lưu và không thay đổi thư viện.

## Giao diện

- Màu thương hiệu, trạng thái, nút, form và bố cục responsive dùng chung nằm trong `public/assets/css/shared/theme.css`. File này được tải sau CSS riêng của từng trang; chỉnh các biến trong `:root` để đổi bảng màu.
- Logo dùng `public/assets/images/company-logo.png`; bộ icon SVG dùng `public/assets/icons.svg`.
- Các trang chuyển từ bố cục cũ dùng khung điều hướng `app/views/shell-start.php`. Hỗ trợ menu điện thoại và bàn phím nằm trong `public/assets/js/shared/theme.js`.

## Chạy với XAMPP

1. Đặt toàn bộ thư mục qlvt-new trong htdocs, hoặc cấu hình Apache Alias/VirtualHost trỏ DocumentRoot tới qlvt-new/public.
2. Nếu đặt trong htdocs, truy cập http://localhost/qlvt-new/public/.
3. Dùng cơ sở dữ liệu `qlvt` hiện có. Nếu cần tạo mới, tạo database và nhập `database/schema.example.sql` bằng phpMyAdmin. File mẫu chỉ chứa cấu trúc bảng, không có dữ liệu hoặc tài khoản đăng nhập.
4. Sao chép `.env.example` thành `.env` và chỉnh `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` theo MySQL trên máy. `.env` đã được thêm vào `.gitignore`.
5. Cho Apache quyền ghi vào public/uploads/.
6. Nếu không sao chép vendor/, chạy composer install tại gốc dự án.

Khi cấu hình VirtualHost, đặt DocumentRoot tại public/ để app/, database/, resources/ và vendor/ không được phục vụ qua HTTP.
