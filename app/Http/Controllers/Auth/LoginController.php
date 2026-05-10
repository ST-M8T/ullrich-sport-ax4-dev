<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\AuthenticationService;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class LoginController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $authService,
        private readonly AuthManager $authManager,
    ) {
        // Guard dependencies injected via container.
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->guard()->check()) {
            return redirect()->route('dispatch-lists');
        }

        return view('identity.auth.login', [
            'status' => session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->guard()->check()) {
            return redirect()->route('dispatch-lists');
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'max:255'],
            'two_factor_code' => ['nullable', 'string', 'max:32'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $result = $this->authService->attempt(
            $data['username'],
            $data['password'],
            $request->ip(),
            (string) $request->userAgent(),
            $data['two_factor_code'] ?? null
        );

        if (($result['success'] ?? false) === false || ! isset($result['user'])) {
            return $this->failedAttemptResponse($request, $result);
        }

        $user = $result['user'];

        $model = UserModel::query()->find($user->id()->toInt());

        if (($model instanceof UserModel) === false) {
            return back()
                ->withErrors(['username' => 'Die Anmeldung ist aktuell nicht möglich. Bitte versuchen Sie es später erneut.'], 'login')
                ->withInput($request->except('password', 'two_factor_code'));
        }

        $this->guard()->login($model, (bool) ($data['remember'] ?? false));

        $request->session()->regenerate();

        if ($result['requires_password_change'] ?? false) {
            session()->flash('warning', 'Bitte ändern Sie Ihr Passwort. Wenden Sie sich ggf. an den Administrator.');
        }

        return redirect()->intended(route('dispatch-lists'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $guard = $this->guard();
        $guard->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'Sie wurden abgemeldet.');
    }

    private function guard(): StatefulGuard
    {
        $guard = $this->authManager->guard();
        // Der konfigurierte Default-Guard ist `web` (siehe config/auth.php),
        // also ein StatefulGuard. Im Container kann jedoch theoretisch ein
        // abstrakter Guard angelegt sein — harter Type-Assert für PhpStan-Level 3+.
        if (! $guard instanceof StatefulGuard) {
            throw new \LogicException('Der Default-Guard muss ein StatefulGuard sein, ist aber: '.$guard::class);
        }

        return $guard;
    }

    /**
     * @param  array<string,mixed>  $result
     */
    private function failedAttemptResponse(Request $request, array $result): RedirectResponse
    {
        $errorCode = (string) ($result['error'] ?? 'authentication_failed');

        $message = match ($errorCode) {
            'too_many_attempts' => $this->formatRateLimitMessage((int) ($result['retry_after_seconds'] ?? 60)),
            'two_factor_required' => 'Ein Zwei-Faktor-Code ist erforderlich, um sich anzumelden.',
            'two_factor_invalid' => 'Der eingegebene Zwei-Faktor-Code ist ungültig.',
            default => 'Die Kombination aus Benutzername und Passwort wurde nicht erkannt.',
        };

        $input = $request->except('password');

        if (($result['two_factor_required'] ?? false) === false) {
            unset($input['two_factor_code']);
        }

        return back()
            ->withErrors(['username' => $message], 'login')
            ->withInput($input);
    }

    private function formatRateLimitMessage(int $retryAfter): string
    {
        $retryAfter = max(1, $retryAfter);

        if ($retryAfter >= 60) {
            $minutes = (int) ceil($retryAfter / 60);

            return sprintf(
                'Zu viele Anmeldeversuche. Bitte versuchen Sie es in ca. %d %s erneut.',
                $minutes,
                $minutes === 1 ? 'Minute' : 'Minuten'
            );
        }

        return sprintf(
            'Zu viele Anmeldeversuche. Bitte warten Sie %d %s.',
            $retryAfter,
            $retryAfter === 1 ? 'Sekunde' : 'Sekunden'
        );
    }
}
