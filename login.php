<?php
require_once __DIR__ . '/lib.php';
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$error = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account = edu_admin_account(isset($_POST['password']) ? $_POST['password'] : '');
    if ($account) {
        $_SESSION['edu_admin'] = true;
        $_SESSION['edu_admin_name'] = $account['name'];
        $_SESSION['edu_admin_role'] = $account['role'];
        header('Location: /edu/admin/');
        exit;
    }
    $error = true;
}
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SPLABAB EDU 로그인</title><link rel="stylesheet" href="/edu/styles.css?v=1"></head>
<body class="login-page"><main class="login-box"><img src="/edu/assets/labab-gimbap.png" alt="라밥"><p>SPLABAB EDU</p><h1>관리자 로그인</h1><?php if ($error): ?><strong class="error">비밀번호를 확인하세요.</strong><?php endif; ?><form method="post"><input type="password" name="password" inputmode="numeric" maxlength="4" placeholder="관리자 비밀번호" required autofocus><button>로그인</button></form></main></body></html>
