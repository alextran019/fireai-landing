# Zalo Bot FireAI

Bot Zalo (Zalo Bot Platform) tư vấn sản phẩm FireAI và quy chuẩn PCCC, dùng Claude + tài liệu trong `knowledge/`.

## Chạy
```bash
cd zalo-bot
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env   # điền token
python poll.py         # chạy thử local (long polling)
```
Production (webhook, cần HTTPS):
```bash
uvicorn main:app --host 0.0.0.0 --port 8000
python set_webhook.py https://<domain>/zalo/webhook
```

## Cập nhật kiến thức
Sửa/thêm file `.md` trong `knowledge/`, khởi động lại bot. Tổng tài liệu nhỏ (vài chục nghìn token) nên nhét thẳng vào prompt (có prompt caching). Khi tài liệu lớn hơn nhiều thì chuyển sang RAG (vector search) trong `llm.py`.

## Chưa làm
- Tài liệu PCCC gốc (79 trang, bản scan) mới nạp bảng tóm tắt và mục báo cháy tự động; cần nạp thêm phần bình chữa cháy, họng nước, chữa cháy tự động, xử phạt...
- Lịch sử hội thoại đang lưu trong RAM.
- Chuyển khách cho nhân viên thật (hiện chỉ đưa hotline).

## Deploy 24/7
- Docker: `docker build -t fireai-zalo-bot . && docker run -d --restart unless-stopped --env-file .env fireai-zalo-bot`
- Railway/Render: trỏ vào thư mục `zalo-bot` (Dockerfile), đặt biến môi trường như `.env.example`, chạy dạng Worker (không cần cổng).
