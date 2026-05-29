<?php
namespace App\Controllers;

use App\Response;

/**
 * BaseController — абстрактний базовий клас для всіх контролерів.
 *
 * Реалізує спільну функціональність (спадкування):
 *  - Читання тіла запиту body()
 *  - Валідація обов'язкових полів
 *  - Пагінація параметрів
 *  - Заглушки CRUD-методів (поліморфізм через перевизначення)
 *
 * Конкретні контролери (BookController, CommentController тощо)
 * розширюють цей клас та перевизначають потрібні методи.
 */
abstract class BaseController
{
    /**
     * Читає та декодує JSON-тіло запиту.
     */
    protected static function body(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    /**
     * Перевіряє наявність обов'язкових полів у масиві.
     * При відсутності — повертає HTTP 400.
     */
    protected static function requireFields(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (empty($data[$field])) {
                Response::error("Поле «{$field}» є обов'язковим");
            }
        }
    }

    /**
     * Повертає параметри пагінації з GET-запиту.
     */
    protected static function pagination(int $defaultPer = 10, int $maxPer = 50): array
    {
        return [
            'page'    => max(1, (int)($_GET['page']     ?? 1)),
            'perPage' => min($maxPer, max(1, (int)($_GET['per_page'] ?? $defaultPer))),
        ];
    }

    /**
     * Заглушка index — перевизначається у підкласах (поліморфізм).
     */
    public static function index(): void
    {
        Response::error('Метод index не реалізовано', 501);
    }

    /**
     * Заглушка show — перевизначається у підкласах.
     */
    public static function show(array $params): void
    {
        Response::error('Метод show не реалізовано', 501);
    }

    /**
     * Заглушка create — перевизначається у підкласах.
     */
    public static function create(): void
    {
        Response::error('Метод create не реалізовано', 501);
    }

    /**
     * Заглушка update — перевизначається у підкласах.
     */
    public static function update(array $params): void
    {
        Response::error('Метод update не реалізовано', 501);
    }

    /**
     * Заглушка delete — перевизначається у підкласах.
     */
    public static function delete(array $params): void
    {
        Response::error('Метод delete не реалізовано', 501);
    }
}
