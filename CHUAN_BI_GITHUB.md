# 📦 CHUẨN BỊ CODE ĐỂ ĐẨY LÊN GITHUB

## ⚠️ QUAN TRỌNG: COMPILE CSS TRƯỚC KHI PUSH!

File `fireai-landing.scss` cần được compile thành CSS trước khi push lên GitHub.

## 🔧 CÁCH COMPILE CSS

### Cách 1: Sử dụng công cụ online (NHANH NHẤT)

1. Truy cập: **https://www.sassmeister.com/**
2. Mở file `assets/css/fireai-landing.scss` trong Notepad/VS Code
3. Copy **TOÀN BỘ** nội dung (Ctrl+A → Ctrl+C)
4. Paste vào editor bên trái của SassMeister
5. Copy CSS output từ bên phải
6. Tạo file mới: `assets/css/fireai-landing.css`
7. Paste CSS vào file đó và lưu

### Cách 2: Cài đặt Sass và compile

```bash
# Cài đặt Sass
npm install -g sass

# Compile
cd assets/css
sass fireai-landing.scss fireai-landing.css
```

### Cách 3: Sử dụng VS Code Extension

1. Cài extension "Live Sass Compiler"
2. Click "Watch Sass" ở status bar
3. File CSS sẽ tự động được tạo

## ✅ CHECKLIST TRƯỚC KHI PUSH

- [ ] File `assets/css/fireai-landing.css` đã được tạo (compile từ SCSS)
- [ ] File `index.html` ở root
- [ ] File `config/fireai-config.json` đã được điền đầy đủ
- [ ] File `assets/js/fireai-landing.js` tồn tại
- [ ] Ảnh hero đã được upload (hoặc cập nhật URL trong config)
- [ ] Đã test local (mở `index.html` trong browser)

## 📁 CẤU TRÚC FILE SAU KHI CHUẨN BỊ

```
VI_DU_CHUYEN_DOI/
├── index.html                    ✅
├── config/
│   └── fireai-config.json       ✅
├── assets/
│   ├── css/
│   │   ├── fireai-landing.scss  (file nguồn)
│   │   └── fireai-landing.css    ⚠️ CẦN TẠO FILE NÀY!
│   ├── js/
│   │   └── fireai-landing.js     ✅
│   └── images/
│       └── fireai_hero_image.jpg (nếu có)
├── .gitignore                    ✅
├── README.md                     ✅
└── DEPLOY_INSTRUCTIONS.md        ✅
```

## 🚀 SAU KHI COMPILE CSS

Bạn có thể push lên GitHub ngay:

```bash
cd "F:\HOÀNG VIỆT\home_haven_HV_7_12\VI_DU_CHUYEN_DOI"
git init
git add .
git commit -m "Initial commit: FireAI Landing Page"
git remote add origin https://github.com/yourusername/fireai-landing.git
git push -u origin main
```

## 💡 LƯU Ý

- **Netlify có thể compile SCSS tự động**, nhưng tốt nhất nên compile trước
- Nếu không compile CSS, website sẽ không có style
- File CSS phải tồn tại trong repository để website hoạt động đúng

---

**Xem hướng dẫn deploy:** `HUONG_DAN_DEPLOY_GITHUB_NETLIFY.md`

