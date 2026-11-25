# BÀI 2. XÂY DỰNG HẠ TẦNG MẠNG CHO DOANH NGHIỆP NOVATECH

**Nền tảng triển khai:** OpenStack

## Phần 1. Use case / Case study / Case project

### Bối cảnh doanh nghiệp

Công ty TNHH Công nghệ NovaTech là doanh nghiệp hoạt động trong lĩnh vực công nghệ thông tin, cung cấp các giải pháp hạ tầng, phần mềm và dịch vụ hỗ trợ kỹ thuật.

Công ty có ba phòng ban chính:

- Phòng Kế toán (Accounting).
- Phòng Nhân sự (HR).
- Phòng Công nghệ thông tin (IT).

Công ty có nhu cầu xây dựng hệ thống mạng nội bộ trên nền tảng OpenStack Cloud. Các phòng ban có thể sử dụng những dịch vụ dùng chung nhưng dữ liệu của từng phòng ban phải được phân quyền riêng biệt.

Hệ thống sử dụng Active Directory để quản lý tài khoản tập trung, DNS Server để phân giải tên miền nội bộ, File Server để lưu trữ và chia sẻ tài liệu.

Bên cạnh đó, công ty xây dựng website nội bộ nhằm hỗ trợ quá trình tiếp nhận và xử lý các yêu cầu kỹ thuật. Khi phòng Kế toán hoặc phòng Nhân sự gặp sự cố về máy tính, phần mềm, kết nối mạng hoặc các dịch vụ nội bộ, phòng ban có thể gửi yêu cầu thông qua website để phòng IT tiếp nhận và xử lý.

Database Server có nhiệm vụ lưu trữ thông tin yêu cầu, trạng thái và kết quả xử lý.

### Nhu cầu và yêu cầu nghiệp vụ

| Mã | Nhu cầu |
|---|---|
| UC01 | Mỗi phòng ban có một subnet riêng. |
| UC02 | Các máy chủ dịch vụ nội bộ được đặt trong mạng Servers; Web Server được đặt tại DMZ. |
| UC03 | Các phòng ban truy cập được website nội bộ. |
| UC04 | Tài khoản phòng ban được phép truy cập thư mục dữ liệu tương ứng. |
| UC05 | Tài khoản phòng ban không được truy cập dữ liệu của phòng ban khác khi không có quyền. |
| UC06 | Phòng IT có thể quản lý các máy chủ và tài nguyên được giao. |
| UC07 | Quản lý tài khoản, nhóm và máy tính tập trung bằng Active Directory. |
| UC08 | DNS Server thực hiện phân giải tên miền và dịch vụ nội bộ. |
| UC09 | Phòng Kế toán và HR có thể gửi yêu cầu hỗ trợ kỹ thuật qua website. |
| UC10 | Phòng IT tiếp nhận, kiểm tra, xử lý và cập nhật kết quả yêu cầu hỗ trợ. |
| UC11 | Database Server lưu trữ thông tin và lịch sử xử lý yêu cầu. |

### Nhiệm vụ của nhóm

Đóng vai trò là nhóm triển khai hạ tầng mạng cho công ty NovaTech. Sử dụng OpenStack để xây dựng hệ thống mạng nhiều phân đoạn, triển khai các máy chủ, máy trạm, dịch vụ dùng chung và thiết lập quyền truy cập theo từng phòng ban.

Xây dựng website nội bộ phục vụ nghiệp vụ hỗ trợ kỹ thuật, kết nối Web Server với Database Server và kiểm thử khả năng hoạt động của hệ thống.

## Phần 2. Case solution

### Solution 1 – Phân đoạn mạng doanh nghiệp

Sử dụng OpenStack Neutron để xây dựng hai mạng Net_LAN và Net_DMZ.

Net_LAN bao gồm bốn subnet phục vụ hệ thống máy chủ nội bộ và ba phòng ban. Net_DMZ được sử dụng để triển khai Web Server.

