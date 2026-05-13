<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>DHL-Katalog-Sync fehlgeschlagen</title>
    @include('mail.partials.styles')
</head>
<body class="mail-body">
    <h1 class="mail-h1">DHL-Katalog-Sync fehlgeschlagen</h1>

    <p class="mail-text">
        Der automatische DHL-Katalog-Sync ist fehlgeschlagen. Diese Mail wird nur
        beim <strong>ersten</strong> Fehler einer Serie versendet — bis zum
        nächsten erfolgreichen Lauf gibt es keine weiteren Erinnerungen.
    </p>

    <table cellpadding="6" class="mail-table">
        <tr>
            <td class="mail-cell"><strong>Fehler</strong></td>
            <td class="mail-cell"><pre class="mail-pre">{{ $errorMessage }}</pre></td>
        </tr>
        <tr>
            <td class="mail-cell"><strong>Letzter Erfolg</strong></td>
            <td class="mail-cell">{{ $lastSuccessAt }}</td>
        </tr>
        <tr>
            <td class="mail-cell"><strong>Aufeinanderfolgende Fehler</strong></td>
            <td class="mail-cell">{{ $consecutiveFailures }}</td>
        </tr>
        <tr>
            <td class="mail-cell"><strong>Routing-Filter</strong></td>
            <td class="mail-cell">{{ $routingFilter }}</td>
        </tr>
    </table>

    @if (! empty($resultSummary))
        <h2 class="mail-h2">Result-Übersicht</h2>
        <pre class="mail-pre-block">{{ json_encode($resultSummary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    @endif

    <p class="mail-footer">
        Automatisch generiert von AX4. Kontakt: DevOps.
    </p>
</body>
</html>
