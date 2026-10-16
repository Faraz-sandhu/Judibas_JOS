<section class="studio-hero page-container">
    <div class="hero-copy"><p class="eyebrow">{{ $settings['hero_eyebrow'] }}</p><h1>{{ $settings['headline'] }}</h1><p class="hero-intro">{{ $settings['intro'] }}</p><div class="hero-actions"><a class="btn" href="#opportunities">Find your role &rarr;</a><a class="text-link" href="#apply">Introduce yourself &nearr;</a></div><div class="hero-stats"><div><strong>{{ $jobs->count() }}</strong><span>Open opportunities</span></div><div><strong>{{ $departments->count() }}</strong><span>Teams to discover</span></div></div></div>
    <div class="studio-art" aria-hidden="true"><div class="studio-circle"></div><div class="studio-square"></div><span class="art-label">THE NEXT BIG IDEA</span><strong>Could<br>be yours.</strong><span class="art-footnote">DIFFERENT MINDS. SHARED AMBITION.</span><div class="studio-sticker">YOU + US &nearr;</div></div>
</section>
<div class="studio-ribbon"><span>CREATE WITH PURPOSE</span><span>GROW TOGETHER</span><span>BRING YOUR PERSPECTIVE</span></div>
@include('cv-bank.careers.opportunities',['presentation'=>'cards'])
@include('cv-bank.careers.application')
