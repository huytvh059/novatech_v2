# NovaTech IT Helpdesk & Intranet Portal (v2)

Hệ thống website nội bộ tiếp nhận và xử lý yêu cầu hỗ trợ kỹ thuật (IT Helpdesk) cho doanh nghiệp **NovaTech**, triển khai trên hạ tầng **OpenStack** (Nginx + PHP 8.3 + **Agile Toolkit UI `atk4/ui` 6.0** + MariaDB), tích hợp **xác thực Active Directory LDAP & đồng bộ vai trò**.

---

## 1. Tính năng chính

- **Giao diện `atk4/ui` 6.0 (Fomantic-UI) hoàn toàn offline:** Không phụ thuộc CDN bên ngoài (`/atk-assets/` phục vụ cục bộ từ `vendor/atk4/ui/public`).
- **Quy trình IT Helpdesk 5 trạng thái:**
  - `Mới` (`new`) → `Đã tiếp nhận` (`received`) → `Đang xử lý` (`in_progress`) → `Hoàn thành` (`completed`) → `Đã đóng` (`closed`).
  - Phòng **Kế toán** và **Nhân sự (HR)** gửi yêu cầu, theo dõi tiến độ và bấm **Xác nhận kết quả & Đóng yêu cầu** sau khi IT xử lý xong.
  - Phòng **IT** tiếp nhận và xử lý toàn bộ yêu cầu từ Kế toán, HR và nội bộ; không tự tạo ticket hỗ trợ.
- **Phân quyền theo phòng ban (RBAC):**
  - **Kế toán (`accounting`)** & **Nhân sự (`hr`)**: chỉ xem/trao đổi/xác nhận yêu cầu thuộc phòng ban mình; bị chặn `HTTP 403` khi truy cập ticket phòng khác hoặc trang quản trị IT (`/assets`, `/users`, `/audit`).
  - **Phòng IT (`it`)**: xem & xử lý toàn bộ ticket, quản lý tài sản/VM (`assets` CRUD), quản lý & xóa tài khoản (`/users`), xem nhật ký hoạt động (`/audit`).
- **Danh bạ liên hệ (`/directory`):**
  - Phòng **Kế toán** và **HR** chỉ thấy thông tin liên lạc của bộ phận **IT** để liên hệ sửa chữa (không nhìn thấy thông tin liên hệ của nhau).
  - Phòng **IT** xem được toàn bộ danh bạ nhân sự đang hoạt động.
- **Xác thực Active Directory LDAP & Đồng bộ vai trò:**
  - Hỗ trợ đăng nhập chế độ `db` (MariaDB) hoặc `ldap` (Active Directory `DC01` tại `10.10.100.11`).
  - Hỗ trợ các định dạng `username`, `NOVATECH\username`, `username@ad.novatech.test`.
  - Ánh xạ nhóm bảo mật lồng nhau (`memberOf:1.2.840.113556.1.4.1941:`): `GG_IT` → `it`, `GG_HR` → `hr`, `GG_KeToan` → `accounting`, còn lại → `employee` (theo `department_map`).
  - Liên kết cố định bằng `objectGUID` (`ad_guid`), làm mới phiên định kỳ (`refresh_seconds`), khóa/thu hồi phiên tự động nếu tài khoản bị khóa/xóa trên AD, và hỗ trợ tài khoản DB cứu hộ (`local_admins`).
- **Bảo mật:** Prepared Statements (PDO & `atk4/data`), hash mật khẩu `bcrypt`, bảo vệ brute-force theo cặp `username|IP` (`login_attempts`, 5 lần sai khóa 5 phút), chống CSRF/XSS/Clickjacking, ghi `audit_logs`.

---

## 2. Hạ tầng mục tiêu (IP Plan)

