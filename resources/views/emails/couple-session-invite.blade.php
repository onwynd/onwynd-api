@extends('emails.layouts.main')

@section('content')

{{-- Heart icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background-color:#fce8e8;display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" fill="#e05c5c"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    You're invited to grow together
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    A couples therapy session, just for the two of you.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Hi{{ $partnerName ? ' '.e($partnerName) : '' }},<br><br>
    <strong style="color:#4b3425;">{{ e($inviterName) }}</strong> has invited you to a private couples therapy session on {{ config('app.name') }}. This is a safe, confidential space for both of you to be heard, understood, and supported — together.<br><br>
    Your therapist will guide the conversation with care and without judgement.
</div>

{{-- Session details --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:1px solid #f0dede;border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:20px 24px;background-color:#fdf8f8;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#e05c5c;margin-bottom:16px;">Session details</div>
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#926247;padding-bottom:10px;width:110px;">Title</td>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;padding-bottom:10px;">{{ $session->title }}</td>
                </tr>
                <tr>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#926247;padding-bottom:10px;">Date &amp; Time</td>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;padding-bottom:10px;">{{ $session->scheduled_at->format('M d, Y @ H:i') }} UTC</td>
                </tr>
                <tr>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#926247;padding-bottom:10px;">Duration</td>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;padding-bottom:10px;">{{ $session->duration_minutes }} minutes</td>
                </tr>
                <tr>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#926247;">Therapist</td>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;">{{ $session->therapist->name }}</td>
                </tr>
            </table>

            @if($session->description)
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f0dede;">
                <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;margin-bottom:6px;font-weight:600;">About this session</div>
                <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;line-height:22px;color:#4b3425;">{{ $session->description }}</div>
            </div>
            @endif
        </td>
    </tr>
</table>

{{-- Reassurance note --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td style="padding:16px 20px;background-color:#fdf8f8;border-left:3px solid #e05c5c;border-radius:0 8px 8px 0;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;color:#4b3425;">
                <strong>This session is private and confidential.</strong> Only you, {{ e($inviterName) }}, and your therapist will be present. Everything shared stays in the room.
            </div>
        </td>
    </tr>
</table>

{{-- CTA --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:20px;">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius:123px;background-color:#e05c5c;">
                        <a href="{{ $joinUrl }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            Accept &amp; Join Session
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;text-align:center;color:#bda193;margin-bottom:8px;">
    No account? You can join as a guest using the link above.<br>
    If you have any concerns about attending, you can decline by simply not using this link — no explanation needed.
</div>

@endsection
