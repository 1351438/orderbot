<?php
global $telegram, $sessionId, $session, $mysqli;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\MessageType;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

function startCommand(Nutgram $bot)
{
    global $session;
    $user = new UserController($bot->userId());
    $user->setSetting("step", 'none');

    $phone = $user->getSetting("phone_number") ?? "ثبت نشده";
    $address = $user->getSetting("address") ?? "ثبت نشده";
    $expireIn = jdate("d F Y H:i", strtotime($session['expire_in']));


    $bot->sendMessage(sprintf("خوش اومدید، قبل از ثبت سفارش حتما آدرس و شماره تماس خود را ثبت کنید.\nآدرس فعلی: %s \n شماره تماس: %s\nانقضای نشست فعلی: %s", $address, $phone, $expireIn),
        protect_content: true,
        reply_markup: InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make("مشاهد مناطق", callback_data: "regions"),
            )->addRow(
                InlineKeyboardButton::make("تنظیم شماره تماس", callback_data: "set_number"),
                InlineKeyboardButton::make("تنظیم آدرس", callback_data: "set_address"),
            ));
}

$telegram->onCommand('start', 'startCommand');

// select city
$telegram->onCallbackQueryData('regions', function (Nutgram $bot) {
    global $mysqli;
    $cities = $mysqli->query("SELECT * FROM city");
    $buttons = InlineKeyboardMarkup::make();
    while ($city = $cities->fetch_assoc()) {
        $buttons->addRow(InlineKeyboardButton::make($city['name'], callback_data: "city " . $city['tag']));
    }
    $bot->editMessageText("لطفا از لیست شهر های زیر یک شهر را انتخاب کنید.", reply_markup: $buttons);
});

// select region in the city
$telegram->onCallbackQueryData('city {tag}', function (Nutgram $bot, $tag) {
    global $mysqli;

    $regions = $mysqli->query("SELECT * FROM region WHERE city_tag = '$tag'");
    $buttons = InlineKeyboardMarkup::make();
    if ($regions->num_rows > 0) {
        while ($region = $regions->fetch_assoc()) {
            $buttons->addRow(InlineKeyboardButton::make($region['region'], callback_data: $tag . " region " . $region['id']));
        }
        $buttons->addRow(InlineKeyboardButton::make("بازگشت به شهر ها", callback_data: "regions"));
        $bot->editMessageText("لطفا منطقه شهر خود را انتخاب کنید.", reply_markup: $buttons);
    } else {
        $buttons->addRow(InlineKeyboardButton::make("بازگشت به شهر ها", callback_data: "regions"));
        $bot->editMessageText("هیچ منطقه ای برای این شهر یافت نشد.", reply_markup: $buttons);
    }
});

// showing product after selecting regions
$telegram->onCallbackQueryData('{tag} region {id}', function (Nutgram $bot, $tag, $regionId) {
    global $mysqli;

    $products = $mysqli->query("SELECT * FROM products WHERE region = '$regionId'");
    $buttons = InlineKeyboardMarkup::make();
    if ($products->num_rows > 0) {
        while ($product = $products->fetch_assoc()) {
            $buttons->addRow(InlineKeyboardButton::make($product['name'], callback_data: "product $product[id]-$tag-$regionId"));
        }
        $buttons->addRow(InlineKeyboardButton::make("بازگشت به منطقه ها", callback_data: "city " . $tag));
        $bot->editMessageText("لطفا یکی از محصولات زیر را انتخاب کنید.", reply_markup: $buttons);
    } else {
        $buttons->addRow(InlineKeyboardButton::make("بازگشت به منطقه ها", callback_data: "city " . $tag));
        $bot->editMessageText("هیچ محصولی در این منطقه یافت نشد.", reply_markup: $buttons);
    }
});


