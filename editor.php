<?php
const MODULE = true;

session_start();

require_once "include/init.php";

if (empty($_SESSION["authenticated"])) {
    TPL::redirectTo("index.php");
}

$data = null;

$content = [
    "NAME" => "",
    "CONTENT" => ""
];


if (!empty($_GET['id'])) {
    if (!is_numeric($_GET['id'])) {
        TPL::redirectTo("list.php");
    }

    $data = DB::fetch("SELECT * FROM `pages` WHERE `id` = :id", ["id" => $_GET['id']]);

    if ($data == false) {
        TPL::redirectTo("list.php");
    }

    $content = [
        "NAME" => $data['name'],
        "CONTENT" => $data['content']
    ];
}

if (!empty($_POST['name']) && !empty($_POST['content'])) {
    if ($data != null) {
        DB::query("UPDATE `pages` SET `name` = :name, `content` = :content WHERE `id`  = :id", ['name' => $_POST['name'], "content" => $_POST["content"], "id" => $data['id']]);
    } else {
        DB::insert("pages", [
            "name" => $_POST['name'],
            "content" => $_POST['content']
        ]);
    }

    TPL::redirectTo("list.php?saved");
}

TPL::addTemplateToContent("editor", $content);


echo TPL::getTemplate();