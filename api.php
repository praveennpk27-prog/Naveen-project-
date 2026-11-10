<?php
/**
 * ============================================================================
 * Hostinger PHP + MySQL Backend API (api.php)
 * Anaesthesiologist Logbook — 3-Tier Subscription Plans & Admin Plan Approval
 * ============================================================================
 * Plans:
 *   - 'free'    : Default on Sign Up. Patient cases stored in user's browser LocalStorage.
 *   - 'elite'   : Unlocks Google Drive Sync for the user.
 *   - 'premium' : Unlocks patient data storage directly in this Hostinger MySQL Database.
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================================================
// HOSTINGER DATABASE CREDENTIALS — UPDATE THESE 4 VALUES WITH YOUR HPANEL DETAILS
// ============================================================================
$DB_HOST = "localhost";                  // Usually "localhost" on Hostinger
$DB_NAME = "u123456789_logbook";         // Your Hostinger MySQL Database Name
$DB_USER = "u123456789_admin";           // Your Hostinger MySQL Username
$DB_PASS = "YourStrongPassword123!";     // Your Hostinger MySQL Password

// ============================================================================
// DEFAULT ADMIN CREDENTIALS
// ============================================================================
$ADMIN_IDS  = ["admin", "9999999999"];
$ADMIN_PASS = "Admin@123";

function sendJson($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `doctors` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `doctor_name` VARCHAR(150) NOT NULL,
          `mobile_number` VARCHAR(20) NOT NULL,
          `password_hash` VARCHAR(255) NOT NULL,
          `plan` VARCHAR(20) NOT NULL DEFAULT 'free',
          `requested_plan` VARCHAR(20) DEFAULT '',
          `plan_status` VARCHAR(20) NOT NULL DEFAULT 'none',
          `profile_json` LONGTEXT DEFAULT NULL,
          `cases_json` LONGTEXT DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uniq_mobile_number` (`mobile_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Safe migration if `doctors` table was created earlier without plan columns
    $cols = $pdo->query("SHOW COLUMNS FROM `doctors`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('plan', $cols)) {
        $pdo->exec("ALTER TABLE `doctors` ADD COLUMN `plan` VARCHAR(20) NOT NULL DEFAULT 'free' AFTER `password_hash`");
    }
    if (!in_array('requested_plan', $cols)) {
        $pdo->exec("ALTER TABLE `doctors` ADD COLUMN `requested_plan` VARCHAR(20) DEFAULT '' AFTER `plan`");
    }
    if (!in_array('plan_status', $cols)) {
        $pdo->exec("ALTER TABLE `doctors` ADD COLUMN `plan_status` VARCHAR(20) NOT NULL DEFAULT 'none' AFTER `requested_plan`");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `logbook_cases` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `doctor_id` INT UNSIGNED NOT NULL,
          `case_uid` VARCHAR(64) NOT NULL,
          `case_date` DATE DEFAULT NULL,
          `hospital` VARCHAR(200) DEFAULT NULL,
          `patient_id` VARCHAR(100) DEFAULT NULL,
          `age` VARCHAR(20) DEFAULT NULL,
          `sex` VARCHAR(20) DEFAULT NULL,
          `priority` VARCHAR(30) DEFAULT NULL,
          `supervision` VARCHAR(60) DEFAULT NULL,
          `specialty` VARCHAR(120) DEFAULT NULL,
          `diagnosis` VARCHAR(255) DEFAULT NULL,
          `procedure_name` VARCHAR(255) DEFAULT NULL,
          `asa_status` VARCHAR(20) DEFAULT NULL,
          `technique` VARCHAR(200) DEFAULT NULL,
          `case_json` LONGTEXT NOT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uniq_doctor_case` (`doctor_id`, `case_uid`),
          KEY `idx_doctor_id` (`doctor_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (PDOException $e) {
    sendJson([
        "status"  => "error",
        "message" => "Hostinger MySQL connection failed. Check DB credentials in api.php.",
        "debug"   => $e->getMessage()
    ], 500);
}

$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = isset($input['action']) ? trim($input['action']) : (isset($_GET['action']) ? trim($_GET['action']) : 'status');

function normalizeMobile($mob) {
    $raw = strtolower(trim((string)$mob));
    if ($raw === 'admin') return 'admin';
    $digits = preg_replace('/[^0-9]/', '', $raw);
    if (strlen($digits) > 10 && substr($digits, 0, 2) === '91' && strlen($digits) === 12) {
        $digits = substr($digits, 2);
    }
    return $digits;
}

// ============================================================================
// 1. TEST CONNECTION (ADMIN)
// ============================================================================
if ($action === 'status' || $action === 'test_connection') {
    $countStmt = $pdo->query("SELECT COUNT(*) AS cnt FROM `doctors`");
    $row = $countStmt->fetch();
    sendJson([
        "status"         => "success",
        "message"        => "Connected to Hostinger MySQL Database!",
        "registeredDocs" => (int)($row['cnt'] ?? 0),
        "timestamp"      => date('c')
    ]);
}

// ============================================================================
// 2. DOCTOR SIGN UP (Defaults to Free Plan)
// ============================================================================
if ($action === 'signup') {
    $doctorName = trim($input['doctor_name'] ?? '');
    $mobileRaw  = trim($input['mobile_number'] ?? '');
    $mobile     = normalizeMobile($mobileRaw);
    $password   = (string)($input['password'] ?? '');

    if ($doctorName === '') {
        sendJson(["status" => "error", "message" => "Doctor Name is required."], 400);
    }
    if ($mobile === 'admin' || in_array($mobile, $ADMIN_IDS, true)) {
        sendJson(["status" => "error", "message" => "This mobile number is reserved for Admin."], 400);
    }
    if (strlen($mobile) < 10 || strlen($mobile) > 15) {
        sendJson(["status" => "error", "message" => "Please enter a valid 10-digit mobile number."], 400);
    }
    if (strlen($password) < 4) {
        sendJson(["status" => "error", "message" => "Password must be at least 4 characters long."], 400);
    }

    $checkStmt = $pdo->prepare("SELECT `id` FROM `doctors` WHERE `mobile_number` = :mobile LIMIT 1");
    $checkStmt->execute([':mobile' => $mobile]);
    if ($checkStmt->fetch()) {
        sendJson([
            "status"  => "error",
            "message" => "This mobile number ({$mobile}) is already registered. Please Sign In."
        ], 409);
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $defaultProfile = json_encode([
        "name"    => $doctorName,
        "mobile"  => $mobile,
        "dob"     => "",
        "reg"     => "",
        "qual"    => "",
        "desig"   => "",
        "council" => "",
        "addr"    => "",
        "country" => "India"
    ], JSON_UNESCAPED_UNICODE);

    $insertStmt = $pdo->prepare("
        INSERT INTO `doctors` (`doctor_name`, `mobile_number`, `password_hash`, `plan`, `requested_plan`, `plan_status`, `profile_json`, `cases_json`)
        VALUES (:name, :mobile, :hash, 'free', '', 'none', :profile, '[]')
    ");
    $insertStmt->execute([
        ':name'    => $doctorName,
        ':mobile'  => $mobile,
        ':hash'    => $passwordHash,
        ':profile' => $defaultProfile
    ]);

    sendJson([
        "status"  => "success",
        "message" => "Sign Up successful! Please Sign In with your mobile number and password.",
        "doctor"  => [
            "id"             => (int)$pdo->lastInsertId(),
            "doctor_name"    => $doctorName,
            "mobile_number"  => $mobile,
            "role"           => "doctor",
            "plan"           => "free",
            "requested_plan" => "",
            "plan_status"    => "none"
        ]
    ], 201);
}

// ============================================================================
// 3. SIGN IN (ADMIN + DOCTOR)
// ============================================================================
if ($action === 'signin') {
    $mobileRaw = trim($input['mobile_number'] ?? '');
    $mobile    = normalizeMobile($mobileRaw);
    $password  = (string)($input['password'] ?? '');

    if ($mobile === '' || $password === '') {
        sendJson(["status" => "error", "message" => "Please enter both mobile number and password."], 400);
    }

    if (in_array($mobile, $ADMIN_IDS, true) && $password === $ADMIN_PASS) {
        sendJson([
            "status"  => "success",
            "message" => "Welcome, Administrator!",
            "doctor"  => [
                "id"             => 0,
                "doctor_name"    => "Administrator",
                "mobile_number"  => $mobile,
                "role"           => "admin",
                "plan"           => "premium",
                "requested_plan" => "",
                "plan_status"    => "approved",
                "profile"        => ["name" => "Administrator", "mobile" => $mobile, "country" => "India"],
                "cases"          => []
            ]
        ]);
    }

    $stmt = $pdo->prepare("
        SELECT `id`, `doctor_name`, `mobile_number`, `password_hash`, `plan`, `requested_plan`, `plan_status`, `profile_json`, `cases_json`, `created_at`
        FROM `doctors`
        WHERE `mobile_number` = :mobile
        LIMIT 1
    ");
    $stmt->execute([':mobile' => $mobile]);
    $doctor = $stmt->fetch();

    if (!$doctor || !password_verify($password, $doctor['password_hash'])) {
        sendJson([
            "status"  => "error",
            "message" => "Invalid mobile number or password."
        ], 401);
    }

    $profile = json_decode($doctor['profile_json'] ?? '{}', true) ?: [];
    if (empty($profile['name'])) $profile['name'] = $doctor['doctor_name'];
    $profile['mobile'] = $doctor['mobile_number'];

    $plan = $doctor['plan'] ?: 'free';
    // Only return server-stored cases if user is on Premium plan
    $cases = ($plan === 'premium') ? (json_decode($doctor['cases_json'] ?? '[]', true) ?: []) : [];

    sendJson([
        "status"  => "success",
        "message" => "Welcome back, " . $doctor['doctor_name'] . "!",
        "doctor"  => [
            "id"             => (int)$doctor['id'],
            "doctor_name"    => $doctor['doctor_name'],
            "mobile_number"  => $doctor['mobile_number'],
            "role"           => "doctor",
            "plan"           => $plan,
            "requested_plan" => $doctor['requested_plan'] ?? '',
            "plan_status"    => $doctor['plan_status'] ?? 'none',
            "profile"        => $profile,
            "cases"          => $cases
        ]
    ]);
}

// ============================================================================
// 4. DOCTOR REQUESTS PLAN UPGRADE ('elite' or 'premium')
// ============================================================================
if ($action === 'request_plan') {
    $mobile  = normalizeMobile($input['mobile_number'] ?? '');
    $reqPlan = strtolower(trim($input['requested_plan'] ?? ''));

    if (!in_array($reqPlan, ['elite', 'premium'], true)) {
        sendJson(["status" => "error", "message" => "Invalid plan requested."], 400);
    }

    $upd = $pdo->prepare("
        UPDATE `doctors`
        SET `requested_plan` = :req, `plan_status` = 'pending'
        WHERE `mobile_number` = :m
    ");
    $upd->execute([':req' => $reqPlan, ':m' => $mobile]);

    sendJson([
        "status"         => "success",
        "message"        => "Upgrade request for " . ucfirst($reqPlan) . " Plan sent to Admin for approval.",
        "requested_plan" => $reqPlan,
        "plan_status"    => "pending"
    ]);
}

// ============================================================================
// 5. CHECK DOCTOR PLAN STATUS & FETCH PREMIUM DATA
// ============================================================================
if ($action === 'check_plan_status' || $action === 'fetch_data') {
    $mobile = normalizeMobile($input['mobile_number'] ?? ($_GET['mobile_number'] ?? ''));
    $stmt = $pdo->prepare("
        SELECT `id`, `doctor_name`, `mobile_number`, `plan`, `requested_plan`, `plan_status`, `profile_json`, `cases_json`, `updated_at`
        FROM `doctors`
        WHERE `mobile_number` = :m
        LIMIT 1
    ");
    $stmt->execute([':m' => $mobile]);
    $doc = $stmt->fetch();
    if (!$doc) {
        sendJson(["status" => "error", "message" => "Doctor not found."], 404);
    }
    $plan = $doc['plan'] ?: 'free';
    sendJson([
        "status"         => "success",
        "doctor_name"    => $doc['doctor_name'],
        "mobile_number"  => $doc['mobile_number'],
        "plan"           => $plan,
        "requested_plan" => $doc['requested_plan'] ?? '',
        "plan_status"    => $doc['plan_status'] ?? 'none',
        "doctorProfile"  => json_decode($doc['profile_json'] ?? '{}', true) ?: [],
        "cases"          => ($plan === 'premium') ? (json_decode($doc['cases_json'] ?? '[]', true) ?: []) : []
    ]);
}

// ============================================================================
// 6. SYNC CASES & PROFILE TO HOSTINGER MYSQL (CASES STORED ONLY FOR PREMIUM)
// ============================================================================
if ($action === 'sync_data') {
    $mobile = normalizeMobile($input['mobile_number'] ?? '');
    if ($mobile === '' || $mobile === 'admin') {
        sendJson(["status" => "success", "message" => "Skipped."]);
    }

    $stmt = $pdo->prepare("SELECT `id`, `doctor_name`, `plan` FROM `doctors` WHERE `mobile_number` = :mobile LIMIT 1");
    $stmt->execute([':mobile' => $mobile]);
    $doctor = $stmt->fetch();

    if (!$doctor) {
        sendJson(["status" => "error", "message" => "Doctor account not found."], 404);
    }

    $docId   = (int)$doctor['id'];
    $plan    = $doctor['plan'] ?: 'free';
    $profile = isset($input['doctorProfile']) && is_array($input['doctorProfile']) ? $input['doctorProfile'] : [];
    $cases   = isset($input['allCases']) && is_array($input['allCases']) ? $input['allCases'] : [];
    $docName = !empty($profile['name']) ? trim($profile['name']) : $doctor['doctor_name'];

    if ($plan !== 'premium') {
        // Free & Elite users only update profile metadata on Hostinger; patient cases are NOT stored in Hostinger DB
        $upd = $pdo->prepare("UPDATE `doctors` SET `doctor_name` = :name, `profile_json` = :profile WHERE `id` = :id");
        $upd->execute([
            ':name'    => $docName,
            ':profile' => json_encode($profile, JSON_UNESCAPED_UNICODE),
            ':id'      => $docId
        ]);
        sendJson([
            "status"  => "success",
            "plan"    => $plan,
            "message" => "Profile synced. Upgrade to Premium Plan to store patient cases in the server database."
        ]);
    }

    // PREMIUM PLAN: Store all patient cases in `doctors.cases_json` and `logbook_cases`
    $upd = $pdo->prepare("
        UPDATE `doctors`
        SET `doctor_name` = :name,
            `profile_json` = :profile,
            `cases_json` = :cases
        WHERE `id` = :id
    ");
    $upd->execute([
        ':name'    => $docName,
        ':profile' => json_encode($profile, JSON_UNESCAPED_UNICODE),
        ':cases'   => json_encode($cases, JSON_UNESCAPED_UNICODE),
        ':id'      => $docId
    ]);

    $upsertCase = $pdo->prepare("
        INSERT INTO `logbook_cases` (
            `doctor_id`, `case_uid`, `case_date`, `hospital`, `patient_id`,
            `age`, `sex`, `priority`, `supervision`, `specialty`,
            `diagnosis`, `procedure_name`, `asa_status`, `technique`, `case_json`
        ) VALUES (
            :doc_id, :uid, :cdate, :hosp, :pat,
            :age, :sex, :prio, :sup, :spec,
            :diag, :proc, :asa, :tech, :cjson
        )
        ON DUPLICATE KEY UPDATE
            `case_date`      = VALUES(`case_date`),
            `hospital`       = VALUES(`hospital`),
            `patient_id`     = VALUES(`patient_id`),
            `age`            = VALUES(`age`),
            `sex`            = VALUES(`sex`),
            `priority`       = VALUES(`priority`),
            `supervision`    = VALUES(`supervision`),
            `specialty`      = VALUES(`specialty`),
            `diagnosis`      = VALUES(`diagnosis`),
            `procedure_name` = VALUES(`procedure_name`),
            `asa_status`     = VALUES(`asa_status`),
            `technique`      = VALUES(`technique`),
            `case_json`      = VALUES(`case_json`)
    ");

    foreach ($cases as $c) {
        if (!is_array($c) || empty($c['id'])) continue;
        $cDate = (!empty($c['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $c['date'])) ? $c['date'] : null;
        $upsertCase->execute([
            ':doc_id' => $docId,
            ':uid'    => substr((string)$c['id'], 0, 64),
            ':cdate'  => $cDate,
            ':hosp'   => substr((string)($c['hosp'] ?? ''), 0, 200),
            ':pat'    => substr((string)($c['patId'] ?? ''), 0, 100),
            ':age'    => substr((string)($c['age'] ?? ''), 0, 20),
            ':sex'    => substr((string)($c['sex'] ?? ''), 0, 20),
            ':prio'   => substr((string)($c['prio'] ?? ''), 0, 30),
            ':sup'    => substr((string)($c['sup'] ?? ''), 0, 60),
            ':spec'   => substr((string)($c['spec'] ?? ''), 0, 120),
            ':diag'   => substr((string)($c['diag'] ?? ''), 0, 255),
            ':proc'   => substr((string)($c['proc'] ?? ''), 0, 255),
            ':asa'    => substr((string)($c['asa'] ?? ''), 0, 20),
            ':tech'   => substr((string)($c['tech'] ?? ''), 0, 200),
            ':cjson'  => json_encode($c, JSON_UNESCAPED_UNICODE)
        ]);
    }

    sendJson([
        "status"     => "success",
        "plan"       => "premium",
        "totalCases" => count($cases),
        "updatedAt"  => date('c')
    ]);
}

// ============================================================================
// 7. ADMIN ENDPOINTS: LIST DOCTORS, APPROVE / REJECT PLAN, RESET PASS, DELETE
// ============================================================================
if ($action === 'admin_list_doctors') {
    $stmt = $pdo->query("SELECT `id`, `doctor_name`, `mobile_number`, `plan`, `requested_plan`, `plan_status`, `profile_json`, `cases_json`, `created_at`, `updated_at` FROM `doctors` ORDER BY `id` DESC");
    $rows = $stmt->fetchAll();
    $list = [];
    foreach ($rows as $r) {
        $cArr = json_decode($r['cases_json'] ?? '[]', true);
        $list[] = [
            "id"             => (int)$r['id'],
            "doctor_name"    => $r['doctor_name'],
            "mobile_number"  => $r['mobile_number'],
            "plan"           => $r['plan'] ?: 'free',
            "requested_plan" => $r['requested_plan'] ?: '',
            "plan_status"    => $r['plan_status'] ?: 'none',
            "cases_count"    => is_array($cArr) ? count($cArr) : 0,
            "cases"          => is_array($cArr) ? $cArr : [],
            "profile"        => json_decode($r['profile_json'] ?? '{}', true) ?: [],
            "created_at"     => $r['created_at'],
            "updated_at"     => $r['updated_at']
        ];
    }
    sendJson(["status" => "success", "doctors" => $list]);
}

if ($action === 'admin_handle_plan') {
    $targetMob = normalizeMobile($input['target_mobile'] ?? '');
    $decision  = strtolower(trim($input['decision'] ?? '')); // 'approve', 'reject', or 'set_plan'
    $newPlan   = strtolower(trim($input['plan'] ?? ''));

    $stmt = $pdo->prepare("SELECT `plan`, `requested_plan` FROM `doctors` WHERE `mobile_number` = :m LIMIT 1");
    $stmt->execute([':m' => $targetMob]);
    $doc = $stmt->fetch();
    if (!$doc) {
        sendJson(["status" => "error", "message" => "Doctor not found."], 404);
    }

    if ($decision === 'approve') {
        $approvedPlan = in_array($newPlan, ['free', 'elite', 'premium'], true) ? $newPlan : ($doc['requested_plan'] ?: 'elite');
        $upd = $pdo->prepare("UPDATE `doctors` SET `plan` = :p, `requested_plan` = '', `plan_status` = 'approved' WHERE `mobile_number` = :m");
        $upd->execute([':p' => $approvedPlan, ':m' => $targetMob]);
        sendJson(["status" => "success", "plan" => $approvedPlan, "plan_status" => "approved"]);
    } elseif ($decision === 'reject') {
        $upd = $pdo->prepare("UPDATE `doctors` SET `plan_status` = 'rejected' WHERE `mobile_number` = :m");
        $upd->execute([':m' => $targetMob]);
        sendJson(["status" => "success", "plan" => $doc['plan'], "plan_status" => "rejected"]);
    } elseif ($decision === 'set_plan' && in_array($newPlan, ['free', 'elite', 'premium'], true)) {
        $upd = $pdo->prepare("UPDATE `doctors` SET `plan` = :p, `requested_plan` = '', `plan_status` = 'approved' WHERE `mobile_number` = :m");
        $upd->execute([':p' => $newPlan, ':m' => $targetMob]);
        sendJson(["status" => "success", "plan" => $newPlan, "plan_status" => "approved"]);
    }

    sendJson(["status" => "error", "message" => "Invalid decision parameter."], 400);
}

if ($action === 'admin_delete_doctor') {
    $targetMob = normalizeMobile($input['target_mobile'] ?? '');
    $del = $pdo->prepare("DELETE FROM `doctors` WHERE `mobile_number` = :m");
    $del->execute([':m' => $targetMob]);
    sendJson(["status" => "success", "message" => "Doctor deleted from database."]);
}

if ($action === 'admin_reset_password') {
    $targetMob = normalizeMobile($input['target_mobile'] ?? '');
    $newPass   = (string)($input['new_password'] ?? '');
    if (strlen($newPass) < 4) {
        sendJson(["status" => "error", "message" => "Password must be at least 4 characters."], 400);
    }
    $hash = password_hash($newPass, PASSWORD_BCRYPT);
    $upd = $pdo->prepare("UPDATE `doctors` SET `password_hash` = :h WHERE `mobile_number` = :m");
    $upd->execute([':h' => $hash, ':m' => $targetMob]);
    sendJson(["status" => "success", "message" => "Password updated in database."]);
}

sendJson(["status" => "error", "message" => "Unknown API action: " . $action], 400);
