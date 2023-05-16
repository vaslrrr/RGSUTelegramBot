<?php
const MODULE = true;

session_start();

require_once "include/init.php";

if (empty($_SESSION["authenticated"])) {
    TPL::redirectTo("index.php");
}

if (!empty($_GET['del'])) {
    DB::query("DELETE FROM `pages` WHERE `id` = :id", ["id" => $_GET['del']]);
    TPL::redirectTo("list.php?deleted");
}

$data = DB::fetchAll("SELECT * FROM `pages`");

$rows = "";

foreach ($data as $line) {
    $rows .= TPL::getTemplateContent("list_row", ["ID" => $line['id'], "NAME" => $line['name']]);
}

if (isset($_GET['saved'])) {
    TPL::makeAlert("Страница сохранена!", "Успешно!", "success");
}

if (isset($_GET['deleted'])) {
    TPL::makeAlert("Страница удалена", "Успешно!", "success");
}

TPL::addTemplateToContent("list", ['ROWS' => $rows]);

echo TPL::getTemplate();