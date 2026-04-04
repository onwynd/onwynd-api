@extends('emails.layouts.main')

@section('content')

{{-- Icon: heart for couple, clock for group --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            @if($isCouple)
            <div style="width:72px;height:72px;border-radius:50%;background-color:#fce8e8;display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" fill="#e05c5c"/>
                </svg>
            </div>
            @else
            <div style="width:72px;height:72px;border-radius:50%;background-color:#e5ead7;display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z" fill="#9bb068"/>
                </svg>
            </div>
            @endif
        </td>
    </tr>
</table>

{{-- Headline --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    @if($isCouple)
        Starting in {{ $timeText }}
    @else
        Session starts in {{ $timeText }}
    @endif
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    @if($isCouple)
        Your couples therapy session is almost here.
    @else
        Don't forget — your group session is coming up.
    @endif
</div>

{{-- Body --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Hi{{ $recipientName ? ' '.e($recipientName) : '' }},<br><br>
    @if($isCouple)
        Just a reminder that your private couples therapy session begins in <strong style="color:#4b3425;">{{ $timeText }}</strong>. Take a moment to find a quiet, comfortable space where you and your partner can be present together.
    @else
        Your group session <strong style="color:#4b3425;">"{{ $session->title }}"</strong> begins in <strong style="color:#4b3425;">{{ $timeText }}</strong>. Make sure you're ready to join on time.
    @endif
</div>

{{-- Session details card --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:1px solid {{ $isCouple ? '#f0dede' : '#e8ddd9' }};border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:20px 24px;background-color:{{ $isCouple ? '#fdf8f8' : '#f7f4f2' }};">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:{{ $isCouple ? '#e05c5c' : '#9bb068' }};margin-bottom:16px;">Session details</div>
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
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#926247;">Duration</td>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;">{{ $session->duration_minutes }} minutes</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if($isCouple)
{{-- Couple-specific prep note --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td style="padding:16px 20px;background-color:#fdf8f8;border-left:3px solid #e05c5c;border-radius:0 8px 8px 0;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;color:#4b3425;">
                <strong>A small tip:</strong> Close other tabs, silence your phone, and give each other your full attention. The more present you both are, the more you'll get out of it.
            </div>
        </td>
    </tr>
</table>
@endif

{{-- CTA --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:20px;">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius:123px;background-color:{{ $isCouple ? '#e05c5c' : '#9bb068' }};">
                        <a href="{{ $joinUrl }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            Join Session
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;text-align:center;color:#bda193;">
    This session is private and confidential. Everything shared stays in the room.
</div>

@endsection
