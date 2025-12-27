<?php
// ======= إعداد التوكنات =======
$MY_VERIFY_TOKEN = "FAZA.4"; // توكن التحقق الجديد
$MY_PAGE_TOKEN   = "ضع_هنا_page_token"; // ضع توكن الصفحة من فيسبوك

// ======= قاعدة البيانات =======
$host = 'localhost';
$dbname = 'facebook_bot_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // إنشاء الجدول إذا لم يكن موجوداً
    createDatabaseAndTable($host, $username, $password);
}

// ======= التحقق من Webhook =======
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (
        isset($_GET['hub.mode']) && $_GET['hub.mode'] === 'subscribe' &&
        isset($_GET['hub.verify_token']) && $_GET['hub.verify_token'] === $MY_VERIFY_TOKEN
    ) {
        echo $_GET['hub.challenge'];
        exit;
    } else {
        http_response_code(403);
        echo "Verification failed. Token must be: FAZA.4";
        exit;
    }
}

// ======= استقبال الرسائل =======
$input = file_get_contents("php://input");
$data  = json_decode($input, true);

if (isset($data['entry'][0]['messaging'][0]['sender']['id'])) {
    $sender_id    = $data['entry'][0]['messaging'][0]['sender']['id'];
    $message_text = strtolower(trim($data['entry'][0]['messaging'][0]['message']['text'] ?? ''));
    
    // التحقق من حالة المستخدم
    $userState = getUserState($sender_id);
    
    // ======= نظام التحقق FAZA.4 =======
    if ($userState === 'pending_verification') {
        // التحقق من رمز FAZA.4
        if (verifyFAZA4Code($sender_id, $message_text)) {
            $reply = "✅ تم التحقق بنجاح! مرحباً بك في النظام.\n\nيمكنك الآن استخدام الأوامر:\n1. /info - معلومات الحساب\n2. /code - إنشاء رمز جديد\n3. /help - المساعدة";
            updateUserState($sender_id, 'verified');
        } else {
            $reply = "❌ رمز التحقق غير صحيح. حاول مرة أخرى أو اطلب رمز جديد باستخدام /newcode";
        }
    } 
    // إذا كان المستخدم جديداً
    elseif ($userState === 'new_user') {
        $verificationCode = generateFAZA4Code($sender_id);
        $reply = "🔐 مرحباً بك في نظام FAZA.4 للتحقق!\n\nلقد أرسلنا رمز تحقق إلى حسابك.\nرمز التحقق الخاص بك هو: *$verificationCode*\n\nيرجى إدخال هذا الرمز للمتابعة.";
        updateUserState($sender_id, 'pending_verification');
    }
    // إذا كان المستخدم مفعل بالفعل
    elseif ($userState === 'verified') {
        // ======= الأوامر بعد التحقق =======
        if ($message_text === '/info' || preg_match('/معلومات/', $message_text)) {
            $userInfo = getUserInfo($sender_id);
            $reply = "📋 معلومات حسابك:\n\n🔹 الرقم: " . ($userInfo['phone'] ?? 'غير مضبوط') . 
                    "\n🔹 حالة التحقق: ✓ مفعل" .
                    "\n🔹 تاريخ التسجيل: " . ($userInfo['created_at'] ?? 'غير معروف');
        }
        elseif ($message_text === '/code' || preg_match('/رمز جديد/', $message_text)) {
            $newCode = generateFAZA4Code($sender_id);
            $reply = "🔐 رمز FAZA.4 الجديد: *$newCode*\n\nاستخدم هذا الرمز عند الحاجة.";
        }
        elseif ($message_text === '/help' || preg_match('/مساعدة/', $message_text)) {
            $reply = "🤖 أوامر البوت:\n\n" .
                    "🔹 /info - عرض معلومات الحساب\n" .
                    "🔹 /code - إنشاء رمز FAZA.4 جديد\n" .
                    "🔹 /help - عرض هذه المساعدة\n" .
                    "🔹 /contact - الاتصال بالدعم\n" .
                    "🔹 /reset - إعادة تعيين كلمة المرور";
        }
        elseif ($message_text === '/contact' || preg_match('/اتصال|دعم/', $message_text)) {
            $reply = "📞 للاتصال بالدعم:\n\n" .
                    "البريد الإلكتروني: support@faza4.com\n" .
                    "الهاتف: +1234567890\n" .
                    "ساعات العمل: 9 صباحاً - 5 مساءً";
        }
        elseif ($message_text === '/reset') {
            $resetCode = generateFAZA4Code($sender_id);
            $reply = "🔄 رمز إعادة التعيين: *$resetCode*\n\nاستخدم هذا الرمز في صفحة إعادة تعيين كلمة المرور.";
        }
        elseif (preg_match('/مرحبا|سلام|hi|hello/', $message_text)) {
            $reply = "مرحبا بيك 👋 كيف يمكنني مساعدتك اليوم؟\n\nاستخدم /help لرؤية الأوامر المتاحة.";
        } 
        elseif (preg_match('/شكون مطورك|who made you/', $message_text)) {
            $reply = "مطوّري هو ياسين 💙😎\nمع نظام تحقق FAZA.4 المتطور!";
        }
        elseif (preg_match('/شكرا|merci|thanks/', $message_text)) {
            $reply = "العفو 🤍 أي وقت! نظام FAZA.4 دائماً لحمايتك.";
        }
        elseif (preg_match('/اسمك|name/', $message_text)) {
            $reply = "أنا بوت FAZA.4 🤖\nنظام تحقق متطور من طرف ياسين";
        }
        else {
            $reply = "🤔 ما فهمتش سؤالك، تقدر تعاود بصيغة أخرى أو استخدم /help للمساعدة.";
        }
    } else {
        // حالة افتراضية للمستخدمين الجدد
        $verificationCode = generateFAZA4Code($sender_id);
        $reply = "🔐 مرحباً بك في نظام FAZA.4 للتحقق!\n\nلقد أرسلنا رمز تحقق إلى حسابك.\nرمز التحقق الخاص بك هو: *$verificationCode*\n\nيرجى إدخال هذا الرمز للمتابعة.";
    }
    
    sendMessage($sender_id, $reply, $MY_PAGE_TOKEN);
}

