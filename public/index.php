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

Session::start();

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
$tokenRepo = new TokenRepository($pdo);
$pepper    = Env::get('APP_PEPPER');

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

    $remaining = Middleware::loginRateLimit();
    if ($remaining > 0) {
        $mins = (int)ceil($remaining / 60);
        $errors = ["Too many failed attempts. Try again in {$mins} minute(s)."];
        $email  = $req->post('email');
        http_response_code(429);
        require __DIR__ . '/../views/login.php';
        return;
    }

    $email    = trim($req->post('email'));
    $password = $req->post('password');
    $user     = $userRepo->findByEmail($email);

    if ($user === null || !\App\Auth\Password::verify($password, $user->passwordHash)) {
        Middleware::recordFailedLogin();
        $errors = ['Invalid email or password.'];
        require __DIR__ . '/../views/login.php';
        return;
    }

    Middleware::resetLoginAttempts();
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

$router->post('/register', function (Request $req) use ($userRepo): void {
    Middleware::verifyCsrf($req);

    $email    = trim($req->post('email'));
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
        createdAt:    $now,
        updatedAt:    $now,
    );
    $userRepo->create($user);
    session_regenerate_id(true);
    \App\Auth\Session::set('user_id', $user->id);
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

    $title   = trim($req->post('title')) ?: 'Untitled';
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

$router->post('/app/notes/{id}', function (Request $req, array $args) use ($noteRepo): void {
    Middleware::requireAuth($req);
    Middleware::verifyCsrf($req);
    $userId = \App\Auth\Session::userId();
    $note   = $noteRepo->findById($args['id'], $userId);
    if ($note === null) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><body><h1>Note not found.</h1></body></html>';
        exit;
    }

    $title   = trim($req->post('title')) ?: 'Untitled';
    $content = $req->post('content_md');

    $updated = new \App\Repos\NoteDto(
        id:        $note->id,
        userId:    $note->userId,
        title:     $title,
        contentMd: $content,
        createdAt: $note->createdAt,
        updatedAt: time(),
        deletedAt: null,
    );
    $noteRepo->update($updated);
    header('Location: /app/notes/' . $note->id);
    exit;
});

$router->post('/app/notes/{id}/delete', function (Request $req, array $args) use ($noteRepo): void {
    Middleware::requireAuth($req);
    Middleware::verifyCsrf($req);
    $userId = \App\Auth\Session::userId();
    $noteRepo->softDelete($args['id'], $userId);
    header('Location: /app/notes');
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
    $errors = \App\Util\Validate::required($name, 'Token name');
    if ($errors !== []) {
        $tokens = $tokenRepo->listByUser($userId);
        require __DIR__ . '/../views/settings/tokens.php';
        return;
    }

    $raw   = \App\Auth\Token::generate();
    $hash  = \App\Auth\Token::hash($raw, $pepper);
    $scope = $req->post('scopes') ?: 'notes:read';

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

$router->get('/api/v1/notes', function (Request $req) use ($noteRepo, $tokenRepo, $pepper): void {
    $token  = Middleware::requireApiToken($req, $tokenRepo, 'notes:read', $pepper);
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

$router->post('/api/v1/notes', function (Request $req) use ($noteRepo, $tokenRepo, $pepper): void {
    $token  = Middleware::requireApiToken($req, $tokenRepo, 'notes:write', $pepper);
    $userId = $token->userId;

    $body = $req->rawBody();
    if (!json_validate($body)) {
        Middleware::apiError(400, 'BAD_REQUEST', 'Request body is not valid JSON.');
    }
    $data = json_decode($body, assoc: true);

    $title   = isset($data['title']) ? trim((string)$data['title']) : 'Untitled';
    $content = isset($data['content_md']) ? (string)$data['content_md'] : '';

    if ($title === '') {
        $title = 'Untitled';
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

$router->patch('/api/v1/notes/{id}', function (Request $req, array $args) use ($noteRepo, $tokenRepo, $pepper): void {
    $token  = Middleware::requireApiToken($req, $tokenRepo, 'notes:write', $pepper);
    $userId = $token->userId;

    $existing = $noteRepo->findById($args['id'], $userId);
    if ($existing === null) {
        Middleware::apiError(404, 'NOT_FOUND', 'Note not found.');
    }

    $body = $req->rawBody();
    if (!json_validate($body)) {
        Middleware::apiError(400, 'BAD_REQUEST', 'Request body is not valid JSON.');
    }
    $data = json_decode($body, assoc: true);

    $title   = isset($data['title'])      ? trim((string)$data['title'])      : $existing->title;
    $content = isset($data['content_md']) ? (string)$data['content_md']        : $existing->contentMd;

    $updated = new \App\Repos\NoteDto(
        id:        $existing->id,
        userId:    $existing->userId,
        title:     $title ?: 'Untitled',
        contentMd: $content,
        createdAt: $existing->createdAt,
        updatedAt: time(),
        deletedAt: null,
    );
    $noteRepo->update($updated);

    header('Content-Type: application/json');
    echo json_encode([
        'id'         => $updated->id,
        'title'      => $updated->title,
        'updated_at' => $updated->updatedAt,
    ]);
});

$router->delete('/api/v1/notes/{id}', function (Request $req, array $args) use ($noteRepo, $tokenRepo, $pepper): void {
    $token  = Middleware::requireApiToken($req, $tokenRepo, 'notes:write', $pepper);
    $userId = $token->userId;

    $existing = $noteRepo->findById($args['id'], $userId);
    if ($existing === null) {
        Middleware::apiError(404, 'NOT_FOUND', 'Note not found.');
    }

    $noteRepo->softDelete($args['id'], $userId);

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
