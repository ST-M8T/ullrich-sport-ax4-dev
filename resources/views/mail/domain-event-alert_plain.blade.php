{{ $severity }} alert

{{ $messageLine }}

Context:
{{ json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}
