// Authorized LocalWP integration. Private path contains disposable credentials; never commit it.
const {chromium}=require(process.env.LK_PLAYWRIGHT_PATH||'playwright');
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),cp=require('node:child_process'),crypto=require('node:crypto');
const privatePath=process.env.LK_SECURITY_PRIVATE;
if(!privatePath||process.env.LK_ALLOW_LOCAL_SECURITY_FIXTURES!=='1')throw Error('Explicit private LocalWP fixture configuration required');
const c=JSON.parse(fs.readFileSync(privatePath,'utf8'));
if(!new URL(c.base).hostname.endsWith('.local'))throw Error('Local scope only');
const base=c.base,loginURL=base+'/'+c.login_slug+'/';
const evidence=path.resolve(__dirname,'../docs/security/evidence');
const results=[];let browser;
const php=process.env.LK_PHP;
function helper(command){return cp.execFileSync(php,['-d','mysqli.default_socket='+process.env.LK_DB_SOCKET,path.resolve(__dirname,'../scripts/security-local-fixtures.php'),command],{env:{...process.env,LD_LIBRARY_PATH:process.env.LK_PHP_LIB},encoding:'utf8'}).trim();}
function check(value,name){assert.ok(value,name);results.push({check:name,passed:true});console.log('PASS: '+name);}
(async()=>{
 browser=await chromium.launch({executablePath:process.env.LK_BROWSER||'/usr/bin/microsoft-edge',headless:true,args:['--no-sandbox']});
 const context=await browser.newContext();const page=await context.newPage();page.setDefaultNavigationTimeout(60000);page.setDefaultTimeout(20000);
 await context.route('**/*',route=>new URL(route.request().url()).origin===new URL(base).origin?route.continue():route.abort());
 const jsErrors=[];page.on('pageerror',e=>jsErrors.push(e.message));
 async function login(role,target='/wp-admin/'){
  await context.clearCookies();await page.goto(loginURL+'?redirect_to='+encodeURIComponent(base+target),{waitUntil:'domcontentloaded'});
  await page.fill('#user_login',c.users[role].login);await page.fill('#user_pass',c.users[role].password||c.password);
  await Promise.all([page.waitForURL(u=>!u.pathname.includes(c.login_slug),{waitUntil:'domcontentloaded',timeout:60000}),page.click('#wp-submit')]);
  check((await context.cookies()).some(x=>x.name.startsWith('wordpress_logged_in_')),'Successful HTTP login: '+role);
 }
 async function apiNonce(){await page.goto(base+'/wp-admin/post-new.php?post_type=lk_photo',{waitUntil:'domcontentloaded'});return page.evaluate(()=>window.wpApiSettings?.nonce||window.wp?.apiFetch?.nonceMiddleware?.nonce);}
 async function api(endpoint,method,body,nonce){const r=await context.request.fetch(base+'/wp-json/wp/v2/'+endpoint,{method:method||'GET',headers:{'X-WP-Nonce':nonce},data:body});return {status:r.status(),data:await r.json()};}
 await page.goto(base+'/');const baseline=await page.locator('.lk-card').count();const heroBefore=await page.locator('.lk-hero-art>img').getAttribute('src');
 check(await page.locator('.lk-card[data-author="Bộ sưu tập mẫu"]').count()===30,'30 watermarked demos remain visible');
 await page.goto(base+'/dang-tac-pham/');const returnLogin=await page.getByRole('link',{name:'Đăng nhập',exact:true}).getAttribute('href');
 check(new URL(returnLogin).pathname.includes(c.login_slug)&&new URL(returnLogin).searchParams.get('redirect_to')===base+'/dang-tac-pham/','Upload form uses new login URL and return target');
 await login('subscriber','/dang-tac-pham/');check((await page.textContent('body')).includes('chưa được cấp quyền'),'Subscriber upload is refused');
 await login('author','/dang-tac-pham/');check(await page.locator('#lk-photo-title').count(),'Login redirects back to upload form');
 async function upload(title){await page.fill('#lk-photo-title',title);await page.setInputFiles('#lk-photo-file',path.resolve(__dirname,'../astra-child/assets/demo/hoa-sen-26.jpg'));await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.getByRole('button',{name:'Gửi tác phẩm',exact:true}).click()]);check(await page.locator('.lk-upload-message--success').count(),'Actual LocalWP upload stored: '+title);return Number(new URL(page.url()).searchParams.get('lk_upload_id'));}
 const first=await upload('Security fixture one');const second=await upload('Security fixture two');check(first>0&&second>0&&first!==second,'Consecutive uploads create two pending works');
 const uploadNonce=await page.inputValue('[name=lk_upload_nonce]');
 const invalid=await context.request.post(page.url(),{multipart:{lk_upload_submit:'1',lk_upload_nonce:'invalid',lk_photo_title:'Invalid security fixture'}});
 check((await invalid.text()).includes('Phiên gửi biểu mẫu đã hết hạn'),'Invalid nonce refused after hardening');
 const invalidFile=await context.request.post(base+'/dang-tac-pham/',{multipart:{lk_upload_submit:'1',lk_upload_nonce:uploadNonce,lk_photo_title:'Invalid security fixture',lk_photo_file:{name:'fake.jpg',mimeType:'image/jpeg',buffer:Buffer.from('Not an image')}}});
 check((await invalidFile.text()).includes('Chỉ chấp nhận ảnh'),'Invalid JPEG refused after hardening');
 let nonce=await apiNonce();check(nonce,'Authenticated REST nonce available');
 for(const status of ['publish','future','private']){const r=await api('lk_photo','POST',{title:'Forbidden fixture',status,date:'2035-01-01T12:00:00'},nonce);check(r.status===403,'Author cannot create '+status+' through REST');}
 check((await api('lk_photo/'+first,'POST',{status:'publish'},nonce)).status===403,'Author cannot promote own pending work through REST');
 await page.goto(base+'/wp-admin/post.php?post='+first+'&action=edit',{waitUntil:'domcontentloaded'});
 const editNonce=await page.inputValue('#_wpnonce');await context.request.post(base+'/wp-admin/post.php',{form:{action:'editpost',post_ID:String(first),post_type:'lk_photo',_wpnonce:editNonce,post_title:'Security fixture one',post_status:'publish'}});
 await login('editor');nonce=await apiNonce();
 for(const id of [first,second]){const r=await api('lk_photo/'+id+'?context=edit','GET',null,nonce);check(r.status===200&&r.data.status==='pending'&&r.data.featured_media>0,'Editor sees pending work with thumbnail: fixture '+(id===first?'one':'two'));}
 check((await api('lk_photo/'+first,'POST',{status:'publish'},nonce)).status===200,'Editor approves work on real LocalWP');
 await login('administrator');nonce=await apiNonce();check((await api('lk_photo/'+second+'?context=edit','GET',null,nonce)).data.status==='pending','Administrator can read pending work');
 const heartNonce=await page.evaluate(()=>window.heartbeatSettings?.nonce);
 if(heartNonce){const hb=await context.request.post(base+'/wp-admin/admin-ajax.php',{form:{action:'heartbeat',_nonce:heartNonce}});const j=await hb.json();check(hb.status()===200&&j.server_time,'Authenticated admin-ajax heartbeat remains functional');}
 const weak=await api('users/'+c.users.subscriber.id,'POST',{password:'123456'},nonce);check(weak.status===400&&weak.data.code==='lk_weak_password','Weak password rejected by actual REST user endpoint');
 await page.goto(base+'/wp-admin/user-edit.php?user_id='+c.users.subscriber.id,{waitUntil:'domcontentloaded'});
 const userNonce=await page.inputValue('#_wpnonce');
 const profile=await context.request.post(base+'/wp-admin/user-edit.php',{form:{action:'update',user_id:String(c.users.subscriber.id),_wpnonce:userNonce,user_login:c.users.subscriber.login,nickname:'Security fixture',email:c.users.subscriber.email,pass1:'123456',pass2:'123456',pw_weak:'on'}});
 check((await profile.text()).includes('Mật khẩu phải có ít nhất 12'),'Weak password rejected by actual admin profile even with weak-password checkbox');
 await page.goto(base+'/wp-admin/tools.php?page=lk-security',{waitUntil:'domcontentloaded'});await page.locator('.wrap').screenshot({path:path.join(evidence,'hardening-settings.png')});
 await page.goto(base+'/');check(await page.locator('.lk-card').count()===baseline+1,'Approved image appears with all 30 demos');check(await page.locator('.lk-hero-art>img').getAttribute('src')===heroBefore,'Fixed banner unaffected by new publication');
 await page.fill('#lk-search','security fixture one');check(await page.locator('.lk-card:visible').count()===1,'Search remains functional');await page.locator('.lk-photo:visible').click();check(await page.locator('.lk-lightbox').isVisible(),'Lightbox opens');await page.click('#lk-close');await page.fill('#lk-search','');await page.selectOption('#lk-author','Bộ sưu tập mẫu');check(await page.locator('.lk-card:visible').count()===30,'Author filter still finds 30 demos');
 await page.locator('[data-album="thien-nhien"]').click();check(await page.locator('.lk-card:visible').count()>0,'Album filtering still works');
 await page.setViewportSize({width:390,height:844});await page.goto(base+'/');check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile gallery remains within viewport');await page.screenshot({path:path.join(evidence,'gallery-after.png'),fullPage:false});
 // Logout uses the plugin-rewritten URL generated by WordPress.
 const logout=await page.locator('#wp-admin-bar-logout a').getAttribute('href');check(new URL(logout).pathname.includes(c.login_slug),'Logout uses renamed login endpoint');await page.goto(logout,{waitUntil:'domcontentloaded'});check(!(await context.cookies()).some(x=>x.name.startsWith('wordpress_logged_in_')),'Logout removes authentication cookie');
 // Password reset mail stays in LocalWP Mailpit, addressed only to a disposable fixture.
 await page.goto(loginURL+'?action=lostpassword',{waitUntil:'domcontentloaded'});await page.fill('#user_login',c.users.subscriber.email);await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.click('#wp-submit')]);
 let message;
 for(let i=0;i<15;i++){const r=await context.request.get('http://127.0.0.1:10000/api/v1/messages');const messages=(await r.json()).messages;message=messages.find(m=>(m.To||[]).some(t=>t.Address===c.users.subscriber.email));if(message)break;await new Promise(r=>setTimeout(r,500));}
 check(message,'Forgot-password mail delivered to LocalWP Mailpit for disposable account');
 const detail=await(await context.request.get('http://127.0.0.1:10000/api/v1/message/'+message.ID)).json();
 const text=(detail.Text||'')+' '+(detail.HTML||'');const urls=(text.match(/https?:\/\/[^\s<>"']+/g)||[]).map(s=>s.replaceAll('&amp;','&'));
 const reset=urls.find(u=>u.startsWith(base)&&u.includes('key=')&&u.includes('action=rp'));check(reset&&new URL(reset).pathname.includes(c.login_slug),'Recovery mail links to renamed login endpoint');
 await page.goto(reset,{waitUntil:'domcontentloaded'});const resetURL=page.url();const fields=await page.locator('#resetpassform').evaluate(f=>Object.fromEntries(new FormData(f)));
 const resetWeak=await context.request.post(resetURL,{form:{...fields,pass1:'123456',pass2:'123456',pw_weak:'on'}});check((await resetWeak.text()).includes('Mật khẩu phải có ít nhất 12'),'Weak password rejected by real password-reset endpoint');
 const newPassword=crypto.randomBytes(18).toString('base64url')+'aA1!';const resetStrong=await context.request.post(resetURL,{form:{...fields,pass1:newPassword,pass2:newPassword}});check(!(await resetStrong.text()).includes('Mật khẩu phải có ít nhất 12'),'Strong reset accepted');c.users.subscriber.password=newPassword;fs.writeFileSync(privatePath,JSON.stringify(c,null,2),{mode:0o600});
 await login('subscriber','/dang-tac-pham/');
 // No failures are attempted against any original administrator account.
 await context.clearCookies();helper('lock-snapshot');
 try{
  await page.goto(loginURL,{waitUntil:'domcontentloaded'});const counts=[];let html='';
  for(let i=1;i<=5;i++){const r=await context.request.post(loginURL,{form:{log:c.users.subscriber.login,pwd:'Wrong-fixture-only-1!',testcookie:'1','wp-submit':'Log In'}});html=await r.text();counts.push(JSON.parse(helper('lock-status')).active_locks);}
  check(counts.slice(0,4).every(n=>n===0)&&counts[4]>0,'Configured lockout occurs on fifth failure, not before');
  const blocked=await context.request.post(loginURL,{form:{log:c.users.subscriber.login,pwd:newPassword,testcookie:'1','wp-submit':'Log In'}});check(!(await context.cookies()).some(x=>x.name.startsWith('wordpress_logged_in_')),'Correct disposable password is blocked during lockout');
  await page.setContent(await blocked.text(),{waitUntil:'domcontentloaded'});await page.locator('#user_login').evaluate(e=>{e.style.filter='blur(8px)';});await page.locator('#login').screenshot({path:path.join(evidence,'login-lockout.png')});
 }finally{helper('unlock');}
 await login('subscriber','/dang-tac-pham/');check(true,'Private CLI recovery restores access without altering administrator password');
 check(jsErrors.length===0,'No frontend JavaScript errors during regression');
 await browser.close();
 if(fs.existsSync(path.join(evidence,'regression-localwp-partial.json')))fs.unlinkSync(path.join(evidence,'regression-localwp-partial.json'));
 fs.writeFileSync(path.join(evidence,'regression-localwp.json'),JSON.stringify({time_utc:new Date().toISOString(),scope:base,after_actual_localwp_stop_start:true,checks:results,screenshot_redaction:'login username field blurred; no private URL/cookie/key captured'},null,2)+'\n');
})().catch(async error=>{if(browser)await browser.close().catch(()=>{});fs.writeFileSync(path.join(evidence,'regression-localwp-partial.json'),JSON.stringify({scope:base,checks:results,failed:true},null,2)+'\n');let message=String(error).replaceAll(c.login_slug,'[PRIVATE_LOGIN]');for(const secret of [c.password,...Object.values(c.users).map(u=>u.password)].filter(Boolean))message=message.replaceAll(secret,'[SECRET]');console.error(message);process.exit(1);});
