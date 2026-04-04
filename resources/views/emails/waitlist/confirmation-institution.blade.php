@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')

@php
    $typeLabels = [
        'company'    => 'your company',
        'university' => 'your institution',
        'hospital'   => 'your facility',
        'ngo'        => 'your organisation',
    ];
    $typeLabel = $typeLabels[$institutionType ?? ''] ?? 'your organisation';
    $orgDisplay = $organizationName ? " — <strong style='color:#4b3425;'>{$organizationName}</strong>" : '';
@endphp

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#e5ead7 0%,#c8d5aa 100%);display:inline-flex;align-items:center;justify-content:center;margin:0 auto;font-size:34px;line-height:72px;text-align:center;">
                🏛️
            </div>
        </td>
    </tr>
</table>

{{-- Headline --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    You're on the list 🌿
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    Wellness at scale — coming to {!! $typeLabel !!}.
</div>

{{-- Greeting --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#1f160f;margin-bottom:20px;">
    Hi <strong style="color:#4b3425;">{{ $firstName }}</strong>,
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Thank you for registering interest in Onwynd for {!! $typeLabel . $orgDisplay !!}. We're building an enterprise-grade mental wellness solution for organisations that take their people's wellbeing seriously — and we're glad you reached out.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:32px;">
    One of our team members will be in touch personally to understand your needs and walk you through how Onwynd can work for your organisation.
</div>

{{-- What's included --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:32px;">
    <tr>
        <td style="background-color:#f7f4f2;border-radius:16px;border:1px solid #e8ddd9;padding:24px 28px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#9bb068;margin-bottom:16px;">What Onwynd brings to your organisation</div>

            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:14px;">
                <tr>
                    <td style="vertical-align:top;width:28px;">
                        <div style="width:22px;height:22px;border-radius:50%;background-color:#9bb068;text-align:center;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:800;color:#fff;line-height:22px;">✦</div>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;line-height:1.3;">Team-wide mental wellness access</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;line-height:1.5;margin-top:2px;">Give every member of your team access to licensed therapists, AI support, and wellness tools — without the overhead.</div>
                    </td>
                </tr>
            </table>

            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:14px;">
                <tr>
                    <td style="vertical-align:top;width:28px;">
                        <div style="width:22px;height:22px;border-radius:50%;background-color:#fe814b;text-align:center;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:800;color:#fff;line-height:22px;">✦</div>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;line-height:1.3;">Aggregated wellness insights</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;line-height:1.5;margin-top:2px;">Anonymous, HIPAA-aligned reporting so leadership can understand wellbeing trends without compromising privacy.</div>
                    </td>
                </tr>
            </table>

            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="vertical-align:top;width:28px;">
                        <div style="width:22px;height:22px;border-radius:50%;background-color:#ffce5c;text-align:center;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:800;color:#4b3425;line-height:22px;">✦</div>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;line-height:1.3;">Dedicated onboarding &amp; account support</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;line-height:1.5;margin-top:2px;">We handle setup, rollout communication, and ongoing support — so your HR team doesn't have to.</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- What happens next --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:32px;">
    <tr>
        <td style="background-color:#4b3425;border-radius:16px;padding:24px 28px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#9bb068;margin-bottom:16px;">What happens next</div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:rgba(255,255,255,0.8);line-height:24px;">
                1 &nbsp;·&nbsp; Our team reviews your submission (within 1–2 business days)<br>
                2 &nbsp;·&nbsp; We reach out to schedule a discovery call<br>
                3 &nbsp;·&nbsp; Custom onboarding plan built around your organisation<br>
                4 &nbsp;·&nbsp; Pilot launch &amp; full rollout
            </div>
        </td>
    </tr>
</table>

{{-- Note --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;text-align:center;color:#bda193;">
    Need to speak with someone sooner? Reply to this email and we'll prioritise your enquiry.
</div>

@endsection
