@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')

{{-- Icon circle --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background-color:#ffd2c2;display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z" fill="#fe814b"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

{{-- Headline --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Password Reset
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    No worries — it happens to the best of us.
</div>

{{-- Body --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:32px;">
    Hi {{ $name ?? 'there' }},<br><br>
    We received a request to reset the password on your {{ config('app.name') }} account. Click the button below to set a new one. This link expires in <strong style="color:#4b3425;">60 minutes</strong>.
</div>

{{-- CTA Button --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius:123px;background-color:#fe814b;">
                        <a href="{{ $url }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            Reset My Password
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Fallback URL --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:20px;text-align:center;color:#bda193;margin-bottom:24px;">
    Or copy this link into your browser:<br>
    <a href="{{ $url }}" style="color:#fe814b;text-decoration:none;font-weight:600;word-break:break-all;">{{ $url }}</a>
</div>

{{-- Security note --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td style="background-color:#faf8f6;border-left:3px solid #e8ddd9;border-radius:0 8px 8px 0;padding:16px 20px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;color:#926247;">
                <strong style="color:#4b3425;">Didn't request this?</strong> Simply ignore this email — your password won't change. If you're worried, contact us at <a href="mailto:support@onwynd.com" style="color:#fe814b;text-decoration:none;">support@onwynd.com</a>.
            </div>
        </td>
    </tr>
</table>

@endsection
