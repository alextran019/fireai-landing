"""Chạy thử trên máy local không cần domain/HTTPS: python poll.py
(Zalo không cho dùng getUpdates khi đang đặt webhook.)"""
import asyncio
import logging

from handler import bot, handle_update

logging.basicConfig(level=logging.INFO)


async def main() -> None:
    print("Bot:", await bot.get_me())
    while True:
        try:
            data = await bot.get_updates(timeout=30)
        except Exception as e:
            logging.warning("getUpdates lỗi: %s", e)
            await asyncio.sleep(3)
            continue
        result = data.get("result")
        for upd in result if isinstance(result, list) else [data] if result else []:
            asyncio.create_task(handle_update(upd))


asyncio.run(main())
