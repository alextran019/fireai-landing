"""Chạy production: uvicorn main:app --host 0.0.0.0 --port 8000
Sau đó đăng ký webhook một lần: python set_webhook.py https://<domain>/zalo/webhook
"""
import asyncio
import hmac
import logging

from fastapi import BackgroundTasks, FastAPI, Header, HTTPException, Request

import config
from handler import handle_update

logging.basicConfig(level=logging.INFO)
app = FastAPI()


@app.get("/health")
async def health():
    return {"ok": True}


@app.post("/zalo/webhook")
async def webhook(
    request: Request,
    background: BackgroundTasks,
    x_bot_api_secret_token: str = Header(default=""),
):
    if not hmac.compare_digest(x_bot_api_secret_token, config.ZALO_WEBHOOK_SECRET):
        raise HTTPException(status_code=403)
    body = await request.json()
    background.add_task(handle_update, body)  # trả 200 ngay, xử lý sau
    return {"ok": True}
