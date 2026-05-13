{{-- Mail styles embedded in <style> for HTML-mail-client compatibility. --}}
{{-- Values mirror resources/css/variables.css tokens but are inlined because --}}
{{-- many mail clients do not support CSS custom properties. --}}
<style>
    body.mail-body {
        font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
        color: #0f172a;
        max-width: 100%;
        overflow-x: hidden;
        word-break: break-word;
    }
    .mail-h1 { color: #c82333; }
    .mail-h2, .mail-h3 { color: #0f172a; font-family: Arial, sans-serif; max-width: 100%; overflow-x: hidden; }
    .mail-text { font-family: Arial, sans-serif; font-size: 14px; color: #0f172a; max-width: 100%; overflow-x: hidden; }
    .mail-table { border-collapse: collapse; border: 1px solid #e5e7eb; }
    .mail-cell { border: 1px solid #e5e7eb; padding: 6px; }
    .mail-pre {
        margin: 0;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .mail-pre-block {
        background: #f3f4f6;
        padding: 12px;
        border-radius: 6px;
        font-size: 13px;
        line-height: 1.5;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .mail-footer {
        color: #475569;
        font-size: 12px;
    }
</style>
