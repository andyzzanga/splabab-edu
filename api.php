<?php
require_once __DIR__ . '/lib.php';

$route = isset($_GET['route']) ? $_GET['route'] : '';
$method = $_SERVER['REQUEST_METHOD'];

if ($route === 'config') {
    edu_json(array('csrf'=>edu_csrf_token(), 'stores'=>edu_stores()), 200);
}

if ($route === 'employee-login' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || !edu_check_csrf(isset($body['csrf']) ? $body['csrf'] : '')) edu_json(array('error'=>'요청이 만료되었습니다. 새로고침 후 다시 시도해 주세요.'), 403);
    $employee = trim(isset($body['employee']) ? (string)$body['employee'] : '');
    $password = isset($body['password']) ? (string)$body['password'] : '';
    if (function_exists('mb_substr')) $employee = mb_substr($employee, 0, 40, 'UTF-8'); else $employee = substr($employee, 0, 40);
    if ($employee === '' || !preg_match('/^\d{4}$/', $password)) edu_json(array('error'=>'이름과 4자리 비밀번호를 입력해 주세요.'), 400);
    $account = edu_employee_account($employee);
    if (!$account || !edu_employee_password_ok($account, $password)) edu_json(array('error'=>'직원 이름 또는 비밀번호를 확인해 주세요.'), 401);
    edu_employee_login($account);
    edu_json(array('ok'=>true, 'employee'=>edu_public_employee($account)), 200);
}

if ($route === 'employee-me' && $method === 'GET') {
    $employee = edu_current_employee();
    edu_json(array('employee'=>$employee ? edu_public_employee($employee) : null), 200);
}

