import Echo from 'laravel-echo';import Pusher from 'pusher-js';
export async function connectCanteen(company:string,csrf:string,onChange:()=>void){
 const c=await (await fetch('/pms/api/realtime/config',{headers:{Accept:'application/json'}})).json();if(!c.key)return ()=>{};
 const echo:any=new Echo({broadcaster:c.provider,client:new Pusher(c.key,{cluster:c.cluster||'mt1',forceTLS:c.provider==='pusher'||c.scheme==='https',wsHost:c.host||location.hostname,wsPort:Number(c.port||8080),wssPort:Number(c.port||8080),enabledTransports:['ws','wss'],})});
 echo.channel('canteen.company.'+company).listen('.canteen.order.changed',onChange);return ()=>echo.disconnect();
}
