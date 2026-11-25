-- NovaTech IT Helpdesk & Intranet Portal — Database Schema (MariaDB 10.11+)
-- Chạy bằng tài khoản quản trị DB, không chạy bằng tài khoản ứng dụng webapp.

CREATE DATABASE IF NOT EXISTS novatech_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE novatech_db;

CREATE TABLE IF NOT EXISTS departments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ma_phong_ban VARCHAR(32) NOT NULL UNIQUE,
  ten_phong_ban VARCHAR(100) NOT NULL UNIQUE,
  nhom_ad VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  ho_ten VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL DEFAULT '',
  so_may_le VARCHAR(20) NOT NULL DEFAULT '',
  phong_ban_id INT UNSIGNED NOT NULL,
  role ENUM('employee','accounting','hr','it') NOT NULL DEFAULT 'employee',
  trang_thai ENUM('active','locked') NOT NULL DEFAULT 'active',
  so_lan_sai INT UNSIGNED NOT NULL DEFAULT 0,
  khoa_den DATETIME NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  auth_source ENUM('db','ldap') NOT NULL DEFAULT 'db',
  ad_guid CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
  ad_synced_at DATETIME NULL,
  session_version INT UNSIGNED NOT NULL DEFAULT 1,
  ngay_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_department FOREIGN KEY (phong_ban_id) REFERENCES departments(id),
  INDEX idx_users_role_status (role, trang_thai),
  INDEX idx_users_dept (phong_ban_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ten_may VARCHAR(100) NOT NULL UNIQUE,
  vai_tro VARCHAR(190) NOT NULL,
  mang VARCHAR(100) NOT NULL,
  fixed_ip VARCHAR(45) NOT NULL,
  floating_ip VARCHAR(45) NULL,
  he_dieu_hanh VARCHAR(100) NOT NULL,
  phong_ban_id INT UNSIGNED NULL,
  tinh_trang ENUM('active','maintenance','inactive') NOT NULL DEFAULT 'active',
  ghi_chu VARCHAR(255) NOT NULL DEFAULT '',
  CONSTRAINT fk_assets_department FOREIGN KEY (phong_ban_id) REFERENCES departments(id) ON DELETE SET NULL,
  INDEX idx_assets_mang (mang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tieu_de VARCHAR(200) NOT NULL,
  noi_dung TEXT NOT NULL,
  pham_vi ENUM('company','department') NOT NULL DEFAULT 'company',
  phong_ban_id INT UNSIGNED NULL,
  tao_boi INT UNSIGNED NOT NULL,
  ngay_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ann_dept FOREIGN KEY (phong_ban_id) REFERENCES departments(id),
  CONSTRAINT fk_ann_author FOREIGN KEY (tao_boi) REFERENCES users(id),
  CONSTRAINT chk_ann_scope CHECK ((pham_vi = 'company' AND phong_ban_id IS NULL) OR (pham_vi = 'department' AND phong_ban_id IS NOT NULL)),
  INDEX idx_ann_scope (pham_vi, phong_ban_id, ngay_tao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tieu_de VARCHAR(200) NOT NULL,
  loai_su_co ENUM('hardware','software','network','internal_service','other') NOT NULL DEFAULT 'other',
  mo_ta TEXT NOT NULL,
  uu_tien ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  trang_thai ENUM('new','received','in_progress','completed','closed') NOT NULL DEFAULT 'new',
  ket_qua_xu_ly TEXT NULL,
  phong_ban_id INT UNSIGNED NOT NULL,
  nguoi_tao INT UNSIGNED NOT NULL,
  nguoi_xu_ly INT UNSIGNED NULL,
  asset_id INT UNSIGNED NULL,
  ngay_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ngay_cap_nhat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ngay_hoan_thanh DATETIME NULL,
  ngay_dong DATETIME NULL,
  CONSTRAINT fk_tickets_dept FOREIGN KEY (phong_ban_id) REFERENCES departments(id),
  CONSTRAINT fk_tickets_creator FOREIGN KEY (nguoi_tao) REFERENCES users(id),
  CONSTRAINT fk_tickets_handler FOREIGN KEY (nguoi_xu_ly) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_tickets_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
  INDEX idx_tickets_dept_status (phong_ban_id, trang_thai),
  INDEX idx_tickets_creator_status (nguoi_tao, trang_thai),
  INDEX idx_tickets_status_updated (trang_thai, ngay_cap_nhat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL,
  nguoi_gui INT UNSIGNED NOT NULL,
  noi_dung TEXT NOT NULL,
  ngay_gui DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comments_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id),
  CONSTRAINT fk_comments_user FOREIGN KEY (nguoi_gui) REFERENCES users(id),
  INDEX idx_comments_ticket (ticket_id, ngay_gui)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  username VARCHAR(64) NOT NULL,
  hanh_dong VARCHAR(64) NOT NULL,
  doi_tuong VARCHAR(190) NOT NULL,
  ip_nguon VARCHAR(45) NOT NULL,
  ket_qua VARCHAR(30) NOT NULL,
  thoi_gian DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_user_time (username, thoi_gian),
  INDEX idx_audit_action_time (hanh_dong, thoi_gian),
  INDEX idx_audit_time (thoi_gian)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  attempt_key CHAR(64) CHARACTER SET ascii PRIMARY KEY,
  failures INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_attempt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_attempts_last (last_attempt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
