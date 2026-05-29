<?php
namespace App\Models;

use App\Database;

/**
 * Book — модель книги.
 * Розширює абстрактний Model (спадкування).
 * Перевизначає toArray() та findById() (поліморфізм).
 */
class Book extends Model
{
    protected static function getTable(): string { return 'books'; }

    protected function boot(array $row): void {}   // дані зберігаються в $this->data

    // ── Поліморфізм: типізований toArray() ───────────
    public function toArray(): array
    {
        return [
            'id'             => (int)$this->data['id'],
            'title'          => $this->data['title'],
            'author'         => $this->data['author'],
            'description'    => $this->data['description']    ?? null,
            'genre'          => $this->data['genre']          ?? null,
            'cover_emoji'    => $this->data['cover_emoji']    ?? '📚',
            'cover_url'      => $this->data['cover_url']      ?? null,
            'isbn'           => $this->data['isbn']           ?? null,
            'published_year' => isset($this->data['published_year'])
                                ? (int)$this->data['published_year'] : null,
            'average_rating' => round((float)($this->data['average_rating'] ?? 0), 1),
            'ratings_count'  => (int)($this->data['ratings_count']  ?? 0),
            'comment_count'  => (int)($this->data['comment_count']  ?? 0),
            'created_at'     => $this->data['created_at'],
        ];
    }

    // ── SELECT з агрегацією ───────────────────────────
    private static function baseSQL(): string
    {
        return '
            SELECT b.*,
                COALESCE(ROUND(AVG(r.value)::numeric, 1), 0)                       AS average_rating,
                COALESCE(COUNT(DISTINCT r.id), 0)                                    AS ratings_count,
                COUNT(DISTINCT c.id) FILTER (WHERE c.is_deleted = FALSE)             AS comment_count
            FROM books b
            LEFT JOIN ratings  r ON r.book_id = b.id
            LEFT JOIN comments c ON c.book_id = b.id
        ';
    }

    // ── Перевизначення findById() (поліморфізм) ──────
    public static function findById(int $id): ?static
    {
        $row = Database::one(self::baseSQL() . ' WHERE b.id = ? GROUP BY b.id', [$id]);
        return $row ? new static($row) : null;
    }

    public static function search(
        ?string $q, ?string $genre,
        int $page, int $per,
        string $sort = 'created_at'
    ): array {
        $safe   = in_array($sort, ['created_at', 'title', 'author']) ? $sort : 'created_at';
        $conds  = [];
        $params = [];

        if ($q) {
            $conds[]  = '(b.title ILIKE ? OR b.author ILIKE ?)';
            $params[] = "%$q%"; $params[] = "%$q%";
        }
        if ($genre) {
            $conds[]  = 'b.genre ILIKE ?';
            $params[] = "%$genre%";
        }

        $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';
        $total = (int)(Database::one(
            "SELECT COUNT(*) AS cnt FROM books b $where", $params
        )['cnt'] ?? 0);

        $params[] = $per;
        $params[] = ($page - 1) * $per;

        $rows = Database::query(
            self::baseSQL() . "$where GROUP BY b.id ORDER BY b.$safe DESC LIMIT ? OFFSET ?",
            $params
        );

        return [
            'items'    => array_map(fn($r) => (new static($r))->toArray(), $rows),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per,
            'pages'    => (int)ceil($total / $per),
        ];
    }

    public static function create(array $d, int $by): int
    {
        return Database::insert(
            'INSERT INTO books
                (title, author, description, genre, cover_emoji,
                 cover_url, isbn, published_year, created_by_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['title'],
                $d['author'],
                $d['description']    ?? null,
                $d['genre']          ?? null,
                $d['cover_emoji']    ?? '📚',
                $d['cover_url']      ?? null,
                $d['isbn']           ?? null,
                $d['published_year'] ?? null,
                $by,
            ]
        );
    }
}
