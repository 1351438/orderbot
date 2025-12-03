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
    $telegram->onText("/manage", function (Nutgram $bot) {
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
        $stats = $stats->fetch_assoc();

        $bot->sendMessage(sprintf("به پنل مدیریت خوش آمدید
تعداد کل سفارشات ثبت شده: %s
تعداد کل سفارش انجام شده: %s
تعداد کل راننده ها: %s
تعداد کل محصولات: %s
------------------------------
مقدار کل قابل پرداخت جهت تسویه:
%s TON
تعداد نفرات جهت تسویه:
%s نفر

------------------------------
/balances - مشاهده برداشت ها و تسویه", $stats['all_orders'], $stats['done_orders'], $stats['total_drivers'], $stats['total_products'],
            $stats['total_balances'],
            $stats['total_people_for_payout']));
    });

    $telegram->onCommand("balances", function (Nutgram $bot) {
        global $mysqli, $user;
        $res = $mysqli->query("SELECT balances.user_id, balances.balance as balance, settings.value as wallet_address FROM balances JOIN settings on settings.user_id = balances.user_id AND settings.name = 'wallet_address' AND balances.balance > 0 ORDER BY balance LIMIT 1");
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $bot->sendMessage("موجودی یکی از کاربران، پس از تسویه میتوانید به شخص بعدی با ارسال مجدد /balances برای تسویه بروید.
" . sprintf("
آیدی کاربر: %s
مبلغ: %s TON
آدرس کیف پول: <code>%s</code>
", $row['user_id'], $row['balance'], $row['wallet_address']),
                parse_mode: ParseMode::HTML,
                reply_markup: InlineKeyboardMarkup::make()->addRow(
                    InlineKeyboardButton::make("کپی آدرس کیف پول", copy_text: CopyTextButton::make($row['wallet_address'])),
                )->addRow(
                    InlineKeyboardButton::make("کپی مبلغ", copy_text: CopyTextButton::make($row['balance'])),
                )->addRow(
                    InlineKeyboardButton::make("صفر کردن موجودی",callback_data: "payout $row[user_id]")
                )
            );
        } else {
            $bot->sendMessage("رکوردی برای تسویه حساب موجود نمیباشد");
        }
    });

    $telegram->onCallbackQueryData("payout {userId}", function (Nutgram $bot, $userId) {
        global $mysqli;
        $target = new UserController($userId);
        $balance = $target->getBalance();
        $target->reduceBalance($balance, 'withdraw');
        $bot->editMessageReplyMarkup($bot->userId(), message_id: $bot->messageId());
        $bot->sendMessage(sprintf("مقدار %s TON از کاربر %s کم شد و موجودی آن 0 شد.", $balance, $userId));
    });
}