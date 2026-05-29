# BookRatings — WampServer, будь-який домен

Запускається під **будь-яким доменом** без зміни коду:
`book-rating.io`, `test-book.io`, `localhost`, `mybooks.local` — все працює.

---

## Стек

| Шар       | Технологія                                           |
|-----------|------------------------------------------------------|
| Frontend  | Vanilla HTML/CSS/JS (без React, без npm, без build)  |
| Backend   | PHP 8.3 (без Composer, без фреймворків)              |
| БД        | PostgreSQL                                           |
| Сервер    | Apache (WampServer)                                  |
| JWT       | Власна реалізація HS256 через `hash_hmac()`          |

---

## Встановлення

### 1. Скопіюйте файли

```
C:\wamp64\www\book-rating.io\
```

### 2. Увімкніть модулі у WampServer

Лівий клік на іконку WampServer:
- **Apache → Apache modules** → ✅ `rewrite_module`
- **PHP → PHP extensions** → ✅ `php_pdo_pgsql` + ✅ `php_pgsql`

Перезапустіть WampServer.

### 3. Налаштуйте PostgreSQL

```sql
-- у pgAdmin або psql:
CREATE DATABASE bookratings;
```

Відредагуйте `api/config.php`:
```php
define('DB_USER',     'postgres');
define('DB_PASSWORD', 'your_password');
define('JWT_SECRET',  'your-secret-key');
```

### 4. Міграції та seed

Відкрийте у браузері:
```
http://book-rating.io/api/migrate.php
http://book-rating.io/api/seed.php
```

### 5. Налаштуйте домен (опційно)

#### a) Відредагуйте httpd-vhosts.conf

Файл: `C:\wamp64\bin\apache\apache2.4.x\conf\extra\httpd-vhosts.conf`

Вставте вміст файлу `vhosts.conf` з цього проєкту або додайте блок:

```apache
<VirtualHost *:80>
    ServerName book-rating.io
    DocumentRoot "C:/wamp64/www/book-rating.io"
    <Directory "C:/wamp64/www/book-rating.io">
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### b) Додайте домен у hosts

Файл: `C:\Windows\System32\drivers\etc\hosts`
(відкрити Notepad **від імені адміністратора**)

```
127.0.0.1    book-rating.io
127.0.0.1    test-book.io
127.0.0.1    mybooks.local
```

#### c) Перезапустіть WampServer

Відкрийте `http://book-rating.io` — готово!

---

## Як це працює без прив'язки до домену

**Бекенд** (`api/config.php`):
```php
// CORS origin визначається автоматично з поточного запиту
$_scheme = (!empty($_SERVER['HTTPS'])) ? 'https' : 'http';
define('CLIENT_URL', $_scheme . '://' . $_SERVER['HTTP_HOST']);
```

**Фронтенд** (`js/config.js`):
```js
// Завжди відносний шлях — не залежить від домену
const API = '/api';
```

**Apache** (`.htaccess`):
```apache
RewriteBase /   ← корінь домену, не /bookratings/
```

---

## Тестові акаунти (після seed)

| Роль         | Email               | Пароль    |
|--------------|---------------------|-----------|
| Адмін        | admin@books.ua      | admin123  |
| Модератор    | oksana@email.ua     | pass1234  |
| Читач        | olena@email.ua      | pass1234  |
| Заблокований | ivan@email.ua       | pass1234  |

---

## Структура файлів

```
bookratings/
├── .htaccess           ← RewriteBase / (корінь домену)
├── index.html          ← SPA shell
├── vhosts.conf         ← Шаблони Apache VirtualHost
├── css/style.css
├── js/
│   ├── config.js       ← const API = '/api'
│   ├── api.js          ← fetch-клієнт
│   ├── auth.js         ← JWT + localStorage
│   ├── toast.js
│   ├── utils.js        ← el(), fmtDate(), pagination()...
│   ├── router.js       ← hash-router (#/, #/books/3...)
│   ├── app.js          ← bootstrap
│   ├── components/nav.js
│   ├── components/comment.js
│   └── pages/          ← home, book, auth-page, profile, admin
└── api/
    ├── .htaccess       ← RewriteBase /api/
    ├── index.php       ← front controller
    ├── config.php      ← DB + JWT (єдиний файл для редагування)
    ├── migrate.php
    ├── seed.php
    ├── migrations/001_init.sql
    └── src/
        ├── JWT.php
        ├── Database.php
        ├── Router.php
        ├── Response.php
        ├── Middleware/Auth.php
        ├── Models/Models.php
        └── Controllers/Controllers.php
```
