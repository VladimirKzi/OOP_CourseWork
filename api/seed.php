<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/JWT.php';
require_once __DIR__ . '/src/Database.php';
use App\Database;

echo '<pre>';
echo "🌱 Seeding database...\n\n";

Database::get()->exec('TRUNCATE comment_votes, ratings, comments, books, users RESTART IDENTITY CASCADE');

// ── Users ────────────────────────────────────────────────
$userData = [
    ['Адмін Головний',  'admin@books.ua',  'admin123', 'admin'],
    ['Олена Петрова',   'olena@email.ua',  'pass1234', 'user'],
    ['Максим Коваль',   'max@email.ua',    'pass1234', 'user'],
    ['Марія Сидоренко', 'maria@email.ua',  'pass1234', 'user'],
    ['Іван Мельник',    'ivan@email.ua',   'pass1234', 'user'],
    ['Оксана Ткач',     'oksana@email.ua', 'pass1234', 'moderator'],
];

$uids = [];
foreach ($userData as [$name, $email, $pw, $role]) {
    $uids[] = Database::insert(
        'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
        [$name, $email, password_hash($pw, PASSWORD_BCRYPT), $role]
    );
}
Database::exec("UPDATE users SET is_blocked = TRUE WHERE email = 'ivan@email.ua'");
echo count($uids) . " users created\n";

// ── Books ────────────────────────────────────────────────
$booksData = [
    ['Кобзар',               'Тарас Шевченко',        'Збірка поезій — символ українського духу.',        'Поезія',  '📜', 1840],
    ['Лісова пісня',         'Леся Українка',         'Драма-феєрія про кохання мавки Лісової.',          'Драма',   '🌿', 1911],
    ['Тіні забутих предків', 'Михайло Коцюбинський',  'Повість про кохання на тлі карпатської природи.',  'Проза',   '🏔️', 1912],
    ['Майстер і Маргарита',  'Михайло Булгаков',      'Роман про диявола у Москві.',                      'Роман',   '🐱', 1967],
    ['Дракула',              'Брем Стокер',           'Готичний роман у формі листів та щоденників.',     'Містика', '🦇', 1897],
    ['Ворошиловград',        'Сергій Жадан',          "Роман про повернення додому та пам'ять.",          'Роман',   '🏭', 2010],
    ['Місто',                "Валер'ян Підмогильний", 'Роман про хлопця зі села у великому місті.',       'Роман',   '🌆', 1928],
    ['Солодка Даруся',       'Марія Матіос',          'Трагічна сімейна сага на Буковині.',               'Проза',   '🌸', 2004],
];

$bids = [];
foreach ($booksData as [$title, $author, $desc, $genre, $emoji, $year]) {
    $bids[] = Database::insert(
        'INSERT INTO books (title, author, description, genre, cover_emoji, published_year, created_by_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$title, $author, $desc, $genre, $emoji, $year, $uids[0]]
    );
}
echo count($bids) . " books created\n";

// ── Ratings ──────────────────────────────────────────────
$raters = array_slice($uids, 1);
foreach ($bids as $bid) {
    shuffle($raters);
    foreach (array_slice($raters, 0, rand(2, 4)) as $uid) {
        Database::exec(
            'INSERT INTO ratings (value, user_id, book_id)
             VALUES (?, ?, ?)
             ON CONFLICT (user_id, book_id) DO NOTHING',
            [round(mt_rand(35, 50) / 10, 1), $uid, $bid]
        );
    }
}
echo "Ratings created\n";

// ── Comments ─────────────────────────────────────────────
$rootTexts  = [
    'Неперевершений твір! Читаю вже втретє і кожного разу відкриваю щось нове.',
    'Абсолютна класика — рекомендую від щирого серця.',
    'Образи персонажів дуже живі та переконливі.',
    'Не міг відірватись до самого ранку!',
    'Чудова атмосфера, автор майстерно передає дух епохи.',
];
$replyTexts = [
    'Повністю згоден!',
    'Цікава точка зору.',
    "Дякую за рекомендацію!",
    'Так, це справді вражає!',
];

$cids = [];
foreach ($bids as $bid) {
    shuffle($raters);
    foreach (array_slice($raters, 0, rand(2, 4)) as $uid) {
        $cid = Database::insert(
            'INSERT INTO comments (text, user_id, book_id) VALUES (?, ?, ?)',
            [$rootTexts[array_rand($rootTexts)], $uid, $bid]
        );
        $cids[] = $cid;

        if (mt_rand(0, 9) >= 4) {
            $others  = array_values(array_filter($raters, fn($r) => $r !== $uid));
            $replier = $others[array_rand($others)];
            Database::exec(
                'INSERT INTO comments (text, user_id, book_id, parent_id) VALUES (?, ?, ?, ?)',
                [$replyTexts[array_rand($replyTexts)], $replier, $bid, $cid]
            );
        }
    }
}
echo count($cids) . " root comments created\n";

// ── Votes ────────────────────────────────────────────────
foreach ($cids as $cid) {
    shuffle($raters);
    foreach (array_slice($raters, 0, rand(0, 3)) as $uid) {
        Database::exec(
            'INSERT INTO comment_votes (vote_type, user_id, comment_id)
             VALUES (?, ?, ?)
             ON CONFLICT (user_id, comment_id) DO NOTHING',
            [mt_rand(0, 9) > 2 ? 'like' : 'dislike', $uid, $cid]
        );
    }
}
echo "Votes created\n";

echo "\n✅ Done!\n\n";
echo "Credentials:\n";
echo "  Admin:     admin@books.ua  / admin123\n";
echo "  Moderator: oksana@email.ua / pass1234\n";
echo "  User:      olena@email.ua  / pass1234\n";
echo "  Blocked:   ivan@email.ua   / pass1234\n";
echo '</pre>';
