@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#e5ead7 0%,#c8d5aa 100%);display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M20 6h-2.18c.07-.44.18-.88.18-1.33C18 2.97 16.04 1 13.67 1c-1.21 0-2.36.54-3.17 1.39L10 2.9l-.5-.51C8.86 1.54 7.71 1 6.5 1 4.13 1 2 2.97 2 5.33c0 .45.11.89.18 1.33H0v14h24V6h-4zM14 3c.83 0 1.5.67 1.5 1.5 0 .28-.08.52-.2.75L13 7H9.18L14 3zM7.5 3c.83 0 1.5.67 1.5 1.5 0 .28-.08.52-.2.75L6.5 7H2.18L7.5 3zM2 8h9v12H2V8zm11 12V8h9v12h-9z" fill="#9bb068"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    You've earned a reward
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    Your referral just paid off — literally.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Thanks to your referral, <strong style="color:#4b3425;">{{ $referredName }}</strong> has joined {{ config('app.name') }}. That's a win for them, and a reward for you.
</div>

{{-- Reward card --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:2px solid #9bb068;border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:24px;background-color:#f4f7ee;text-align:center;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#9bb068;margin-bottom:8px;">Reward unlocked</div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:36px;font-weight:800;color:#4b3425;">{{ $rewardAmount }}</div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#926247;margin-top:6px;">{{ $rewardType }}</div>
        </td>
    </tr>
</table>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius:123px;background-color:#9bb068;">
                        <a href="{{ $rewardsLink }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            View Your Wallet
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@endsection
