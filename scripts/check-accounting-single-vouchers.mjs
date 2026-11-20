import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
const origin='http://127.0.0.1:8000',browser=await chromium.launch({channel:'msedge',headless:true});
try {
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 let voucher=null,permissions=['bank_payments.view','bank_payments.create','bank_payments.update','bank_payments.print','workflow.view','workflow.submit','workflow.upload_signed'];
 const accounts=[{id:11,code:'01-01',name:'Cash',account_type:'Cash',root_type:'Asset'},{id:12,code:'01-02',name:'Bank',account_type:'Bank',root_type:'Asset'},{id:15,code:'05-01',name:'Salary expense',account_type:'General',root_type:'Expense'}].map(a=>({...a,active:true,is_group:false}));
 await page.route(/\/accounting\/api\//,async route=>{
  const u=new URL(route.request().url()),path=u.pathname.replace('/accounting/api/',''),write=route.request().method()==='POST',body=write&&route.request().headers()['content-type']?.includes('application/json')?route.request().postDataJSON():null;let data={};
  if(path==='data')data={company_id:51,companies:[{id:51,name:'Voucher UI demo'}],role:'finance',role_name:'Finance',permissions,can_manage:true,can_delete:false,accounts,account_types:{},book:{version:1,currency:'PKR',start_date:'2026-01-01',approval_required:true}};
  else if(path==='journals'&&write){assert.equal(body.kind,'bank_payment');assert.equal(body.lines.length,4);assert.equal(body.lines[0].credit,'105000.00');assert.equal(body.lines[0].debit,'0.00');assert.deepEqual(body.lines.slice(1).map(l=>l.description),['Ali salary','Ahmed salary','Sara salary']);assert.deepEqual(body.lines.slice(1).map(l=>l.debit),['30000','40000','35000']);voucher={...body,id:91,version:1,number:'BPV-0000091',status:'draft',total:'105000.00',lines:body.lines.map(l=>({...l,account_name:accounts.find(a=>a.id===l.account_id).name})),approval:null};data={journal:voucher,message:'Voucher saved as draft.'};}
  else if(path==='journals')data=u.searchParams.has('id')?{journal:voucher}:{data:voucher?[voucher]:[],total:voucher?1:0,last_page:1};
  else if(path==='approvals/document')data={enabled:true,workflow_mode:'paper',print_url:'/accounting/vouchers/91/print?company_id=51',approval:voucher?.approval,can_submit:!voucher?.approval};
  else if(path==='approvals/0/signed-copy'){
   assert.match(route.request().headers()['content-type'],/multipart\/form-data/);assert.ok(route.request().postDataBuffer().includes(Buffer.from('signed.pdf')));
   voucher.approval={id:1,workflow_mode:'paper',stage:'manager_review',stage_label:'Finance Head verification',version:2,can_approve:true,can_return:true,can_withdraw:false,events:[],signed_copies:[{id:1,original_name:'signed.pdf',document_version:1,current:true,uploaded_name:'Finance test',created_at:'2026-11-04T10:00:00Z',url:'/accounting/api/signed-copies/1?company_id=51'}]};data={message:'Signed copy sent to Finance Head.'};
  }
  else if(path==='approvals/1/actions'){assert.equal(body.action,'post');assert.equal(body.verified_signed_copy,true);voucher.status='posted';voucher.version=2;voucher.approval={...voucher.approval,stage:'posted',stage_label:'Posted',can_approve:false,can_return:false,version:3};data={message:'Verified voucher posted.'};}
  await route.fulfill({contentType:'application/json',body:JSON.stringify(data)});
 });
 await page.route(/\/accounting(?:\?.*)?$/,async route=>{const response=await page.request.get(origin+'/products/accounts');await route.fulfill({contentType:'text/html',body:await response.text()});});
 async function choose(label,text){await page.getByRole('combobox',{name:label,exact:true}).click();await page.getByRole('searchbox',{name:'Search '+label,exact:true}).fill(text);const trigger=page.getByRole('combobox',{name:label,exact:true});const dropdown=page.locator('.ac-select-panel');await expect.poll(async()=>{const t=await trigger.boundingBox(),d=await dropdown.boundingBox();return Math.min(Math.abs(d.y+d.height-t.y),Math.abs(d.y-t.y-t.height));}).toBeLessThanOrEqual(8);await page.getByRole('option').first().click();}
 await page.goto(origin+'/accounting?company_id=51#single-vouchers',{waitUntil:'networkidle'});await page.getByRole('button',{name:'+ Create voucher',exact:true}).click();
 await expect(page.locator('.ac-filters')).toBeHidden();
 await expect(page.locator('.ac-section-heading')).toBeHidden();
 await expect(page.getByRole('combobox',{name:'Voucher type',exact:true}).last()).toContainText('BPV');
 await page.getByLabel('Purpose / person',{exact:true}).fill('November salaries');await choose('Settlement account','Bank');
 for(const [i,[name,amount]] of [['Ali','30000'],['Ahmed','40000'],['Sara','35000']].entries()){
  if(i){
   if(i===2){await page.getByLabel('Detail amount 1',{exact:true}).press('Enter');await expect(page.getByRole('combobox',{name:/^Detail account /})).toHaveCount(2);}
   await page.getByLabel(i===1?'Detail amount 1':'Detail note 2',{exact:true}).press('Enter');
   await expect(page.getByRole('combobox',{name:'Detail account '+(i+1),exact:true})).toBeFocused();
  }
  await choose('Detail account '+(i+1),'Salary');await page.getByLabel('Detail note '+(i+1),{exact:true}).fill(name+' salary');await page.getByLabel('Detail amount '+(i+1),{exact:true}).fill(amount);
 }
 await expect(page.locator('.ac-journal-totals')).toContainText('105,000.00');await page.getByRole('button',{name:'Save draft',exact:true}).click();
 await expect(page.locator('.ac-document')).toContainText('BPV-0000091');await expect(page.getByLabel('Detail note 3',{exact:true})).toHaveValue('Sara salary');
 await expect(page.getByRole('button',{name:'Review posting',exact:true})).toHaveCount(0);
 await expect(page.getByRole('link',{name:'Print voucher for CEO signature',exact:true})).toBeVisible();
 await page.getByLabel('CEO-stamped / signed voucher copy',{exact:true}).setInputFiles({name:'signed.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4 test signed voucher')});
 await page.getByLabel('Approval note',{exact:true}).fill('CEO signed and stamped all three salary payments.');
 await page.getByRole('button',{name:'Attach signed copy & send to Finance Head',exact:true}).click();
 await expect(page.locator('.ac-approval-panel')).toContainText('Finance Head verification');await expect(page.getByRole('button',{name:'Save draft',exact:true})).toHaveCount(0);
 await expect(page.getByRole('link',{name:'View signed copy',exact:true})).toBeVisible();
 const post=page.getByRole('button',{name:'Verify signed copy & post',exact:true});await expect(post).toBeDisabled();
 await page.getByLabel(/^I verified the CEO stamp/).check();await page.getByLabel('Approval note',{exact:true}).fill('Checked stamp, saved version and all salary amounts.');
 await post.click();await expect(page.locator('.ac-approval-panel')).toContainText('Current stage: Posted');
 await page.setViewportSize({width:390,height:844});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 permissions=['bank_payments.view'];await page.goto(origin+'/accounting?company_id=51#single-vouchers',{waitUntil:'networkidle'});await page.reload({waitUntil:'networkidle'});await expect(page.getByRole('button',{name:'+ Create voucher',exact:true})).toHaveCount(0);
 assert.deepEqual(errors,[]);console.log('Single voucher browser checks passed: three salary lines, exact automatic settlement, saved-line preservation, signed-copy upload, mandatory verification and posting, role restrictions and mobile layout. API writes mocked.');
}finally{await browser.close();}
