<?php

class TPL
{

    private static $CONTENT = "";
    private static $ALERT = "";

    public static function addTemplateToContent($name, $data = [])
    {

        if (!file_exists("template/$name.tpl")) {
            throw new Exception("NoTemplateFound");
        }

        $content = file_get_contents("template/$name.tpl");

        foreach ($data as $key => $val) {
            $content = str_replace("%$key%", $val, $content);
        }
        self::$CONTENT .= $content;

        return;
    }

    public static function getTemplateContent($name, $data)
    {
        if (!file_exists("template/$name.tpl")) {
            throw new Exception("NoTemplateFound");
        }

        $content = file_get_contents("template/$name.tpl");

        foreach ($data as $key => $val) {
            $content = str_replace("%$key%", $val, $content);
        }

        return $content;
    }

    public static function makeAlert($text, $name = "", $type = "error")
    {
        self::$ALERT = "new swal('$name', '$text', '$type');";
    }

    public static function redirectTo($url)
    {
        $host = $_SERVER['HTTP_HOST'];
        $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

        header("Location: http://$host$uri/$url");
        die("<a href='http://$host$uri/$url' style='text-align: center;'>Redirecting to $link</a>");
    }

    /** @noinspection PhpUnnecessaryLocalVariableInspection */
    public static function getTemplate()
    {
        if (!file_exists("template/main.tpl")) {
            throw new Exception("NoMainTemplateFound");
        }

        $tpl_main = file_get_contents("template/main.tpl");

        $tpl_main = str_replace("%CONTENT%", self::$CONTENT, $tpl_main);
        $tpl_main = str_replace("%ALERT%", self::$ALERT, $tpl_main);

        return $tpl_main;
    }
}