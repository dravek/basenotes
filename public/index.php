<?php

declare(strict_types=1);

// ── Autoloader ──────────────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $base = __DIR__ . '/../src/';
    $rel  = str_replace('App\\', '', $class);
    $file = $base . str_replace('\\', '/', $rel) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// ── Bootstrap ────────────────────────────────────────────────────────────────
use App\Auth\Session;
use App\Http\Middleware;
use App\Http\Request;
use App\Http\Router;
use App\Repos\NoteRepository;
use App\Repos\NoteVersionRepository;
use App\Repos\RecoveryAuditRepository;
use App\Repos\RecoveryCodeRepository;
use App\Repos\TokenRepository;
use App\Repos\UserRepository;
use App\Util\Env;

$envPath = __DIR__ . '/../.env';
Env::load(file_exists($envPath) ? $envPath : '', [
    'APP_PEPPER',
    'APP_ENV',
    'DB_HOST',
    'DB_PORT',
    'DB_NAME',
    'DB_USER',
    'DB_PASS',
]);

// Global output escaping helper
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

final class WebAbortException extends RuntimeException
{
    public function __construct(public int $status, public string $html)
    {
        parent::__construct('Web request aborted', $status);
    }
}

final class ApiAbortException extends RuntimeException
{
    public function __construct(
        public int $status,
        public string $errorCode,
        public string $errorMessage,
    ) {
        parent::__construct('API request aborted', $status);
    }
}

function webNotFound(string $message): never
{
    throw new WebAbortException(
        404,
        '<!DOCTYPE html><html><body><h1>' . e($message) . '</h1></body></html>',
    );
}

function apiAbort(int $status, string $code, string $message): never
{
    throw new ApiAbortException($status, $code, $message);
}

