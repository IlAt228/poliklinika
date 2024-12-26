<?php

session_start();

function pdo(): PDO
{
    static $pdo;

    if (!$pdo) {
        if (file_exists(__DIR__ . '/config.php')) {
            $config = include __DIR__ . '/config.php';
        }
        // Подключение к БД
        $dsn = 'mysql:dbname=' . $config['db_name'] . ';host=' . $config['db_host'];
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    return $pdo;
}

function flash(?string $message = null)
{
    if ($message) {
        $_SESSION['flash'] = $message;
    } else {
        if (!empty($_SESSION['flash'])) { ?>
            <div class="form-container">
                <?= $_SESSION['flash'] ?>
            </div>
        <?php }
        unset($_SESSION['flash']);
    }
}

function check_auth(): bool
{
    return !!($_SESSION['id'] ?? false);
}


// if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 120)) {
//     session_unset();
//     session_destroy();
// }
// $_SESSION['LAST_ACTIVITY'] = time();

// if (!isset($_SESSION['CREATED'])) {
//     $_SESSION['CREATED'] = time();
// } else if (time() - $_SESSION['CREATED'] > 10) {
//     session_regenerate_id(true);
//     $_SESSION['CREATED'] = time();
// }