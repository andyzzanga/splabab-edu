var EDU = { csrf: '', stores: [], courses: [], notices: [], store: '', storeName: '', employee: '', selected: null };
var MAX_SUBMISSION_ATTACHMENTS=10;
var submissionPreviewUrls=[];
function $(id){return document.getElementById(id)}
function esc(v){return String(v||'').replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function statusLabel(status){return status==='completed'?'완료':(status==='viewed'?'확인 중':'미진행')}
function statusClass(status){return status==='completed'?'done':(status==='viewed'?'viewed':'new')}
function saveProfile(){localStorage.setItem('eduProfile',JSON.stringify({store:EDU.store,employee:EDU.employee}))}
function applyEmployee(employee){
  if(!employee)return false;
  EDU.store=employee.store_code||'';
  EDU.storeName=employee.store_name||'';
  EDU.employee=employee.employee_name||'';
  if(!EDU.employee)return false;
  saveProfile();
  $('employeeName').value=EDU.employee;
  $('employeePassword').value='';
  $('profileLabel').textContent=(EDU.storeName?EDU.storeName+' · ':'')+EDU.employee;
  return true;
}
function setLoginState(loggedIn){
  document.body.classList.remove('is-loading');
  document.body.classList.toggle('is-logged-in',!!loggedIn);
  document.body.classList.toggle('is-logged-out',!loggedIn);
  $('profilePanel').hidden=!!loggedIn;
  $('coursePanel').hidden=!loggedIn;
  if(!loggedIn)$('detailPanel').hidden=true;
}
function restoreProfile(){
  try{var p=JSON.parse(localStorage.getItem('eduProfile')||'{}'); if(p.employee)$('employeeName').value=p.employee;}catch(e){}
}
function loadConfig(){
  return fetch('/edu/api.php?route=config',{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(data){EDU.csrf=data.csrf;EDU.stores=data.stores||[];restoreProfile();});
}
function restoreSession(){
  return fetch('/edu/api.php?route=employee-me',{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(data){
    if(data.employee && applyEmployee(data.employee))return loadCourses();
    setLoginState(false);
  }).catch(function(){setLoginState(false);});
}
function selectedStoreName(){
  return EDU.storeName || '';
}
function noticeMediaHtml(files){
  files=files||[];
  if(!files.length)return'';
  return '<div class="notice-media">'+files.map(function(file){
    var name=esc(file.original_name||'첨부파일'), url=esc(file.url||'#'), mime=String(file.mime||'');
    if(mime.indexOf('image/')===0)return'<a href="'+url+'" target="_blank" rel="noopener"><img src="'+url+'" alt="'+name+'"></a>';
    if(mime.indexOf('video/')===0)return'<video controls preload="metadata" src="'+url+'"></video><a class="notice-file" href="'+url+'" target="_blank" rel="noopener"><span>'+name+'</span><b>열기</b></a>';
    return'<a class="notice-file" href="'+url+'" target="_blank" rel="noopener"><span>'+name+'</span><b>열기</b></a>';
  }).join('')+'</div>';
}
function selectedSubmissionFiles(){
  return Array.prototype.slice.call($('submitProof').files||[]);
}
function clearSubmissionPreviewUrls(){
  submissionPreviewUrls.forEach(function(url){URL.revokeObjectURL(url)});
  submissionPreviewUrls=[];
}
function fileKind(file){
  var mime=String(file.type||file.mime||''), name=String(file.name||file.original_name||'');
  if(mime.indexOf('image/')===0)return'image';
  if(mime.indexOf('video/')===0)return'video';
  if(/\.(jpe?g|png|gif|webp)$/i.test(name))return'image';
  if(/\.(mp4|webm|mov|m4v)$/i.test(name))return'video';
  return'file';
}
function renderSubmissionPreview(){
  var files=selectedSubmissionFiles(), box=$('submitPreview'), help=$('submitAttachmentHelp'), over=files.length>MAX_SUBMISSION_ATTACHMENTS;
  clearSubmissionPreviewUrls();
  help.textContent=files.length+' / '+MAX_SUBMISSION_ATTACHMENTS+'개 선택'+(over?' · 최대 개수를 초과했습니다.':'');
  help.className='attachment-help'+(over?' is-error':'');
  if(!files.length){box.hidden=true;box.innerHTML='';return;}
  box.hidden=false;
  box.innerHTML=files.map(function(file){
    var url=URL.createObjectURL(file), name=esc(file.name), kind=fileKind(file);
    submissionPreviewUrls.push(url);
    if(kind==='image')return'<div class="submit-file-card"><img src="'+esc(url)+'" alt=""><span>'+name+'</span><b>이미지</b></div>';
    if(kind==='video')return'<div class="submit-file-card is-video"><video src="'+esc(url)+'" preload="metadata" muted playsinline></video><span>'+name+'</span><b>동영상</b></div>';
    return'<div class="submit-file-card is-doc"><i>파일</i><span>'+name+'</span><b>확인</b></div>';
  }).join('');
}
function loadCourses(){
  return fetch('/edu/api.php?route=courses',{credentials:'same-origin'}).then(function(r){return r.json().then(function(data){if(!r.ok)throw new Error(data.error||'교육 목록을 불러오지 못했습니다.');return data})}).then(function(data){EDU.courses=data.courses||[];EDU.notices=data.notices||[];setLoginState(true);renderCourses();}).catch(function(err){$('profileError').textContent=err.message;$('profileError').hidden=false;setLoginState(false);});
}
function startProfile(event){
  event.preventDefault();
  EDU.employee=$('employeeName').value.trim();
  if(!EDU.employee)return;
  $('profileError').hidden=true;
  fetch('/edu/api.php?route=employee-login',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:EDU.csrf,employee:EDU.employee,password:$('employeePassword').value})}).then(function(r){return r.json().then(function(body){if(!r.ok)throw new Error(body.error||'직원 정보를 확인하지 못했습니다.');return body})}).then(function(body){
    applyEmployee(body.employee);
    setLoginState(true);
    $('detailPanel').hidden=true;
    loadCourses();
  }).catch(function(err){$('profileError').textContent=err.message;$('profileError').hidden=false;});
}
function renderCourses(){
  var pending=EDU.notices.filter(function(n){return !n.confirmed});
  var confirmed=EDU.notices.filter(function(n){return n.confirmed});
  var noticeHtml='';
  if(pending.length){
    noticeHtml='<section class="notice-priority"><p class="eyebrow">본사 공지</p>'+pending.map(function(n){return '<article class="notice-card urgent"><span>미확인</span><h3>'+esc(n.title)+'</h3><p>'+esc(n.body).replace(/\n/g,'<br>')+'</p>'+noticeMediaHtml(n.attachments)+'<button type="button" onclick="confirmNotice('+n.id+')">확인했습니다</button></article>'}).join('')+'</section>';
  }
  if(confirmed.length){
    noticeHtml+='<details class="notice-confirmed"><summary>확인한 공지 '+confirmed.length+'건</summary>'+confirmed.map(function(n){return '<article><strong>'+esc(n.title)+'</strong><p>'+esc(n.body).replace(/\n/g,'<br>')+'</p>'+noticeMediaHtml(n.attachments)+'</article>'}).join('')+'</details>';
  }
  var courseHtml=EDU.courses.map(function(c){
    return '<button class="course-card" type="button" onclick="openCourse('+c.id+')"><span class="badge '+statusClass(c.status)+'">'+statusLabel(c.status)+'</span><strong>'+esc(c.title)+'</strong><small>'+esc(c.category||'교육')+(c.dueDate?' · '+esc(c.dueDate)+'까지':'')+'</small><p>'+esc(c.description||'교육 내용을 확인하고 완료를 제출하세요.')+'</p></button>';
  }).join('') || '<p class="empty">등록된 교육 과정이 없습니다.</p>';
  $('courseList').innerHTML=noticeHtml+'<div class="course-stack">'+courseHtml+'</div>';
}
function confirmNotice(id){
  fetch('/edu/api.php?route=notice-confirm',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:EDU.csrf,notice_id:id})}).then(function(r){return r.json().then(function(body){if(!r.ok)throw new Error(body.error||'공지 확인을 저장하지 못했습니다.');return body})}).then(loadCourses).catch(function(err){alert(err.message)});
}
function openCourse(id){
  EDU.selected=EDU.courses.filter(function(c){return c.id===id})[0];
  if(!EDU.selected)return;
  $('detailPanel').hidden=false;
  var proofNote=EDU.selected.requiresProof?'필수 제출 · 최대 10개':'선택 제출 · 최대 10개';
  $('detailPanel').innerHTML='<button class="back" type="button" onclick="closeCourse()">목록으로</button><article class="course-detail"><span class="badge '+statusClass(EDU.selected.status)+'">'+statusLabel(EDU.selected.status)+'</span><h2>'+esc(EDU.selected.title)+'</h2><p class="course-desc">'+esc(EDU.selected.description)+'</p><div class="course-content">'+esc(EDU.selected.content).replace(/\n/g,'<br>')+'</div><form id="submitForm" class="submit-form"><label>메모<textarea id="submitNote" rows="4" placeholder="확인 내용이나 교육 결과를 남겨 주세요.">'+esc(EDU.selected.note)+'</textarea></label><section class="upload-panel" aria-labelledby="uploadTitle"><div class="upload-head"><div><p class="eyebrow">사진·동영상 업로드</p><h3 id="uploadTitle">교육 사진 또는 영상을 첨부하세요</h3></div><strong>'+proofNote+'</strong></div><label class="upload-drop" for="submitProof"><input id="submitProof" type="file" accept="image/*,video/mp4,video/webm,video/quicktime" multiple><span>파일 선택</span><small>사진·동영상 파일을 눌러 선택해 주세요. 여러 개를 한 번에 선택할 수 있습니다.</small></label><p id="submitAttachmentHelp" class="attachment-help">0 / 10개 선택</p><div id="submitPreview" class="submit-file-grid" hidden></div></section><button type="submit">교육 완료 제출</button><button type="button" class="secondary" onclick="markViewed()">나중에 완료하기</button><p id="submitError" class="error" hidden></p></form></article>';
  $('submitForm').onsubmit=function(e){e.preventDefault();submitCourse('completed')};
  $('submitProof').onchange=renderSubmissionPreview;
  renderSubmissionPreview();
  $('detailPanel').scrollIntoView({behavior:'smooth',block:'start'});
}
function closeCourse(){ clearSubmissionPreviewUrls(); $('detailPanel').hidden=true; EDU.selected=null; }
function markViewed(){ submitCourse('viewed'); }
function submitCourse(status){
  var fd=new FormData();
  fd.append('csrf',EDU.csrf); fd.append('course_id',EDU.selected.id); fd.append('status',status); fd.append('note',$('submitNote').value.trim());
  var files=selectedSubmissionFiles();
  if(files.length>MAX_SUBMISSION_ATTACHMENTS){$('submitError').textContent='첨부는 최대 10개까지 선택할 수 있습니다.';$('submitError').hidden=false;return;}
  files.forEach(function(file){fd.append('proofs[]',file,file.name)});
  var button=$('submitForm').querySelector('button[type="submit"]'); button.disabled=true; button.textContent='제출 중...'; $('submitError').hidden=true;
  fetch('/edu/api.php?route=submission',{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json().then(function(body){if(!r.ok)throw new Error(body.error||'제출하지 못했습니다.');return body})}).then(function(){return loadCourses()}).then(function(){button.disabled=false;button.textContent='교육 완료 제출';closeCourse();}).catch(function(err){button.disabled=false;button.textContent='교육 완료 제출';$('submitError').textContent=err.message;$('submitError').hidden=false;});
}
$('profileForm').onsubmit=startProfile;
$('changeProfile').onclick=function(){ setLoginState(false); };
loadConfig().then(restoreSession).catch(function(){setLoginState(false);});
if('serviceWorker' in navigator) navigator.serviceWorker.register('/edu/sw.js?v=114');
var installPrompt=null;
var installButton=$('installButton');
var installDialog=$('installDialog');
var installConfirm=$('installConfirm');
var installHelp=$('installHelp');
var standalone=window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
function isIosDevice(){return /iphone|ipad|ipod/i.test(navigator.userAgent)}
function updateInstallButton(){if(installButton)installButton.hidden=standalone;}
function openInstallDialog(){
  if(!installDialog)return;
  if(isIosDevice()){
    installHelp.textContent='Safari 공유 버튼을 누른 뒤 홈 화면에 추가를 선택해 주세요.';
    installConfirm.textContent='확인';
  }else if(installPrompt){
    installHelp.textContent='설치를 누르면 휴대폰 홈 화면에 SPLABAB EDU 앱이 추가됩니다.';
    installConfirm.textContent='설치';
  }else{
    installHelp.textContent='브라우저 오른쪽 위 메뉴에서 앱 설치 또는 홈 화면에 추가를 눌러 주세요.';
    installConfirm.textContent='확인';
  }
  installDialog.hidden=false;
}
window.addEventListener('beforeinstallprompt',function(event){event.preventDefault();installPrompt=event;updateInstallButton();});
if(installButton)installButton.onclick=openInstallDialog;
if($('installClose'))$('installClose').onclick=function(){installDialog.hidden=true};
if($('installCancel'))$('installCancel').onclick=function(){installDialog.hidden=true};
if(installDialog)installDialog.onclick=function(event){if(event.target===installDialog)installDialog.hidden=true};
if(installConfirm)installConfirm.onclick=function(){
  if(installPrompt){
    installDialog.hidden=true;
    installPrompt.prompt();
    installPrompt.userChoice.then(function(){installPrompt=null;updateInstallButton();});
    return;
  }
  installDialog.hidden=true;
};
window.addEventListener('appinstalled',function(){standalone=true;if(installDialog)installDialog.hidden=true;updateInstallButton();});
updateInstallButton();
