import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export function connectDaymark(csrf:()=>string,onChange:(event:{report_id:number;action:string})=>void,onState:(state:string)=>void,onSubscribed:()=>void){
 let closed=false,echo:Echo<any>|undefined;
 onState('connecting');
 void fetch('/daymark/api/realtime/config',{headers:{Accept:'application/json'}}).then(async response=>{
  if(!response.ok)throw Error('Realtime configuration unavailable.');
  const config=await response.json();if(closed)return;
  if(!config.enabled||!config.key){onState('disabled');return;}
  const client=new Pusher(config.key,{
   cluster:config.cluster||'mt1',forceTLS:config.provider==='pusher'||config.scheme==='https',disableStats:true,
   ...(config.provider==='reverb'?{wsHost:config.host||location.hostname,wsPort:Number(config.port),wssPort:Number(config.port),enabledTransports:['ws','wss'] as ('ws'|'wss')[]} : {}),
   authorizer:channel=>({authorize:(socketId,callback)=>{
    void fetch('/daymark/api/realtime/auth',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify({socket_id:socketId,channel_name:channel.name})}).then(async response=>{if(!response.ok)throw Error('Daymark channel authorization failed.');const result=await response.json();if(!closed)callback(null,result);}).catch(error=>{if(!closed){onState('unavailable');callback(error,null);}});
   }}),
  });
  echo=new Echo({broadcaster:config.provider,client});
  client.connection.bind('state_change',({current}:{current:string})=>{if(!closed)onState(current==='connected'?'connecting':['failed','unavailable'].includes(current)?'unavailable':'reconnecting');});
  echo.private(config.channel).listen('.daymark.report.changed',onChange).subscribed(()=>{if(!closed){onState('live');onSubscribed();}}).error(()=>{if(!closed)onState('unavailable');});
 }).catch(()=>{if(!closed)onState('unavailable');});
 return ()=>{closed=true;echo?.disconnect();};
}
