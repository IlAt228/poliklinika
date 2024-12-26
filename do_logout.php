<?php


require_once __DIR__.'/boot.php';

$_SESSION['id'] = null;
header('Location: login.php');
