<script setup lang="ts">
import {ref,computed,watch,onBeforeUnmount} from 'vue';
import ConversationAvatar from './ConversationAvatar.vue';
type Member={id:number;name:string;email:string;company_name:string|null};
type Item={id:number;sender_name:string;body?:string|null;created_at:string;attachment_name?:string|null;attachment_url?:string|null;links?:string[]};
const props=defineProps<{conversation:{id:number;name:string;kind:string;company_name?:string};viewer:number|null;mode:string;request:(path:string,method?:string,body?:any,extra?:Record<string,string>)=>Promise<any>;reviewUser?:string;adminReview?:boolean;avatar?:(id:number|null|undefined)=>string|null}>();
const emit=defineEmits<{close:[];jump:[id:number]}>();
const tab=ref('info'),items=ref<Item[]>([]),members=ref<Member[]>([]),company=ref<string|null>(null),query=ref(''),busy=ref(false),error=ref(''),more=ref(false),cursor=ref<number|null>(null);
let version=0,debounce:ReturnType<typeof setTimeout>|undefined;
const title=computed(()=>tab.value==='search'?'Search messages':props.conversation.kind==='direct'?(props.adminReview?'Conversation info':'Contact info'):props.conversation.kind==='community'?'Community info':'Group info');
const profile=computed(()=>props.conversation.kind==='direct'&&!props.adminReview?members.value.filter(m=>m.id!==props.viewer):members.value);
const results=computed(()=>items.value.filter(i=>tab.value!=='links'||i.links?.length));
function attachment(item:Item){return item.attachment_url+(props.reviewUser?'?view_user='+props.reviewUser:'');}
async function fetchItems(older=false){
 const id=props.conversation.id,token=++version,chosen=tab.value,term=query.value.trim();error.value='';
 if(chosen==='search'&&!term){items.value=[];busy.value=false;more.value=false;return;}
 busy.value=true;
 try{
 const extra:Record<string,string>={};if(older&&cursor.value)extra.before=String(cursor.value);
 if(chosen==='search')extra.search=term;else extra.kind=chosen==='info'?'media':chosen;
 const result=await props.request('/conversations/'+id+(chosen==='search'?'/messages':'/shared'),'GET',undefined,extra);
 if(token!==version||id!==props.conversation.id||chosen!==tab.value)return;
 const incoming=chosen==='search'?[...result.messages].reverse():result.items;
 items.value=older?[...items.value,...incoming]:incoming;more.value=result.has_more;
 cursor.value=chosen==='search'?result.messages[0]?.id||null:result.before;
 if(result.members){members.value=result.members;company.value=result.company_name;}
 }catch(e){if(token===version)error.value=(e as Error).message;}finally{if(token===version)busy.value=false;}
}
function select(value:string){if(debounce)clearTimeout(debounce);tab.value=value;items.value=[];more.value=false;void fetchItems();}
function search(){version++;if(debounce)clearTimeout(debounce);items.value=[];more.value=false;busy.value=!!query.value.trim();debounce=setTimeout(()=>void fetchItems(),300);}
function date(value:string){return new Date(value.replace(' ','T')+'Z').toLocaleDateString([],{month:'short',day:'numeric'});}
watch(()=>props.conversation.id+'|'+props.mode+'|'+props.reviewUser,()=>{version++;query.value='';members.value=[];company.value=null;select(props.mode==='search'?'search':'info');},{immediate:true});
onBeforeUnmount(()=>{version++;if(debounce)clearTimeout(debounce);});
</script>
<template>
<aside class="comm-detail-panel" aria-label="Chat details" @keydown.esc="emit('close')">
<header class="comm-detail-heading"><button aria-label="Close chat details" @click="emit('close')">&times;</button><h2>{{title}}</h2></header>
<nav class="comm-detail-tabs" aria-label="Chat detail sections"><button v-for="value in ['info','media','docs','links','search']" :key="value" :class="{selected:tab===value}" @click="select(value)">{{value==='info'?'Info':value==='docs'?'Docs':value.charAt(0).toUpperCase()+value.slice(1)}}</button></nav>
<div class="comm-detail-content">
<template v-if="tab==='info'">
<div class="comm-profile-card"><ConversationAvatar :kind="conversation.kind" :name="conversation.name" :members="profile" :viewer="viewer" :review="adminReview" :avatar="avatar" large/><h3>{{adminReview&&conversation.kind==='direct'?profile.map(p=>p.name).join(' & ')||conversation.name:conversation.name}}</h3><p>{{conversation.kind==='direct'?(adminReview?'Personal conversation':'Company contact'):conversation.kind==='community'?'Company announcements':members.length+' members'}}</p><small v-if="company">{{company}}</small></div>
<div class="comm-profile-section" v-if="conversation.kind==='community'"><h3>About this community</h3><p>Company announcements from Super Admin. Members can react to updates.</p></div>
<div class="comm-profile-section" v-if="profile.length"><h3>{{conversation.kind==='direct'?(adminReview?'Participants':'Contact details'):'Members'}}</h3><div class="comm-member-card" v-for="m in profile" :key="m.id"><span class="comm-mini-avatar"><img v-if="avatar?.(m.id)" :src="avatar(m.id)!" alt=""><template v-else>{{m.name.slice(0,1)}}</template></span><div><strong>{{m.name}}<small v-if="!adminReview&&m.id===viewer"> (you)</small></strong><a :href="'mailto:'+m.email">{{m.email}}</a><small v-if="m.company_name">{{m.company_name}}</small></div></div></div>
<button class="comm-shared-shortcut" @click="select('media')"><span>Media, links and documents</span><span>&rsaquo;</span></button>
<div class="comm-media-grid" v-if="items.length"><a v-for="item in items.slice(0,6)" :key="item.id" :href="attachment(item)" :title="item.attachment_name||'Shared image'"><img :src="attachment(item)" :alt="item.attachment_name||'Shared image'" loading="lazy"></a></div>
<button class="comm-search-shortcut" @click="select('search')">Search this conversation</button>
</template>
<template v-else-if="tab==='search'">
<form class="comm-detail-search" @submit.prevent="fetchItems()"><input v-model="query" @input="search" placeholder="Search in conversation" aria-label="Search messages" maxlength="200"><button type="submit" aria-label="Search messages now"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg></button></form>
<p class="comm-detail-hint">{{query.trim()?'Select a result to jump to its message.':'Find a message in this conversation.'}}</p>
<button v-for="item in results" :key="item.id" class="comm-search-result" @click="emit('jump',item.id)"><span><strong>{{item.sender_name}}</strong><time>{{date(item.created_at)}}</time></span><p>{{item.body||item.attachment_name}}</p></button>
</template>
<div class="comm-media-grid" v-else-if="tab==='media'"><div v-for="item in results" :key="item.id"><a :href="attachment(item)" :title="item.attachment_name||'Shared image'"><img :src="attachment(item)" :alt="item.attachment_name||'Shared image'" loading="lazy"></a><button @click="emit('jump',item.id)" :aria-label="'View message for '+item.attachment_name">{{date(item.created_at)}}</button></div></div>
<template v-else-if="tab==='docs'"><article v-for="item in results" :key="item.id" class="comm-document-card"><span class="comm-document-icon">DOC</span><div><a :href="attachment(item)">{{item.attachment_name}}</a><small>{{item.sender_name}} &middot; {{date(item.created_at)}}</small><button @click="emit('jump',item.id)">View message</button></div></article></template>
<template v-else-if="tab==='links'"><article v-for="item in results" :key="item.id" class="comm-link-card"><a v-for="link in item.links" :key="link" :href="link" target="_blank" rel="noopener noreferrer">{{link}}</a><small>{{item.sender_name}} &middot; {{date(item.created_at)}}</small><button @click="emit('jump',item.id)">View message</button></article></template>
<p class="comm-detail-hint" v-if="busy" role="status">Loading...</p><p class="comm-detail-hint" v-else-if="!results.length&&tab!=='info'">{{tab==='search'?(query.trim()?'No matching messages.':''):tab==='media'?'No shared photos yet.':tab==='docs'?'No shared documents yet.':'No shared links yet.'}}</p>
<p v-if="error" class="comm-error" role="alert">{{error}}</p><button v-if="more&&tab!=='info'" class="comm-detail-more" :disabled="busy" @click="fetchItems(true)">Load more</button>
</div>
</aside>
</template>