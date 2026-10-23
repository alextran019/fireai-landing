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

## Phần 4: Báo khách mới về điện thoại (Telegram)

Mỗi khi có khách gửi form website hoặc từ quảng cáo, nhóm Telegram của đội Sale nhận ngay một tin nhắn: tên, số điện thoại, nguồn, chiến dịch, nội dung và nút mở thẳng hồ sơ khách. Miễn phí, không giới hạn số tin.

Khách do Sale tự nhập (hotline) và khách cũ nhập lại **không** gửi thông báo.

### Bước 1: Tạo bot (chỉ admin làm, 1 lần)
1. Cài **Telegram** trên điện thoại và đăng nhập.
2. Ô tìm kiếm → gõ **`@BotFather`** → chọn tài khoản có dấu tick xanh → bấm **Start**.
3. Gõ **`/newbot`** rồi gửi.
4. BotFather hỏi tên: gõ **`FireAI CRM`**.
5. BotFather hỏi username: gõ một tên **kết thúc bằng `bot`**, VD **`fireai_crm_bot`** (nếu báo trùng thì thêm số: `fireai_crm2026_bot`).
6. BotFather trả về một dòng dạng `123456789:AAH...` → đây là **Bot Token**. Bấm giữ để sao chép. **Không gửi token này cho người ngoài.**

### Bước 2: Tạo nhóm cho đội Sale
1. Telegram → biểu tượng bút chì → **Nhóm mới** (New Group).
2. Thêm các nhân viên Sale **và bot vừa tạo** (tìm theo username, VD `fireai_crm_bot`).
3. Đặt tên nhóm, VD **"FireAI – Khách mới"** → Tạo.
4. Trong nhóm, gửi tin nhắn **`/start`**.

### Bước 3: Kết nối với CRM
1. WordPress → **CRM → Cài đặt**.
2. Dán Bot Token vào ô **Telegram Bot Token** → bấm **Lưu cài đặt**.
3. Bấm nút **Tìm Chat ID** (hiện ra sau khi lưu token). Thông báo "Đã kết nối với "FireAI – Khách mới"" là được.
4. Bấm **Gửi tin nhắn thử** → nhóm Telegram nhận tin "✅ Kết nối thành công!".

### Bước 4: Trên điện thoại của mỗi Sale
- Vào nhóm → bấm tên nhóm → đảm bảo **Thông báo đang bật** (không tắt tiếng).
- Lần đầu bấm **"Mở trong CRM"** sẽ phải đăng nhập tài khoản Sale trên trình duyệt điện thoại; các lần sau vào thẳng.

**Báo qua email (tùy chọn):** điền email vào ô **Email nhận thông báo** trong Cài đặt (nhiều email cách nhau bằng dấu phẩy).

## Phần 5: Nhập khách gọi hotline / nhắn Zalo qua Telegram

Người cầm máy hotline hoặc Zalo **không cần mở CRM**. Nói chuyện xong, chỉ cần gõ 1 dòng vào nhóm Telegram Sale:

| Trường hợp | Gõ vào nhóm |
|---|---|
| Khách gọi hotline | `/kh 0912345678 Anh Nam, hỏi báo cháy nhà xưởng 2000m2` |
| Khách nhắn Zalo | `/zalo 0912345678 Chị Lan, cần báo giá nhà 3 tầng` |
| Xem hướng dẫn | `/help` |

Quy tắc: **số điện thoại** → **tên khách** → **dấu phẩy** → **nội dung**. Bot trả lời ngay "✅ Đã lưu khách" kèm nút mở CRM, và **cảnh báo nếu số đã có trong CRM**.

Nhắn riêng cho bot (không phải trong nhóm) thì không cần gõ `/kh`, chỉ cần `0912345678 Anh Nam, nội dung` (thêm chữ `zalo` ở đầu nếu là khách Zalo). Chỉ thành viên nhóm Sale mới dùng được bot.

### Bật (admin làm 1 lần)
1. Đã làm xong Phần 4 (bot đã gửi tin nhắn thử thành công).
2. **CRM → Cài đặt** → mục **Nhập khách qua Telegram** → bấm **Bật nhập khách qua Telegram**.
3. Thấy nhãn xanh **"Đang hoạt động"** là xong. Thử gõ `/help` trong nhóm.

