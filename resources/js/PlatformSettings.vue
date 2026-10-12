<script setup lang="ts">
import {ref,onMounted} from 'vue';
const props=defineProps<{csrf?:string}>();
const settings=ref<any>(null),busy=ref(false),error=ref(''),message=ref(''),testEmail=ref('');
async function request(path='',body?:any){const r=await fetch('/admin/api/platform-settings'+path,{method:body?'POST':'GET',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':props.csrf||''},body:body?JSON.stringify(body):undefined});const d=await r.json();if(!r.ok)throw Error(d.errors?Object.values(d.errors).flat().join(' '):d.message||'Unable to complete this action.');return d;}
async function load(){try{settings.value=await request();settings.value.mail.scheme||='smtp';}catch(e){error.value=(e as Error).message;}}
async function submit(kind:string,test=false){busy.value=true;error.value='';message.value='';try{const d=await request('/'+kind+(test?'/test':''),test?(kind==='mail'?{test_email:testEmail.value}:{}):settings.value[kind]);message.value=d.message;if(!test)await load();}catch(e){error.value=(e as Error).message;}finally{busy.value=false;}}
onMounted(load);
</script>
<template>
<div class="platform-settings"><h2>Shared product settings</h2><p class="muted">Super admin manages one email and realtime configuration for PMS, Communication, and future products.</p>
<p v-if="error" class="notice admin-error" role="alert">{{error}}</p><p v-if="message" class="notice" role="status">{{message}}</p>
<p v-if="!settings&&!error" role="status">Loading settings?</p>
<template v-if="settings">
<form class="admin-editor" @submit.prevent="submit('mail')"><h3>Email server</h3><div class="platform-fields">
<label v-for="field in ['host','username','from_name']" :key="field">{{({host:'SMTP host',username:'Username',from_name:'Sender name'} as Record<string,string>)[field]}}<input v-model="settings.mail[field]" maxlength="255"></label>
<label>Sender email<input type="email" v-model="settings.mail.from_address"></label><label>Port<input type="number" v-model.number="settings.mail.port" min="1" max="65535" required></label>
<label>Encryption<select v-model="settings.mail.scheme"><option value="smtp">STARTTLS (usually 587)</option><option value="smtps">SSL (usually 465)</option></select></label>
<label>SMTP password<input type="password" v-model="settings.mail.password" autocomplete="new-password" placeholder="Leave blank to keep saved password"><small>{{settings.mail.password_saved?'Password saved securely.':'No saved password.'}}</small></label>
<label class="check"><input type="checkbox" v-model="settings.mail.notification_emails_enabled">PMS notification emails</label><label class="check"><input type="checkbox" v-model="settings.mail.invitation_emails_enabled">Invitation emails across products</label>
<label>PMS daily sending limit<input type="number" v-model.number="settings.mail.daily_limit" min="0" max="100000"><small>0 means unlimited. This quota tracks PMS deliveries.</small></label><label>PMS warning threshold<input type="number" v-model.number="settings.mail.warning_threshold" min="0" :max="settings.mail.daily_limit"></label>
</div><button class="button primary" :disabled="busy">Save email settings</button></form>
<form class="admin-editor" @submit.prevent="submit('mail',true)"><h3>Test saved email connection</h3><label>Recipient<input type="email" v-model="testEmail" required></label><button class="button" :disabled="busy">Send test email</button></form>
<form class="admin-editor" @submit.prevent="submit('realtime')"><h3>Realtime updates</h3><label class="check"><input type="checkbox" v-model="settings.realtime.enabled">Enable live updates across products</label>
<label>Provider<select v-model="settings.realtime.provider"><option value="reverb">Laravel Reverb</option><option value="pusher">Pusher Channels</option></select></label>
<p v-if="settings.realtime.provider==='reverb'" class="muted">Uses the shared Reverb server credentials and connection in .env. Keep the Reverb server running. Switching from Pusher clears the saved Pusher credentials.</p>
<div v-else class="platform-fields"><label>App ID<input v-model="settings.realtime.app_id" required></label><label>App key<input v-model="settings.realtime.app_key" required></label><label>Cluster<input v-model="settings.realtime.cluster" required pattern="[A-Za-z0-9-]+"></label><label>App secret<input type="password" v-model="settings.realtime.app_secret" autocomplete="new-password" placeholder="Leave blank to keep saved secret"><small>{{settings.realtime.secret_saved?'Secret saved securely.':'No saved secret.'}}</small></label></div>
<p class="muted">Refresh open product tabs after changing the connection.</p><button class="button primary" :disabled="busy">Save realtime settings</button><button class="button" type="button" :disabled="busy||!settings.realtime.enabled" @click="submit('realtime',true)">Test saved connection</button></form>
<p class="muted">Cloudflare R2 stores attachments; Cloudflare TURN relays voice calls. Those credentials remain in .env and are separate from Reverb/Pusher live updates.</p>
</template></div>
</template>
<style scoped>.platform-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.platform-settings select{width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:inherit}.platform-settings .button+.button{margin-left:10px}@media(max-width:700px){.platform-fields{grid-template-columns:1fr}}</style>
