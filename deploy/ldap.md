# Hướng dẫn cấu hình Active Directory LDAP (DC01) cho NovaTech

> Tài liệu tham chiếu theo `Bai_2_NovaTech_Helpdesk.md` và mô hình xác thực `huytvh059/NOVATECH`.

## 1. Chuẩn bị trên DC01 (`10.10.100.11`)
- Domain: `ad.novatech.test` (NetBIOS: `NOVATECH`).
- Tạo tài khoản dịch vụ chỉ đọc `svc_portal` (không thêm vào Domain Admins).
- Kiểm tra 3 nhóm bảo mật: `GG_IT`, `GG_HR`, `GG_KeToan`.
- Lấy DN thật của OU nhân viên và 3 nhóm trên PowerShell DC01:

```powershell
Import-Module ActiveDirectory
Get-ADDomain | Select-Object DNSRoot,NetBIOSName,DistinguishedName
Get-ADUser svc_portal | Select-Object DistinguishedName,Enabled
Get-ADGroup GG_IT | Select-Object DistinguishedName
Get-ADGroup GG_HR | Select-Object DistinguishedName
Get-ADGroup GG_KeToan | Select-Object DistinguishedName
```

## 2. Kích hoạt trên `Web_Server` (`10.10.190.11`)
1. Cài PHP LDAP extension: `sudo apt install -y php8.3-ldap ldap-utils`
2. Nếu DB cũ chưa có cột AD, chạy một lần `db/migrations/002_ldap.sql` bằng quyền quản trị DB.
3. Trong `/etc/novatech/config.php`, chuyển `'provider' => 'ldap'`, điền `base_dn`, `bind_dn`, `bind_password`, `groups` DN thật.
4. Giữ `'local_admins' => ['it01']` làm tài khoản cứu hộ DB nếu `DC01` bảo trì.

## 3. Cơ chế đồng bộ vai trò & phiên
- Ánh xạ nhóm lồng nhau (`memberOf:1.2.840.113556.1.4.1941:`): `GG_IT` → `it`, `GG_HR` → `hr`, `GG_KeToan` → `accounting`, còn lại → `employee` (theo `department_map`).
- Liên kết cố định theo `objectGUID` (`ad_guid`), không ghi đè tài khoản DB trùng tên để chống chiếm tài khoản.
- Kiểm tra lại AD định kỳ mỗi `refresh_seconds` (mặc định 60 giây): nếu đổi nhóm sẽ tăng `session_version` bắt đăng nhập lại để nhận quyền mới; nếu khóa/xóa trên AD sẽ thu hồi phiên ngay.
