<?php
const MODULE = true;

session_start();

require_once "include/init.php";
require_once __DIR__ . '/include/class-telegram.php';


if (empty($_SESSION["authenticated"])) {
    TPL::redirectTo("index.php");
}


if (!empty($_POST['message'])) {
    $users = DB::query("SELECT `chat_id` FROM `users`");

    $cnt = 0;
    $bot = new Telegram(BOT_TOKEN);

    foreach ($users as $user) {
        if ($cnt++ > 35) {
            sleep(1);
            $cnt = 0;
        }

        $bot->sendMessage($user['chat_id'], $_POST['message']);
    }

    TPL::redirectTo("newsletter.php?sended");
} else {
    if (isset($_GET['sended'])) {
        TPL::makeAlert("Рассылка завершена", "Успешно", "success");
    }
}

TPL::addTemplateToContent("newsletter");

echo TPL::getTemplate();