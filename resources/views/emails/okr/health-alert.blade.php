@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')
@php
    $emoji = match($newHealth) {
        'on_track'  => '🟢',
        'at_risk'   => '🟡',
        'off_track' => '🔴',
        default     => '⚪',
    };
    $statusLabel = str_replace('_', ' ', $newHealth);
    $oldLabel    = str_replace('_', ' ', $oldHealth);
    $badgeColor  = match($newHealth) {
        'on_track'  => '#9bb068',
        'at_risk'   => '#ffce5c',
        'off_track' => '#fe814b',
        default     => '#bda193',
    };
    $progress = round($progress, 1);
@endphp

<!-- Greeting -->
<p style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:26px;color:#4b3425;margin:0 0 20px;">
    Hi {{ $notifiable->first_name ?? 'there' }},
</p>

<!-- Hero badge -->
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td align="center" style="padding:28px;background-color:{{ $badgeColor }}18;border-radius:16px;border:1.5px solid {{ $badgeColor }}40;">
            <div style="font-size:36px;margin-bottom:10px;">{{ $emoji }}</div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:{{ $badgeColor }};margin-bottom:6px;">
                OKR Health Alert
            </div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:22px;font-weight:800;color:#4b3425;line-height:1.2;">
                {{ $kr->title }}
            </div>
        </td>
    </tr>
</table>

<!-- Status change -->
<p style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;line-height:24px;color:#4b3425;margin:0 0 24px;">
    The health status of this key result has changed:
</p>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td align="center" style="padding:20px;background-color:#faf8f6;border-radius:12px;border:1px solid #e8ddd9;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:600;color:#6d4b36;padding:0 16px 0 0;">
                        {{ ucfirst(str_replace('_', ' ', $oldLabel)) }}
                    </td>
                    <td style="font-size:20px;color:#bda193;padding:0 16px;">→</td>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:800;color:{{ $badgeColor }};padding:0 0 0 16px;text-transform:capitalize;">
                        {{ ucfirst($statusLabel) }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Progress bar -->
<p style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;color:#6d4b36;text-transform:uppercase;letter-spacing:0.5px;margin:0 0 8px;">Current Progress</p>
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:6px;">
    <tr>
        <td style="background-color:#e8ddd9;border-radius:999px;height:10px;overflow:hidden;">
            <!--[if !mso]><!-->
            <div style="width:{{ min($progress, 100) }}%;background-color:{{ $badgeColor }};height:10px;border-radius:999px;"></div>
            <!--<![endif]-->
        </td>
    </tr>
</table>
<p style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#6d4b36;margin:0 0 24px;text-align:right;">
    <strong>{{ $progress }}%</strong> toward target
    @if($progress > 100) &nbsp;🎉 Exceeded! @endif
</p>

<!-- KR details -->
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td style="padding:20px;background-color:#faf8f6;border-radius:12px;border:1px solid #e8ddd9;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="padding-bottom:10px;border-bottom:1px solid #e8ddd9;">
                        <span style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:700;color:#bda193;text-transform:uppercase;letter-spacing:0.5px;">Objective</span><br>
                        <span style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#4b3425;font-weight:600;">{{ $kr->objective?->title ?? '—' }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding-top:10px;">
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td width="50%" style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#6d4b36;">
                                    <span style="font-weight:700;">Current:</span> {{ number_format($kr->current_value, 0) }} {{ $kr->unit }}
                                </td>
                                <td width="50%" style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#6d4b36;">
                                    <span style="font-weight:700;">Target:</span> {{ number_format($kr->target_value, 0) }} {{ $kr->unit }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if($newHealth !== 'on_track')
<p style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;line-height:24px;color:#4b3425;margin:0 0 24px;">
    @if($newHealth === 'at_risk')
    This key result is falling slightly behind pace. Consider reviewing your initiatives or reassigning resources to get back on track.
    @else
    This key result is significantly behind pace. Immediate attention is recommended — review blockers, adjust strategy, or escalate if needed.
    @endif
</p>
@else
<p style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;line-height:24px;color:#4b3425;margin:0 0 24px;">
    Great news — this key result has recovered and is back on track. Keep up the momentum! 🎉
</p>
@endif

<!-- CTA -->
<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin:0 auto 8px;">
    <tr>
        <td align="center" style="border-radius:12px;background-color:#4b3425;">
            <a href="{{ config('frontend.url') }}/okr" style="display:inline-block;padding:14px 32px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px;">
                View OKR Dashboard
            </a>
        </td>
    </tr>
</table>
@endsection
