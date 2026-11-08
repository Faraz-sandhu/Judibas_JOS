<script setup lang="ts">
import {ref,watch,computed,onBeforeUnmount,nextTick} from 'vue';
import {unlockedKeys,unlockedSecrets,unlockedKeyIds,messageKey,rememberHistorical,clearCurrentSecret,importSecret,rememberSecret,forgetSecret,forgetSecrets,encrypt,b64,aad,protect} from './protectedCrypto';
import {deliveryDevice,wrapDelivery,unwrapDelivery,verifySecret,createRecovery,recoverSecret,downloadSecret,type DeliveryDevice} from './keyDeliveryCrypto';
const props=defineProps<{chat:number;chatName?:string;userId?:number;admin:boolean;review:boolean;api:(path:string,method?:string,body?:any)=>Promise<any>}>();
const config=ref<any>(null),participant=ref(false),busy=ref(false),ready=ref(false),selected=ref(false);
const secret=ref(''),generated=ref(''),recoverySecret=ref(''),error=ref(''),show=ref(false),recover=ref(false);
const access=ref<any>({devices:[],requests:[],has_delivery:false,recovery:null}),requested=ref(false),delivery=ref<any>(null),dialogMode=ref('key');
const unlockConfig=ref<any>(null),unlockRecovery=ref<any>(null),resetConfirmed=ref(false),generatedKeyId=ref('');
const historical=computed(()=>!!unlockConfig.value&&unlockConfig.value.key_id!==config.value?.key_id);
const keyInput=ref<HTMLInputElement|null>(null),dialogClose=ref<HTMLButtonElement|null>(null);
let previousFocus:HTMLElement|null=null,epoch=0,disposed=false,loadingAccess=false,sharing=false;
let device:DeliveryDevice|null=null,pendingBackup:any=null;
const unlocked=computed(()=>!!config.value&&!!messageKey(props.chat,config.value.key_id));
const currentSecret=computed(()=>unlockedKeyIds.get(props.chat)===config.value?.key_id?unlockedSecrets.get(props.chat):undefined);
const requests=computed<any[]>(()=>access.value.requests||[]);
function path(suffix:string){return '/conversations/'+props.chat+'/'+suffix;}
watch(show,async visible=>{if(visible){previousFocus=document.activeElement as HTMLElement;await nextTick();(keyInput.value||dialogClose.value)?.focus();}else{secret.value='';recover.value=false;previousFocus?.focus();}});
async function closeDialog(){if(busy.value)return;if(delivery.value&&device){busy.value=true;try{await props.api(path('key-acknowledge'),'POST',{delivery_id:delivery.value.id,device_id:device.id});delivery.value=null;}catch{error.value='Unable to confirm delivery. Save the key and try closing again.';return;}finally{busy.value=false;}}generated.value='';recoverySecret.value='';show.value=false;}
async function openUnlock(key?:string){const chat=props.chat,version=epoch;if(!config.value||!participant.value||props.review)return;dialogMode.value='key';recover.value=false;generated.value='';error.value='';unlockConfig.value=config.value;unlockRecovery.value=access.value.recovery;
 if(typeof key==='string'&&key!==config.value.key_id){try{const result=await props.api(path('protection/history/'+key)+(device?'?device_id='+device.id:''));if(disposed||chat!==props.chat||version!==epoch)return;unlockConfig.value=result.protection;unlockRecovery.value=result.recovery;if(result.delivery&&device){const value=await unwrapDelivery(props.chat,result.protection,device,result.delivery);if(disposed||chat!==props.chat||version!==epoch)return;const oldKey=await verifySecret(chat,result.protection,value);if(disposed||chat!==props.chat||version!==epoch)return;rememberHistorical(chat,key,oldKey);delivery.value=result.delivery;generated.value=value;generatedKeyId.value=key;dialogMode.value='received';}}catch(e){error.value=(e as Error).message;return;}}
 show.value=true;}