Các subnet được kết nối thông qua Router R1. Router R1 kết nối với External-Network để hỗ trợ truy cập từ mạng bên ngoài thông qua Floating IP.

### Solution 2 – Triển khai hệ thống máy chủ và máy trạm

Triển khai các máy ảo trên OpenStack theo từng vai trò:

| Thành phần | Chức năng |
|---|---|
| DC01 | Quản lý Active Directory và DNS. |
| FS01 | Lưu trữ và chia sẻ tài liệu nội bộ. |
| Web_Server | Cung cấp website nội bộ. |
| DB_Server | Lưu trữ dữ liệu website bằng MariaDB. |
| CLIENT_IT01 | Máy trạm phòng IT. |
| CLIENT_IT02 | Máy trạm phòng IT. |
| CLIENT_HR01 | Máy trạm phòng Nhân sự. |
| CLIENT_KETOAN01 | Máy trạm phòng Kế toán. |

### Solution 3 – Triển khai dịch vụ dùng chung

Triển khai Active Directory Domain Services và DNS trên Windows Server 2019 để quản lý danh tính tập trung và phân giải tên miền nội bộ.

Triển khai Windows File Server sử dụng SMB để cung cấp dịch vụ lưu trữ và chia sẻ tài liệu giữa các máy trạm và máy chủ.

Triển khai Nginx và PHP trên Web_Server, kết hợp MariaDB trên DB_Server để xây dựng website hỗ trợ kỹ thuật nội bộ.

Website được định hướng theo mô hình IT Helpdesk, trong đó phòng Kế toán và phòng Nhân sự gửi yêu cầu hỗ trợ, phòng IT tiếp nhận và xử lý các sự cố.


### Solution 4 – Thiết lập phân quyền và kiểm soát truy cập

Sử dụng Active Directory để tổ chức tài khoản và nhóm bảo mật tương ứng với từng phòng ban.

Thiết lập NTFS Permission và SMB Share Permission trên File Server nhằm bảo đảm các phòng ban chỉ truy cập được dữ liệu theo quyền được cấp.

Đối với website, xây dựng cơ chế xác thực và phân quyền chức năng theo ba phòng ban: Kế toán, Nhân sự và IT.

Security Group là nội dung kiểm soát truy cập mạng được yêu cầu trong Bài 2. Tuy nhiên, mô hình NovaTech hiện tại chưa triển khai Security Group như một lớp lọc luồng mạng. Do đó, đây là hạng mục cần bổ sung nếu thực hiện đầy đủ yêu cầu của đề bài.

### Kiến trúc mục tiêu và IP Plan

**Sơ đồ tóm tắt kiến trúc mạng NovaTech**

```text
External-Network (172.20.1.0/24)
                  │
              Router R1
                  │
     ┌────────────┴─────────────┐
     │                          │
Net_LAN                     Net_DMZ
     │                      10.10.190.0/24
     ├─ SN_Servers              ├─ Web_Server
     │  10.10.100.0/24          │- 10.10.190.11
     │  ├─ DC01                 
     │  ├─ FS01                    
     │  └─ DB_Server           
     │
     ├─ SN_IT
     │  10.10.110.0/24
     │  ├─ CLIENT_IT01
     │ 
     │
     ├─ SN_HR
     │  10.10.120.0/24
     │  └─ CLIENT_HR01
     │
     └─ SN_KeToan
        10.10.130.0/24
        └─ CLIENT_KETOAN01
```

*Sơ đồ logic tóm tắt theo cấu trúc trong tài liệu NovaTech gốc. Router R1 có interface trên từng subnet.*

**Bảng phân hoạch mạng**

| Network/Subnet | CIDR | Gateway |
|---|---|---|
| Net_LAN – SN_Servers | 10.10.100.0/24 | 10.10.100.1 |
| Net_LAN – SN_IT | 10.10.110.0/24 | 10.10.110.1 |
| Net_LAN – SN_HR | 10.10.120.0/24 | 10.10.120.1 |
| Net_LAN – SN_KeToan | 10.10.130.0/24 | 10.10.130.1 |
| Net_DMZ | 10.10.190.0/24 | 10.10.190.1 |
| External-Network | 172.20.1.0/24 | — |

