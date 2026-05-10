<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentifizierungs-Middleware fuer api/admin/*.
 *
 * WICHTIG: Diese Middleware prueft AUSSCHLIESSLICH Authentifizierung
 * (Engineering-Handbuch Section 20). Permission-Gating muss pro Route ueber
 * das can:<permission>-Middleware erfolgen, analog zu routes/web.php.
 *
 * Hintergrund: Frueher gab es nur diese Middleware ohne nachgelagerte
 * Autorisierung. Resultat war eine Privilege-Escalation: jeder authentifizierte
 * Nutzer (auch viewer/support/operations) konnte Schreibzugriff auf System-
 * Settings und Log-Files erhalten. Der Fix bindet die API-Routen jetzt strikt
 * an die gleichen Permissions wie die Web-Routen.
 */
final class EnsureAdminApiAuthenticated
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        if ($this->authenticateWithAvailableGuards($request)) {
            return $next($request);
        }

        return $this->unauthorizedResponse();
    }

    private function authenticateWithAvailableGuards(Request $request): bool
    {
        $availableGuards = array_keys((array) config('auth.guards', []));

        foreach (['admin-token', 'web'] as $guard) {
            if (! in_array($guard, $availableGuards, true)) {
                continue;
            }

            $authGuard = Auth::guard($guard);
            if ($authGuard->check()) {
                $user = $authGuard->user();
                $request->setUserResolver(static fn () => $user);
                Auth::shouldUse($guard);

                return true;
            }
        }

        return false;
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'errors' => [[
                'status' => '401',
                'title' => 'Unauthorized',
                'detail' => 'Authentication is required for admin API access.',
            ]],
        ], 401, [
            'Content-Type' => 'application/vnd.api+json',
        ]);
    }
}
