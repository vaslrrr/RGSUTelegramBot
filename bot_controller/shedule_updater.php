<?php
ini_set("display_errors", true);
const MODULE = true;

require_once dirname(__DIR__) . '/include/settings.php';
require_once dirname(__DIR__) . '/include/class-telegram.php';
require_once dirname(__DIR__) . '/include/database_engine.php';

//Скрипт тырит данные расписания с сайта. Выполняется cron(планировщиком) раз в сутки в 21:00. По последним тестам - делает 418 запросов к сайту
//Результат получен методом reverse engineering

//Инициализируем соединение с БД
DB::init();

//Создаем счетчики(для собственного интереса, чтобы вывести в конце)
$requestCounter = 0;
$notified = 0;
$inserts = 0;
$updates = 0;
$deletes = 0;
$total = 0;
$startTime = time();

//Создаем соединение с ботом, чтобы, если потребуется, рассылать уведомления
$bot = new Telegram(BOT_TOKEN);

//Генерируем случайный хэш. Пришлось сделать из-за накладок расписания. Чтобы видеть, что эту строка добавлена/изменена в этот обновлении,
// а не изменение расписания. Чтобы наклада не переписывала строку, которое было добавлено предыдущим. Т.е. Если 2 пары в 8:10(накладка),
//чтобы пара, при обновлении, не была зафиксирорвана как изменение и изменила название и кабинет, а следом пара в это же время - не меняла на другое название эту же пару, а добавляла новую и тп
$updateHash = null;
do {
    $updateHash = gen_key(44);
} while (DB::uFetch("SELECT `id` FROM `schedule` WHERE `update_hash` = '$updateHash'") != false);

//Получаем содержимое страницы расписания
$page = request("https://old.rgsu.net/for-students/timetable/");

//Разбираем содержимое страницы. С помощью регулярного выражения выцепливаем html элемент списка факультетов
$f_match = [];
if (!preg_match("/<select name='f_Faculty' id='faculty'>(.*?)<\/select>/ius", $page, $f_match)) {
    //Если ничего не найдено - значит ошибка на странице или что-то не так
    exit("ERROR 1");
}

//Из полученной строки вида"<select...><option value='1'>Факультет экономики</option><option..>....</option>...</select>" выцепливаем все option, разбирая на группыэ
//<option value='(группа 1)'>(группа 2)</option>, т.е
// <option value='ид для запроса'>Название факультета</option>
$g_match = [];
if (!preg_match_all("/<option value='(.*?)' id='.*?'>(.*?)<\/option>/iu", $f_match[1], $g_match)) {
    //Если ничего не найдено - значит ошибка на странице или что-то не так
    exit("ERROR 2");
}

//Создадим список факультетов
$facultets = [];
//Перебираем все результаты, путем перебора группы 1
foreach ($g_match[1] as $key => $s_id) {
    //Если ключ = 0, то это первый элемент в списке с --выбрать--. Он нам не нужен игнорируем
    if ($key == 0) {
        continue;
    }

    //Получаем имя факультета из 2ой группы с помощью ключа массива. Обе группы имеют одинаковые ключи к тем же элементам из списка
    $name = $g_match[2][$key];

    //Проверяем, есть ли такой факультет в базе данных по названию, предварительно экранировав название от символов, которые могут сломать или взломать нашу базу
    $f_id = DB::uFetch("SELECT `id` FROM `facultets` WHERE `name` = '" . DB::escapeSQLstring($name) . "'");
    if ($f_id == false) {
        //Если не найден такй элемент то внесем его в базу
        DB::uQuery("INSERT INTO `facultets`(`name`) VALUES ('" . DB::escapeSQLstring($name) . "')");
        //И запишем ид, который ему назначила наша база
        $f_id = DB::uLastInsertId();
    } else {
        //Или сохраним для будущего ид который в нашей базе
        $f_id = $f_id['id'];
    }

    //Поместим в список факультет, где ключ - ид в нашей базе, значение - ид в их базе
    $facultets[$f_id] = $s_id;
}

//Создадим второй список факультетов, но расширенный
$f_list = [];

$a_groups = [];

