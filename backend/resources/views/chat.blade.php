<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>シンプルチャット（ストリーミング）</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background-color: #f4f4f4;
        }
        .chat-container {
            width: 90%;
            max-width: 600px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        .messages {
            height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 10px;
            background: #f9f9f9;
        }
        .messages div {
            margin-bottom: 10px;
        }
        .user-message {
            text-align: right;
            color: #333;
        }
        .ai-message {
            text-align: left;
            color: #555;
        }
        form {
            display: flex;
            gap: 10px;
        }
        input[type="text"] {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="messages" id="messages">
            <!-- ストリーミング結果がここに追加される -->
        </div>
        <form id="chat-form">
            @csrf
            <input type="text" id="message" name="message" placeholder="メッセージを入力してください" required>
            <button type="submit">送信</button>
        </form>
    </div>

    <script>
        const form = document.getElementById('chat-form');
        const messages = document.getElementById('messages');
        const csrfToken = '{{ csrf_token() }}';

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const userMessage = document.getElementById('message').value;

            const userDiv = document.createElement('div');
            userDiv.className = 'user-message';
            userDiv.textContent = userMessage;
            messages.appendChild(userDiv);
            messages.scrollTop = messages.scrollHeight;

            const aiDiv = document.createElement('div');
            aiDiv.className = 'ai-message';
            messages.appendChild(aiDiv);
            messages.scrollTop = messages.scrollHeight;

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/stream-response', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);

            xhr.onreadystatechange = function() {
                if (xhr.readyState === XMLHttpRequest.LOADING || xhr.readyState === XMLHttpRequest.DONE) {
                    aiDiv.textContent = xhr.responseText;
                    messages.scrollTop = messages.scrollHeight;
                }
            };

            xhr.onerror = function() {
                aiDiv.textContent += '\n[エラーが発生しました]';
            };

            xhr.send('message=' + encodeURIComponent(userMessage));

            form.reset();
        });
    </script>
</body>
</html>
