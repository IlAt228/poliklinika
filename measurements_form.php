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

// Проверка наличия examination_id в GET-параметрах
if (!isset($_GET['examination_id'])) {
    die("Не указан ID осмотра.");
}

$examination_id = intval($_GET['examination_id']);
$specialist_id = intval($_SESSION['id']);

// Подключение к базе данных
$link = mysqli_connect("localhost", "root", "A@cexlAEI3vMcn)r", "poliklinika");
if (!$link) {
    die("Ошибка подключения: " . mysqli_connect_error());
}

// Подготовленный SQL-запрос для получения информации об осмотре и текущей группы здоровья
$stmt = mysqli_prepare($link, "SELECT 
                                    se.id AS examination_id,
                                    s.id AS student_id,
                                    s.first_name AS student_first_name,
                                    s.last_name AS student_last_name,
                                    s.middle_name AS student_middle_name,
                                    se.examination_date,
                                    s.health_group_id
                                FROM 
                                    student_examinations se
                                INNER JOIN 
                                    students s ON se.student_id = s.id
                                WHERE 
                                    se.id = ? 
                                    AND se.specialist_id = ?");
if (!$stmt) {
    die("Ошибка подготовки запроса: " . mysqli_error($link));
}
mysqli_stmt_bind_param($stmt, "ii", $examination_id, $specialist_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if (!$result) {
    die("Ошибка выполнения запроса: " . mysqli_error($link));
}

if ($row = mysqli_fetch_assoc($result)) {
    $student_full_name = htmlspecialchars($row['student_last_name'] . ' ' . $row['student_first_name'] . ' ' . $row['student_middle_name']);
    $student_id = intval($row['student_id']);
    $examination_date = htmlspecialchars($row['examination_date']);
    $current_health_group_id = isset($row['health_group_id']) ? intval($row['health_group_id']) : NULL;
} else {
    die("Осмотр не найден или у вас нет доступа к нему.");
}
mysqli_stmt_close($stmt);

// Получение списка медицинских параметров
$param_sql = "SELECT id, parameter_name, unit FROM medical_parameters";
$parameters_result = mysqli_query($link, $param_sql);
if (!$parameters_result) {
    die("Ошибка выполнения запроса (parameters): " . mysqli_error($link));
}

// Получение списка групп здоровья
$hg_sql = "SELECT id, group_name FROM health_groups";
$health_groups_result = mysqli_query($link, $hg_sql);
if (!$health_groups_result) {
    die("Ошибка выполнения запроса (health_groups): " . mysqli_error($link));
}

// Получение списка болезней из таблицы diseases
$diseases_sql = "SELECT id, disease_name FROM diseases ORDER BY disease_name ASC";
$diseases_result = mysqli_query($link, $diseases_sql);
if (!$diseases_result) {
    die("Ошибка выполнения запроса (diseases): " . mysqli_error($link));
}
?>
    
<h2>Заполнение Измерений для Осмотра №<?php echo $examination_id; ?></h2>
<p><strong>Студент:</strong> <?php echo $student_full_name; ?></p>
<p><strong>Дата и время осмотра:</strong> <?php echo $examination_date; ?></p>
    
<form action="save_measurements.php" method="POST" id="measurements_form">
    <input type="hidden" name="examination_id" value="<?php echo $examination_id; ?>">
    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">

    <h3>Медицинские Параметры</h3>
    <?php if (mysqli_num_rows($parameters_result) > 0): ?>
        <?php while ($param = mysqli_fetch_assoc($parameters_result)): ?>
            <div>
                <label for="parameter_<?php echo htmlspecialchars($param['id']); ?>">
                    <?php echo htmlspecialchars($param['parameter_name'] . ' (' . $param['unit'] . ')'); ?>:
                </label>
                <input type="text" 
                       id="parameter_<?php echo htmlspecialchars($param['id']); ?>" 
                       name="measurements[<?php echo htmlspecialchars($param['id']); ?>]" 
                       required>
            </div>
            <br>
        <?php endwhile; ?>
    <?php else: ?>
        <p>Нет доступных медицинских параметров.</p>
    <?php endif; ?>

    <h3>Болезни <em>(необязательно)</em></h3>
    <div id="diseases_container">
        <div class="disease_entry">
            <label for="disease_id_0">Выберите болезнь:</label>
            <!-- Убираем required, чтобы болезнь была необязательной -->
            <select name="diseases[]" id="disease_id_0">
                <option value="" disabled selected>-- Выберите болезнь --</option>
                <?php 
                // Перемотать указатель результата обратно, если он ушёл вперёд
                mysqli_data_seek($diseases_result, 0);
                while ($disease = mysqli_fetch_assoc($diseases_result)): ?>
                    <option value="<?php echo htmlspecialchars($disease['id']); ?>">
                        <?php echo htmlspecialchars($disease['disease_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button type="button" onclick="removeDisease(this)">Удалить</button>
        </div>
    </div>
    <button type="button" onclick="addDisease()">Добавить болезнь</button>
    <br><br>

    <h3>Количество больничных дней <em>(необязательно)</em></h3>
    <div>
        <label for="sick_days">Количество дней болезни:</label>
        <!-- Поле уже не имело required, оставляем как есть -->
        <input type="number" name="sick_days" id="sick_days" min="0">
    </div>
    <br>

    <h3>Группа Здоровья Студента</h3>
    <div>
        <label for="health_group_id">Выберите группу здоровья:</label>
        <select name="health_group_id" id="health_group_id" required>
            <option value="" disabled <?php echo is_null($current_health_group_id) ? 'selected' : ''; ?>>-- Выберите группу здоровья --</option>
            <?php while ($group = mysqli_fetch_assoc($health_groups_result)): ?>
                <option value="<?php echo htmlspecialchars($group['id']); ?>" <?php if($group['id'] == $current_health_group_id) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($group['group_name']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <br>

    <button type="submit">Сохранить Измерения</button>
</form>

<!-- JavaScript для динамического добавления и удаления болезней -->
<script>
// Получение списка болезней из PHP и преобразование в JavaScript массив
const diseases = <?php
    mysqli_data_seek($diseases_result, 0); // Сброс указателя результата
    $disease_list = [];
    while ($disease = mysqli_fetch_assoc($diseases_result)) {
        $disease_list[] = ['id' => $disease['id'], 'name' => $disease['disease_name']];
    }
    echo json_encode($disease_list);
?>;

let diseaseCount = 1; // Начальное количество болезней

function addDisease() {
    const container = document.getElementById('diseases_container');
    
    const diseaseDiv = document.createElement('div');
    diseaseDiv.className = 'disease_entry';
    
    // Создание выпадающего списка болезней (без required)
    let selectHTML = `
        <label for="disease_id_${diseaseCount}">Выберите болезнь:</label>
        <select name="diseases[]" id="disease_id_${diseaseCount}">
            <option value="" disabled selected>-- Выберите болезнь --</option>`;
    
    diseases.forEach(function(disease) {
        selectHTML += `<option value="${disease.id}">${disease.name}</option>`;
    });
    
    selectHTML += `</select>
                   <button type="button" onclick="removeDisease(this)">Удалить</button>`;
    
    diseaseDiv.innerHTML = selectHTML;
    container.appendChild(diseaseDiv);
    diseaseCount++;
}

function removeDisease(button) {
    const diseaseEntry = button.parentElement;
    // Теперь удаляем заболевание без каких-либо ограничений
    diseaseEntry.remove();
}
</script>

<?php
// Закрытие соединения с базой данных
mysqli_close($link);
include 'includes/footer.php';
?>