//Перебираем все элементы первого списка факультетов, на $key => $value, т.е. $key = иду в нашей базе, $value - в их базе
foreach ($facultets as $key => $value) {
    //Отследив, что же происходит при выборе другого факультета в списке, заметим, что при каждом изменении страничка делает запрос по ссылке, где в ответ ей приходит нечто формата <option value='...'>...</option>
    //Тоже сделаем этот запрос для каждого факультета, чтобы получить список групп
    $data = request("https://old.rgsu.net/for-students/timetable/timetable.html?&isNaked=1&nc_ctpl=758&gr=&id=$value");

    if (!in_array($key, $a_groups)) {
        $a_groups[$key] = [];
    }

    //Переберем все по регулярке. Проигнорируем имя в value так как оно дублирует имя в содержимом элемента и запишем в группу
    $groups = [];
    if (!preg_match_all("/<option .*?>(.*?)<\/option>/iu", $data, $groups)) {
        //Если ничего не нашло - произошла ошибка, то пропустим и перейдем к следующему факультету
        echo "FACULTET NULL!!";
        continue;
    }

    //Обнуляем спсок групп и перебираем результаты
    $g_list = [];
    foreach ($groups[1] as $name) {
        //Проверяем наличие в базе группы с таким именем и факультетом в базе
        $g_id = DB::uFetch("SELECT `id` FROM `groups` WHERE `name` = '" . DB::escapeSQLstring($name) . "' AND `f_id` = '" . DB::escapeSQLstring($key) . "'");
        $a_groups[$key][] = $name;
        if ($g_id == false) {
            //Если нет, то создаем новый и записываем его новый ид
            DB::uQuery("INSERT INTO `groups`(`f_id`, `name`) VALUES ('" . DB::escapeSQLstring($key) . "', '" . DB::escapeSQLstring($name) . "')");
            $g_id = DB::uLastInsertId();
        } else {
            //Иначе записываем уже существующий ид
            $g_id = $g_id['id'];
        }

        //Записываем в массив группу, где ключ - ид в нашей базе и значение - имя группы
        $g_list[$g_id] = $name;
    }

    //Заполняем список факультетов. Где ключ - ид факультета в нашей базе, значение состоит из массива с 2 элементами, 1ый - ключ fac_id со значением - ид факультета в их базе, 2ой - ключ groups и значением - массив групп $g_list
    $f_list[$key] = [
        "fac_id" => $value,
        "groups" => $g_list
    ];


}

