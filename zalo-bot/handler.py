import logging
from collections import OrderedDict, defaultdict

import config
import llm
from zalo import ZaloBot, parse_update

log = logging.getLogger("bot")
bot = ZaloBot()

# Bộ nhớ tạm trong tiến trình; chuyển sang Redis/Postgres khi chạy nhiều instance
_history: dict[str, list[dict]] = defaultdict(list)
_seen: OrderedDict[str, None] = OrderedDict()

FALLBACK = f"Xin lỗi, hệ thống đang bận. Anh/chị vui lòng liên hệ hotline {config.HOTLINE} để được hỗ trợ ngay ạ."


def _is_duplicate(message_id: str) -> bool:
    if not message_id:
        return False
    if message_id in _seen:
        return True
    _seen[message_id] = None
    if len(_seen) > 2000:
        _seen.popitem(last=False)
    return False


async def handle_update(body: dict) -> None:
    parsed = parse_update(body)
    if not parsed:
        return
    chat_id, message_id, text = parsed
    if _is_duplicate(message_id):
        return

    hist = _history[chat_id]
    hist.append({"role": "user", "content": text})
    del hist[: -config.HISTORY_TURNS * 2]
    # API yêu cầu tin đầu tiên là của user
    while hist and hist[0]["role"] != "user":
        hist.pop(0)

    try:
        reply = await llm.answer(hist)
    except Exception:
        log.exception("LLM error")
        hist.pop()
        reply = FALLBACK
    else:
        hist.append({"role": "assistant", "content": reply})

    try:
        await bot.send_message(chat_id, reply)
    except Exception:
        log.exception("Zalo send error")
