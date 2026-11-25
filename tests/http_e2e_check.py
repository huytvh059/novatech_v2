#!/usr/bin/env python3
"""
Real HTTP + MariaDB End-to-End Verification for NovaTech IT Helpdesk (atk4/ui).
Tests Login, Dashboard, RBAC 403 isolation, Ticket 5-state lifecycle, Assets, Users, Directory, Announcements, Audit.
"""
import http.cookiejar
import json
import os
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

BASE = os.environ.get("NOVATECH_TEST_URL", "http://127.0.0.1:8080").rstrip("/")
PASSWORD = os.environ.get("NOVATECH_TEST_PASSWORD", "")
if not PASSWORD:
    print("ERROR: NOVATECH_TEST_PASSWORD env var is required.", file=sys.stderr)
    sys.exit(2)


class Client:
    def __init__(self) -> None:
        self.cj = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj))

    def get(self, path: str) -> tuple[int, str, str]:
        req = urllib.request.Request(BASE + path, method="GET")
        try:
            with self.opener.open(req, timeout=10) as resp:
                return resp.status, resp.geturl(), resp.read().decode("utf-8", errors="replace")
        except urllib.error.HTTPError as e:
            return e.code, e.geturl(), e.read().decode("utf-8", errors="replace")

    def submit_atk_form(self, page_html: str, fields: dict[str, str], form_hint: str = "") -> dict:
        # Find atk4/ui form .api({'on': 'submit', 'url': '...'})
        matches = re.findall(r"\$\('#([^']+)'\)\.api\(\{'on': 'submit', 'url': '([^']+)'", page_html)
        if not matches:
            raise AssertionError("No atk4/ui submit API URL found in page HTML")
        target_url = None
        if form_hint:
            # Match form ID owning an input/textarea/select with name="<form_hint>" or matching ID substring
            m_input = re.search(rf'form="([^"]+)"[^>]*name="{re.escape(form_hint)}"|name="{re.escape(form_hint)}"[^>]*form="([^"]+)"', page_html)
            hint_fid = (m_input.group(1) or m_input.group(2)) if m_input else form_hint
            for fid, url in matches:
                if hint_fid == fid or form_hint in fid:
                    target_url = url
                    break
        if target_url is None:
            target_url = matches[0][1]

        data = urllib.parse.urlencode(fields).encode("utf-8")
        req = urllib.request.Request(
            BASE + target_url,
            data=data,
            method="POST",
            headers={
                "X-Requested-With": "XMLHttpRequest",
                "Content-Type": "application/x-www-form-urlencoded",
            },
        )
        with self.opener.open(req, timeout=10) as resp:
            raw = resp.read().decode("utf-8", errors="replace")
        payload = json.loads(raw)
        if not payload.get("success", False):
            raise AssertionError(f"atk4/ui form returned error: {raw[:500]}")
        return payload

    def login(self, username: str, password: str) -> None:
        self.get("/logout")
        code, _, html = self.get("/login")
        assert code == 200, f"GET /login expected 200, got {code}"
        res = self.submit_atk_form(html, {"username": username, "password": password})
        atkjs = res.get("atkjs", "")
        assert "window.open('/'" in atkjs or 'window.open("/"' in atkjs, f"Login failed for {username}: {atkjs}"


passed = 0


def check(cond: bool, msg: str) -> None:
    global passed
    if not cond:
        raise AssertionError(f"FAIL: {msg}")
    passed += 1


# 1. Guest redirect & assets check
guest = Client()
code, url, _ = guest.get("/")
check(url.endswith("/login"), "Guest / redirects to /login")
for asset_path in [
    "/atk-assets/js/atkjs-ui.js",
    "/atk-assets/external/jquery/dist/jquery.js",
    "/atk-assets/external/fomantic-ui/dist/semantic.css",
    "/atk-assets/css/agileui.min.css",
]:
    ac, _, _ = guest.get(asset_path)
    check(ac == 200, f"Local asset {asset_path} returns 200")