//Перебираем новый список факультетов, разбив на ключ=>значение по $key=>$value, или ид в нашей базе => массив двух элементов и тп
foreach ($f_list as $key => $value) {
    //Для удобства выделим группы из массива в новую переменную(зачем - не помню, мб просто так)
    $groups = $value['groups'];

    //Переберем эти группы
    foreach ($groups as $g_id => $name) {
        //Проследив на то, как реагирует страница на выбор группы, а точнее переадресовывает на страницу по ссылке
        //...?template=&action=index&admin_mode=&f_Faculty=(ид факультета)&group=(имя группы)
        //скопируем поведение, запросив содержимое страницы, предварительно закодировав urlencode имя группы
        $data = request("https://old.rgsu.net/for-students/timetable/timetable.html?template=&action=index&admin_mode=&f_Faculty={$value['fac_id']}&group=" . urlencode($name));

        //Просмотрев исходный код зафикисируем, что расписание выделено в две таблицы, по каждой для четной и нечетной недели
        //С одинаковым синтаксисом. Так что выделим их группами по <table class=\"timetable\">содержимое таблицы - группа<\/table>
        $timetables = [];
        if (!preg_match_all("/<table class=\".*? timetable.*?\">(.*?)<\/table>/ius", $data, $timetables)) {
            //Если ничего не найдено - игнорируем страницу и переходим к следующей учебной группе
            echo "TIMETABLE NULL!!" . $data;
            continue;
        }

        //Перебираем найденные таблицы. Их должно быть две
        foreach ($timetables[1] as $val) {
            $week = null;

            //Выясняем, с какой таблицей работаем, путем поиска в содержимом текста "нечетная неделя" или "четная неделя"
            //Обязательно первой проверяем на нечетная "неделя", так как текст "четная неделя" есть в "(не)четная неделя"
            if (mb_stripos($val, "Нечетная неделя") !== false) {
                $week = "odd";
            } else if (mb_strpos($val, "Четная неделя") !== false) {
                $week = "even";
            }

            if ($week === null) {
                echo "WEEK NULL!!";
                //Если ни один текст не найдем - что то не так, значит игнорируем эту  страницу и идем дальше
                continue;
            }

            //Замечаем, что дни (Понедельник, вторник, среда...) разбиты в уникальные(в целом) блоки, а значит их можно удобно разбить регуляркой
            $days = [];
            if (!preg_match_all("/<tbody class=\"day\">(.*?)<\/tbody>/ius", $val, $days)) {
                //Больше пропуски комментировать не буду, смысл понятен
                echo "DAYS NULL!!";
                continue;
            }

            //Перебираем каждый день
            foreach ($days[1] as $dayData) {
                //Получаем название дня недели
                $dNumber = [];
                if (!preg_match("/<tr><td colspan=\"4\" align=\"center\"  class=\"name\">(.*?)<\/td><\/tr>/iu", $dayData, $dNumber)) {
                    //Ну тут поясню - не найдено имя дня, значит что то не так, значит переходим к следующему элемену на 2(!) цикла вверх, т.е. не к следующему дню, а следующей группе
                    echo "DAY DATA NULL!!";
                    continue 2;
                }
                //Записываем имя дня недели так, как мы хранима в базе - на английском языке
                $dayName = dayToEng($dNumber[1]);

                //Удаляем название дня недели из "содержимого страницы", чтобы не мешалось в дальнейшем переборе строк
                $dayData = preg_replace("/<tr><td colspan=\"4\" align=\"center\"  class=\"name\">(.*?)<\/td><\/tr>/iu", "", $dayData);

                //Отмечаем, что не было обновлений(вдруг в предыдущем дне были, чтобы не наложилось лучше установить)
                $updated = false;

                //Запишем преподов, которых так же надо оповестить об изменении
                $lectors = [];

                //Если нет текста, что занятий нет
                if (!mb_stripos($dayData, "Занятий нет")) {
                    //Удаляем ненужные нам ученые степени преподов(к.т.н и тп)
                    $dData = preg_replace("/<span class=\"prepod-position\">(.*?)<\/span>/iu", "", $dayData);

                    //Найдем строки tr, где каждая строка - предмет
                    $prep = [];
                    if (!preg_match_all("/<tr>(.*?)<\/tr>/ius", $dData, $prep)) {
                        continue;
                    }


                    //Переберем результат поиска
                    foreach ($prep[1] as $v) {
                        //Выдернем время начала и конца пары
                        $timings = [];
                        if (!preg_match_all("/<span class=\"time-start\">(.*?)<\/span>.*?<span class=\"time-end\">(.*?)<\/span>/ius", $v, $timings)) {
                            continue;
                        }

                        //Так же разберем строку дня по столбцам <td>, где каждый столбец за что то отвечает(название предмета, время, препод)
                        $params = [];
                        if (!preg_match_all("/<td>(.*?)<\/td>/ius", $v, $params)) {
                            continue 2;
                        }

                        //Получим время начала пары и конца, уберем ненужный нам знак двоеточия, уберем ненужные пробелы вначале и конце
                        $timeStart = trim(str_replace(":", "", $timings[1][0]));
                        $timeEnd = trim(str_replace(":", "", $timings[2][0]));

                        //Разберем остальные данные(аудитория, название дисциплины, препод), уберем из них переносы строк(\r\n - в винде, \n - unix. \n везде есть, так что сначала уберем сначала \r отдельно, потом \n). Уберем ненужные пробелы, хотя трим убирает и переносы и пробелы, я подстраховываюсь
                        $d = $params[1];
                        $aud = trim(str_replace("\n", "", str_replace("\r", "", $d[1])));
                        $name = trim(str_replace("\n", "", str_replace("\r", "", $d[2])));
                        $lector = trim(str_replace("\n", "", str_replace("\r", "", $d[3])));

                        //Выделим из названия предмета даты, в которые он может быть
                        $days = [];
                        if (preg_match_all("/([0-9]{1,2}\.[0-9]{1,2}\.(20)?[0-9]{2})/iu", $name, $days)) {
                            $days = $days[1];
                        } else {
                            //если ни в какие, то просто запишем пустоту, которая будет обозначать, что предмет есть в любую дату
                            $days = [""];
                        }

                        //Переберем даты
                        foreach ($days as $d) {
                            //Проверим, есть ли предмет такой в базу уже, с такой группой, временем начала и конца, днем недели, датой и хэшем обновления(т.е. что предмет в этой сессии еще не обновлялся - костыль для обхода наложений предметов в одно время)
                            $inf = DB::uFetch("SELECT * FROM `schedule` WHERE `g_id` = '$g_id' AND `starttime` = '" . DB::escapeSQLstring($timeStart) . "' AND `endtime` = '" . DB::escapeSQLstring($timeEnd) . "' AND `day` = '$dayName' AND `week` = '$week' AND `date` = '" . DB::escapeSQLstring($d) . "' AND `update_hash` != '$updateHash'");

                            $total++;// - счетик для себя

                            if ($inf == false) {
                                //Если нет в базе - то добавим новый
                                DB::uQuery("INSERT INTO `schedule`(`g_id`, `starttime`, `endtime`, `day`, `name`, `aud`, `lector`, `week`, `date`, `update_hash`) VALUES('" . DB::escapeSQLstring($g_id) . "', '" . DB::escapeSQLstring($timeStart) . "', '" . DB::escapeSQLstring($timeEnd) . "', '" . DB::escapeSQLstring($dayName) . "', '" . DB::escapeSQLstring($name) . "', '" . DB::escapeSQLstring($aud) . "', '" . DB::escapeSQLstring($lector) . "', '" . DB::escapeSQLstring($week) . "', '" . DB::escapeSQLstring($d) . "', '$updateHash')");
                                $inserts++;// - счетик для себя
                                $updated = true;//фиксируем что было изменение
                            } else {
                                //Проверяем на изменение имени, препода, аудитории
                                if ($inf['name'] != $name || $inf['lector'] != $lector || $inf['aud'] != $aud) {
                                    //Сохраняем изменение
                                    DB::uQuery("UPDATE `schedule` SET `name` = '" . DB::escapeSQLstring($name) . "', `lector` = '" . DB::escapeSQLstring($lector) . "', `aud` = '" . DB::escapeSQLstring($aud) . "' WHERE `id` = '{$inf['id']}'");
                                    $updates++;// - счетик для себя
                                    $updated = true;//Фиксируем, что было изменение
                                    $lectors[] = $lector;

                                    if ($inf['lector'] != $lector) {
                                        $lectors[] = $inf['lector'];
                                    }
                                }
                                //Устанавливаем текущий хэш обновления
                                DB::uQuery("UPDATE `schedule` SET `update_hash` = '$updateHash' WHERE `id` = '{$inf['id']}'");
                            }
                        }
                    }
                }

                //Получаем расписание на этот день(без учета даты), но с отличающимся хэшем(старым)
                //т.е. те - которых текущее обновление не затронуло
                $shed = DB::uFetchALL("SELECT * FROM `schedule` WHERE `g_id` = '$g_id' AND `day` = '$dayName' AND `week` = '$week' AND `update_hash` != '$updateHash'");

                //Удаляем это все из базы, т.к их нет в новом расписании
                DB::uQuery("DELETE FROM `schedule` WHERE `g_id` = '$g_id' AND `day` = '$dayName' AND `week` = '$week' AND `update_hash` != '$updateHash'");
                foreach ($shed as $s) {
                    $deletes++;// - счетик для себя

                    //Если есть дата - то проверяем, если она прошедшая, то уведомлять об изменении не надо. Иначе - уведомляем об изменении
                    if (!empty($s['date'])) {
                        $d = explode(".", $s['date']);
                        $chkDate = mktime(23, 59, 59, $d[1], $d[0], $d[2]);
                        if ($chkDate >= time()) {
                            //Запишем препода и отметим, что есть изменение в дне
                            $lectors[] = $s['lector'];
                            $updated = true;
                        }
                    } else {
                        //Запишем препода и отметим, что есть изменение в дне
                        $lectors[] = $s['lector'];
                        $updated = true;
                    }
                }

                //Если зафикисировано изменение
                if ($updated == true) {
                    //Получаем группу
                    $group = DB::uFetch("SELECT * FROM `groups` WHERE `id` = '{$g_id}'");
                    //Получаем список кого надо оповестить
                    $notifitactions_s = DB::uFetchALL("SELECT * FROM `notifications` WHERE `data_mode` = 'student' AND `data_id` = '{$g_id}' AND `mode` = 'change'");

                    //Перебираем список и оповещаем
                    foreach ($notifitactions_s as $notify) {
                        $fac = DB::uFetch("SELECT * FROM `facultets` WHERE `id` = '{$key}'");

                        $bot->sendMessage($notify['chat_id'], "Оповещение для <b>{$group['name']}, {$fac['name']}</b>:\n" .
                            "Обнаружено изменение в расписании на <b>{$dNumber[1]}</b>, <b>" . ($week == "odd" ? "нечетная" : "четная") . " неделя</b>");
                        $notified++; // - счетик для себя
                    }

                    //Если есть преподы для оповещения - оповещаем преподов
                    foreach ($lectors as $lector) {
                        $lector = DB::uFetch("SELECT * FROM `lectors` WHERE `name` = '$lector' AND `f_id` = '{$key}'");

                        if ($lector != false) {
                            $fac = DB::uFetch("SELECT * FROM `facultets` WHERE `id` = '{$key}'");

                            $notifitactions_s = DB::uFetchALL("SELECT * FROM `notifications` WHERE `data_mode` = 'lector' AND `data_id` = '{$lector['id']}' AND `mode` = 'change'");

                            foreach ($notifitactions_s as $notify) {
                                $fac = DB::uFetch("SELECT * FROM `facultets` WHERE `id` = '{$key}'");

                                $bot->sendMessage($notify['chat_id'], "Оповещение для <b>{$lector['name']}, {$group['name']}, {$fac['name']}</b>:\n" .
                                    "Обнаружено изменение в расписании на <b>{$dNumber[1]}</b>, <b>" . ($week == "odd" ? "нечетная" : "четная") . " неделя</b>");
                                $notified++; // - счетик для себя
                            }
                        }
                    }
                }
            }
        }
    }
}

