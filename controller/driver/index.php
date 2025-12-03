<?php
global $telegram, $sessionId, $session, $mysqli;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\MessageType;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\CopyTextButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardRemove;

$user = new UserController($telegram->userId());
$type = $user->getUser()['type'];


/**
driver actions
 */
if ($type == "DRIVER") {

    $telegram->onText("/manage", function (Nutgram $bot){
        global $mysqli, $user;
        $balance = $user->getBalance();
        $orders = $mysqli->query("SELECT  count(o.id) as c FROM orders o WHERE o.driver = '{$bot->userId()}' AND o.status = 'DONE'")->fetch_assoc()['c'];
        $bot->sendMessage(sprintf("سلام به پنل مدیریت خوش آمدید.\n موجودی شما: %s \n تعداد سفرها: %s
--------------------
/new_deliveries - بررسی برای سفر جدید
/submit_wallet - ثبت آدرس ولت", $balance, $orders));
    });


    /**
    get new driver tasks to deliver
     */
    function getNewDeliveries(Nutgram $bot)
    {
        global $mysqli;
        $res = $mysqli->query("SELECT * FROM orders WHERE status = 'SENT' AND driver is null ORDER BY update_on ASC LIMIT 2");
        if ($res->num_rows == 0) {
            $bot->sendMessage("سفری موجود نمیباشد.");
        }
        while ($row = $res->fetch_assoc()) {
            $userOrder = new UserController($row['user_id']);
            $address = $userOrder->getSetting("address");
            $product = $mysqli->query("SELECT * FROM products WHERE id = '$row[product_id]' LIMIT 1");
            $product=$product->fetch_assoc();

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

    // accept the delivery by the driver
    $telegram->onCallbackQueryData("accept_driving_order {id}", function (Nutgram $bot, $id) {
        global $mysqli;

        $noOrderDelivery = $mysqli->query("SELECT id FROM orders WHERE driver is {$bot->userId()} AND status IN ('SENT', 'DRIVER')");
        $res = $mysqli->query("SELECT * FROM orders WHERE id = '$id' AND driver is null AND status = 'SENT' LIMIT 1");
        if ($res->num_rows > 0 && $noOrderDelivery->num_rows == 0) {
            $order = $res->fetch_assoc();

            $userOrder = new UserController($order['user_id']);
            $address = $userOrder->getSetting("address");
            $number = $userOrder->getSetting("phone_number");

            $driver = new UserController($bot->userId());
            $driverNumber = $driver->getDriver()['phone_number'];
            $driverName = $driver->getDriver()['name'];

            $product = $mysqli->query("SELECT * FROM products WHERE id = '$order[product_id]' LIMIT 1");
            $product = $product->fetch_assoc();

            $mysqli->query("UPDATE orders SET status = 'DRIVER', driver = '{$bot->userId()}' WHERE id = '$id'");
            $bot->editMessageReplyMarkup(reply_markup: InlineKeyboardMarkup::make());
            $bot->sendMessage(sprintf("سفارش قبول شد.

توضیحات مبدا:
<blockquote>%s</blockquote>
مقصد:
<b>%s</b>
شماره تماس:
%s

هزینه حمل و نقل: %s
", $product['driver_note'], $address, $number, ($order['amount'] * DRIVER_FEE / 100)),
                parse_mode: ParseMode::HTML, reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make("سفارش تحویل داده شد", callback_data: "delivered $order[id]")
                    )
            );


            $bot->sendMessage(sprintf("سفارش شما (%s) توسط راننده قبول شد، درحال ارسال است.
شماره تماس راننده: %s", $order['id'], $driverNumber), chat_id: $order['user_id']);

            $bot->sendMessage(sprintf("راننده برای سفارش %s انتخاب شد
شماره تماس راننده: %s
نام راننده: %s", $id, $driverNumber, $driverNumber), chat_id: $product['manager']);
        } else {
            $bot->sendMessage("سفارش توسط راننده ای دیگر قبول شده است");
        }
    });


    // delivered by the driver and change status for admin to confirm and payout the share
    $telegram->onCallbackQueryData("delivered {id}", function (Nutgram $bot, $id) {
        global $mysqli;

        $uid = $bot->userId();
        $res = $mysqli->query("SELECT * FROM orders WHERE id = '$id' AND driver = '$uid' LIMIT 1");
        if ($res->num_rows > 0) {

            $order = $res->fetch_assoc();

            $product = $mysqli->query("SELECT * FROM products WHERE id = '$order[product_id]' LIMIT 1");
            $product = $product->fetch_assoc();

            $mysqli->query("UPDATE orders SET status = 'DELIVERED' WHERE id = '$id'");
            $bot->editMessageReplyMarkup(reply_markup: InlineKeyboardMarkup::make());
            $bot->sendMessage(("سقارش به اتمام رسید به مدیریت اعلام شد."),
                parse_mode: ParseMode::HTML,
            );

            $bot->sendMessage(sprintf("سفارش شماره %s به مشتری تحویل داده شد، تایید و بستن سفارش.", $order['id']), chat_id: $product['manager'],
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make("تغییر وضعیت سفارش به انجام شده", callback_data: "change_order_status $id-DONE")
                    ));
        } else {
            $bot->sendMessage("سفارش توسط راننده ای دیگر قبول شده است");
        }
    });
}