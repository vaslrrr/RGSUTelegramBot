<?php
if (!defined("MODULE")) {
    exit("HACKING ATTEMPT");
}

class Telegram
{

    private $token = null;
    private $handler = null;
    private $active = false;
    private $username = null;
    private $last_callback_data = null;

    /**
     * Конструктор класса Telegram
     * @param $auth string Токен бота
     */
    public function __construct($token)
    {
        $this->token = $token;
        if (!empty($token)) {
            $this->active = true;
        }
    }

    /**
     * Основной метод для работы с API
     * @param $method String Метод для вызова
     * @param $fields array Параметры запроса
     * @return mixed Результат запроса
     */
    public function apiRequest($method, $fields = array())
    {
        if ($this->token == null) {
            return false;
        }

        $url = 'https://api.telegram.org/bot' . $this->token . '/' . $method;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        // POST запрос. Также возможен GET и передача JSON
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 1);

        $response = curl_exec($ch);
        if ($response === false) {
            $this->processRequestError(curl_error($ch));
        }
        curl_close($ch);
        $response = json_decode($response, true);
        if ($response == null || empty($response["ok"])) {
            $this->processAPIError($response);
        }
        return $response;
    }

    /**
     * Обработка ошибок при запросе
     * @param $error
     */
    private function processRequestError($error)
    {
        $this->processError("Ошибка при запросе. Сервер вернул код " . $error);
    }

    /**
     * Обработка ошибок при работе с API
     * @param $response array Результат запроса к API
     */
    private function processAPIError($response)
    {
        $this->processError("Ошибка при работе с API. Детали: " . json_encode($response));
    }

    /**
     * Метод для обработки и оповещения об ошибках
     * @param $error String строка для отправки
     */
    private function processError($error)
    {

    }

    /**
     * Метод для загрузки изображения
     * @param $fileid String ID file
     */
    public function downloadImage($fileid)
    {
        if ($this->active == false) {
            return false;
        }

        $cc = 0;

        get_img:
        $apiRequest = $this->apiRequest('getFile', array('file_id' => $fileid));
        return file_get_contents('https://api.telegram.org/file/bot' . $this->token . '/' . $apiRequest->result->file_path);
    }

    /**
     * Отправка изображения
     * @param $path String ID file
     */
    public function sendPhoto($chatid, $path)
    {
        if ($this->active == false) {
            return false;
        }

        $fileid = new CURLFile($path);
        $this->apiRequest('sendPhoto', array('chat_id' => $chatid, 'photo' => $fileid));
        $this->last_callback_data = null;
        return false;
    }

    public function setWebhook($url)
    {
        $this->apiRequest('setWebhook', ['url' => $url, 'allowed_updates' => '["message","callback_query"]', 'drop_pending_updates' => "true"]);
        return true;
    }

    public function deleteWebhook()
    {
        if ($this->active == false) {
            return false;
        }

        return $this->apiRequest('deleteWebhook', ['drop_pending_updates' => "true"]);
    }

    /**
     * Отправляет сообщение пользователю
     * @param $chat_id String|Int Чат
     * @param $text String Текст для отправки
     * @param $inline_keyboard array|null Клавиатура inline
     * @param $keyboard array|null Клавиатура reply. Если указана inline, только она будет отправлена
     * @param $removeKeyboard boolean Удалить клавиатуру,если указана inline или reply - что выше, то будет отправлено
     * @param $options array дополнительные параметры
     */
    public function sendMessage($chat_id, $text, $inline_keyboard = [], $keyboard = [], $removeKeyboard = false, $options = [])
    {
        if ($this->active == false) {
            return false;
        }

        $fields = array_merge([
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => "HTML"
        ], $options);

        $markup = [];

        if (!empty($keyboard)) {
            $keys = [
                "keyboard" => [],
                "resize_keyboard" => true
            ];

            if (!empty($keyboard['placeholder'])) {
                $keys["input_field_placeholder"] = $keyboard['placeholder'];
                unset($keyboard['placeholder']);
            }

            foreach ($keyboard as $key) {
                $keys["keyboard"][] = [["text" => $key]];
            }

            $markup = $keys;
        }

        if ($removeKeyboard == true) {
            $markup = ["remove_keyboard" => true];
        }

        if (!empty($inline_keyboard)) {
            $keys = [
                "inline_keyboard" => []
            ];

            foreach ($inline_keyboard as $key => $value) {
                if (is_array($value)) {
                    $elements = [];
                    foreach ($value as $k => $v) {
                        $elements[] = ["text" => $v, "callback_data" => $k];
                    }
                    $keys["inline_keyboard"][] = $elements;
                } else {
                    $keys["inline_keyboard"][] = [["text" => $value, "callback_data" => $key]];
                }
            }

            $markup = $keys;
        }

        if (!empty($markup)) {
            $fields["reply_markup"] = json_encode($markup, JSON_UNESCAPED_UNICODE);
        }

        $this->last_callback_data = null;
        return $this->apiRequest('sendMessage', $fields);
    }

    /**
     * Отправляет сообщение пользователю
     * @param $message_id String|Int Изменяет сообщение с указанным ID
     * @param $text String Текст для отправки
     * @param $inline_keyboard array|null Клавиатура inline
     * @param $keyboard array|null Клавиатура reply. Если указана inline, только она будет отправлена
     * @param $removeKeyboard boolean Удалить клавиатуру,если указана inline или reply - что выше, то будет отправлено
     */
    public function editMessage($message_id, $text, $inline_keyboard = [], $keyboard = [], $removeKeyboard = false)
    {
        if ($this->active == false) {
            return false;
        }

        $msg = explode(" - ", $message_id);

        $fields = [
            'chat_id' => $msg[1],
            'message_id' => $msg[0],
            'text' => $text,
            'parse_mode' => "HTML"
        ];

        $markup = [];

        if (!empty($keyboard)) {
            $keys = [
                "keyboard" => [],
                "resize_keyboard" => true
            ];

            if (!empty($keyboard['placeholder'])) {
                $keys["input_field_placeholder"] = $keyboard['placeholder'];
                unset($keyboard['placeholder']);
            }

            foreach ($keyboard as $key) {
                $keys["keyboard"][] = [["text" => $key]];
            }

            $markup = $keys;
        }

        if ($removeKeyboard == true) {
            $markup = ["remove_keyboard" => true];
        }


        if (!empty($inline_keyboard)) {
            $keys = [
                "inline_keyboard" => []
            ];

            foreach ($inline_keyboard as $key => $value) {
                if (is_array($value)) {
                    $elements = [];
                    foreach ($value as $k => $v) {
                        $elements[] = ["text" => $v, "callback_data" => $k];
                    }
                    $keys["inline_keyboard"][] = $elements;
                } else {
                    $keys["inline_keyboard"][] = [["text" => $value, "callback_data" => $key]];
                }
            }

            $markup = $keys;
        }

        if (!empty($markup)) {
            $fields["reply_markup"] = json_encode($markup, JSON_UNESCAPED_UNICODE);
        }

        return $this->apiRequest('editMessageText', $fields);
    }

    /**
     * Отправляет или изменяет сообщение пользователю
     * Сообщение изменяется в том случае, если входящее обновление от телеграмм - реакция на inline кнопку
     * @param $chat_id String|Int Чат
     * @param $text String Текст для отправки
     * @param $inline_keyboard array|null Клавиатура inline
     * @param $keyboard array|null Клавиатура reply. Если указана inline, только она будет отправлена
     * @param $removeKeyboard boolean Удалить клавиатуру,если указана inline или reply - что выше, то будет отправлено
     */
    public function sendOReditMessage($chat_id, $text, $inline_keyboard = [], $keyboard = [], $removeKeyboard = false)
    {
        if (!empty($this->last_callback_data)) {
            return $this->editMessage($this->last_callback_data, $text, $inline_keyboard, $keyboard, $removeKeyboard);
        } else {
            return $this->sendMessage($chat_id, $text, $inline_keyboard, $keyboard, $removeKeyboard);
        }
    }

    /**
     * Устанавливает меню для конкретного пользователя
     */
    public function setMenu($chat_id, $cmds)
    {
        $commands = [];

        foreach ($cmds as $k => $v) {
            $commands[] = [
                "command" => $k,
                "description" => $v
            ];
        }

        $this->apiRequest("setMyCommands", ["commands" => json_encode($commands, JSON_UNESCAPED_UNICODE), "scope" => json_encode(["type" => "chat", "chat_id" => $chat_id])]);
    }

    /**
     * Сообщает, что обновление на реакцию на inline кнопку выполнено, можно указать text, и оно будет выведено как Toast
     */
    public function answerCallbackQuery($query_id, $message = null)
    {
        $data = [
            "callback_query_id" => $query_id
        ];

        if (!empty($message)) {
            $data['show_alert'] = "true";
            $data['text'] = $message;
        }

        $this->apiRequest("answerCallbackQuery", $data);
    }

    /**
     * Устанавливает функцию, которая будет выполнена при получении обновления(сообщения)
     */
    public function setHandler($handler)
    {
        $this->handler = $handler;
    }

    /**
     * Обрабатывает обновление
     */
    private function processUpdate($update)
    {
        $from_id = 0;
        if (isset($update["callback_query"])) {
            $update = $update["callback_query"];
            $update["message"]["text"] = preg_replace("/[^a-zA-Z\-0-9:\*\,\.а-яА-ЯёЁйЙ_@()\\\s ]/ius", "", $update['data']);
            $from_id = $update["message"]['from']['id'];
            $update["message"]['from'] = $update['from'];
            $update['callback_query_id'] = $update['id'];
            $update["inline_message_id"] = $update['message']['message_id'] . " - " . $update['message']['chat']['id'];
            $this->last_callback_data = $update["inline_message_id"];
        }

        if (!isset($update["message"]['from']['is_bot']) || $update["message"]['from']['is_bot'] == true) {
            return;
        }
        if (!isset($update["message"]["chat"]['type']) || $update["message"]["chat"]['type'] != 'private') {
            return;
        }

        if (!isset($update["message"]["text"])) {
            return;
        }
        if ($this->handler != null) {
            $this->handler->__invoke($update, $this);
        }
        if (!empty($update['callback_query_id'])) {
            $this->answerCallbackQuery($update["callback_query_id"]);
        }
    }

    /**
     * Считывает обновления
     */
    public function poll()
    {
        $this->processUpdate(json_decode(file_get_contents("php://input"), true));
        die("ok");
    }

}
