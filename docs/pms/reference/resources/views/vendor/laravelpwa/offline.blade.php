<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#7367f0">
    <title>Connection unavailable | PMS</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f5f5f9; color: #25324b; font-family: Arial, sans-serif; }
        .offline-card { width: min(440px, 100%); padding: 36px; border: 1px solid #e4e6eb; border-radius: 16px; background: #fff; box-shadow: 0 14px 40px rgba(34, 48, 74, .12); text-align: center; }
        .offline-icon { display: grid; place-items: center; width: 64px; height: 64px; margin: 0 auto 20px; border-radius: 50%; background: #eeecff; color: #7367f0; font-size: 30px; }
        h1 { margin: 0 0 10px; font-size: 24px; }
        p { margin: 0 0 24px; color: #6f7890; line-height: 1.55; }
        button { padding: 11px 20px; border: 0; border-radius: 8px; background: #7367f0; color: #fff; cursor: pointer; font-size: 14px; font-weight: 600; }
        button:hover { background: #685dd8; }
    </style>
</head>
<body>
    <main class="offline-card">
        <div class="offline-icon" aria-hidden="true">!</div>
        <h1>Connection unavailable</h1>
        <p>PMS could not reach the server. Check your connection or make sure the local Laravel server is running, then try again.</p>
        <button type="button" onclick="window.location.reload()">Try again</button>
    </main>
</body>
</html>
