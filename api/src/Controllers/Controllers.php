<?php
namespace App\Controllers;

use App\{Database, Response};
use App\Middleware\Auth;
use App\Models\{User, Book, Comment, Rating};

// ── AuthController ────────────────────────────────────────
/**
 * AuthController — управління авторизацією.
 * Розширює BaseController (спадкування).
 * Перевизначає create() як register (поліморфізм).
 */
class AuthController extends BaseController
{
    public static function create(): void          // поліморфне перевизначення → register
    {
        self::register();
    }

    public static function register(): void
    {
        $b = self::body();
        self::requireFields($b, ['name', 'email', 'password']);

        $name  = trim($b['name']);
        $email = trim($b['email']);
        $pass  = $b['password'];

        if (strlen($name) < 2)                          Response::error("Ім'я мінімум 2 символи");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) Response::error('Невалідний email');
        if (strlen($pass) < 6)                          Response::error('Пароль мінімум 6 символів');
        if (User::findByEmail($email))                  Response::error('Email вже зареєстровано', 409);

        $id = User::create($name, $email, password_hash($pass, PASSWORD_BCRYPT));
        Response::json(['token' => Auth::createToken($id), 'user' => User::findById($id)->toArray()], 201);
    }

    public static function login(): void
    {
        $b     = self::body();
        $email = trim($b['email'] ?? '');
        $pass  = $b['password'] ?? '';

        $row = User::findByEmail($email);
        if (!$row || !password_verify($pass, $row['password_hash']))
            Response::error('Невірний email або пароль', 401);
        if ($row['is_blocked'])
            Response::error('Акаунт заблоковано', 403);

        $user = new User($row);
        Response::json(['token' => Auth::createToken($user->id), 'user' => $user->toArray()]);
    }

    public static function me(): void
    {
        $u = Auth::require();
        Response::json(User::findById($u['id'])->toArray());
    }

    // Заглушки (поліморфізм від BaseController)
    public static function index(): void           { Response::error('Not allowed', 405); }
    public static function show(array $p): void    { Response::error('Not allowed', 405); }
    public static function update(array $p): void  { Response::error('Not allowed', 405); }
    public static function delete(array $p): void  { Response::error('Not allowed', 405); }
}

// ── BookController ────────────────────────────────────────
/**
 * BookController — CRUD для книг.
 * Повністю перевизначає всі методи BaseController (поліморфізм).
 */
class BookController extends BaseController
{
    public static function index(): void
    {
        ['page' => $page, 'perPage' => $per] = self::pagination();
        Response::json(Book::search(
            $_GET['q']     ?? null,
            $_GET['genre'] ?? null,
            $page, $per,
            $_GET['sort']  ?? 'created_at'
        ));
    }

    public static function show(array $p): void
    {
        $book = Book::findById((int)$p['id']);
        if (!$book) Response::error('Не знайдено', 404);
        Response::json($book->toArray());
    }

    public static function create(): void
    {
        $user = Auth::requireModerator();
        $b    = self::body();
        self::requireFields($b, ['title', 'author']);

        if (!empty($b['isbn']) && Database::one('SELECT id FROM books WHERE isbn = ?', [$b['isbn']]))
            Response::error('ISBN вже існує', 409);

        $id = Book::create($b, $user['id']);
        Response::json(Book::findById($id)->toArray(), 201);
    }

    public static function update(array $p): void
    {
        Auth::requireModerator();
        $book = Book::findById((int)$p['id']);
        if (!$book) Response::error('Не знайдено', 404);

        $b      = self::body();
        $fields = ['title', 'author', 'description', 'genre', 'cover_emoji', 'cover_url', 'published_year'];
        $sets   = []; $vals = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $b)) { $sets[] = "$f = ?"; $vals[] = $b[$f]; }
        }
        if (!$sets) Response::error('Немає змін');
        $vals[] = (int)$p['id'];
        Database::exec('UPDATE books SET ' . implode(', ', $sets) . ' WHERE id = ?', $vals);
        Response::json(Book::findById((int)$p['id'])->toArray());
    }

    public static function delete(array $p): void
    {
        Auth::requireAdmin();
        if (!Book::findById((int)$p['id'])) Response::error('Не знайдено', 404);
        Database::exec('DELETE FROM books WHERE id = ?', [(int)$p['id']]);
        Response::noContent();
    }
}

