<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daymark daily team report — {{ $date }}</title>
    <style>
        @page {
            margin: 36px 40px 42px;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            color: #24382f;
            font-size: 11px;
            line-height: 1.6;
            margin: 0;
        }

        .masthead {
            background: #1c5948;
            color: white;
            padding: 23px 25px;
            margin-bottom: 18px;
        }

        .brand {
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #d9ef9c;
        }

        h1 {
            font-size: 23px;
            margin: 8px 0 3px;
            line-height: 1.3;
        }

        .date {
            font-size: 12px;
        }

        .summary {
            border-bottom: 1px solid #dce4db;
            padding-bottom: 13px;
            margin-bottom: 20px;
            color: #637168;
            font-size: 10px;
        }

        .company-title {
            font-size: 19px;
            border-bottom: 2px solid #1c5948;
            padding-bottom: 6px;
            margin: 22px 0 12px;
            page-break-after: avoid;
        }

        .department-title {
            background: #eaf1e4;
            padding: 10px 13px;
            margin: 18px 0 10px;
            font-size: 14px;
            page-break-after: avoid;
        }

        .department-title span {
            font-size: 10px;
            color: #64755e;
            font-weight: normal;
        }

        .person {
            border-left: 3px solid #92ad79;
            padding-left: 11px;
            margin: 16px 0 10px;
            page-break-after: avoid;
        }

        .person h3 {
            font-size: 14px;
            margin: 0;
        }

        .meta {
            color: #6a786e;
            font-size: 10px;
        }

        .section-title {
            color: #1c5948;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .6px;
            font-weight: bold;
            margin: 13px 0 4px;
            page-break-after: avoid;
        }

        .report-text {
            margin: 0 0 9px;
            overflow-wrap: anywhere;
            word-wrap: break-word;
        }

        .report-text p {
            margin: 0 0 3px;
        }

        .report-text h2 {
            font-size: 14px;
            margin: 8px 0 4px;
        }

        .report-text h3 {
            font-size: 12px;
            margin: 6px 0 3px;
        }

        .report-text ul,
        .report-text ol {
            margin: 4px 0 8px;
            padding-left: 22px;
        }

        .report-text blockquote {
            border-left: 3px solid #92ad79;
            margin: 8px 0;
            padding-left: 12px;
            color: #64755e;
        }

        .report-text a {
            color: #1c5948;
            text-decoration: underline;
        }

        .keep-together {
            page-break-inside: avoid;
        }

        .end-person {
            border-bottom: 1px solid #e5e9e1;
            margin: 17px 0;
        }

        .footnote {
            font-size: 9px;
            color: #718074;
            margin-top: 23px;
        }

        @media screen {
            body {
                max-width: 780px;
                margin: 30px auto;
                padding: 24px;
                background: #fff;
            }

            html {
                background: #f4f6f1;
            }
        }
    </style>
</head>

<body>
    <div class="masthead">
        <div class="brand">DAYMARK</div>
        <h1>Daily team report</h1>
        <div class="date">{{ $date }}</div>
    </div>
    <div class="summary">
        {{ $count }} {{ $count === 1 ? 'person' : 'people' }} reported &nbsp; | &nbsp;
        {{ number_format($hours, 2) }} hours reported &nbsp; | &nbsp;
        Prepared for {{ $viewer->name }}<br>
        Generated {{ $generatedAt }}. Includes submitted reports visible to you; private drafts are excluded.
    </div>
    @foreach ($companies as $company)
    <h2 class="company-title">{{ $company['name'] }}</h2>
    @foreach ($company['departments'] as $department)
    <h2 class="department-title">{{ $department['name'] }} <span>— {{ $department['reports']->count() }} report(s)</span></h2>
    @foreach ($department['reports'] as $report)
    @php
    $reportText = implode("\n", [$report->sectionText('completed'), $report->sectionText('progress'), $report->sectionText('plan'), $report->sectionText('blockers'), $report->feedback]);
    $keepTogether = mb_strlen($reportText) < 1800 && substr_count($reportText, "\n" ) < 20;
        @endphp
        <div class="person-report {{ $keepTogether ? 'keep-together' : '' }}">
        <div class="person">
            <h3>{{ $report->user->name }}</h3>
            <div class="meta">{{ $report->user->designation }} &nbsp; | &nbsp; {{ str($report->status)->replace('_', ' ')->title() }} &nbsp; | &nbsp; {{ number_format($report->hours, 2) }} hours</div>
        </div>
        @foreach (['completed' => 'Completed tasks', 'progress' => 'In progress', 'plan' => 'Next plan', 'blockers' => 'Hurdles / blockers'] as $field => $label)
        <div class="section-title">{{ $label }}</div>
        <div class="report-text">
            {!! $report->sectionHtml($field) !!}
        </div>
        @endforeach
        @if ($report->feedback)
        <div class="section-title">Reviewer feedback</div>
        <div class="report-text">
            @foreach (preg_split('/\r\n|\r|\n/', $report->feedback) as $line)
            <p>{{ $line }}</p>
            @endforeach
        </div>
        @endif
        <div class="end-person"></div>
        </div>
        @endforeach
        @endforeach
        @endforeach
        <div class="footnote">Daymark · Make progress visible. This report is a snapshot of the selected day at the time of download.</div>
</body>

</html>