import {ref,onBeforeUnmount} from 'vue';
export function useCommunicationPresence(request:(path:string,method?:string,body?:any)=>Promise<any>,csrf:()=>string){
 const states=ref<Record<number,string>>({}),preferences=ref<Record<number,string>>({}),members=ref<Record<number,boolean>>({});let version=0,ownId=0,stopped=false,lastActive=Date.now(),lastSent='',publishing=false,pending=false;
 const tab=typeof crypto.randomUUID==='function'?crypto.randomUUID():'10000000-1000-4000-8000-100000000000'.replace(/[018]/g,c=>(Number(c)^crypto.getRandomValues(new Uint8Array(1))[0]&15>>Number(c)/4).toString(16));
 let heartbeat:ReturnType<typeof setInterval>|undefined,idle:ReturnType<typeof setTimeout>|undefined,snapshotTimer:ReturnType<typeof setTimeout>|undefined;
 function preference(id:number){return preferences.value[id]||'online';}
 function label(id:number){const chosen=preference(id);if(chosen==='offline'||members.value[id]===false)return 'Offline';const effective=states.value[id]||'offline';return ({online:'Online',away:'Away',busy:'Do not disturb',offline:'Offline'} as Record<string,string>)[effective];}
 function snapshot(){if(snapshotTimer)return;snapshotTimer=setTimeout(async()=>{snapshotTimer=undefined;const token=version;try{const result=await request('/presence');if(token===version){states.value=result.states;preferences.value=result.preferences;}}catch{}},100);}
 function update(e:{user_id:number;state:string;preference?:string|null}){version++;states.value[e.user_id]=e.state;if(e.preference)preferences.value[e.user_id]=e.preference;if(e.state!=='offline')members.value[e.user_id]=true;}
 async function publish(force=false){if(!ownId||stopped)return;if(publishing){pending=true;return;}publishing=true;try{do{pending=false;const activity=document.hidden||!document.hasFocus()||Date.now()-lastActive>=120000?'away':'online';if(force||activity!==lastSent){try{const result=await request('/presence','POST',{tab,activity});lastSent=activity;update({user_id:ownId,...result});}catch{lastSent='';}}force=true;}while(pending&&!stopped);}finally{publishing=false;}}
 function arm(){if(idle)return;idle=setTimeout(()=>{idle=undefined;if(Date.now()-lastActive>=120000)void publish();else arm();},Math.max(100,120000-(Date.now()-lastActive)));}
 function activity(){lastActive=Date.now();arm();if(lastSent!=='online')void publish();}
 function visibility(){if(!document.hidden&&document.hasFocus())lastActive=Date.now();arm();void publish(true);}
 function leave(){if(ownId)void fetch('/communication/api/presence',{method:'POST',keepalive:true,headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify({tab,activity:'offline'})}).catch(()=>{});}
 function start(employee:boolean,id:number){snapshot();if(!employee)return;ownId=id;void publish(true);arm();heartbeat=setInterval(()=>void publish(true),45000);for(const name of ['pointerdown','keydown','pointermove'])window.addEventListener(name,activity,{passive:true});document.addEventListener('visibilitychange',visibility);window.addEventListener('focus',visibility);window.addEventListener('blur',visibility);window.addEventListener('pagehide',leave);}
 function membership(id:number,online:boolean){members.value[id]=online;if(online)snapshot();else states.value[id]='offline';}
 function connection(state:string){if(state==='connected'){void publish(true);snapshot();}else members.value={};}
 onBeforeUnmount(()=>{stopped=true;leave();if(heartbeat)clearInterval(heartbeat);if(idle)clearTimeout(idle);if(snapshotTimer)clearTimeout(snapshotTimer);for(const name of ['pointerdown','keydown','pointermove'])window.removeEventListener(name,activity);document.removeEventListener('visibilitychange',visibility);window.removeEventListener('focus',visibility);window.removeEventListener('blur',visibility);window.removeEventListener('pagehide',leave);});
 return {label,preference,start,update,connection,membership};
}
