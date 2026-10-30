"""Client mỏng cho Zalo Bot Platform.

Cấu trúc URL/field dưới đây viết theo tài liệu Zalo Bot Platform (bot.zapps.me/docs);
nếu Zalo đổi API thì chỉ cần sửa file này.
"""
import httpx

import config

API_BASE = "https://bot-api.zaloplatforms.com"
# Zalo giới hạn độ dài một tin nhắn văn bản
MAX_LEN = 2000


class ZaloBot:
    def __init__(self, token: str = config.ZALO_BOT_TOKEN):
        self._url = f"{API_BASE}/bot{token}"
        self._http = httpx.AsyncClient(timeout=40)

    async def _call(self, method: str, payload: dict | None = None) -> dict:
        r = await self._http.post(f"{self._url}/{method}", json=payload or {})
        r.raise_for_status()
        return r.json()

    async def get_me(self) -> dict:
        return await self._call("getMe")

    async def set_webhook(self, url: str, secret_token: str) -> dict:
        return await self._call("setWebhook", {"url": url, "secret_token": secret_token})

    async def get_updates(self, timeout: int = 30) -> dict:
        return await self._call("getUpdates", {"timeout": timeout})

    async def send_message(self, chat_id: str, text: str) -> None:
        for i in range(0, len(text), MAX_LEN):
            await self._call("sendMessage", {"chat_id": chat_id, "text": text[i : i + MAX_LEN]})


def parse_update(body: dict) -> tuple[str, str, str] | None:
    """Trả về (chat_id, message_id, text) nếu là tin nhắn văn bản của người dùng, ngược lại None."""
    result = body.get("result", body)
    msg = result.get("message")
    if not msg or not msg.get("text"):
        return None
    if (msg.get("from") or {}).get("is_bot"):
        return None
    chat_id = (msg.get("chat") or {}).get("id") or (msg.get("from") or {}).get("id")
    if not chat_id:
        return None
    return str(chat_id), str(msg.get("message_id", "")), msg["text"].strip()
