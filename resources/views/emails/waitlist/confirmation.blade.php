@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')

{{-- Icon circle --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#e5ead7 0%,#c8d5aa 100%);display:inline-flex;align-items:center;justify-content:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z" fill="#9bb068"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

{{-- Headline --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    You're on the list 🌿
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    Something good is coming your way.
</div>

{{-- Greeting --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#1f160f;margin-bottom:20px;">
    Hi <strong style="color:#4b3425;">{{ $firstName }}</strong>,
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Thank you for your interest in {{ config('app.name') }}. We're currently in a quiet testing phase — ironing out the small things so your first experience feels exactly right.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:32px;">
    We'll send you a personal invite as soon as your spot is ready. No spam, no noise — just one email when it's time.
</div>

{{-- What to expect card --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:32px;">
    <tr>
        <td style="background-color:#f7f4f2;border-radius:16px;border:1px solid #e8ddd9;padding:24px 28px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#9bb068;margin-bottom:16px;">What's waiting for you</div>

            <!-- Feature 1 -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:14px;">
                <tr>
                    <td style="vertical-align:top;width:28px;">
                        <div style="width:22px;height:22px;border-radius:50%;background-color:#9bb068;text-align:center;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:800;color:#fff;line-height:22px;">✦</div>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;line-height:1.3;">Licensed therapists, on your schedule</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;line-height:1.5;margin-top:2px;">Book 1-on-1 sessions with verified professionals at times that work for you.</div>
                    </td>
                </tr>
            </table>

            <!-- Feature 2 -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:14px;">
                <tr>
                    <td style="vertical-align:top;width:28px;">
                        <div style="width:22px;height:22px;border-radius:50%;background-color:#fe814b;text-align:center;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:800;color:#fff;line-height:22px;">✦</div>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;line-height:1.3;">AI companion for everyday moments</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;line-height:1.5;margin-top:2px;">A thoughtful, always-available guide for the in-between moments.</div>
                    </td>
                </tr>
            </table>

            <!-- Feature 3 -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="vertical-align:top;width:28px;">
                        <div style="width:22px;height:22px;border-radius:50%;background-color:#ffce5c;text-align:center;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:800;color:#4b3425;line-height:22px;">✦</div>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;line-height:1.3;">Tools that grow with you</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;line-height:1.5;margin-top:2px;">Mood tracking, journaling, soundscapes, and wellness assessments — all in one calm space.</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Position badge --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:32px;">
    <tr>
        <td align="center">
            <div style="display:inline-block;background:linear-gradient(135deg,#4b3425 0%,#6d4b36 100%);border-radius:123px;padding:12px 28px;">
                <span style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#ffffff;letter-spacing:0.3px;">
                    Waitlist position confirmed &nbsp;·&nbsp; We'll be in touch
                </span>
            </div>
        </td>
    </tr>
</table>

{{-- Note --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;text-align:center;color:#bda193;">
    Questions or can't wait? Reply to this email — a real human reads every message.
</div>

@endsection
