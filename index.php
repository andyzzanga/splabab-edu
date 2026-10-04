<?php require_once __DIR__ . '/lib.php'; $stores = edu_stores(); ?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#174d35">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="SPLABAB EDU">
  <title>SPLABAB EDU</title>
  <link rel="manifest" href="/edu/manifest.json?v=114">
  <link rel="apple-touch-icon" sizes="180x180" href="/edu/assets/labab-type-v5-180.png">
  <link rel="stylesheet" href="/edu/styles.css?v=114">
</head>
<body class="edu-user is-loading">
  <main class="edu-shell">
    <div class="app-version" aria-label="앱 버전">SPLABAB EDU v1.1.4</div>
    <header class="hero">
      <img src="/edu/assets/labab-gimbap.png" alt="라밥">
      <div>
        <p>SPLABAB EDU</p>
        <h1>매장 교육</h1>
      </div>
      <button type="button" id="installButton" class="install-link">홈 화면 추가</button>
      <a href="/edu/admin/">관리자</a>
    </header>
    <section id="profilePanel" class="panel">
      <div class="login-intro">
        <p class="eyebrow">직원 확인</p>
        <h2>로그인</h2>
        <p>이름과 4자리 비밀번호를 입력하면 교육과 공지를 바로 확인할 수 있습니다.</p>
      </div>
      <form id="profileForm" class="profile-form">
        <label><span>이름</span><input id="employeeName" maxlength="40" placeholder="예: 홍길동" required autocomplete="username"></label>
        <label><span>비밀번호</span><input id="employeePassword" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="4자리 숫자" required autocomplete="current-password"></label>
        <button type="submit">로그인</button>
        <p id="profileError" class="error" hidden></p>
      </form>
    </section>
    <section id="coursePanel" class="course-panel" hidden>
      <div class="section-head">
        <div><p class="eyebrow" id="profileLabel">교육 목록</p><h2>진행할 교육</h2></div>
        <button id="changeProfile" type="button">변경</button>
      </div>
      <div id="courseList" class="course-list"></div>
    </section>
    <section id="detailPanel" class="detail-panel" hidden></section>
  </main>
  <div id="installDialog" class="install-dialog" role="dialog" aria-modal="true" aria-labelledby="installTitle" hidden>
    <div class="install-sheet">
      <button type="button" id="installClose" class="install-close" aria-label="닫기" title="닫기">×</button>
      <img src="/edu/assets/labab-type-v5-192.png" alt="" class="install-app-icon">
      <h2 id="installTitle">SPLABAB EDU</h2>
      <p id="installHelp" class="install-help">홈 화면에 추가하면 직원 교육을 앱처럼 바로 열 수 있습니다.</p>
      <div class="install-actions">
        <button type="button" id="installCancel" class="install-cancel">취소</button>
        <button type="button" id="installConfirm" class="install-confirm">설치</button>
      </div>
    </div>
  </div>
  <script src="/edu/app.js?v=114"></script>
</body>
</html>
