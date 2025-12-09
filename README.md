# 🔥 FireAI Landing Page

Landing Page cho Hệ thống Báo cháy Không dây Địa chỉ FireAI.

## 📁 Cấu trúc thư mục

```
fireai-landing/
├── index.html                    # File HTML chính
├── config/
│   └── fireai-config.json       # File cấu hình (chỉnh sửa nội dung ở đây)
├── assets/
│   ├── css/
│   │   ├── fireai-landing.scss  # File SCSS gốc (nếu cần chỉnh sửa)
│   │   └── fireai-landing.css   # File CSS đã compile (sử dụng file này)
│   ├── js/
│   │   └── fireai-landing.js    # File JavaScript
│   └── images/
│       └── fireai_hero_image.jpg # Ảnh hero (cần upload)
├── .gitignore                    # Git ignore file
└── README.md                     # File này
```

## 🚀 Cách sử dụng

### 1. Chỉnh sửa nội dung

Mở file `config/fireai-config.json` và chỉnh sửa các giá trị:
- Hero title, subtitle
- Video ID (YouTube)
- Trust badges
- Features
- FAQ
- Form settings

### 2. Compile CSS (nếu chỉnh sửa SCSS)

Nếu bạn chỉnh sửa file `fireai-landing.scss`, cần compile thành CSS:

```bash
# Cài đặt Sass (nếu chưa có)
npm install -g sass

# Compile SCSS → CSS
sass assets/css/fireai-landing.scss assets/css/fireai-landing.css
```

### 3. Upload ảnh hero

Đặt ảnh hero vào `assets/images/fireai_hero_image.jpg` hoặc cập nhật đường dẫn trong `config/fireai-config.json`.

### 4. Test local

Mở file `index.html` trong browser hoặc chạy local server:

```bash
# Python 3
python -m http.server 8000

# Node.js
npx http-server
```

Truy cập: `http://localhost:8000`

## 📦 Deploy lên GitHub + Netlify

Xem hướng dẫn chi tiết trong file `HUONG_DAN_DEPLOY_GITHUB_NETLIFY.md` ở thư mục gốc.

### Quick start:

1. **Push lên GitHub:**
   ```bash
   git init
   git add .
   git commit -m "Initial commit: FireAI Landing Page"
   git remote add origin https://github.com/yourusername/fireai-landing.git
   git push -u origin main
   ```

2. **Deploy lên Netlify:**
   - Vào https://www.netlify.com/
   - "Add new site" → "Import from Git"
   - Chọn repository
   - Deploy!

## ⚙️ Cấu hình Form

Form mặc định submit đến Google Forms. Để sử dụng:

1. Tạo Google Form
2. Lấy Form ID từ URL
3. Cập nhật trong `config/fireai-config.json`:
   ```json
   "form": {
     "action_url": "https://docs.google.com/forms/d/e/YOUR_FORM_ID/formResponse",
     "entry_name": "entry.XXXXX",
     "entry_phone": "entry.YYYYY",
     "entry_content": "entry.ZZZZZ"
   }
   ```

## 📝 Lưu ý

- File `index.html` phải ở root repository
- Đường dẫn file phải tương đối (không có `/` ở đầu)
- Đảm bảo file CSS đã được compile trước khi deploy
- Upload ảnh hero vào thư mục `assets/images/`

## 🐛 Troubleshooting

- **CSS không load:** Kiểm tra đường dẫn trong `index.html`
- **Config không load:** Kiểm tra đường dẫn trong `fireai-landing.js`
- **Ảnh không hiển thị:** Kiểm tra đường dẫn trong config và đảm bảo file tồn tại

## 📄 License

MIT License

---

**Chúc bạn deploy thành công! 🚀**
