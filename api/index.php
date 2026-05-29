<?php
declare(strict_types=1);

define ( 'ROOT_DIR', dirname ( __FILE__, 2 ) );
define ( 'API_DIR', ROOT_DIR . '/api' );

require_once API_DIR . '/config.php';

spl_autoload_register(function (string $class): void {
    $rel  = str_replace(['App\\', '\\'], ['', '/'], $class);
    $file = API_DIR . "/src/$rel.php";
    if (file_exists($file)) require_once $file;
});

require_once API_DIR . '/src/Models/Model.php';
require_once API_DIR . '/src/Controllers/Controllers.php';

// ── CORS ──────────────────────────────────────────────────
header('Access-Control-Allow-Origin: ' . CLIENT_URL);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
header('Content-Type: application/json; charset=utf-8');

use App\{Router, Response};
use App\Controllers\{AuthController, BookController, CommentController, UserController, RatingController};

$router = new Router();

// Health + debug (показує чи доходить Authorization header)
$router->get('/health', function () {
    $token   = null;
    $sources = [];

    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $k) {
        if (!empty($_SERVER[$k])) { $sources[] = $k; $token = $_SERVER[$k]; }
    }
    if (function_exists('apache_request_headers')) {
        $h = apache_request_headers();
        if (!empty($h['Authorization']))   { $sources[] = 'apache_request_headers[Authorization]';   $token = $h['Authorization']; }
        if (!empty($h['authorization']))   { $sources[] = 'apache_request_headers[authorization]';   $token = $h['authorization']; }
    }

    Response::json([
        'status'        => 'ok',
        'domain'        => $_SERVER['HTTP_HOST'] ?? '?',
        'auth_received' => !empty($token),
        'auth_source'   => $sources,
        'auth_preview'  => $token ? substr($token, 0, 30) . '...' : null,
    ]);
});

$router->post('/auth/register', fn()  => AuthController::register());
$router->post('/auth/login',    fn()  => AuthController::login());
$router->get('/auth/me',        fn()  => AuthController::me());

$router->get('/books',          fn()  => BookController::index());
$router->get('/books/:id',      fn($p)=> BookController::show($p));
$router->post('/books',         fn()  => BookController::create());
$router->put('/books/:id',      fn($p)=> BookController::update($p));
$router->delete('/books/:id',   fn($p)=> BookController::delete($p));

$router->get('/comments/flagged',      fn()  => CommentController::flagged());
$router->get('/comments/book/:bookId', fn($p)=> CommentController::forBook($p));
$router->get('/comments/:id/replies',  fn($p)=> CommentController::replies($p));
$router->post('/comments',             fn()  => CommentController::create());
$router->post('/comments/vote',        fn()  => CommentController::vote());
$router->post('/comments/:id/flag',    fn($p)=> CommentController::flag($p));
$router->put('/comments/:id',          fn($p)=> CommentController::update($p));
$router->delete('/comments/:id',       fn($p)=> CommentController::delete($p));

$router->get('/users',               fn()  => UserController::index());
$router->put('/users/me',            fn()  => UserController::updateMe());
$router->get('/users/:id',           fn($p)=> UserController::show($p));
$router->post('/users/:id/block',    fn($p)=> UserController::block($p));
$router->post('/users/:id/role',     fn($p)=> UserController::changeRole($p));
$router->get('/users/:id/comments',  fn($p)=> UserController::comments($p));

$router->post('/ratings',                fn()  => RatingController::create());
$router->get('/ratings/book/:bookId/my', fn($p)=> RatingController::my($p));

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
