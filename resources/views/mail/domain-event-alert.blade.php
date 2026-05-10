<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Domain Event Alert</title>
</head>
<style>
        body { max-width: 100%; overflow-x: hidden; word-break: break-word; }
        h2, p, h3 { max-width: 100%; overflow-x: hidden; }
        pre { white-space: pre-wrap; word-break: break-word; }
    </style>
<body>
    <h2 style="font-family: Arial, sans-serif; color: #1f2933;">
        {{ $severity }} alert
    </h2>

    <p style="font-family: Arial, sans-serif; font-size: 14px; color: #1f2933;">
        {{ $messageLine }}
    </p>

    <h3 style="font-family: Arial, sans-serif; color: #1f2933;">Context</h3>
    <pre style="background-color: #f3f4f6; padding: 12px; border-radius: 4px; font-size: 13px; line-height: 1.5;">
{{ json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}
    </pre>
</body>
</html>
