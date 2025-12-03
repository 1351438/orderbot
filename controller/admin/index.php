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

if ($type == "ADMIN") {
    $telegram->onText("/manage", function (Nutgram $bot){
        global $mysqli, $user;
        $balance = $user->getBalance();
        $stats = $mysqli->query("SELECT count(id) as all_orders, 
                                            sum(case when status = 'DONE' then 1 else 0 end) as done_orders,
                                            sum(case when status = 'WAITING' then 1 else 0 end) as waiting_for_payment_orders,
                                            (SELECT count(distinct user_id) FROM drivers) as total_drivers, 
                                            (SELECT count(distinct id) FROM products) as total_products,
                                            (SELECT sum(balance) FROM balances) as total_balances,
                                            (SELECT count(user_id) FROM balances WHERE balance > 0) as total_people_for_payout
                                        FROM orders");
        $stats= $stats->fetch_assoc();

        $bot->sendMessage(sprintf("به پنل مدیریت خوش آمدید
تعداد کل سفارشات ثبت شده: %s
تعداد کل سفارش انجام شده: %s
تعداد کل راننده ها: %s
تعداد کل محصولات: %s
------------------------------
مقدار کل قابل پرداخت جهت تسویه:
%s TON
تعداد نفرات جهت تسویه:
%s نفر", $stats['all_orders'],$stats['done_orders'], $stats['total_drivers'],$stats['total_products'],
        $stats['total_balances'],
        $stats['total_people_for_payout']));
    });

}