**Bảng địa chỉ IP máy chủ và máy trạm**

| Tên máy | Fixed IP | Floating IP |
|---|---|---|
| DC01 | 10.10.100.11 | 172.20.1.2 |
| FS01 | 10.10.100.12 | 172.20.1.150 |
| DB_Server | 10.10.100.13 | 172.20.1.219 |
| Web_Server | 10.10.190.11 | 172.20.1.115 |
| CLIENT_IT01 | 10.10.110.184 | — |
| CLIENT_IT02 | 10.10.110.213 | 172.20.1.41 |
| CLIENT_HR01 | 10.10.120.208 | — |
| CLIENT_KETOAN01 | 10.10.130.204 | — |

Địa chỉ IP và tên máy được giữ nguyên theo bảng IP Plan trong tài liệu NovaTech. Địa chỉ được giữ trong kế hoạch triển khai.

## Phần 3. Các bước thực hiện cho mỗi Case solution

### Solution 1 – Phân đoạn mạng doanh nghiệp

**Trạng thái:** Đã triển khai theo tài liệu gốc.

1. Đăng nhập Horizon, truy cập Project → Network → Networks.
2. Tạo Net_LAN và các subnet SN_Servers, SN_IT, SN_HR, SN_KeToan theo IP Plan.
3. Tạo Net_DMZ với địa chỉ mạng 10.10.190.0/24.
4. Tạo Router R1 và thiết lập External Gateway kết nối với External-Network.
5. Thêm interface của năm subnet vào Router R1.
6. Kiểm tra Network Topology, địa chỉ gateway và khả năng kết nối giữa các mạng.

**Kết quả cần đạt:** Các subnet được tạo đúng địa chỉ, có gateway tương ứng và có khả năng định tuyến thông qua Router R1.

### Solution 2 – Triển khai hệ thống máy chủ và máy trạm

**Trạng thái:** Đã triển khai theo tài liệu gốc.

7. Chuẩn bị Image và Key Pair phục vụ quá trình triển khai máy ảo.
8. Tạo DC01 và FS01 sử dụng Windows Server 2019.
9. Tạo DB_Server và Web_Server sử dụng Ubuntu 24.04.
10. Tạo các máy trạm sử dụng Windows 10, đặt vào subnet của phòng ban tương ứng.
11. Gán Floating IP cho các máy ảo theo IP Plan hiện tại.
12. Kiểm tra trạng thái hoạt động, Fixed IP và Floating IP của từng máy.

**Kết quả cần đạt:** Các máy ảo được triển khai đúng hệ điều hành, vai trò, địa chỉ IP và vùng mạng theo thiết kế.

### Solution 3 – Triển khai dịch vụ dùng chung

#### A. Active Directory và DNS Server

**Trạng thái:** Đã triển khai theo tài liệu gốc.

13. Cấu hình DC01 với địa chỉ IP 10.10.100.11.
14. Cài đặt Active Directory Domain Services và DNS Server thông qua Server Manager.
15. Nâng cấp DC01 thành Domain Controller và cấu hình tên miền nội bộ.
16. Tạo Organizational Unit (OU) tương ứng với các phòng ban Kế toán, Nhân sự và IT.
17. Tạo các nhóm bảo mật:

| Nhóm AD | Phòng ban |
|---|---|
| GG_KeToan | Kế toán |
| GG_HR | Nhân sự |
| GG_IT | Công nghệ thông tin |

18. Tạo và kiểm tra các bản ghi DNS phục vụ hệ thống máy chủ nội bộ.
19. Cấu hình DNS trên các máy trạm trỏ về DC01 và thực hiện Join Domain.

#### B. File Server

**Trạng thái:** Đã triển khai theo tài liệu gốc.

