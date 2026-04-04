<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Stream Test</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 24px; }
        .row { margin-bottom: 12px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; }
        input, textarea { width: 100%; padding: 10px; font-size: 14px; }
        button { padding: 10px 16px; font-size: 14px; cursor: pointer; }
        #output { white-space: pre-wrap; border: 1px solid #ddd; padding: 12px; min-height: 120px; }
    </style>
    <script>
        async function startStream() {
            const token = document.getElementById('token').value.trim();
            const message = document.getElementById('message').value.trim();
            const out = document.getElementById('output');
            out.textContent = '';
            if (!token || !message) {
                out.textContent = 'Provide token and message';
                return;
            }
            try {
                const resp = await fetch('/api/v1/ai/chat/stream', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ message })
                });
                if (!resp.ok) {
                    out.textContent = 'HTTP ' + resp.status;
                    const t = await resp.text();
                    out.textContent += '\n' + t;
                    return;
                }
                const reader = resp.body.getReader();
                const decoder = new TextDecoder();
                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;
                    const chunk = decoder.decode(value, { stream: true });
                    out.textContent += chunk;
                }
            } catch (e) {
                out.textContent = 'Error: ' + e.message;
            }
        }
    </script>
    </head>
<body>
    <h1>AI Stream Test</h1>
    <div class="row">
        <label for="token">Bearer Token</label>
        <input id="token" placeholder="Bearer token from auth:issue-test-token">
    </div>
    <div class="row">
        <label for="message">Message</label>
        <textarea id="message" rows="3">Stream a short daily mental health tip</textarea>
    </div>
    <div class="row">
        <button onclick="startStream()">Start Stream</button>
    </div>
    <h3>Output</h3>
    <div id="output"></div>
</body>
</html>
