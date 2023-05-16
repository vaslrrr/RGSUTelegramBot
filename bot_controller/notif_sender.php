<?php
const MODULE = true;

require_once dirname(__DIR__) . '/include/settings.php';
require_once dirname(__DIR__) . '/include/class-telegram.php';
require_once dirname(__DIR__) . '/include/database_control.php';

//ФАЙЛ ДОБАВЛЕН В cron(планировщик) и выполняется раз в 5 минут(потому что пары начинаются всегда кратно 5)

//Инициализируем базу данных
DB::init();

//Инициализируем бота
$bot = new Telegram(BOT_TOKEN);

//Записываем текущее время. Так как может пройти n секунд, на всякий случай создаем таймстамп относительно 0 секунд текущего часа и минуты
$timestamp = mktime(date("H"), date("i"), 0);
//Создаем время относительно таймстампа в формате 832, т.е. без : и всяких лишних символов
$time = date('Hi', $timestamp);

//Получаем все нужные уведомления с режимом день и для студентп
$day_list = DB::uFetchALL("SELECT * FROM `notifications` WHERE `data_mode` = 'student' AND `mode` = 'day'");
foreach ($day_list as $day_notif) {
    //Получаем группу
    $group = DB::uFetch("SELECT * FROM `groups` WHERE `id` = '{$day_notif['data_id']}'");

    //Если группа не найдена - удаляем оповещение из базы и переходим к следующему
    if ($group == false) {
        DB::uQuery("DELETE FROM `notifications` WHERE `id` = '{$day_notif['id']}'");
        continue;
    }

    //Получаем факультет
    $fac = DB::uFetch("SELECT * FROM `facultets` WHERE `id` = '{$group['f_id']}'");

    if ($fac == false) {
        continue;
    }

    //Получаем самую первую пару.
    //Пришлось выяснять время первой пары и относительно этого времени искать пары в это время и даты ввиду того, что могут быть накладки пар(несколько в одно время, поэтому оповещаем обо всех парах в это время)
    $first_shed_el = DB::uFetch("SELECT `starttime` FROM `schedule` WHERE `g_id` = '{$group['id']}' AND `day` = '" . DB::escapeSQLstring(date("l")) . "' AND `week` = '" . DB::escapeSQLstring(date("W") % 2 == 0 ? "even" : "odd") . "' AND (`date` = '' OR `date` = '" . DB::escapeSQLstring(date('d.m.Y')) . "' OR `date` = '" . DB::escapeSQLstring(date('d.m.y')) . "') ORDER BY `starttime` ASC LIMIT 1");

    //Если есть в этот день первая пара
    if ($first_shed_el != false) {
        //Получаем все пары в это время
        $first_shed_all = DB::uFetchALL("SELECT * FROM `schedule` WHERE `g_id` = '{$group['id']}' AND `day` = '" . DB::escapeSQLstring(date("l")) . "' AND `week` = '" . DB::escapeSQLstring(date("W") % 2 == 0 ? "even" : "odd") . "' AND (`date` = '' OR `date` = '" . DB::escapeSQLstring(date('d.m.Y')) . "' OR `date` = '" . DB::escapeSQLstring(date('d.m.y')) . "') AND `starttime` = '{$first_shed_el['starttime']}'");
        foreach ($first_shed_all as $first_shed) {
            //Получаем таймстамп начала пары, но в привязке
            $timestampStart = mktime(mb_substr($first_shed['starttime'], 0, -2), mb_substr($first_shed['starttime'], -2), 0);

            //Проверяем, что разница между текщим таймстампом временем и таймстампом времени - 60*60(час)
            if ($timestampStart - $timestamp == 60 * 60) {
                //Делаем красивое вермя(с двоеточием)
                $tStart = mb_substr($first_shed['starttime'], 0, -2) . ":" . mb_substr($first_shed['starttime'], -2);
                $tEnd = mb_substr($first_shed['endtime'], 0, -2) . ":" . mb_substr($first_shed['endtime'], -2);

                //Отправляем сообщение
                $bot->sendMessage($day_notif['chat_id'], "Напоминание для {$group['name']}, {$fac['name']}! Через 60 минут будет пара!\n" .
                    " - с <b>$tStart</b> до <b>$tEnd</b> - <b>{$first_shed['name']}</b> - <i>{$first_shed['aud']}</i> - <i>{$first_shed['lector']}</i>", ["group {$group['id']} 1" => "Посмотреть полное расписание"]);
            }
        }
    }
}