function dbTransaction(PDO $pdo, callable $callback): mixed
{
    $pdo->beginTransaction();
    try {
        $result = $callback();
        $pdo->commit();
        return $result;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function dbTransactionWeb(PDO $pdo, callable $callback): mixed
{
    try {
        return dbTransaction($pdo, $callback);
    } catch (WebAbortException $e) {
        http_response_code($e->status);
        echo $e->html;
        exit;
    }
}

function dbTransactionApi(PDO $pdo, callable $callback): mixed
{
    try {
        return dbTransaction($pdo, $callback);
    } catch (ApiAbortException $e) {
        Middleware::apiError($e->status, $e->errorCode, $e->errorMessage);
    }
}

Session::start();

Middleware::securityHeaders();

$pdo = new PDO(
    dsn: sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        Env::get('DB_HOST'),
        Env::get('DB_PORT'),
        Env::get('DB_NAME'),
    ),
    username: Env::get('DB_USER'),
    password: Env::get('DB_PASS'),
    options: [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ],
);

$userRepo  = new UserRepository($pdo);
$noteRepo  = new NoteRepository($pdo);
$noteVersionRepo = new NoteVersionRepository($pdo);
$tokenRepo = new TokenRepository($pdo);
$recoveryRepo = new RecoveryCodeRepository($pdo);
$recoveryAuditRepo = new RecoveryAuditRepository($pdo);
$pepper    = Env::get('APP_PEPPER');

$sessionUserId = Session::userId();
if ($sessionUserId !== null) {
    $sessionUser = $userRepo->findById($sessionUserId);
    if ($sessionUser === null || $sessionUser->disabledAt !== null) {
        Session::destroy();
        header('Location: /login');
        exit;
    }
    Session::set('is_admin', $sessionUser->isAdmin);
}

$request = new Request();
$router  = new Router();

// ── Static assets ─────────────────────────────────────────────────────────
// (Caddy serves /assets directly; PHP only handles app routes)

// ── Auth routes ───────────────────────────────────────────────────────────

$router->get('/login', function (Request $req) use ($userRepo): void {
    if (\App\Auth\Session::userId() !== null) {
        header('Location: /app/notes');
        exit;
    }
    require __DIR__ . '/../views/login.php';
});

$router->post('/login', function (Request $req) use ($userRepo): void {
    Middleware::verifyCsrf($req);

    $email = trim($req->post('email'));
    $identity = strtolower($email) . '|' . $req->ip();

    $remaining = Middleware::loginRateLimit($identity);
    if ($remaining > 0) {
        $mins = (int)ceil($remaining / 60);
        $errors = ["Too many failed attempts. Try again in {$mins} minute(s)."];
        http_response_code(429);
        require __DIR__ . '/../views/login.php';
        return;
    }

    $password = $req->post('password');
    $user     = $userRepo->findByEmail(strtolower($email));

    if ($user === null || !\App\Auth\Password::verify($password, $user->passwordHash)) {
        Middleware::recordFailedLogin($identity);
        $errors = ['Invalid email or password.'];
        require __DIR__ . '/../views/login.php';
        return;
    }

    if ($user->disabledAt !== null) {
        $errors = ['Your account has been disabled. Contact an administrator.'];
        require __DIR__ . '/../views/login.php';
        return;
    }

    Middleware::resetLoginAttempts($identity);
    session_regenerate_id(true);
    \App\Auth\Session::set('user_id', $user->id);
    header('Location: /app/notes');
    exit;
});

$router->get('/register', function (Request $req): void {
    if (\App\Auth\Session::userId() !== null) {
        header('Location: /app/notes');
        exit;
    }
    require __DIR__ . '/../views/register.php';
});

$router->post('/register', function (Request $req) use ($userRepo, $recoveryRepo, $pepper, $pdo): void {
    Middleware::verifyCsrf($req);

    $email    = strtolower(trim($req->post('email')));
    $password = $req->post('password');

    $errors = \App\Util\Validate::merge(
        \App\Util\Validate::required($email, 'Email'),
        \App\Util\Validate::email($email),
        \App\Util\Validate::required($password, 'Password'),
        \App\Util\Validate::minLength($password, 10, 'Password'),
    );

    if ($userRepo->findByEmail($email) !== null) {
        $errors[] = 'An account with that email already exists.';
    }

    if ($errors !== []) {
        require __DIR__ . '/../views/register.php';
        return;
    }

    $now  = time();
    $user = new \App\Repos\UserDto(
        id:           \App\Util\Id::ulid(),
        email:        $email,
        passwordHash: \App\Auth\Password::hash($password),
        isAdmin:      false,
        disabledAt:   null,
        createdAt:    $now,
        updatedAt:    $now,
    );

    $rawCodes = \App\Auth\RecoveryCode::generateBatch(10);
    $hashes = array_map(
        fn(string $raw) => \App\Auth\RecoveryCode::hash($raw, $pepper),
        $rawCodes,
    );

    dbTransaction($pdo, function () use ($userRepo, $recoveryRepo, $user, $hashes): void {
        $userRepo->create($user);
        $recoveryRepo->createMany($user->id, $hashes);
    });

    $displayCodes = array_map(\App\Auth\RecoveryCode::format(...), $rawCodes);
    \App\Auth\Session::set('new_recovery_codes', $displayCodes);

    session_regenerate_id(true);
    \App\Auth\Session::set('user_id', $user->id);
    header('Location: /app/settings/recovery');
    exit;
});

$router->get('/recovery', function (Request $req): void {
    $errors = [];
    require __DIR__ . '/../views/recovery.php';
});

$router->post('/recovery', function (Request $req) use ($userRepo, $recoveryRepo, $recoveryAuditRepo, $pepper): void {
    Middleware::verifyCsrf($req);

    $identity = $req->ip();
    $remaining = Middleware::recoveryRateLimit($identity);
    if ($remaining > 0) {
        $mins = (int)ceil($remaining / 60);
        $errors = ["Too many attempts. Try again in {$mins} minute(s)."];
        http_response_code(429);
        require __DIR__ . '/../views/recovery.php';
        return;
    }

    $codeInput = $req->post('recovery_code');
    $new       = $req->post('new_password');
    $confirm   = $req->post('confirm_password');

    $errors = \App\Util\Validate::merge(
        \App\Util\Validate::required($codeInput, 'Recovery code'),
        \App\Util\Validate::required($new, 'New password'),
        \App\Util\Validate::minLength($new, 10, 'New password'),
    );

    if ($new !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    $normalized = \App\Auth\RecoveryCode::normalize((string)$codeInput);
    if ($normalized === '') {
        $errors[] = 'Recovery code is invalid.';
    }

    if ($errors !== []) {
        require __DIR__ . '/../views/recovery.php';
        return;
    }

    $hash = \App\Auth\RecoveryCode::hash($normalized, $pepper);
    $code = $recoveryRepo->findValidByHash($hash);
    if ($code === null) {
        Middleware::recordFailedRecovery($identity);
        $errors = ['Recovery code is invalid or already used.'];
        require __DIR__ . '/../views/recovery.php';
        return;
    }

    $codeUser = $userRepo->findById($code->userId);
    if ($codeUser === null || $codeUser->disabledAt !== null) {
        Middleware::recordFailedRecovery($identity);
        $errors = ['Recovery code is invalid or already used.'];
        require __DIR__ . '/../views/recovery.php';
        return;
    }

    $userRepo->updatePassword($code->userId, \App\Auth\Password::hash($new));
    $recoveryRepo->markUsed($code->id);
    Middleware::resetRecoveryAttempts($identity);
    $recoveryAuditRepo->record(
        $code->userId,
        'recovery_code_used',
        $req->ip(),
        $req->header('User-Agent'),
    );

    session_regenerate_id(true);
    \App\Auth\Session::set('user_id', $code->userId);
    header('Location: /app/notes');
    exit;
});

$router->post('/logout', function (Request $req): void {
    Middleware::verifyCsrf($req);
    \App\Auth\Session::destroy();
    header('Location: /login');
    exit;
});

// ── Notes routes ─────────────────────────────────────────────────────────

$router->get('/app/notes', function (Request $req) use ($noteRepo): void {
    Middleware::requireAuth($req);
    $userId = \App\Auth\Session::userId();
    $search = $req->query('q');
    $notes  = $noteRepo->listByUser($userId, $search !== '' ? $search : null);
    require __DIR__ . '/../views/notes/list.php';
});

$router->get('/app/notes/new', function (Request $req): void {
    Middleware::requireAuth($req);
    $note = null;
    require __DIR__ . '/../views/notes/edit.php';
});

$router->post('/app/notes', function (Request $req) use ($noteRepo): void {
    Middleware::requireAuth($req);
    Middleware::verifyCsrf($req);
    $userId = \App\Auth\Session::userId();

    $title   = mb_substr(trim($req->post('title')) ?: 'Untitled', 0, 500);
    $content = $req->post('content_md');

    $now  = time();
    $note = new \App\Repos\NoteDto(
        id:        \App\Util\Id::ulid(),
        userId:    $userId,
        title:     $title,
        contentMd: $content,
        createdAt: $now,
        updatedAt: $now,
        deletedAt: null,
    );
    $noteRepo->create($note);
    header('Location: /app/notes/' . $note->id);
    exit;
});

$router->get('/app/notes/{id}', function (Request $req, array $args) use ($noteRepo): void {
    Middleware::requireAuth($req);
    $userId = \App\Auth\Session::userId();
    $note   = $noteRepo->findById($args['id'], $userId);
    if ($note === null) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><body><h1>Note not found.</h1></body></html>';
        exit;
    }
    require __DIR__ . '/../views/notes/edit.php';
});

$router->post('/app/notes/{id}', function (Request $req, array $args) use ($noteRepo, $noteVersionRepo, $pdo): void {
    Middleware::requireAuth($req);
    Middleware::verifyCsrf($req);
    $userId = \App\Auth\Session::userId();
    $title   = mb_substr(trim($req->post('title')) ?: 'Untitled', 0, 500);
    $content = $req->post('content_md');

    $noteId = $args['id'];
    dbTransactionWeb($pdo, function () use ($noteRepo, $noteVersionRepo, $userId, $noteId, $title, $content): void {
        $note = $noteRepo->findByIdForUpdate($noteId, $userId);
        if ($note === null) {
            webNotFound('Note not found.');
        }

        $now = time();
        $noteVersionRepo->createSnapshot($note, 'update', $now);

        $updated = new \App\Repos\NoteDto(
            id:        $note->id,
            userId:    $note->userId,
            title:     $title,
            contentMd: $content,
            createdAt: $note->createdAt,
            updatedAt: $now,
            deletedAt: null,
        );
        $noteRepo->update($updated);
    });

    header('Location: /app/notes/' . $noteId);
    exit;
});

$router->get('/app/notes/{id}/history', function (Request $req, array $args) use ($noteRepo, $noteVersionRepo): void {
    Middleware::requireAuth($req);
    $userId = \App\Auth\Session::userId();
    $note   = $noteRepo->findByIdIncludingDeleted($args['id'], $userId);
    if ($note === null) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><body><h1>Note not found.</h1></body></html>';
        exit;
    }

    $versions = $noteVersionRepo->listByNote($note->id, $userId, 100);
    require __DIR__ . '/../views/notes/history.php';
});

