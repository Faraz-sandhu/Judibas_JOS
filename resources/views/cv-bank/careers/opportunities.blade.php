<section class="career-opportunities" id="opportunities">
    <header class="section-heading"><div><p class="eyebrow">FIND YOUR TEAM</p><h2>{{ $settings['jobs_heading'] }}</h2></div><p>{{ $settings['jobs_intro'] }}</p></header>
    <div class="opportunities-{{ $presentation }}">
    @forelse($jobs as $job)
        @if($presentation === 'accordion')
        <details class="role-accordion" data-job-card data-search="{{ strtolower($job->title.' '.$job->department) }}" data-department="{{ $job->department }}">
            <summary><span class="role-number">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><span><small>{{ $job->department }}</small><strong>{{ $job->title }}</strong></span><span class="accordion-plus" aria-hidden="true">+</span></summary>
            <div class="accordion-content"><p>{{ $job->description ?: 'Bring your skills and a fresh perspective to our team.' }}</p><button type="button" class="btn" data-job="{{ $job->id }}" data-department="{{ $job->department }}">Apply for this role <span>&rarr;</span></button></div>
        </details>
        @elseif(in_array($presentation, ['editorial','compact','directory']))
        <article class="role-row" data-job-card data-search="{{ strtolower($job->title.' '.$job->department) }}" data-department="{{ $job->department }}">
            <span class="role-number">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><div class="role-row-copy"><span class="job-tag">{{ $job->department }}</span><h3>{{ $job->title }}</h3><p>{{ $job->description ?: 'Bring your skills and a fresh perspective to our team.' }}</p></div><button type="button" class="row-apply" data-job="{{ $job->id }}" data-department="{{ $job->department }}"><span>Apply</span> &nearr;</button>
        </article>
        @else
        <article class="job-card" data-job-card data-search="{{ strtolower($job->title.' '.$job->department) }}" data-department="{{ $job->department }}">
            <div class="job-top"><span class="job-tag">{{ $job->department }}</span><span class="job-arrow" aria-hidden="true">&nearr;</span></div><h3>{{ $job->title }}</h3><p>{{ $job->description ?: 'Bring your skills and a fresh perspective to our team.' }}</p><button type="button" class="job-apply" data-job="{{ $job->id }}" data-department="{{ $job->department }}">Apply for this role <span>&rarr;</span></button>
        </article>
        @endif
    @empty
        <div class="no-jobs"><h3>Great conversations come before great opportunities.</h3><p>We have no open positions right now. Send a general application to your preferred department so we can get to know you.</p><a class="btn" href="#apply">Introduce yourself &rarr;</a></div>
    @endforelse
    </div>
    @if($jobs->isNotEmpty())<p class="filter-empty" data-filter-empty hidden>No matching roles. Try another search or department.</p>@endif
</section>
