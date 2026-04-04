@extends('emails.layouts.main')

@section('content')

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background-color:#ffd2c2;display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="#fe814b"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Document verification issue
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    We need a little more from you to proceed.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Hello <strong style="color:#4b3425;">{{ $therapistName }}</strong>,<br><br>
    Thank you for registering with {{ config('app.name') }}. We reviewed your application, but were unable to verify some of the documents you uploaded. They may be unclear, incomplete, or not matching our requirements.
</div>

@if($reason)
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td style="background-color:#fff8f5;border-left:3px solid #fe814b;border-radius:0 8px 8px 0;padding:16px 20px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;color:#fe814b;margin-bottom:6px;">Details</div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;line-height:22px;color:#4b3425;">{{ $reason }}</div>
        </td>
    </tr>
</table>
@endif

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Please log in to your dashboard, review your uploaded documents, and re-upload the correct versions. Once updated, our team will re-evaluate your application promptly.
</div>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:20px;">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius:123px;background-color:#fe814b;">
                        <a href="{{ $link }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            Update Documents
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;text-align:center;color:#bda193;">
    Have questions? Contact us at <a href="mailto:support@onwynd.com" style="color:#fe814b;text-decoration:none;font-weight:600;">support@onwynd.com</a>
</div>

@endsection
