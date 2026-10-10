<script setup lang="ts">
import { computed, ref, reactive, onMounted, onBeforeUnmount, defineAsyncComponent } from 'vue';
const PmsWorkspace=defineAsyncComponent(()=>import('./modules/pms/PmsWorkspace.vue'));
import AdminPanel from './AdminPanel.vue';
import Communication from './modules/communication/Communication.vue';
import CommunicationOverview from './modules/communication/CommunicationOverview.vue';
import InvitationAcceptance from './modules/communication/InvitationAcceptance.vue';
type Product={slug:string;name:string;category:string;icon:string;color:string;description:string;features:string[]};
type Info={title:string;intro:string;body:string};
declare global {interface Window {portal:{name:string;tagline:string;logo:string|null;logoIcon?:string|null;products:Product[];product:Product|null;page?:Info|null;admin:boolean;settingsPage:boolean;productsPage:boolean;loginPage:boolean;employee:{name:string;email:string}|null;invitation?:{token:string;email:string;company:string;existing:boolean};posts:{title:string;slug:string;excerpt:string}[];announcement:{title:string;text:string;url:string};error:string|null;success:string|null;validationErrors:string[]}}}
const portal=reactive(window.portal);
const PmsInvitationJoin=defineAsyncComponent(()=>import('./modules/pms/PmsInvitationJoin.vue'));
const pmsWorkspace=window.location.pathname==='/pms';
const pmsInvitationJoin=window.location.pathname.startsWith('/pms/join/');
const communicationWorkspace=window.location.pathname==='/communication';
const communicationManagement=!!new URLSearchParams(location.search).get('manage');
const accountMenu=ref<HTMLDetailsElement|null>(null),accountOpen=ref(false);
function accountToggle(event:Event){accountOpen.value=(event.target as HTMLDetailsElement).open;}
function closeAccountOutside(event:PointerEvent){if(event.target instanceof Node&&!accountMenu.value?.contains(event.target)&&accountMenu.value)accountMenu.value.open=false;}
function closeAccountEscape(event:KeyboardEvent){if(event.key==='Escape'&&accountMenu.value?.open){accountMenu.value.open=false;accountMenu.value.querySelector<HTMLElement>('summary')?.focus();}}
onMounted(()=>{document.addEventListener('pointerdown',closeAccountOutside);document.addEventListener('keydown',closeAccountEscape);});
onBeforeUnmount(()=>{document.removeEventListener('pointerdown',closeAccountOutside);document.removeEventListener('keydown',closeAccountEscape);});
const logoFailed=ref(false);
const csrf=ref(document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content);
function accountUpdated(name:string,token:string){if(portal.employee)portal.employee.name=name;csrf.value=token;const meta=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');if(meta)meta.content=token;}
const theme=ref(localStorage.getItem('judibas-theme-v2')||'auto');
const systemTheme=matchMedia('(prefers-color-scheme: dark)');
const resolvedTheme=ref<'light'|'dark'>('light');
function applyTheme(){resolvedTheme.value=theme.value==='auto'?(systemTheme.matches?'dark':'light'):(theme.value==='dark'?'dark':'light');document.documentElement.dataset.theme=resolvedTheme.value;}
function setTheme(value:string){theme.value=value;localStorage.setItem('judibas-theme-v2',value);applyTheme();}
systemTheme.addEventListener('change',()=>{if(theme.value==='auto')applyTheme();});applyTheme();
const navigation=[
{title:'Products',href:'/products',path:'M3 3h6v6H3z M15 3h6v6h-6z M3 15h6v6H3z M15 15h6v6h-6z'},
{title:'About Judibas',href:'/about',path:'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18 M3 12h18 M12 3c-5 6-5 12 0 18 M12 3c5 6 5 12 0 18'},
{title:'Our values',href:'/values',path:'m12 3 3 6 6 1-4 5 1 6-6-3-6 3 1-6-4-5 6-1z'},
{title:'Company updates',href:'/blog',path:'M5 3h14v18H5z M8 7h8 M8 11h8 M8 15h5'},
{title:'Contact',href:'/contact',path:'M3 5h18v14H3z m0 0 9 7 9-7'},

{title:'Learning',href:'/products/learning',path:'M4 3h16v18H4z M7 7h10 M7 11h10 M7 15h5'},
{title:'Helpdesk',href:'/products/helpdesk',path:'M3 14v-3a9 9 0 0 1 18 0v3 M3 11h4v7H3z M17 11h4v7h-4z M17 18v3h-5'},
{title:'Our team',href:'/team',path:'M9 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M2 21v-2a7 7 0 0 1 14 0v2 M18 5a3 3 0 0 1 0 6 M22 21v-2a6 6 0 0 0-4-5'},
{title:'Administration',href:'/admin',path:'M12 3v3 M12 18v3 M3 12h3 M18 12h3 M6 6l2 2 M16 16l2 2 M6 18l2-2 M16 8l2-2 M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8'}
];
const groups=computed(()=>[
{title:'Business apps',items:portal.products.filter(p=>!['framework','builder','drive','gameplan','press'].includes(p.slug))},
{title:'Developer tools',items:portal.products.filter(p=>['framework','builder'].includes(p.slug))},
{title:'Productivity tools',items:portal.products.filter(p=>['drive','gameplan'].includes(p.slug))},
{title:'Infrastructure',items:portal.products.filter(p=>p.slug==='press')}
].filter(g=>g.items.length));
const productIcons:Record<string,{color:string;path:string}>={
erp:{color:'#0087e0',path:'M5 4h14 M5 4v16h14 M5 12h10'},
crm:{color:'#dd00dc',path:'M3 4h18l-7 8v7l-4 2v-9z'},
hr:{color:'#00a783',path:'M8 4h6a4 4 0 0 1 0 8H8 M6 21c2-5 10-5 12 0 M8 4v8'},
learning:{color:'#006951',path:'M3 4c4-1 7 0 9 2 2-2 5-3 9-2v15c-4-1-7 0-9 2-2-2-5-3-9-2z M12 6v15'},
insights:{color:'#109da6',path:'M3 9h5v11H3z M10 4h5v16h-5z M17 7h4v13h-4z'},
helpdesk:{color:'#7435e6',path:'M3 4h18v6a3 3 0 0 0 0 6v4H3v-4a3 3 0 0 0 0-6z M8 8h8'},
lending:{color:'#529651',path:'m3 8 9-5 9 5 M3 10h18 M5 10v9 M12 10v9 M19 10v9 M3 21h18'},
framework:{color:'#777b84',path:'m12 3 9 5v9l-9 5-9-5V8z M3 8l9 5 9-5 M12 13v9 M8 5l9 5'},
builder:{color:'#0750ad',path:'M3 5h9 M3 10h4 M12 4v16l4-5h5z'},
drive:{color:'#047b98',path:'M3 6h7l2 3h9v11H3z'},
gameplan:{color:'#e27924',path:'M3 4h18v13H9l-6 4z M7 8h10 M7 12h7'},
press:{color:'#414c5c',path:'M3 4h18v6H3z M3 14h18v6H3z M7 7h1 M7 17h1'},
projects:{color:'#8655cc',path:'M4 4h16v16H4z M4 9h16 M9 9v11'},
inventory:{color:'#37956d',path:'m12 3 9 5v9l-9 5-9-5V8z M3 8l9 5 9-5 M12 13v9'},
accounts:{color:'#438bad',path:'M5 3h14v18H5z M8 7h8 M8 12h2 M14 12h2 M8 17h2 M14 17h2'}
};
const productVisual=(slug:string)=>productIcons[slug]||{color:'#777',path:'M4 4h16v16H4z'};

const productIndex=(slug:string)=>portal.products.findIndex(p=>p.slug===slug)+1;
const contentGroups=[
{title:'II. About '+portal.name,items:[{name:'Story',description:'A shared home for the way our company works',href:'/about'},{name:'Team',description:'The people behind our work',href:'/team'},{name:'Values',description:'The principles that guide us',href:'/values'}]},
{title:'III. Publications',items:[{name:'Blog',description:'Stories and updates from our company',href:'/blog'}]},
{title:'IV. Get in touch',items:[{name:'Contact',description:'Connect with your company administrators',href:'/contact'}]}
];
const title=computed(()=>portal.settingsPage?'Administration':portal.product?.name||portal.page?.title||(portal.productsPage?'Products':'Home'));
</script>
<template>
<a class="skip-link" href="#main">Skip to content</a>
<aside v-if="!communicationWorkspace&&!pmsWorkspace" class="rail" aria-label="Site navigation"><a href="/" class="rail-brand" :aria-label="portal.name+' home'"><img v-if="portal.logo&&!logoFailed" @error="logoFailed=true" :src="portal.logoIcon || portal.logo" :class="{'company-logo': !!portal.logoIcon}" alt=""><span v-else>J</span><span class="rail-brand-name">{{ portal.name }}</span></a><nav><a v-for="(item,index) in navigation" :key="item.title" :href="item.href" :title="item.title" :aria-label="item.title" :class="{'rail-divider':index===5||index===8,'active':portal.productsPage&&item.title==='Products'}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="item.path"/></svg><span class="rail-label">{{ item.title }}</span><span class="rail-tooltip">{{ item.title }}</span></a></nav></aside>
<div class="site-shell" :class="{'communication-shell':communicationWorkspace,'pms-product-shell':pmsWorkspace}">
<header class="topbar"><div class="breadcrumbs"><a href="/">{{ portal.name }}</a><span>&rsaquo;</span><a v-if="portal.product" href="/products">Products</a><span v-if="portal.product">&rsaquo;</span><span>{{ title }}</span></div><details v-if="portal.employee||portal.admin" class="account-menu" ref="accountMenu" @toggle="accountToggle"><summary :aria-expanded="accountOpen" aria-controls="account-dropdown"><span class="account-name">{{ portal.admin?'Super Admin':portal.employee?.name }}</span><svg class="account-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary><div class="account-dropdown" id="account-dropdown"><p>{{ portal.admin?'Super Admin':portal.employee?.email }}</p><a href="/communication">Communication</a><a v-if="portal.admin" href="/admin">Administration</a><div class="account-theme"><span>Appearance</span><button v-for="value in ['light','dark','auto']" :key="value" @click="setTheme(value)" :aria-pressed="theme===value">{{ value }}</button></div><form action="/logout" method="post"><input type="hidden" name="_token" :value="csrf"><button>Sign out</button></form></div></details><a v-else class="top-action" href="/login">Log in <span>&rarr;</span></a></header>
<main id="main"><InvitationAcceptance v-if="portal.invitation" :invitation="portal.invitation" :csrf="csrf" :errors="portal.validationErrors"/><Communication v-else-if="communicationWorkspace" :csrf="csrf" @profile-updated="accountUpdated"/><template v-else>
<template v-if="portal.settingsPage">
<AdminPanel v-if="portal.admin" :name="portal.name" :tagline="portal.tagline" :logo="portal.logo" :csrf="csrf" :success="portal.success" :errors="portal.validationErrors"/>

</template>
<template v-else-if="portal.loginPage"><section class="admin-content"><p class="eyebrow">COMPANY WORKSPACE</p><h1>Welcome back.</h1><p class="muted">Sign in with your company email and password.</p><p v-if="portal.error" class="notice" role="alert">{{ portal.error }}</p><ul v-if="portal.validationErrors.length" class="notice" role="alert"><li v-for="error in portal.validationErrors" :key="error">{{ error }}</li></ul><form action="/login" method="post" class="settings-form"><input type="hidden" name="_token" :value="csrf"><label>Email<input name="email" type="email" required autocomplete="username"></label><label>Password<input name="password" type="password" required autocomplete="current-password"></label><button class="button primary">Sign in &rarr;</button></form></section></template>
<template v-else-if="pmsInvitationJoin"><PmsInvitationJoin :csrf="csrf"/></template><template v-else-if="pmsWorkspace"><PmsWorkspace :csrf="csrf" :theme="resolvedTheme"/></template>
<template v-else-if="portal.product?.slug==='communication'"><CommunicationOverview v-if="portal.admin&&communicationManagement" :name="portal.name" :csrf="csrf"/><template v-else>
<section class="product-hero"><p class="eyebrow">COMMUNICATION</p><h1>A space for every conversation.</h1><p class="lead">Personal chats, groups, company communities, and announcements. Together in your company workspace.</p><div class="hero-actions"><a class="button primary" :href="portal.admin?'/products/communication?manage=overview':'/communication'">Open Communication &rarr;</a></div><p class="availability-note">Sign in with your company account. Access is assigned by your administrator.</p></section>
<section class="product-section"><p class="eyebrow">YOUR COMPANY, CONNECTED</p><h2>Stay close to your team.</h2><div class="features"><article><h3>Conversations</h3><p>Message colleagues, create groups, reply to messages, and share files.</p></article><article><h3>Company communities</h3><p>Join your assigned company community, receive Super Admin announcements, and chat in your groups.</p></article><article><h3>Company stories</h3><p>Read updates published by your administrators. Stories expire after 24 hours.</p></article><article><h3>Company oversight</h3><p>Super Admin can review employee conversations and files. Reviews are logged and clearly labelled.</p></article></div></section>
</template></template>
<template v-else-if="portal.product">
<div class="product-nav"><a href="/products">{{ portal.name }} <span>/</span> {{ portal.product.name }}</a><a href="#availability">Product availability &rarr;</a></div>
<section class="product-hero"><p class="eyebrow">{{ portal.product.name }}</p><h1>{{ portal.product.description }}</h1><p class="lead">Simple tools. A familiar workspace. Built around your company.</p><div class="hero-actions"><a v-if="portal.product.slug==='communication'" class="button primary" href="/communication">Open Communication &rarr;</a><a v-if="portal.product.slug==='projects'" class="button primary" href="/pms">Open PMS &rarr;</a><a v-else class="button primary" href="#availability">Coming soon <span>&rarr;</span></a><a class="button" href="#features">Explore features</a></div><p class="availability-note">In development for your {{ portal.name }} workspace</p></section>
<div class="product-preview" aria-label="Illustrative product interface preview"><div class="preview-top"><span class="preview-brand">{{ portal.name }}</span><span>{{ portal.product.name }} / Overview</span><span class="preview-badge">Design preview</span></div><div class="preview-body"><aside><strong>{{ portal.product.name }}</strong><span class="selected">Overview</span><span v-for="feature in portal.product.features" :key="feature">{{ feature }}</span><span>Settings</span></aside><div class="preview-workspace"><div class="preview-heading"><h3>{{ portal.product.name }} overview</h3><span class="preview-badge">Sample layout</span></div><div class="preview-stats"><div v-for="feature in portal.product.features.slice(0,3)" :key="feature"><span>{{ feature }}</span><b>&mdash;</b><small>No records yet</small></div></div><div class="preview-table"><div><strong>Your workspace</strong><span>Status</span><span>Last updated</span></div><div v-for="feature in portal.product.features" :key="feature"><span>{{ feature }}</span><span class="table-status">Planned</span><span>&mdash;</span></div></div></div></div></div>
<section class="product-section" id="features"><p class="eyebrow">EVERYDAY WORK, SIMPLIFIED</p><h2>A place for everything.</h2><p class="section-intro">Explore the capabilities planned for {{ portal.product.name }}.</p><div class="features"><article v-for="(feature,index) in portal.product.features" :key="feature"><span class="feature-number">0{{ index+1 }}</span><h3>{{ feature }}</h3><p>Part of the {{ portal.product.name }} roadmap, designed to work within your company&rsquo;s shared workspace.</p></article></div></section>
<section class="product-section availability-section" id="availability"><p class="eyebrow">YOUR COMPANY WORKSPACE</p><h2>{{ portal.product.name }} is coming soon.</h2><p>The application is being planned. Once it is ready, authorised employees will be able to open it from this page.</p><a class="button" href="/products">Explore all products <span>&rarr;</span></a></section>
<section class="product-section faq"><p class="eyebrow">GOT A QUERY?</p><h2>Frequently asked questions</h2><details><summary>Can I use this product now?</summary><p>Not yet. This is the product information page. We will build and release the application in a subsequent stage.</p></details><details><summary>Who can use this product?</summary><p>This is an internal company product. Your administrator assigns access to employees.</p></details><details><summary>How will I access the application?</summary><p>Once released, you will use your company account and the permissions assigned by your administrator.</p></details></section>
</template>
<template v-else-if="portal.productsPage">
<section class="catalogue-page"><p class="eyebrow">PRODUCTS</p><h1>Making life easier with better software</h1>
<div class="catalogue-panel"><section v-for="group in groups" :key="group.title" class="catalogue-group"><h2 class="eyebrow">{{ group.title }}</h2><div class="catalogue-grid"><a v-for="product in group.items" :key="product.slug" :href="'/products/'+product.slug" class="catalogue-product"><span class="catalogue-icon" :style="{background:product.color!=='#f5f5f5'?product.color:productVisual(product.slug).color}"><span v-if="product.icon">{{ product.icon }}</span><svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="productVisual(product.slug).path"/></svg></span><span class="catalogue-copy"><strong>{{ product.name }}</strong><span>{{ product.description }}</span></span></a></div></section></div>
</section>
</template>

<template v-else-if="portal.page">
<article class="info-page"><p class="eyebrow">ABOUT {{ portal.name }}</p><h1>{{ portal.page.title }}</h1><p class="lead">{{ portal.page.intro }}</p><hr><p class="article-body">{{ portal.page.body }}</p><div v-if="portal.posts.length" class="blog-list"><article v-for="post in portal.posts" :key="post.slug"><h2><a :href="'/blog/'+post.slug">{{ post.title }}</a></h2><p>{{ post.excerpt }}</p><a :href="'/blog/'+post.slug">Read article &rarr;</a></article></div><a class="button" href="/products">Explore our products &rarr;</a></article>
</template>
<template v-else>
<div class="announcement"><span class="announcement-symbol">&#10035;</span><strong>{{ portal.announcement.title }}</strong><span class="announcement-slash">/</span><span>{{ portal.announcement.text }}</span><a :href="portal.announcement.url" aria-label="Homepage announcement">&rsaquo;</a></div>
<section class="home-intro"><p class="eyebrow">FRAMEWORK + APPS</p><h1>Hello, we are {{ portal.name }}!</h1><p>{{ portal.tagline }}</p><div class="intro-rule"></div></section>
<div class="directory"><section id="products"><h2>I. Products</h2><div v-for="group in groups" :key="group.title" class="directory-group"><h3 class="eyebrow">{{ group.title }}</h3><a v-for="product in group.items" :key="product.slug" :href="'/products/'+product.slug" class="directory-row"><span class="row-name">{{ product.name }}</span><span class="row-separator">|</span><span class="row-description">{{ product.description }}</span><span class="row-dots"></span><span class="row-number">{{ productIndex(product.slug) }}</span></a></div></section>
<section v-for="(group,groupIndex) in contentGroups" :key="group.title" :id="groupIndex===0?'about':groupIndex===1?'updates':'contact'"><h2>{{ group.title }}</h2><div class="directory-group"><a v-for="(item,index) in group.items" :key="item.name" :href="item.href" class="directory-row"><span class="row-name">{{ item.name }}</span><span class="row-separator">|</span><span class="row-description">{{ item.description }}</span><span class="row-dots"></span><span class="row-number">{{ portal.products.length+1+index+contentGroups.slice(0,groupIndex).reduce((sum,g)=>sum+g.items.length,0) }}</span></a></div></section>
</div>
</template>
</template></main>
<footer v-if="!communicationWorkspace&&!pmsWorkspace"><nav aria-label="Footer"><a href="/">Home</a><a href="/products">Products</a><a href="/about">About</a><a href="/blog">Blog</a><a href="/contact">Contact</a><a href="/admin">Administration</a></nav><p class="footer-quote">&ldquo;Good design is as little design as possible.&rdquo; &mdash; Dieter Rams</p><div class="theme-switch">Switch theme: <button v-for="value in ['light','dark','auto']" :key="value" @click="setTheme(value)" :aria-pressed="theme===value" :class="{active:theme===value}">{{ value }}</button></div><p class="copyright">&copy; {{ new Date().getFullYear() }} {{ portal.name }}</p></footer>
</div>
</template>