//Удалим старые группы и пары
//Статистика
$old_groups_count = 0;
$old_groups_del_count = 0;
$old_lines_count = 0;
$old_facts_del = 0;

//Получим все строки, не тронутых обновлением
$ou_list = DB::uFetchALL("SELECT `id`, `g_id`, `lector` FROM `schedule` WHERE `update_hash` != '$updateHash'");

//Запишем иды удаленных групп, чтобы, если у них неи пар, удалить их
$ou_groups = [];

foreach ($ou_list as $l) {
    $old_lines_count++;
    if (!in_array($l['g_id'], $ou_groups)) {
        $ou_groups[] = $l['g_id'];

        //Оповестим препода об изменении
        $group = DB::uFetch("SELECT * FROM `groups` WHERE `id` = '{$l['g_id']}'");

        $lector = DB::uFetch("SELECT * FROM `lectors` WHERE `name` = '{$l['lector']}' AND `f_id` = '{$group['f_id']}'");

        if ($lector != false) {
            $fac = DB::uFetch("SELECT * FROM `facultets` WHERE `id` = '{$group['f_id']}'");

            $notifitactions_s = DB::uFetchALL("SELECT * FROM `notifications` WHERE `data_mode` = 'lector' AND `data_id` = '{$lector['id']}' AND `mode` = 'change'");

            foreach ($notifitactions_s as $notify) {
                $bot->sendMessage($notify['chat_id'], "Оповещение для <b>{$lector['name']}, {$group['name']}, {$fac['name']}</b>:\n" .
                    "Обнаружено изменение в расписании");
                $notified++; // - счетик для себя
            }
        }
    }

    DB::uQuery("DELETE FROM `schedule` WHERE `id` = '{$l['id']}'");
}

