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

if ($type === "ADMIN") {

}