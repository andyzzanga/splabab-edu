<?php
date_default_timezone_set('Asia/Seoul');

define('EDU_DATA_DIR', __DIR__ . '/data');
define('EDU_UPLOAD_DIR', EDU_DATA_DIR . '/uploads');
define('EDU_DB_FILE', EDU_DATA_DIR . '/edu.sqlite');
define('EDU_MAX_UPLOAD', 30 * 1024 * 1024);
define('EDU_MAX_NOTICE_ATTACHMENTS', 10);
define('EDU_MAX_SUBMISSION_ATTACHMENTS', 10);

function edu_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function edu_json($data, $status = 200) {
    if (function_exists('http_response_code')) http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

function edu_seed_stores() {
    $names = array('종로','대치(정성담은)','회현','선릉','이태원','등촌','단당','이수2','반포(고터)','잠실','종각','시청','영등포1','신대방2','신대방삼거리','노량진3','신림','건대','아현','문래','서울대1','영등포2','대방','숙대','방배','서울대2','여의도','성신여대','노량진2','발산','봉천','강남역','을지로','위례');
    $stores = array();
    foreach ($names as $i => $name) $stores[] = array('code' => str_pad($i + 1, 2, '0', STR_PAD_LEFT), 'name' => $name, 'active' => 1);
    return $stores;
}

function edu_stores() {
    return edu_seed_stores();
}

function edu_store_name($code) {
    foreach (edu_stores() as $store) if ($store['code'] === $code) return $store['name'];
    return '';
}

function edu_admin_account($password) {
    $raw = getenv('EDU_ADMIN_ACCOUNTS');
    $accounts = $raw ? json_decode($raw, true) : array();
    if (!is_array($accounts)) $accounts = array();
    foreach ($accounts as $account) if ((string)$account['password'] === (string)$password) return $account;
    return null;
}

function edu_is_admin() {
    if (session_id() === '') session_start();
    return !empty($_SESSION['edu_admin']);
}

function edu_admin_name() {
    if (session_id() === '') session_start();
    return !empty($_SESSION['edu_admin_name']) ? (string)$_SESSION['edu_admin_name'] : '관리자';
}

function edu_require_admin_page() {
    if (!edu_is_admin()) { header('Location: /edu/login.php'); exit; }
}

function edu_require_admin_api() {
    if (!edu_is_admin()) edu_json(array('error' => '관리자 로그인이 필요합니다.'), 401);
}

function edu_employee_key($id) {
    return 'employee:' . (int)$id;
}

function edu_employee_account($employee) {
    $employee = trim((string)$employee);
    if ($employee === '') return null;
    $db = edu_db();
    $stmt = $db->prepare("SELECT * FROM employees WHERE employee_name=? AND active=1 LIMIT 1");
    $stmt->execute(array($employee));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : null;
}

function edu_employee_password_ok($account, $password) {
    if (!$account) return true;
    $password = (string)$password;
    if (function_exists('password_verify')) return password_verify($password, $account['password_hash']);
    return hash('sha256', $password) === $account['password_hash'];
}

function edu_public_employee($row) {
    return array(
        'id'=>(int)$row['id'],
        'store_code'=>$row['store_code'],
        'store_name'=>$row['store_name'],
        'employee_name'=>$row['employee_name'],
        'active'=>(int)$row['active'],
        'created_at'=>$row['created_at'],
        'updated_at'=>$row['updated_at']
    );
}

function edu_employee_login($account) {
    if (session_id() === '') session_start();
    $_SESSION['edu_employee_key'] = edu_employee_key($account['id']);
    $_SESSION['edu_employee_id'] = (int)$account['id'];
    $_SESSION['edu_employee_name'] = $account['employee_name'];
    $_SESSION['edu_employee_store'] = $account['store_code'];
    $_SESSION['edu_employee_store_name'] = $account['store_name'];
}

function edu_current_employee() {
    if (session_id() === '') session_start();
    if (empty($_SESSION['edu_employee_id']) || empty($_SESSION['edu_employee_key'])) return null;
    $db = edu_db();
    $stmt = $db->prepare('SELECT * FROM employees WHERE id=? AND active=1');
    $stmt->execute(array((int)$_SESSION['edu_employee_id']));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || $_SESSION['edu_employee_key'] !== edu_employee_key($row['id'])) return null;
    return $row;
}

