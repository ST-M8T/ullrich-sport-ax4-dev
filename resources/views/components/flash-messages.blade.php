@props([
    'messages' => [],
    'error' => null,
    'success' => null,
    'info' => null,
])

@php
    $allMessages = [];

    foreach ((array) $messages as $message) {
        if (is_array($message)) {
            $allMessages[] = [
                'text' => $message['text'] ?? '',
                'type' => $message['type'] ?? 'info',
            ];
        } else {
            $allMessages[] = [
                'text' => (string) $message,
                'type' => 'info',
            ];
        }
    }

    if (!empty($error)) {
        $allMessages[] = ['text' => (string) $error, 'type' => 'error'];
    }

    if (!empty($success)) {
        $allMessages[] = ['text' => (string) $success, 'type' => 'success'];
    }

    if (!empty($info)) {
        $allMessages[] = ['text' => (string) $info, 'type' => 'info'];
    }

    $request = request();
    if ($request->has('error')) {
        $allMessages[] = ['text' => (string) $request->query('error'), 'type' => 'error'];
    }
    if ($request->has('success')) {
        $allMessages[] = ['text' => (string) $request->query('success'), 'type' => 'success'];
    }

    foreach (['error' => 'error', 'success' => 'success', 'info' => 'info', 'warning' => 'warning'] as $sessionKey => $type) {
        if (session()->has($sessionKey)) {
            $allMessages[] = [
                'text' => (string) session()->get($sessionKey),
                'type' => $type,
            ];
        }
    }

    $typeClasses = [
        'success' => 'alert alert-success',
        'error' => 'alert alert-error',
        'danger' => 'alert alert-error',
        'info' => 'alert alert-info',
        'warning' => 'alert alert-warning',
    ];
@endphp

@foreach($allMessages as $message)
    @php
        $type = $message['type'] ?? 'info';
        $cssClass = $typeClasses[$type] ?? 'alert alert-info';
    @endphp
    @if(!empty($message['text']))
        <div class="{{ $cssClass }}">
            {{ $message['text'] }}
        </div>
    @endif
@endforeach
