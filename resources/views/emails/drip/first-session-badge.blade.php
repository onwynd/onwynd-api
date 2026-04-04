@extends('emails.layouts.main')

@section('content')

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#fff3d4 0%,#ffce5c 100%);display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M12 1L9 9H1l6 5-2 8 7-5 7 5-2-8 6-5h-8z" fill="#c89b00"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    First Session — unlocked
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    That took courage. You should be proud.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Hey <strong style="color:#4b3425;">{{ $user->first_name }}</strong>,<br><br>
    Completing your first therapy session is no small thing. It means you showed up for yourself — and that's the hardest part. You've earned your <strong style="color:#4b3425;">First Session badge</strong>, and we genuinely mean it when we say: we're proud of you.
</div>

{{-- Badge card --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:2px solid #ffce5c;border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:24px;background:linear-gradient(135deg,#fff9e6 0%,#fffdf5 100%);text-align:center;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;margin:0 auto 12px;">
                <path d="M12 1L9 9H1l6 5-2 8 7-5 7 5-2-8 6-5h-8z" fill="#ffce5c"/>
            </svg>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:18px;font-weight:800;color:#4b3425;">First Session</div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;margin-top:6px;">Awarded for completing your first therapy session on {{ config('app.name') }}</div>
        </td>
    </tr>
</table>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius:123px;background-color:#9bb068;">
                        <a href="{{ $ctaUrl }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            View My Achievements
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@endsection
