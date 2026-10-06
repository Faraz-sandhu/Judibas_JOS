export function parseChatDate(value:string):Date {
 return new Date(value.replace(' ','T')+(/(?:Z|[+-]\d{2}:?\d{2})$/.test(value)?'':'Z'));
}
export function conversationTime(value:string|null|undefined,now=new Date()):string {
 if(!value)return '';
 const date=parseChatDate(value);if(Number.isNaN(date.getTime()))return '';
 const calendarDay=(d:Date)=>Date.UTC(d.getFullYear(),d.getMonth(),d.getDate());
 const days=Math.round((calendarDay(now)-calendarDay(date))/86400000);
 if(days<=0)return date.toLocaleTimeString('en',{hour:'2-digit',minute:'2-digit'});
 if(days===1)return 'Yesterday';
 if(days<=7)return date.toLocaleDateString('en',{weekday:'long'});
 return String(date.getDate()).padStart(2,'0')+'/'+String(date.getMonth()+1).padStart(2,'0')+'/'+date.getFullYear();
}