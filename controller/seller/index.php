<?php
global $telegram, $sessionId, $session, $mysqli;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\MessageType;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\CopyTextButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

$user = new UserController($telegram->userId());
$type = $user->getUser()['type'];

if ($type == "SELLER") {
    $telegram->onText("/manage", function (Nutgram $bot){
        global $mysqli, $user;
        $balance = $user->getBalance();
        $orders = $mysqli->query("SELECT  count(o.id) as c FROM orders o WHERE o.product_id IN (SELECT id FROM products WHERE manager = '{$bot->userId()}') AND o.status NOT IN ('EXPIRED', 'WAITING')")->fetch_assoc()['c'];
        $bot->sendMessage(sprintf("سلام به پنل مدیریت خوش آمدید.\n موجودی شما: %s \n تعداد سفارشات: %s", $balance, $orders));
    });
}