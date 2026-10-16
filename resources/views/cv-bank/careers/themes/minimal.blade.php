<div class="minimal-workspace page-container">
    <aside class="minimal-intro"><p class="eyebrow">{{ $settings['hero_eyebrow'] }}</p><h1>{{ $settings['headline'] }}</h1><p class="hero-intro">{{ $settings['intro'] }}</p><nav aria-label="Page sections"><a href="#opportunities"><span>Open opportunities</span><b>{{ $jobs->count() }}</b></a><a href="#apply"><span>General application</span><b>&nearr;</b></a></nav><p class="minimal-footnote">GOOD WORK STARTS WITH GOOD PEOPLE.</p></aside>
    <div class="minimal-content">@include('cv-bank.careers.opportunities',['presentation'=>'compact']) @include('cv-bank.careers.application')</div>
</div>
