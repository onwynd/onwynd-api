@extends('emails.layouts.main')

@php
    $title     = $assessment->title ?? 'Assessment';
    $severity  = $result->severity_level ?? '';
    $score     = $result->total_score ?? 0;
    $interp    = $result->interpretation ?? null;
    $recs      = array_slice((array) ($result->recommendations ?? []), 0, 3);
    $firstName = $result->user->first_name ?? 'there';

    $sevLower = strtolower($severity);
    $isSevere = str_contains($sevLower, 'severe')
             || str_contains($sevLower, 'high')
             || str_contains($sevLower, 'low well-being');

    if (str_contains($sevLower, 'severe') || $sevLower === 'high' || str_contains($sevLower, 'low well-being')) {
        $pillColor = '#fe814b';
    } elseif (str_contains($sevLower, 'moderate') || str_contains($sevLower, 'mild')) {
        $pillColor = '#ffce5c';
    } else {
        $pillColor = '#9bb068';
    }

    $ctaLabel = $isSevere ? 'Talk to a Therapist Today' : 'Book a Session';
    $ctaColor = $isSevere ? '#fe814b' : '#9bb068';
    $ctaUrl   = config('frontend.url') . '/booking';
@endphp

@section('content')

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Your {{ $title }} results
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    Here's what your answers tell us.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Hi <strong style="color:#4b3425;">{{ $firstName }}</strong>,<br><br>
    You just completed the <strong>{{ $title }}</strong>. We've put together a personalised summary of your results below — take a moment to read through it.
</div>

{{-- Score card --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:1px solid #e8ddd9;border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:24px 28px;background-color:#f7f4f2;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="vertical-align:middle;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:600;color:#bda193;letter-spacing:0.6px;text-transform:uppercase;margin-bottom:4px;">Your Score</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:40px;font-weight:800;color:#4b3425;line-height:1;">{{ $score }}</div>
                    </td>
                    <td style="vertical-align:middle;text-align:right;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:600;color:#bda193;letter-spacing:0.6px;text-transform:uppercase;margin-bottom:8px;">Result</div>
                        <span style="display:inline-block;padding:7px 18px;border-radius:50px;background-color:{{ $pillColor }};font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#ffffff;letter-spacing:0.3px;">
                            {{ $severity }}
                        </span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Interpretation --}}
@if($interp)
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;font-weight:700;color:#4b3425;margin-bottom:10px;">
    What this means
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;line-height:26px;color:#3d2e22;margin-bottom:28px;padding:18px 22px;background-color:#f7f4f2;border-radius:12px;border-left:3px solid {{ $pillColor }};">
    {{ \Illuminate\Support\Str::limit($interp, 400) }}
</div>
@endif

{{-- Recommendations --}}
@if(!empty($recs))
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;font-weight:700;color:#4b3425;margin-bottom:12px;">
    Suggested next steps
</div>
@foreach($recs as $rec)
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:8px;">
    <tr>
        <td style="width:18px;vertical-align:top;padding-top:7px;">
            <div style="width:7px;height:7px;border-radius:50%;background-color:{{ $pillColor }};"></div>
        </td>
        <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;line-height:26px;color:#3d2e22;">
            {{ is_array($rec) ? ($rec['text'] ?? $rec['recommendation'] ?? '') : $rec }}
        </td>
    </tr>
</table>
@endforeach
<div style="height:20px;"></div>
@endif

{{-- Urgent callout for severe results --}}
@if($isSevere)
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:1px solid #fde5d8;border-radius:12px;overflow:hidden;">
    <tr>
        <td style="padding:16px 20px;background-color:#fff8f5;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;line-height:22px;color:#8b3a1a;">
                <strong>You don't have to carry this alone.</strong> Speaking with a therapist — even once — can make a real difference. We're here to make that as easy as possible.
            </div>
        </td>
    </tr>
</table>
@endif

{{-- CTA --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:32px;">
    <tr>
        <td align="center">
            <a href="{{ $ctaUrl }}"
               style="display:inline-block;padding:16px 40px;background-color:{{ $ctaColor }};border-radius:50px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:800;color:#ffffff;text-decoration:none;letter-spacing:0.3px;">
                {{ $ctaLabel }} →
            </a>
        </td>
    </tr>
</table>

{{-- Trust note --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:1px solid #e8ddd9;border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:18px 24px;background-color:#f7f4f2;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;color:#926247;text-align:center;">
                🔒 &nbsp;Your results are private. &nbsp;|&nbsp; 💚 &nbsp;Confidential & judgment-free. &nbsp;|&nbsp; 📋 &nbsp;Not a clinical diagnosis.
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;line-height:24px;color:#926247;">
    You can <a href="{{ config('frontend.url') }}/dashboard/assessments" style="color:#9bb068;text-decoration:none;font-weight:600;">view your full history</a> anytime in your dashboard. If you have questions, just reply to this email — we're here.
</div>

@endsection
