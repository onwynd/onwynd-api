@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')

{{-- Icon circle --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#fff3e0 0%,#ffe0b2 100%);display:inline-flex;align-items:center;justify-content:center;margin:0 auto;">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;">
                    <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" fill="#fe814b"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

{{-- Headline --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Your invite is here 🎉
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    You're one of the first people to experience {{ config('app.name') }}.
</div>

{{-- Greeting --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#1f160f;margin-bottom:20px;">
    Hi <strong style="color:#4b3425;">{{ $firstName }}</strong>,
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:20px;">
    The wait is over. Your {{ config('app.name') }} account is ready — and we're genuinely excited to have you as one of our earliest members.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:32px;">
    As an early member, your experience directly shapes what {{ config('app.name') }} becomes. We'd love to hear what resonates, what feels off, and what you wish existed. Every reply goes straight to the founding team.
</div>

{{-- CTA Button --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:32px;">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius:123px;background:linear-gradient(135deg,#fe814b 0%,#c4561a 100%);">
                        <a href="{{ $signupUrl }}" style="display:inline-block;padding:18px 48px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:17px;font-weight:800;color:#ffffff;text-decoration:none;border-radius:123px;letter-spacing:0.2px;">
                            Create my account →
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Link fallback --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;text-align:center;color:#bda193;margin-bottom:32px;">
    Button not working? Copy this link:<br>
    <a href="{{ $signupUrl }}" style="color:#9bb068;word-break:break-all;">{{ $signupUrl }}</a>
</div>

{{-- Getting started card --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:32px;">
    <tr>
        <td style="background-color:#f7f4f2;border-radius:16px;border:1px solid #e8ddd9;padding:24px 28px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#9bb068;margin-bottom:16px;">Get the most from day one</div>

            <!-- Step 1 -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:14px;">
                <tr>
                    <td style="vertical-align:top;width:28px;">
                        <div style="width:22px;height:22px;border-radius:50%;background-color:#9bb068;text-align:center;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:800;color:#fff;line-height:22px;">1</div>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;line-height:1.3;">Create your account</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;line-height:1.5;margin-top:2px;">Click the button above — takes less than two minutes.</div>
                    </td>
                </tr>
            </table>

            <!-- Step 2 -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:14px;">
                <tr>
                    <td style="vertical-align:top;width:28px;">
                        <div style="width:22px;height:22px;border-radius:50%;background-color:#fe814b;text-align:center;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:800;color:#fff;line-height:22px;">2</div>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;line-height:1.3;">Take a 2-minute wellness check</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;line-height:1.5;margin-top:2px;">Helps us personalize your experience right away.</div>
                    </td>
                </tr>
            </table>

            <!-- Step 3 -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="vertical-align:top;width:28px;">
                        <div style="width:22px;height:22px;border-radius:50%;background-color:#ffce5c;text-align:center;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:800;color:#4b3425;line-height:22px;">3</div>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;line-height:1.3;">Tell us what you think</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;line-height:1.5;margin-top:2px;">Reply to this email anytime — blunt feedback is the most useful kind.</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Expiry note --}}
@if(isset($expiresAt))
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;text-align:center;color:#bda193;margin-bottom:16px;">
    This invite link expires on <strong style="color:#926247;">{{ $expiresAt }}</strong>.
</div>
@endif

{{-- Note --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;text-align:center;color:#bda193;">
    Questions? Just reply — a real human (probably the founder) reads every message.
</div>

@endsection
