@extends('emails.layouts.main')

@section('content')

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background-color:#fff3d4;display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z" fill="#c89b00"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Your trial has ended
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    Don't lose the progress you've made.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Hi <strong style="color:#4b3425;">{{ $name ?? 'there' }}</strong>,<br><br>
    Your free trial of {{ config('app.name') }} has come to an end. We hope you got a real feel for what's possible. Your data is safe and we'll hold onto it for the next 30 days — you're not starting from zero.
</div>

{{-- What you're missing --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:1px solid #e8ddd9;border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:20px 24px;background-color:#f7f4f2;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#926247;margin-bottom:16px;">Premium features on hold</div>

            @php $features = ['Unlimited therapist sessions','Advanced mood & sleep tracking','Priority 1:1 support','AI insights & journaling','Customizable wellness plans']; @endphp

            @foreach($features as $feature)
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin-bottom:10px;">
                <tr>
                    <td style="width:20px;vertical-align:top;padding-top:2px;">
                        <div style="width:16px;height:16px;border-radius:50%;background-color:#e8ddd9;text-align:center;line-height:16px;">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" fill="#bda193"/>
                            </svg>
                        </div>
                    </td>
                    <td style="padding-left:10px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#926247;">{{ $feature }}</td>
                </tr>
            </table>
            @endforeach
        </td>
    </tr>
</table>

{{-- Primary CTA --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:16px;">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius:123px;background-color:#9bb068;">
                        <a href="{{ $billingUrl ?? config('frontend.url') . '/profile/subscription' }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            Choose a Plan
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Secondary CTA --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;line-height:22px;text-align:center;color:#926247;">
    Need more time? <a href="{{ $extensionUrl ?? config('frontend.url') . '/help' }}" style="color:#fe814b;text-decoration:none;font-weight:700;">Request a trial extension</a> and we'll do our best to help.
</div>

@endsection