//Получаем список целевых уведомлений на оповещение о предстоящей паре
$schedule_list = DB::uFetchALL("SELECT * FROM `notifications` WHERE `data_mode` = 'student' AND `mode` = 'schedule'");
foreach ($schedule_list as $shed_notif) {
    //Проверки
    $group = DB::uFetch("SELECT * FROM `groups` WHERE `id` = '{$shed_notif['data_id']}'");
    if ($group == false) {
        DB::uQuery("DELETE FROM `notifications` WHERE `id` = '{$shed_notif['id']}'");
        continue;
    }

    $fac = DB::uFetch("SELECT * FROM `facultets` WHERE `id` = '{$group['f_id']}'");

    if ($fac == false) {
        continue;
    }

    //Получаем список предстоящих пар на этот день
    $next_sheds = DB::uFetchALL("SELECT * FROM `schedule` WHERE `g_id` = '{$group['id']}' AND `day` = '" . DB::escapeSQLstring(date("l")) . "' AND `week` = '" . DB::escapeSQLstring(date("W") % 2 == 0 ? "even" : "odd") . "' AND (`date` = '' OR `date` = '" . DB::escapeSQLstring(date('d.m.Y')) . "' OR `date` = '" . DB::escapeSQLstring(date('d.m.y')) . "') AND `starttime` > '$time' ORDER BY `starttime` ASC");
    foreach ($next_sheds as $next_shed) {
        //Формируем таймстамп
        $timestampStart = mktime(mb_substr($next_shed['starttime'], 0, -2), mb_substr($next_shed['starttime'], -2), 0);

        if ($timestampStart - $timestamp == 15 * 60) {
            $tStart = mb_substr($next_shed['starttime'], 0, -2) . ":" . mb_substr($next_shed['starttime'], -2);
            $tEnd = mb_substr($next_shed['endtime'], 0, -2) . ":" . mb_substr($next_shed['endtime'], -2);

            //Отправляем сообщение
            $bot->sendMessage($shed_notif['chat_id'], "Напоминание для {$group['name']}, {$fac['name']}! Через 15 минут будет пара!\n" .
                " - с <b>$tStart</b> до <b>$tEnd</b> - <b>{$next_shed['name']}</b> - <i>{$next_shed['aud']}</i> - <i>{$next_shed['lector']}</i>", ["group {$group['id']} 1" => "Посмотреть полное расписание"]);
        }
    }
}

//Получаем список оповещений для преподавателя перед первой парой
$day_list = DB::uFetchALL("SELECT * FROM `notifications` WHERE `data_mode` = 'lector' AND `mode` = 'day'");
foreach ($day_list as $day_notif) {

    //Проверяем существование такого препода в базе
    $lector = DB::uFetch("SELECT * FROM `lectors` WHERE `id` = '{$day_notif['data_id']}'");
    if ($lector == false) {
        DB::uQuery("DELETE FROM `notifications` WHERE `id` = '{$day_notif['id']}'");
        continue;
    }

    //Алгоритм дальше такой же как для группы, только с дополнительной проверкой в запросе на строку, чтобы группа расписания имела тот же факультет. что и препод
    //И еще получаем название группы и факультета, чтобы выслать их в уведомлении

    $first_shed_el = DB::uFetch("SELECT * FROM `schedule` s WHERE EXISTS(SELECT `id` FROM `groups` g WHERE s.`g_id` = g.`id` AND g.`f_id` = '{$lector['f_id']}') AND `lector` = '" . DB::escapeSQLstring($lector['name']) . "' AND `day` = '" . DB::escapeSQLstring(date("l")) . "' AND `week` = '" . DB::escapeSQLstring(date("W") % 2 == 0 ? "even" : "odd") . "' AND (`date` = '' OR `date` = '" . DB::escapeSQLstring(date('d.m.Y')) . "' OR `date` = '" . DB::escapeSQLstring(date('d.m.y')) . "') ORDER BY `starttime` ASC LIMIT 1");
    if ($first_shed_el != false) {
        $first_shed_all = DB::uFetchALL("SELECT * FROM `schedule` s WHERE EXISTS(SELECT `id` FROM `groups` g WHERE s.`g_id` = g.`id` AND g.`f_id` = '{$lector['f_id']}') AND `lector` = '" . DB::escapeSQLstring($lector['name']) . "' AND `day` = '" . DB::escapeSQLstring(date("l")) . "' AND `week` = '" . DB::escapeSQLstring(date("W") % 2 == 0 ? "even" : "odd") . "' AND (`date` = '' OR `date` = '" . DB::escapeSQLstring(date('d.m.Y')) . "' OR `date` = '" . DB::escapeSQLstring(date('d.m.y')) . "') AND `starttime` = '{$first_shed_el['starttime']}'");
        foreach ($first_shed_all as $first_shed) {
            $group = DB::uFetch("SELECT * FROM `groups` WHERE `id` = '{$first_shed['g_id']}'");

            if ($group == false) {
                continue;
            }

            $fac = DB::uFetch("SELECT * FROM `facultets` WHERE `id` = '{$group['f_id']}'");

            if ($fac == false) {
                continue;
            }

            $timestampStart = mktime(mb_substr($first_shed['starttime'], 0, -2), mb_substr($first_shed['starttime'], -2), 0);
            if ($timestampStart - $timestamp == 60 * 60) {
                $tStart = mb_substr($first_shed['starttime'], 0, -2) . ":" . mb_substr($first_shed['starttime'], -2);
                $tEnd = mb_substr($first_shed['endtime'], 0, -2) . ":" . mb_substr($first_shed['endtime'], -2);

                $bot->sendMessage($day_notif['chat_id'], "Напоминание для {$lector['name']}, {$fac['name']}! Через 60 минут будет пара!\n" .
                    " - с <b>$tStart</b> до <b>$tEnd</b> - <b>{$first_shed['name']}</b> - <i>{$first_shed['aud']}</i> - <i>{$group['name']}, {$fac['name']}</i>", ["lector {$lector['id']} 1" => "Посмотреть полное расписание"]);
            }
        }
    }
}

