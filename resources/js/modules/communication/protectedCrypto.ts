import {shallowReactive} from 'vue';
export const unlockedKeys=shallowReactive(new Map<number,CryptoKey>());
export const unlockedSecrets=new Map<number,string>();
export const unlockedKeyIds=shallowReactive(new Map<number,string>());
const messageKeys=shallowReactive(new Map<string,CryptoKey>());
export function messageKey(chat:number,id:string){return messageKeys.get(chat+':'+id)||(!unlockedKeyIds.has(chat)?unlockedKeys.get(chat):undefined);}
export function rememberHistorical(chat:number,id:string,key:CryptoKey){messageKeys.set(chat+':'+id,key);}
export function rememberSecret(chat:number,secret:string,key:CryptoKey,id?:string){unlockedSecrets.set(chat,secret);unlockedKeys.set(chat,key);if(id){unlockedKeyIds.set(chat,id);rememberHistorical(chat,id,key);}}
export function clearCurrentSecret(chat:number){unlockedSecrets.delete(chat);unlockedKeys.delete(chat);unlockedKeyIds.delete(chat);}
export function forgetSecret(chat:number){clearCurrentSecret(chat);for(const id of messageKeys.keys())if(id.startsWith(chat+':'))messageKeys.delete(id);}
export function forgetSecrets(){unlockedSecrets.clear();unlockedKeys.clear();unlockedKeyIds.clear();messageKeys.clear();}
const enc=new TextEncoder();
export function b64(bytes:Uint8Array){let s='';for(let i=0;i<bytes.length;i+=8192)s+=String.fromCharCode(...bytes.subarray(i,i+8192));return btoa(s);}
export function unb64(value:string){return Uint8Array.from(atob(value),c=>c.charCodeAt(0));}
export function aad(chat:number,id:string,kind:string){return enc.encode(`judibas-protected-v1:${chat}:${id}:${kind}`);}
export async function encrypt(key:CryptoKey,bytes:BufferSource,context:Uint8Array){const iv=crypto.getRandomValues(new Uint8Array(12));const cipher=await crypto.subtle.encrypt({name:'AES-GCM',iv,additionalData:context,tagLength:128},key,bytes);return {iv:b64(iv),ciphertext:b64(new Uint8Array(cipher))};}
export async function decrypt(key:CryptoKey,iv:string,ciphertext:string,context:Uint8Array){return crypto.subtle.decrypt({name:'AES-GCM',iv:unb64(iv),additionalData:context,tagLength:128},key,unb64(ciphertext));}
export async function importSecret(secret:string){const raw=unb64(secret.trim());if(raw.length!==32)throw new Error('Enter the complete 256-bit access key.');return crypto.subtle.importKey('raw',raw,'AES-GCM',false,['encrypt','decrypt']);}
export async function protect(chat:number,config:any,body:string,file:File|null){const key=messageKey(chat,config.key_id);if(!key)throw new Error('Unlock this chat before sending a protected message.');const id=crypto.randomUUID();let fileIv:string|undefined;let blob:Blob|undefined;if(file){if(file.size>10*1024*1024)throw new Error('Files must be 10 MB or smaller.');const iv=crypto.getRandomValues(new Uint8Array(12));fileIv=b64(iv);blob=new Blob([await crypto.subtle.encrypt({name:'AES-GCM',iv,additionalData:aad(chat,id,'file'),tagLength:128},key,await file.arrayBuffer())],{type:'application/octet-stream'});}
const payload=await encrypt(key,enc.encode(JSON.stringify({body,file:file?{name:file.name,type:file.type}:null})),aad(chat,id,'message'));const form=new FormData();form.append('protected_uuid',id);form.append('protected_payload',JSON.stringify({...payload,key_id:config.key_id,file_iv:fileIv}));if(blob)form.append('attachment',blob,'encrypted.bin');return form;}
