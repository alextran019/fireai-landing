# FireAI CRM – Hướng dẫn cài đặt và sử dụng

Plugin WordPress để đội Sale quản lý khách hàng đổ về từ website, quảng cáo và hotline.
Dữ liệu nằm ngay trong database của website (các bảng `tend_crm_leads`, `tend_crm_notes`), không đụng tới dữ liệu hiện có.

---

## Phần 1: Cài đặt (làm 1 lần, khoảng 10 phút)

### Bước 0: Sao lưu website (bắt buộc)
1. Vào trang quản trị hosting DirectAdmin.
2. Gõ `backup` vào ô **Elements filter**, chọn **Create/Restore Backups**.
3. Tích chọn tất cả rồi bấm **Create Backup**. Chờ có email báo xong.

### Bước 1: Tải file plugin về máy
1. Mở: https://github.com/alextran019/fireai-landing/blob/claude/desktop-claude-connection-1w9a6c/crm/fireai-crm.zip
2. Bấm nút **Download raw file** (biểu tượng mũi tên tải xuống) ở góc phải.
3. File `fireai-crm.zip` sẽ nằm trong thư mục **Downloads**. **Không giải nén.**

### Bước 2: Tải plugin lên WordPress
1. Vào https://fireai.com.vn/wp-admin/plugin-install.php và đăng nhập bằng tài khoản admin.
   - Hoặc: vào menu **Plugin**, **cuộn lên đầu trang**, bấm nút **Thêm plugin mới** cạnh chữ "Plugin".
2. Bấm nút **Tải plugin lên** ở đầu trang.
3. Bấm **Chọn tệp**, chọn file `fireai-crm.zip` vừa tải.
4. Bấm **Cài đặt ngay**, rồi bấm **Kích hoạt plugin**.
5. Menu bên trái xuất hiện mục **CRM**.

> **Nếu không thấy nút "Thêm plugin mới" hoặc "Tải plugin lên":** website có thể đang khóa cài plugin.
> Cách thay thế qua hosting:
> 1. DirectAdmin → gõ `file` vào Elements filter → **File Manager**.
> 2. Mở thư mục `domains/fireai.com.vn/public_html/wp-content/plugins` (hoặc `public_html/wp-content/plugins`).
> 3. Bấm **Upload**, chọn `fireai-crm.zip`.
> 4. Chuột phải vào file vừa tải lên → **Extract**.
> 5. Quay lại WordPress → **Plugin** → tìm **FireAI CRM** → bấm **Kích hoạt**.

### Bước 3: Nhập khách cũ
1. Vào **CRM → Cài đặt**.
2. Ở mục **Nhập khách cũ từ form website**, bấm **Nhập khách cũ**.
3. Thông báo "Đã nhập X khách hàng" là xong. Bấm nhiều lần cũng không bị trùng.

### Bước 4: Tạo tài khoản cho nhân viên Sale
1. Vào **Tài khoản → Thêm mới**.
2. Điền tên đăng nhập, email, mật khẩu.
3. Ở ô **Vai trò**, chọn **Nhân viên Sale (CRM)**.
4. Bấm **Thêm người dùng mới**.

Nhân viên Sale đăng nhập tại `https://fireai.com.vn/wp-admin` sẽ vào thẳng CRM, không thấy và không sửa được nội dung website.

### Bước 5: Kiểm tra
1. Mở website ở cửa sổ ẩn danh, điền thử form liên hệ.
2. Vào **CRM → Khách hàng**: khách vừa gửi phải hiện ở dòng đầu tiên.

---

## Phần 2: Sử dụng hằng ngày (cho Sale)

| Việc cần làm | Cách làm |
|---|---|
| Xem khách mới | **CRM → Khách hàng**, bấm tab **Mới** |
| Tìm khách | Gõ tên hoặc số điện thoại vào ô tìm kiếm, bấm **Lọc** |
| Lọc theo nguồn / ngày | Chọn **Nguồn**, chọn ngày **Từ … đến …**, bấm **Lọc** |
| Đổi trạng thái nhanh | Chọn trạng thái mới ngay trên dòng của khách, tự lưu |
| Khách gọi hotline | **CRM → Thêm khách (Hotline)**. Gõ số điện thoại: nếu số đã có, hệ thống báo ngay |
| Ghi lại cuộc gọi | Bấm tên khách → khung **Lịch sử chăm sóc** → gõ ghi chú → **Thêm ghi chú** |
| Nhận khách chưa ai phụ trách | Bấm tên khách → tích **Nhận khách này** → **Cập nhật** |

Nhãn đỏ **Trùng SĐT** nghĩa là số đó đã liên hệ nhiều lần. Bấm vào để xem tất cả.

**Trạng thái:** Mới → Đang liên hệ → Tiềm năng → Đã chốt / Không thành công / Sai số – Rác.

## Phần 3: Dành cho Admin

- **Xuất Excel:** nút **Xuất Excel** cạnh tiêu đề trang Khách hàng (xuất đúng theo bộ lọc đang chọn).
- **Giao khách cho Sale:** mở khách → chọn **Người phụ trách** → **Cập nhật**.
- **Sale chỉ thấy khách của mình:** CRM → Cài đặt → tích ô tương ứng.
- **Xóa khách:** mở khách → link **Xóa khách hàng** ở cuối trang.

## Phần 4: Nhận khách từ quảng cáo (Facebook / Google / TikTok)

Khách điền form trên website từ link quảng cáo được tự nhận nguồn nhờ mã `utm_source`, `fbclid`, `gclid` trên đường dẫn.

Khách điền **form ngay trên Facebook / Google (Lead Ads)** cần một dịch vụ trung gian như **Zapier** hoặc **Make**:
1. Vào **CRM → Cài đặt**, sao chép địa chỉ trong mục **Nhận khách từ quảng cáo**.
2. Trong Zapier/Make: chọn trigger "Facebook Lead Ads – New Lead", action "Webhooks – POST".
3. Dán địa chỉ đã sao chép, gửi các trường `name`, `phone`, `email`, `source` (ghi `facebook`), `campaign`.

> Nếu Zapier báo lỗi 401/403: vào **WP Security (All-In-One Security) → Tường lửa**, kiểm tra tùy chọn chặn REST API và cho phép `fireai-crm`.

---

## Gỡ cài đặt
Vô hiệu hóa plugin **không xóa dữ liệu**. Dữ liệu khách hàng vẫn còn nguyên trong database và hiện lại khi kích hoạt lại.
