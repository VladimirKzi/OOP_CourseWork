<?php
namespace App\Interfaces;

/**
 * IModel — інтерфейс для всіх моделей системи.
 * Визначає обов'язковий контракт: кожна модель повинна
 * вміти серіалізуватися в масив та знаходити себе за ID.
 */
interface IModel
{
    /**
     * Серіалізує об'єкт у масив для JSON-відповіді.
     */
    public function toArray(): array;

    /**
     * Знаходить запис за первинним ключем.
     */
    public static function findById(int $id): ?static;
}
