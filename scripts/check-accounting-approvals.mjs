import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
const origin='http://127.0.0.1:8000';
const browser=await chromium.launch({channel:'msedge',headless:true});
try {
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 let permissions=['journals.view','journals.update','journals.print','workflow.view','workflow.submit','workflow.upload_signed','workflow.finance_review','workflow.ceo_approve','workflow.finance_verify','workflow.finalize','journals.post','roles.view','roles.manage','books.view','books.manage','workflow.configure'];
 let approval=null,scope=['general'];
 const stages=['finance_review','ceo_review','finance_verify','manager_review','posted'];
 const journal={id:91,version:1,number:'JV-TEST',status:'draft',posting_date:'2026-11-04',kind:'general',remarks:'Sample approval',reference:'',total:'100.00',lines:[{account_id:11,account_name:'Cash',debit:'100.00',credit:'0.00'},{account_id:14,account_name:'Services',debit:'0.00',credit:'100.00'}]};
 const accounts=[{id:11,code:'01-01',name:'Cash',account_type:'Cash',root_type:'Asset',active:true,is_group:false},{id:14,code:'04-01',name:'Services',account_type:'Sales',root_type:'Income',active:true,is_group:false}];
 const role={id:1,slug:'finance',name:'Finance test',permissions:['journals.view'],active:true,protected:false,version:1,assigned_users:2};
 const decorated=()=>approval?{...approval,document:journal,events:[],stage_label:approval.stage.replaceAll('_',' '),can_approve:approval.stage!=='posted',can_return:approval.stage!=='posted',can_withdraw:approval.stage!=='posted'}:null;
 await page.route(/\/accounting\/api\//,async route=>{
  const path=new URL(route.request().url()).pathname.replace('/accounting/api/',''),write=route.request().method()==='POST',body=write&&route.request().headers()['content-type']?.includes('application/json')?route.request().postDataJSON():null;
  let data={};
  if(path==='data')data={is_super_admin:true,company_id:51,companies:[{id:51,name:'Workflow demo'}],permissions,role_name:'Finance test',role:'finance',can_manage:true,can_delete:false,accounts,account_types:{},book:{version:1,currency:'PKR',start_date:'2026-01-01',approval_required:true,approval_types:scope}};
  else if(path==='journals')data=new URL(route.request().url()).searchParams.has('id')?{journal:{...journal,approval}}:{data:[journal],total:1,last_page:1};
  else if(path==='approvals/document')data={workflow_mode:'paper',print_url:'/accounting/journals/91/print?company_id=51',enabled:true,approval:decorated(),can_submit:!approval};
  else if(path==='approvals/0/signed-copy'){assert.ok(route.request().postDataBuffer().includes(Buffer.from('signed.pdf')));approval={id:1,version:1,workflow_mode:'paper',stage:'manager_review',document_id:91,document_type:'journal',signed_copies:[]};data={message:'Signed copy submitted to Finance Head.'};}
  else if(path==='approvals/1/actions'){assert.equal(body.version,approval.version);assert.equal(body.action,'post');assert.equal(body.verified_signed_copy,true);approval={...approval,version:approval.version+1,stage:'posted'};journal.status='posted';journal.version=2;data={message:'Signed voucher verified and posted.'};}
  else if(path==='approvals')data={entries:{data:approval?[decorated()]:[],total:approval?1:0,last_page:1},stages:Object.fromEntries(stages.map(s=>[s,s])),enabled:true};
  else if(path==='books'&&write){assert.deepEqual([...body.approval_types].sort(),['bank_payment','general']);scope=body.approval_types;data={message:'Books settings saved.'};}
  else if(path==='roles'&&!write)data={roles:[role],catalog:[{key:'journals',name:'Journals',permissions:[{key:'journals.view',name:'View'},{key:'journals.update',name:'Update'}]}],can_manage:true,grantable:permissions};
  else if(path==='roles'&&write){assert.equal(body.name,'New finance role');assert.deepEqual(body.permissions,['journals.view']);data={message:'Accounting role saved.'};}
  await route.fulfill({contentType:'application/json',body:JSON.stringify(data)});
 });
 await page.route(/\/accounting(?:\?.*)?$/,async route=>{const r=await page.request.get(origin+'/products/accounts');await route.fulfill({contentType:'text/html',body:await r.text()});});
 await page.goto(origin+'/accounting?company_id=51#journals',{waitUntil:'networkidle'});
 await page.getByRole('button',{name:'Edit draft',exact:true}).click();
 await expect(page.getByRole('button',{name:'Review posting',exact:true})).toHaveCount(0);
 await page.getByLabel('CEO-stamped / signed voucher copy',{exact:true}).setInputFiles({name:'signed.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4 test')});
 await page.getByLabel('Approval note',{exact:true}).fill('Signed paper returned from the CEO.');
 await page.getByRole('button',{name:'Attach signed copy & send to Finance Head',exact:true}).click();
 await expect(page.locator('.ac-approval-panel')).toContainText('manager review');
 await expect(page.getByRole('button',{name:'Save draft',exact:true})).toHaveCount(0);
 await expect(page.getByRole('button',{name:'Verify signed copy & post',exact:true})).toBeDisabled();
 await page.getByLabel(/^I verified the CEO stamp/).check();
 await page.getByLabel('Approval note',{exact:true}).fill('CEO signature and voucher amounts verified.');
 await page.getByRole('button',{name:'Verify signed copy & post',exact:true}).click();
 await expect(page.locator('.ac-approval-panel')).toContainText('posted');
 const nav=page.getByRole('navigation',{name:'Accounting navigation'});
 await nav.getByRole('button',{name:'Roles and permissions',exact:true}).click();
 await page.getByRole('button',{name:'+ Create role',exact:true}).click();
 await page.getByLabel('Role name',{exact:true}).fill('New finance role');
 await page.getByLabel('Update',{exact:true}).check();
 await expect(page.getByLabel('View',{exact:true})).toBeChecked();
 await page.getByLabel('View',{exact:true}).uncheck();
 await expect(page.getByLabel('Update',{exact:true})).not.toBeChecked();
 await page.getByLabel('View',{exact:true}).check();
 await page.setViewportSize({width:390,height:844});
 assert.equal(await page.getByRole('dialog').evaluate(el=>el.scrollWidth>el.clientWidth),false);
 await page.getByRole('button',{name:'Save role',exact:true}).scrollIntoViewIfNeeded();
 await expect(page.getByRole('button',{name:'Save role',exact:true})).toBeInViewport();
 await page.setViewportSize({width:1440,height:1000});
 await page.getByRole('button',{name:'Save role',exact:true}).click();await expect(page.getByRole('dialog')).toHaveCount(0);
 await nav.getByRole('button',{name:'Company books',exact:true}).click();
 await expect(page.getByRole('checkbox',{name:/^Journal entries/})).toBeChecked();
 await expect(page.getByRole('checkbox',{name:/^BPV - Bank payment/})).not.toBeChecked();
 await page.getByRole('checkbox',{name:/^BPV - Bank payment/}).check();
 await page.getByRole('button',{name:'Save books settings',exact:true}).click();
 await expect(page.getByRole('checkbox',{name:/^BPV - Bank payment/})).toBeChecked();
 await page.setViewportSize({width:390,height:844});
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 permissions=['journals.view'];await page.goto(origin+'/accounting?company_id=51#journals',{waitUntil:'networkidle'});await page.reload({waitUntil:'networkidle'});
 await expect(page.getByRole('navigation',{name:'Accounting navigation'}).getByRole('button',{name:'Approvals',exact:true})).toHaveCount(0);
 await expect(page.getByRole('navigation',{name:'Accounting navigation'}).getByRole('button',{name:'Roles and permissions',exact:true})).toHaveCount(0);
 assert.deepEqual(errors,[]);
 console.log('Accounting approval UI passed: saved-draft signed-copy upload and head verification, edit freeze, final posting, role creation, permission navigation and mobile layout. APIs mocked; server invariants covered by feature tests.');
} finally {await browser.close();}