function openReset(){if(!props.admin||busy.value)return;resetConfirmed.value=false;dialogMode.value='reset';error.value='';show.value=true;}
function applyConfig(value:any){if(value?.key_id&&unlockedKeyIds.has(props.chat)&&unlockedKeyIds.get(props.chat)!==value.key_id)clearCurrentSecret(props.chat);config.value=value;}
function dialogKeys(event:KeyboardEvent){if(event.key==='Escape'){event.preventDefault();void closeDialog();}if(event.key==='Tab'){const elements=Array.from((event.currentTarget as HTMLElement).querySelectorAll<HTMLElement>('button:not([disabled]),input'));const first=elements[0],last=elements[elements.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}}}
async function share(target:any){if(sharing||!props.admin)return;const value=currentSecret.value;if(!value){openUnlock();return;}sharing=true;busy.value=true;error.value='';try{await props.api(path('key-delivery'),'POST',await wrapDelivery(props.chat,config.value.key_id,value,target));access.value.has_delivery=true;access.value.requests=requests.value.filter(r=>r.device_id!==target.id);}catch(e){error.value=(e as Error).message;}finally{sharing=false;busy.value=false;}}
async function loadAccess(){if(loadingAccess||!config.value||!participant.value||disposed)return;loadingAccess=true;const chat=props.chat,version=epoch;try{
 if(props.admin){const result=await props.api(path('key-access'));if(disposed||chat!==props.chat||version!==epoch)return;access.value={devices:[],requests:[],...result};if(!result.has_delivery&&!!currentSecret.value&&result.devices?.length){const target=result.devices.find((d:any)=>requests.value.some(r=>r.device_id===d.id))||result.devices[0];await share(target);}}
 else if(props.userId){device=await deliveryDevice(props.userId,props.api);const result=await props.api(path('key-access')+'?device_id='+device.id);if(disposed||chat!==props.chat||version!==epoch)return;requested.value=!!result.requested;
 if(result.delivery&&delivery.value?.id!==result.delivery.id){const value=await unwrapDelivery(chat,config.value,device,result.delivery);if(disposed||chat!==props.chat||version!==epoch)return;const key=await verifySecret(chat,config.value,value);if(disposed||chat!==props.chat||version!==epoch)return;rememberSecret(chat,value,key,config.value.key_id);delivery.value=result.delivery;generatedKeyId.value=config.value.key_id;generated.value=value;dialogMode.value='received';error.value='';show.value=true;}
 else if(!result.delivery&&!result.has_history&&!result.requested){await requestAccess();}
 }
 }catch(e){if(!disposed&&chat===props.chat)error.value=(e as Error).message;}finally{loadingAccess=false;}}
