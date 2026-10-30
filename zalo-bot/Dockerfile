FROM python:3.12-slim
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY . .
# Mặc định chạy long polling (không cần domain/HTTPS). Chế độ webhook: đổi CMD thành
# uvicorn main:app --host 0.0.0.0 --port 8000
CMD ["python", "-u", "poll.py"]
