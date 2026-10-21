<script setup lang="ts">
import PmsHierarchyProjectNode from './PmsHierarchyProjectNode.vue';
defineProps<{client:any;currentClient:number|null;currentProject:number|null;currentDepartment:number|null}>();
const emit=defineEmits(['client','project','board','preview']);
function toggle(event:Event){
 const node=event.currentTarget as HTMLDetailsElement;if(!node.open)return;
 node.parentElement?.querySelectorAll<HTMLDetailsElement>(':scope > details.pms-hierarchy-client').forEach(other=>{if(other!==node)other.open=false;});
}
</script>
<template>
    <details class="pms-tree-node pms-hierarchy-client pms-tree-client-root" name="pms-client-tree" @toggle="toggle" :open="client.id===currentClient||client.projects.some((project:any)=>project.id===currentProject)">
     <summary><i class="ti ti-address-book" aria-hidden="true"></i><button class="pms-tree-label" :title="client.name" @click.stop.prevent="emit('client',client.id)">{{client.name}}</button><button class="pms-tree-eye" :aria-label="'View client board: '+client.name" :title="'View all '+client.name+' tasks'" @click.stop.prevent="emit('preview','client',client.id)"><i class="ti ti-eye" aria-hidden="true"></i></button><i class="ti ti-chevron-right pms-tree-chevron" aria-hidden="true"></i></summary>
     <div class="pms-tree-children"><PmsHierarchyProjectNode v-for="project in client.projects" :key="project.id" :project="project" :current-project="currentProject" :current-department="currentDepartment" @project="emit('project',$event)" @board="(project,department)=>emit('board',project,department)" @preview="(scope,id)=>emit('preview',scope,id)"/><p v-if="!client.projects.length" class="small text-muted pms-tree-node">No projects yet.</p></div>
    </details>
</template>