//Навсякий запишем филиалы, чтоб удалить их из базы, вдруг они вообще закрыли его
$o_fact = [];

//Получим факультеты
$f_ch = DB::uFetchALL("SELECT `id` FROM `facultets`");

//Удалим группы, которых нет в расписании
foreach ($f_ch as $facl) {
    $g_ch = DB::uFetchALL("SELECT `id`, `name` FROM `groups` WHERE `f_id` = '{$facl['id']}'");

    foreach ($g_ch as $gc) {
        if (!empty($a_groups[$facl['id']])) {
            if (!in_array($gc['name'], $a_groups[$facl['id']])) {
                if (!in_array($gc['id'], $ou_groups)) {
                    $ou_groups[] = $gc['id'];
                }
            }
        } else {
            $ou_groups[] = $gc['id'];
        }
    }
}

foreach ($ou_groups as $g) {
    $st = DB::uFetch("SELECT * FROM `groups` WHERE `id` = '$g'");

    if ($st != false) {
        $c_sh = DB::uFetch("SELECT COUNT(`id`) AS `count` FROM `schedule` WHERE `g_id` = '$g'")['count'];

        if ($c_sh > 0) {
            //Получаем список кого надо оповестить
            $notifitactions_s = DB::uFetchALL("SELECT * FROM `notifications` WHERE `data_mode` = 'student' AND `data_id` = '{$st['id']}' AND `mode` = 'change'");

            //Перебираем список и оповещаем
            foreach ($notifitactions_s as $notify) {
                $fac = DB::uFetch("SELECT * FROM `facultets` WHERE `id` = '{$st['f_id']}'");

                $bot->sendMessage($notify['chat_id'], "Оповещение для <b>{$st['name']}, {$fac['name']}</b>:\n" .
                    "Обнаружено изменение в расписании");
                $notified++; // - счетик для себя
            }
        } else {
            $old_groups_del_count++;

            if (!in_array($st['f_id'], $o_fact)) {
                $o_fact[] = $st['f_id'];
            }
            DB::uQuery("DELETE FROM `groups` WHERE `id` = '{$st['id']}'");
        }
    }
}

