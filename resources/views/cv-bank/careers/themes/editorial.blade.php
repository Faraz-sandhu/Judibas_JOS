<section class="editorial-hero page-container">
    <div class="editorial-kicker"><p class="eyebrow">{{ $settings['hero_eyebrow'] }}</p><span>THE PEOPLE EDITION / {{ date('Y') }}</span></div>
    <h1>{{ $settings['headline'] }}</h1>
    <div class="editorial-lede"><span class="editorial-monogram" aria-hidden="true">J.</span><p>{{ $settings['intro'] }}</p><a class="text-link" href="#opportunities">Discover the opportunities <span>&darr;</span></a></div>
</section>
<div class="editorial-index page-container"><span>01 / THE OPPORTUNITIES</span><span>{{ $jobs->count() }} ROLES. MANY POSSIBILITIES.</span></div>
@include('cv-bank.careers.opportunities',['presentation'=>'editorial'])
<div class="editorial-note page-container"><p>Different stories.<br><em>A shared next chapter.</em></p><a class="text-link" href="#apply">Write yours with us &nearr;</a></div>
@include('cv-bank.careers.application')
