import os
from pathlib import Path

from dotenv import load_dotenv

load_dotenv()

BASE_DIR = Path(__file__).parent
KNOWLEDGE_DIR = BASE_DIR / "knowledge"

ZALO_BOT_TOKEN = os.getenv("ZALO_BOT_TOKEN", "")
ZALO_WEBHOOK_SECRET = os.getenv("ZALO_WEBHOOK_SECRET", "")
# LLM_PROVIDER: "gemini" (có gói miễn phí) hoặc "anthropic" (Claude, tính phí)
LLM_PROVIDER = os.getenv("LLM_PROVIDER", "gemini").lower()
GEMINI_API_KEY = os.getenv("GEMINI_API_KEY", "")
GEMINI_MODEL = os.getenv("GEMINI_MODEL", "gemini-2.5-flash")
ANTHROPIC_API_KEY = os.getenv("ANTHROPIC_API_KEY", "")
CLAUDE_MODEL = os.getenv("CLAUDE_MODEL", "claude-sonnet-5-5")
HOTLINE = os.getenv("HOTLINE", "0385.908.114")

# Số lượt hội thoại gần nhất giữ lại cho mỗi khách
HISTORY_TURNS = 10


def llm_key_missing() -> str | None:
    """Trả về tên biến môi trường còn thiếu, hoặc None nếu đủ."""
    if LLM_PROVIDER == "anthropic":
        return None if ANTHROPIC_API_KEY else "ANTHROPIC_API_KEY"
    return None if GEMINI_API_KEY else "GEMINI_API_KEY"
