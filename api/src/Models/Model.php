<?php
namespace App\Models;

use App\Database;
use App\Interfaces\IModel;

/**
 * Model — абстрактний базовий клас для всіх моделей.
 *
 * Реалізує спільну функціональність (спадкування):
 *  - Зберігання сирих даних з БД у $data
 *  - Магічний доступ до полів через __get()
 *  - Часові мітки createdAt / updatedAt
 *  - Шаблонний метод toArray() (може бути перевизначений)
 *
 * Кожна підмодель (User, Book, Comment, Rating) розширює
 * цей клас та додає власну специфічну логіку.
 */
abstract class Model implements IModel
{
    protected array $data;

    public function __construct(array $row)
    {
        $this->data = $row;
        $this->boot($row);
    }

    /**
     * Ініціалізація властивостей конкретної моделі.
     * Шаблонний метод (Template Method pattern) —
     * викликається в конструкторі, перевизначається підкласами.
     */
    protected function boot(array $row): void {}

    /**
     * Магічний доступ до полів масиву $data.
     * User->name замість $user->data['name'].
     */
    public function __get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Повертає сирі дані рядка БД.
     */
    public function getRaw(): array
    {
        return $this->data;
    }

    /**
     * Базова реалізація toArray() — повертає всі поля.
     * Підкласи можуть перевизначити (поліморфізм).
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Часова мітка створення запису (якщо є в БД).
     */
    public function getCreatedAt(): ?string
    {
        return $this->data['created_at'] ?? null;
    }

    /**
     * Часова мітка оновлення запису (якщо є в БД).
     */
    public function getUpdatedAt(): ?string
    {
        return $this->data['updated_at'] ?? null;
    }

    /**
     * Базова реалізація findById — шукає рядок за id.
     * Підкласи перевизначають для додаткових JOIN/агрегацій.
     */
    public static function findById(int $id): ?static
    {
        $table = static::getTable();
        $row   = Database::one("SELECT * FROM $table WHERE id = ?", [$id]);
        return $row ? new static($row) : null;
    }

    /**
     * Назва таблиці БД — визначається підкласом.
     * Необхідна для базової реалізації findById().
     */
    protected static function getTable(): string
    {
        return '';
    }

    /**
     * Перетворює рядок з підкреслення в camelCase.
     * Корисний хелпер для підкласів.
     */
    protected static function camel(string $key): string
    {
        return lcfirst(str_replace('_', '', ucwords($key, '_')));
    }
}