> Nếu hiện cảnh báo đỏ lỗi 401/403: plugin All-In-One Security đang chặn. Vào **WP Security → Tường lửa (Firewall)** → tắt tùy chọn chặn REST API cho khách chưa đăng nhập, rồi bấm Tắt → Bật lại.

### Khách tự giao cho đúng Sale
Mỗi Sale đăng nhập CRM → **Hồ sơ** (menu trái) → ô **Username Telegram** → điền username Telegram của mình (Telegram → Cài đặt → Username) → **Cập nhật hồ sơ**.
Từ đó khách Sale nào nhập qua bot sẽ tự giao cho Sale đó.

## Phần 6: Khách bấm nút Gọi / Chat Zalo trên website

Khi khách bấm **Gọi ngay** hoặc **Chat Zalo**, website không biết được số điện thoại của khách (điện thoại và Zalo không cho phép), nhưng biết khách **đến từ quảng cáo nào**. CRM sẽ:

1. **Báo vào nhóm Telegram** ngay lúc khách bấm: "📞 Có khách vừa bấm GỌI HOTLINE – Nguồn: Facebook · npp-mien-nam · M1A".
2. **Tự gắn chiến dịch**: nếu trong 30 phút sau, người cầm máy gõ `/kh` (hoặc `/zalo`), khách đó được ghi luôn chiến dịch quảng cáo của lượt bấm.
3. **Đếm lượt bấm** mỗi ngày, hiện ở đầu trang **CRM → Khách hàng**.

Tính năng bật sẵn. Muốn tắt báo Telegram cho lượt bấm: **CRM → Cài đặt** → bỏ tích **"Báo vào nhóm Telegram mỗi khi có khách bấm"**.

## Phần 7: Nhận khách từ quảng cáo (Facebook / Google / TikTok)

Khách điền form trên website từ link quảng cáo được tự nhận nguồn nhờ mã `utm_source`, `fbclid`, `gclid` trên đường dẫn.

Khách điền **form ngay trên Facebook / Google (Lead Ads)** cần một dịch vụ trung gian như **Zapier** hoặc **Make**:
1. Vào **CRM → Cài đặt**, sao chép địa chỉ trong mục **Nhận khách từ quảng cáo**.
2. Trong Zapier/Make: chọn trigger "Facebook Lead Ads – New Lead", action "Webhooks – POST".
3. Dán địa chỉ đã sao chép, gửi các trường `name`, `phone`, `email`, `source` (ghi `facebook`), `campaign`.

> Nếu Zapier báo lỗi 401/403: vào **WP Security (All-In-One Security) → Tường lửa**, kiểm tra tùy chọn chặn REST API và cho phép `fireai-crm`.

---

## Cập nhật plugin lên bản mới

Dữ liệu khách hàng, ghi chú và cài đặt **được giữ nguyên** khi cập nhật.

1. Tải file `fireai-crm.zip` bản mới về máy. **Không bấm đúp / giải nén** (nếu lỡ giải nén: chuột phải vào thư mục `fireai-crm` → Send to → Compressed (zipped) folder).
2. WordPress → **Plugin** → dòng **FireAI CRM** → bấm **Vô hiệu hóa**.
3. DirectAdmin → **File Manager** → `domains/fireai.com.vn/public_html/wp-content/plugins`.
4. Chuột phải vào **thư mục `fireai-crm`** (bản cũ) → **Delete**.
5. **NEW → Upload files** → chọn `fireai-crm.zip` mới → chờ 100% → đóng cửa sổ → bấm làm mới ↻.
6. Chuột phải `fireai-crm.zip` → **Extract** → giữ nguyên đường dẫn `/plugins`.
7. Chuột phải `fireai-crm.zip` → **Delete** (chỉ xóa file .zip).
8. WordPress → **Plugin** → **FireAI CRM** → **Kích hoạt**.

## Gỡ cài đặt
Vô hiệu hóa plugin **không xóa dữ liệu**. Dữ liệu khách hàng vẫn còn nguyên trong database và hiện lại khi kích hoạt lại.
