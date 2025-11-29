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

if ($type == "DRIVER") {
    function getNewDeliveries(Nutgram $bot) {
        global $mysqli;
        $res = $mysqli->query("SELECT * FROM orders WHERE status = 'SENT' AND driver is null ORDER BY update_on ASC LIMIT 2");
        while ($row = $res->fetch_assoc()) {
            $userOrder = new UserController($row['user_id']);
            $address = $userOrder->getSetting("address");
            $product = $mysqli->query("SELECT * FROM products WHERE id = '$row[product_id]' LIMIT 1");

            $bot->sendMessage(
                sprintf("#سفارش_جدید
مقصد:
<b>%s</b>
توضیحات:
<blockquote>%s</blockquote>
هزینه حمل و نقل: %s
", $address, $product['driver_note'],
                ($row['amount'] * DRIVER_FEE / 100) . " TON"
                ),
                parse_mode: ParseMode::HTML, reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make("قبول کردن سفارش", callback_data: "accept_driving_order $row[id]")
                )
            );
        }
    }
    $telegram->onCallbackQueryData("new_deliveries", "getNewDeliveries");
    $telegram->onCommand("new_deliveries", "getNewDeliveries");
}