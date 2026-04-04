@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')
@php
    $score      = $data['health_score'] ?? 0;
    $totals     = $data['totals'] ?? [];
    $attention  = $data['attention_needed'] ?? collect();
    $objectives = $data['objectives'] ?? collect();
    $weekOf     = $data['week_of'] ?? now()->format('M j, Y');

    $scoreColor = match(true) {
        $score >= 80 => '#9bb068',
        $score >= 50 => '#ffce5c',
        default      => '#fe814b',
    };
    $scoreEmoji = match(true) {
        $score >= 80 => '🟢',
        $score >= 50 => '🟡',
        default      => '🔴',
    };
@endphp

<p style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:26px;color:#4b3425;margin:0 0 8px;">
    Hi {{ $notifiable->first_name ?? 'there' }},
</p>
<p style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;line-height:24px;color:#6d4b36;margin:0 0 28px;">
    Here's your weekly OKR snapshot for <strong>{{ $quarter }}</strong> — week of {{ $weekOf }}.
</p>

<!-- Company Health Score hero -->
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td align="center" style="padding:32px;background-color:{{ $scoreColor }}18;border-radius:16px;border:1.5px solid {{ $scoreColor }}40;">
            <div style="font-size:32px;margin-bottom:8px;">{{ $scoreEmoji }}</div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:{{ $scoreColor }};margin-bottom:4px;">
                Company Health Score
            </div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:52px;font-weight:800;color:{{ $scoreColor }};line-height:1;">
                {{ round($score) }}
            </div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#6d4b36;margin-top:4px;">out of 100</div>
        </td>
    </tr>
</table>

<!-- KR Breakdown -->
<p style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;color:#6d4b36;text-transform:uppercase;letter-spacing:0.5px;margin:0 0 12px;">Key Result Breakdown</p>
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td width="33%" align="center" style="padding:16px 8px;background-color:#9bb06818;border-radius:12px 0 0 12px;border:1px solid #9bb06840;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:28px;font-weight:800;color:#9bb068;">{{ $totals['on_track'] ?? 0 }}</div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:700;color:#9bb068;text-transform:uppercase;letter-spacing:0.5px;margin-top:2px;">On Track</div>
        </td>
        <td width="33%" align="center" style="padding:16px 8px;background-color:#ffce5c18;border-top:1px solid #ffce5c40;border-bottom:1px solid #ffce5c40;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:28px;font-weight:800;color:#d4a800;">{{ $totals['at_risk'] ?? 0 }}</div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:700;color:#d4a800;text-transform:uppercase;letter-spacing:0.5px;margin-top:2px;">At Risk</div>
        </td>
        <td width="33%" align="center" style="padding:16px 8px;background-color:#fe814b18;border-radius:0 12px 12px 0;border:1px solid #fe814b40;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:28px;font-weight:800;color:#fe814b;">{{ $totals['off_track'] ?? 0 }}</div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:700;color:#fe814b;text-transform:uppercase;letter-spacing:0.5px;margin-top:2px;">Off Track</div>
        </td>
    </tr>
</table>

@if($attention->isNotEmpty())
<!-- Needs Attention -->
<p style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;color:#6d4b36;text-transform:uppercase;letter-spacing:0.5px;margin:0 0 12px;">🚨 Needs Attention</p>
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    @foreach($attention->take(5) as $kr)
    @php
        $krHealth = is_array($kr) ? ($kr['health_status'] ?? 'at_risk') : $kr->health_status;
        $krTitle  = is_array($kr) ? ($kr['title'] ?? '') : $kr->title;
        $krObj    = is_array($kr) ? ($kr['objective']['title'] ?? '') : ($kr->objective?->title ?? '');
        $krOwner  = is_array($kr) ? ($kr['owner']['first_name'] ?? '') . ' ' . ($kr['owner']['last_name'] ?? '') : (($kr->owner?->first_name ?? '') . ' ' . ($kr->owner?->last_name ?? ''));
        $krProg   = is_array($kr) ? ($kr['progress'] ?? 0) : $kr->progress;
        $krColor  = $krHealth === 'off_track' ? '#fe814b' : '#d4a800';
        $krEmoji  = $krHealth === 'off_track' ? '🔴' : '🟡';
    @endphp
    <tr>
        <td style="padding:12px 16px;background-color:#faf8f6;border-radius:10px;border:1px solid #e8ddd9;margin-bottom:8px;display:block;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;color:#4b3425;margin-bottom:2px;">
                            {{ $krEmoji }} {{ $krTitle }}
                        </div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;color:#bda193;">
                            {{ $krObj }} &nbsp;·&nbsp; Owner: {{ trim($krOwner) ?: '—' }}
                        </div>
                    </td>
                    <td align="right" style="white-space:nowrap;">
                        <span style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:800;color:{{ $krColor }};">{{ round($krProg, 1) }}%</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    @if(!$loop->last)<tr><td style="height:6px;font-size:0;"></td></tr>@endif
    @endforeach
</table>
@endif

@if($objectives->isNotEmpty())
<!-- Objectives Summary -->
<p style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;color:#6d4b36;text-transform:uppercase;letter-spacing:0.5px;margin:0 0 12px;">Objectives Overview</p>
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    @foreach($objectives->take(6) as $obj)
    @php
        $objTitle   = is_array($obj) ? ($obj['title'] ?? '') : $obj->title;
        $objHealth  = is_array($obj) ? ($obj['health'] ?? 'on_track') : $obj->health;
        $objProg    = is_array($obj) ? ($obj['progress'] ?? 0) : $obj->progress;
        $objEmoji   = match($objHealth) { 'on_track' => '🟢', 'at_risk' => '🟡', default => '🔴' };
        $objBarColor = match($objHealth) { 'on_track' => '#9bb068', 'at_risk' => '#ffce5c', default => '#fe814b' };
    @endphp
    <tr>
        <td style="padding:14px 16px;background-color:#ffffff;border-radius:10px;border:1px solid #e8ddd9;margin-bottom:8px;display:block;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="padding-bottom:8px;">
                        <span style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;color:#4b3425;">
                            {{ $objEmoji }} {{ $objTitle }}
                        </span>
                        <span style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:800;color:{{ $objBarColor }};float:right;">{{ round($objProg, 1) }}%</span>
                    </td>
                </tr>
                <tr>
                    <td style="background-color:#e8ddd9;border-radius:999px;height:6px;">
                        <!--[if !mso]><!-->
                        <div style="width:{{ min(round($objProg), 100) }}%;background-color:{{ $objBarColor }};height:6px;border-radius:999px;"></div>
                        <!--<![endif]-->
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    @if(!$loop->last)<tr><td style="height:6px;font-size:0;"></td></tr>@endif
    @endforeach
</table>
@endif

<!-- CTA -->
<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin:0 auto;">
    <tr>
        <td align="center" style="border-radius:12px;background-color:#4b3425;">
            <a href="{{ config('frontend.url') }}/okr" style="display:inline-block;padding:14px 32px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px;">
                Open OKR Dashboard
            </a>
        </td>
    </tr>
</table>
@endsection