function edu_require_employee_api() {
    $employee = edu_current_employee();
    if (!$employee) edu_json(array('error'=>'직원 로그인이 필요합니다.'), 401);
    return $employee;
}

function edu_csrf_token() {
    if (session_id() === '') session_start();
    if (empty($_SESSION['edu_csrf'])) $_SESSION['edu_csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
    return $_SESSION['edu_csrf'];
}

function edu_check_csrf($token) {
    if (session_id() === '') session_start();
    if (empty($_SESSION['edu_csrf'])) return false;
    return function_exists('hash_equals') ? hash_equals($_SESSION['edu_csrf'], (string)$token) : $_SESSION['edu_csrf'] === (string)$token;
}

function edu_db() {
    if (!is_dir(EDU_DATA_DIR)) mkdir(EDU_DATA_DIR, 0775, true);
    if (!is_dir(EDU_UPLOAD_DIR)) mkdir(EDU_UPLOAD_DIR, 0775, true);
    static $db = null;
    if ($db instanceof PDO) return $db;
    $db = new PDO('sqlite:' . EDU_DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec("CREATE TABLE IF NOT EXISTS courses (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, category TEXT NOT NULL DEFAULT '', description TEXT NOT NULL DEFAULT '', content TEXT NOT NULL DEFAULT '', due_date TEXT NOT NULL DEFAULT '', active INTEGER NOT NULL DEFAULT 1, requires_proof INTEGER NOT NULL DEFAULT 0, sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS submissions (id INTEGER PRIMARY KEY AUTOINCREMENT, course_id INTEGER NOT NULL, store_code TEXT NOT NULL, store_name TEXT NOT NULL, employee_name TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'viewed', note TEXT NOT NULL DEFAULT '', proof_filename TEXT NOT NULL DEFAULT '', proof_original_name TEXT NOT NULL DEFAULT '', proof_mime TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE(course_id, store_code, employee_name))");
    $db->exec("CREATE TABLE IF NOT EXISTS submission_attachments (id INTEGER PRIMARY KEY AUTOINCREMENT, submission_id INTEGER NOT NULL, filename TEXT NOT NULL, original_name TEXT NOT NULL, mime TEXT NOT NULL, size INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS notices (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, body TEXT NOT NULL DEFAULT '', active INTEGER NOT NULL DEFAULT 1, priority INTEGER NOT NULL DEFAULT 0, created_by TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS notice_attachments (id INTEGER PRIMARY KEY AUTOINCREMENT, notice_id INTEGER NOT NULL, filename TEXT NOT NULL, original_name TEXT NOT NULL, mime TEXT NOT NULL, size INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS notice_confirmations (id INTEGER PRIMARY KEY AUTOINCREMENT, notice_id INTEGER NOT NULL, store_code TEXT NOT NULL, store_name TEXT NOT NULL, employee_name TEXT NOT NULL, confirmed_at TEXT NOT NULL, UNIQUE(notice_id, store_code, employee_name))");
    $db->exec("CREATE TABLE IF NOT EXISTS employee_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, store_code TEXT NOT NULL DEFAULT '', employee_name TEXT NOT NULL, password_hash TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE(store_code, employee_name))");
    $db->exec("CREATE TABLE IF NOT EXISTS employees (id INTEGER PRIMARY KEY AUTOINCREMENT, store_code TEXT NOT NULL, store_name TEXT NOT NULL, employee_name TEXT NOT NULL, password_hash TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE(employee_name))");
    $count = (int)$db->query('SELECT COUNT(*) FROM courses')->fetchColumn();
    if ($count === 0) {
        $now = date('c');
        $stmt = $db->prepare('INSERT INTO courses(title,category,description,content,due_date,active,requires_proof,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute(array('신입 직원 기본 오리엔테이션','기본 교육','매장 근무 전 반드시 확인해야 하는 기본 교육입니다.','라밥 브랜드 기준, 출근 전 준비, 고객 응대 기본, 위생 복장 기준을 확인하세요.\n\n확인 후 완료 버튼을 눌러 교육 이력을 남겨 주세요.','',1,0,10,$now,$now));
        $stmt->execute(array('위생·안전 체크 교육','필수 교육','전 매장 공통 위생/안전 점검 교육입니다.','손 씻기, 식재료 보관, 조리도구 세척, 화상/미끄럼 예방, 이상 상황 보고 기준을 확인하세요.\n\n매장에서 자체 체크 후 메모를 남겨 주세요.','',1,1,20,$now,$now));
        $stmt->execute(array('CARE 설비 신고 사용법','운영 교육','설비 문제가 생겼을 때 CARE 앱으로 신고하는 절차입니다.','매장 선택, 장비 선택, 증상 선택, 사진 촬영, 접수번호 확인 순서로 진행합니다.\n\n실제 고장이 아니라면 테스트 신고는 하지 말고 교육 완료만 제출해 주세요.','',1,0,30,$now,$now));
    }
    return $db;
}

function edu_public_notice($row, $confirmed) {
    return array(
        'id'=>(int)$row['id'],
        'title'=>$row['title'],
        'body'=>$row['body'],
        'priority'=>(int)$row['priority'],
        'confirmed'=>$confirmed ? true : false,
        'attachments'=>isset($row['attachments']) && is_array($row['attachments']) ? $row['attachments'] : array(),
        'updatedAt'=>$row['updated_at']
    );
}

function edu_public_course($row, $submission = null) {
    return array(
        'id'=>(int)$row['id'],
        'title'=>$row['title'],
        'category'=>$row['category'],
        'description'=>$row['description'],
        'content'=>$row['content'],
        'dueDate'=>$row['due_date'],
        'requiresProof'=>(int)$row['requires_proof'] === 1,
        'status'=>$submission ? $submission['status'] : 'not_started',
        'note'=>$submission ? $submission['note'] : '',
        'updatedAt'=>$submission ? $submission['updated_at'] : ''
    );
}

function edu_submission_upload_count() {
    if (empty($_FILES['proofs']) || empty($_FILES['proofs']['name'])) {
        if (empty($_FILES['proof']) || $_FILES['proof']['error'] === UPLOAD_ERR_NO_FILE) return 0;
        return 1;
    }
    $names = $_FILES['proofs']['name'];
    if (!is_array($names)) $names = array($names);
    $count = 0;
    foreach ($names as $i => $unused) {
        $error = isset($_FILES['proofs']['error'][$i]) ? $_FILES['proofs']['error'][$i] : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_NO_FILE) $count++;
    }
    return $count;
}

function edu_upload_proof() {
    if (empty($_FILES['proof']) || $_FILES['proof']['error'] === UPLOAD_ERR_NO_FILE) return array('', '', '');
    if ($_FILES['proof']['error'] !== UPLOAD_ERR_OK || $_FILES['proof']['size'] <= 0 || $_FILES['proof']['size'] > EDU_MAX_UPLOAD) edu_json(array('error'=>'첨부파일은 30MB 이하로 올려 주세요.'), 400);
    $allowed = array('image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','application/pdf'=>'pdf','video/mp4'=>'mp4','video/webm'=>'webm','video/quicktime'=>'mov');
    $mime = '';
    if (function_exists('finfo_open')) { $finfo = finfo_open(FILEINFO_MIME_TYPE); $mime = finfo_file($finfo, $_FILES['proof']['tmp_name']); finfo_close($finfo); }
    if ($mime === '' && getimagesize($_FILES['proof']['tmp_name'])) $mime = getimagesize($_FILES['proof']['tmp_name'])['mime'];
    if (!isset($allowed[$mime])) edu_json(array('error'=>'사진, PDF, MP4 파일만 첨부할 수 있습니다.'), 400);
    $dir = EDU_UPLOAD_DIR . '/' . date('Ym');
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = bin2hex(openssl_random_pseudo_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($_FILES['proof']['tmp_name'], $dir . '/' . $name)) edu_json(array('error'=>'첨부파일 저장에 실패했습니다.'), 500);
    chmod($dir . '/' . $name, 0644);
    return array(date('Ym') . '/' . $name, basename($_FILES['proof']['name']), $mime);
}

function edu_public_submission_attachment($row) {
    return array(
        'id'=>(int)$row['id'],
        'submission_id'=>(int)$row['submission_id'],
        'original_name'=>$row['original_name'],
        'mime'=>$row['mime'],
        'size'=>(int)$row['size'],
        'url'=>'/edu/api.php?route=submission-file&id=' . (int)$row['id']
    );
}

function edu_submission_attachments_map($db, $submissionIds) {
    $submissionIds = array_values(array_filter(array_map('intval', $submissionIds)));
    $map = array();
    if (!$submissionIds) return $map;
    $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
    $stmt = $db->prepare('SELECT * FROM submission_attachments WHERE submission_id IN (' . $placeholders . ') ORDER BY id');
    $stmt->execute($submissionIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $submissionId = (int)$row['submission_id'];
        if (!isset($map[$submissionId])) $map[$submissionId] = array();
        $map[$submissionId][] = edu_public_submission_attachment($row);
    }
    return $map;
}

function edu_attach_submission_files($submissionId, $replaceExisting) {
    $newCount = edu_submission_upload_count();
    if ($newCount > EDU_MAX_SUBMISSION_ATTACHMENTS) edu_json(array('error'=>'교육 완료 첨부는 최대 10개까지 올릴 수 있습니다.'), 400);
    $db = edu_db();
    if ($replaceExisting) {
        $delete = $db->prepare('DELETE FROM submission_attachments WHERE submission_id=?');
        $delete->execute(array((int)$submissionId));
    }
    if ($newCount < 1) return;
    $allowed = array('image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','video/mp4'=>'mp4','video/webm'=>'webm','video/quicktime'=>'mov');
    $files = array();
    if (!empty($_FILES['proofs']) && !empty($_FILES['proofs']['name'])) {
        $names = $_FILES['proofs']['name'];
        if (!is_array($names)) $names = array($names);
        for ($i = 0; $i < count($names); $i++) {
            $files[] = array(
                'name'=>$_FILES['proofs']['name'][$i],
                'tmp_name'=>$_FILES['proofs']['tmp_name'][$i],
                'error'=>$_FILES['proofs']['error'][$i],
                'size'=>$_FILES['proofs']['size'][$i]
            );
        }
    } else if (!empty($_FILES['proof'])) {
        $files[] = $_FILES['proof'];
    }
    $dir = EDU_UPLOAD_DIR . '/submissions/' . date('Ym');
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $now = date('c');
    foreach ($files as $file) {
        if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) continue;
        $size = isset($file['size']) ? (int)$file['size'] : 0;
        if ((int)$file['error'] !== UPLOAD_ERR_OK || $size <= 0 || $size > EDU_MAX_UPLOAD) edu_json(array('error'=>'교육 완료 첨부는 파일당 30MB 이하로 올려 주세요.'), 400);
        $mime = '';
        if (function_exists('finfo_open')) { $finfo = finfo_open(FILEINFO_MIME_TYPE); $mime = finfo_file($finfo, $file['tmp_name']); finfo_close($finfo); }
        if ($mime === '' && @getimagesize($file['tmp_name'])) $mime = getimagesize($file['tmp_name'])['mime'];
        if (!isset($allowed[$mime])) edu_json(array('error'=>'교육 완료 첨부는 이미지와 동영상만 올릴 수 있습니다.'), 400);
        $name = bin2hex(openssl_random_pseudo_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) edu_json(array('error'=>'교육 완료 첨부파일 저장에 실패했습니다.'), 500);
        chmod($dir . '/' . $name, 0644);
        $stmt = $db->prepare('INSERT INTO submission_attachments(submission_id,filename,original_name,mime,size,created_at) VALUES(?,?,?,?,?,?)');
        $stmt->execute(array((int)$submissionId,'submissions/' . date('Ym') . '/' . $name,basename($file['name']),$mime,$size,$now));
    }
}

function edu_public_notice_attachment($row) {
    return array(
        'id'=>(int)$row['id'],
        'notice_id'=>(int)$row['notice_id'],
        'original_name'=>$row['original_name'],
        'mime'=>$row['mime'],
        'size'=>(int)$row['size'],
        'url'=>'/edu/api.php?route=notice-file&id=' . (int)$row['id']
    );
}

function edu_notice_attachments_map($db, $noticeIds) {
    $noticeIds = array_values(array_filter(array_map('intval', $noticeIds)));
    $map = array();
    if (!$noticeIds) return $map;
    $placeholders = implode(',', array_fill(0, count($noticeIds), '?'));
    $stmt = $db->prepare('SELECT * FROM notice_attachments WHERE notice_id IN (' . $placeholders . ') ORDER BY id');
    $stmt->execute($noticeIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $noticeId = (int)$row['notice_id'];
        if (!isset($map[$noticeId])) $map[$noticeId] = array();
        $map[$noticeId][] = edu_public_notice_attachment($row);
    }
    return $map;
}

function edu_notice_upload_count() {
    if (empty($_FILES['notice_files']) || empty($_FILES['notice_files']['name'])) return 0;
    $names = $_FILES['notice_files']['name'];
    if (!is_array($names)) $names = array($names);
    $count = 0;
    foreach ($names as $i => $unused) {
        $error = isset($_FILES['notice_files']['error'][$i]) ? $_FILES['notice_files']['error'][$i] : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_NO_FILE) $count++;
    }
    return $count;
}

