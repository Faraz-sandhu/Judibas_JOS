import {computed,inject,type ComputedRef} from 'vue';
export function useAccountingPermissions(){
 const granted=inject<ComputedRef<string[]|undefined>>('accounting-permissions',computed(()=>undefined));
 const legacy=inject<ComputedRef<{manage:boolean;admin:boolean}>>('accounting-legacy',computed(()=>({manage:false,admin:false})));
 return (key:string)=>{
  if(granted.value)return granted.value.includes(key);
  if(key==='roles.manage'||key==='books.manage'||key==='workflow.configure'||key==='audit.view'||key.endsWith('.delete')||key.endsWith('.reverse'))return legacy.value.admin;
  if(key.endsWith('.view')||key.endsWith('.print')||(key.startsWith('reports.')&&key!=='reports.export'))return true;
  return legacy.value.manage;
 };
}

export function useAccountingSuperAdmin(){return inject<ComputedRef<boolean>>('accounting-super-admin',computed(()=>false));}
