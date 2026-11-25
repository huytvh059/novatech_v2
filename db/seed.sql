-- Dữ liệu mẫu chuẩn theo IP Plan trong Bai_2_NovaTech_Helpdesk.md
-- Chạy một lần trên database mới tạo. Mật khẩu tạm của tài khoản mẫu cần cấp qua tools/set_password.php.

USE novatech_db;

START TRANSACTION;

INSERT INTO departments (id, ma_phong_ban, ten_phong_ban, nhom_ad) VALUES
  (1, 'KeToan', 'Kế toán', 'GG_KeToan'),
  (2, 'HR', 'Nhân sự', 'GG_HR'),
  (3, 'IT', 'IT', 'GG_IT');

-- Password hash khởi tạo ngẫu nhiên (không dùng để đăng nhập trước khi cấp mật khẩu bằng tools/set_password.php)
INSERT INTO users (id, username, password_hash, ho_ten, email, so_may_le, phong_ban_id, role, must_change_password, auth_source) VALUES
  (1, 'kt01', '$2y$10$InvalidPlaceholderHashDoNotUseDirectly000000000000000000', 'Nguyễn Minh Anh', 'kt01@novatech.test', '101', 1, 'accounting', 1, 'db'),
  (2, 'hr01', '$2y$10$InvalidPlaceholderHashDoNotUseDirectly000000000000000000', 'Trần Thu Hà', 'hr01@novatech.test', '201', 2, 'hr', 1, 'db'),
  (3, 'it01', '$2y$10$InvalidPlaceholderHashDoNotUseDirectly000000000000000000', 'Lê Hoàng Nam', 'it01@novatech.test', '301', 3, 'it', 1, 'db');

-- Đúng 8 máy ảo trong IP Plan của Bai_2_NovaTech_Helpdesk.md
INSERT INTO assets (id, ten_may, vai_tro, mang, fixed_ip, floating_ip, he_dieu_hanh, phong_ban_id, tinh_trang, ghi_chu) VALUES
  (1, 'DC01', 'Active Directory DS & DNS Server', 'Net_LAN (SN_Servers)', '10.10.100.11', '172.20.1.2', 'Windows Server 2019', 3, 'active', 'Domain Controller chính'),
  (2, 'FS01', 'Windows File Server (SMB)', 'Net_LAN (SN_Servers)', '10.10.100.12', '172.20.1.150', 'Windows Server 2019', 3, 'active', 'Lưu trữ D:\\Shares\\{KeToan,HR,IT}'),
  (3, 'DB_Server', 'Database Server (MariaDB)', 'Net_LAN (SN_Servers)', '10.10.100.13', '172.20.1.219', 'Ubuntu 24.04', 3, 'active', 'Lưu trữ novatech_db'),
  (4, 'Web_Server', 'Web Server (Nginx + PHP-FPM)', 'Net_DMZ', '10.10.190.11', '172.20.1.115', 'Ubuntu 24.04', 3, 'active', 'Cổng Portal IT Helpdesk'),
  (5, 'CLIENT_IT01', 'Máy trạm phòng IT', 'Net_LAN (SN_IT)', '10.10.110.184', NULL, 'Windows 10', 3, 'active', 'Máy trạm quản trị IT 01'),
  (6, 'CLIENT_IT02', 'Máy trạm phòng IT', 'Net_LAN (SN_IT)', '10.10.110.213', '172.20.1.41', 'Windows 10', 3, 'active', 'Máy trạm quản trị IT 02'),
  (7, 'CLIENT_HR01', 'Máy trạm phòng Nhân sự', 'Net_LAN (SN_HR)', '10.10.120.208', NULL, 'Windows 10', 2, 'active', 'Máy trạm phòng HR'),
  (8, 'CLIENT_KETOAN01', 'Máy trạm phòng Kế toán', 'Net_LAN (SN_KeToan)', '10.10.130.204', NULL, 'Windows 10', 1, 'active', 'Máy trạm phòng Kế toán');

INSERT INTO announcements (tieu_de, noi_dung, pham_vi, phong_ban_id, tao_boi, ngay_tao) VALUES
  ('Chào mừng đến với NovaTech IT Helpdesk', 'Hệ thống tiếp nhận và xử lý sự cố kỹ thuật nội bộ đã sẵn sàng phục vụ các phòng ban Kế toán, Nhân sự và IT.', 'company', NULL, 3, DATE_SUB(NOW(), INTERVAL 2 DAY)),
  ('Quy trình gửi & xác nhận yêu cầu hỗ trợ', 'Phòng ban tạo ticket chọn đúng thiết bị và mức ưu tiên. Sau khi IT cập nhật trạng thái Hoàn thành, phòng ban kiểm tra và bấm Xác nhận đóng.', 'company', NULL, 3, DATE_SUB(NOW(), INTERVAL 1 DAY)),
  ('Lịch kiểm tra quyền truy cập File Server FS01', 'Đề nghị các phòng ban xác nhận quyền truy cập thư mục chia sẻ D:\\Shares tương ứng.', 'company', NULL, 2, NOW()),
  ('Kế hoạch rà soát Security Group OpenStack', 'Đội IT triển khai kiểm tra luồng cổng AD/DNS/SMB/HTTP/MariaDB giữa Net_DMZ và SN_Servers.', 'department', 3, 3, NOW());

INSERT INTO tickets (id, tieu_de, loai_su_co, mo_ta, uu_tien, trang_thai, ket_qua_xu_ly, phong_ban_id, nguoi_tao, nguoi_xu_ly, asset_id, ngay_hoan_thanh, ngay_dong) VALUES
  (1, 'Không mở được thư mục chia sẻ Kế toán trên FS01', 'internal_service', 'Từ máy CLIENT_KETOAN01 truy cập \\\\10.10.100.12\\KeToan báo lỗi quyền truy cập.', 'high', 'new', NULL, 1, 1, NULL, 8, NULL, NULL),
  (2, 'Kiểm tra kết nối mạng máy trạm phòng Nhân sự', 'network', 'Máy CLIENT_HR01 chập chờn khi kết nối cổng nội bộ http://10.10.190.11.', 'medium', 'in_progress', NULL, 2, 2, 3, 7, NULL, NULL),
  (3, 'Cài đặt chứng chỉ và cấu hình DNS nội bộ cho CLIENT_IT01', 'software', 'Đã kiểm tra phân giải tên miền nội bộ về DC01 (10.10.100.11).', 'low', 'completed', 'Đã cấu hình DNS primary 10.10.100.11 và xác nhận Join Domain thành công.', 3, 3, 3, 5, NOW(), NULL);

INSERT INTO ticket_comments (ticket_id, nguoi_gui, noi_dung) VALUES
  (2, 3, 'Đã tiếp nhận yêu cầu và chuyển sang trạng thái Đang xử lý kiểm tra tuyến Router R1.'),
  (3, 3, 'Hoàn tất cấu hình DNS trỏ về DC01. Mời kiểm tra lại kết nối.');

COMMIT;