$router->post('/app/notes/{id}/history/{versionId}/rollback', function (Request $req, array $args) use ($noteRepo, $noteVersionRepo, $pdo): void {
    Middleware::requireAuth($req);
    Middleware::verifyCsrf($req);
    $userId = \App\Auth\Session::userId();
    $noteId = $args['id'];
    $versionId = $args['versionId'];

    dbTransactionWeb($pdo, function () use ($noteRepo, $noteVersionRepo, $userId, $noteId, $versionId): void {
        $note = $noteRepo->findByIdForUpdateIncludingDeleted($noteId, $userId);
        if ($note === null) {
            webNotFound('Note not found.');
        }

        $version = $noteVersionRepo->findById($versionId, $noteId, $userId);
        if ($version === null) {
            webNotFound('Version not found.');
        }

        $now = time();
        $noteVersionRepo->createSnapshot($note, 'rollback', $now);

        $rolledBack = new \App\Repos\NoteDto(
            id:        $note->id,
            userId:    $note->userId,
            title:     trim($version->title) !== '' ? $version->title : 'Untitled',
            contentMd: $version->contentMd,
            createdAt: $note->createdAt,
            updatedAt: $now,
            deletedAt: null,
        );
        if ($note->deletedAt !== null) {
            $noteRepo->restore($rolledBack);
            return;
        }

        $noteRepo->update($rolledBack);
    });

    header('Location: /app/notes/' . $noteId);
    exit;
});

