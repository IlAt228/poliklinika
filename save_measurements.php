<?php
// save_measurements.php

// Инициализация сессии (если не инициализирована в header.php)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Проверка, вошёл ли специалист
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// Проверка наличия необходимых данных в POST
if (
    !isset($_POST['examination_id']) ||
    !isset($_POST['student_id']) ||
    !isset($_POST['measurements']) ||
    !isset($_POST['health_group_id'])
) {
    die("Недостаточно данных для сохранения.");
}

$examination_id = intval($_POST['examination_id']);
$student_id = intval($_POST['student_id']);
$measurements = $_POST['measurements'];
$diseases = isset($_POST['diseases']) ? $_POST['diseases'] : [];
$sick_days = isset($_POST['sick_days']) ? intval($_POST['sick_days']) : 0;
$health_group_id = intval($_POST['health_group_id']);

// Подключение к базе данных
$link = mysqli_connect("localhost", "root", "A@cexlAEI3vMcn)r", "poliklinika");
if (!$link) {
    die("Ошибка подключения: " . mysqli_connect_error());
}

// Начало транзакции
mysqli_begin_transaction($link);

try {
    // 1. Сохранение измерений
    $insert_measurement_stmt = mysqli_prepare($link, "INSERT INTO student_measurements (examination_id, parameter_id, value) VALUES (?, ?, ?)
                                                    ON DUPLICATE KEY UPDATE value = VALUES(value)");
    if (!$insert_measurement_stmt) {
        throw new Exception("Ошибка подготовки запроса измерений: " . mysqli_error($link));
    }

    foreach ($measurements as $parameter_id => $value) {
        // Валидация параметра
        $parameter_id = intval($parameter_id);
        $value = trim($value);
        if ($value === '') {
            throw new Exception("Значение параметра не может быть пустым.");
        }

        mysqli_stmt_bind_param($insert_measurement_stmt, "iis", $examination_id, $parameter_id, $value);
        if (!mysqli_stmt_execute($insert_measurement_stmt)) {
            throw new Exception("Ошибка выполнения запроса измерений: " . mysqli_stmt_error($insert_measurement_stmt));
        }
    }
    mysqli_stmt_close($insert_measurement_stmt);

    // 2. Обновление группы здоровья студента
    $update_health_group_stmt = mysqli_prepare($link, "UPDATE students SET health_group_id = ? WHERE id = ?");
    if (!$update_health_group_stmt) {
        throw new Exception("Ошибка подготовки запроса обновления группы здоровья: " . mysqli_error($link));
    }

    mysqli_stmt_bind_param($update_health_group_stmt, "ii", $health_group_id, $student_id);
    if (!mysqli_stmt_execute($update_health_group_stmt)) {
        throw new Exception("Ошибка выполнения запроса обновления группы здоровья: " . mysqli_stmt_error($update_health_group_stmt));
    }
    mysqli_stmt_close($update_health_group_stmt);

    // 3. Сохранение болезней
    if (!empty($diseases)) {
        // Очистка существующих болезней для данного осмотра
        $delete_sd_sql = "DELETE FROM student_diseases WHERE examination_id = ?";
        $stmt_delete_sd = mysqli_prepare($link, $delete_sd_sql);
        if (!$stmt_delete_sd) {
            throw new Exception("Ошибка подготовки запроса удаления болезней: " . mysqli_error($link));
        }
        mysqli_stmt_bind_param($stmt_delete_sd, "i", $examination_id);
        if (!mysqli_stmt_execute($stmt_delete_sd)) {
            throw new Exception("Ошибка выполнения запроса удаления болезней: " . mysqli_stmt_error($stmt_delete_sd));
        }
        mysqli_stmt_close($stmt_delete_sd);

        // Вставка выбранных болезней
        $insert_disease_stmt = mysqli_prepare($link, "INSERT INTO student_diseases (examination_id, disease_id) VALUES (?, ?)");
        if (!$insert_disease_stmt) {
            throw new Exception("Ошибка подготовки запроса болезней: " . mysqli_error($link));
        }

        foreach ($diseases as $disease_id) {
            $disease_id = intval($disease_id);
            if ($disease_id > 0) {
                mysqli_stmt_bind_param($insert_disease_stmt, "ii", $examination_id, $disease_id);
                if (!mysqli_stmt_execute($insert_disease_stmt)) {
                    throw new Exception("Ошибка выполнения запроса болезней: " . mysqli_stmt_error($insert_disease_stmt));
                }
            }
        }
        mysqli_stmt_close($insert_disease_stmt);
    }

    // 4. Обновление количества больничных дней в осмотре
    $update_sick_days_stmt = mysqli_prepare($link, "UPDATE student_examinations SET sick_days = ? WHERE id = ?");
    if (!$update_sick_days_stmt) {
        throw new Exception("Ошибка подготовки запроса обновления больничных дней: " . mysqli_error($link));
    }

    mysqli_stmt_bind_param($update_sick_days_stmt, "ii", $sick_days, $examination_id);
    if (!mysqli_stmt_execute($update_sick_days_stmt)) {
        throw new Exception("Ошибка выполнения запроса обновления больничных дней: " . mysqli_stmt_error($update_sick_days_stmt));
    }
    mysqli_stmt_close($update_sick_days_stmt);

    // 5. Обновление статуса осмотра на 'completed'
    $update_status_stmt = mysqli_prepare($link, "UPDATE student_examinations SET status = 'completed' WHERE id = ?");
    if (!$update_status_stmt) {
        throw new Exception("Ошибка подготовки запроса обновления статуса: " . mysqli_error($link));
    }

    mysqli_stmt_bind_param($update_status_stmt, "i", $examination_id);
    if (!mysqli_stmt_execute($update_status_stmt)) {
        throw new Exception("Ошибка выполнения запроса обновления статуса: " . mysqli_stmt_error($update_status_stmt));
    }
    mysqli_stmt_close($update_status_stmt);

    // Фиксация транзакции
    mysqli_commit($link);

    // Перенаправление после успешного сохранения
    header("Location: reports.php");
    exit();

} catch (Exception $e) {
    // Откат транзакции в случае ошибки
    mysqli_rollback($link);
    die("Произошла ошибка при сохранении данных: " . $e->getMessage());
}

// Закрытие соединения с базой данных
mysqli_close($link);
?>