// ── CommentController ─────────────────────────────────────
/**
 * CommentController — управління коментарями.
 * Розширює BaseController, перевизначає всі методи (поліморфізм).
 * Додає специфічні методи: vote(), flag(), replies(), flagged().
 */
class CommentController extends BaseController
{
    public static function index(): void
    {
        Response::error('Використовуйте /comments/book/:bookId', 400);
    }

    public static function show(array $p): void
    {
        $uid     = Auth::user()['id'] ?? null;
        $comment = Comment::findById((int)$p['id'], $uid);
        if (!$comment) Response::error('Не знайдено', 404);
        Response::json($comment->toArray());
    }

    public static function forBook(array $p): void
    {
        $bookId = (int)$p['bookId'];
        if (!Database::one('SELECT id FROM books WHERE id = ?', [$bookId]))
            Response::error('Книгу не знайдено', 404);

        ['page' => $page, 'perPage' => $per] = self::pagination(15, 100);
        $uid = Auth::user()['id'] ?? null;
        Response::json(Comment::forBook($bookId, $_GET['sort'] ?? 'new', $page, $per, $uid));
    }

    public static function replies(array $p): void
    {
        Response::json(Comment::replies((int)$p['id'], Auth::user()['id'] ?? null));
    }

    public static function flagged(): void
    {
        Auth::requireModerator();
        Response::json(Comment::flagged());
    }

    public static function create(): void
    {
        $user   = Auth::require();
        $b      = self::body();
        $text   = trim($b['text'] ?? '');
        $bookId = (int)($b['book_id'] ?? 0);

        if (strlen($text) < 1 || strlen($text) > 5000)
            Response::error('Текст від 1 до 5000 символів');
        if (!$bookId || !Database::one('SELECT id FROM books WHERE id = ?', [$bookId]))
            Response::error('Книгу не знайдено', 404);

        $parentId = isset($b['parent_id']) ? (int)$b['parent_id'] : null;
        if ($parentId && !Database::one('SELECT id FROM comments WHERE id = ?', [$parentId]))
            Response::error('Батьківський коментар не знайдено', 404);

        $id = Comment::create($text, $user['id'], $bookId, $parentId);
        Response::json(Comment::findById($id, $user['id'])->toArray(), 201);
    }

    public static function update(array $p): void
    {
        $user    = Auth::require();
        $comment = Comment::findById((int)$p['id']);
        if (!$comment) Response::error('Не знайдено', 404);

        $isMod = in_array($user['role'], ['admin', 'moderator'], true);
        if ($comment->userId !== $user['id'] && !$isMod) Response::error('Немає доступу', 403);

        $text = trim(self::body()['text'] ?? '');
        if (!$text) Response::error('Текст порожній');
        Database::exec('UPDATE comments SET text = ? WHERE id = ?', [$text, $comment->id]);
        Response::json(Comment::findById($comment->id, $user['id'])->toArray());
    }

    public static function delete(array $p): void
    {
        $user    = Auth::require();
        $comment = Comment::findById((int)$p['id']);
        if (!$comment) Response::error('Не знайдено', 404);

        $isMod = in_array($user['role'], ['admin', 'moderator'], true);
        if ($comment->userId !== $user['id'] && !$isMod) Response::error('Немає доступу', 403);

        $comment->softDelete();
        Response::noContent();
    }

    public static function vote(): void
    {
        $user = Auth::require();
        $b    = self::body();
        $cid  = (int)($b['comment_id'] ?? 0);
        $type = $b['vote_type'] ?? '';

        if (!in_array($type, ['like', 'dislike'], true)) Response::error('vote_type: like або dislike');
        if (!Database::one('SELECT id FROM comments WHERE id = ?', [$cid]))
            Response::error('Не знайдено', 404);

        $existing = Database::one(
            'SELECT id, vote_type FROM comment_votes WHERE user_id = ? AND comment_id = ?',
            [$user['id'], $cid]
        );
        if ($existing) {
            if ($existing['vote_type'] === $type) {
                Database::exec('DELETE FROM comment_votes WHERE id = ?', [$existing['id']]);
                Response::json(['action' => 'removed']);
            }
            Database::exec('UPDATE comment_votes SET vote_type = ? WHERE id = ?', [$type, $existing['id']]);
            Response::json(['action' => 'changed']);
        }
        Database::exec(
            'INSERT INTO comment_votes (vote_type, user_id, comment_id) VALUES (?, ?, ?)',
            [$type, $user['id'], $cid]
        );
        Response::json(['action' => 'added']);
    }

