<?php
const MODULE = true;

require_once __DIR__ . '/include/settings.php';
require_once __DIR__ . '/include/class-telegram.php';
require_once __DIR__ . '/include/database_engine.php';

//Устанавливаем соединение с базой данных
DB::init();

//Проверяем секретный ключ запроса
if ($_GET['secret'] != SECRET_KEY) {
    exit("ok");
}

//Создаем экземпляр бота
$bot = new Telegram(BOT_TOKEN);

//Устанавливаем функцию-обработчик
//Из-за особенностей реализации - технически боту можно отправить команду group 285 - и бот отправит в ответ сообщение с расписанием группы ID285, именно поэтому используется sendOrEditMessage, а не просто sendMessage или editMessage
$bot->setHandler(function ($update, Telegram &$bot) {
    //ID пользователя
    $chatId = $update["message"]["from"]['id'];
    //Текст сообщения
    $messageIn = str_replace("'", "", $update["message"]["text"]);

    //Если сообщение начинается с ignore - ничего не отвечать
    if (startsWith($messageIn, "ignore ")) {
        return;
    }

    //Получить данные пользователя из сообщения
    $usr = $update["message"]["from"];

    $firstname = empty($usr["first_name"]) ? "" : $usr["first_name"];
    $lastname = empty($usr["last_name"]) ? "" : $usr["last_name"];
    $username = empty($usr["username"]) ? "" : $usr["username"];

    //Есть ли пользователь в базе
    $data = DB::uFetch("SELECT `chat_id` FROM `users` WHERE `chat_id` = '" . DB::escapeSQLstring($chatId) . "'");

    if ($data == false) {
        //Если нет - то добавить нового
        DB::uQuery("INSERT INTO `users`(`chat_id`, `name`, `surname`, `username`) VALUES('" . DB::escapeSQLstring($chatId) . "', '" . DB::escapeSQLstring($firstname) . "', '" . DB::escapeSQLstring($lastname) . "', '" . DB::escapeSQLstring($username) . "')");
    } else {
        //Обновить данные в базе
        DB::uQuery("UPDATE `users` SET `name` = '" . DB::escapeSQLstring($firstname) . "', `surname` = '" . DB::escapeSQLstring($lastname) . "', `username` = '" . DB::escapeSQLstring($username) . "' WHERE `chat_id` = '{$data['chat_id']}'");
    }
    //Получить данные о пользователе
    $dataUser = DB::uFetch("SELECT * FROM `users` WHERE `chat_id` = '" . DB::escapeSQLstring($chatId) . "'");

    //Установить меню персонально для пользователя
    $bot->setMenu($chatId, [
        "/start" => "Выбор факультета",
        "/info" => "Информационные страницы",
        "/help" => "Помощь",
        "/stats" => "Статистика",
        "/version" => "О приложении"
    ]);

    //Cтавим метку
    start:

    //Если команда /start - выполнить функцию start со страницей start 1
    if (startsWith($messageIn, "/start")) {
        $messageIn = "start 1";
    }

    if (startsWith($messageIn, "/info")) {
        $messageIn = "info 1";
    }

    //Команда помощи
    if (startsWith($messageIn, "/help")) {
        $bot->sendMessage($chatId, "Данный бот помогает уточнить расписание и может подсказать, какая следующая пара и где, а так же расписание на день в целом. А иногда и об зафиксированном изменении в расписании. Расписание строится учитывая даты пар. Т.е. если в названии предмета указаны даты, по которым дисциплина - данная дисциплина будет выводится, а оповещения рассылаться только в эти даты.\n\n" .
            "<b>Оповещение об расписании на день</b>\n" .
            "Оповещение представляет собой сообщение от бота, которое присылается за 60 минут до предстоящей первой пары. Сообщение содержит информацию о первой паре. Сообщенее содержит кнопку, позволяющее перейти в полное расписание на предстоящий учебный день. Если в дне нет пар - оповещение не рассылается\n\n" .
            "<b>Оповещение об предстоящей паре</b>\n" .
            "Оповещение представляет собой сообщение от бота, которое присылается за 15 минут до начала пары и содержит в себе информацию о предстоящей паре с возможностью перейти в полное расписание.\n\n" .
            "<b>Оповещение об изменениях</b>\n" .
            "Оповещение представляет собой сообщение от бота, которое присылается если система при обновлении расписания зафиксирует несоответвие в данных с информауионного сайта и хранящихся во внутренней базе данных. Время отправки оповещения не регламентировано\n\n" .
            "<b>Для преподавателей</b>\n" .
            "Весь функционал так же доступен для преподавателей. Список преподавателей вынесен в конце списка групп факультета. Преподаватели привязаны к конкретному факультету. Если у Вас есть пары в разных факультетах, вам необходимо смотреть пары и подписываться в разных факультетах\n\n" .
            "<b>Отказ от ответсвенности</b>\n" .
            "Создатели, администрация и т.д. не несут ответсвенности за информацию, предосталенную на данном ресурсе. Информация получена из открытых источников. Так же администрация не несет ответственности за несответсвие каких либо данных\n\n" .
            "<b>Лицензии</b>\n" .
            "Все права принадлежат их правообладателям.\n" .
            "Расписание получено с ресурса rgsu.net (https://rgsu.net/for-students/timetable/).\n" .
            "Использованы технологии Telegram, а так же примеры работы с платформой с сайта https://core.telegram.org/bots/api \n" .
            "Некоторые примеры взяты с сайта php.net\n\n" .
            "<b>Поддержка</b>\n" .
            "Для связи с тех.поддержкой пишите нам в телеграмм @");
        return;
    }

    //Статистика бота
    if (startsWith($messageIn, "/stats")) {
        $users = DB::uFetch("SELECT COUNT(`chat_id`) AS `count` FROM `users`")['count'];
        $facultets = DB::uFetch("SELECT COUNT(`id`) AS `count` FROM `facultets`")['count'];
        $groups = DB::uFetch("SELECT COUNT(`id`) AS `count` FROM `groups`")['count'];
        $shedules = DB::uFetch("SELECT COUNT(`id`) AS `count` FROM `schedule`")['count'];
        $lectors = DB::uFetch("SELECT COUNT(`id`) AS `count` FROM `lectors`")['count'];
        $notifications = DB::uFetch("SELECT COUNT(`id`) AS `count` FROM `notifications`")['count'];
        $bot->sendMessage($chatId, "Всего пользователей: $users\n" .
            "Всего обрабатываемых факультетов: $facultets\n" .
            "Всего обрабатываемых групп: $groups\n" .
            "Всего обрабатываемых преподавателей: $lectors\n" .
            "Всего обрабатываемых пунктов расписания: $shedules\n" .
            "Всего обслуживаемых оповещений: $notifications");
        return;
    }

    //Версия приложения
    if (startsWith($messageIn, "/version")) {
        $bot->sendMessage($chatId, "Версия приложения: 0.8 build 267");
        return;
    }

    //Стандартные значения режима
    $mode = "";
    $data = "";
    $page = 1;

    //Разбиваем входящее сообщение на части
    $msg = explode(" ", $messageIn);

    if (count($msg) == 2) {
        //если элементов 2, и 2ой цифровой (group 256) разбить на режим group и значения 256
        if (is_numeric($msg[1])) {
            $mode = $msg[0];
            $data = (int)$msg[1];
        }
    } else if (count($msg) == 3) {
        //если элементов 3, и 1, и 2ой цифровой (group 256 2) разбить на режим group и значения 256, и страница 2
        if (is_numeric($msg[1]) && is_numeric($msg[2])) {
            $mode = $msg[0];
            $data = (int)$msg[1];
            $page = (int)$msg[2];
        } else if (is_numeric($msg[2])) {
            $mode = $msg[0] . " " . $msg[1];
            $data = (int)$msg[2];
        }
    }

    if ($mode == "info") {

        $btn_l = [];

        $pages = DB::fetchAll("SELECT * FROM `pages`");
        foreach ($pages as $p) {
            $btn_l["page " . $p['id']] = $p['name'];
        }

        //Получаем максимальное количество страниц
        $maxPages = ceil(count($btn_l) / 10);
        if ($page > $maxPages) {
            $page = $maxPages;
        }

        //проверяем что страница не меньше минмальной
        if ($page < 1) {
            $page = 1;
        }

        //Если страниц больше 1 - формируем кнопочный переключатель
        if ($maxPages > 1) {
            //Вырезаем кусок из 10 элементов начиная с (номер страницы - 1) * 10
            $btn_l = array_slice($btn_l, ($page - 1) * 10, 10);

            //задаем следующую/предыдущую страницу
            $next = "/info " . ($page + 1);
            $prev = "/info " . ($page - 1);

            //Если страница первая, то ставим команду игнорирования на кнопку
            if ($page == 1) {
                $prev = "ignore prev";
            }

            //Если страница последняя, то ставим команду игнорирования на кнопку
            if ($page == $maxPages) {
                $next = "ignore next";
            }

            //Добавляем кнопки пагинации
            $btn_l["ignore paginator"] = [
                $prev => "<<",
                "ignore page" => "$page/$maxPages",
                $next => ">>"
            ];
        }

        $bot->sendMessage($chatId, "Выберите страницу:", $btn_l);
        return;
    }

    if ($mode == "page") {

        if (empty($data)) {
            $bot->sendMessage($chatId, "Страница не найдена");
            return;
        }

        $pg = DB::fetch("SELECT * FROM `pages` WHERE `id` = :id", ["id" => $data]);

        if ($pg == false) {
            $bot->sendMessage($chatId, "Страница не найдена");
            return;
        }

        $bot->sendOReditMessage($chatId, "<b>{$pg['name']}</b>\n\n{$pg['content']}");
        return;
    }


    //Если операция - start
    if ($mode == "start") {
        //Если есть значение в запросе - определить его как страницу
        if (!empty($data)) {
            $page = $data;
        }

        //Получаем список факультетов из базы
        $facultets = DB::uFetchALL("SELECT * FROM `facultets`");

        //Создаем массив со списком факультетов, где ключ - команда facultet id - текст - название
        $facul_list = [];
        foreach ($facultets as $f) {
            $facul_list["facultet " . $f['id']] = $f['name'];
        }

        //Получаем максимальное количество страниц
        $maxPages = ceil(count($facul_list) / 10);
        if ($page > $maxPages) {
            $page = $maxPages;
        }

        //проверяем что страница не меньше минмальной
        if ($page < 1) {
            $page = 1;
        }

        //Если страниц больше 1 - формируем кнопочный переключатель
        if ($maxPages > 1) {
            //Вырезаем кусок из 10 элементов начиная с (номер страницы - 1) * 10
            $facul_list = array_slice($facul_list, ($page - 1) * 10, 10);

            //задаем следующую/предыдущую страницу
            $next = "start " . ($page + 1);
            $prev = "start " . ($page - 1);

            //Если страница первая, то ставим команду игнорирования на кнопку
            if ($page == 1) {
                $prev = "ignore prev";
            }

            //Если страница последняя, то ставим команду игнорирования на кнопку
            if ($page == $maxPages) {
                $next = "ignore next";
            }

            //Добавляем кнопки пагинации
            $facul_list["ignore paginator"] = [
                $prev => "<<",
                "ignore page" => "$page/$maxPages",
                $next => ">>"
            ];
        }

        //Если сообщение из inline кнопки, то изменить сообщение, иначе - отправить новое
        $bot->sendOReditMessage($chatId, "Привет, <b>{$dataUser['name']} {$dataUser['surname']}</b>!\n" .
            "Выбери свой факультет:", $facul_list);
        return;
    } else if ($mode == "facultet") {
        //Список групп факультета

        //Получаем факультет из базы
        $facultet = DB::uFetch("SELECT * FROM `facultets` WHERE `id` = '" . DB::escapeSQLstring($data) . "'");

        //Если не нашли - то выводим ошибку
        if ($facultet == false) {
            $bot->sendOReditMessage($chatId, "Факультет не найден", ["start 1" => "Вернуться назад"]);
            return;
        }

        //Получаем группы  факультета
        $groups = DB::uFetchALL("SELECT * FROM `groups` WHERE `f_id` = '{$facultet['id']}'");

        //Если групп нет, то выводим заглушку
        if (count($groups) < 1) {
            $bot->sendOReditMessage($chatId, "К сожалению, у нас еще нет групп твоего факультета", ["start 1" => "Вернуться назад"]);
            return;
        }

        //Формируем массив групп, с промежуточной переменной $pList, чтобы делать по 2 кнопки в строку
        $g_list = [];
        $pList = null;
        foreach ($groups as $g) {
            //Если $pList пустая(== null), то записать туда текущую строку и перейти к ледующему элементу
            if ($pList == null) {
                $pList = $g;
            } else {
                //Иначе записать элемент-массив в массив $gList с 2мя строками - $pList cначала(предыдущий), а потом текущий
                $g_list[] = [
                    "group " . $pList['id'] => $pList['name'],
                    "group " . $g['id'] => $g['name']
                ];
                //Обнулить $pList
                $pList = null;
            }
        }

        //Если вдруг был еще один элемент, который записан в $pList, но не выведен - добавить его
        if ($pList != null) {
            $g_list["group " . $pList['id']] = $pList['name'];
        }

        //В самый конец добавить кнопку к списку преподавателей факультета
        $g_list["lectors {$facultet['id']}"] = "Преподаватели";

        //Схожая пагинация как предыдущая
        $maxPages = ceil(count($g_list) / 9);
        if ($page > $maxPages) {
            $page = $maxPages;
        }

        if ($page < 1) {
            $page = 1;
        }

        if ($maxPages > 1) {
            $g_list = array_slice($g_list, ($page - 1) * 9, 9);

            $next = "facultet $data " . ($page + 1);
            $prev = "facultet $data " . ($page - 1);

            if ($page == 1) {
                $prev = "ignore prev";
            }


            if ($page == $maxPages) {
                $next = "ignore next";
            }


            $g_list["ignore paginator"] = [
                $prev => "<<",
                "ignore page" => "$page/$maxPages",
                $next => ">>"
            ];
        }

        //В конец добавим кнопку для возврата в список факультетов
        $g_list["start 1"] = "Вернутся назад";

        //Отправим или изменим сообщение
        $bot->sendOReditMessage($chatId, "Ты выбрал <b>{$facultet['name']}</b>. Выбери группу:", $g_list);
        return;
    } else if ($mode == "group") {
        //Расписание для группы

        //Проверяем группу
        $group = DB::uFetch("SELECT * FROM `groups` WHERE `id` = '" . DB::escapeSQLstring($data) . "'");

        //Если не найдена - выдать ошибку
        if ($group == false) {
            $bot->sendOReditMessage($chatId, "Группа не найдена", ["start 1" => "Вернуться в начало"]);
            return;
        }

        //Проверяем факультет
        $facultet = DB::uFetch("SELECT * FROM `facultets` WHERE `id` = '{$group['f_id']}'");

        //Если не найден - выдадим ошибку
        if ($facultet == false) {
            $bot->sendOReditMessage($chatId, "Факультет не найден", ["start 1" => "Вернуться назад"]);
            return;
        }

        //Используем значение страницы (команда group (ид_группы) (страница)) как количество дней на сдвиг от текущей даты. Отнимаем один, иначе текщая дата тоже будет сдвинута на один(стандартное значение page - 1)
        $page--;

        //Получаем текущий день недели на английском(Monday, Tuesday ...) от timestamp(количество секунд от 1 января 1970, дата начала отсчета Unix систем, даты в ней вычисляются), за основу берем текщий день(текущее колчество секунд - time()) и добавляем $page * 86400(60 * 60 * 24 - 86400 секунд в сутках) - получим желаемый день
        $day = date("l", time() + ($page * 86400));
        //Получаем четность недели с помощью тернарного условного оператра: получем номер текущей недели, делим на 2 и берем остаток, если остаток == 0, то четная(even), иначе нечетная(odd)
        $week = date("W", time() + ($page * 86400)) % 2 == 0 ? "even" : "odd";

        //Получаем расписание исходя из дня недели, четности, даты(если дата не указана, или дата - текущая), сортируя по времени начала
        $data = DB::uFetchALL("SELECT * FROM `schedule` WHERE `g_id` = '{$group['id']}' AND `day` = '" . DB::escapeSQLstring($day) . "' AND `week` = '" . DB::escapeSQLstring($week) . "' AND (`date` = '' OR `date` = '" . DB::escapeSQLstring(date('d.m.Y', time() + ($page * 86400))) . "' OR `date` = '" . DB::escapeSQLstring(date('d.m.y', time() + ($page * 86400))) . "') ORDER BY `starttime` ASC");

        //Формируем сообщение. Для выделения используем поддерживаемые теги html
        $message = "Выбранная группа: <b>{$group['name']}</b>, {$facultet['name']}\n" .
            "Дата: <b>" . date('d.m.Y', time() + ($page * 86400)) . "</b> <i>(" . dayToRus($day) . ")</i>\n" .
            "Неделя: <b>" . (date("W", time() + ($page * 86400)) % 2 == 0 ? "четная" : "нечетная") . "</b> <i>(" . date("W", time() + ($page * 86400)) . ")</i>\n\n";

        //Если ничего не найдено в базе
        if ($data == false || count($data) < 1) {
            $message .= "<i>(Расписание на текущий день отсутсвует)</i>";
        }

        //Формируем вывод
        foreach ($data as $d) {
            //Время начала в базе хранится как число, 8:30 - 830, 12:28 - 1228, для удобства сортировки, больший час всегда большее время.
            //Для вывода надо добавить :, для этого берем число как текст, отрезаем последние 2 сивола, добалвяем : и добавляем посление в символа
            $tStart = mb_substr($d['starttime'], 0, -2) . ":" . mb_substr($d['starttime'], -2);
            $tEnd = mb_substr($d['endtime'], 0, -2) . ":" . mb_substr($d['endtime'], -2);

            $message .= " - с <b>$tStart</b> до <b>$tEnd</b> - <b>{$d['name']}</b> - <i>{$d['aud']}</i> - <i>{$d['lector']}</i>\n\n";
        }

        $notif_button = [];

        //Добавляем кнопки оповещений исходя из того, есть ли в базе информация о уже включенном оповещении.
        //Используем страницу для типа оповещения(для препода(от 5) или студента(от 2), и тип - на день(+0), перед парой(+1) и изменение(+2))
        if (DB::uFetch("SELECT `id` FROM `notifications` WHERE `chat_id` = '" . DB::escapeSQLstring($chatId) . "' AND `data_mode` = 'student' AND `data_id` = '" . DB::escapeSQLstring($group['id']) . "' AND `mode` = 'day'") == false) {
            $notif_button["notify {$group['id']} 2"] = "Оповещать о расписании на день";
        } else {
            $notif_button["notify {$group['id']} 2"] = "Не оповещать о расписании на день";
        }

        if (DB::uFetch("SELECT `id` FROM `notifications` WHERE `chat_id` = '" . DB::escapeSQLstring($chatId) . "' AND `data_mode` = 'student' AND `data_id` = '" . DB::escapeSQLstring($group['id']) . "' AND `mode` = 'schedule'") == false) {
            $notif_button["notify {$group['id']} 3"] = "Оповещать о предстоящем занятии";
        } else {
            $notif_button["notify {$group['id']} 3"] = "Не оповещать о предстоящем занятии";
        }

        if (DB::uFetch("SELECT `id` FROM `notifications` WHERE `chat_id` = '" . DB::escapeSQLstring($chatId) . "' AND `data_mode` = 'student' AND `data_id` = '" . DB::escapeSQLstring($group['id']) . "' AND `mode` = 'change'") == false) {
            $notif_button["notify {$group['id']} 4"] = "Оповещать об изменениях в расписании";
        } else {
            $notif_button["notify {$group['id']} 4"] = "Не оповещать об изменениях в расписании";
        }

        //Ну и само собой возврат в начало
        $notif_button["start 1"] = "Вернуться в начало";

        //Отправляем или изменяем сообщение
        $bot->sendOReditMessage($chatId, $message, array_merge([["group {$group['id']} " . ($page - 6) => "<<", "group {$group['id']} " . ($page) => "<", "group {$group['id']} " . ($page + 2) => ">", "group {$group['id']} " . ($page + 8) => ">>"]], $notif_button));
    } else if ($mode == "lectors") {
        //Проверяем факультет
        $facultet = DB::uFetch("SELECT * FROM `facultets` WHERE `id` = '" . DB::escapeSQLstring($data) . "'");

        //Если не найден - выводим ошибку
        if ($facultet == false) {
            $bot->sendOReditMessage($chatId, "Факультет не найден", ["start 1" => "Вернуться назад"]);
            return;
        }

        //Получаем список преподов факультета. Использована переменная $groups, так как код почти полностью повторяет предыдущий схожий и просто взят и скопирован с мелкими изменениями))
        //А переменную лень было переименовывать
        $groups = DB::uFetchALL("SELECT * FROM `lectors` WHERE `f_id` = '{$facultet['id']}' ORDER BY `name` ASC");

        //Почти все схожо с аналогичной функцией выше
        if (count($groups) < 1) {
            $bot->sendOReditMessage($chatId, "К сожалению у нас еще нет преподавателей твоего факультета {$facultet['id']}", ["start 1" => "Вернуться назад"]);
            return;
        }

        $g_list = [];
        $pList = null;
        foreach ($groups as $g) {
            if ($pList == null) {
                $pList = $g;
            } else {
                $g_list[] = [
                    "lector " . $pList['id'] => $pList['name'],
                    "lector " . $g['id'] => $g['name']
                ];
                $pList = null;
            }
        }

        if ($pList != null) {
            $g_list["lector " . $pList['id']] = $pList['name'];
        }

        $maxPages = ceil(count($g_list) / 9);
        if ($page > $maxPages) {
            $page = $maxPages;
        }

        if ($page < 1) {
            $page = 1;
        }

        if ($maxPages > 1) {
            $g_list = array_slice($g_list, ($page - 1) * 9, 9);

            $next = "lectors $data " . ($page + 1);
            $prev = "lectors $data " . ($page - 1);

            if ($page == 1) {
                $prev = "ignore prev";
            }


            if ($page == $maxPages) {
                $next = "ignore next";
            }


            $g_list["ignore paginator"] = [
                $prev => "<<",
                "ignore page" => "$page/$maxPages",
                $next => ">>"
            ];
        }

        $g_list["start 1"] = "Вернутся назад";

        $bot->sendOReditMessage($chatId, "Преподаватели <b>{$facultet['name']}</b>. Выбери преподавателя:", $g_list);
        return;
    } else if ($mode == "lector") {
        //Аналогичная функция как для группы с мелкими изменениями и одним крупным, в выборе строк расписания

        $lector = DB::uFetch("SELECT * FROM `lectors` WHERE `id` = '" . DB::escapeSQLstring($data) . "'");

        if ($lector == false) {
            $bot->sendOReditMessage($chatId, "Преподаватель не найден", ["start 1" => "Вернуться в начало"]);
            return;
        }

        $facultet = DB::uFetch("SELECT * FROM `facultets` WHERE `id` = '{$lector['f_id']}'");

        if ($facultet == false) {
            $bot->sendOReditMessage($chatId, "Факультет не найден", ["start 1" => "Вернуться назад"]);
            return;
        }


        $page--;

        $day = date("l", time() + ($page * 86400));
        $week = date("W", time() + ($page * 86400)) % 2 == 0 ? "even" : "odd";

        //Поменяли выбор не по ид группы, а по имени препода и добавили вхождение запроса, для проверки, что группа принадлежит тому же факультету, что и препод
        //проверив, чтобы поиск в таблице groups, где id группы соответсовал строке из раписания и ид факултета был равен указанному у препода, выдавал бы хлть одну строку(EXISTS)
        $data = DB::uFetchALL("SELECT * FROM `schedule` s WHERE `lector` = '" . DB::escapeSQLstring($lector['name']) . "' AND EXISTS(SELECT `id` FROM `groups` g WHERE s.`g_id` = g.`id` AND g.`f_id` = '{$lector['f_id']}') AND `day` = '" . DB::escapeSQLstring($day) . "' AND `week` = '" . DB::escapeSQLstring($week) . "' AND (`date` = '' OR `date` = '" . DB::escapeSQLstring(date('d.m.Y', time() + ($page * 86400))) . "' OR `date` = '" . DB::escapeSQLstring(date('d.m.y', time() + ($page * 86400))) . "') ORDER BY s.`starttime` ASC");

        $message = "Расписание преподавателя: <b>{$lector['name']}</b>, {$facultet['name']}\n" .
            "Дата: <b>" . date('d.m.Y', time() + ($page * 86400)) . "</b> <i>(" . dayToRus($day) . ")</i>\n" .
            "Неделя: <b>" . (date("W", time() + ($page * 86400)) % 2 == 0 ? "четная" : "нечетная") . "</b> <i>(" . date("W", time() + ($page * 86400)) . ")</i>\n\n";

        if ($data == false || count($data) < 1) {
            $message .= "<i>(Расписание на текущий день отсутсвует)</i>";
        }

        foreach ($data as $d) {
            $group = DB::uFetch("SELECT `name` FROM `groups` WHERE `id` = '{$d['g_id']}'");
            if ($group == false) {
                $group = ["name" => "<i>(не известно)</i>"];
            }

            $tStart = mb_substr($d['starttime'], 0, -2) . ":" . mb_substr($d['starttime'], -2);
            $tEnd = mb_substr($d['endtime'], 0, -2) . ":" . mb_substr($d['endtime'], -2);

            $message .= " - с <b>$tStart</b> до <b>$tEnd</b> - <b>{$d['name']}</b> - <i>{$d['aud']}</i> - <i>{$group['name']}, {$facultet['name']}</i>\n\n";
        }

        $notif_button = [];

        if (DB::uFetch("SELECT `id` FROM `notifications` WHERE `chat_id` = '" . DB::escapeSQLstring($chatId) . "' AND `data_mode` = 'lector' AND `data_id` = '" . DB::escapeSQLstring($lector['id']) . "' AND `mode` = 'day'") == false) {
            $notif_button["notify {$lector['id']} 5"] = "Оповещать о расписании на день";
        } else {
            $notif_button["notify {$lector['id']} 5"] = "Не оповещать о расписании на день";
        }

        if (DB::uFetch("SELECT `id` FROM `notifications` WHERE `chat_id` = '" . DB::escapeSQLstring($chatId) . "' AND `data_mode` = 'lector' AND `data_id` = '" . DB::escapeSQLstring($lector['id']) . "' AND `mode` = 'schedule'") == false) {
            $notif_button["notify {$lector['id']} 6"] = "Оповещать о предстоящем занятии";
        } else {
            $notif_button["notify {$lector['id']} 6`"] = "Не оповещать о предстоящем занятии";
        }

        if (DB::uFetch("SELECT `id` FROM `notifications` WHERE `chat_id` = '" . DB::escapeSQLstring($chatId) . "' AND `data_mode` = 'lector' AND `data_id` = '" . DB::escapeSQLstring($lector['id']) . "' AND `mode` = 'change'") == false) {
            $notif_button["notify {$lector['id']} 7"] = "Оповещать об изменениях в расписании";
        } else {
            $notif_button["notify {$lector['id']} 7"] = "Не оповещать об изменениях в расписании";
        }

        $notif_button["start 1"] = "Вернуться в начало";

        $bot->sendOReditMessage($chatId, $message, array_merge([["lector {$lector['id']} " . ($page - 6) => "<<", "lector {$lector['id']} " . ($page) => "<", "lector {$lector['id']} " . ($page + 2) => ">", "lector {$lector['id']} " . ($page + 8) => ">>"]], $notif_button));
    } else if ($mode == "notify") {
        //Проверяем наличие такой группы/преподавателя
        if ($page >= 2 and $page <= 4) {
            $group = DB::uFetch("SELECT * FROM `groups` WHERE `id` = '" . DB::escapeSQLstring($data) . "'");
        } else {
            $group = DB::uFetch("SELECT * FROM `lectors` WHERE `id` = '" . DB::escapeSQLstring($data) . "'");
        }

        //Если не нашли - ошибка
        if ($group == false) {
            $bot->sendOReditMessage($chatId, "Группа/преподователь не найден(а)", ["start 1" => "Вернуться в начало"]);
            return;
        }
        $mode = null;
        $data_mode = null;

        //Задаем тип уведомления, которое требуется изменить: препод/студент и день/перед парой/изменение
        switch ($page) {
            case 2:
            {
                $mode = 'day';
                $data_mode = "student";
                break;
            }
            case 3:
            {
                $mode = 'schedule';
                $data_mode = "student";
                break;
            }
            case 4:
            {
                $mode = 'change';
                $data_mode = "student";
                break;
            }
            case 5:
            {
                $mode = 'day';
                $data_mode = "lector";
                break;
            }
            case 6:
            {
                $mode = 'schedule';
                $data_mode = "lector";
                break;
            }
            case 7:
            {
                $mode = 'change';
                $data_mode = "lector";
                break;
            }
            default:
            {
                return;
            }
        }

        //проверяем, включено ли уже такое уведомление
        $notif = DB::uFetch("SELECT `id` FROM `notifications` WHERE `chat_id` = '" . DB::escapeSQLstring($chatId) . "' AND `data_mode` = '$data_mode' AND `data_id` = '" . DB::escapeSQLstring($group['id']) . "' AND `mode` = '$mode'");

        if ($notif != false) {
            //Если включено - тио отключаем удалив из списка в базе
            DB::uQuery("DELETE FROM `notifications` WHERE `id` = '{$notif['id']}'");
            if (!empty($update['callback_query_id'])) {
                //Уведомляем пользователя уведомлением Toast непосредственно в телеге, отправив ответ на запрос.
                $bot->answerCallbackQuery($update['callback_query_id'], "Вы успешно отписаны!");
            }
        } else {
            //Добавлеяем пользователя, еслт
            DB::uQuery("INSERT INTO `notifications`(`chat_id`, `data_mode`, `data_id`, `mode`) VALUES('" . DB::escapeSQLstring($chatId) . "', '$data_mode', '" . DB::escapeSQLstring($group['id']) . "', '$mode')");
            if (!empty($update['callback_query_id'])) {
                $bot->answerCallbackQuery($update['callback_query_id'], "Вы успешно подписаны!");
            }
        }

        //Ставим что "входящее сообщение" =
        $messageIn = "group {$group['id']} 1";
        if ($data_mode == "lector") {
            $messageIn = "lector {$group['id']} 1";
        }

        //Продолжаем программу с метки
        goto start;
    }


});
//Обрабатываем сообщение от сервера телеги
$bot->poll();

//Функция для проверки, начинается ли строка $haystack с $needle
function startsWith($haystack, $needle)
{
    // search backwards starting from haystack length characters from the end
    return $needle === "" || mb_strrpos($haystack, $needle, -mb_strlen($haystack)) !== false;
}

//Переводим название недели в русский
function dayToRus($day)
{
    switch (mb_strtolower($day)) {
        case "monday":
        {
            return "Понедельник";
            break;
        }
        case "tuesday":
        {
            return "Вторник";
            break;
        }
        case "wednesday":
        {
            return "Среда";
            break;
        }
        case "thursday":
        {
            return "Четверг";
            break;
        }
        case "friday":
        {
            return "Пятница";
            break;
        }
        case "saturday":
        {
            return "Суббота";
            break;
        }
        case "sunday":
        {
            return "Воскресенье";
            break;
        }
    }
}
