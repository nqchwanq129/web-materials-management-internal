-- Sample database schema only. No internal data or user accounts.
-- Create an empty database before importing this file.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `export_bill` (
  `id` int(11) NOT NULL,
  `nguoi_nhan` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `ten_kho_xuat` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `ngay_nhan` date NOT NULL,
  `tong_tien` decimal(20,3) NOT NULL DEFAULT 0.000,
  `so_hd_xuat` varchar(100) DEFAULT NULL,
  `ly_do_xuat` varchar(500) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `export_bill_details` (
  `id` int(11) NOT NULL,
  `export_bill_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `so_luong_xuat` int(11) NOT NULL,
  `don_gia` decimal(20,3) NOT NULL DEFAULT 0.000,
  `thanh_tien` decimal(20,3) NOT NULL DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `import_bill` (
  `id` int(11) NOT NULL,
  `so_hoa_don` varchar(100) NOT NULL,
  `serial` varchar(100) DEFAULT NULL,
  `nha_cung_cap` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `nguoi_nhan_hang` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL COMMENT 'Họ và tên người nhận hàng',
  `nhap_vao_don_vi` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `ngay_nhap` date NOT NULL,
  `tong_tien` decimal(20,3) NOT NULL DEFAULT 0.000,
  `so_luong_mat_hang` int(11) NOT NULL,
  `pdf_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `import_bill_details` (
  `id` int(11) NOT NULL,
  `import_bill_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `so_luong_nhap` int(11) NOT NULL,
  `don_gia` decimal(20,3) NOT NULL DEFAULT 0.000,
  `thanh_tien` decimal(20,3) NOT NULL DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `ten_san_pham` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `don_vi` varchar(50) NOT NULL,
  `ngay_nhap` date NOT NULL,
  `so_luong_nhap` int(11) NOT NULL,
  `don_gia` decimal(20,3) NOT NULL DEFAULT 0.000,
  `thanh_tien` decimal(20,3) NOT NULL DEFAULT 0.000,
  `so_luong_da_xuat` int(11) DEFAULT 0,
  `so_luong_con_lai` int(11) NOT NULL,
  `loai` enum('Công cụ dụng cụ','Vật tư','Tài sản cố định','Phụ tùng thay thế','Khác') NOT NULL,
  `serial` varchar(100) DEFAULT NULL COMMENT 'Số serial của sản phẩm',
  `ghi_chu` text DEFAULT NULL COMMENT 'Ghi chú về sản phẩm',
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Bảng sản phẩm với thông tin serial và ghi chú';

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `position` tinyint(4) NOT NULL DEFAULT 1,
  `alt_text` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `import_bill_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `gender` enum('Nam','Nữ') NOT NULL,
  `birthday` date DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `role` enum('Admin','Thủ kho','Người nhận hàng') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `export_bill`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_export_bill_so_hd_xuat` (`so_hd_xuat`);

ALTER TABLE `export_bill_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `export_bill_id` (`export_bill_id`),
  ADD KEY `product_id` (`product_id`);

ALTER TABLE `import_bill`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `so_hoa_don` (`so_hoa_don`);

ALTER TABLE `import_bill_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_import_bill_details_bill` (`import_bill_id`),
  ADD KEY `idx_import_bill_details_product` (`product_id`);

ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_import_bill_id` (`import_bill_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

ALTER TABLE `export_bill`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `export_bill_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `import_bill`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `import_bill_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `export_bill_details`
  ADD CONSTRAINT `export_bill_details_ibfk_1` FOREIGN KEY (`export_bill_id`) REFERENCES `export_bill` (`id`),
  ADD CONSTRAINT `export_bill_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

ALTER TABLE `import_bill_details`
  ADD CONSTRAINT `import_bill_details_ibfk_1` FOREIGN KEY (`import_bill_id`) REFERENCES `import_bill` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `import_bill_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `product_images`
  ADD CONSTRAINT `fk_pi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_product_images_import_bill` FOREIGN KEY (`import_bill_id`) REFERENCES `import_bill` (`id`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
