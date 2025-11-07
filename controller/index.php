<?php
global $telegram;

use Psr\Container\NotFoundExceptionInterface;
use SergiX44\Nutgram\Nutgram;

try {
    $telegram->onCommand("start", function (Nutgram $telegram) {
        $telegram->sendMessage("Hi", 2123795043);
    });

    $telegram->onText("Hello {name}", function (Nutgram $telegram, $name) {
        $telegram->sendMessage($name);
    });

    $telegram->run();
} catch (NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface $e) {
    error_log("error: " . $e->getTraceAsString());
}