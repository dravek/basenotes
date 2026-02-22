<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\Session;
use App\Util\Csrf;
use App\Repos\TokenRepository;

final class Middleware
{
    public static function requireAuth(Request $request): void
    {
        if (Session::userId() === null) {
            header('Location: /login');
            exit;
        }
    }

    public static function verifyCsrf(Request $request): void
    {
        $submitted = $request->post('_csrf');
        if (!Csrf::validate($submitted)) {
            http_response_code(403);
            echo '<!DOCTYPE html><html><body><h1>403 Forbidden</h1><p>Invalid or missing CSRF token.</p></body></html>';
            exit;
        }
    }

    /**
     * Rate-limit POST /login: max 5 attempts per 15-minute window.
     * Returns remaining seconds of lockout (0 = not locked).
     */
    public static function loginRateLimit(): int
    {
        $window    = 15 * 60;
        $maxTries  = 5;
        $count     = (int)Session::get('login_attempts', 0);
        $firstTime = (int)Session::get('login_first_attempt', 0);
        $now       = time();

        if ($count >= $maxTries) {
            $elapsed   = $now - $firstTime;
            $remaining = $window - $elapsed;
            if ($remaining > 0) {
                return $remaining;
            }
            // Window expired — reset
            Session::set('login_attempts', 0);
            Session::set('login_first_attempt', 0);
        }

        return 0;
    }

    public static function recordFailedLogin(): void
    {
        $count = (int)Session::get('login_attempts', 0);
        if ($count === 0) {
            Session::set('login_first_attempt', time());
        }
        Session::set('login_attempts', $count + 1);
    }

    public static function resetLoginAttempts(): void
    {
        Session::set('login_attempts', 0);
        Session::set('login_first_attempt', 0);
    }

    /**
     * Authenticate API request via Bearer token.
     * Returns [TokenDto, userId] or sends 401 and exits.
     */
    public static function requireApiToken(
        Request $request,
        TokenRepository $tokenRepo,
        string $requiredScope,
        string $pepper,
    ): \App\Repos\TokenDto {
        $raw = $request->bearerToken();
        if ($raw === null) {
            self::apiError(401, 'UNAUTHORIZED', 'Missing Authorization header.');
        }

        $hash = hash_hmac('sha256', $raw, $pepper);
        $token = $tokenRepo->findByHash($hash);

        if ($token === null || $token->revokedAt !== null) {
            self::apiError(401, 'UNAUTHORIZED', 'Invalid or revoked token.');
        }

        $scopes = explode(',', $token->scopes);
        if (!in_array($requiredScope, $scopes, strict: true)) {
            self::apiError(403, 'FORBIDDEN', 'Token does not have required scope.');
        }

        $tokenRepo->updateLastUsed($token->id);

        return $token;
    }

    public static function apiError(int $status, string $code, string $message): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['error' => ['code' => $code, 'message' => $message]]);
        exit;
    }
}
