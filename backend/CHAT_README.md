## シンプル Prism チャット

PrismPHP と OpenAI を使用したシンプルなチャットアプリケーション。

### 機能
- **チャット機能**: OpenAI GPT-4o-mini モデルとの会話
- **履歴管理**: セッションベースでチャット履歴を保持
- **履歴クリア**: ワンクリックで会話履歴をリセット

### セットアップ
1. 依存関係をインストール
   ```bash
   composer install
   ```

2. 環境変数を設定
   ```bash
   cp .env.example .env
   ```
   `.env` ファイルで `OPENAI_API_KEY` を設定

3. アプリケーションを起動
   ```bash
   php artisan serve
   ```

4. ブラウザで `http://localhost:8000/chat` にアクセス

### 使用方法
1. メッセージ入力欄にテキストを入力
2. 「送信」ボタンをクリック
3. AIからの応答を確認
4. 必要に応じて「履歴クリア」で会話をリセット

### 技術仕様
- **Backend**: Laravel 11 + PHP 8.2+
- **AI**: Prism PHP + OpenAI GPT-4o-mini
- **Frontend**: HTML + CSS + JavaScript
- **Storage**: セッションベース
