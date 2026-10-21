<script setup lang="ts">
import PmsHierarchyClientNode from './PmsHierarchyClientNode.vue';
import PmsHierarchyProjectNode from './PmsHierarchyProjectNode.vue';
import {computed,ref,onMounted,onUnmounted,watch} from 'vue';
import {pmsFetch,withoutPmsLoader} from './loading';
const showCompanies=ref(false),currentCompany=ref<number|null>(null);
const workspaceOpened=ref(false),root=ref<HTMLElement|null>(null);
const currentClient=ref<number|null>(null),currentProject=ref<number|null>(null),currentDepartment=ref<number|null>(null);
const companies=ref<any[]>([]),legacy=ref<any[]>([]),error=ref(''),loading=ref(false),loaded=ref(false);
const emit=defineEmits(['client','project','board','preview']);
// Presentation follows management access; the server continues filtering all hierarchy data.
const clients=computed(()=>companies.value.flatMap(company=>company.clients||[]).sort((a,b)=>a.name.localeCompare(b.name)));
const unassignedProjects=computed(()=>{
 const projects=[...(showCompanies.value?[]:companies.value.flatMap(company=>company.unassigned_projects||[])),...legacy.value];
 return [...new Map(projects.map(project=>[project.id,project])).values()].sort((a,b)=>a.name.localeCompare(b.name));
});
function collapse(){workspaceOpened.value=false;const details=root.value?.querySelector<HTMLDetailsElement>('.pms-client-workspace');if(details)details.open=false;}
function outside(event:PointerEvent){if(event.target instanceof Node&&!root.value?.contains(event.target))collapse();}
function escape(event:KeyboardEvent){if(event.key==='Escape')collapse();}
function selection(){
 const query=new URLSearchParams(location.search);
 currentCompany.value=query.get('board-scope')==='company'?Number(query.get('board-id'))||null:null;
 currentClient.value=(query.get('board-scope')==='client'?Number(query.get('board-id')):Number(query.get('client')))||null;
 currentProject.value=(query.get('board-scope')==='project'?Number(query.get('board-id')):Number(query.get('project')))||null;
 currentDepartment.value=Number(query.get('department'))||null;
 workspaceOpened.value=!!(currentCompany.value||currentClient.value||currentProject.value);
}
selection();
let pendingLoad:Promise<void>|null=null;
function load():Promise<void>{
 if(pendingLoad)return pendingLoad;
 loading.value=true;error.value='';
 pendingLoad=(async()=>{try{
  const response=await withoutPmsLoader(()=>pmsFetch('/pms/api/hierarchy',{headers:{Accept:'application/json'}}));
  const data=await response.json();if(!response.ok)throw Error(data.message||'Unable to load workspace.');
  showCompanies.value=data.show_companies===true;companies.value=data.companies||[];legacy.value=data.unassigned_projects||[];loaded.value=true;
 }catch(e){error.value=(e as Error).message;}finally{loading.value=false;pendingLoad=null;}})();
 return pendingLoad;
}
watch(workspaceOpened,opened=>{if(opened&&!loaded.value)void load();});
onMounted(()=>{document.addEventListener('pointerdown',outside);document.addEventListener('keydown',escape);if(workspaceOpened.value)void load();window.addEventListener('pms:hierarchy-changed',load);window.addEventListener('pms:navigation',selection);});
onUnmounted(()=>{document.removeEventListener('pointerdown',outside);document.removeEventListener('keydown',escape);window.removeEventListener('pms:hierarchy-changed',load);window.removeEventListener('pms:navigation',selection);});
</script>
<template>
 <div ref="root" class="pms-hierarchy-nav">
  <details class="pms-client-workspace" :open="workspaceOpened" @toggle="workspaceOpened=($event.currentTarget as HTMLDetailsElement).open">
   <summary :class="{active:!!currentCompany||!!currentClient||!!currentProject}"><i class="ti ti-users" aria-hidden="true"></i><span class="pms-tree-label">Client Workspace</span><i class="ti ti-chevron-right pms-tree-chevron" aria-hidden="true"></i></summary>
   <div class="pms-client-workspace-content pms-tree-children">
    <p v-if="error" class="small text-danger" role="alert">{{error}}</p>
    <p v-if="loading&&!loaded" class="small text-muted" role="status">Loading clients...</p>
    <template v-if="showCompanies">
     <details v-for="company in companies" :key="company.id" class="pms-tree-node pms-hierarchy-company" :open="company.id===currentCompany||company.clients.some((client:any)=>client.id===currentClient||client.projects.some((project:any)=>project.id===currentProject))||company.unassigned_projects.some((project:any)=>project.id===currentProject)">
      <summary><i class="ti ti-building" aria-hidden="true"></i><span class="pms-tree-label" :title="company.name">{{company.name}}</span><button class="pms-tree-eye" :aria-label="'View company board: '+company.name" @click.stop.prevent="emit('preview','company',company.id)"><i class="ti ti-eye" aria-hidden="true"></i></button><i class="ti ti-chevron-right pms-tree-chevron" aria-hidden="true"></i></summary>
      <div class="pms-tree-children"><PmsHierarchyClientNode v-for="client in company.clients" :key="client.id" :client="client" :current-client="currentClient" :current-project="currentProject" :current-department="currentDepartment" @client="emit('client',$event)" @project="emit('project',$event)" @board="(project,department)=>emit('board',project,department)" @preview="(scope,id)=>emit('preview',scope,id)"/>
       <PmsHierarchyProjectNode v-for="project in company.unassigned_projects" :key="project.id" :project="project" :current-project="currentProject" :current-department="currentDepartment" @project="emit('project',$event)" @board="(project,department)=>emit('board',project,department)" @preview="(scope,id)=>emit('preview',scope,id)"/>
       <p v-if="!company.clients.length&&!company.unassigned_projects.length" class="small text-muted pms-tree-node">No clients yet.</p>
      </div>
     </details>
    </template>
    <PmsHierarchyClientNode v-if="!showCompanies" v-for="client in clients" :key="client.id" :client="client" :current-client="currentClient" :current-project="currentProject" :current-department="currentDepartment" @client="emit('client',$event)" @project="emit('project',$event)" @board="(project,department)=>emit('board',project,department)" @preview="(scope,id)=>emit('preview',scope,id)"/>
    <details v-if="unassignedProjects.length" class="pms-tree-node pms-tree-client-root" :open="unassignedProjects.some(project=>project.id===currentProject)">
     <summary><i class="ti ti-folder" aria-hidden="true"></i><span class="pms-tree-label">Projects without a client</span><i class="ti ti-chevron-right pms-tree-chevron" aria-hidden="true"></i></summary>
     <div class="pms-tree-children"><PmsHierarchyProjectNode v-for="project in unassignedProjects" :key="project.id" :project="project" :current-project="currentProject" :current-department="currentDepartment" @project="emit('project',$event)" @board="(project,department)=>emit('board',project,department)" @preview="(scope,id)=>emit('preview',scope,id)"/></div>
    </details>
    <p v-if="loaded&&!loading&&!error&&!clients.length&&!unassignedProjects.length" class="small text-muted">No clients available.</p>
   </div>
  </details>
 </div>
</template>
<style scoped>
.pms-tree-client-root{width:100%;min-width:0;margin-bottom:5px!important}
.pms-tree-client-root>summary{padding-left:5px}
.pms-hierarchy-nav .pms-client-workspace-content.pms-tree-children{margin:6px 0 0 10px;padding-left:10px;border-left:1px solid var(--pms-border)}
.pms-hierarchy-nav :deep(.pms-tree-children .pms-tree-children){margin-left:8px;padding-left:10px;min-width:0}
.pms-hierarchy-nav :deep(.pms-tree-node::before){left:-10px;width:10px}
.pms-hierarchy-nav :deep(.pms-tree-node:last-child::after){left:-11px}
</style>
