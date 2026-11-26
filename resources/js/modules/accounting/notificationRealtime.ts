import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export function connectAccountingNotifications(csrf:()=>string,onChange:(event?:{company_id?:number})=>void,onState:(state:string)=>void,base='/accounting/api/notifications/realtime'){
 let closed=false,echo:Echo<any>|undefined;
 onState('connecting');
 void fetch(base+'/config',{headers:{Accept:'application/json'}}).then(async r=>{
  if(!r.ok)throw Error('Realtime configuration unavailable.');const c=await r.json();if(closed)return;
  if(!c.enabled||!c.key||!c.channels?.length){onState('disabled');return;}
  const client=new Pusher(c.key,{cluster:c.cluster||'mt1',forceTLS:c.provider==='pusher'||c.scheme==='https',disableStats:true,
   ...(c.provider==='reverb'?{wsHost:c.host||location.hostname,wsPort:Number(c.port),wssPort:Number(c.port),enabledTransports:['ws','wss'] as ('ws'|'wss')[]} : {}),
   authorizer:channel=>({authorize:(socketId,callback)=>{void fetch(base+'/auth',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify({socket_id:socketId,channel_name:channel.name})}).then(async response=>{if(!response.ok)throw Error('Accounting channel authorization failed.');const d=await response.json();if(!closed)callback(null,d);}).catch(error=>{if(!closed){onState('unavailable');callback(error,null);}});}}),
  });
  echo=new Echo({broadcaster:c.provider,client});
  client.connection.bind('state_change',({current}:{current:string})=>{if(!closed)onState(current==='connected'?'connecting':['failed','unavailable'].includes(current)?'unavailable':'reconnecting');});
  for(const channel of c.channels)echo.private(channel).listen('.accounting.activity',(event:{company_id?:number})=>{if(!closed)onChange(event);}).subscribed(()=>{if(!closed){onState('live');onChange();}}).error(()=>{if(!closed)onState('unavailable');});
 }).catch(()=>{if(!closed)onState('unavailable');});
 return ()=>{closed=true;echo?.disconnect();};
}
