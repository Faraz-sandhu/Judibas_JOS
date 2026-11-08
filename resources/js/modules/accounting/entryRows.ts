import {nextTick} from 'vue';

// Enter in an editable text field adds a row only at the end of the current list.
// Account picker Enter remains reserved for choosing its highlighted option.
export async function addRowOnEnter<T>(event:KeyboardEvent,index:number,rows:T[],create:()=>T,allowed:boolean,max=100){
 const target=event.target;
 if(event.defaultPrevented||event.isComposing||event.ctrlKey||event.altKey||event.metaKey||event.shiftKey||!(target instanceof HTMLInputElement)||target.closest('.ac-select')||['checkbox','radio','button','submit','hidden'].includes(target.type))return;
 event.preventDefault();
 if(event.repeat||!allowed||target.disabled||target.readOnly||index!==rows.length-1||rows.length>=max)return;
 const previousRow=event.currentTarget as HTMLElement;
 rows.push(create());
 await nextTick();
 const row=previousRow.nextElementSibling;
 const field=row?.querySelector<HTMLElement>('[role="combobox"]:not(:disabled),input:not([type="hidden"]):not([type="checkbox"]):not(:disabled)');
 field?.focus();field?.scrollIntoView({block:'nearest'});
}