| Thành phần | Mạng / Subnet | Fixed IP | Floating IP | Vai trò |
|---|---|---|---|---|
| `DC01` | `Net_LAN – SN_Servers` (`10.10.100.0/24`) | `10.10.100.11` | `172.20.1.2` | Active Directory DS & DNS |
| `FS01` | `Net_LAN – SN_Servers` (`10.10.100.0/24`) | `10.10.100.12` | `172.20.1.150` | Windows File Server (SMB) |
| `DB_Server` | `Net_LAN – SN_Servers` (`10.10.100.0/24`) | `10.10.100.13` | `172.20.1.219` | MariaDB (`novatech_db`) |
| `Web_Server` | `Net_DMZ` (`10.10.190.0/24`) | `10.10.190.11` | `172.20.1.115` | Nginx + PHP 8.3 FPM |
| `CLIENT_IT01` | `Net_LAN – SN_IT` (`10.10.110.0/24`) | `10.10.110.184` | `—` | Máy trạm phòng IT |
| `CLIENT_IT02` | `Net_LAN – SN_IT` (`10.10.110.0/24`) | `10.10.110.213` | `172.20.1.41` | Máy trạm phòng IT |
| `CLIENT_HR01` | `Net_LAN – SN_HR` (`10.10.120.0/24`) | `10.10.120.208` | `—` | Máy trạm phòng Nhân sự |
| `CLIENT_KETOAN01` | `Net_LAN – SN_KeToan` (`10.10.130.0/24`) | `10.10.130.204` | `—` | Máy trạm phòng Kế toán |

---

## 3. Cấu trúc thư mục

```text
├── config.sample.php         # Mẫu cấu hình (sao chép ra ngoài webroot)
├── composer.json / .lock     # Khai báo atk4/ui ^6.0 và autoload NovaTech\
├── db/
│   ├── schema.sql            # DDL 8 bảng cho MariaDB (novatech_db)
│   ├── seed.sql              # Dữ liệu mẫu: 3 phòng ban, kt01/hr01/it01, 8 VM IP Plan
│   └── migrations/002_ldap.sql
├── deploy/
│   ├── nginx-novatech.conf   # Cấu hình Nginx + alias /atk-assets/
│   └── ldap.md               # Hướng dẫn tích hợp & nghiệm thu DC01 thật
├── public/
│   ├── index.php             # Front controller duy nhất
│   └── router.php            # Router cho PHP built-in dev server
├── src/
│   ├── Auth/                 # AuthFactory, DbAuthProvider, LdapAuthProvider, LoginGuard, SessionContext
│   ├── Model/                # Atk4\Data\Model (Asset, User, Ticket, TicketComment, Announcement, AuditLog, Department)
│   ├── Pages/                # Các trang UI sử dụng atk4/ui
│   └── Ui/                   # PortalAtkApp & PortalLayout
├── tests/                    # self_check.php, ldap_check.php, db_integration_check.php, http_e2e_check.py
└── tools/                    # hash_password.php, set_password.php (nhập qua stdin)
```

---

## 4. Cài đặt & Chạy thử nhanh

### Yêu cầu
- PHP 8.3 (`pdo_mysql`, `mbstring`, `ldap`)
- MariaDB 10.11+
- Composer 2.x

### Các bước triển khai
1. **Cài đặt thư viện PHP (`atk4/ui`):**
   ```bash
   composer install --no-dev
   ```
2. **Import Database (`novatech_db`) bằng tài khoản quản trị DB:**
   ```bash
   sudo mariadb < db/schema.sql
   sudo mariadb < db/seed.sql
   ```
   Tạo user ứng dụng `webapp` chỉ có quyền DML (`SELECT, INSERT, UPDATE, DELETE`) trên `novatech_db.*`.
3. **Cấu hình ngoài webroot:**
   Sao chép `config.sample.php` thành `/etc/novatech/config.php` (hoặc đặt biến môi trường `NOVATECH_CONFIG`), điền mật khẩu DB thực tế.
4. **Cấp mật khẩu cho 3 tài khoản mẫu (`kt01`, `hr01`, `it01`):**
   ```bash
   php tools/set_password.php kt01
   php tools/set_password.php hr01
   php tools/set_password.php it01
   ```
5. **Chạy chế độ phát triển cục bộ:**
   ```bash
   export NOVATECH_CONFIG=/etc/novatech/config.php
   php -S 127.0.0.1:8080 -t public public/router.php
   ```
   Khi triển khai thực tế trên `Web_Server`, dùng cấu hình Nginx tại `deploy/nginx-novatech.conf`. Hướng dẫn bật chế độ Active Directory LDAP xem tại `deploy/ldap.md`.

---

## 5. Kiểm thử

```bash
find src public tools tests -name '*.php' -exec php -l {} +
php tests/self_check.php
php tests/ldap_check.php
NOVATECH_CONFIG=/path/to/config.php php tests/db_integration_check.php
NOVATECH_TEST_URL=http://127.0.0.1:8080 NOVATECH_TEST_PASSWORD='<password>' python3 tests/http_e2e_check.py
```
