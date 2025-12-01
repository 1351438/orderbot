<?php
// Enable CORS for all origins (adjust as needed for production)
header("Access-Control-Allow-Origin: *");
header("access-control-allow-credentials: true");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Telegram-Bot-Api-Secret-Token");
header("Content-Type: application/json; charset=UTF-8");

error_reporting(E_ALL);
ini_set('ignore_repeated_errors', TRUE);
ini_set('display_errors', FALSE);
ini_set('log_errors', TRUE);
ini_set('error_log', __DIR__ . '/errors.log'); // Logging file path


use Psr\Container\ContainerInterface;
use SergiX44\Nutgram\Configuration;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Webhook;

require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/lib/autoload.php";
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/controller/UserController.php";
require_once __DIR__ . "/controller/WalletController.php";
global $mysqli;

$_ENV = parse_ini_file(__DIR__ . "/.env"); // parse .env elements.

try {
    $configuration = new Configuration(
        logger: CustomLogger::class,
    );
    $telegram = new Nutgram(BOT_TOKEN, $configuration);
    $telegram->setRunningMode(Webhook::class);
    $telegram->getContainer()->get(Webhook::class)->processUpdates($telegram);

    if (isset($_GET['set'])) {
        echo "Setting webhook";
        var_dump($telegram->setWebhook(WEBHOOK_URL, drop_pending_updates: true, secret_token: SECRET_TOKEN));
    } else if (isset($_GET['info'])) {
        echo json_encode($telegram->getWebhookInfo());
    } else if (isset($_GET['cron'])) {
        // set cron job for this path https://hosturl?cron=events
        switch (strtolower($_GET['cron'])) {
            case "events": // every minute
                $wallet = new WalletController();
                echo json_encode($wallet->fetchEvents());
                break;
            case "settle_orders": // every minute
                $wallet = new WalletController();
                $wallet->settleOrders();
                break;
            case "delete_messages": // every 5 minute
                $res = $mysqli->query("SELECT * FROM messages WHERE status = 'ACTIVE' AND created_at < SUBDATE(CURRENT_TIMESTAMP(), INTERVAL 1 HOUR) AND user_id NOT IN (select user_id from users where type = 'ADMIN') GROUP BY user_id, message_id ORDER BY created_at ASC LIMIT 100");
                $deletableMessages = [];
                while ($row = $res->fetch_assoc()) {
                    $deletableMessages[$row['user_id']][] = $row['message_id'];
                }
                $counter = 0;
                foreach ($deletableMessages as $uid => $messages) {
                    if ($counter < 10) {
                        if (count($messages) > 0) {
                            try {
                                $telegram->deleteMessages($uid, $messages);
                            } catch (Exception $e) {}
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
                break;
        }
    } else {
        $headers = getallheaders();
        if (isset($headers['X-Telegram-Bot-Api-Secret-Token']) && $headers['X-Telegram-Bot-Api-Secret-Token'] == SECRET_TOKEN) {
            require_once __DIR__ . "/controller/index.php";
        } else {
            echo "Secret token invalid";
        }
    }
} catch (Exception $e) {
    error_log($e->getTraceAsString());
} catch (\GuzzleHttp\Exception\GuzzleException $e) {
    error_log("GuzzleException: " . $e->getMessage());
} catch (\Psr\SimpleCache\InvalidArgumentException $e) {
    error_log("InvalidArgumentException: " . $e->getMessage());
} catch (Throwable $e) {
    error_log("Exception: " . $e->getTraceAsString() . $e->getMessage());
}
$mysqli->close();
?>