<?php
namespace App\Models;

use App\Database;

/**
 * User — модель користувача.
 * Розширює абстрактний базовий клас Model (спадкування).
 * Перевизначає toArray() (поліморфізм).
 */
class User extends Model
{
    public int     $id;
    public string  $name;
    public string  $email;
    public string  $role;
    public ?string $avatarUrl;
    public bool    $isBlocked;
    public bool    $isActive;
    public string  $createdAt;

    protected static function getTable(): string { return 'users'; }

    /** Шаблонний метод — ініціалізує типізовані властивості */
    protected function boot(array $row): void
    {
        $this->id        = (int)$row['id'];
        $this->name      = $row['name'];
        $this->email     = $row['email'];
        $this->role      = $row['role'];
        $this->avatarUrl = $row['avatar_url'] ?? null;
        $this->isBlocked = (bool)$row['is_blocked'];
        $this->isActive  = (bool)$row['is_active'];
        $this->createdAt = $row['created_at'];
    }

    // ── Бізнес-логіка ─────────────────────────────────
    public function isAdmin(): bool      { return $this->role === 'admin'; }
    public function isModerator(): bool  { return in_array($this->role, ['admin', 'moderator'], true); }

    public function initials(): string
    {
        return strtoupper(implode('', array_map(
            fn($w) => mb_substr($w, 0, 1),
            explode(' ', trim($this->name))
        )));
    }

    // ── Поліморфізм: перевизначення toArray() ─────────
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'role'       => $this->role,
            'avatar_url' => $this->avatarUrl,
            'is_blocked' => $this->isBlocked,
            'is_active'  => $this->isActive,
            'created_at' => $this->createdAt,
        ];
    }

    // ── Статичні фабричні методи ─────────────────────
    /** Перевизначення findById() з кастомним SELECT */
    public static function findById(int $id): ?static
    {
        $row = Database::one(
            'SELECT id, name, email, role, avatar_url, is_blocked, is_active, created_at
             FROM users WHERE id = ?',
            [$id]
        );
        return $row ? new static($row) : null;
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::one('SELECT * FROM users WHERE email = ?', [$email]);
    }

    public static function create(string $name, string $email, string $hash): int
    {
        return Database::insert(
            'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)',
            [$name, $email, $hash]
        );
    }

    public static function all(int $page = 1, int $per = 20): array
    {
        return Database::query(
            'SELECT id, name, email, role, avatar_url, is_blocked, is_active, created_at
             FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$per, ($page - 1) * $per]
        );
    }

    public static function recentComments(int $userId): array
    {
        return Database::query(
            'SELECT c.id, c.text, c.book_id, c.created_at,
                    b.title AS book_title, b.cover_emoji
             FROM comments c JOIN books b ON b.id = c.book_id
             WHERE c.user_id = ? AND c.is_deleted = FALSE
             ORDER BY c.created_at DESC LIMIT 20',
            [$userId]
        );
    }
}
