<?php
/**
 * BookRatings — Конфігурація
 * Відредагуйте лише DB_* і JWT_SECRET.
 * Домен/шлях визначається автоматично — хардкодити нічого не потрібно.
 */

// ── PostgreSQL ─────────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_PORT',     '5432');
define('DB_NAME',     'db_Name');
define('DB_USER',     'postgres');
define('DB_PASSWORD', 'db_Pass');          // ← вкажіть ваш пароль

// ── JWT ───────────────────────────────────────────────────
define('JWT_SECRET',     'dfg54ydfgdfgdfg4egdfgdfbookratings-change-this-secret-key');
define('JWT_EXPIRES_IN', 604800);   // 7 днів

// ── Авто-визначення origin (для CORS) ─────────────────────
// Підтримує http://book-rating.io, http://test-book.io, http://localhost тощо
$_scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('CLIENT_URL', $_scheme . '://' . $_host);