if ($route === 'courses' && $method === 'GET') {
    $employeeRow = edu_require_employee_api();
    $store = $employeeRow['store_code'];
    $employee = $employeeRow['employee_name'];
    $db = edu_db();
    $courses = $db->query('SELECT * FROM courses WHERE active=1 ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    $noticeRows = $db->query('SELECT * FROM notices WHERE active=1 ORDER BY priority DESC, updated_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
    $submissions = array();
    $confirmedNotices = array();
    if ($store !== '' && $employee !== '') {
        $stmt = $db->prepare('SELECT * FROM submissions WHERE store_code=? AND employee_name=?');
        $stmt->execute(array($store, $employee));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $submissions[(int)$row['course_id']] = $row;
        $noticeStmt = $db->prepare('SELECT notice_id FROM notice_confirmations WHERE store_code=? AND employee_name=?');
        $noticeStmt->execute(array($store, $employee));
        foreach ($noticeStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $confirmedNotices[(int)$row['notice_id']] = true;
    }
    $noticeIds = array();
    foreach ($noticeRows as $notice) $noticeIds[] = (int)$notice['id'];
    $attachmentMap = edu_notice_attachments_map($db, $noticeIds);
    $notices = array();
    foreach ($noticeRows as $notice) {
        $notice['attachments'] = isset($attachmentMap[(int)$notice['id']]) ? $attachmentMap[(int)$notice['id']] : array();
        $notices[] = edu_public_notice($notice, isset($confirmedNotices[(int)$notice['id']]));
    }
    $out = array();
    foreach ($courses as $course) $out[] = edu_public_course($course, isset($submissions[(int)$course['id']]) ? $submissions[(int)$course['id']] : null);
    edu_json(array('courses'=>$out, 'notices'=>$notices), 200);
}

if ($route === 'notice-confirm' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || !edu_check_csrf(isset($body['csrf']) ? $body['csrf'] : '')) edu_json(array('error'=>'요청이 만료되었습니다. 새로고침 후 다시 시도해 주세요.'), 403);
    $noticeId = isset($body['notice_id']) ? (int)$body['notice_id'] : 0;
    $employeeRow = edu_require_employee_api();
    $store = $employeeRow['store_code'];
    $storeName = edu_store_name($store);
    $employee = $employeeRow['employee_name'];
    if ($noticeId < 1 || $storeName === '' || $employee === '') edu_json(array('error'=>'공지 확인 정보를 확인해 주세요.'), 400);
    $db = edu_db();
    $exists = $db->prepare('SELECT COUNT(*) FROM notices WHERE id=? AND active=1');
    $exists->execute(array($noticeId));
    if (!(int)$exists->fetchColumn()) edu_json(array('error'=>'공지사항을 찾을 수 없습니다.'), 404);
    $find = $db->prepare('SELECT id FROM notice_confirmations WHERE notice_id=? AND store_code=? AND employee_name=?');
    $find->execute(array($noticeId, $store, $employee));
    if (!$find->fetchColumn()) {
        $stmt = $db->prepare('INSERT INTO notice_confirmations(notice_id,store_code,store_name,employee_name,confirmed_at) VALUES(?,?,?,?,?)');
        $stmt->execute(array($noticeId,$store,$storeName,$employee,date('c')));
    }
    edu_json(array('ok'=>true), 200);
}

if ($route === 'submission' && $method === 'POST') {
    if (!edu_check_csrf(isset($_POST['csrf']) ? $_POST['csrf'] : '')) edu_json(array('error'=>'요청이 만료되었습니다. 새로고침 후 다시 시도해 주세요.'), 403);
    $courseId = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
    $employeeRow = edu_require_employee_api();
    $store = $employeeRow['store_code'];
    $storeName = edu_store_name($store);
    $employee = $employeeRow['employee_name'];
    $status = isset($_POST['status']) && $_POST['status'] === 'completed' ? 'completed' : 'viewed';
    $note = trim(isset($_POST['note']) ? (string)$_POST['note'] : '');
    if (function_exists('mb_substr')) { $note = mb_substr($note, 0, 500, 'UTF-8'); }
    else { $note = substr($note, 0, 500); }
    if ($courseId < 1 || $storeName === '' || $employee === '') edu_json(array('error'=>'매장, 이름, 교육 과정을 확인해 주세요.'), 400);
    $db = edu_db();
    $courseStmt = $db->prepare('SELECT * FROM courses WHERE id=? AND active=1');
    $courseStmt->execute(array($courseId));
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    if (!$course) edu_json(array('error'=>'교육 과정을 찾을 수 없습니다.'), 404);
    $newAttachmentCount = edu_submission_upload_count();
    if ($newAttachmentCount > EDU_MAX_SUBMISSION_ATTACHMENTS) edu_json(array('error'=>'교육 완료 첨부는 최대 10개까지 올릴 수 있습니다.'), 400);
    if ((int)$course['requires_proof'] === 1 && $status === 'completed' && $newAttachmentCount < 1) {
        $existing = $db->prepare('SELECT s.id,s.proof_filename,(SELECT COUNT(*) FROM submission_attachments sa WHERE sa.submission_id=s.id) AS attachment_count FROM submissions s WHERE s.course_id=? AND s.store_code=? AND s.employee_name=?');
        $existing->execute(array($courseId, $store, $employee));
        $existingProof = $existing->fetch(PDO::FETCH_ASSOC);
        if (!$existingProof || ($existingProof['proof_filename'] === '' && (int)$existingProof['attachment_count'] < 1)) edu_json(array('error'=>'이 교육은 완료 확인 첨부가 필요합니다.'), 400);
    }
    $now = date('c');
    $find = $db->prepare('SELECT id,proof_filename,proof_original_name,proof_mime FROM submissions WHERE course_id=? AND store_code=? AND employee_name=?');
    $find->execute(array($courseId, $store, $employee));
    $existing = $find->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $stmt = $db->prepare('UPDATE submissions SET status=?,note=?,proof_filename=?,proof_original_name=?,proof_mime=?,updated_at=? WHERE id=?');
        $stmt->execute(array($status,$note,$existing['proof_filename'],$existing['proof_original_name'],$existing['proof_mime'],$now,(int)$existing['id']));
        edu_attach_submission_files((int)$existing['id'], $newAttachmentCount > 0);
    } else {
        $stmt = $db->prepare('INSERT INTO submissions(course_id,store_code,store_name,employee_name,status,note,proof_filename,proof_original_name,proof_mime,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute(array($courseId,$store,$storeName,$employee,$status,$note,'','','',$now,$now));
        edu_attach_submission_files((int)$db->lastInsertId(), false);
    }
    edu_json(array('ok'=>true), 200);
}

if ($route === 'admin-dashboard' && $method === 'GET') {
    edu_require_admin_api();
    $db = edu_db();
    $courses = $db->query('SELECT * FROM courses ORDER BY active DESC, sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    $notices = $db->query('SELECT n.*, (SELECT COUNT(*) FROM notice_confirmations nc WHERE nc.notice_id=n.id) AS confirmed_count, (SELECT MAX(nc.confirmed_at) FROM notice_confirmations nc WHERE nc.notice_id=n.id) AS last_confirmed_at FROM notices n ORDER BY active DESC, priority DESC, updated_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
    $employees = $db->query('SELECT id,store_code,store_name,employee_name,active,created_at,updated_at FROM employees ORDER BY active DESC, store_code, employee_name')->fetchAll(PDO::FETCH_ASSOC);
    $rows = $db->query('SELECT s.*, c.title AS course_title, c.category AS course_category, c.requires_proof FROM submissions s JOIN courses c ON c.id=s.course_id ORDER BY s.updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $stores = edu_stores();
    $storeTotal = count($stores);
    $complete = 0;
    $submissionIds = array();
    foreach ($rows as $row) $submissionIds[] = (int)$row['id'];
    $submissionAttachmentMap = edu_submission_attachments_map($db, $submissionIds);
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id']; $row['course_id'] = (int)$row['course_id']; $row['requires_proof'] = (int)$row['requires_proof'] === 1; $row['proofUrl'] = $row['proof_filename'] !== '' ? '/edu/api.php?route=proof&id=' . (int)$row['id'] : '';
        $row['attachments'] = isset($submissionAttachmentMap[(int)$row['id']]) ? $submissionAttachmentMap[(int)$row['id']] : array();
        if ($row['proofUrl'] !== '' && !count($row['attachments'])) $row['attachments'][] = array('id'=>0,'submission_id'=>(int)$row['id'],'original_name'=>$row['proof_original_name'],'mime'=>$row['proof_mime'],'size'=>0,'url'=>$row['proofUrl']);
        if ($row['status'] === 'completed') $complete++;
    }
    unset($row);
    foreach ($courses as &$course) { $course['id'] = (int)$course['id']; $course['active'] = (int)$course['active']; $course['requires_proof'] = (int)$course['requires_proof']; }
    unset($course);
    $noticeIds = array();
    foreach ($notices as $notice) $noticeIds[] = (int)$notice['id'];
    $noticeAttachmentMap = edu_notice_attachments_map($db, $noticeIds);
    foreach ($notices as &$notice) { $notice['id'] = (int)$notice['id']; $notice['active'] = (int)$notice['active']; $notice['priority'] = (int)$notice['priority']; $notice['confirmed_count'] = (int)$notice['confirmed_count']; $notice['attachments'] = isset($noticeAttachmentMap[(int)$notice['id']]) ? $noticeAttachmentMap[(int)$notice['id']] : array(); }
    unset($notice);
    foreach ($employees as &$employee) { $employee['id'] = (int)$employee['id']; $employee['active'] = (int)$employee['active']; }
    unset($employee);
    edu_json(array('csrf'=>edu_csrf_token(),'stores'=>$stores,'courses'=>$courses,'notices'=>$notices,'employees'=>$employees,'submissions'=>$rows,'metrics'=>array('courseCount'=>count(array_filter($courses,function($c){return (int)$c['active']===1;})),'noticeCount'=>count(array_filter($notices,function($n){return (int)$n['active']===1;})),'employeeCount'=>count(array_filter($employees,function($e){return (int)$e['active']===1;})),'storeCount'=>$storeTotal,'submissionCount'=>count($rows),'completedCount'=>$complete),'adminName'=>edu_admin_name()), 200);
}

if ($route === 'course' && $method === 'POST') {
    edu_require_admin_api();
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || !edu_check_csrf(isset($body['csrf']) ? $body['csrf'] : '')) edu_json(array('error'=>'요청이 만료되었습니다.'), 403);
    $id = isset($body['id']) ? (int)$body['id'] : 0;
    $title = trim(isset($body['title']) ? (string)$body['title'] : '');
    $category = trim(isset($body['category']) ? (string)$body['category'] : '');
    $description = trim(isset($body['description']) ? (string)$body['description'] : '');
    $content = trim(isset($body['content']) ? (string)$body['content'] : '');
    $due = isset($body['due_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $body['due_date']) ? $body['due_date'] : '';
    $active = !empty($body['active']) ? 1 : 0;
    $proof = !empty($body['requires_proof']) ? 1 : 0;
    if ($title === '' || $content === '') edu_json(array('error'=>'교육명과 교육 내용을 입력해 주세요.'), 400);
    $db = edu_db();
    $now = date('c');
    if ($id > 0) {
        $stmt = $db->prepare('UPDATE courses SET title=?,category=?,description=?,content=?,due_date=?,active=?,requires_proof=?,updated_at=? WHERE id=?');
        $stmt->execute(array($title,$category,$description,$content,$due,$active,$proof,$now,$id));
    } else {
        $sort = (int)$db->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM courses')->fetchColumn();
        $stmt = $db->prepare('INSERT INTO courses(title,category,description,content,due_date,active,requires_proof,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute(array($title,$category,$description,$content,$due,$active,$proof,$sort,$now,$now));
    }
    edu_json(array('ok'=>true), 200);
}

if ($route === 'notice' && $method === 'POST') {
    edu_require_admin_api();
    if (!edu_check_csrf(isset($_POST['csrf']) ? $_POST['csrf'] : '')) edu_json(array('error'=>'요청이 만료되었습니다.'), 403);
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $title = trim(isset($_POST['title']) ? (string)$_POST['title'] : '');
    $noticeBody = trim(isset($_POST['body']) ? (string)$_POST['body'] : '');
    $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 0;
    if ($priority < 0) $priority = 0;
    if ($priority > 99) $priority = 99;
    $active = !empty($_POST['active']) ? 1 : 0;
    if ($title === '' || $noticeBody === '') edu_json(array('error'=>'공지 제목과 내용을 입력해 주세요.'), 400);
    $db = edu_db();
    edu_validate_notice_attachment_limit($id);
    $now = date('c');
    if ($id > 0) {
        $stmt = $db->prepare('UPDATE notices SET title=?,body=?,priority=?,active=?,updated_at=? WHERE id=?');
        $stmt->execute(array($title,$noticeBody,$priority,$active,$now,$id));
        edu_attach_notice_files($id);
    } else {
        $stmt = $db->prepare('INSERT INTO notices(title,body,priority,active,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?)');
        $stmt->execute(array($title,$noticeBody,$priority,$active,edu_admin_name(),$now,$now));
        edu_attach_notice_files((int)$db->lastInsertId());
    }
    edu_json(array('ok'=>true), 200);
}

if ($route === 'employee' && $method === 'POST') {
    edu_require_admin_api();
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || !edu_check_csrf(isset($body['csrf']) ? $body['csrf'] : '')) edu_json(array('error'=>'요청이 만료되었습니다.'), 403);
    $id = isset($body['id']) ? (int)$body['id'] : 0;
    $store = isset($body['store']) ? preg_replace('/[^0-9]/', '', $body['store']) : '';
    $storeName = edu_store_name($store);
    $employee = trim(isset($body['employee']) ? (string)$body['employee'] : '');
    $password = isset($body['password']) ? (string)$body['password'] : '';
    $active = !empty($body['active']) ? 1 : 0;
    if (function_exists('mb_substr')) $employee = mb_substr($employee, 0, 40, 'UTF-8'); else $employee = substr($employee, 0, 40);
    if ($storeName === '' || $employee === '') edu_json(array('error'=>'매장과 직원 이름을 입력해 주세요.'), 400);
    if ($id < 1 && !preg_match('/^\d{4}$/', $password)) edu_json(array('error'=>'신규 직원은 4자리 비밀번호가 필요합니다.'), 400);
    if ($password !== '' && !preg_match('/^\d{4}$/', $password)) edu_json(array('error'=>'비밀번호는 4자리 숫자로 입력해 주세요.'), 400);
    $db = edu_db();
    $now = date('c');
    if ($id > 0) {
        if ($password !== '') {
            $hash = function_exists('password_hash') ? password_hash($password, PASSWORD_DEFAULT) : hash('sha256', $password);
            $stmt = $db->prepare('UPDATE employees SET store_code=?,store_name=?,employee_name=?,password_hash=?,active=?,updated_at=? WHERE id=?');
            $stmt->execute(array($store,$storeName,$employee,$hash,$active,$now,$id));
        } else {
            $stmt = $db->prepare('UPDATE employees SET store_code=?,store_name=?,employee_name=?,active=?,updated_at=? WHERE id=?');
            $stmt->execute(array($store,$storeName,$employee,$active,$now,$id));
        }
    } else {
        $hash = function_exists('password_hash') ? password_hash($password, PASSWORD_DEFAULT) : hash('sha256', $password);
        $stmt = $db->prepare('INSERT INTO employees(store_code,store_name,employee_name,password_hash,active,created_at,updated_at) VALUES(?,?,?,?,?,?,?)');
        $stmt->execute(array($store,$storeName,$employee,$hash,$active,$now,$now));
    }
    edu_json(array('ok'=>true), 200);
}

if ($route === 'proof') {
    edu_require_admin_api();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $db = edu_db();
    $stmt = $db->prepare('SELECT * FROM submissions WHERE id=?');
    $stmt->execute(array($id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['proof_filename'] === '') { http_response_code(404); exit; }
    $path = EDU_UPLOAD_DIR . '/' . $row['proof_filename'];
    if (!is_file($path)) { http_response_code(404); exit; }
    header('Content-Type: ' . $row['proof_mime']);
    header('Content-Length: ' . filesize($path));
    header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($row['proof_original_name']));
    readfile($path);
    exit;
}

if ($route === 'submission-file') {
    edu_require_admin_api();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $db = edu_db();
    $stmt = $db->prepare('SELECT * FROM submission_attachments WHERE id=?');
    $stmt->execute(array($id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['filename'] === '') { http_response_code(404); exit; }
    $path = EDU_UPLOAD_DIR . '/' . $row['filename'];
    if (!is_file($path)) { http_response_code(404); exit; }
    header('Content-Type: ' . $row['mime']);
    header('Content-Length: ' . filesize($path));
    header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($row['original_name']));
    readfile($path);
    exit;
}

if ($route === 'notice-file') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $db = edu_db();
    $stmt = $db->prepare('SELECT a.* FROM notice_attachments a JOIN notices n ON n.id=a.notice_id WHERE a.id=? AND n.active=1');
    $stmt->execute(array($id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['filename'] === '') { http_response_code(404); exit; }
    $path = EDU_UPLOAD_DIR . '/' . $row['filename'];
    if (!is_file($path)) { http_response_code(404); exit; }
    header('Content-Type: ' . $row['mime']);
    header('Content-Length: ' . filesize($path));
    header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($row['original_name']));
    readfile($path);
    exit;
}

edu_json(array('error'=>'Not found'), 404);
?>
