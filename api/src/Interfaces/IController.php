<?php
namespace App\Interfaces;

/**
 * IController — інтерфейс для всіх контролерів системи.
 * Гарантує наявність стандартних CRUD-методів.
 */
interface IController
{
    public static function index(): void;
    public static function show(array $params): void;
    public static function create(): void;
    public static function update(array $params): void;
    public static function delete(array $params): void;
}