//Удалим устаревшие факультеты, если у них нет групп
foreach ($o_fact as $fact) {
    $c = DB::uFetch("SELECT COUNT(`id`) AS `count` FROM `groups` WHERE `f_id` = '{$fact}'")['count'];

    if ($c == 0) {
        DB::uQuery("DELETE FROM `facultets` WHERE `id` = '$fact'");
        $old_facts_del++;
    }
}

//Счетчики для преподов
$lectors_count = 0;
$lectors_new = 0;
$lectors_deleted = 0;

//Получим список факультетов
$facultets = DB::uFetchALL("SELECT `id` FROM `facultets`");
foreach ($facultets as $facultet) {
    //Получим список преподов из факультета, сгрупировав одинаковые строки в 1 результат
    $lectors = DB::uFetchALL("SELECT `lector` FROM `schedule` s, `groups` g WHERE s.`g_id` = g.`id` AND g.`f_id` = '{$facultet['id']}' GROUP BY `lector`");

    //Переберем всех преподов
    $lec = [];
    foreach ($lectors as $lector) {
        $lectors_count++;// - счетик для себя
        //Проверим, есть ли препод в базе. Если нет, то добавим
        $check = DB::uFetch("SELECT `id` FROM `lectors` WHERE `f_id` = '{$facultet['id']}' AND `name` = '" . DB::escapeSQLstring($lector['lector']) . "'");
        if ($check == false) {
            DB::uQuery("INSERT INTO `lectors`(`f_id`, `name`) VALUES ('{$facultet['id']}', '" . DB::escapeSQLstring($lector['lector']) . "')");
            $lectors_new++;// - счетик для себя
        }
        //Запишем в массив, что такой препод есть в новом списке
        $lec[] = $lector['lector'];
    }

    //Получим всех преподов из базы
    $lectors_db = DB::uFetchALL("SELECT `id`, `name` FROM `lectors` WHERE `f_id` = '{$facultet['id']}'");

    //Проверим, всех преподов перебрав результат из базы
    foreach ($lectors_db as $lector) {
        //Если мы не находили этого препода - то удалим его из базы, у него нет пар
        if (!in_array($lector['name'], $lec)) {
            DB::uQuery("DELETE FROM `lectors` WHERE `id` = '{$lector['id']}'");
            $lectors_deleted++;// - счетик для себя
        }
    }
}


