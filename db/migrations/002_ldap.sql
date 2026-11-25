-- Migration 002: Bổ sung cột đồng bộ Active Directory LDAP cho bảng users nếu DB tạo từ bản cũ.
-- Chạy một lần bởi quản trị DB sau khi sao lưu.

USE novatech_db;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS auth_source ENUM('db','ldap') NOT NULL DEFAULT 'db',
  ADD COLUMN IF NOT EXISTS ad_guid CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
  ADD COLUMN IF NOT EXISTS ad_synced_at DATETIME NULL,
  ADD UNIQUE KEY uq_users_ad_guid (ad_guid);
