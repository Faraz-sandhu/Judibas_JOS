import {chromium,expect} from '@playwright/test';
import {readFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
const superMode=process.argv.includes('--super');
execFileSync('php',['scripts/call-diagnostic-fixtures.php',...(superMode?['--super']:[])]);
let browser;
try{
 const fixture=JSON.parse(readFileSync('.preview/call-diagnostic.json','utf8'));
 browser=await chromium.launch({channel:'msedge',headless:true,args:['--use-fake-ui-for-media-stream','--use-fake-device-for-media-stream']});
 const pages=[];
 for(const user of [...(superMode?[{super:true}]:[]),...fixture.users]){
  const context=await browser.newContext({permissions:['microphone'],serviceWorkers:'block'}),page=await context.newPage();pages.push(page);
  page.on('response',async response=>{if(response.url().includes('/broadcasting/auth/batch')){const body=await response.json();console.log('Channel authorization:',response.status(),Object.entries(body.channels||{}).map(([name,value])=>({channel:name,authorized:!!value.data?.auth,error:value.error})));}});
  if(user.super){await context.addCookies([{name:fixture.session_cookie_name,value:fixture.session_cookie,domain:'127.0.0.1',path:'/',httpOnly:true}]);await page.goto('http://127.0.0.1:8000/communication');}else{await page.goto('http://127.0.0.1:8000/login');await page.locator('input[name=email]').fill(user.email);await page.locator('input[name=password]').fill(fixture.password);await page.getByRole('button',{name:/Sign in/}).click();await page.waitForURL('**/communication');}
  await expect(page.locator('.comm-conversations > button').first()).toBeVisible({timeout:15000});
  await page.locator('.comm-conversations > button').first().click();
  await expect(page.getByRole('button',{name:'Start voice call',exact:true})).toBeEnabled({timeout:20000});
 }
 console.log('Both employee call buttons enabled after authenticated WebSocket subscription.');
 await pages[0].getByRole('button',{name:'Start voice call',exact:true}).click();
 await expect(pages[1].getByRole('dialog',{name:'Voice call',exact:true})).toBeVisible({timeout:20000});
 console.log('Incoming call delivered to second employee via live signaling.');
 await pages[1].getByRole('button',{name:'Accept',exact:true}).click();
 await expect(pages[0].locator('.voice-state')).toContainText('Connected',{timeout:25000});
 await expect(pages[1].locator('.voice-state')).toContainText('Connected',{timeout:10000});
 console.log(superMode?'Super Admin-to-employee WebRTC call connected successfully.':'Employee-to-employee WebRTC call connected successfully in two isolated browser sessions.');
 await pages[0].getByRole('button',{name:'End call',exact:true}).click();
 if(superMode){
  await expect(pages[1].getByRole('dialog',{name:'Voice call',exact:true})).toHaveCount(0);
  await pages[0].getByRole('button',{name:'Conversation review',exact:true}).click();
  await pages[1].getByRole('button',{name:'Start voice call',exact:true}).click();
  await expect(pages[0].getByRole('button',{name:'Accept',exact:true})).toBeVisible({timeout:20000});
  await expect(pages[0].getByRole('button',{name:'My chats',exact:true})).toHaveAttribute('aria-pressed','true');
  await pages[0].getByRole('button',{name:'Accept',exact:true}).click();
  await expect(pages[0].locator('.voice-state')).toContainText('Connected',{timeout:25000});
  await expect(pages[1].locator('.voice-state')).toContainText('Connected',{timeout:10000});
  await pages[0].getByRole('button',{name:'End call',exact:true}).click();
  await pages[0].getByRole('button',{name:'Conversation review',exact:true}).click();
  await expect(pages[0].locator('.comm-conversations')).toContainText('You & Call diagnostic a');
  console.log('Employee-to-Super Admin call connected; review correctly labels You & employee.');
 }
}finally{
 await browser?.close();process.stdout.write(execFileSync('php',['scripts/call-diagnostic-fixtures.php','cleanup']));
}
