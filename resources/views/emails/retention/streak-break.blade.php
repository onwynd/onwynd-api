@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background-color:#e5ead7;display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M17 8C8 10 5.9 16.17 3.82 19.42L5.71 21l1-1.18C7.38 20.6 8.27 21 9.27 21c2.54 0 3.56-2.33 4.55-4.58C15 13.9 15.86 12 18 12v-2c-.77 0-1.45.15-2.09.39L17 8zm3-5c-.77 0-1.45.15-2.09.39L19 1l-2.28 6.26C15.37 9.23 14.47 11 12.24 11c-2.14 0-3-.87-4.2-2.19A4.48 4.48 0 0 0 4 7.5C1.79 7.5 0 9.29 0 11.5S1.79 15.5 4 15.5c1.08 0 2.06-.39 2.82-1.03L6 15c0 2.5-1.5 4-3 5l1.5 2c2-1.5 3.5-3.5 3.5-6 0-.35-.03-.69-.08-1.03C9.05 15.63 10.18 16 11.5 16c.27 0 .53-.03.79-.07L11 19H9l-1 4h8l-1-4h-1.95l1.25-3.66C16.56 14.48 18 12.23 18 9.5c0-.72-.14-1.4-.39-2.03L19 5l1 3h2L20 3z" fill="#9bb068"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Streaks break — and that's okay
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    What matters is starting again.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Hey <strong style="color:#4b3425;">{{ $user->first_name }}</strong>,<br><br>
    It's been <strong style="color:#4b3425;">{{ $daysSinceActivity }} {{ Str::plural('day', $daysSinceActivity) }}</strong> since your last activity. That's okay — really. Every day is a fresh start, and returning to your wellness practice, even once, is the most powerful thing you can do right now.
</div>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td style="background-color:#f4f7ee;border-left:3px solid #9bb068;border-radius:0 8px 8px 0;padding:16px 20px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;line-height:26px;color:#4b3425;font-style:italic;">
                Every day is a fresh start. Returning to your wellness practice, even once, is the most powerful thing you can do right now.
            </div>
        </td>
    </tr>
</table>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:1px solid #e8ddd9;border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:20px 24px;background-color:#f7f4f2;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#9bb068;margin-bottom:16px;">Ease back in — 2 minutes is enough</div>
            @foreach([['Breathing exercise', 'A simple 4-4-4 breathing cycle'], ['Mood check-in', 'Log how you feel right now'], ['One journal line', 'Write a single sentence about your day']] as $option)
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:10px;">
                <tr>
                    <td style="vertical-align:top;width:20px;padding-top:5px;"><div style="width:8px;height:8px;border-radius:50%;background-color:#9bb068;"></div></td>
                    <td style="padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;">{{ $option[0] }}</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;margin-top:2px;">{{ $option[1] }}</div>
                    </td>
                </tr>
            </table>
            @endforeach
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
                            Start Fresh Today
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@endsection
