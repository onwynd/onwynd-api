@extends('emails.layouts.main')

@section('content')

{{-- Icon --}}
@php $approved = ($status === 'approved'); @endphp
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background-color:{{ $approved ? '#e5ead7' : '#ffd2c2' }};display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                @if($approved)
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z" fill="#9bb068"/>
                </svg>
                @else
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="#fe814b"/>
                </svg>
                @endif
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Profile {{ ucfirst($status) }}
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    {{ $approved ? 'You\'re ready to start helping people.' : 'Here\'s what you can do next.' }}
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Hello <strong style="color:#4b3425;">{{ $therapistName }}</strong>,<br><br>
    @if($approved)
    Congratulations — your therapist profile has been <strong style="color:#9bb068;">approved</strong>. You can now start accepting appointments and managing your practice on {{ config('app.name') }}. We're glad to have you.
    @else
    Your therapist profile application has been <strong style="color:#fe814b;">{{ ucfirst($status) }}</strong>. @if($reason)<br><br><strong style="color:#4b3425;">Reason:</strong> {{ $reason }}<br><br>Please update your profile and re-submit your application when ready.@endif
    @endif
</div>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius:123px;background-color:{{ $approved ? '#9bb068' : '#fe814b' }};">
                        <a href="{{ $link }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            Go to Dashboard
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@endsection
