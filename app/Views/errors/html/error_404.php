<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>Volt — Page Not Found</title>
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
        .code { font-size: 4rem; font-weight: 700; color: #3b82f6; line-height: 1; margin-bottom: .5rem; }
        h1 { font-size: 1.25rem; font-weight: 600; margin-bottom: .75rem; }
        p { color: #71717a; font-size: .875rem; line-height: 1.5; }
        .detail { margin-top: 1rem; padding: .75rem; background: #eff6ff; border-radius: 8px; font-size: .8125rem; color: #1e40af; word-break: break-word; }
        hr { border: none; border-top: 1px solid #e4e4e7; margin: 1.5rem 0; }
        .footer { font-size: .75rem; color: #a1a1aa; }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">404</div>
        <h1>Page Not Found</h1>
        <p>The page you requested could not be found on this server.</p>
        <?php if (isset($message) && ENVIRONMENT !== 'production') : ?>
            <div class="detail"><?= esc($message) ?></div>
        <?php endif; ?>
        <hr>
        <p class="footer"><a href="<?= site_url('/desk') ?>" style="color:#2563eb;text-decoration:none;">Back to Desk</a></p>
    </div>
</body>
</html>