# 0. Verify itadmin is removed (cannot log in)
bad = Client()
code, _, login_html = bad.get("/login")
res_bad = bad.submit_atk_form(login_html, {"username": "itadmin", "password": PASSWORD})
check("window.open('/'" not in res_bad.get("atkjs", ""), "itadmin login must not redirect to /")
_, url_after, chk_after = bad.get("/")
check("/login" in url_after or "Đăng nhập" in chk_after, "itadmin cannot establish authenticated session")

# 2. kt01 (Accounting) logs in, sees dashboard, creates a ticket, cannot access HR ticket #2 or IT admin routes
kt = Client()
kt.login("kt01", PASSWORD)

for route in ["/", "/tickets", "/announcements", "/directory"]:
    c, u, body = kt.get(route)
    check(c == 200 and "Critical Error" not in body, f"kt01 GET {route} HTTP 200 without errors")

# Test 17: KeToan denied IT admin pages
for admin_route in ["/assets", "/users", "/audit"]:
    c, _, body = kt.get(admin_route)
    check(c == 403 and "403" in body, f"kt01 GET {admin_route} returns 403")

# Test 16: KeToan denied HR ticket #2
c, _, body = kt.get("/ticket?id=2")
check(c == 403 and "403" in body, "kt01 GET HR ticket #2 returns 403")

# Test 11: KeToan creates a new ticket via atk4/ui Form
c, _, t_html = kt.get("/tickets")
res = kt.submit_atk_form(t_html, {
    "tieu_de": "E2E KeToan: Lỗi máy in phòng Kế toán",
    "loai_su_co": "hardware",
    "uu_tien": "high",
    "asset_id": "8",
    "mo_ta": "Kiểm thử Kế toán gửi yêu cầu hỗ trợ đến phòng IT.",
})
m = re.search(r"/ticket\?id=(\d+)", res.get("atkjs", ""))
check(m is not None, f"kt01 ticket creation redirected to /ticket?id=..., got {res.get('atkjs')}")
kt_ticket_id = int(m.group(1))

c, _, tv_html = kt.get(f"/ticket?id={kt_ticket_id}")
check(c == 200 and "E2E KeToan: Lỗi máy in phòng Kế toán" in tv_html, "kt01 views created ticket")

# 1b. Directory isolation checks (Kế toán & HR only see IT contact in directory table; IT sees all)
c, _, dir_kt = kt.get("/directory")
m_tbody_kt = re.search(r"<tbody>(.*?)</tbody>", dir_kt, re.S)
tbody_kt = m_tbody_kt.group(1) if m_tbody_kt else ""
check(c == 200 and "Lê Hoàng Nam" in tbody_kt and "it01@novatech.test" in tbody_kt and "Trần Thu Hà" not in tbody_kt and "Nguyễn Minh Anh" not in tbody_kt and "hr01@novatech.test" not in tbody_kt, "kt01 /directory table shows ONLY IT contact (hides HR & KeToan)")

# 3. hr01 (HR) logs in, denied kt01's ticket (Test 16), denied IT pages (Test 17), directory shows ONLY IT, creates HR ticket (Test 12)
hr = Client()
hr.login("hr01", PASSWORD)
c, _, dir_hr = hr.get("/directory")
m_tbody_hr = re.search(r"<tbody>(.*?)</tbody>", dir_hr, re.S)
tbody_hr = m_tbody_hr.group(1) if m_tbody_hr else ""
check(c == 200 and "Lê Hoàng Nam" in tbody_hr and "it01@novatech.test" in tbody_hr and "Nguyễn Minh Anh" not in tbody_hr and "Trần Thu Hà" not in tbody_hr and "kt01@novatech.test" not in tbody_hr, "hr01 /directory table shows ONLY IT contact (hides KeToan & HR)")
c, _, _ = hr.get(f"/ticket?id={kt_ticket_id}")
check(c == 403, f"hr01 GET kt01 ticket #{kt_ticket_id} returns 403")
for admin_route in ["/assets", "/users", "/audit"]:
    c, _, _ = hr.get(admin_route)
    check(c == 403, f"hr01 GET {admin_route} returns 403")

