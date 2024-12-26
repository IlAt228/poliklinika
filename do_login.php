<?php require_once __DIR__.'/boot.php';

// Проверяем наличие пользователя с указанным юзернеймом
$stmt = pdo()->prepare("SELECT * FROM `specialists` WHERE `login` = :login");
$stmt->execute(['login' => $_POST['login']]);
if (!$stmt->rowCount()) {
    flash('Пользователь с такими данными не зарегистрирован');
    header('Location: index.php');
    die;
}
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Проверяем пароль
if (password_verify($_POST['password_hash'], $user['password_hash'])) {
    // Проверяем, не нужно ли использовать более новый алгоритм
    // или другую алгоритмическую стоимость
    // Например, если вы поменяете опции хеширования
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($_POST['password_hash'], PASSWORD_DEFAULT);
        $stmt = pdo()->prepare('UPDATE `specialists` SET `password_hash` = :password_hash WHERE `login` = :login');
        $stmt->execute([    
            'login' => $_POST['login'],
            'password_hash' => $newHash,
        ]);
    }
    $_SESSION['id'] = $user['id'];
    header('Location: index.php');
    die;
}

flash('Пароль неверен');
header('Location: index.php');
