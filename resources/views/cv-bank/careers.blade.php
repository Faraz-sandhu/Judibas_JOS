<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark">
    <title>Careers &middot; Judibas</title>
    <link rel="stylesheet" href="/careers.css?v={{ filemtime(public_path('careers.css')) }}">
</head>
<body class="theme-{{ $settings['theme'] }}">
@if($preview)<aside class="preview-banner">Design preview &mdash; your published careers page has not changed. <a href="/cv-bank">Return to CV Bank</a></aside>@endif
<header class="career-nav"><a class="brand" href="{{ $settings['header_brand_url'] }}">{{ $settings['header_brand'] }}<span class="brand-dot"></span></a><span class="nav-label">{{ $settings['header_label'] }}</span><nav>@if($settings['header_show_roles'])<a href="#opportunities">{{ $settings['header_roles_label'] }} <span>{{ $jobs->count() }}</span></a>@endif @if($settings['header_show_apply'])<a class="nav-apply" href="#apply">{{ $settings['header_apply_label'] }} &nearr;</a>@endif</nav></header>
<main data-career-layout="{{ $settings['theme'] }}">
@include('cv-bank.careers.themes.'.$settings['theme'])
</main>@if($settings['footer_show'])<footer class="career-footer"><a class="brand" href="{{ $settings['header_brand_url'] }}">{{ $settings['footer_brand'] }}<span class="brand-dot"></span></a><p>{{ $settings['footer_message'] }}</p><span>{{ str_replace('{year}',date('Y'),$settings['footer_copyright']) }}</span>@if($settings['footer_link_label'] && $settings['footer_link_url'])<a class="footer-link" href="{{ $settings['footer_link_url'] }}">{{ $settings['footer_link_label'] }}</a>@endif</footer>@endif
<script>
const jobField=document.querySelector('[name="job_id"]'),departmentField=document.querySelector('[name="department"]');
jobField?.addEventListener('change',function(){const department=this.selectedOptions[0].dataset.department;if(department)departmentField.value=department;});
document.querySelectorAll('button[data-job]').forEach(button=>button.addEventListener('click',()=>{if(jobField){jobField.value=button.dataset.job;departmentField.value=button.dataset.department;document.querySelector('#apply').scrollIntoView({behavior:'smooth'});jobField.focus({preventScroll:true});}}));
const roleSearch=document.querySelector('[data-role-search]');
const departmentFilter=document.querySelector('[data-department-filter]');
let selectedTeam='';
function filterRoles(){let visible=0;document.querySelectorAll('[data-job-card]').forEach(card=>{const query=(roleSearch?.value||'').toLowerCase();const department=departmentFilter?departmentFilter.value:selectedTeam;const show=(!query||card.dataset.search.includes(query))&&(!department||card.dataset.department===department);card.hidden=!show;if(show)visible++;});const empty=document.querySelector('[data-filter-empty]');if(empty)empty.hidden=visible>0;}
roleSearch?.addEventListener('input',filterRoles);departmentFilter?.addEventListener('change',()=>{selectedTeam=departmentFilter.value;document.querySelectorAll('[data-team-filter]').forEach(item=>item.setAttribute('aria-pressed',String(item.dataset.teamFilter===selectedTeam)));filterRoles();});
document.querySelectorAll('[data-team-filter]').forEach(button=>button.addEventListener('click',()=>{selectedTeam=button.dataset.teamFilter;if(departmentFilter)departmentFilter.value=selectedTeam;document.querySelectorAll('[data-team-filter]').forEach(item=>item.setAttribute('aria-pressed',String(item===button)));filterRoles();}));
</script></body></html>
