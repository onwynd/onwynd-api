@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#e5ead7 0%,#c8d5aa 100%);display:inline-flex;align-items:center;justify-content:center;margin:0 auto;font-size:34px;line-height:72px;text-align:center;">
                🧠
            </div>
        </td>
    </tr>
</table>

{{-- Headline --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Welcome to the network 🌿
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    Your application is in good hands.
</div>

{{-- Greeting --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#1f160f;margin-bottom:20px;">
    Hi <strong style="color:#4b3425;">{{ $firstName }}</strong>,
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Thank you for expressing interest in joining Onwynd as a therapist or coach. We're building a carefully curated network of licensed professionals — and we're excited you want to be part of it.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:32px;">
    We review each application personally. Once we're ready to onboard the next wave of practitioners, you'll be among the first we reach out to.
</div>

{{-- What to expect --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:32px;">
    <tr>
        <td style="background-color:#f7f4f2;border-radius:16px;border:1px solid #e8ddd9;padding:24px 28px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#9bb068;margin-bottom:16px;">What joining means for your practice</div>

            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:14px;">
                <tr>
                    <td style="vertical-align:top;width:28px;">
                        <div style="width:22px;height:22px;border-radius:50%;background-color:#9bb068;text-align:center;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:800;color:#fff;line-height:22px;">✦</div>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;line-height:1.3;">Clients who are ready</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;line-height:1.5;margin-top:2px;">Our AI matches clients to therapists based on needs, fit, and availability — no cold outreach, no wasted discovery calls.</div>
                    </td>
                </tr>
            </table>

            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:14px;">
                <tr>
                    <td style="vertical-align:top;width:28px;">
                        <div style="width:22px;height:22px;border-radius:50%;background-color:#fe814b;text-align:center;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:800;color:#fff;line-height:22px;">✦</div>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;line-height:1.3;">Your schedule, your terms</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;line-height:1.5;margin-top:2px;">Set your hours, session types, and rates. You're in control of your practice.</div>
                    </td>
                </tr>
            </table>

            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="vertical-align:top;width:28px;">
                        <div style="width:22px;height:22px;border-radius:50%;background-color:#ffce5c;text-align:center;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:800;color:#4b3425;line-height:22px;">✦</div>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;line-height:1.3;">Tools built for practitioners</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;line-height:1.5;margin-top:2px;">Session notes, client progress tracking, secure messaging, and invoicing — all in one place.</div>
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
                1 &nbsp;·&nbsp; We review your application<br>
                2 &nbsp;·&nbsp; You receive a personal invite with onboarding details<br>
                3 &nbsp;·&nbsp; Complete your practitioner profile &amp; credentials<br>
                4 &nbsp;·&nbsp; Go live and start receiving matched clients
            </div>
        </td>
    </tr>
</table>

{{-- Note --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;text-align:center;color:#bda193;">
    Questions about the platform or your application? Reply to this email — a real person reads every message.
</div>

@endsection
