<?php
global $telegram, $sessionId, $session, $mysqli;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\MessageType;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\CopyTextButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardRemove;

$telegram->onCommand("submit_wallet", function (Nutgram $bot) {
    $user = new UserController($bot->userId());
    $user->setSetting("step", "set_wallet");
    $bot->sendMessage("آدرس کیف پول خود مبتنی بر شبکه TON را ارسال کنید.");
});

$telegram->onMessageType(MessageType::TEXT, function (Nutgram $bot) {
    $user = new UserController($bot->userId());
    if ($user->getSetting("step") == "set_wallet") {
        $walletAddress = $bot->update()->message->text;
        if (\Olifanton\Interop\Address::isValid($walletAddress)) {
            $user->setSetting("wallet_address", $walletAddress);
            $user->setSetting("step", "none");
            $bot->sendMessage("آدرس ولت شما ثبت شد. دستور /start را ارسال کنید");
        } else {
            $bot->sendMessage("آدرس کیف پول نامعتبر است، مجدد تلاش کنید یا با دستور /start به صفحه اصلی بازگردید.");
        }
    }
});