//Закончили обработку, выведем статистику работы
echo "TOTAL REQUESTS TO SITE: $requestCounter\n" .
    "TOTAL PARSED DATA: $total\n" .
    "UPDATES: $updates\n" .
    "NEW: $inserts\n" .
    "DELETES: $deletes\n" .
    "TOTAL LECTORS (NEW/DELETED): $lectors_count ($lectors_new/$lectors_deleted)\n" .
    "OUTDATED SCHEDULES: $old_lines_count\n" .
    "OUTDATED GROUPS (TOTAL/DELETED): $old_groups_count/$old_groups_del_count\n" .
    "OUTDATED FACULTETS: $old_facts_del\n" .
    "ELAPSED TIME: " . (time() - $startTime) . " sec\n" .
    "NOTIFIED: $notified\n";

//Функция получает содержимое страницы через CURL
function request($url, $post = null)
{
    global $requestCounter;// - счетчик для себя. global - значит переменная из глобальной видимости, не только функции

    $ch = curl_init();//создаем объект курл
    curl_setopt($ch, CURLOPT_URL, $url); //указывваем адрес страницы

    $headers = [];

    $headers[] = "accept-language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7";
    $headers[] = "x-requested-with: XMLHttpRequest";
    $headers[] = "Connection: keep-alive";


    curl_setopt($ch, CURLOPT_ENCODING, "gzip");//указываем доступный метод сжатия данных
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.74 Safari/537.36"); //Указываем юзерагент(прикидываемся браузером хром)
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);//Указываем дополнительные заголовки. Конечно по уму надо больше, чтобы быть больше на человека похожим

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);//указываем, что нам ответ надо не в браузер отправить, а вернуть в переменную
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);//Указываем максимальное время на соеденинение с сервером секунд
    curl_setopt($ch, CURLOPT_TIMEOUT, 9);//Указываем максимальное время соединения секунд
    $resp = curl_exec($ch);//выполняем запрос
    curl_close($ch);//закрываем сессию с CURL

    $requestCounter++;// - счетчик для себя

    return $resp;// возвращаем ответ
}

//Функция переводит названия дня с русского на английский для бд
function dayToEng($day)
{
    switch (mb_strtolower($day)) {
        case "понедельник":
        {
            return "Monday";
            break;
        }
        case "вторник":
        {
            return "Tuesday";
            break;
        }
        case "среда":
        {
            return "Wednesday";
            break;
        }
        case "четверг":
        {
            return "Thursday";
            break;
        }
        case "пятница":
        {
            return "Friday";
            break;
        }
        case "суббота":
        {
            return "Saturday";
            break;
        }
        case "воскресенье":
        {
            return "Sunday";
            break;
        }
    }
    return;
}

//Создает строку из случайных символов длиной $length
function gen_key($length)
{
    $arr = array('a', 'b', 'c', 'd', 'e', 'f',
        'g', 'h', 'i', 'j', 'k', 'l',
        'm', 'n', 'o', 'p', 'r', 's',
        't', 'u', 'v', 'x', 'y', 'z',
        'A', 'B', 'C', 'D', 'E', 'F',
        'G', 'H', 'I', 'J', 'K', 'L',
        'M', 'N', 'O', 'P', 'R', 'S',
        'T', 'U', 'V', 'X', 'Y', 'Z',
        '1', '2', '3', '4', '5', '6',
        '7', '8', '9', '0');
    $key = "";
    for ($i = 0; $i < $length; $i++) {
        $index = rand(0, count($arr) - 1);
        $key .= $arr[$index];
    }
    return $key;
}