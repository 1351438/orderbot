<?php
global $telegram, $mysqli;

use Psr\Container\NotFoundExceptionInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\RunningMode;
use SergiX44\Nutgram\RunningMode\Webhook;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

try {
    // delete messages before last hours
    $res = $mysqli->query("SELECT * FROM messages WHERE status = 'ACTIVE' AND created_at < SUBDATE(CURRENT_TIMESTAMP(), INTERVAL 1 HOUR) GROUP BY user_id, message_id ORDER BY created_at ASC LIMIT 100");
    $deletableMessages = [];
    while ($row = $res->fetch_assoc()) {
        $deletableMessages[$row['user_id']][] = $row['message_id'];
    }
    $counter = 0;
    foreach ($deletableMessages as $uid => $messages) {
        if ($counter < 10) {
            if (count($messages) > 0) {
                $telegram->deleteMessages($uid, $messages);
                $placeholders = implode(',', array_fill(0, count($messages), '?'));
                $stmt = $mysqli->prepare("UPDATE messages SET status = 'REMOVED' WHERE user_id = ? AND message_id IN ($placeholders)");
                $params = array_merge([$uid], $messages); // $uid is the first parameter
                $types = "i" . str_repeat('i', count($messages));
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $counter++;
            }
        } else {
            break;
        }
    }
    // delete messages before last hours


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
                sprintf("نشست جدید آغاز شد، این نشست تا پایان %s فعال میباشد، برای تمدید دوباره از کلمه مخفی استفاده کنید.", jdate("d F H:i", strtotime($row['expire_in']))),
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make("مشاهد مناطق", callback_data: "regions")
                    )
            );
        }
    }


    // check if there is a session load hidden bot
    $sessions = $mysqli->query("SELECT * FROM sessions WHERE user_id = '$userId' AND status = 'ACTIVE'");
    if ($sessions->num_rows == 1) {
        $row = $sessions->fetch_assoc();
        $sessionId = $row['id'];
        $mysqli->query("UPDATE sessions SET update_on = CURRENT_TIMESTAMP() WHERE user_id = '$userId' AND id = '$sessionId'");
        require_once __DIR__ . "/HiddenBot/HiddenBotController.php";
    }

    $telegram->run();
} catch (mysqli_sql_exception $exception) {
    error_log("Error: " . $mysqli->error);
} catch (NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface|\Psr\SimpleCache\InvalidArgumentException|Throwable $e) {
    error_log("error: " . $e->getMessage() . $e->getTraceAsString());
}