$router->post('/app/notes/{id}/delete', function (Request $req, array $args) use ($noteRepo, $noteVersionRepo, $pdo): void {
    Middleware::requireAuth($req);
    Middleware::verifyCsrf($req);
    $userId = \App\Auth\Session::userId();
    $noteId = $args['id'];

    dbTransactionWeb($pdo, function () use ($noteRepo, $noteVersionRepo, $userId, $noteId): void {
        $note = $noteRepo->findByIdForUpdate($noteId, $userId);
        if ($note === null) {
            webNotFound('Note not found.');
        }

        $noteVersionRepo->createSnapshot($note, 'delete', time());
        $noteRepo->softDelete($noteId, $userId);
    });

    header('Location: /app/notes/' . $noteId . '/history');
    exit;
});

$router->get('/app/notes/{id}/export', function (Request $req, array $args) use ($noteRepo): void {
    Middleware::requireAuth($req);
    $userId = \App\Auth\Session::userId();
    $note   = $noteRepo->findById($args['id'], $userId);
    if ($note === null) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><body><h1>Note not found.</h1></body></html>';
        exit;
    }

    $base = trim($note->title);
    if ($base === '') {
        $base = 'note';
    }
    $base = preg_replace('/[^A-Za-z0-9]+/', '-', $base);
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'note';
    }
    $base = substr($base, 0, 80);
    $filename = $base . '.md';

    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo (string) $note->contentMd;
    exit;
});

// ── Admin routes ─────────────────────────────────────────────────────────

$router->get('/app/admin/users', function (Request $req) use ($userRepo): void {
    Middleware::requireAdmin($req, $userRepo);
    $users = $userRepo->listAll();
    require __DIR__ . '/../views/admin/users.php';
});

