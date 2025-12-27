<?php
// ======= إعداد التوكن للتحقق =======
$VERIFY_TOKEN = "079552"; // ضع نفس التوكن في Webhook على فيسبوك

// ======= التحقق عند GET =======
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($mode === 'subscribe' && $token === $VERIFY_TOKEN) {
        echo $challenge; // فيسبوك يقبل Verify
        exit;
    } else {
        echo "Token mismatch";
        exit;
    }
}

// ======= استقبال الرسائل عند POST =======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // عرض الحدث في الطرفية لتتبع الرسائل
    file_put_contents("log.txt", print_r($input, true), FILE_APPEND);

    // مثال: استقبال معرف المرسل والنص
    if (isset($input['entry'][0]['messaging'][0]['sender']['id'])) {
        $sender = $input['entry'][0]['messaging'][0]['sender']['id'];
        $message = $input['entry'][0]['messaging'][0]['message']['text'] ?? "";

        // رسالة تلقائية للتجربة
        $response = [
            "recipient" => ["id" => $sender],
            "message" => ["text" => "تم استلام رسالتك: ".$message]
        ];

        $PAGE_TOKEN = ""; // ضع Page Access Token هنا لاحقًا إذا أردت الرد تلقائي
        if ($PAGE_TOKEN !== "") {
            $url = "https://graph.facebook.com/v18.0/me/messages?access_token=$PAGE_TOKEN";
            $options = [
                "http" => [
                    "method"  => "POST",
                    "header"  => "Content-Type: application/json",
                    "content" => json_encode($response)
                ]
            ];
            @file_get_contents($url, false, stream_context_create($options));
        }
    }

    // واجب لفيسبوك
    echo "EVENT_RECEIVED";
    exit;
}
?>
