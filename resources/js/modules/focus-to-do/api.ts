export async function focusApi(path:string,method='GET',body?:unknown):Promise<any>{
 const token=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content||'';
 const response=await fetch('/focus-to-do/api/'+path,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token},...(body!==undefined?{body:JSON.stringify(body)}:{})});
 const result=await response.json().catch(()=>({message:'Unable to connect. Please try again.'}));
 if(!response.ok)throw Error(Object.values(result.errors||{}).flat().join(' ')||result.message||'Request failed.');return result;
}
export const duration=(seconds:number)=>`${Math.floor(seconds/3600)}h ${Math.floor(seconds%3600/60)}m`;
export function localDate(date=new Date()){return `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;}
