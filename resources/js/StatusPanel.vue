<script setup lang="ts">
import {computed} from 'vue';
import {parseChatDate} from './chatTime';
type Status={id:number;author_id?:number|null;author_name?:string;author_avatar?:string|null;title:string;body:string;expires_at:string;created_at:string;attachment_mime?:string|null;attachment_url?:string|null};
const props=defineProps<{stories:Status[];canPublish:boolean;canDelete?:boolean;deletingId?:number|null;userId:number|null;profile:{name:string;avatar_url:string|null}|null;now:number;attachment:(s:any)=>string}>();
const emit=defineEmits<{open:[story:Status];publish:[];back:[];remove:[story:Status]}>();
const mine=computed(()=>props.stories.filter(s=>s.author_id===props.userId));
const recent=computed(()=>props.stories.filter(s=>s.author_id!==props.userId));
function posted(value:string){const date=parseChatDate(value),now=new Date(props.now);const day=(d:Date)=>Date.UTC(d.getFullYear(),d.getMonth(),d.getDate());const days=Math.round((day(now)-day(date))/86400000);return (days===0?'Today':days===1?'Yesterday':date.toLocaleDateString())+' at '+date.toLocaleTimeString('en',{hour:'numeric',minute:'2-digit'});}
</script>
<template>
<div class="comm-status-layout">
<aside class="comm-status-sidebar">
<header class="comm-status-heading"><h2>Status</h2><button v-if="canPublish" aria-label="Add status update" title="Add status update" @click="emit('publish')"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M7 12h10"/></svg></button></header>
<button v-if="canPublish" class="comm-status-self" @click="emit('publish')"><span class="comm-status-avatar"><img v-if="profile?.avatar_url" :src="profile.avatar_url" alt=""><span v-else>{{profile?.name.slice(0,1)||'A'}}</span><span class="comm-status-add-badge" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span></span><span><strong>My status</strong><small>Click to add status update</small></span></button>
<template v-for="group in [{label:'My updates',items:mine},{label:'Recent updates',items:recent}]" :key="group.label"><h3 v-if="group.items.length">{{group.label}}</h3><div v-for="s in group.items" :key="s.id" class="comm-status-row" :class="{'can-delete':canDelete}"><button class="comm-status-item" @click="emit('open',s)"><span class="comm-status-avatar ring"><img v-if="s.author_avatar" :src="s.author_avatar" alt=""><img v-else-if="s.attachment_mime?.startsWith('image/')&&s.attachment_url" :src="attachment(s)" alt=""><span v-else>{{(s.author_name||'Company Admin').slice(0,1)}}</span></span><span class="comm-status-copy"><strong>{{s.author_name||'Company Admin'}}</strong><time :datetime="s.created_at">{{posted(s.created_at)}}</time></span></button><button v-if="canDelete" class="comm-status-delete" :aria-label="'Delete status from '+(s.author_name||'Company Admin')" title="Delete status" :disabled="deletingId!=null" @click="emit('remove',s)"><span v-if="deletingId===s.id" class="comm-spinner" aria-hidden="true"></span><svg v-else width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/></svg></button></div></template>
<p v-if="!stories.length" class="comm-status-no-updates">No recent updates. Company statuses will appear here.</p>
<footer>Company updates disappear after 24 hours.</footer>
</aside>
<section class="comm-status-welcome"><svg width="66" height="66" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"><path d="M26 8a25 25 0 0 0-18 23m1 12a25 25 0 0 0 41 7m6-16A25 25 0 0 0 39 8"/><circle cx="32" cy="32" r="17" fill="currentColor" stroke="none"/></svg><h2>Share statuses</h2><p>Share photos, videos and text that disappear after 24 hours.</p><small>Company updates from your admins, all in one place.</small></section>
</div>
</template>