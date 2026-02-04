"""
Utility to POST questions and answers JSON to the PHP API.

Usage examples:
  python send_payloads.py --questions questions.json
  python send_payloads.py --answers answers.json
  python send_payloads.py --questions questions.json --answers answers.json --base "http://localhost:8080/index.php?r="

The API expects:
- /questions : array of question objects (question_id, domain_name, topic_name, title, stem, choices[choice_id, choice_label, choice_text])
- /answers   : array of answer objects (user_id, question_id, selected_label, elapsed_ms optional)
"""

import argparse
import json
import sys
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any


def post_json(url: str, payload: Any) -> dict:
    data = json.dumps(payload, ensure_ascii=False).encode("utf-8")
    req = urllib.request.Request(
        url,
        data=data,
        headers={"Content-Type": "application/json"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req) as resp:
            body = resp.read()
            text = body.decode("utf-8")
            try:
                return json.loads(text)
            except json.JSONDecodeError:
                raise RuntimeError(f"Non-JSON response: {text}")
    except urllib.error.HTTPError as e:
        detail = e.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"HTTP {e.code}: {detail}") from e
    except urllib.error.URLError as e:
        raise RuntimeError(f"Request failed: {e}") from e


def load_json(path: Path) -> Any:
    text = path.read_text(encoding="utf-8")
    return json.loads(text)


def main() -> int:
    parser = argparse.ArgumentParser(description="POST questions/answers JSON to the API.")
    parser.add_argument("--base", default="http://localhost:8080/index.php?r=", help="Base URL (prefix) for API routes")
    parser.add_argument("--questions", type=Path, help="Path to questions JSON file")
    parser.add_argument("--answers", type=Path, help="Path to answers JSON file")
    args = parser.parse_args()

    if not args.questions and not args.answers:
        parser.error("at least one of --questions or --answers is required")

    if args.questions:
        payload = load_json(args.questions)
        if not isinstance(payload, list):
            raise SystemExit("--questions payload must be a JSON array")
        res = post_json(f"{args.base}/questions", payload)
        print("questions response:", res)

    if args.answers:
        payload = load_json(args.answers)
        if not isinstance(payload, list):
            raise SystemExit("--answers payload must be a JSON array")
        res = post_json(f"{args.base}/answers", payload)
        print("answers response:", res)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
