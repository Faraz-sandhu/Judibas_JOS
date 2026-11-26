import assert from 'node:assert/strict';
import {readFile,mkdir,writeFile,unlink} from 'node:fs/promises';
import ts from 'typescript';
import {createRenderer,ref,nextTick} from 'vue';

await mkdir('.preview',{recursive:true});
const file=new URL('../.preview/accounting-live-hook.mjs',import.meta.url);
await writeFile(file,ts.transpileModule(await readFile('resources/js/modules/accounting/workspaceRealtime.ts','utf8'),{compilerOptions:{module:ts.ModuleKind.ESNext,target:ts.ScriptTarget.ES2022}}).outputText);
try{
 const {useAccountingLiveRefresh}=await import(file.href);
 const renderer=createRenderer({createElement:()=>({}),insert(){},remove(){},setElementText(){},createText:()=>({}),createComment:()=>({}),setText(){},setComment(){},parentNode:()=>null,nextSibling:()=>null,patchProp(){}});
 const revision=ref(0),editing=ref(false);let calls=0,release;
 const app=renderer.createApp({setup(){useAccountingLiveRefresh(async()=>{calls++;if(calls===1)await new Promise(resolve=>release=resolve);},()=>!editing.value,revision);return ()=>null;}});
 app.mount({});
 revision.value++;await nextTick();assert.equal(calls,1);
 revision.value++;revision.value++;await nextTick();assert.equal(calls,1,'Concurrent events must not start overlapping requests');
 release();await new Promise(resolve=>setTimeout(resolve,0));assert.equal(calls,2,'Pending events must trigger a final fresh read');
 editing.value=true;await nextTick();revision.value++;await nextTick();assert.equal(calls,2,'Editing must preserve unsaved form contents');
 editing.value=false;await nextTick();assert.equal(calls,3,'Closing the form must apply pending updates');
 app.unmount();revision.value++;await nextTick();assert.equal(calls,3,'Unmount must stop refreshes');
 console.log('Accounting live refresh: burst coalescing, editor preservation, pending refresh and cleanup passed.');
}finally{await unlink(file);}