function edu_validate_notice_attachment_limit($noticeId) {
    $newCount = edu_notice_upload_count();
    if ($newCount < 1) return;
    $existingCount = 0;
    if ((int)$noticeId > 0) {
        $db = edu_db();
        $countStmt = $db->prepare('SELECT COUNT(*) FROM notice_attachments WHERE notice_id=?');
        $countStmt->execute(array((int)$noticeId));
        $existingCount = (int)$countStmt->fetchColumn();
    }
    if ($existingCount + $newCount > EDU_MAX_NOTICE_ATTACHMENTS) edu_json(array('error'=>'공지 첨부는 기존 파일을 포함해 최대 10개까지 등록할 수 있습니다.'), 400);
}

function edu_attach_notice_files($noticeId) {
    if (empty($_FILES['notice_files']) || empty($_FILES['notice_files']['name'])) return;
    $names = $_FILES['notice_files']['name'];
    if (!is_array($names)) $names = array($names);
    $newCount = edu_notice_upload_count();
    if ($newCount < 1) return;
    $db = edu_db();
    edu_validate_notice_attachment_limit($noticeId);
    $allowed = array(
        'image/jpeg'=>'jpg',
        'image/png'=>'png',
        'image/webp'=>'webp',
        'image/gif'=>'gif',
        'video/mp4'=>'mp4',
        'video/webm'=>'webm',
        'video/quicktime'=>'mov',
        'application/pdf'=>'pdf',
        'text/plain'=>'txt',
        'text/csv'=>'csv',
        'application/msword'=>'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx',
        'application/vnd.ms-excel'=>'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx',
        'application/vnd.ms-powerpoint'=>'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'=>'pptx'
    );
    $dir = EDU_UPLOAD_DIR . '/notices/' . date('Ym');
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $now = date('c');
    for ($i = 0; $i < count($names); $i++) {
        $error = isset($_FILES['notice_files']['error'][$i]) ? $_FILES['notice_files']['error'][$i] : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        $size = isset($_FILES['notice_files']['size'][$i]) ? (int)$_FILES['notice_files']['size'][$i] : 0;
        if ($error !== UPLOAD_ERR_OK || $size <= 0 || $size > EDU_MAX_UPLOAD) edu_json(array('error'=>'공지 첨부파일은 파일당 30MB 이하로 올려 주세요.'), 400);
        $tmp = $_FILES['notice_files']['tmp_name'][$i];
        $mime = '';
        if (function_exists('finfo_open')) { $finfo = finfo_open(FILEINFO_MIME_TYPE); $mime = finfo_file($finfo, $tmp); finfo_close($finfo); }
        if ($mime === '' && @getimagesize($tmp)) $mime = getimagesize($tmp)['mime'];
        if (!isset($allowed[$mime])) edu_json(array('error'=>'공지에는 사진, 동영상, PDF, 문서 파일만 첨부할 수 있습니다.'), 400);
        $name = bin2hex(openssl_random_pseudo_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($tmp, $dir . '/' . $name)) edu_json(array('error'=>'공지 첨부파일 저장에 실패했습니다.'), 500);
        chmod($dir . '/' . $name, 0644);
        $stmt = $db->prepare('INSERT INTO notice_attachments(notice_id,filename,original_name,mime,size,created_at) VALUES(?,?,?,?,?,?)');
        $stmt->execute(array((int)$noticeId,'notices/' . date('Ym') . '/' . $name,basename($_FILES['notice_files']['name'][$i]),$mime,$size,$now));
    }
}
?>
