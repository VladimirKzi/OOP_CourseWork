<?php
namespace App\Models;

use App\Database;

/**
 * Comment — модель коментаря.
 * Розширює абстрактний Model (спадкування).
 * Перевизначає toArray(), findById(), boot() (поліморфізм).
 */
class Comment extends Model
{
    public int     $id;
    public string  $text;
    public bool    $isDeleted;
    public bool    $isFlagged;
    public int     $userId;
    public int     $bookId;
    public ?int    $parentId;
    public string  $createdAt;
    public int     $likesCount;
    public int     $dislikesCount;
    public int     $repliesCount;
    public ?string $userVote;
    public array   $author;

    protected static function getTable(): string { return 'comments'; }

    protected function boot(array $row): void
    {
        $this->id            = (int)$row['id'];
        $this->text          = $row['is_deleted'] ? '[видалено]' : $row['text'];
        $this->isDeleted     = (bool)$row['is_deleted'];
        $this->isFlagged     = (bool)$row['is_flagged'];
        $this->userId        = (int)$row['user_id'];
        $this->bookId        = (int)$row['book_id'];
        $this->parentId      = isset($row['parent_id']) ? (int)$row['parent_id'] : null;
        $this->createdAt     = $row['created_at'];
        $this->likesCount    = (int)($row['likes_count']    ?? 0);
        $this->dislikesCount = (int)($row['dislikes_count'] ?? 0);
        $this->repliesCount  = (int)($row['replies_count']  ?? 0);
        $this->userVote      = $row['user_vote'] ?? null;
        $this->author        = [
            'id'         => (int)$row['author_id'],
            'name'       => $row['author_name'],
            'role'       => $row['author_role'],
            'avatar_url' => $row['author_avatar'] ?? null,
        ];
    }

    // ── Поліморфізм: перевизначення toArray() ────────
    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'text'           => $this->text,
            'is_deleted'     => $this->isDeleted,
            'is_flagged'     => $this->isFlagged,
            'user_id'        => $this->userId,
            'book_id'        => $this->bookId,
            'parent_id'      => $this->parentId,
            'created_at'     => $this->createdAt,
            'likes_count'    => $this->likesCount,
            'dislikes_count' => $this->dislikesCount,
            'replies_count'  => $this->repliesCount,
            'user_vote'      => $this->userVote,
            'author'         => $this->author,
        ];
    }

    /** М'яке видалення */
    public function softDelete(): void
    {
        Database::exec('UPDATE comments SET is_deleted = TRUE WHERE id = ?', [$this->id]);
    }

    // ── SELECT з агрегацією та автором ───────────────
    private static function baseSQL(?int $uid): string
    {
        $voteCol = $uid !== null
            ? "(SELECT vote_type::text FROM comment_votes
                WHERE user_id = $uid AND comment_id = c.id LIMIT 1)"
            : 'NULL';

        return "
            SELECT
                c.id, c.text, c.is_deleted, c.is_flagged,
                c.user_id, c.book_id, c.parent_id, c.created_at,
                u.id         AS author_id,
                u.name       AS author_name,
                u.role       AS author_role,
                u.avatar_url AS author_avatar,
                COALESCE(SUM(CASE WHEN v.vote_type = 'like'    THEN 1 ELSE 0 END), 0) AS likes_count,
                COALESCE(SUM(CASE WHEN v.vote_type = 'dislike' THEN 1 ELSE 0 END), 0) AS dislikes_count,
                COUNT(DISTINCT rp.id) AS replies_count,
                $voteCol AS user_vote
            FROM comments c
            JOIN users u ON u.id = c.user_id
            LEFT JOIN comment_votes v  ON v.comment_id = c.id
            LEFT JOIN comments      rp ON rp.parent_id = c.id
        ";
    }

    // ── Перевизначення findById() (поліморфізм) ──────
    public static function findById(int $id, ?int $uid = null): ?static
    {
        $row = Database::one(
            self::baseSQL($uid) . ' WHERE c.id = ? GROUP BY c.id, u.id',
            [$id]
        );
        return $row ? new static($row) : null;
    }

    public static function forBook(
        int $bookId, string $sort,
        int $page, int $per, ?int $uid
    ): array {
        $total  = (int)(Database::one(
            'SELECT COUNT(*) AS cnt FROM comments WHERE book_id = ? AND parent_id IS NULL',
            [$bookId]
        )['cnt'] ?? 0);

        $order  = $sort === 'popular'
            ? 'likes_count DESC, c.created_at DESC'
            : 'c.created_at DESC';
        $offset = ($page - 1) * $per;

        $rows = Database::query(
            self::baseSQL($uid) .
            ' WHERE c.book_id = ? AND c.parent_id IS NULL
              GROUP BY c.id, u.id ORDER BY ' . $order . ' LIMIT ? OFFSET ?',
            [$bookId, $per, $offset]
        );

        return [
            'items'    => array_map(fn($r) => (new static($r))->toArray(), $rows),
            'total'    => $total, 'page' => $page,
            'per_page' => $per,   'pages' => (int)ceil($total / $per),
        ];
    }

    public static function replies(int $parentId, ?int $uid): array
    {
        $rows = Database::query(
            self::baseSQL($uid) .
            ' WHERE c.parent_id = ? GROUP BY c.id, u.id ORDER BY c.created_at',
            [$parentId]
        );
        return array_map(fn($r) => (new static($r))->toArray(), $rows);
    }

    public static function create(
        string $text, int $userId, int $bookId, ?int $parentId
    ): int {
        return Database::insert(
            'INSERT INTO comments (text, user_id, book_id, parent_id) VALUES (?, ?, ?, ?)',
            [$text, $userId, $bookId, $parentId]
        );
    }

    public static function flagged(): array
    {
        $rows = Database::query(
            self::baseSQL(null) .
            ' WHERE c.is_flagged = TRUE AND c.is_deleted = FALSE
              GROUP BY c.id, u.id ORDER BY c.created_at DESC LIMIT 50',
            []
        );
        return array_map(fn($r) => (new static($r))->toArray(), $rows);
    }
}
