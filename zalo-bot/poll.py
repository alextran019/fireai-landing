"""Chạy thử trên máy local không cần domain/HTTPS: python poll.py
(Zalo không cho dùng getUpdates khi đang đặt webhook.)"""
import asyncio
import logging
import sys
import time

import config
from handler import bot, handle_update

logging.basicConfig(level=logging.INFO)
logging.getLogger("httpx").setLevel(logging.WARNING)


async def main() -> None:
    if not config.ZALO_BOT_TOKEN:
        sys.exit("Thiếu ZALO_BOT_TOKEN: kiểm tra file .env (phải tên đúng là .env, cùng thư mục với poll.py).")
    missing = config.llm_key_missing()
    if missing:
        sys.exit(f"Thiếu {missing} trong file .env.")
    print("Bot:", await bot.get_me())
    print("Đang chờ tin nhắn... (Ctrl+C để dừng)")
    while True:
        started = time.monotonic()
        try:
            data = await bot.get_updates(timeout=30)
        except Exception as e:
            logging.warning("getUpdates lỗi: %s", e)
            await asyncio.sleep(5)
            continue
        result = data.get("result")
        updates = result if isinstance(result, list) else [data] if result else []
        for upd in updates:
            asyncio.create_task(handle_update(upd))
        if not updates and time.monotonic() - started < 2:
            # Trả về quá nhanh mà không có tin: tránh gọi dồn gây lỗi 429
            logging.warning("getUpdates trả về ngay: %s", str(data)[:200])
            await asyncio.sleep(5)


asyncio.run(main())
