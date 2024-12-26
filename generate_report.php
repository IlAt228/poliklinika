<?php

// Начало буферизации вывода
ob_start();

require 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Проверка, что запрос был сделан через POST и student_id установлен
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['student_id'])) {

    // Подключение к базе данных
    $link = mysqli_connect("localhost", "root", "A@cexlAEI3vMcn)r", "poliklinika");
    if (!$link) {
        include 'includes/header.php';
        die("Ошибка подключения: " . mysqli_connect_error());
    }

    // Получение и экранирование student_id
    $student_id = intval(mysqli_real_escape_string($link, $_POST['student_id']));

    // Определение типа отчёта
    $report_type = isset($_POST['report_type']) ? $_POST['report_type'] : 'full';

    // Для примера. Реально здесь нужно взять ID текущего залогиненного специалиста.
    // Замените логику получения ID специалиста по вашей системе аутентификации (сессия и т.п.).
    $current_specialist_id = 1;

    // 1. Получаем данные о студенте
    $stmt2 = mysqli_prepare($link, "
        SELECT 
            s.id, 
            s.first_name, 
            s.last_name, 
            s.middle_name, 
            s.date_of_birth, 
            s.student_number, 
            s.gender,
            s.university_id,            -- важно: чтобы узнать, к какому университету относится студент
            hg.group_name AS health_group
        FROM students s
        LEFT JOIN health_groups hg ON s.health_group_id = hg.id
        WHERE s.id = ?
    ");
    if (!$stmt2) {
        include 'includes/header.php';
        die("Ошибка подготовки запроса (получение информации о студенте): " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt2, "i", $student_id);
    mysqli_stmt_execute($stmt2);
    $result2 = mysqli_stmt_get_result($stmt2);
    $student = mysqli_fetch_assoc($result2);
    mysqli_stmt_close($stmt2);

    if (!$student) {
        include 'includes/header.php';
        die("Студент не найден.");
    }

    // Извлечём university_id студента для записи в report_messages
    $university_id = $student['university_id'];

    // Если у студента не указан университет, нужно обработать это отдельно
    if (empty($university_id)) {
        include 'includes/header.php';
        die("У студента не указан университет. Невозможно сохранить отчёт.");
    }

    // Подготовим общие опции для Dompdf
    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);

    // ----------------------------------------------
    // =============== ОТЧЁТ О БОЛЕЗНЯХ ===============
    // ----------------------------------------------
    if ($report_type === 'illness') {

        // Найдём последний завершённый осмотр студента, в котором были зафиксированы болезни
        $stmt_ill = mysqli_prepare($link, "
            SELECT se.id AS examination_id, se.sick_days
            FROM student_examinations se
            INNER JOIN student_diseases sd ON se.id = sd.examination_id
            WHERE se.student_id = ? AND se.status = 'completed'
            ORDER BY se.examination_date DESC
            LIMIT 1
        ");
        if (!$stmt_ill) {
            include 'includes/header.php';
            die("Ошибка подготовки запроса (поиск осмотра с болезнями): " . mysqli_error($link));
        }
        mysqli_stmt_bind_param($stmt_ill, "i", $student_id);
        mysqli_stmt_execute($stmt_ill);
        $result_ill = mysqli_stmt_get_result($stmt_ill);
        $ill_exam = mysqli_fetch_assoc($result_ill);
        mysqli_stmt_close($stmt_ill);

        if (!$ill_exam) {
            include 'includes/header.php';
            die("У данного студента нет завершённых осмотров с зафиксированными болезнями.");
        }

        // Собираем данные
        $examination_id = $ill_exam['examination_id'];
        $sick_days      = $ill_exam['sick_days'];
        $health_group   = $student['health_group'] ? htmlspecialchars($student['health_group']) : 'Не назначена';

        // Получим список болезней
        $stmt_diseases = mysqli_prepare($link, "
            SELECT disease_name 
            FROM student_diseases sd
            INNER JOIN diseases d ON sd.disease_id = d.id
            WHERE sd.examination_id = ?
        ");
        if (!$stmt_diseases) {
            include 'includes/header.php';
            die("Ошибка подготовки запроса (получение болезней): " . mysqli_error($link));
        }
        mysqli_stmt_bind_param($stmt_diseases, "i", $examination_id);
        mysqli_stmt_execute($stmt_diseases);
        $result_diseases = mysqli_stmt_get_result($stmt_diseases);
        $diseases = mysqli_fetch_all($result_diseases, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_diseases);

        // Генерируем HTML для отчёта
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <title>Отчёт о Болезнях Студента</title>
            <style>
                body {
                    font-family: 'DejaVu Sans', sans-serif;
                    font-size: 12px;
                    line-height: 1.5;
                }
                h2 {
                    text-align: center;
                    margin-bottom: 20px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                th, td {
                    border: 1px solid #000;
                    padding: 5px;
                    text-align: left;
                }
                th {
                    background-color: #f2f2f2;
                }
                .section-title {
                    background-color: #ddd;
                    padding: 10px;
                    margin-top: 20px;
                    margin-bottom: 10px;
                }
            </style>
        </head>
        <body>
            <h2>Отчёт о Болезнях Студента</h2>

            <!-- Личная Информация -->
            <div class="section-title">Личная Информация</div>
            <table>
                <tr>
                    <th>ID Студента</th>
                    <td><?php echo htmlspecialchars($student['id']); ?></td>
                </tr>
                <tr>
                    <th>Имя</th>
                    <td><?php echo htmlspecialchars($student['first_name']); ?></td>
                </tr>
                <tr>
                    <th>Фамилия</th>
                    <td><?php echo htmlspecialchars($student['last_name']); ?></td>
                </tr>
                <tr>
                    <th>Отчество</th>
                    <td><?php echo htmlspecialchars($student['middle_name']); ?></td>
                </tr>
                <tr>
                    <th>Дата Рождения</th>
                    <td><?php echo htmlspecialchars($student['date_of_birth']); ?></td>
                </tr>
                <tr>
                    <th>Номер Студента</th>
                    <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                </tr>
                <tr>
                    <th>Пол</th>
                    <td><?php echo htmlspecialchars(ucfirst($student['gender'])); ?></td>
                </tr>
                <tr>
                    <th>Группа Здоровья</th>
                    <td><?php echo $health_group; ?></td>
                </tr>
            </table>

            <!-- Болезни и больничные дни -->
            <div class="section-title">Информация о Болезнях</div>
            <table>
                <tr>
                    <th>Название Болезни</th>
                </tr>
                <?php foreach ($diseases as $disease): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($disease['disease_name']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <div class="section-title">Количество Больничных Дней</div>
            <table>
                <tr>
                    <th>Больничные Дни</th>
                    <td><?php echo htmlspecialchars($sick_days); ?></td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        $html = ob_get_clean();

        // Генерация PDF
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Получим байтовое содержимое PDF
        $pdfContent = $dompdf->output();

        // Сохраняем PDF в таблицу report_messages (с учётом student_id!)
        $insertQuery = "
            INSERT INTO report_messages (university_id, specialist_id, student_id, file_data)
            VALUES (?, ?, ?, ?)
        ";
        $stmtInsert = mysqli_prepare($link, $insertQuery);
        if (!$stmtInsert) {
            mysqli_close($link);
            die("Ошибка подготовки запроса (сохранение PDF): " . mysqli_error($link));
        }

        // Параметры привязки: i - university_id, i - specialist_id, i - student_id, s - file_data
        mysqli_stmt_bind_param($stmtInsert, "iiis", $university_id, $current_specialist_id, $student_id, $pdfContent);

        if (!mysqli_stmt_execute($stmtInsert)) {
            mysqli_stmt_close($stmtInsert);
            mysqli_close($link);
            die("Ошибка при выполнении запроса (сохранение PDF): " . mysqli_error($link));
        }

        mysqli_stmt_close($stmtInsert);
        mysqli_close($link);

        // Отдаём файл на скачивание (по желанию, можно оставить только сохранение)
        $dompdf->stream("report_illness_student_" . $student_id . ".pdf", array("Attachment" => true));
        exit();

    // ----------------------------------------------
    // =============== ПОЛНЫЙ ОТЧЁТ ===============
    // ----------------------------------------------
    } else {
        // 3. Получение списка всех осмотров студента
        $stmt3 = mysqli_prepare($link, "
            SELECT 
                se.id AS examination_id, 
                se.examination_date, 
                se.status, 
                se.specialist_id, 
                sp.full_name AS specialist_name 
            FROM student_examinations se 
            INNER JOIN specialists sp ON se.specialist_id = sp.id 
            WHERE se.student_id = ? 
            ORDER BY se.examination_date DESC
        ");
        if (!$stmt3) {
            include 'includes/header.php';
            die("Ошибка подготовки запроса (список осмотров): " . mysqli_error($link));
        }
        mysqli_stmt_bind_param($stmt3, "i", $student_id);
        mysqli_stmt_execute($stmt3);
        $result3 = mysqli_stmt_get_result($stmt3);
        $examinations = mysqli_fetch_all($result3, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt3);

        // 4. Получение измерений по каждому осмотру (исключая parameter_id = 7)
        $measurements = [];
        if (!empty($examinations)) {
            $stmt4 = mysqli_prepare($link, "
                SELECT 
                    sm.parameter_id, 
                    mp.parameter_name, 
                    mp.unit, 
                    sm.value 
                FROM student_measurements sm 
                INNER JOIN medical_parameters mp ON sm.parameter_id = mp.id 
                WHERE sm.examination_id = ? 
                  AND sm.parameter_id != 7
            ");
            if (!$stmt4) {
                include 'includes/header.php';
                die("Ошибка подготовки запроса (измерения): " . mysqli_error($link));
            }

            foreach ($examinations as $exam) {
                $exam_id = intval($exam['examination_id']);
                mysqli_stmt_bind_param($stmt4, "i", $exam_id);
                mysqli_stmt_execute($stmt4);
                $result4 = mysqli_stmt_get_result($stmt4);
                $measurements[$exam_id] = mysqli_fetch_all($result4, MYSQLI_ASSOC);
            }
            mysqli_stmt_close($stmt4);
        }

        // 5. Текущая группа здоровья студента
        $health_group = $student['health_group'] ? htmlspecialchars($student['health_group']) : 'Не назначена';

        // 6. Получение средних значений измерений студента
        $stmt6 = mysqli_prepare($link, "
            SELECT 
                mp.parameter_name, 
                mp.unit, 
                AVG(CAST(sm.value AS DECIMAL(10,2))) AS average_value 
            FROM student_measurements sm 
            INNER JOIN medical_parameters mp ON sm.parameter_id = mp.id 
            INNER JOIN student_examinations se ON sm.examination_id = se.id 
            WHERE se.student_id = ? 
            GROUP BY sm.parameter_id
        ");
        if (!$stmt6) {
            include 'includes/header.php';
            die("Ошибка подготовки запроса (средние значения): " . mysqli_error($link));
        }
        mysqli_stmt_bind_param($stmt6, "i", $student_id);
        mysqli_stmt_execute($stmt6);
        $result6 = mysqli_stmt_get_result($stmt6);
        $average_measurements = mysqli_fetch_all($result6, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt6);

        // 7. Подсчёт только завершённых осмотров
        $stmt7 = mysqli_prepare($link, "
            SELECT COUNT(*) AS total_examinations
            FROM student_examinations
            WHERE student_id = ?
              AND status = 'completed'
        ");
        if (!$stmt7) {
            include 'includes/header.php';
            die("Ошибка подготовки запроса (количество осмотров): " . mysqli_error($link));
        }
        mysqli_stmt_bind_param($stmt7, "i", $student_id);
        mysqli_stmt_execute($stmt7);
        $result7 = mysqli_stmt_get_result($stmt7);
        $total_examinations = mysqli_fetch_assoc($result7)['total_examinations'];
        mysqli_stmt_close($stmt7);

        // 8. Дата последнего завершённого осмотра
        $stmt8 = mysqli_prepare($link, "
            SELECT examination_date
            FROM student_examinations
            WHERE student_id = ?
              AND status = 'completed'
            ORDER BY examination_date DESC
            LIMIT 1
        ");
        if (!$stmt8) {
            include 'includes/header.php';
            die("Ошибка подготовки запроса (дата последнего осмотра): " . mysqli_error($link));
        }
        mysqli_stmt_bind_param($stmt8, "i", $student_id);
        mysqli_stmt_execute($stmt8);
        $result8 = mysqli_stmt_get_result($stmt8);
        $last_examination = mysqli_fetch_assoc($result8)['examination_date'] ?? null;
        mysqli_stmt_close($stmt8);

        // 9. Список специалистов, проводивших осмотры
        $stmt9 = mysqli_prepare($link, "
            SELECT DISTINCT sp.full_name
            FROM student_examinations se
            INNER JOIN specialists sp ON se.specialist_id = sp.id
            WHERE se.student_id = ?
        ");
        if (!$stmt9) {
            include 'includes/header.php';
            die("Ошибка подготовки запроса (список специалистов): " . mysqli_error($link));
        }
        mysqli_stmt_bind_param($stmt9, "i", $student_id);
        mysqli_stmt_execute($stmt9);
        $result9 = mysqli_stmt_get_result($stmt9);
        $specialists = mysqli_fetch_all($result9, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt9);

        // 10. Информация об университете
        $stmt10 = mysqli_prepare($link, "
            SELECT 
                u.name AS university_name, 
                u.contact_email, 
                u.contact_phone, 
                u.address
            FROM university u
            WHERE u.id = ?
        ");
        if (!$stmt10) {
            include 'includes/header.php';
            die("Ошибка подготовки запроса (информация об университете): " . mysqli_error($link));
        }
        mysqli_stmt_bind_param($stmt10, "i", $university_id);
        mysqli_stmt_execute($stmt10);
        $result10 = mysqli_stmt_get_result($stmt10);
        $universities = mysqli_fetch_all($result10, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt10);

        // Закрываем соединение, чтобы не держать открытым слишком долго
        mysqli_close($link);

        // Генерация HTML для полного отчёта
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <title>Полный Отчёт о Студенте</title>
            <style>
                body {
                    font-family: 'DejaVu Sans', sans-serif;
                    font-size: 12px;
                    line-height: 1.5;
                }
                h2 {
                    text-align: center;
                    margin-bottom: 20px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                th, td {
                    border: 1px solid #000;
                    padding: 5px;
                    text-align: left;
                }
                th {
                    background-color: #f2f2f2;
                }
                .section-title {
                    background-color: #ddd;
                    padding: 10px;
                    margin-top: 20px;
                    margin-bottom: 10px;
                }
            </style>
        </head>
        <body>
            <h2>Полный Отчёт о Студенте</h2>

            <!-- Личная Информация -->
            <div class="section-title">Личная Информация</div>
            <table>
                <tr>
                    <th>ID Студента</th>
                    <td><?= htmlspecialchars($student['id']) ?></td>
                </tr>
                <tr>
                    <th>Имя</th>
                    <td><?= htmlspecialchars($student['first_name']) ?></td>
                </tr>
                <tr>
                    <th>Фамилия</th>
                    <td><?= htmlspecialchars($student['last_name']) ?></td>
                </tr>
                <tr>
                    <th>Отчество</th>
                    <td><?= htmlspecialchars($student['middle_name']) ?></td>
                </tr>
                <tr>
                    <th>Дата Рождения</th>
                    <td><?= htmlspecialchars($student['date_of_birth']) ?></td>
                </tr>
                <tr>
                    <th>Номер Студента</th>
                    <td><?= htmlspecialchars($student['student_number']) ?></td>
                </tr>
                <tr>
                    <th>Пол</th>
                    <td><?= htmlspecialchars(ucfirst($student['gender'])) ?></td>
                </tr>
                <tr>
                    <th>Группа Здоровья</th>
                    <td><?= $health_group ?></td>
                </tr>
            </table>

            <!-- История Осмотров -->
            <div class="section-title">История Осмотров</div>
            <table>
                <tr>
                    <th>ID Осмотра</th>
                    <th>Дата Осмотра</th>
                    <th>Статус</th>
                    <th>Специалист</th>
                </tr>
                <?php foreach ($examinations as $exam): ?>
                    <tr>
                        <td><?= htmlspecialchars($exam['examination_id']) ?></td>
                        <td><?= htmlspecialchars($exam['examination_date']) ?></td>
                        <td><?= htmlspecialchars($exam['status']) ?></td>
                        <td><?= htmlspecialchars($exam['specialist_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <!-- Измерения по каждому осмотру -->
            <div class="section-title">Измерения</div>
            <?php foreach ($measurements as $exam_id => $params): ?>
                <h3>Осмотр ID: <?= htmlspecialchars($exam_id) ?></h3>
                <table>
                    <tr>
                        <th>Параметр</th>
                        <th>Значение</th>
                    </tr>
                    <?php foreach ($params as $param): ?>
                        <tr>
                            <td><?= htmlspecialchars($param['parameter_name'] . " (" . $param['unit'] . ")") ?></td>
                            <td><?= htmlspecialchars($param['value']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endforeach; ?>

            <!-- Средние Значения Измерений -->
            <div class="section-title">Средние Значения Измерений</div>
            <table>
                <tr>
                    <th>Параметр</th>
                    <th>Среднее Значение</th>
                </tr>
                <?php foreach ($average_measurements as $avg): ?>
                    <tr>
                        <td><?= htmlspecialchars($avg['parameter_name'] . " (" . $avg['unit'] . ")") ?></td>
                        <td><?= htmlspecialchars(number_format($avg['average_value'], 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <!-- Статистика Осмотров -->
            <div class="section-title">Статистика Осмотров</div>
            <table>
                <tr>
                    <th>Общее Количество Завершённых Осмотров</th>
                    <td><?= htmlspecialchars($total_examinations) ?></td>
                </tr>
                <tr>
                    <th>Дата Последнего Завершённого Осмотра</th>
                    <td><?= htmlspecialchars($last_examination ? $last_examination : 'Нет данных') ?></td>
                </tr>
            </table>

            <!-- Специалисты -->
            <div class="section-title">Специалисты, Проводившие Осмотры</div>
            <ul>
                <?php foreach ($specialists as $spec): ?>
                    <li><?= htmlspecialchars($spec['full_name']) ?></li>
                <?php endforeach; ?>
            </ul>

            <!-- Информация об Университете -->
            <div class="section-title">Информация об Университете</div>
            <?php foreach ($universities as $uni): ?>
                <table>
                    <tr>
                        <th>Название Университета</th>
                        <td><?= htmlspecialchars($uni['university_name']) ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?= htmlspecialchars($uni['contact_email']) ?></td>
                    </tr>
                    <tr>
                        <th>Телефон</th>
                        <td><?= htmlspecialchars($uni['contact_phone']) ?></td>
                    </tr>
                    <tr>
                        <th>Адрес</th>
                        <td><?= htmlspecialchars($uni['address']) ?></td>
                    </tr>
                </table>
                <br>
            <?php endforeach; ?>
        </body>
        </html>
        <?php
        $html = ob_get_clean();

        // Генерация PDF
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Получаем байтовое содержимое PDF
        $pdfContent = $dompdf->output();

        // Повторное подключение к БД, чтобы сохранить PDF (т.к. выше мы закрыли соединение)
        $link = mysqli_connect("localhost", "root", "A@cexlAEI3vMcn)r", "poliklinika");
        if (!$link) {
            die("Ошибка повторного подключения: " . mysqli_connect_error());
        }

        // Сохраняем PDF в report_messages (с учётом student_id!)
        $insertQuery = "
            INSERT INTO report_messages (university_id, specialist_id, student_id, file_data)
            VALUES (?, ?, ?, ?)
        ";
        $stmtInsert = mysqli_prepare($link, $insertQuery);
        if (!$stmtInsert) {
            mysqli_close($link);
            die("Ошибка подготовки запроса (сохранение PDF): " . mysqli_error($link));
        }

        // Параметры привязки: i - university_id, i - specialist_id, i - student_id, s - file_data
        mysqli_stmt_bind_param($stmtInsert, "iiis", $university_id, $current_specialist_id, $student_id, $pdfContent);

        if (!mysqli_stmt_execute($stmtInsert)) {
            mysqli_stmt_close($stmtInsert);
            mysqli_close($link);
            die("Ошибка при выполнении запроса (сохранение PDF): " . mysqli_error($link));
        }

        mysqli_stmt_close($stmtInsert);
        mysqli_close($link);

        // Отдаём файл на скачивание (если нужно)
        $dompdf->stream("full_report_student_" . $student_id . ".pdf", array("Attachment" => true));
        exit();
    }
}

// Если форма не была отправлена, перенаправляем на страницу выбора
header("Location: reports.php");
exit();