//Получаем список запроса оповещений преподу для рассылки оповещения
$schedule_list = DB::uFetchALL("SELECT * FROM `notifications` WHERE `data_mode` = 'lector' AND `mode` = 'schedule'");

foreach ($schedule_list as $shed_notif) {

    //Алгоритм совмещает два предыдущих, комментарии излишни

    $lector = DB::uFetch("SELECT * FROM `lectors` WHERE `id` = '{$shed_notif['data_id']}'");
    if ($lector == false) {
        DB::uQuery("DELETE FROM `notifications` WHERE `id` = '{$shed_notif['id']}'");
        continue;
    }

    $next_sheds = DB::uFetchALL("SELECT * FROM `schedule` s WHERE EXISTS(SELECT `id` FROM `groups` g WHERE s.`g_id` = g.`id` AND g.`f_id` = '{$lector['f_id']}') AND `lector` = '" . DB::escapeSQLstring($lector['name']) . "' AND `day` = '" . DB::escapeSQLstring(date("l")) . "' AND `week` = '" . DB::escapeSQLstring(date("W") % 2 == 0 ? "even" : "odd") . "' AND (`date` = '' OR `date` = '" . DB::escapeSQLstring(date('d.m.Y')) . "' OR `date` = '" . DB::escapeSQLstring(date('d.m.y')) . "') AND `starttime` > '$time' ORDER BY `starttime` ASC");
    foreach ($next_sheds as $next_shed) {
        $group = DB::uFetch("SELECT * FROM `groups` WHERE `id` = '{$next_shed['g_id']}'");

        if ($group == false) {
            continue;
        }

        $fac = DB::uFetch("SELECT * FROM `facultets` WHERE `id` = '{$group['f_id']}'");

        if ($fac == false) {
            continue;
        }

        $timestampStart = mktime(mb_substr($next_shed['starttime'], 0, -2), mb_substr($next_shed['starttime'], -2), 0);

        if ($timestampStart - $timestamp == 15 * 60) {
            $tStart = mb_substr($next_shed['starttime'], 0, -2) . ":" . mb_substr($next_shed['starttime'], -2);
            $tEnd = mb_substr($next_shed['endtime'], 0, -2) . ":" . mb_substr($next_shed['endtime'], -2);

            $bot->sendMessage($shed_notif['chat_id'], "Напоминание для {$lector['name']}, {$fac['name']}! Через 15 минут будет пара!\n" .
                " - с <b>$tStart</b> до <b>$tEnd</b> - <b>{$next_shed['name']}</b> - <i>{$next_shed['aud']}</i> - <i>{$group['name']}, {$fac['name']}</i>", ["lector {$lector['id']} 1" => "Посмотреть полное расписание"]);
        }
    }
}