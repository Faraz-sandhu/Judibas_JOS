import {inject,watch,type Ref} from 'vue';

export function useAccountingAlerts(...errors:Ref<string>[]){
 const notify=inject<((message:string,error?:boolean)=>void)>('accounting-notify');
 if(!notify)return;
 for(const error of errors)watch(error,message=>{if(message)notify(message,true);});
}
