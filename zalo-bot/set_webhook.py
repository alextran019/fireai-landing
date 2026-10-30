"""python set_webhook.py https://your-domain/zalo/webhook"""
import asyncio
import sys

import config
from zalo import ZaloBot


async def main(url: str) -> None:
    print(await ZaloBot().set_webhook(url, config.ZALO_WEBHOOK_SECRET))


asyncio.run(main(sys.argv[1]))
