# ⚡ QUICK START - CHUẨN BỊ CODE ĐỂ PUSH LÊN GITHUB

## ✅ CHECKLIST NHANH

### 1. Compile CSS (QUAN TRỌNG!)

File `assets/css/fireai-landing.scss` cần được compile thành CSS:

**Cách nhanh nhất - Sử dụng công cụ online:**
1. Truy cập: https://www.sassmeister.com/ hoặc https://www.sassme.com/
2. Mở file `assets/css/fireai-landing.scss`
3. Copy toàn bộ nội dung
4. Paste vào editor bên trái
5. Copy CSS output từ bên phải
6. Tạo file mới `assets/css/fireai-landing.css`
7. Paste CSS vào file đó

**Hoặc cài Sass và compile:**
```bash
npm install -g sass
cd assets/css
sass fireai-landing.scss fireai-landing.css
```

### 2. Upload ảnh hero (nếu có)

Đặt ảnh vào `assets/images/fireai_hero_image.jpg` hoặc cập nhật URL trong `config/fireai-config.json`.

### 3. Cấu hình Form (nếu cần)

Cập nhật Google Form URL trong `config/fireai-config.json`.

### 4. Kiểm tra file

Đảm bảo có đầy đủ:
- ✅ `index.html`
- ✅ `config/fireai-config.json`
- ✅ `assets/css/fireai-landing.css` (QUAN TRỌNG!)
- ✅ `assets/js/fireai-landing.js`
- ✅ `assets/images/fireai_hero_image.jpg` (nếu có)

## 🚀 PUSH LÊN GITHUB

```bash
cd "F:\HOÀNG VIỆT\home_haven_HV_7_12\VI_DU_CHUYEN_DOI"
git init
git add .
git commit -m "Initial commit: FireAI Landing Page"
git remote add origin https://github.com/yourusername/fireai-landing.git
git push -u origin main
```

## 📝 LƯU Ý

- **File CSS phải tồn tại** trước khi push
- Nếu không có ảnh hero, website vẫn hoạt động (ảnh sẽ không hiển thị)
- Form có thể cấu hình sau khi deploy

---

**Xem chi tiết:** `DEPLOY_INSTRUCTIONS.md` hoặc `HUONG_DAN_DEPLOY_GITHUB_NETLIFY.md`

