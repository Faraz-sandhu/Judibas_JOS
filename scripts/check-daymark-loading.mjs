import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
// Isolated API fixtures: browser checks never alter live users or reports.
const origin=process.env.JUDIBAS_TEST_URL||'http://127.0.0.1:8000';
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1440,height:1050}}),page=await context.newPage(),errors=[];
 page.on('pageerror',error=>errors.push(error.message));
 const today=new Date().toLocaleDateString('en-CA');const previous=new Date();previous.setDate(previous.getDate()-1);const previousDate=previous.toLocaleDateString('en-CA');
 let role='employee',writes=[],failNextReport=false,failOwnLookup=false;
 const company={id:51,name:'Skyinfinit'},departments=[{id:1,company_id:51,name:'Development'},{id:2,company_id:51,name:'Marketing'}];
 const abilities={view_reports:'View reports',write_reports:'Write reports',export_reports:'Export reports',view_companies:'View companies',view_people:'View people',review_reports:'Review reports',manage_departments:'Manage departments',manage_invitations:'Invite people',manage_people:'Manage people',manage_roles:'Manage roles',view_roles:'View roles',view_audit:'View audit',manage_settings:'Manage settings'};
 const users=Array.from({length:15},(_,i)=>({id:100+i,name:'Employee '+i,email:'employee'+i+'@example.test',department:'Development',designation:'Developer',company_id:51,role:i===0?'pm':'employee',active:true,role_name:i===0?'Project Manager':'Employee'}));
 const reports=users.map((u,i)=>({id:i+1,user_id:u.id,name:u.name,company_id:51,department:'Development',designation:'Developer',date:today,hours:8,status:i%3===0?'reviewed':'submitted',feedback:'',content_format:'html',completed:'<p>Completed a feature</p>',progress:'<p>Testing</p>',plan:'<p>Release</p>',blockers:'<p>None</p>',sections_html:{completed:'<p><strong>Completed a feature</strong></p>',progress:'<p>Testing</p>',plan:'<p>Release</p>',blockers:'<p>None</p>'},sections_text:{completed:'Completed a feature',progress:'Testing',plan:'Release',blockers:'None'}}));
 reports[0].date=previousDate;
 await page.route(/\/daymark\/api\//,async route=>{
  const requestUrl=new URL(route.request().url()),path=requestUrl.pathname.replace('/daymark/api/','');let result={};
  if(path==='own-report'&&failOwnLookup){failOwnLookup=false;await route.fulfill({status:503,json:{message:'Report lookup unavailable. Please retry.'}});return;}
  if(route.request().method()==='POST'&&path==='report'&&failNextReport){failNextReport=false;await route.fulfill({status:422,json:{message:'Please check the report and try again.'}});return;}
  if(route.request().method()==='POST'){writes.push({path,body:route.request().postDataJSON()});result={message:'Saved successfully.',invitation_url:origin+'/invitations/test-token',role:{id:1}};}
  else if(path==='data'){const permissions=role==='super_admin'?Object.keys(abilities).filter(key=>key!=='write_reports'):['view_reports','write_reports','export_reports','view_companies'];result={users:role==='employee'?[users[1]]:users,companies:[company],departments,reports:(role==='employee'?[reports[1]]:reports).filter(r=>!requestUrl.searchParams.has('date')||r.date===requestUrl.searchParams.get('date')),viewer:role==='super_admin'?{id:0,name:'Super Admin',email:'',role}:users[1],permissions,custom_roles:[{id:1,company_id:51,name:'Report reader',base_role:'employee',permissions:['view_reports']}],assignments:[{manager_id:100,employee_id:101}],audit:[{id:1,name:'Super Admin',action:'Department saved',target:'Development',created_at:new Date().toISOString()}],action_items:role==='super_admin'?[{id:1,date:previousDate,name:'Employee 0',status:'changes_requested',feedback:'Please add details.'}]:[],role_matrix:{abilities,roles:[{role:'employee',permissions:['view_reports','write_reports','export_reports','view_companies'],scope:'Own reports only'},{role:'company_admin',permissions:Object.keys(abilities),scope:'Own company'}]}};}
  else if(path==='invitations')result={invitations:[]};
  else if(path==='own-report')result={report:null};
  if(path==='departments'&&route.request().method()==='POST'){const input=route.request().postDataJSON();let department=departments.find(d=>d.company_id===input.company_id&&d.name===input.name);if(!department){department={id:Math.max(...departments.map(d=>d.id))+1,company_id:input.company_id,name:input.name};departments.push(department);}result.department=department;}
  await route.fulfill({json:result});
 });
 await page.route(/\/daymark(?:\?.*)?$/,async route=>{const r=await page.request.get(origin+'/products/daymark');await route.fulfill({contentType:'text/html',body:await r.text()});});

 let releaseReport,releaseDownload;
 let reportRequests=0,downloadRequests=0;
 await page.route('**/daymark/api/report',async route=>{reportRequests++;await new Promise(resolve=>releaseReport=resolve);await route.fulfill({json:{ok:true}});});
 await page.route(/\/daymark\/api\/(export|daily-export)\?/,async route=>{downloadRequests++;await new Promise(resolve=>releaseDownload=resolve);await route.fulfill({body:'test export',headers:{'content-type':'application/octet-stream'}});});
 await page.addInitScript(()=>{window.testDownloads=[];HTMLAnchorElement.prototype.click=function(){window.testDownloads.push(this.download);};});
 await page.goto(origin+'/daymark',{waitUntil:'networkidle'});
 await page.getByRole('button',{name:'Write report',exact:false}).click();
 for(const name of ['Completed tasks','Work in progress','Next plan','Hurdles & blockers']){const editor=page.getByRole('textbox',{name,exact:true});await editor.click();await page.keyboard.insertText('Testing submission feedback.');}
 await page.getByRole('button',{name:'Submit report',exact:true}).click();
 const saving=page.getByRole('button',{name:'Submitting report...',exact:true});
 await expect(saving).toBeDisabled();await expect(saving).toHaveAttribute('aria-busy','true');await expect(saving.locator('.dm-spinner')).toBeVisible();assert.equal(reportRequests,1);
 releaseReport();await expect(page.getByRole('dialog')).toHaveCount(0);
 for(const [label,format] of [['CSV','csv'],['Excel','xlsx']]){
  await page.getByRole('button',{name:'Export '+label,exact:true}).click();
  const pending=page.getByRole('button',{name:'Preparing '+label+'...',exact:true});await expect(pending).toBeDisabled();await expect(pending.locator('.dm-spinner')).toBeVisible();
  const before=await page.evaluate(()=>window.testDownloads.length);releaseDownload();await expect.poll(()=>page.evaluate(()=>window.testDownloads.length)).toBe(before+1);
  await expect(page.getByRole('button',{name:'Export '+label,exact:true})).toBeEnabled();
 }
 role='super_admin';await page.goto(origin+'/daymark',{waitUntil:'networkidle'});
 await page.getByRole('navigation',{name:'Daymark navigation'}).getByRole('button',{name:'Daily reports',exact:true}).click();
 await page.getByRole('button',{name:'Day-end report',exact:true}).click();
 for(const label of ['Excel','PDF']){
  await page.getByRole('button',{name:'Download '+label,exact:true}).click();
  const pending=page.getByRole('button',{name:'Preparing '+label+'...',exact:true});await expect(pending).toBeDisabled();await expect(pending).toHaveAttribute('aria-busy','true');await expect(pending.locator('.dm-spinner')).toBeVisible();
  const before=await page.evaluate(()=>window.testDownloads.length);releaseDownload();await expect.poll(()=>page.evaluate(()=>window.testDownloads.length)).toBe(before+1);
  await expect(page.getByRole('button',{name:'Download '+label,exact:true})).toBeEnabled();
 }
 assert.equal(downloadRequests,4);assert.deepEqual(errors,[]);console.log('Daymark loading feedback checks passed: submit, CSV, Excel, day-end Excel/PDF, disabled duplicate actions and recovery after completion.');
}finally{await browser.close();}