20. Cấu hình FS01 với địa chỉ IP 10.10.100.12 và DNS trỏ về DC01.
21. Thực hiện Join Domain cho FS01.
22. Cài đặt File Server Role trên Windows Server 2019.
23. Tạo thư mục lưu trữ riêng cho từng phòng ban, ví dụ:

```text
D:\Shares\
├── KeToan
├── HR
└── IT
```

24. Cấu hình SMB Share để các máy trạm có thể truy cập tài liệu qua mạng.

#### C. Web Server

**Trạng thái:** Đã triển khai hạ tầng Nginx và PHP, truy cập website bằng trình duyệt thành công. Chức năng Helpdesk đang trong giai đoạn thiết kế.

25. Truy cập Web_Server tại địa chỉ 10.10.190.11.
26. Cài đặt Nginx, PHP-FPM và module hỗ trợ kết nối MariaDB.

```bash
sudo apt update
sudo apt install nginx php-fpm php-mysql -y
```

27. Khởi động và kiểm tra dịch vụ.

```bash
sudo systemctl enable --now nginx
sudo systemctl enable --now php8.3-fpm
sudo systemctl status nginx
```

28. Cấu hình thư mục website và Nginx để xử lý các yêu cầu PHP thông qua PHP-FPM.
29. Xây dựng website nội bộ theo nghiệp vụ hỗ trợ kỹ thuật.

Website được thiết kế phục vụ ba phòng ban, với chức năng chính như sau:

| Phòng ban | Chức năng |
|---|---|
| Kế toán | Gửi yêu cầu hỗ trợ, theo dõi trạng thái và xem kết quả xử lý. |
| Nhân sự | Gửi yêu cầu hỗ trợ, theo dõi trạng thái và xem kết quả xử lý. |
| IT | Tiếp nhận yêu cầu, phân loại sự cố, kiểm tra, sửa chữa và cập nhật kết quả. |

**Quy trình nghiệp vụ website đề xuất**

```text
Phòng Kế toán / Phòng Nhân sự
               │
               ▼
Tạo yêu cầu hỗ trợ kỹ thuật
(Hư máy, lỗi mạng, phần mềm, dịch vụ nội bộ)
               │
               ▼
Website nội bộ (Nginx + PHP)
               │
               ▼
Phòng IT tiếp nhận yêu cầu
               │
               ▼
Kiểm tra và phân loại sự cố
               │
               ▼
Tiến hành sửa chữa, khắc phục
               │
               ▼
Cập nhật kết quả xử lý
               │
               ▼
Phòng ban gửi yêu cầu kiểm tra và xác nhận kết quả
```

Thông tin yêu cầu và các lần cập nhật được lưu tại DB_Server. Website thực hiện kết nối và truy vấn dữ liệu MariaDB; các phòng ban sử dụng trình duyệt để tương tác với website, không truy cập trực tiếp cơ sở dữ liệu.

Mỗi yêu cầu hỗ trợ dự kiến bao gồm thông tin phòng ban gửi yêu cầu, tiêu đề sự cố, thiết bị gặp sự cố, nội dung mô tả, mức độ ưu tiên, thời gian gửi và trạng thái xử lý.

Các trạng thái xử lý được đề xuất gồm:

| Trạng thái | Ý nghĩa |
|---|---|
| Mới | Phòng ban vừa gửi yêu cầu. |
| Đã tiếp nhận | IT đã tiếp nhận yêu cầu. |
| Đang xử lý | IT đang kiểm tra hoặc sửa chữa. |
| Hoàn thành | IT đã xử lý và cập nhật kết quả. |
| Đã đóng | Phòng ban gửi yêu cầu xác nhận kết quả. |

30. Kiểm tra truy cập website từ các máy trạm bằng địa chỉ IP nội bộ:

```text
http://10.10.190.11
```

#### D. Database Server

**Trạng thái:** Đã cài đặt hạ tầng MariaDB theo tài liệu gốc. Cần kiểm thử kết nối và lưu trữ dữ liệu nghiệp vụ Helpdesk.

