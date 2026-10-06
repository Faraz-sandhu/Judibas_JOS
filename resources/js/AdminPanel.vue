<script setup lang="ts">
import { ref, onMounted } from 'vue';
import CommunicationManagement from './CommunicationManagement.vue';
const props=defineProps<{name:string;tagline:string;logo:string|null;csrf?:string;success?:string|null;errors?:string[]}>();
type Row=Record<string,any>;
const data=ref<Row|null>(null), tab=ref(window.location.hash==='#users'?'Users':'Dashboard'), editor=ref<Row|null>(null), error=ref(props.errors?.join(' ')||''), message=ref(props.success||''), busy=ref(false);
const tabs=['Dashboard','Blogs','Website','Products','Users','Communication','Branding'];
async function request(url:string,method='GET',body?:Row){
const response=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':props.csrf||''},body:body?JSON.stringify(body):undefined});
const result=await response.json();
if(!response.ok)throw new Error(result.errors?Object.values(result.errors).flat().join(' '):result.message||'Unable to complete this action.');
return result;
}
async function load(){try{data.value=await request('/admin/api');}catch(e){error.value=(e as Error).message;}}
onMounted(load);
function select(value:string){tab.value=value;editor.value=null;error.value='';message.value='';}
function edit(row?:Row){
error.value='';message.value='';
if(row){const copy:Row=JSON.parse(JSON.stringify(row));if(tab.value==='Users')copy.password='';editor.value=copy;return;}
editor.value=tab.value==='Blogs'?{title:'',slug:'',excerpt:'',body:'',published:false}:tab.value==='Users'?{name:'',email:'',password:'',is_active:true,company_id:'',communication_admin:false,product_slugs:[]}:{};
}
async function save(resource:string,row:Row){
busy.value=true;error.value='';message.value='';
try{const result=await request('/admin/api/'+resource+(row.id?'/'+row.id:''),'POST',row);message.value=result.message;editor.value=null;await load();}catch(e){error.value=(e as Error).message;}finally{busy.value=false;}
}
async function remove(resource:string,row:Row){
if(!confirm('Delete '+(row.title||row.name)+'? This cannot be undone.'))return;
busy.value=true;error.value='';
try{await request('/admin/api/'+resource+'/'+row.id,'DELETE');message.value='Deleted.';await load();}catch(e){error.value=(e as Error).message;}finally{busy.value=false;}
}
</script>
<template>
<section class="super-admin">
<div class="admin-heading"><div><p class="eyebrow">SUPER ADMIN</p><h1>Administration</h1><p class="muted">Manage your company website, people, and products.</p></div><a class="button" href="/">View website →</a></div>
<nav class="admin-tabs" aria-label="Administration sections"><button v-for="item in tabs" :key="item" @click="select(item)" :class="{active:tab===item}" :aria-current="tab===item?'page':undefined">{{ item }}</button></nav>
<p v-if="error" class="notice admin-error" role="alert">{{ error }}</p><p v-if="message" class="notice" role="status">{{ message }}</p><p v-if="!data&&!error" class="muted">Loading administration…</p>
<template v-if="data">
<div v-if="tab==='Dashboard'"><h2>Your workspace at a glance</h2><div class="admin-stats"><button @click="select('Blogs')"><strong>{{ data.blogs.length }}</strong><span>Articles</span></button><button @click="select('Products')"><strong>{{ data.products.length }}</strong><span>Registered products</span></button><button @click="select('Users')"><strong>{{ data.users.length }}</strong><span>Employee accounts</span></button><button @click="select('Communication')"><strong>{{ data.companies.length }}</strong><span>Company communities</span></button></div><p class="muted">Use the sections above to publish content, manage product presentation, and assign employee access.</p></div>
<template v-if="tab==='Blogs'">
<div class="admin-section-heading"><h2>Blog articles</h2><button class="button primary" @click="edit()">New article</button></div>
<form v-if="editor" @submit.prevent="save('blogs',editor)" class="admin-editor"><h3>{{ editor.id?'Edit article':'New article' }}</h3><label>Title<input v-model="editor.title" required maxlength="200"></label><label>URL slug<input v-model="editor.slug" required pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="company-update"></label><label>Excerpt<textarea v-model="editor.excerpt" maxlength="1000"></textarea></label><label>Article text<textarea v-model="editor.body" required rows="10"></textarea></label><label class="check"><input type="checkbox" v-model="editor.published">Publish article on the website</label><div class="editor-actions"><button class="button primary" :disabled="busy">Save article</button><button type="button" class="button" @click="editor=null">Cancel</button></div></form>
<div class="admin-list"><p v-if="!data.blogs.length" class="empty-state">No articles yet. Create your first company update.</p><article v-for="post in data.blogs" :key="post.id"><div><strong>{{ post.title }}</strong><p class="muted">/blog/{{ post.slug }} · {{ post.published?'Published':'Draft' }}</p></div><div class="row-actions"><a v-if="post.published" :href="'/blog/'+post.slug">View</a><button @click="edit(post)">Edit</button><button :disabled="busy" @click="remove('blogs',post)">Delete</button></div></article></div>
</template>
<template v-if="tab==='Website'">
<h2>Website content</h2><form @submit.prevent="save('announcement',data.announcement)" class="admin-editor"><h3>Homepage announcement</h3><label>Title<input v-model="data.announcement.title" required maxlength="100"></label><label>Message<input v-model="data.announcement.text" required maxlength="250"></label><label>Link within this website<input v-model="data.announcement.url" required placeholder="/about"></label><button class="button primary" :disabled="busy">Save announcement</button></form>
<h3>Company pages</h3><form v-if="editor" @submit.prevent="save('pages',editor)" class="admin-editor"><h3>Edit {{ editor.slug }}</h3><label>Page title<input v-model="editor.title" required></label><label>Introduction<textarea v-model="editor.intro"></textarea></label><label>Page text<textarea v-model="editor.body" required rows="10"></textarea></label><div class="editor-actions"><button class="button primary" :disabled="busy">Save page</button><button type="button" class="button" @click="editor=null">Cancel</button></div></form>
<div class="admin-list"><article v-for="(page,slug) in data.pages" :key="slug"><div><strong>{{ page.title }}</strong><p class="muted">/{{ slug }}</p></div><button @click="edit({...page,slug})">Edit page</button></article></div>
</template>
<template v-if="tab==='Products'">
<h2>Product catalogue</h2><p class="muted">Developers register products. Manage their public presentation here.</p>
<form v-if="editor" @submit.prevent="save('products',editor)" class="admin-editor"><h3>Edit {{ editor.name }}</h3><label>Display name<input v-model="editor.name" required maxlength="100"></label><label>Description<textarea v-model="editor.description" required></textarea></label><label>Icon symbol (optional)<input v-model="editor.icon" maxlength="16" placeholder="Leave blank for the default icon"></label><label>Icon colour<input type="color" v-model="editor.color"></label><label>Display order<input type="number" v-model.number="editor.display_order" min="0" max="10000" required></label><label class="check"><input type="checkbox" v-model="editor.visible">Visible on the website</label><div class="editor-actions"><button class="button primary" :disabled="busy">Save product</button><button type="button" class="button" @click="editor=null">Cancel</button></div></form>
<div class="admin-list"><article v-for="product in data.products" :key="product.slug"><div><strong>{{ product.name }}</strong><p class="muted">{{ product.visible?'Visible':'Hidden' }} · Order {{ product.display_order }}</p></div><button @click="edit(product)">Edit product</button></article></div>
</template>
<template v-if="tab==='Users'">
<div class="admin-section-heading"><h2>Employees and access</h2><button class="button primary" @click="edit()">New employee</button></div>
<form v-if="editor" @submit.prevent="save('users',editor)" class="admin-editor"><h3>{{ editor.id?'Edit employee':'New employee' }}</h3><label>Name<input v-model="editor.name" required maxlength="100"></label><label>Company (required)<select required v-model.number="editor.company_id"><option value="" disabled>Select a company</option><option v-for="company in data.companies" :key="company.id" :value="company.id">{{company.name}}</option></select></label><label>Email<input type="email" v-model="editor.email" required autocomplete="off"></label><label>{{ editor.id?'New password (leave blank to keep current password)':'Password' }}<input type="password" v-model="editor.password" :required="!editor.id" minlength="12" autocomplete="new-password"><small>At least 12 characters.</small></label><label class="check"><input type="checkbox" v-model="editor.is_active">Account active</label><fieldset><legend>Products this employee can access</legend><label class="check" v-for="product in data.products" :key="product.slug"><input type="checkbox" :value="product.slug" v-model="editor.product_slugs" @change="editor.communication_admin=editor.product_slugs.includes('communication')&&editor.communication_admin">{{ product.name }}</label></fieldset><label class="check"><input type="checkbox" v-model="editor.communication_admin" :disabled="!editor.product_slugs.includes('communication')">Communication Admin: can invite people and manage company groups</label><small>Communication product access is required. Community publishing, deletion, and global chat review stay with Super Admin.</small><div class="editor-actions"><button class="button primary" :disabled="busy">Save employee</button><button type="button" class="button" @click="editor=null">Cancel</button></div></form>
<div class="admin-list"><p v-if="!data.users.length" class="empty-state">No employee accounts yet.</p><article v-for="user in data.users" :key="user.id"><div><strong>{{ user.name }}</strong><p class="muted">{{ user.email }} · {{ data.companies.find((c:Row)=>c.id===user.company_id)?.name }} · {{ user.communication_admin?'Communication Admin':'Member' }} · {{ user.is_active?'Active':'Inactive' }} · {{ user.product_slugs.length }} products</p></div><div class="row-actions"><button @click="edit(user)">Edit</button><button :disabled="busy" @click="remove('users',user)">Delete</button></div></article></div>
</template>
<template v-if="tab==='Communication'"><p class="muted"><a class="button" href="/communication">Open Communication &rarr;</a></p><CommunicationManagement :csrf="csrf" @changed="load"/></template>
<template v-if="tab==='Branding'"><h2>Branding</h2><form action="/admin/settings" method="post" enctype="multipart/form-data" class="settings-form"><input type="hidden" name="_token" :value="csrf"><label>Project name<input name="name" :value="name" maxlength="80" required></label><label>Homepage introduction<input name="tagline" :value="tagline" maxlength="160" required></label><label>Company logo<input name="logo" type="file" accept="image/png,image/jpeg,image/webp"><small>PNG, JPEG or WebP. Up to 2 MB.</small></label><img v-if="logo" :src="logo" alt="Current company logo" class="admin-logo"><button class="button primary">Save branding</button></form></template>
</template>

</section>
</template>