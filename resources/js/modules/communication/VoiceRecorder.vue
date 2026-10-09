<script setup lang="ts">
import {ref,onBeforeUnmount,watch} from 'vue';
const props=defineProps<{disabled?:boolean}>();
const emit=defineEmits<{ready:[file:File];active:[value:boolean]}>();
const recording=ref(false),starting=ref(false),seconds=ref(0),url=ref(''),error=ref('');
let recorder:MediaRecorder|null=null,stream:MediaStream|null=null,timer:ReturnType<typeof setInterval>|undefined,chunks:Blob[]=[],result:File|null=null,discard=false,disposed=false;
function release(){if(timer)clearInterval(timer);timer=undefined;stream?.getTracks().forEach(t=>t.stop());stream=null;}
function clear(){if(url.value)URL.revokeObjectURL(url.value);url.value='';result=null;}
function cancel(){discard=true;if(recorder?.state==='recording')recorder.stop();release();recording.value=false;clear();}
async function start(){if(props.disabled||starting.value)return;error.value='';clear();starting.value=true;try{
 if(!window.isSecureContext||!navigator.mediaDevices?.getUserMedia||!window.MediaRecorder)throw new Error('Recording needs HTTPS or localhost and a supported browser.');
 stream=await navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true},video:false});if(disposed){release();return;}
 const mime=['audio/webm;codecs=opus','audio/ogg;codecs=opus','audio/mp4'].find(t=>MediaRecorder.isTypeSupported(t));if(!mime)throw new Error('Audio recording is unsupported in this browser.');
 recorder=new MediaRecorder(stream,{mimeType:mime,audioBitsPerSecond:32000});chunks=[];discard=false;seconds.value=0;
 recorder.ondataavailable=e=>{if(e.data.size)chunks.push(e.data);};
 recorder.onstop=()=>{release();recording.value=false;if(discard||disposed)return;const blob=new Blob(chunks,{type:mime});if(!blob.size){error.value='No audio recorded. Please try again.';return;}const ext=mime.includes('ogg')?'ogg':mime.includes('mp4')?'m4a':'webm';result=new File([blob],'voice-message-'+Date.now()+'.'+ext,{type:mime});url.value=URL.createObjectURL(blob);};
 recorder.onerror=()=>{discard=true;release();recording.value=false;error.value='Recording failed. Please try again.';};
 recorder.start(250);recording.value=true;timer=setInterval(()=>{seconds.value++;if(seconds.value>=300)stop();},1000);
 }catch(e){release();error.value=(e as DOMException).name==='NotAllowedError'?'Allow microphone access to record a voice message.':(e as Error).message;}finally{starting.value=false;}}
function stop(){if(recorder?.state==='recording')recorder.stop();}
function send(){if(result&&!props.disabled){emit('ready',result);clear();}}
watch(()=>starting.value||recording.value||!!url.value,value=>emit('active',value));
onBeforeUnmount(()=>{disposed=true;cancel();emit('active',false);});
</script>
<template>
<div class="voice-recorder" :class="{expanded:recording||url||starting}">
 <button v-if="!recording&&!url" class="voice-record-trigger" type="button" :disabled="disabled||starting" @click="start" aria-label="Record voice message" title="Record voice message"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0 0 14 0v-2M12 19v3m-4 0h8"/></svg></button>
 <span v-if="starting" class="voice-record-label" role="status">Opening microphone...</span>
 <template v-if="recording||url">
 <button type="button" class="voice-record-delete" @click="cancel" :aria-label="recording?'Cancel':'Discard'" title="Discard recording"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/></svg></button>
 <div v-if="recording" class="voice-record-progress"><span class="voice-record-dot"></span><span class="voice-record-timer" role="status" :aria-label="'Recording '+seconds+' seconds'">{{Math.floor(seconds/60)}}:{{String(seconds%60).padStart(2,'0')}}</span><div class="voice-record-bars" aria-hidden="true"><i v-for="n in 20" :key="n" :style="{animationDelay:(n%5)*.13+'s'}"></i></div><span class="voice-record-label">Recording</span></div>
 <audio v-else :src="url" controls preload="none" aria-label="Voice message preview"/>
 <button v-if="recording" type="button" class="voice-record-stop" @click="stop" aria-label="Stop" title="Stop and preview"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg></button>
 <button v-else type="button" class="voice-record-send" :disabled="disabled" @click="send" aria-label="Send voice" title="Send voice message"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="m3 3 19 9-19 9 4-9-4-9Zm4 9h15"/></svg></button>
 </template>
 <small v-if="error" class="voice-record-error" role="alert">{{error}}</small>
</div>
</template>
<style scoped>
.voice-recorder{position:relative;display:flex;align-items:center;gap:12px;min-width:0;flex-shrink:0}.voice-recorder.expanded{flex:1;width:100%;min-height:44px}.voice-recorder.voice-recorder button{display:grid;place-items:center;flex:0 0 42px;width:42px;height:42px;padding:0;border:0;border-radius:50%;background:transparent;color:var(--muted);cursor:pointer;font-size:14px}.voice-recorder button:hover{background:rgba(128,128,128,.15)}.voice-recorder button:disabled{opacity:.5;cursor:wait}.voice-recorder button:focus-visible{outline:2px solid #12b78e;outline-offset:2px}.voice-recorder.voice-recorder .voice-record-delete{color:#ef6472}.voice-recorder.voice-recorder .voice-record-stop{background:rgba(239,100,114,.12);color:#ef6472}.voice-recorder.voice-recorder .voice-record-send{background:#119b7e;color:#fff}.voice-record-progress{display:flex;align-items:center;gap:12px;flex:1;min-width:0;padding:0 6px}.voice-record-dot{width:8px;height:8px;flex-shrink:0;border-radius:50%;background:#ef6472;animation:voice-pulse 1.2s ease-in-out infinite}.voice-record-timer{font-variant-numeric:tabular-nums;font-size:14px;color:var(--text);white-space:nowrap}.voice-record-bars{display:flex;align-items:center;justify-content:center;gap:4px;height:28px;flex:1;overflow:hidden;min-width:25px}.voice-record-bars i{width:3px;height:12px;flex-shrink:0;border-radius:3px;background:#13b78e;animation:voice-bars .9s ease-in-out infinite alternate}.voice-record-label{font-size:12px;color:var(--muted);white-space:nowrap}.voice-recorder audio{flex:1;width:0;min-width:0;height:40px}.voice-record-error{position:absolute;bottom:100%;left:0;width:min(320px,70vw);padding:10px;background:var(--rail);border:1px solid var(--line);border-radius:8px;color:#ef6472;font-size:12px;z-index:5}@keyframes voice-pulse{50%{opacity:.35}}@keyframes voice-bars{to{height:25px}}@media(max-width:480px){.voice-recorder{gap:6px}.voice-record-progress{gap:8px}.voice-record-label{display:none}.voice-recorder.voice-recorder button{width:36px;height:36px;flex-basis:36px}}@media(prefers-reduced-motion:reduce){.voice-record-dot,.voice-record-bars i{animation:none}}
</style>
