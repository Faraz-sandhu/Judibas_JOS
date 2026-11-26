import {inject,onBeforeUnmount,watch,type Ref} from 'vue';

// Coalesce bursts without polling; retain pending refreshes while a form is open.
export function useAccountingLiveRefresh(load:()=>Promise<unknown>,safe:()=>boolean=()=>true,source?:Ref<number>){
 const revision=source??inject<Ref<number>>('accountingLiveRevision');
 if(!revision)return;
 let closed=false,running=false,handled=revision.value;
 async function refresh(){
  if(running||closed||!safe()||handled===revision!.value)return;
  running=true;
  try{while(!closed&&safe()&&handled!==revision!.value){handled=revision!.value;await load();}}
  catch(error){console.warn('Accounting live refresh failed.',error instanceof Error?error.message:'Request failed');}
  finally{running=false;}
 }
 watch(()=>[revision.value,safe()],()=>void refresh());
 onBeforeUnmount(()=>{closed=true;});
}