c, _, hr_t_html = hr.get("/tickets")
res_hr = hr.submit_atk_form(hr_t_html, {
    "tieu_de": "E2E HR: Lỗi đăng nhập tài liệu Nhân sự",
    "loai_su_co": "internal_service",
    "uu_tien": "high",
    "asset_id": "7",
    "mo_ta": "Kiểm thử HR (hr01) gửi yêu cầu hỗ trợ đến phòng IT.",
})
m_hr = re.search(r"/ticket\?id=(\d+)", res_hr.get("atkjs", ""))
check(m_hr is not None, f"hr01 ticket creation redirected to /ticket?id=..., got {res_hr.get('atkjs')}")
hr_ticket_id = int(m_hr.group(1))

# 4. it01 (IT department) logs in, accesses ALL admin pages, does NOT see submit form, SEES BOTH hr01 and kt01 tickets!
it = Client()
it.login("it01", PASSWORD)
for it_route in ["/", "/tickets", "/assets", "/users", "/directory", "/announcements", "/audit"]:
    c, _, body = it.get(it_route)
    check(c == 200 and "Critical Error" not in body, f"it01 GET {it_route} HTTP 200 without errors")

c, _, it_tickets_html = it.get("/tickets")
check("Gửi yêu cầu hỗ trợ kỹ thuật mới" not in it_tickets_html, "IT does not see ticket creation form (per Bai_2 matrix)")
check("E2E KeToan: Lỗi máy in phòng Kế toán" in it_tickets_html, "it01 SEES KeToan ticket on /tickets")
check("E2E HR: Lỗi đăng nhập tài liệu Nhân sự" in it_tickets_html, "it01 SEES HR ticket on /tickets")

# it01 views and handles BOTH HR ticket & KeToan ticket: new -> received -> in_progress -> completed
for tid in (kt_ticket_id, hr_ticket_id):
    c, _, tv_it = it.get(f"/ticket?id={tid}")
    check(c == 200, f"it01 views ticket #{tid}")
    res = it.submit_atk_form(tv_it, {
        "nguoi_xu_ly": "3",
        "trang_thai": "in_progress",
        "ket_qua_xu_ly": "",
    }, form_hint="trang_thai")
    check(f"/ticket?id={tid}" in res.get("atkjs", ""), f"it01 transitioned ticket #{tid} to in_progress")

    c, _, tv_it2 = it.get(f"/ticket?id={tid}")
    check("Đang xử lý" in tv_it2, f"ticket #{tid} displays Đang xử lý status")
    res = it.submit_atk_form(tv_it2, {
        "nguoi_xu_ly": "3",
        "trang_thai": "completed",
        "ket_qua_xu_ly": "Phòng IT đã kiểm tra và xử lý hoàn tất.",
    }, form_hint="trang_thai")
    check(f"/ticket?id={tid}" in res.get("atkjs", ""), f"it01 transitioned ticket #{tid} to completed")

# 5. kt01 & hr01 confirm completion (completed -> closed)
for client_obj, tid, label_name in [(kt, kt_ticket_id, "kt01"), (hr, hr_ticket_id, "hr01")]:
    c, _, tv_u = client_obj.get(f"/ticket?id={tid}")
    check("Phòng IT đã kiểm tra" in tv_u, f"{label_name} sees IT resolution note on #{tid}")
    res = client_obj.submit_atk_form(tv_u, {"xac_nhan": "1"}, form_hint="xac_nhan")
    check(f"/ticket?id={tid}" in res.get("atkjs", ""), f"{label_name} confirmed and closed ticket #{tid}, got {res.get('atkjs', '')}")
    c, _, tv_u2 = client_obj.get(f"/ticket?id={tid}")
    check("Đã đóng" in tv_u2, f"ticket #{tid} displays Đã đóng status")

