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


use SergiX44\Nutgram\RunningMode\Webhook;

require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/vendor/autoload.php";

try {
    $telegram = new \SergiX44\Nutgram\Nutgram(BOT_TOKEN);
    $telegram->setRunningMode(Webhook::class);
    if (isset($_GET['set'])) {
        echo "Setting webhook";
         var_dump($telegram->setWebhook(WEBHOOK_URL, drop_pending_updates: true, secret_token: SECRET_TOKEN));
    } else if(isset($_GET['info'])) {
        echo json_encode($telegram->getWebhookInfo());
    }else {
        $headers = getallheaders();
        if ($headers['X-Telegram-Bot-Api-Secret-Token'] == SECRET_TOKEN) {
            require_once __DIR__."/controller/index.php";
        } else {
            echo "Secret token invalid";
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
} catch (\GuzzleHttp\Exception\GuzzleException $e) {
    error_log("GuzzleException: ".$e->getMessage());
}
?>