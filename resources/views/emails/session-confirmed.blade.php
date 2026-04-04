@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background-color:#e5ead7;display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="#9bb068"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Session confirmed
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    Your therapist accepted your request.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Hello <strong style="color:#4b3425;">{{ $patientName }}</strong>,<br><br>
    Great news — <strong style="color:#4b3425;">{{ $therapistName }}</strong> has confirmed your therapy session. You're all set. Find a quiet, comfortable space ahead of time and you'll be in great hands.
</div>

{{-- Session details --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:1px solid #e8ddd9;border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:20px 24px;background-color:#f7f4f2;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#9bb068;margin-bottom:16px;">Your session</div>
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#926247;padding-bottom:10px;width:110px;">Therapist</td>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;padding-bottom:10px;">{{ $therapistName }}</td>
                </tr>
                <tr>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#926247;">Date & Time</td>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;">{{ $sessionDateTime }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;color:#926247;margin-bottom:8px;">
    You'll receive a reminder 15 minutes before your session. If you need to reschedule or cancel, please do so at least 24 hours in advance.
</div>

@endsection