async function sync(){const chat=props.chat,version=epoch;try{const result=await props.api(path('protection'));if(disposed||chat!==props.chat||version!==epoch)return;applyConfig(result.protection);participant.value=result.participant;ready.value=true;await loadAccess();}catch(e){if(!disposed)error.value=(e as Error).message;}}
watch(()=>[props.chat,props.review],async()=>{epoch++;ready.value=false;config.value=null;participant.value=false;selected.value=false;secret.value='';generated.value='';recoverySecret.value='';show.value=false;error.value='';access.value={devices:[],requests:[]};device=null;delivery.value=null;await sync();},{immediate:true});
async function backup(){const value=currentSecret.value;if(!props.admin||!value)return;const result=pendingBackup||(pendingBackup=await createRecovery(props.chat,config.value,value));recoverySecret.value=result.recovery;const saved=await props.api(path('key-recovery'),'POST',result.envelope);if(!saved.saved)throw new Error('Unable to save the encrypted recovery backup.');access.value.recovery=result.envelope;pendingBackup=null;}
async function setup(rotate=false){busy.value=true;error.value='';const chat=props.chat,version=epoch;try{const value=b64(crypto.getRandomValues(new Uint8Array(32))),keyId=crypto.randomUUID();const key=await importSecret(value);const check=await encrypt(key,new TextEncoder().encode('Judibas protected chat key'),aad(chat,keyId,'key'));const result=await props.api(path(rotate?'protection/rotate':'protection'),rotate?'POST':'PUT',{...check,key_id:keyId,...(rotate?{expected_key_id:config.value.key_id,confirmed:resetConfirmed.value}:{future:false})});if(disposed||chat!==props.chat||version!==epoch)return;applyConfig(result.protection);access.value={devices:[],requests:[],has_delivery:false,recovery:null};pendingBackup=null;rememberSecret(chat,value,key,config.value.key_id);generatedKeyId.value=keyId;generated.value=value;dialogMode.value='key';selected.value=true;show.value=true;await backup();}catch(e){if(!disposed)error.value=(e as Error).message;}finally{busy.value=false;if(!disposed)await loadAccess();}}
async function unlock(){busy.value=true;error.value='';const chat=props.chat,version=epoch;try{const target=unlockConfig.value||config.value;const value=recover.value&&props.admin?await recoverSecret(chat,target,unlockRecovery.value,secret.value):secret.value.trim();const key=await verifySecret(chat,target,value);if(disposed||chat!==props.chat||version!==epoch)return;if(target.key_id===config.value.key_id)rememberSecret(chat,value,key,target.key_id);else rememberHistorical(chat,target.key_id,key);secret.value='';show.value=false;recover.value=false;await loadAccess();}catch{error.value=recover.value?'Incorrect recovery key. Protected content remains locked.':'Incorrect access key. Protected content remains locked.';}finally{busy.value=false;}}
async function requestAccess(){if(props.admin||!props.userId||requested.value)return;busy.value=true;error.value='';try{device=await deliveryDevice(props.userId,props.api);const result=await props.api(path('key-request'),'POST',{device_id:device.id});requested.value=!!result.requested;}catch(e){error.value=(e as Error).message;}finally{busy.value=false;}}
async function createBackup(){busy.value=true;error.value='';try{await backup();generatedKeyId.value=config.value.key_id;generated.value=currentSecret.value||'';dialogMode.value='key';show.value=true;}catch(e){error.value=(e as Error).message;}finally{busy.value=false;}}
async function future(){if(!unlocked.value){await openUnlock();return;}busy.value=true;error.value='';try{const result=await props.api(path('protection'),'PUT',{...config.value,future:!config.value.future});applyConfig(result.protection);}catch(e){error.value=(e as Error).message;}finally{busy.value=false;}}
async function prepare(body:string,file:File|null){if(!ready.value)throw new Error('Chat security is still loading. Reopen the chat if this continues.');const latest=await props.api(path('protection'));applyConfig(latest.protection);if(config.value&&(selected.value||config.value.future)){if(!unlocked.value){await openUnlock();return false;}return protect(props.chat,config.value,body,file);}return null;}
async function copyKey(){try{await navigator.clipboard.writeText(generated.value);}catch{error.value='Copy the displayed key manually.';}}
function download(recovery=false){downloadSecret(props.chat,generatedKeyId.value||config.value.key_id,recovery?recoverySecret.value:generated.value,recovery,props.chatName||'');}
function lock(){forgetSecret(props.chat);generated.value='';secret.value='';recoverySecret.value='';}
function reviewRequests(){dialogMode.value='requests';error.value='';show.value=true;}
function sent(){selected.value=false;}
onBeforeUnmount(()=>{disposed=true;epoch++;pendingBackup=null;forgetSecrets();generated.value='';secret.value='';recoverySecret.value='';});
defineExpose({prepare,sync,sent,openUnlock});
</script>
<template>
<div v-if="!review&&(participant||error)" class="chat-protection">
 <span v-if="config">{{config.future?'Protected chat':'Message protection available'}} &middot; {{unlocked?'Unlocked on this page':'Locked'}}</span>
 <button v-if="admin&&!config" type="button" :disabled="busy||!ready" @click="setup(false)">Enable message protection</button>
 <template v-if="config">
  <button v-if="!unlocked" type="button" :disabled="busy" @click="openUnlock()">Unlock</button>
  <button v-if="unlocked" type="button" @click="lock">Lock</button>
  <label v-if="unlocked&&!config.future"><input v-model="selected" type="checkbox"> Protect next message</label>
  <button v-if="admin" type="button" :disabled="busy||!unlocked" @click="future">{{config.future?'Use per-message protection':'Protect all future messages'}}</button>
  <button v-if="admin&&!unlocked" type="button" :disabled="busy" @click="openReset">Forgot key? Start new protection</button>
  <button v-if="admin&&unlocked&&!access.recovery" type="button" :disabled="busy" @click="createBackup">Create recovery backup</button>
  <button v-if="admin&&requests.length" type="button" class="access-request-button" @click="reviewRequests">Key access requested ({{requests.length}})</button>
  <button v-if="!admin&&userId" type="button" :disabled="busy||requested" @click="requestAccess">{{requested?'Access request sent':'Request access again'}}</button>
 </template>
 <small v-if="error&&!show" role="alert">{{error}}</small>
 <Teleport to="body"><div v-if="show&&config&&!review" class="protection-overlay" @click.self="closeDialog"><section class="protection-dialog" role="dialog" aria-modal="true" aria-labelledby="protection-dialog-title" @keydown="dialogKeys">
  <header><span class="protection-dialog-icon" aria-hidden="true"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3"/></svg></span><button ref="dialogClose" type="button" class="protection-close" aria-label="Close access key dialog" :disabled="busy" @click="closeDialog">&times;</button></header>
  <template v-if="dialogMode==='reset'">
   <h3 id="protection-dialog-title">Create a new key for future messages</h3>
   <p>Use this if your previous chat key and recovery key are unavailable. Existing messages will stay protected with their original key. They will not be deleted or unlocked by the new key.</p>
   <label class="recovery-choice"><input v-model="resetConfirmed" type="checkbox"> I understand the new key only protects future messages.</label>
   <small v-if="error" class="protection-error">{{error}}</small>
   <div class="protection-dialog-actions"><button type="button" :disabled="busy" @click="closeDialog">Cancel</button><button type="button" class="protection-primary" :disabled="busy||!resetConfirmed" @click="setup(true)">{{busy?'Creating...':'Create new protection key'}}</button></div>
  </template>
  <template v-else-if="dialogMode==='requests'">
   <h3 id="protection-dialog-title">Key access requests</h3><p>Approve secure delivery to the recipient's browser. Your recovery key is never shared.</p>
   <p v-if="!requests.length">No pending requests.</p>
   <div v-for="request in requests" :key="request.id" class="access-request"><strong>{{request.name}}</strong><span>Requested access to protected messages</span><button type="button" class="protection-primary" :disabled="busy||!unlocked" @click="share(access.devices.find((d:any)=>d.id===request.device_id))">Approve &amp; send key</button></div>
   <button v-if="!unlocked&&requests.length" type="button" @click="openUnlock()">Unlock using chat or recovery key</button>
  </template>
  <template v-else>
   <h3 id="protection-dialog-title">{{generated?(dialogMode==='received'?'Save your access key':'Your private chat key'):'Unlock private messages'}}</h3>
   <p v-if="historical&&!generated">These earlier messages require their original chat key or its saved recovery key. Your new key will not unlock them.</p><p v-else>{{generated?(dialogMode==='received'?'Super Admin has shared your chat key securely. Copy or download it now. After closing this box, you will need your saved key to unlock these messages again.':'Save your chat key and download the recovery backup. Keep the recovery key private; only share the chat key.'):'Enter your saved key to read protected messages in this chat.'}}</p>
   <template v-if="generated">
    <label for="generated-chat-key">Chat access key</label><input id="generated-chat-key" :value="generated" readonly aria-label="Generated access key">
    <div class="protection-dialog-actions"><button type="button" @click="copyKey">Copy key</button><button type="button" @click="download(false)">Download chat key</button></div>
    <div v-if="admin&&recoverySecret" class="recovery-backup"><label>Super Admin recovery key</label><input :value="recoverySecret" readonly aria-label="Recovery key"><p>Store offline. Do not send this recovery key to the recipient.</p><button type="button" @click="download(true)">Download recovery key</button></div>
    <div class="protection-dialog-actions"><button type="button" class="protection-primary" :disabled="busy" @click="closeDialog">{{dialogMode==='received'?'I have saved the key':'Done'}}</button></div>
   </template>
   <form v-else @submit.prevent="unlock">
    <label v-if="admin&&unlockRecovery" class="recovery-choice"><input v-model="recover" type="checkbox"> Use recovery key</label>
    <label for="private-chat-key">{{recover?'Recovery key':'Chat access key'}}</label><input id="private-chat-key" ref="keyInput" v-model="secret" type="password" autocomplete="off" :placeholder="recover?'Enter your saved recovery key':'Enter the key shared by Super Admin'">
    <div class="protection-dialog-actions"><button type="button" @click="closeDialog">Cancel</button><button type="submit" class="protection-primary" :disabled="busy||!secret">{{busy?'Unlocking...':'Unlock chat'}}</button></div>
   </form><button v-if="admin&&!generated&&!historical" type="button" @click="openReset">I do not have either key</button>
  </template>
  <small v-if="error" class="protection-error" role="alert">{{error}}</small>
 </section></div></Teleport>
