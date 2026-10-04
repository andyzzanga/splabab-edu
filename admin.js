var A={csrf:'',courses:[],notices:[],employees:[],stores:[],submissions:[],metrics:{}};
var MAX_NOTICE_ATTACHMENTS=10;
var noticePreviewUrls=[];
function q(id){return document.getElementById(id)}
function esc(v){return String(v||'').replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function shortDate(v){if(!v)return'-';var d=new Date(v);return isNaN(d.getTime())?v:(d.getMonth()+1)+'. '+d.getDate()+'. '+String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0')}
function fileKind(file){
  var mime=String(file.mime||file.type||''), name=String(file.original_name||file.name||'');
  if(mime.indexOf('image/')===0)return'image';
  if(mime.indexOf('video/')===0)return'video';
  if(/\\.(jpe?g|png|gif|webp|bmp|heic)$/i.test(name))return'image';
  if(/\\.(mp4|webm|mov|m4v)$/i.test(name))return'video';
  return'file';
}
function fileLabel(file){
  var mime=String(file.mime||file.type||''), name=String(file.original_name||file.name||'첨부파일'), ext=(name.split('.').pop()||'').toUpperCase();
  if(mime.indexOf('application/pdf')===0)return'PDF';
  if(mime.indexOf('image/')===0)return'이미지';
  if(mime.indexOf('video/')===0)return'동영상';
  return ext&&ext!==name.toUpperCase()?ext:'파일';
}
function renderAttachmentTile(file,url,local){
  var name=esc(file.original_name||file.name||'첨부파일'), kind=fileKind(file), label=fileLabel(file), href=url||file.url||'#', safeUrl=esc(href);
  if(kind==='image')return'<a class="notice-file-card" href="'+safeUrl+'" target="_blank" rel="noopener"><img src="'+safeUrl+'" alt=""><span>'+name+'</span><b>'+label+'</b></a>';
  if(kind==='video')return'<a class="notice-file-card is-video" href="'+safeUrl+'" target="_blank" rel="noopener"><video src="'+safeUrl+'" preload="metadata" muted playsinline></video><span>'+name+'</span><b>동영상</b></a>';
  return'<a class="notice-file-card is-doc" href="'+safeUrl+'" '+(local?'':'target="_blank" rel="noopener"')+'><i>'+esc(label)+'</i><span>'+name+'</span><b>열기</b></a>';
}
function currentNotice(){
  return A.notices.filter(function(row){return row.id===Number(q('noticeId').value||0)})[0];
}
function selectedNoticeFiles(){
  return Array.prototype.slice.call(q('noticeFiles').files||[]);
}
function existingNoticeCount(){
  var current=currentNotice();
  return current&&current.attachments?current.attachments.length:0;
}
function updateNoticeAttachmentHelp(){
  var existing=existingNoticeCount(), selected=selectedNoticeFiles().length, total=existing+selected, over=total>MAX_NOTICE_ATTACHMENTS;
  q('noticeAttachmentHelp').textContent='기존 '+existing+'개 + 새로 선택 '+selected+'개 = 총 '+total+'개 / 최대 '+MAX_NOTICE_ATTACHMENTS+'개'+(over?' · 초과된 파일은 저장할 수 없습니다.':'');
  q('noticeAttachmentHelp').className='notice-help'+(over?' is-error':'');
}
function clearNoticePreviewUrls(){
  noticePreviewUrls.forEach(function(url){URL.revokeObjectURL(url)});
  noticePreviewUrls=[];
}
function load(){
  fetch('/edu/api.php?route=admin-dashboard',{credentials:'same-origin'}).then(function(r){if(r.status===401)location.href='/edu/login.php';return r.json()}).then(function(data){A.csrf=data.csrf;A.courses=data.courses||[];A.notices=data.notices||[];A.employees=data.employees||[];A.stores=data.stores||[];A.submissions=data.submissions||[];A.metrics=data.metrics||{};renderAll();});
}
function renderAll(){renderMetrics();fillFilters();renderCourseList();renderNoticeList();renderEmployeeList();renderTable();}
function renderMetrics(){
  q('metrics').innerHTML='<div><strong>'+A.metrics.courseCount+'</strong><span>진행 교육</span></div><div><strong>'+A.metrics.noticeCount+'</strong><span>노출 공지</span></div><div><strong>'+A.metrics.employeeCount+'</strong><span>직원 계정</span></div><div><strong>'+A.metrics.completedCount+'</strong><span>완료</span></div>';
}
function fillFilters(){
  var s=q('filterStore'), c=q('filterCourse'), es=q('employeeStore'), sv=s.value, cv=c.value, ev=es.value;
  s.innerHTML='<option value="">전체 매장</option>'+A.stores.map(function(row){return'<option value="'+esc(row.code)+'">'+esc(row.name)+'</option>'}).join('');
  es.innerHTML='<option value="">매장 선택</option>'+A.stores.map(function(row){return'<option value="'+esc(row.code)+'">'+esc(row.name)+'</option>'}).join('');
  c.innerHTML='<option value="">전체 교육</option>'+A.courses.map(function(row){return'<option value="'+row.id+'">'+esc(row.title)+(Number(row.active)?'':' (숨김)')+'</option>'}).join('');
  s.value=sv; c.value=cv; es.value=ev;
}
function renderCourseList(){
  q('adminCourseList').innerHTML='<h3>등록된 교육</h3>'+A.courses.map(function(c){return'<button type="button" onclick="editCourse('+c.id+')"><strong>'+esc(c.title)+'</strong><span>'+esc(c.category||'분류 없음')+' · '+(Number(c.active)?'노출':'숨김')+(Number(c.requires_proof)?' · 첨부 필요':'')+'</span></button>'}).join('');
}
function editCourse(id){
  var c=A.courses.filter(function(row){return row.id===id})[0]; if(!c)return;
  q('courseId').value=c.id; q('courseTitle').value=c.title; q('courseCategory').value=c.category; q('courseDescription').value=c.description; q('courseContent').value=c.content; q('courseDue').value=c.due_date; q('courseProof').checked=Number(c.requires_proof)===1; q('courseActive').checked=Number(c.active)===1;
}
function clearForm(){q('courseId').value='';q('courseForm').reset();q('courseActive').checked=true;}
function saveCourse(e){
  e.preventDefault();
  var body={csrf:A.csrf,id:Number(q('courseId').value||0),title:q('courseTitle').value.trim(),category:q('courseCategory').value.trim(),description:q('courseDescription').value.trim(),content:q('courseContent').value.trim(),due_date:q('courseDue').value,requires_proof:q('courseProof').checked?1:0,active:q('courseActive').checked?1:0};
  fetch('/edu/api.php?route=course',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(function(r){return r.json().then(function(data){if(!r.ok)throw new Error(data.error||'저장하지 못했습니다.');return data})}).then(function(){clearForm();load();}).catch(function(err){alert(err.message)});
}
function renderNoticeList(){
  q('adminNoticeList').innerHTML='<h3>등록된 공지</h3>'+A.notices.map(function(n){var count=(n.attachments||[]).length;return'<button type="button" onclick="editNotice('+n.id+')"><strong>'+esc(n.title)+'</strong><span>'+((Number(n.active)?'노출':'숨김'))+' · 우선순위 '+Number(n.priority)+' · 확인 '+Number(n.confirmed_count)+'명'+(count?' · 첨부 '+count+'개':'')+(n.last_confirmed_at?' · 최근 '+shortDate(n.last_confirmed_at):'')+'</span></button>'}).join('')+(A.notices.length?'':'<p class="empty">등록된 공지가 없습니다.</p>');
}
function editNotice(id){
  var n=A.notices.filter(function(row){return row.id===id})[0]; if(!n)return;
  q('noticeId').value=n.id; q('noticeTitle').value=n.title; q('noticeBody').value=n.body; q('noticePriority').value=n.priority; q('noticeActive').checked=Number(n.active)===1;
  q('noticeFiles').value='';
  renderSelectedNoticeFiles();
  renderNoticeFiles(n.attachments||[]);
}
function renderNoticeFiles(files){
  var box=q('noticeExistingFiles');
  if(!files.length){box.hidden=true;box.innerHTML='';updateNoticeAttachmentHelp();return;}
  box.hidden=false;
  box.innerHTML='<p>기존 첨부 '+files.length+'개</p><div class="notice-file-grid">'+files.map(function(file){return renderAttachmentTile(file,file.url,false)}).join('')+'</div>';
  updateNoticeAttachmentHelp();
}
function renderSelectedNoticeFiles(){
  var box=q('noticeSelectedFiles'), files=selectedNoticeFiles();
  clearNoticePreviewUrls();
  if(!files.length){box.hidden=true;box.innerHTML='';updateNoticeAttachmentHelp();return;}
  box.hidden=false;
  box.innerHTML='<p>새로 선택한 첨부 '+files.length+'개</p><div class="notice-file-grid">'+files.map(function(file){var url=URL.createObjectURL(file);noticePreviewUrls.push(url);return renderAttachmentTile(file,url,true)}).join('')+'</div>';
  updateNoticeAttachmentHelp();
}
function clearNoticeForm(){q('noticeId').value='';q('noticeForm').reset();q('noticePriority').value=10;q('noticeActive').checked=true;renderSelectedNoticeFiles();renderNoticeFiles([]);}
function saveNotice(e){
  e.preventDefault();
  var existingCount=existingNoticeCount();
  var newCount=selectedNoticeFiles().length;
  if(existingCount+newCount>MAX_NOTICE_ATTACHMENTS){alert('공지 첨부는 기존 파일을 포함해 최대 10개까지 등록할 수 있습니다.');return;}
  var fd=new FormData();
  fd.append('csrf',A.csrf);
  fd.append('id',Number(q('noticeId').value||0));
  fd.append('title',q('noticeTitle').value.trim());
  fd.append('body',q('noticeBody').value.trim());
  fd.append('priority',Number(q('noticePriority').value||0));
  fd.append('active',q('noticeActive').checked?1:0);
  selectedNoticeFiles().forEach(function(file){fd.append('notice_files[]',file,file.name)});
  fetch('/edu/api.php?route=notice',{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json().then(function(data){if(!r.ok)throw new Error(data.error||'공지 저장에 실패했습니다.');return data})}).then(function(){clearNoticeForm();load();}).catch(function(err){alert(err.message)});
}
function renderEmployeeList(){
  q('adminEmployeeList').innerHTML='<h3>직원 계정</h3>'+A.employees.map(function(e){return'<button type="button" onclick="editEmployee('+e.id+')"><strong>'+esc(e.employee_name)+'</strong><span>'+esc(e.store_name)+' · '+(Number(e.active)?'사용':'중지')+'</span></button>'}).join('')+(A.employees.length?'':'<p class="empty">등록된 직원 계정이 없습니다.</p>');
}
function editEmployee(id){
  var e=A.employees.filter(function(row){return row.id===id})[0]; if(!e)return;
  q('employeeId').value=e.id; q('employeeNameAdmin').value=e.employee_name; q('employeeStore').value=e.store_code; q('employeePasswordAdmin').value=''; q('employeeActive').checked=Number(e.active)===1;
}
function clearEmployeeForm(){q('employeeId').value='';q('employeeForm').reset();q('employeeActive').checked=true;}
function saveEmployee(e){
  e.preventDefault();
  var body={csrf:A.csrf,id:Number(q('employeeId').value||0),store:q('employeeStore').value,employee:q('employeeNameAdmin').value.trim(),password:q('employeePasswordAdmin').value.trim(),active:q('employeeActive').checked?1:0};
  fetch('/edu/api.php?route=employee',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(function(r){return r.json().then(function(data){if(!r.ok)throw new Error(data.error||'직원 계정을 저장하지 못했습니다.');return data})}).then(function(){clearEmployeeForm();load();}).catch(function(err){alert(err.message)});
}
function filteredRows(){
  var store=q('filterStore').value, course=q('filterCourse').value, status=q('filterStatus').value, term=q('filterSearch').value.trim().toLowerCase();
  return A.submissions.filter(function(r){return(!store||r.store_code===store)&&(!course||String(r.course_id)===course)&&(!status||r.status===status)&&(!term||r.employee_name.toLowerCase().indexOf(term)!==-1)});
}
function renderTable(){
  var rows=filteredRows();
  q('statusTable').innerHTML='<table><thead><tr><th>상태</th><th>매장</th><th>직원</th><th>교육</th><th>메모</th><th>첨부</th><th>갱신</th></tr></thead><tbody>'+rows.map(function(r){var files=r.attachments||[], fileHtml=files.length?'<div class="submission-links">'+files.map(function(file,i){return'<a href="'+esc(file.url)+'" target="_blank" rel="noopener">'+(i===0?'보기':'첨부 '+(i+1))+'</a>'}).join('')+(files.length>1?'<span>총 '+files.length+'개</span>':'')+'</div>':'-';return'<tr><td><span class="badge '+(r.status==='completed'?'done':'viewed')+'">'+(r.status==='completed'?'완료':'확인 중')+'</span></td><td>'+esc(r.store_name)+'</td><td>'+esc(r.employee_name)+'</td><td>'+esc(r.course_title)+'</td><td>'+esc(r.note||'-')+'</td><td>'+fileHtml+'</td><td>'+shortDate(r.updated_at)+'</td></tr>'}).join('')+'</tbody></table>'+(rows.length?'':'<p class="empty">조건에 맞는 제출 기록이 없습니다.</p>');
}
['filterStore','filterCourse','filterStatus','filterSearch'].forEach(function(id){q(id).oninput=renderTable;q(id).onchange=renderTable});
q('courseForm').onsubmit=saveCourse;
q('newCourse').onclick=clearForm;
q('noticeForm').onsubmit=saveNotice;
q('noticeFiles').onchange=renderSelectedNoticeFiles;
q('newNotice').onclick=clearNoticeForm;
q('employeeForm').onsubmit=saveEmployee;
q('newEmployee').onclick=clearEmployeeForm;
load();
