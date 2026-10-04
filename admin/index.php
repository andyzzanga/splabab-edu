<?php require_once dirname(__DIR__) . '/lib.php'; edu_require_admin_page(); ?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#1f2f28">
  <title>SPLABAB EDU 관리자</title>
  <link rel="stylesheet" href="/edu/styles.css?v=114">
</head>
<body class="edu-admin">
  <header class="admin-header">
    <div class="brand"><img src="/edu/assets/labab-gimbap.png" alt=""><div><p>SPLABAB EDU · <?php echo edu_h(edu_admin_name()); ?></p><h1>교육 관리 대시보드</h1></div></div>
    <a href="/edu/">직원 화면</a>
  </header>
  <main class="admin-main">
    <section id="metrics" class="metrics"></section>
    <section class="admin-grid">
      <aside class="course-editor">
        <h2>교육 과정 등록</h2>
        <form id="courseForm">
          <input type="hidden" id="courseId">
          <label>교육명<input id="courseTitle" required></label>
          <label>분류<input id="courseCategory" placeholder="예: 필수 교육"></label>
          <label>요약<textarea id="courseDescription" rows="3"></textarea></label>
          <label>교육 내용<textarea id="courseContent" rows="8" required></textarea></label>
          <label>마감일<input id="courseDue" type="date"></label>
          <label class="check"><input id="courseProof" type="checkbox"> 완료 첨부 필요</label>
          <label class="check"><input id="courseActive" type="checkbox" checked> 직원 화면에 노출</label>
          <button type="submit">저장</button>
          <button id="newCourse" type="button">새 교육 작성</button>
        </form>
        <div id="adminCourseList" class="admin-course-list"></div>
        <section class="notice-editor">
          <h2>본사 공지</h2>
          <form id="noticeForm">
            <input type="hidden" id="noticeId">
            <label>공지 제목<input id="noticeTitle" required></label>
            <label>공지 내용<textarea id="noticeBody" rows="6" required></textarea></label>
            <label>첨부파일<input id="noticeFiles" type="file" multiple accept="image/*,video/mp4,video/webm,video/quicktime,application/pdf,text/plain,text/csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx"></label>
            <p class="notice-help" id="noticeAttachmentHelp">사진, 동영상, PDF, 문서 파일을 기존 첨부 포함 최대 10개까지 등록할 수 있습니다.</p>
            <div id="noticeSelectedFiles" class="notice-files" hidden></div>
            <div id="noticeExistingFiles" class="notice-files" hidden></div>
            <label>우선순위<input id="noticePriority" type="number" min="0" max="99" value="10"></label>
            <label class="check"><input id="noticeActive" type="checkbox" checked> 직원 화면에 노출</label>
            <button type="submit">공지 저장</button>
            <button id="newNotice" type="button">새 공지 작성</button>
          </form>
          <div id="adminNoticeList" class="admin-notice-list"></div>
        </section>
        <section class="employee-editor">
          <h2>직원 계정</h2>
          <form id="employeeForm">
            <input type="hidden" id="employeeId">
            <label>매장<select id="employeeStore" required><option value="">매장 선택</option></select></label>
            <label>직원명<input id="employeeNameAdmin" required></label>
            <label>4자리 비밀번호<input id="employeePasswordAdmin" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="신규 필수 · 수정 시 변경할 때만 입력"></label>
            <label class="check"><input id="employeeActive" type="checkbox" checked> 로그인 허용</label>
            <button type="submit">직원 저장</button>
            <button id="newEmployee" type="button">새 직원 작성</button>
          </form>
          <div id="adminEmployeeList" class="admin-employee-list"></div>
        </section>
      </aside>
      <section class="status-board">
        <div class="filters">
          <select id="filterStore"><option value="">전체 매장</option></select>
          <select id="filterCourse"><option value="">전체 교육</option></select>
          <select id="filterStatus"><option value="">전체 상태</option><option value="completed">완료</option><option value="viewed">확인 중</option></select>
          <input id="filterSearch" type="search" placeholder="직원명 검색">
        </div>
        <div id="statusTable" class="status-table"></div>
      </section>
    </section>
  </main>
  <script src="/edu/admin.js?v=114"></script>
</body>
</html>