$router->post('/app/admin/users/{id}/disable', function (Request $req, array $args) use ($userRepo): void {
    Middleware::requireAdmin($req, $userRepo);
    Middleware::verifyCsrf($req);
    $targetId = $args['id'];
    $targetUser = $userRepo->findById($targetId);
    if ($targetUser === null) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><body><h1>404 Not Found</h1><p>User not found.</p></body></html>';
        exit;
    }
    if ($targetId === \App\Auth\Session::userId()) {
        http_response_code(400);
        echo '<!DOCTYPE html><html><body><h1>400 Bad Request</h1><p>You cannot disable your own account.</p></body></html>';
        exit;
    }
    $userRepo->setDisabledAt($targetId, time());
    header('Location: /app/admin/users');
    exit;
});

$router->post('/app/admin/users/{id}/enable', function (Request $req, array $args) use ($userRepo): void {
    Middleware::requireAdmin($req, $userRepo);
    Middleware::verifyCsrf($req);
    $targetId = $args['id'];
    $targetUser = $userRepo->findById($targetId);
    if ($targetUser === null) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><body><h1>404 Not Found</h1><p>User not found.</p></body></html>';
        exit;
    }
    $userRepo->setDisabledAt($targetId, null);
    header('Location: /app/admin/users');
    exit;
});

// ── Settings routes ───────────────────────────────────────────────────────

$router->get('/app/settings/password', function (Request $req): void {
    Middleware::requireAuth($req);
    require __DIR__ . '/../views/settings/password.php';
});