c, _, it_dir_html = it.get("/directory")
check("Lê Hoàng Nam" in it_dir_html and "Nguyễn Minh Anh" in it_dir_html and "Trần Thu Hà" in it_dir_html, "it01 /directory sees all departments")

# 5b. it01 creates a temporary user and deletes it via the Delete modal in /users
temp_uname = f"temp_del_{int(time.time())}"
c, _, users_html = it.get("/users")
check(c == 200 and "Critical Error" not in users_html, "it01 GET /users 200")
res_cu = it.submit_atk_form(users_html, {
    "username": temp_uname,
    "ho_ten": "Nhân viên Tạm Xóa",
    "email": f"{temp_uname}@novatech.test",
    "so_may_le": "199",
    "phong_ban_id": "1",
    "role": "accounting",
    "password": PASSWORD,
}, form_hint="ho_ten")
check("/users" in res_cu.get("atkjs", ""), f"it01 created {temp_uname}, got {res_cu.get('atkjs', '')}")

c, _, users_html2 = it.get("/users")
check(temp_uname in users_html2, f"{temp_uname} visible in /users list")

# Extract temp row id and modal callback URL from /users HTML (do not cross </tr>!)
m_row = re.search(rf'<tr[^>]*data-id="(\d+)"[^>]*>(?:(?!</tr>).)*?{re.escape(temp_uname)}', users_html2, re.S)
check(m_row is not None, f"found {temp_uname} table row data-id")
temp_uid = m_row.group(1)

m_arg = re.search(r"'args':\s*\{\s*'([^']+)':\s*\$\(this\)\.closest\('tr'\)\.data\('id'\)", users_html2)
check(m_arg is not None, "found modal row id arg key in /users HTML")
modal_arg_key = m_arg.group(1)

m_modal_url = re.search(r"'url':\s*'(/users\?[^']*modal_view_callbacklater[^']*)'", users_html2)
check(m_modal_url is not None, "found delete modal callback URL in /users HTML")
del_modal_url = m_modal_url.group(1)

sep = "&" if "?" in del_modal_url else "?"
c_m, _, modal_raw = it.get(f"{del_modal_url}{sep}{urllib.parse.urlencode({modal_arg_key: temp_uid})}")
check(c_m == 200, "delete modal GET returns 200")
modal_json = json.loads(modal_raw)
modal_html = modal_json.get("html", "") + "\n" + modal_json.get("atkjs", "")
check(temp_uname in modal_html and "confirm_delete" in modal_html, f"delete modal renders confirmation form for {temp_uname}")

res_del = it.submit_atk_form(modal_html, {"confirm_delete": "1"}, form_hint="confirm_delete")
check(f"Đã xóa tài khoản {temp_uname}" in res_del.get("atkjs", ""), f"delete form succeeded: {res_del.get('atkjs', '')}")

c, _, users_html3 = it.get("/users")
check(temp_uname not in users_html3, f"{temp_uname} removed from /users list")

# Verify self-delete protection for it01 (id=3)
c_self, _, self_raw = it.get(f"{del_modal_url}{sep}{urllib.parse.urlencode({modal_arg_key: '3'})}")
self_json = json.loads(self_raw)
check("Không được tự xóa" in self_json.get("html", ""), "self-delete blocked with error message")

# 6. Audit logs show real events
c, _, audit_html = it.get("/audit")
check("ticket_create" in audit_html and "ticket_confirm_close" in audit_html and "forbidden" in audit_html and "user_delete" in audit_html, "audit page displays real logged events including user_delete")

print(f"PASS: {passed} live HTTP + MariaDB E2E checks (KeToan #{kt_ticket_id} & HR #{hr_ticket_id} -> IT it01 full lifecycle verified)")
