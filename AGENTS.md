# Repository Guidelines

## Project Structure & Module Organization
- `api/` — PHP 8.2 (Apache) backend. `index.php` exposes `/generate`, `/questions`, `/answers`, plus schema bootstrap.  
- `api/study/` — Frontend HTML/JS for要件入力と問題プレビュー (`requirements.html`).  
- `docker-compose.yml` — Dev stack: `api` (php-apache), `db` (MySQL 8), `phpmyadmin`.  
- `send_payloads.py`, `tmp_payload.json` — Helpers for sending sample data to the API (optional).

## Build, Test, and Development Commands
- `docker-compose up -d` — 起動 (API: http://localhost:8080, phpMyAdmin: http://localhost:8082).  
- `docker-compose logs -f api` / `docker-compose logs -f db` — サービスログ確認。  
- `docker-compose exec api bash` — PHPコンテナに入ってファイル確認・簡易デバッグ。  
- `curl -X POST http://localhost:8080/index.php?r=/generate -d '{"genre":"net","problem_type":"tcp","count":3}' -H "Content-Type: application/json"` — 生成APIの手動確認例。

## Coding Style & Naming Conventions
- PHP: PSR-12 近似。2スペースインデント、短い関数で早期リターン重視。  
- JS/HTML: 2スペース、テンプレートリテラルでHTML組み立て。  
- 変数・関数は lowerCamelCase、定数は UPPER_SNAKE。  
- JSONキーは snake_case（API入出力）。

## Testing Guidelines
- 現状自動テストなし。手動確認手順を優先：  
  - `/generate` で問題生成 → `/questions` で保存内容を確認。  
  - フロント画面で「正解を表示」動作・解説表示・回答送信を確認。  
- 追加する場合は PHPUnit を `api/` に導入し、`docker-compose exec api vendor/bin/phpunit` で実行する想定。

## Commit & Pull Request Guidelines
- コミットメッセージ: 英語の命令形を推奨（例: `Add explanation field to questions`）。  
- 1コミット1テーマを意識し、生成物やvendorは除外。  
- PR では目的、主要変更点、確認手順、関連Issueを簡潔に記載。UI変更はスクショ添付。

## Security & Configuration Tips
- `.env` などに DB 資格情報をまとめ、公開リポには含めない。`docker-compose.yml` のデフォルト資格情報はローカル専用。  
- 本番相当では `GEMINI_API_KEY` を環境変数で渡し、`api/index.php` のデフォルトキーは必ず上書きすること。  
- DBはUTF-8（utf8mb4）で統一済み。文字化けを防ぐためクエリでも明示的にUTF-8を期待する。  
- バックアップ: `docker exec <db-container> mysqldump -uappuser -papppass appdb > backup.sql` で取得可能。  