$router->post('/app/settings/password', function (Request $req) use ($userRepo): void {
    Middleware::requireAuth($req);
    Middleware::verifyCsrf($req);
    $userId  = \App\Auth\Session::userId();
    $user    = $userRepo->findById($userId);

    if ($user === null) {
        \App\Auth\Session::destroy();
        header('Location: /login');
        exit;
    }

    $current = $req->post('current_password');
    $new     = $req->post('new_password');
    $confirm = $req->post('confirm_password');

    $errors = [];
    if (!\App\Auth\Password::verify($current, $user->passwordHash)) {
        $errors[] = 'Current password is incorrect.';
    }
    $errors = array_merge($errors, \App\Util\Validate::minLength($new, 10, 'New password'));
    if ($new !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if ($errors !== []) {
        require __DIR__ . '/../views/settings/password.php';
        return;
    }

    $userRepo->updatePassword($userId, \App\Auth\Password::hash($new));
    session_regenerate_id(true);
    $success = 'Password updated successfully.';
    require __DIR__ . '/../views/settings/password.php';
});

$router->get('/app/settings/recovery', function (Request $req): void {
    Middleware::requireAuth($req);
    $codes = \App\Auth\Session::get('new_recovery_codes');
    \App\Auth\Session::set('new_recovery_codes', null);
    require __DIR__ . '/../views/settings/recovery.php';
});

$router->post('/app/settings/recovery', function (Request $req) use ($recoveryRepo, $recoveryAuditRepo, $pepper, $pdo): void {
    Middleware::requireAuth($req);
    Middleware::verifyCsrf($req);
    $userId = \App\Auth\Session::userId();

    $rawCodes = \App\Auth\RecoveryCode::generateBatch(10);
    $hashes = array_map(
        fn(string $raw) => \App\Auth\RecoveryCode::hash($raw, $pepper),
        $rawCodes,
    );

    dbTransaction($pdo, function () use ($recoveryRepo, $userId, $hashes): void {
        $recoveryRepo->invalidateAll($userId);
        $recoveryRepo->createMany($userId, $hashes);
    });

    $displayCodes = array_map(\App\Auth\RecoveryCode::format(...), $rawCodes);
    \App\Auth\Session::set('new_recovery_codes', $displayCodes);
    $recoveryAuditRepo->record(
        $userId,
        'recovery_codes_regenerated',
        $req->ip(),
        $req->header('User-Agent'),
    );
    header('Location: /app/settings/recovery');
    exit;
});

$router->get('/app/settings/tokens', function (Request $req) use ($tokenRepo): void {
    Middleware::requireAuth($req);
    $userId = \App\Auth\Session::userId();
    $tokens = $tokenRepo->listByUser($userId);
    $newRawToken = \App\Auth\Session::get('new_raw_token');
    \App\Auth\Session::set('new_raw_token', null);
    require __DIR__ . '/../views/settings/tokens.php';
});

$router->post('/app/settings/tokens', function (Request $req) use ($tokenRepo, $pepper): void {
    Middleware::requireAuth($req);
    Middleware::verifyCsrf($req);
    $userId = \App\Auth\Session::userId();

    $name = trim($req->post('name'));
    $errors = \App\Util\Validate::merge(
        \App\Util\Validate::required($name, 'Token name'),
        \App\Util\Validate::maxLength($name, 100, 'Token name'),
    );
    if ($errors !== []) {
        $tokens = $tokenRepo->listByUser($userId);
        require __DIR__ . '/../views/settings/tokens.php';
        return;
    }

    $raw   = \App\Auth\Token::generate();
    $hash  = \App\Auth\Token::hash($raw, $pepper);
    $allowedScopes = ['notes:read', 'notes:read,notes:write'];
    $scope = in_array($req->post('scopes'), $allowedScopes, strict: true)
        ? $req->post('scopes')
        : 'notes:read';

    $now   = time();
    $dto   = new \App\Repos\TokenDto(
        id:         \App\Util\Id::ulid(),
        userId:     $userId,
        name:       $name,
        tokenHash:  $hash,
        scopes:     $scope,
        createdAt:  $now,
        lastUsedAt: null,
        revokedAt:  null,
    );
    $tokenRepo->create($dto);

    // Store raw token in session for one-time display
    \App\Auth\Session::set('new_raw_token', $raw);
    header('Location: /app/settings/tokens');
    exit;
});

$router->post('/app/settings/tokens/{id}/revoke', function (Request $req, array $args) use ($tokenRepo): void {
    Middleware::requireAuth($req);
    Middleware::verifyCsrf($req);
    $userId = \App\Auth\Session::userId();
    $tokenRepo->revoke($args['id'], $userId);
    header('Location: /app/settings/tokens');
    exit;
});

// ── API routes ────────────────────────────────────────────────────────────

$router->get('/api/v1/notes', function (Request $req) use ($noteRepo, $tokenRepo, $userRepo, $pepper): void {
    $token  = Middleware::requireApiToken($req, $tokenRepo, $userRepo, 'notes:read', $pepper);
    $userId = $token->userId;

    $cursor  = $req->query('cursor');
    $perPage = min((int)($req->query('per_page') ?: 20), 100);
    if ($perPage < 1) {
        $perPage = 20;
    }

    $cursorData = null;
    if ($cursor !== '') {
        $decoded = base64_decode($cursor, strict: true);
        if ($decoded !== false && str_contains($decoded, ':')) {
            [$updatedAt, $id] = explode(':', $decoded, 2);
            $cursorData = ['updated_at' => (int)$updatedAt, 'id' => $id];
        }
    }

    [$notes, $nextCursor] = $noteRepo->listPaginated($userId, $cursorData, $perPage);

    header('Content-Type: application/json');
    echo json_encode([
        'data'        => array_map(
            fn(\App\Repos\NoteDto $n) => [
                'id'         => $n->id,
                'title'      => $n->title,
                'updated_at' => $n->updatedAt,
            ],
            $notes,
        ),
        'next_cursor' => $nextCursor,
        'per_page'    => $perPage,
    ]);
});

$router->post('/api/v1/notes', function (Request $req) use ($noteRepo, $tokenRepo, $userRepo, $pepper): void {
    $token  = Middleware::requireApiToken($req, $tokenRepo, $userRepo, 'notes:write', $pepper);
    $userId = $token->userId;

    $body = $req->rawBody();
    if (!json_validate($body)) {
        Middleware::apiError(400, 'BAD_REQUEST', 'Request body is not valid JSON.');
    }
    $data = json_decode($body, true);

    $title   = isset($data['title']) ? trim((string)$data['title']) : 'Untitled';
    $content = isset($data['content_md']) ? (string)$data['content_md'] : '';

    if ($title === '') {
        $title = 'Untitled';
    }
    if (mb_strlen($title) > 500) {
        Middleware::apiError(422, 'VALIDATION_ERROR', 'Title must not exceed 500 characters.');
    }

    $now  = time();
    $note = new \App\Repos\NoteDto(
        id:        \App\Util\Id::ulid(),
        userId:    $userId,
        title:     $title,
        contentMd: $content,
        createdAt: $now,
        updatedAt: $now,
        deletedAt: null,
    );
    $noteRepo->create($note);

    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode([
        'id'         => $note->id,
        'title'      => $note->title,
        'updated_at' => $note->updatedAt,
    ]);
});

$router->get('/api/v1/notes/{id}', function (Request $req, array $args) use ($noteRepo, $tokenRepo, $userRepo, $pepper): void {
    $token  = Middleware::requireApiToken($req, $tokenRepo, $userRepo, 'notes:read', $pepper);
    $userId = $token->userId;

    $note = $noteRepo->findById($args['id'], $userId);
    if ($note === null) {
        Middleware::apiError(404, 'NOT_FOUND', 'Note not found.');
    }

    header('Content-Type: application/json');
    echo json_encode([
        'id'         => $note->id,
        'title'      => $note->title,
        'content_md' => $note->contentMd,
        'created_at' => $note->createdAt,
        'updated_at' => $note->updatedAt,
    ]);
});

$router->patch('/api/v1/notes/{id}', function (Request $req, array $args) use ($noteRepo, $noteVersionRepo, $tokenRepo, $userRepo, $pepper, $pdo): void {
    $token  = Middleware::requireApiToken($req, $tokenRepo, $userRepo, 'notes:write', $pepper);
    $userId = $token->userId;

    $body = $req->rawBody();
    if (!json_validate($body)) {
        Middleware::apiError(400, 'BAD_REQUEST', 'Request body is not valid JSON.');
    }
    $data = json_decode($body, true);

    $updated = dbTransactionApi($pdo, function () use ($noteRepo, $noteVersionRepo, $userId, $args, $data): \App\Repos\NoteDto {
        $existing = $noteRepo->findByIdForUpdate($args['id'], $userId);
        if ($existing === null) {
            apiAbort(404, 'NOT_FOUND', 'Note not found.');
        }

        $title   = isset($data['title'])      ? trim((string)$data['title']) : $existing->title;
        $content = isset($data['content_md']) ? (string)$data['content_md']   : $existing->contentMd;
        if (mb_strlen($title) > 500) {
            apiAbort(422, 'VALIDATION_ERROR', 'Title must not exceed 500 characters.');
        }
        $now = time();

        $noteVersionRepo->createSnapshot($existing, 'update', $now);

        $updated = new \App\Repos\NoteDto(
            id:        $existing->id,
            userId:    $existing->userId,
            title:     $title ?: 'Untitled',
            contentMd: $content,
            createdAt: $existing->createdAt,
            updatedAt: $now,
            deletedAt: null,
        );
        $noteRepo->update($updated);
        return $updated;
    });

    header('Content-Type: application/json');
    echo json_encode([
        'id'         => $updated->id,
        'title'      => $updated->title,
        'updated_at' => $updated->updatedAt,
    ]);
});

$router->delete('/api/v1/notes/{id}', function (Request $req, array $args) use ($noteRepo, $noteVersionRepo, $tokenRepo, $userRepo, $pepper, $pdo): void {
    $token  = Middleware::requireApiToken($req, $tokenRepo, $userRepo, 'notes:write', $pepper);
    $userId = $token->userId;

    dbTransactionApi($pdo, function () use ($noteRepo, $noteVersionRepo, $userId, $args): void {
        $existing = $noteRepo->findByIdForUpdate($args['id'], $userId);
        if ($existing === null) {
            apiAbort(404, 'NOT_FOUND', 'Note not found.');
        }

        $noteVersionRepo->createSnapshot($existing, 'delete', time());
        $noteRepo->softDelete($args['id'], $userId);
    });

    header('Content-Type: application/json');
    echo json_encode(['deleted' => true]);
});

// ── Redirect root ──────────────────────────────────────────────────────────
$router->get('/', function (Request $req): void {
    header('Location: /login');
    exit;
});

// ── Dispatch ──────────────────────────────────────────────────────────────
$router->dispatch($request);