    public static function flag(array $p): void
    {
        Auth::require();
        Database::exec('UPDATE comments SET is_flagged = TRUE WHERE id = ?', [(int)$p['id']]);
        Response::json(['flagged' => true]);
    }
}

// ── UserController ────────────────────────────────────────
/**
 * UserController — управління користувачами.
 * Розширює BaseController (спадкування + поліморфізм).
 */
class UserController extends BaseController
{
    public static function index(): void
    {
        Auth::requireAdmin();
        Response::json(User::all());
    }

    public static function show(array $p): void
    {
        $u = User::findById((int)$p['id']);
        if (!$u) Response::error('Не знайдено', 404);
        Response::json($u->toArray());
    }

    public static function create(): void  { Response::error('Not allowed', 405); }

    public static function update(array $p): void
    {
        $cur  = Auth::require();
        $b    = self::body();
        $sets = []; $vals = [];
        if (!empty($b['name']))               { $sets[] = 'name = ?';       $vals[] = trim($b['name']); }
        if (array_key_exists('avatar_url', $b)){ $sets[] = 'avatar_url = ?'; $vals[] = $b['avatar_url']; }
        if (!$sets) Response::error('Немає змін');
        $vals[] = $cur['id'];
        Database::exec('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?', $vals);
        Response::json(User::findById($cur['id'])->toArray());
    }

    public static function delete(array $p): void  { Response::error('Not allowed', 405); }

    public static function block(array $p): void
    {
        Auth::requireAdmin();
        $u = User::findById((int)$p['id']);
        if (!$u) Response::error('Не знайдено', 404);
        if ($u->isAdmin()) Response::error('Не можна заблокувати адміна', 400);
        $r = Database::one(
            'UPDATE users SET is_blocked = NOT is_blocked WHERE id = ?
             RETURNING id, name, email, role, is_blocked, is_active, created_at, avatar_url',
            [$u->id]
        );
        Response::json($r);
    }

    public static function changeRole(array $p): void
    {
        Auth::requireAdmin();
        $role = self::body()['role'] ?? '';
        if (!in_array($role, ['user', 'moderator', 'admin'], true)) Response::error('Невалідна роль');
        $r = Database::one(
            'UPDATE users SET role = ? WHERE id = ?
             RETURNING id, name, email, role, is_blocked, is_active, created_at, avatar_url',
            [$role, (int)$p['id']]
        );
        if (!$r) Response::error('Не знайдено', 404);
        Response::json($r);
    }

    public static function comments(array $p): void
    {
        if (!User::findById((int)$p['id'])) Response::error('Не знайдено', 404);
        Response::json(User::recentComments((int)$p['id']));
    }
}

// ── RatingController ──────────────────────────────────────
/**
 * RatingController — управління оцінками.
 * Розширює BaseController (спадкування).
 */
class RatingController extends BaseController
{
    public static function index(): void        { Response::error('Not allowed', 405); }
    public static function show(array $p): void { Response::error('Not allowed', 405); }
    public static function update(array $p): void { Response::error('Not allowed', 405); }
    public static function delete(array $p): void { Response::error('Not allowed', 405); }

    public static function create(): void
    {
        $user   = Auth::require();
        $b      = self::body();
        $bookId = (int)($b['book_id'] ?? 0);
        $value  = (float)($b['value']   ?? 0);

        if (!$bookId || $value < 1 || $value > 5)
            Response::error("book_id та value (1–5) обов'язкові");
        if (!Database::one('SELECT id FROM books WHERE id = ?', [$bookId]))
            Response::error('Книгу не знайдено', 404);

        Response::json(Rating::upsert($user['id'], $bookId, $value)->toArray(), 201);
    }

    public static function my(array $p): void
    {
        $user   = Auth::require();
        $rating = Rating::myRating($user['id'], (int)$p['bookId']);
        if (!$rating) Response::error('Не знайдено', 404);
        Response::json($rating->toArray());
    }
}
