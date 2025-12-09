# 📦 HƯỚNG DẪN DEPLOY LÊN GITHUB

## ✅ CHECKLIST TRƯỚC KHI PUSH

### 1. Compile CSS (QUAN TRỌNG!)

File `fireai-landing.scss` cần được compile thành CSS:

**Cách 1: Sử dụng Sass (Khuyến nghị)**
```bash
cd assets/css
sass fireai-landing.scss fireai-landing.css
```

**Cách 2: Sử dụng công cụ online**
- Truy cập: https://www.sassmeister.com/
- Copy nội dung `fireai-landing.scss`
- Paste và lấy CSS output
- Lưu vào `fireai-landing.css`

**Cách 3: Nếu không có Sass**
- File CSS đã được compile sẵn (nếu có)
- Hoặc sử dụng file SCSS trực tiếp (Netlify có thể compile tự động)

### 2. Upload ảnh hero

Đặt ảnh hero vào `assets/images/fireai_hero_image.jpg` hoặc cập nhật đường dẫn trong `config/fireai-config.json`.

### 3. Cấu hình Form

Cập nhật Google Form URL và entry IDs trong `config/fireai-config.json`:
```json
"form": {
  "action_url": "https://docs.google.com/forms/d/e/YOUR_FORM_ID/formResponse",
  "entry_name": "entry.XXXXX",
  "entry_phone": "entry.YYYYY",
  "entry_content": "entry.ZZZZZ"
}
```

### 4. Kiểm tra đường dẫn

Đảm bảo tất cả đường dẫn trong `index.html` đúng:
- `assets/css/fireai-landing.css` ✅
- `config/fireai-config.json` ✅
- `assets/js/fireai-landing.js` ✅

## 🚀 PUSH LÊN GITHUB

### Bước 1: Khởi tạo Git
```bash
cd "F:\HOÀNG VIỆT\home_haven_HV_7_12\VI_DU_CHUYEN_DOI"
git init
```

### Bước 2: Thêm file
```bash
git add .
```

### Bước 3: Commit
```bash
git commit -m "Initial commit: FireAI Landing Page"
```

### Bước 4: Kết nối GitHub
```bash
git remote add origin https://github.com/yourusername/fireai-landing.git
```

### Bước 5: Push
```bash
git push -u origin main
```

**Lưu ý:** Nếu dùng branch `master` thay vì `main`:
```bash
git push -u origin master
```

## 📝 LƯU Ý QUAN TRỌNG

1. **File CSS phải tồn tại:** Đảm bảo `assets/css/fireai-landing.css` đã được compile
2. **Ảnh hero:** Upload ảnh vào `assets/images/` hoặc cập nhật URL trong config
3. **Form URL:** Cập nhật Google Form URL trong config
4. **Test local:** Mở `index.html` trong browser để kiểm tra

## 🐛 TROUBLESHOOTING

### CSS không load:
- Kiểm tra file `fireai-landing.css` đã tồn tại chưa
- Compile SCSS thành CSS nếu chưa có

### Config không load:
- Kiểm tra đường dẫn trong `fireai-landing.js`
- Đảm bảo file `config/fireai-config.json` tồn tại

### Ảnh không hiển thị:
- Kiểm tra đường dẫn trong config
- Đảm bảo file ảnh tồn tại trong `assets/images/`

---

**Sau khi push lên GitHub, xem hướng dẫn deploy Netlify trong file `HUONG_DAN_DEPLOY_GITHUB_NETLIFY.md`**