31. Truy cập DB_Server tại địa chỉ 10.10.100.13.
32. Cài đặt và khởi động MariaDB.

```bash
sudo apt update
sudo apt install mariadb-server -y
sudo systemctl enable --now mariadb
```

33. Tạo cơ sở dữ liệu `novatech_db` và tài khoản ứng dụng `webapp` theo cấu hình triển khai.

```sql
CREATE DATABASE novatech_db
CHARACTER SET utf8mb4;

CREATE USER 'webapp'@'10.10.190.11'
IDENTIFIED BY '<mat_khau>';

GRANT SELECT, INSERT, UPDATE, DELETE
ON novatech_db.*
TO 'webapp'@'10.10.190.11';
```

Thay `<mat_khau>` bằng mật khẩu thực tế khi cấu hình. Không ghi mật khẩu thật vào báo cáo.

34. Cấu hình MariaDB lắng nghe tại địa chỉ nội bộ 10.10.100.13.
35. Kiểm tra kết nối từ Web_Server đến DB_Server.

```bash
nc -zv 10.10.100.13 3306
mysql -h 10.10.100.13 -u webapp -p novatech_db
```

36. Thiết kế các bảng dữ liệu phục vụ website Helpdesk.

| Bảng dữ liệu đề xuất | Chức năng |
|---|---|
| departments | Lưu thông tin phòng ban. |
| users | Lưu thông tin tài khoản và vai trò. |
| tickets | Lưu các yêu cầu hỗ trợ kỹ thuật. |
| ticket_comments | Lưu nội dung trao đổi và ghi chú xử lý. |
| assets | Lưu thông tin máy tính, máy chủ và tài nguyên CNTT. |
| audit_logs | Lưu nhật ký hoạt động trên website. |

**Kết quả cần đạt:** Web_Server có thể kết nối với DB_Server, thực hiện lưu trữ và truy vấn thông tin yêu cầu hỗ trợ kỹ thuật.


### Solution 4 – Thiết lập phân quyền và kiểm soát truy cập

#### A. Phân quyền File Server

**Trạng thái:** Đã triển khai theo tài liệu gốc.

37. Gán các tài khoản miền vào nhóm Active Directory tương ứng.
38. Cấu hình NTFS Permission và SMB Share Permission trên FS01.

| Thư mục | Nhóm được cấp quyền | Quyền |
|---|---|---|
| KeToan | GG_KeToan | Modify |
| HR | GG_HR | Modify |
| IT | GG_IT | Modify hoặc quyền quản trị theo nhiệm vụ |

39. Kiểm tra quyền truy cập thư mục từ các máy trạm của từng phòng ban.

#### B. Phân quyền website nội bộ

**Trạng thái:** Thiết kế đề xuất.

40. Xây dựng chức năng đăng nhập và xác thực tài khoản.
41. Phân quyền website dựa trên ba vai trò tương ứng với ba phòng ban.

| Chức năng | Kế toán | HR | IT |
|---|:---:|:---:|:---:|
| Đăng nhập website | Có | Có | Có |
| Gửi yêu cầu hỗ trợ | Có | Có | — |
| Xem yêu cầu phòng ban mình | Có | Có | Có |
| Xem yêu cầu phòng ban khác | — | — | Có |
| Tiếp nhận yêu cầu | — | — | Có |
| Phân loại sự cố | — | — | Có |
| Cập nhật tiến độ xử lý | — | — | Có |
| Ghi nhận kết quả sửa chữa | — | — | Có |
| Xác nhận kết quả yêu cầu của mình | Có | Có | — |
| Quản lý tài sản CNTT | — | — | Có |
| Xem nhật ký hệ thống | — | — | Có |

42. Kiểm tra quyền truy cập chức năng của từng phòng ban, bảo đảm các tài khoản ngoài phòng IT không thể truy cập chức năng quản trị và xử lý yêu cầu.

Giai đoạn đầu có thể sử dụng tài khoản lưu trong MariaDB. Việc tích hợp xác thực Active Directory thông qua LDAP/LDAPS được giữ làm hướng mở rộng theo thiết kế ban đầu.

