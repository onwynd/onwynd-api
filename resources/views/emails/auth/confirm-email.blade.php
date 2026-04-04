@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')

{{-- Icon circle --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background-color:#e5ead7;display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" fill="#9bb068"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

{{-- Headline --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Confirm your email
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    One small tap — then you're all set.
</div>

{{-- Body --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:32px;">
    Hi {{ $name ?? 'there' }},<br><br>
    Yes, we know — an email to confirm an email. We promise this is the last hoop. Verifying your address keeps your account secure and lets us send you only what matters.
</div>

{{-- CTA --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius:123px;background-color:#9bb068;">
                        <a href="{{ $url }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            Confirm Email Address
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Fallback link --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:20px;text-align:center;color:#bda193;margin-bottom:24px;">
    Or paste this into your browser:<br>
    <a href="{{ $url }}" style="color:#9bb068;text-decoration:none;font-weight:600;word-break:break-all;">{{ $url }}</a>
</div>

{{-- Help note --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td style="background-color:#faf8f6;border-left:3px solid #9bb068;border-radius:0 8px 8px 0;padding:16px 20px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;color:#926247;">
                Got a question or something not working? Reply to this email or reach us at
                <a href="mailto:support@onwynd.com" style="color:#fe814b;text-decoration:none;font-weight:600;">support@onwynd.com</a> — a real person will get back to you.
            </div>
        </td>
    </tr>
</table>

@endsection
