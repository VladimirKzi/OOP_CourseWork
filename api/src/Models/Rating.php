<?php
namespace App\Models;

use App\Database;

/**
 * Rating — модель оцінки книги.
 * Розширює абстрактний Model (спадкування).
 * Перевизначає toArray() (поліморфізм).
 */
class Rating extends Model
{
    protected static function getTable(): string { return 'ratings'; }

    protected function boot(array $row): void {}

    public function toArray(): array
    {
        return [
            'id'         => (int)$this->data['id'],
            'value'      => (float)$this->data['value'],
            'user_id'    => (int)$this->data['user_id'],
            'book_id'    => (int)$this->data['book_id'],
            'created_at' => $this->data['created_at'],
            'updated_at' => $this->data['updated_at'],
        ];
    }

    /** INSERT … ON CONFLICT DO UPDATE (upsert) */
    public static function upsert(int $userId, int $bookId, float $value): static
    {
        $row = Database::one(
            'INSERT INTO ratings (value, user_id, book_id)
             VALUES (?, ?, ?)
             ON CONFLICT (user_id, book_id)
             DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()
             RETURNING *',
            [$value, $userId, $bookId]
        );
        return new static($row);
    }

    public static function myRating(int $userId, int $bookId): ?static
    {
        $row = Database::one(
            'SELECT * FROM ratings WHERE user_id = ? AND book_id = ?',
            [$userId, $bookId]
        );
        return $row ? new static($row) : null;
    }
}
