<?php
require_once dirname(__DIR__) . '/boot.php';
?>


<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Transfer of data to the university</title>
    <link rel="stylesheet" href="http://localhost/poliklinika/assets/css/style.css">
</head>

<body>

<?php if (isset($_SESSION['id'])) { ?> 
<header>
    <div class="header-container">
        <a href="index.php" class="logout-button">Главная</a>
        <a href="reports.php" class="logout-button">Отчёты</a>
        <a href="do_logout.php" class="logout-button">Выход</a>
    </div>
</header>
<?php } ?>

<main class="content">