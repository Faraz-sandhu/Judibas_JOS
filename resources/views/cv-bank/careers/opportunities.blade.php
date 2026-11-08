<section class="career-opportunities" id="opportunities">
    <header class="section-heading"><div><p class="eyebrow">FIND YOUR TEAM</p><h2>{{ $settings['jobs_heading'] }}</h2></div><p>{{ $settings['jobs_intro'] }}</p></header>
    <div class="opportunities-{{ $presentation }}">
    @forelse($jobs as $job)
        @if($presentation === 'accordion')
        <details class="role-accordion" data-job-card data-search="{{ strtolower($job->title.' '.$job->department.' '.implode(' ', $job->locations)) }}" data-department="{{ $job->department }}">
            <summary><span class="role-number">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><span><small>{{ $job->department }}</small><strong>{{ $job->title }}</strong></span><span class="accordion-plus" aria-hidden="true">+</span></summary>
            <div class="accordion-content"><p>{{ \Illuminate\Support\Str::limit($job->description ?: 'Bring your skills and a fresh perspective to our team.',180) }}</p><p class="role-meta">{{ implode(' / ', $job->locations) ?: \Illuminate\Support\Str::headline($job->work_mode) }} &middot; {{ \Illuminate\Support\Str::headline($job->employment_type) }}</p><a class="btn" href="{{ $job->detail_url }}">View details <span>&rarr;</span></a></div>
        </details>
        @elseif(in_array($presentation, ['editorial','compact','directory']))
        <article class="role-row" data-job-card data-search="{{ strtolower($job->title.' '.$job->department.' '.implode(' ', $job->locations)) }}" data-department="{{ $job->department }}">
            <span class="role-number">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><div class="role-row-copy"><span class="job-tag">{{ $job->department }}</span><h3>{{ $job->title }}</h3><small class="role-meta">{{ implode(' / ', $job->locations) ?: \Illuminate\Support\Str::headline($job->work_mode) }}</small><p>{{ \Illuminate\Support\Str::limit($job->description ?: 'Bring your skills and a fresh perspective to our team.',180) }}</p></div><a class="row-apply" href="{{ $job->detail_url }}"><span>View details</span> &nearr;</a>
        </article>
        @else
        <article class="job-card" data-job-card data-search="{{ strtolower($job->title.' '.$job->department.' '.implode(' ', $job->locations)) }}" data-department="{{ $job->department }}">
            <div class="job-top"><span class="job-tag">{{ $job->department }}</span><span class="job-arrow" aria-hidden="true">&nearr;</span></div><h3>{{ $job->title }}</h3><small class="role-meta">{{ implode(' / ', $job->locations) ?: \Illuminate\Support\Str::headline($job->work_mode) }}</small><p>{{ \Illuminate\Support\Str::limit($job->description ?: 'Bring your skills and a fresh perspective to our team.',180) }}</p><p class="role-meta">{{ implode(' / ', $job->locations) ?: \Illuminate\Support\Str::headline($job->work_mode) }} &middot; {{ \Illuminate\Support\Str::headline($job->employment_type) }}</p><a class="job-apply" href="{{ $job->detail_url }}">View details <span>&rarr;</span></a>
        </article>
        @endif
    @empty
        <div class="no-jobs"><h3>Great conversations come before great opportunities.</h3><p>We have no open positions right now. Send a general application to your preferred department so we can get to know you.</p><a class="btn" href="#apply">Introduce yourself &rarr;</a></div>
    @endforelse
    </div>
    @if($jobs->isNotEmpty())<p class="filter-empty" data-filter-empty hidden>No matching roles. Try another search or department.</p>@endif
</section>
