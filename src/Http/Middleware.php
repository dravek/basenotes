<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\Session;
use App\Util\Csrf;
use App\Repos\TokenRepository;
use App\Repos\UserRepository;

final class Middleware
{
    public static function requireAuth(Request $request): void
    {
        if (Session::userId() === null) {
            header('Location: /login');
            exit;
        }
    }

    public static function requireAdmin(Request $request, UserRepository $userRepo): void
    {
        self::requireAuth($request);
        $userId = Session::userId();
        if ($userId === null) {
            header('Location: /login');
            exit;
        }
        $user = $userRepo->findById($userId);
        if ($user === null || !$user->isAdmin) {
            http_response_code(403);
            echo '<!DOCTYPE html><html><body><h1>403 Forbidden</h1><p>Admin access required.</p></body></html>';
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
    public static function loginRateLimit(string $identity): int
    {
        $window   = 15 * 60;
        $maxTries = 5;
        $now      = time();

        $keyPrefix = 'login_' . hash('sha256', $identity);
        $countKey  = $keyPrefix . '_count';
        $firstKey  = $keyPrefix . '_first';

        $count     = (int)Session::get($countKey, 0);
        $firstTime = (int)Session::get($firstKey, 0);

        if ($count >= $maxTries) {
            $elapsed   = $now - $firstTime;
            $remaining = $window - $elapsed;
            if ($remaining > 0) {
                return $remaining;
            }
            // Window expired — reset
            Session::set($countKey, 0);
            Session::set($firstKey, 0);
        }

        return 0;
    }

    public static function recordFailedLogin(string $identity): void
    {
        $keyPrefix = 'login_' . hash('sha256', $identity);
        $countKey  = $keyPrefix . '_count';
        $firstKey  = $keyPrefix . '_first';

        $count = (int)Session::get($countKey, 0);
        if ($count === 0) {
            Session::set($firstKey, time());
        }
        Session::set($countKey, $count + 1);
    }

    public static function resetLoginAttempts(string $identity): void
    {
        $keyPrefix = 'login_' . hash('sha256', $identity);
        $countKey  = $keyPrefix . '_count';
        $firstKey  = $keyPrefix . '_first';

        Session::set($countKey, 0);
        Session::set($firstKey, 0);
    }

    /**
     * Rate-limit POST /recovery: max 5 attempts per 15-minute window.
     * Returns remaining seconds of lockout (0 = not locked).
     */
    public static function recoveryRateLimit(string $identity): int
    {
        $window   = 15 * 60;
        $maxTries = 5;
        $now      = time();

        $keyPrefix = 'recovery_' . hash('sha256', $identity);
        $countKey  = $keyPrefix . '_count';
        $firstKey  = $keyPrefix . '_first';

        $count     = (int)Session::get($countKey, 0);
        $firstTime = (int)Session::get($firstKey, 0);

        if ($count >= $maxTries) {
            $elapsed   = $now - $firstTime;
            $remaining = $window - $elapsed;
            if ($remaining > 0) {
                return $remaining;
            }
            Session::set($countKey, 0);
            Session::set($firstKey, 0);
        }

        return 0;
    }

    public static function recordFailedRecovery(string $identity): void
    {
        $keyPrefix = 'recovery_' . hash('sha256', $identity);
        $countKey  = $keyPrefix . '_count';
        $firstKey  = $keyPrefix . '_first';

        $count = (int)Session::get($countKey, 0);
        if ($count === 0) {
            Session::set($firstKey, time());
        }
        Session::set($countKey, $count + 1);
    }

    public static function resetRecoveryAttempts(string $identity): void
    {
        $keyPrefix = 'recovery_' . hash('sha256', $identity);
        $countKey  = $keyPrefix . '_count';
        $firstKey  = $keyPrefix . '_first';

        Session::set($countKey, 0);
        Session::set($firstKey, 0);
    }

    /**
     * Authenticate API request via Bearer token.
     * Returns [TokenDto, userId] or sends 401 and exits.
     */
    public static function requireApiToken(
        Request $request,
        TokenRepository $tokenRepo,
        UserRepository $userRepo,
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

        $user = $userRepo->findById($token->userId);
        if ($user === null || $user->disabledAt !== null) {
            self::apiError(403, 'FORBIDDEN', 'User account is disabled.');
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

    public static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}