// single product show and show the amount varients they can buy
$telegram->onCallbackQueryData('product {id}-{tag}-{regionId}', function (Nutgram $bot, $id, $tag, $regionId) {
    global $mysqli;

    $products = $mysqli->query("SELECT * FROM products WHERE id = '$id'");
    if ($products->num_rows > 0) {
        $product = $products->fetch_assoc();
        $buttons = InlineKeyboardMarkup::make();
        $variants = $product['variants'];
        $variants = explode(',', $variants);
        $variants = array_chunk($variants, 3);
        foreach ($variants as $variant) {
            $vn = [];
            foreach ($variant as $single) {
                $vn[] = InlineKeyboardButton::make($single, callback_data: "buy $product[id]-$single");
            }
            $buttons->addRow(...$vn);
        }

        $text = sprintf("عنوان محصول: %s
توضیحات: %s

قیمت واحد: %s TON", $product['title'], $product['description'], $product['unit_price']);
        if (isset($product['file_id'])) {
            $bot->editMessageMedia(new \SergiX44\Nutgram\Telegram\Types\Input\InputMediaPhoto($product['file_id'], $text, has_spoiler: true), reply_markup: $buttons);
        } else {
            $bot->editMessageText($text, reply_markup: $buttons);
        }
    } else {
        $bot->editMessageText("هیچ محصولی در این منطقه یافت نشد.");
    }
});

$telegram->onCallbackQueryData('buy {productId}-{count}', function (Nutgram $bot, $productId, $count) {
    global $mysqli;
    $products = $mysqli->query("SELECT p.*, c.name as city_name, r.region as region_name FROM products p LEFT JOIN region r ON r.id = p.region LEFT JOIN city c ON c.tag = r.city_tag WHERE p.id = '$productId'");
    if ($products->num_rows == 1) {
        $row = $products->fetch_assoc();

        $stmt = $mysqli->prepare("INSERT INTO orders (user_id, product_id, count, amount, status) VALUE (?, ?, ?, ?, 'WAITING')");
        $amount = $count * $row['unit_price'];
        $userId = $bot->userId();
        $stmt->bind_param("iiis", $userId, $productId, $count, $amount);
        $stmt->execute();
        $orderId = $mysqli->insert_id;
        $res = $mysqli->query("SELECT * FROM orders WHERE id = '$orderId'");
        if ($res->num_rows > 0) {
            $order = $res->fetch_assoc();

            $user = new UserController($bot->userId());
            $address = $user->getSetting("address") ?? "ثبت نشده";
            $phone = $user->getSetting("phone_number") ?? "ثبت نشده";
            $walletController =new WalletController();
            $bot->sendMessage(
                sprintf("محصول: 
<b>%s</b>
%s
---------------
شهر: %s
منطقه: %s
آدرس: %s
شماره تماس: %s
---------------
مقدار: %s
مبلغ نهایی: <b><code>%s</code> TON</b>
آدرس کیف پول: <code>%s</code>
کامنت: <code>%s</code>

<blockquote>
⚠️ تراکنش را در شبکه TON به آدرس ولت با کامنت معین شده واریز کنید، در غیر این صورت امکان تایید نشدن سفارش وجود دارد.
</blockquote>
<blockquote>
⚠️ این تراکنش تا 10 دقیقه معتبر است، پس از زمان مقرر واریز کردن احتمال لغو سفارش و قبول نشدن آنرا دارد.
</blockquote>",
                    $row['name'], $row['description'],
                    $row['city_name'], $row['region_name'], $address, $phone,
                    $count, $amount, $walletController->getWallet(), $order['transaction_code']),
                parse_mode: ParseMode::HTML,
                protect_content: true,
                reply_to_message_id: $bot->messageId());
        } else {
            $bot->answerCallbackQuery($bot->callbackQuery()->id, "اوردر یافت نشد");
        }
    } else {
        $bot->answerCallbackQuery($bot->callbackQuery()->id, "محصول یافت نشد");
    }
});

/// set phone number and address for orders
$telegram->onCallbackQueryData("set_number", function (Nutgram $bot) {
    $user = new UserController($bot->userId());
    $user->setSetting("step", "set_number");
    $bot->sendMessage("شماره تماس خود را ارسال کنید.");
});

$telegram->onCallbackQueryData("set_address", function (Nutgram $bot) {
    $user = new UserController($bot->userId());
    $user->setSetting("step", "set_address");
    $bot->sendMessage("آدرس خود را ارسال کنید.");
});

$telegram->onMessageType(MessageType::TEXT, function (Nutgram $bot) {
    $user = new UserController($bot->userId());
    if ($user->getSetting("step") == "set_number") {
        $phone_number = $bot->update()->message->text;
        if (preg_match('/(\+?98|098|0|0098)?(9\d{9})/', $phone_number)) {
            $user->setSetting("phone_number", $phone_number);
            $user->setSetting("step", "none");
            $bot->sendMessage("شماره تماس با موفقیت تغییر یافت. دستور /start را ارسال کنید");
        } else {
            $bot->sendMessage("شماره تماس نامعتبر است دوباره ارسال کنید، برای لغو دستور /start را ارسال کنید.");
        }
    } else if ($user->getSetting("step") == "set_address") {
        $text = $bot->update()->message->text;
        $user->setSetting("address", $text);
        $user->setSetting("step", "none");
        $bot->sendMessage("آدرس با موفقیت تغییر یافت. دستور /start را ارسال کنید");
    }
});
