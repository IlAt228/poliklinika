<?php
include 'includes/header.php';

// Инициализация сессии (если не инициализирована в header.php)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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

// Получение ID специалиста из сессии и обеспечение его целочисленного значения
$specialist_id = intval($_SESSION['id']);

// Обновлённый SQL-запрос для получения предстоящих осмотров со связью через health_group_id
$sql = "SELECT 
            se.id AS examination_id,
            CONCAT(s.first_name, ' ', s.last_name, ' ', IFNULL(s.middle_name, '')) AS student_full_name,
            IFNULL(hg.group_name, 'Не назначена') AS health_group,
            sp.full_name AS specialist_name,
            se.examination_date,
            se.status
        FROM 
            student_examinations se
        JOIN 
            students s ON se.student_id = s.id
        JOIN 
            specialists sp ON se.specialist_id = sp.id
        LEFT JOIN 
            health_groups hg ON s.health_group_id = hg.id
        WHERE 
            se.specialist_id = $specialist_id
            AND se.status = 'pending'
        ORDER BY 
            se.examination_date DESC";

// Выполнение запроса
$result = mysqli_query($link, $sql);

if (!$result) {
    die("Ошибка выполнения запроса: " . mysqli_error($link));
}

?>
        
<h2>Предстоящие Осмотры Студентов</h2>

<table border="1" cellpadding="10" cellspacing="0">
    <tr>
        <th>ID Осмотра</th>
        <th>ФИО Студента</th>
        <th>Группа Здоровья</th>
        <th>Специалист</th>
        <th>Дата и Время Осмотра</th>
        <th>Статус</th>
    </tr>
    <?php if (mysqli_num_rows($result) > 0): ?>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['examination_id']); ?></td>
                <td><?php echo htmlspecialchars($row['student_full_name']); ?></td>
                <td><?php echo htmlspecialchars($row['health_group']); ?></td>
                <td><?php echo htmlspecialchars($row['specialist_name']); ?></td>
                <td><?php echo htmlspecialchars($row['examination_date']); ?></td>
                <td>
                    <form action="measurements_form.php" method="GET">
                        <input type="hidden" name="examination_id" value="<?php echo htmlspecialchars($row['examination_id']); ?>">
                        <button type="submit">Заполнить Измерения</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="6">Нет предстоящих осмотров.</td></tr>
    <?php endif; ?>
</table>

<?php
// Закрытие соединения с базой данных
mysqli_close($link);
include 'includes/footer.php';
?>
