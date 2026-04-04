@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')

@php
    $orgLabel = $orgType === 'university' ? 'institution' : 'organisation';
@endphp

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#e5ead7 0%,#c8d5aa 100%);display:inline-block;text-align:center;line-height:72px;font-size:34px;margin:0 auto;">
                🗓️
            </div>
        </td>
    </tr>
</table>

{{-- Headline --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Your demo is on its way 🌿
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    A real person will be in touch within 1 business day.
</div>

{{-- Greeting --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#1f160f;margin-bottom:20px;">
    Hi <strong style="color:#4b3425;">{{ $contactName }}</strong>,
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Thank you for requesting a demo of Onwynd for <strong style="color:#4b3425;">{{ $companyName }}</strong>. We've received your request and our team will reach out shortly to schedule a personalised walkthrough tailored to your {{ $orgLabel }}'s needs.
</div>

{{-- What to expect --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:32px;">
    <tr>
        <td style="background-color:#f7f4f2;border-radius:16px;border:1px solid #e8ddd9;padding:24px 28px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#9bb068;margin-bottom:16px;">What happens next</div>

            @foreach([
                ['01', 'We review your request', 'A team member reads every demo request personally.'],
                ['02', 'We schedule a call', 'Expect an email within 1 business day to find a convenient time.'],
                ['03', 'Your tailored demo', 'A 30-minute live walkthrough built around your organisation\'s goals.'],
            ] as [$step, $title, $body])
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:14px;">
                <tr>
                    <td style="vertical-align:top;width:36px;">
                        <div style="width:28px;height:28px;border-radius:50%;background-color:#4b3425;text-align:center;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:800;color:#fff;line-height:28px;">{{ $step }}</div>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;line-height:1.3;">{{ $title }}</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;line-height:1.5;margin-top:2px;">{{ $body }}</div>
                    </td>
                </tr>
            </table>
            @endforeach
        </td>
    </tr>
</table>

{{-- What you'll see --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:32px;">
    <tr>
        <td style="background-color:#4b3425;border-radius:16px;padding:24px 28px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#9bb068;margin-bottom:16px;">What you'll see in the demo</div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:rgba(255,255,255,0.8);line-height:24px;">
                ✦ &nbsp;Licensed therapist network &amp; booking flow<br>
                ✦ &nbsp;AI wellness companion for your team<br>
                ✦ &nbsp;Anonymous team wellbeing insights &amp; reporting<br>
                ✦ &nbsp;Admin controls, onboarding, and integrations
            </div>
        </td>
    </tr>
</table>

{{-- Note --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;text-align:center;color:#bda193;">
    Questions before the demo? Reply to this email — we read every message.
</div>

@endsection
