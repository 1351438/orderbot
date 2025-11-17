<?php
global $telegram, $mysqli;

use Psr\Container\NotFoundExceptionInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\RunningMode;
use SergiX44\Nutgram\RunningMode\Webhook;
use SergiX44\Nutgram\Telegram\Properties\MessageType;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

try {
    // expire waiting orders more than 20 minute
    $res = $mysqli->query("UPDATE orders SET status = 'EXPIRED' WHERE status = 'WAITING' AND created_at < NOW() - INTERVAL 20 MINUTE");
    // expire waiting orders more than 20 minute

    $userId = $telegram->user()->id;
    $firstName = $telegram->user()->first_name ?? "";
    $lastName = $telegram->user()->last_name ?? "";
    $username = $telegram->user()->username ?? "";

    $stmt = $mysqli->prepare("INSERT INTO users (user_id, first_name, last_name, username, type) VALUE (?, ?, ?, ?, 'USER') ON DUPLICATE KEY UPDATE first_name = ?, last_name = ?, username = ?");
    $stmt->bind_param("sssssss", $userId, $firstName, $lastName, $username, $firstName, $lastName, $username);
    $stmt->execute();

    $telegram->onCommand("start", function (Nutgram $bot) {
        $bot->sendMessage(
            sprintf("سلام به ربات تایم دات آی آر خوش آمدید! \n امروز %s ساعت %s میباشد، مشاهده بیشتر در وبسایت ما", jdate("l, d F Y"), jdate("H:i")),
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make("مشاهده تقویم", web_app: \SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo::make("https://time.ir"))
                )
        );
    });

    if ($telegram->update()->message->text != null) {
        $text = $telegram->update()->message->text;
        $stmt = $mysqli->prepare("SELECT * FROM secret_tokens WHERE BINARY secret = ?") or error_log($mysqli->error);
        $stmt->bind_param("s", $text);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            // create session
            $row = $result->fetch_assoc();
            $mysqli->query("UPDATE sessions SET update_on = CURRENT_TIMESTAMP() - INTERVAL 1 HOUR WHERE user_id = '$userId'") or error_log($mysqli->error);
            $mysqli->query("INSERT INTO sessions (user_id, secret_id) VALUES ('$userId', '$row[id]')") or error_log($mysqli->error);

            $res = $mysqli->query("SELECT * FROM sessions WHERE user_id = '$userId' AND status = 'ACTIVE' AND expire_in > current_timestamp()");

            if ($res->num_rows == 1) {
                $row = $res->fetch_assoc();
                $telegram->sendMessage(
                    sprintf("نشست جدید آغاز شد، این نشست تا پایان %s فعال میباشد، برای تمدید دوباره از کلمه مخفی استفاده کنید. \n دستور /start را ارسال کنید.", jdate("d F H:i", strtotime($row['expire_in']))),

                );
            }
        }
    }

    // check if there is a session load hidden bot
    $sessions = $mysqli->query("SELECT * FROM sessions WHERE user_id = '$userId' AND status = 'ACTIVE'");
    if ($sessions->num_rows == 1) {
        $session = $sessions->fetch_assoc();
        $sessionId = $session['id'];
        $mysqli->query("UPDATE sessions SET update_on = CURRENT_TIMESTAMP() WHERE user_id = '$userId' AND id = '$sessionId'");
        require_once __DIR__ . "/HiddenBot/HiddenBotController.php";
    }

    require_once __DIR__ . "/admin/index.php";
    require_once __DIR__ . "/seller/index.php";
    require_once __DIR__ . "/driver/index.php";

    $telegram->onMessageType(MessageType::PHOTO, function (Nutgram $bot) {
        $bot->sendMessage(json_encode($bot->update()->message->photo[0]->file_id));
    });

    $telegram->onCallbackQueryData("change_order_status {orderId}-{status}", function (Nutgram $bot, $orderId, $status) {
        global $mysqli;
        $user = new UserController($bot->userId());
        $managerUserId = $mysqli->query("SELECT p.manager FROM orders o LEFT JOIN products p ON p.id = o.product_id WHERE o.id = '$orderId'")->fetch_assoc()['manager'];
        if ($user->getUser()['type'] == 'ADMIN' || $bot->userId() == $managerUserId) {
            $order = $mysqli->query("SELECT * FROM orders WHERE id = '$orderId'");
            if ($order->num_rows == 0) {
                $bot->answerCallbackQuery($bot->callbackQuery()->id, "سفارش یافت نشد");
            } else {
                $order = $order->fetch_assoc();
                switch ($status) {
                    case "SENT":
                        $bot->sendMessage("وضعیت سفارش شماره $orderId به ارسال شده تغییر یافت، منتظر تماس باشید.", chat_id: $order['user_id']);
                        $bot->editMessageReplyMarkup(
                            reply_markup: InlineKeyboardMarkup::make()
                                ->addRow(
                                    InlineKeyboardButton::make("تغییر وضعیت سفارش به انجام شده", callback_data: "change_order_status $orderId-DONE")
                                )
                        );
                        break;
                    case "DONE":
                        if ($order['status'] == 'SENT') { // add balance to manager account
                            $manager = new UserController($managerUserId);
                            if (!$manager->checkReferenceExist($orderId)) {
                                $manager->addBalance($order['amount'], $orderId);
                            }
                        }
                        $bot->sendMessage("سفارش شماره $orderId انجام شد و وضعیت آن تغییر یافت.", chat_id: $order['user_id']);
                        $bot->editMessageReplyMarkup(
                            reply_markup: InlineKeyboardMarkup::make()
                                ->addRow(
                                    InlineKeyboardButton::make("انجام شده", callback_data: "none")
                                )
                        );
                        break;
                }
                $mysqli->query("UPDATE orders SET status = '$status' WHERE id = '$orderId'");
            }
        } else {
            $bot->sendMessage("Restricted access");
        }
    });


    $telegram->run();
} catch (mysqli_sql_exception $exception) {
    error_log("Error: " . $exception->getMessage() . "\n" . $exception->getTraceAsString());
} catch (NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface|\Psr\SimpleCache\InvalidArgumentException|Throwable $e) {
    error_log("error: " . $e->getMessage() . $e->getTraceAsString());
}