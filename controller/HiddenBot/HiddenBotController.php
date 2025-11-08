<?php
global $telegram, $sessionId, $mysqli;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

function startCommand(Nutgram $bot)
{
    $bot->sendMessage("hello",
        protect_content: true,
        reply_markup: InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make("مشاهد مناطق", callback_data: "regions")
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