#### C. Kiểm soát truy cập mạng

**Trạng thái:** Chưa triển khai lớp lọc Security Group theo yêu cầu Bài 2.

Mô hình hiện tại sử dụng Neutron Router để định tuyến giữa các subnet và sử dụng quyền trên dịch vụ để kiểm soát truy cập dữ liệu.

Các luồng kết nối chính cần phục vụ hệ thống gồm:

| Nguồn | Đích | Dịch vụ |
|---|---|---|
| Máy trạm | Web_Server | HTTP/HTTPS |
| Máy trạm | FS01 | SMB – TCP 445 |
| Máy trạm | DC01 | DNS và Active Directory |
| Web_Server | DB_Server | MariaDB – TCP 3306 |
| Web_Server | DC01 | LDAP/LDAPS khi tích hợp |

Khi bổ sung Security Group, cần xem xét đầy đủ các cổng phục vụ Active Directory và các dịch vụ liên quan để tránh làm gián đoạn quá trình Join Domain và xác thực.

**Lưu ý kỹ thuật:** Việc phân chia Network/Subnet không đồng nghĩa với cách ly hoàn toàn giữa các mạng khi chúng được kết nối qua Router R1. Quyền NTFS và SMB kiểm soát truy cập dữ liệu nhưng không thay thế cơ chế kiểm soát luồng mạng.

**Kết quả cần đạt:** Các phòng ban sử dụng được tài nguyên theo quyền được cấp và bị từ chối khi truy cập tài nguyên ngoài quyền hạn.

## Bảng kiểm thử bắt buộc

| STT | Kiểm thử | Kết quả mong đợi |
|---:|---|---|
| 01 | CLIENT_KETOAN01 truy cập website | Thành công |
| 02 | CLIENT_HR01 truy cập website | Thành công |
| 03 | CLIENT_IT01 truy cập website | Thành công |
| 04 | Máy trạm phân giải tên miền nội bộ | Thành công |
| 05 | Máy trạm Join Domain | Thành công |
| 06 | Kế toán truy cập thư mục KeToan | Thành công |
| 07 | Kế toán truy cập thư mục HR | Bị từ chối |
| 08 | HR truy cập thư mục HR | Thành công |
| 09 | HR truy cập thư mục KeToan | Bị từ chối |
| 10 | Web_Server kết nối DB_Server | Thành công |
| 11 | Kế toán gửi yêu cầu hỗ trợ | Lưu được yêu cầu |
| 12 | HR gửi yêu cầu hỗ trợ | Lưu được yêu cầu |
| 13 | IT tiếp nhận yêu cầu | Hiển thị yêu cầu |
| 14 | IT cập nhật trạng thái xử lý | Dữ liệu được cập nhật |
| 15 | Kế toán xem kết quả xử lý của mình | Thành công |
| 16 | HR xem yêu cầu riêng của Kế toán | Bị từ chối |
| 17 | Kế toán truy cập chức năng quản trị IT | Bị từ chối |
| 18 | Kiểm tra dữ liệu yêu cầu trong MariaDB | Dữ liệu được lưu chính xác |

Các kết quả trên là **kết quả mong đợi**, không phải kết quả nghiệm thu thực tế. Các kiểm thử Helpdesk thực hiện sau khi hoàn thiện ứng dụng.

## Sản phẩm bàn giao

- Sơ đồ kiến trúc mạng NovaTech trên OpenStack.
- Bảng IP Plan của hệ thống máy chủ và máy trạm.
- Cấu hình Network, Subnet, Router và Floating IP.
- Cấu hình Active Directory và DNS Server.
- Cấu hình Windows File Server và quyền truy cập.
- Cấu hình Nginx, PHP-FPM và MariaDB.
- Website nội bộ hỗ trợ tiếp nhận và xử lý sự cố kỹ thuật khi hoàn thiện.
- Bảng kiểm thử và hình ảnh minh chứng triển khai thực tế.
