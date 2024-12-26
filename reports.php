<?php
// reports.php

include 'includes/header.php';

// Проверка, вошёл ли специалист
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// Подключение к базе данных
$link = mysqli_connect("localhost", "root", "A@cexlAEI3vMcn)r", "poliklinika");
if (!$link) {
    die("Ошибка подключения: " . mysqli_connect_error());
}

// Получение списка студентов для выбора
$sql_students = "SELECT id, first_name, last_name, middle_name FROM students ORDER BY last_name ASC";
$result_students = mysqli_query($link, $sql_students);
if (!$result_students) {
    die("Ошибка выполнения запроса (список студентов): " . mysqli_error($link));
}
?>

<h2>Создание Отчёта о Студенте</h2>

<form action="generate_report.php" method="POST">
    <label for="student_id">Выберите Студента:</label>
    <select name="student_id" id="student_id" required>
        <option value="">-- Выберите --</option>
        <?php while ($student = mysqli_fetch_assoc($result_students)): ?>
            <option value="<?php echo htmlspecialchars($student['id']); ?>">
                <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name'] . ' ' . $student['middle_name']); ?>
            </option>
        <?php endwhile; ?>
    </select>
    <br><br>
    <label><input type="radio" name="report_type" value="full" checked> Полный Отчёт</label><br>
    <label><input type="radio" name="report_type" value="illness"> Отчёт о Болезнях</label><br><br>
    <button type="submit">Скачать Отчёт</button>
</form>


<?php
mysqli_close($link);
include 'includes/footer.php';
?>
