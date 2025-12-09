# HƯỚNG DẪN COMPILE CSS TỪ SCSS

File `fireai-landing.scss` cần được compile thành CSS trước khi deploy.

## Cách 1: Sử dụng Sass Compiler (Khuyến nghị)

### Cài đặt Sass:

**Windows/Mac/Linux:**
```bash
npm install -g sass
```

### Compile:
```bash
cd assets/css
sass fireai-landing.scss fireai-landing.css
```

## Cách 2: Sử dụng công cụ online

1. Truy cập: https://www.sassmeister.com/
2. Copy nội dung file `fireai-landing.scss`
3. Paste vào editor bên trái
4. Copy CSS output từ bên phải
5. Paste vào file `fireai-landing.css`

## Cách 3: Sử dụng VS Code Extension

1. Cài extension "Live Sass Compiler"
2. Click "Watch Sass" ở thanh status bar
3. File CSS sẽ tự động được tạo

## Lưu ý

- File CSS đã được compile sẵn trong thư mục `assets/css/`
- Nếu bạn chỉnh sửa SCSS, cần compile lại
- Đảm bảo file `fireai-landing.css` tồn tại trước khi deploy