// ======= دوال النظام =======

// إنشاء قاعدة البيانات والجداول
function createDatabaseAndTable($host, $username, $password) {
    try {
        $pdo = new PDO("mysql:host=$host", $username, $password);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS facebook_bot_db CHARACTER SET utf8 COLLATE utf8_general_ci");
        $pdo->exec("USE facebook_bot_db");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            facebook_id VARCHAR(50) UNIQUE NOT NULL,
            phone VARCHAR(20),
            state ENUM('new_user', 'pending_verification', 'verified') DEFAULT 'new_user',
            faza4_code VARCHAR(20),
            code_expiry DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS verification_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            facebook_id VARCHAR(50) NOT NULL,
            code VARCHAR(20) NOT NULL,
            status ENUM('success', 'failed') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch(PDOException $e) {
        // تجاهل الخطأ في بيئة الإنتاج
    }
}

// الحصول على حالة المستخدم
function getUserState($facebookId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT state FROM users WHERE facebook_id = ?");
        $stmt->execute([$facebookId]);
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetchColumn();
        } else {
            // إضافة مستخدم جديد
            $stmt = $pdo->prepare("INSERT INTO users (facebook_id, state) VALUES (?, 'new_user')");
            $stmt->execute([$facebookId]);
            return 'new_user';
        }
    } catch(PDOException $e) {
        return 'new_user';
    }
}

// إنشاء رمز FAZA.4
function generateFAZA4Code($facebookId) {
    global $pdo;
    
    // إنشاء رمز فريد: FAZA.4-XXXXXX
    $code = "FAZA.4-" . strtoupper(substr(md5(uniqid()), 0, 6));
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET faza4_code = ?, code_expiry = ?, state = 'pending_verification' WHERE facebook_id = ?");
        $stmt->execute([$code, $expiry, $facebookId]);
        
        // تسجيل إنشاء الرمز
        logVerification($facebookId, $code, 'success');
        
        return $code;
    } catch(PDOException $e) {
        // إنشاء رمز بدون قاعدة بيانات
        return "FAZA.4-" . rand(100000, 999999);
    }
}

// التحقق من رمز FAZA.4
function verifyFAZA4Code($facebookId, $code) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT faza4_code, code_expiry FROM users WHERE facebook_id = ?");
        $stmt->execute([$facebookId]);
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $savedCode = $row['faza4_code'];
            $expiry = $row['code_expiry'];
            
            // التحقق من الصلاحية
            if ($savedCode === $code && strtotime($expiry) > time()) {
                logVerification($facebookId, $code, 'success');
                return true;
            }
        }
        
        logVerification($facebookId, $code, 'failed');
        return false;
    } catch(PDOException $e) {
        // التحقق البسيط بدون قاعدة بيانات
        return preg_match('/^FAZA\.4\-[A-Z0-9]{6}$/', $code);
    }
}

// تحديث حالة المستخدم
function updateUserState($facebookId, $state) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET state = ? WHERE facebook_id = ?");
        $stmt->execute([$state, $facebookId]);
    } catch(PDOException $e) {
        // تجاهل الخطأ
    }
}

// الحصول على معلومات المستخدم
function getUserInfo($facebookId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT phone, created_at FROM users WHERE facebook_id = ?");
        $stmt->execute([$facebookId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch(PDOException $e) {
        return [];
    }
}

// تسجيل محاولات التحقق
function logVerification($facebookId, $code, $status) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO verification_logs (facebook_id, code, status) VALUES (?, ?, ?)");
        $stmt->execute([$facebookId, $code, $status]);
    } catch(PDOException $e) {
        // تجاهل الخطأ
    }
}

// ======= دالة الإرسال =======
function sendMessage($recipient_id, $text, $token) {
    $url = "https://graph.facebook.com/v18.0/me/messages?access_token=" . $token;

    $payload = [
        "recipient" => ["id" => $recipient_id],
        "message"   => ["text" => $text]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

http_response_code(200);
echo "EVENT_RECEIVED";
?>