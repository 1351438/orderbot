<?php


use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class WalletController
{
    private $db;

    public function __construct()
    {
        global $mysqli;
        $this->db = $mysqli;
    }

    public function getWallet()
    {
        $res = $this->db->query("SELECT * FROM `wallets` LIMIT 1");
        if ($res->num_rows == 0) {
            return null;
        } else {
            $row = $res->fetch_assoc();
            return (new \Olifanton\Interop\Address($row['address']))->toString(true, true, true, $_ENV['IS_TESTNET']);
        }
    }

    public function fetchEvents()
    {
        global $mysqli;
        $res = $mysqli->query("SELECT * FROM wallets LIMIT 1");
        while ($row = $res->fetch_assoc()) {
            $wallet = $row['address'];
            $this->F_fetch_events($wallet);
        }

        return ["idk"];
    }

    private function F_fetch_events(string $wallet, int $limit = 100): void
    {
        global $mysqli;

        $ton_api = new TonApi($_ENV["TON_API_KEY"], $_ENV["TON_API_NETWORK"]);
        $maxLt = $mysqli->query("SELECT Max(blockchain_date) as m FROM blockchain_events WHERE detect_date > NOW() - INTERVAL 1 HOUR LIMIT 1")->fetch_assoc()['m'];

        $events = $ton_api->getEvents(
            $wallet,
            false,
            true,
            $limit,
            $maxLt,
            null
        );
        if (is_array($events) && count($events) > 0) {
            foreach ($events as $event) {
                $raw_data = $mysqli->real_escape_string($event['raw_data']);
                if ($event['type'] === 'JETTON_SWAP') {
                    $currency_in = $event['jetton_master_in'];
                    $currency_out = $event['jetton_master_out'];
                    $mysqli->query(
                        "INSERT IGNORE INTO blockchain_events_swap (id,action_index,dex,blockchain_date,account,sender,amount_in,currency_in_master,currency_in, amount_out, currency_out_master,currency_out, ton_out, router_address, base_transactions, lt, raw_data) 
                            VALUE ('$event[event_id]','$event[action_index]','$event[dex]','$event[timestamp]','$event[account]','$event[sender]',$event[amount_in],IF('$currency_in' = '',NULL,'$currency_in'),IF('$currency_in' = '',NULL,(SELECT symbol FROM jettons WHERE master_address = '$currency_in')),$event[amount_out],IF('$currency_out' = '',NULL,'$currency_out'),IF('$currency_out' = '',NULL,(SELECT symbol FROM jettons WHERE master_address = '$currency_out')),'$event[ton_out]','$event[router_address]','$event[base_transactions]','$event[lt]','$raw_data') "
                    );
                } else {
                    $currency = match ($event['type']) {
                        'JETTON_TRANSFER' => $event['jetton_master'],
                        'JETTON_BURN' => $event['jetton_master'],
                        'TON_TRANSFER' => 'TON',
                        default => null
                    };
                    $comment = $mysqli->real_escape_string($event['comment']);
                    $is_sender_wallet = ($event['is_sender_wallet']) ? "YES" : "NO";
                    $is_recipient_wallet = ($event['is_recipient_wallet']) ? "YES" : "NO";
                    $mysqli->query(
                        "INSERT IGNORE INTO blockchain_events (id,action_index,type,account,sender,recipient,amount,currency_master,currency,blockchain_date,item_address,base_transactions,comment,lt,raw_data,is_sender_wallet,is_recipient_wallet) 
                            VALUE ('$event[event_id]','$event[action_index]','$event[type]','$event[account]',IF('$event[sender]' = '',NULL,'$event[sender]'),IF('$event[recipient]' = '',NULL,'$event[recipient]'),'$event[amount]',IF('$currency' = '',NULL,'$currency'),IF('$currency' = '',NULL,(SELECT symbol FROM jettons WHERE master = '$currency')),'$event[timestamp]',IF('$event[item_address]' = '',NULL,'$event[item_address]'),'$event[base_transactions]',IF('$comment' = '',NULL,'$comment'),'$event[lt]','$raw_data','$is_sender_wallet->name','$is_recipient_wallet->name') "
                    );
                }
            }
        }
    }

    public function settleOrders()
    {
        global $mysqli, $telegram;
        $res = $mysqli->query("SELECT e.sender as senderAddress, e.id as eventId, e.amount as eventAmount, o.* 
                                            FROM orders o 
                                            LEFT JOIN blockchain_events e
                                                ON e.comment = o.transaction_code AND e.amount >= o.amount 
                                            WHERE 
                                                o.status = 'WAITING' AND e.status = 'PENDING' AND 
                                                e.recipient IN (SELECT address FROM wallets) 
                                                LIMIT 10");
        while ($row = $res->fetch_assoc()) {
            $mysqli->begin_transaction();
            try {
                $product = $mysqli->query("SELECT p.*, c.name as cityName, r.region as regionName FROM products p 
                                                LEFT JOIN region r ON p.region = r.id 
                                                LEFT JOIN city c ON c.tag = r.city_tag
                                                WHERE p.id = '$row[product_id]'");
                $product = $product->fetch_assoc();

                // do orders

                $stmt1 = $mysqli->prepare("UPDATE orders SET status = 'ACCEPTED' WHERE id = ?");
                $stmt1->bind_param('i', $row['id']);
                $stmt1->execute();

                $telegram->sendMessage(
                    "سفارش شما با موفقیت پرداخت شد، منتظر تایید، مدیران باشید.\nکدپیگیری: " . $row['id'],
                    chat_id: $row['user_id']
                );

                $stmt2 = $mysqli->prepare("UPDATE blockchain_events SET status = 'VERIFIED' WHERE id = ?");
                $stmt2->bind_param('s', $row['eventId']);
                $stmt2->execute();

                $user = new UserController($row['user_id']);
                $userAddress = $user->getSetting("address");
                $phoneNumber = $user->getSetting("phone_number");

                $text = sprintf("#سفارش_جدید
تاریخ: %s
-------------------
آیدی محصول: %s
محصول: <b>%s</b>
%s
-------------------
مقدار: %s
شهر: %s
منطقه: %s
آدرس: %s
شماره تماس: %s
مقدار پرداختی: TON <code>%s</code>
آدرس پرداختی: <code>%s</code>",
                    jdate("Y-m-d H:i"),
                    $row['product_id'],
                    $product['name'],
                    $product['description'],
                    $row['count'],
                    $product['cityName'],
                    $product['regionName'],
                    $userAddress,
                    $phoneNumber,
                    $row['eventAmount'],
                    (new \Olifanton\Interop\Address($row['senderAddress']))->toString(true, true, true),
                );
                $telegram->sendMessage(
                    $text,
                    chat_id: $product['manager'],
                    parse_mode: ParseMode::HTML,
                    reply_markup: InlineKeyboardMarkup::make()
                        ->addRow(
//                                'WAITING','CANCELED','EXPIRED','ACCEPTED','SENT','DONE'
                            InlineKeyboardButton::make("تغییر وضعیت به ارسال شده", callback_data: "change_order_status $row[id]-SENT"),
                        )
                        ->addRow(
                            InlineKeyboardButton::make("تغییر وضعیت سفارش به انجام شده", callback_data: "change_order_status $row[id]-DONE")
                        )
                );

                $mysqli->commit();
            } catch (mysqli_sql_exception $e) {
                error_log($e->getTraceAsString());
                error_log($e->getMessage());
                $mysqli->rollback();
            }
        }
    }
}