</div>
</template>
<style scoped>
.chat-protection{padding:8px 0 12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:11px;color:var(--muted,#737373)}.chat-protection button{padding:6px 10px;border:1px solid var(--line,#ddd);border-radius:7px;background:transparent;color:var(--text,#222);cursor:pointer}.chat-protection label{display:flex;align-items:center;gap:7px}.chat-protection small,.protection-error{color:#c2410c}.protection-overlay{position:fixed;inset:0;z-index:1200;background:rgba(0,0,0,.4);backdrop-filter:blur(3px);display:grid;place-items:center;padding:18px}.protection-dialog{box-sizing:border-box;width:min(420px,100%);padding:24px;border:1px solid var(--line,#ddd);border-radius:18px;background:var(--rail,#fff);color:var(--text,#222);box-shadow:0 24px 80px #0003;max-height:calc(100dvh - 36px);overflow:auto}.protection-dialog header{display:flex;align-items:center;justify-content:space-between}.protection-dialog-icon{width:44px;height:44px;display:grid;place-items:center;border-radius:13px;background:var(--comm-soft,#e8f5f0);color:var(--comm-accent,#128c76)}.protection-dialog h3{font-size:19px;margin:18px 0 8px}.protection-dialog p{font-size:13px;line-height:1.65;color:var(--muted,#737373);margin:0 0 20px}.protection-dialog label{font-size:12px;display:block;margin-bottom:8px;font-weight:600}.protection-dialog input{box-sizing:border-box;width:100%;min-width:0;padding:12px;border:1px solid var(--line,#ddd);background:var(--bg,#fff);color:inherit;border-radius:9px;font-size:12px}.protection-dialog input:focus{outline:2px solid var(--comm-accent,#128c76);outline-offset:2px}.protection-dialog button{font:inherit;border:1px solid var(--line,#ddd);background:transparent;color:inherit;border-radius:8px;padding:10px 14px;font-size:12px;cursor:pointer}.protection-dialog .protection-close{font-size:25px;padding:0;width:32px;height:32px;border:0;line-height:1}.protection-dialog-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:20px}.protection-dialog .protection-primary{background:var(--comm-accent,#128c76);color:#fff;border-color:transparent}.protection-primary:disabled{opacity:.55;cursor:not-allowed}.protection-error{display:block;margin-top:12px;font-size:12px;line-height:1.5}
.access-request{display:flex;flex-direction:column;gap:8px;padding:14px 0;border-top:1px solid var(--line,#ddd)}.access-request span{font-size:12px;color:var(--muted,#737373)}.recovery-backup{padding:14px;margin-top:18px;border:1px solid var(--line,#ddd);border-radius:10px}.recovery-backup p{font-size:11px;margin:8px 0}.protection-dialog .recovery-choice{display:flex;align-items:center;gap:8px;margin-bottom:16px}.protection-dialog .recovery-choice input{width:auto}.access-request-button{font-weight:600;color:var(--comm-accent,#128c76)!important}</style>
