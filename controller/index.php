<?php
global $telegram, $mysqli;

use Psr\Container\NotFoundExceptionInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\RunningMode;
use SergiX44\Nutgram\RunningMode\Webhook;
use SergiX44\Nutgram\Telegram\Properties\MessageType;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
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

    /**
     * this is the front code that shows time and open time.ir as mini app
     **/
    $telegram->onCommand("start", function (Nutgram $bot) {
        $bot->sendMessage(
            sprintf("سلام به ربات تایم دات آی آر خوش آمدید! \n امروز %s ساعت %s میباشد، مشاهده بیشتر در وبسایت ما", jdate("l, d F Y"), jdate("H:i")),
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make("مشاهده تقویم", web_app: \SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo::make("https://time.ir"))
                )
        );
    });

    /**
     * This part is for checking the secret token and create a 1 hour session
     */
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

    $user = new UserController($telegram->userId());
    $type = $user->getUser()['type'];
    if ($type != 'USER') {
        require_once __DIR__ . "/admin/index.php";
        require_once __DIR__ . "/seller/index.php";
        require_once __DIR__ . "/driver/index.php";
        require_once __DIR__ . "/common/index.php";
    }
    /**
     * If you send a picture to bot you can get the file id of the image for adding it into the product detail
     */
    $telegram->onMessageType(MessageType::PHOTO, function (Nutgram $bot) {
        $bot->sendMessage(json_encode($bot->update()->message->photo[0]->file_id));
    });

    /*
     * The manager can change the status after the product delivered and verified and then split the money between driver or themselves
     * they can even send the product and get the full price if they want and close the order
     * */
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
                $newStatus = $order['status'];
                switch ($status) {
                    case "SENT":
                        /**
                         * change the status to sent and notify 10 random drivers there is a new order
                         *  and they by accepting it the have to deliver the product / no cancellation
                         */
                        $bot->sendMessage("وضعیت سفارش شماره $orderId به ارسال شده تغییر یافت، منتظر تماس باشید.", chat_id: $order['user_id']);
                        $newStatus = 'SENT';
                        $res = $mysqli->query("SELECT user_id FROM drivers ORDER BY RAND() LIMIT 10");
                        while ($driver = $res->fetch_assoc()) {
                            $bot->sendMessage(
                                "یک سفارش جدید ثبت شده است",
                                chat_id: $driver['user_id'],
                                parse_mode: ParseMode::HTML,
                                reply_markup: InlineKeyboardMarkup::make()
                                    ->addRow(
                                        InlineKeyboardButton::make("مشاهده بسته های جدید", callback_data: "new_deliveries"),
                                    )
                            );
                        }
                        $bot->editMessageReplyMarkup(
                            reply_markup: InlineKeyboardMarkup::make()
                                ->addRow(
                                    InlineKeyboardButton::make("تغییر وضعیت سفارش به انجام شده", callback_data: "change_order_status $orderId-DONE")
                                )
                        );
                        break;
                    case "DONE":
                        /**
                         * calculate and pay out the amount that recieved between manager and driver
                         */
                        if ($order['status'] == 'DELIVERED' || $order['status'] == 'SENT' || $order['status'] == 'ACCEPTED') { // add balance to manager account
                            $newStatus = 'DONE';
                            $manager = new UserController($managerUserId);
                            if (!$manager->checkReferenceExist($orderId)) {
                                /// pay and split the shares
                                $amount = $order['amount'];

                                $systemFee = $amount * FEE_PERCENTAGE / 100;
                                $driverFee = $amount * DRIVER_FEE / 100;
                                $amount -= ($systemFee);
                                if ($order['driver'] != null) {
                                    $amount -= $driverFee;
                                    $driver = new UserController($order['driver']);
                                    $driver->addBalance($driverFee, $orderId);
                                }
                                $manager->addBalance($amount, $orderId);
                            }
                            $bot->sendMessage("سفارش شماره $orderId انجام شد و وضعیت آن تغییر یافت.", chat_id: $order['user_id']);
                            $bot->editMessageReplyMarkup(
                                reply_markup: InlineKeyboardMarkup::make()
                                    ->addRow(
                                        InlineKeyboardButton::make("انجام شده", callback_data: "none")
                                    )
                            );
                        } else {
                            $bot->answerCallbackQuery($bot->callbackQuery()->id, "وضعیت سفارش قابل تغییر نیست");
                        }
                        break;
                }
                // update order status
                $mysqli->query("UPDATE orders SET status = '$newStatus' WHERE id = '$orderId'");
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