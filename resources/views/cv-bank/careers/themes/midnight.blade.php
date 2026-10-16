<section class="spotlight-hero page-container">
    <div class="spotlight-orbit" aria-hidden="true"></div><p class="eyebrow">{{ $settings['hero_eyebrow'] }}</p><h1>{{ $settings['headline'] }}</h1><p class="hero-intro">{{ $settings['intro'] }}</p><a class="btn" href="#opportunities">Explore {{ $jobs->count() }} opportunities &darr;</a>
    <div class="spotlight-people" aria-hidden="true"><span>IDEAS</span><span>+ YOU</span><span>IMPACT</span></div>
</section>
<div class="spotlight-values page-container"><article><span>01</span><h3>Make your mark.</h3><p>Bring a fresh perspective to work that matters.</p></article><article><span>02</span><h3>Find your people.</h3><p>Build alongside curious, thoughtful teammates.</p></article><article><span>03</span><h3>Keep moving forward.</h3><p>Make space for your next professional chapter.</p></article></div>
@include('cv-bank.careers.opportunities',['presentation'=>'spotlight'])
@include('cv-bank.careers.application')
