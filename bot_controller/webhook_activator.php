<?php
const MODULE = true;

require_once dirname(__DIR__) . '/include/settings.php';
require_once dirname(__DIR__) . '/include/class-telegram.php';

//Создаем объект Telegram с указанным токеном
$bot = new Telegram(BOT_TOKEN);
//Устанавливаем вебхук на указанный линк с секретной фразой
$bot->setWebhook(WRAPPER_LINK . "?secret=" . SECRET_KEY);
exit("OK");
