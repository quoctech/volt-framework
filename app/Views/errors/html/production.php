<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>Volt — Server Error</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f4f4f5; color: #18181b; height: 100vh;
            display: flex; align-items: center; justify-content: center;
        }
        .card {
            background: #fff; border-radius: 12px; padding: 3rem 4rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.1); text-align: center; max-width: 480px;
        }
        .code { font-size: 4rem; font-weight: 700; color: #ef4444; line-height: 1; margin-bottom: .5rem; }
        h1 { font-size: 1.25rem; font-weight: 600; margin-bottom: .75rem; }
        p { color: #71717a; font-size: .875rem; line-height: 1.5; }
        hr { border: none; border-top: 1px solid #e4e4e7; margin: 1.5rem 0; }
        .footer { font-size: .75rem; color: #a1a1aa; }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">500</div>
        <h1>Something went wrong</h1>
        <p>The server encountered an internal error and was unable to complete your request.</p>
        <hr>
        <p class="footer">Volt Framework &mdash; <?= date('Y-m-d H:i') ?></p>
    </div>
</body>
</html>
