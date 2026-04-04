@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')

{{-- Icon circle --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#e5ead7 0%,#c8d5aa 100%);display:inline-flex;align-items:center;justify-content:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z" fill="#9bb068"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

{{-- Headline --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Welcome to {{ config('app.name') }} 🌿
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    Your wellbeing journey starts here.
</div>

{{-- Greeting --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#1f160f;margin-bottom:20px;">
    Hello <strong style="color:#4b3425;">{{ $name ?? 'there' }}</strong>,
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    We're genuinely glad you're here. {{ config('app.name') }} is a calm, modern space built for mental wellness — crafted with care so that support feels less clinical and more human. Whether you're here to unwind, track your growth, or connect with a therapist, we've got you.
</div>

{{-- Steps card --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:32px;">
    <tr>
        <td style="background-color:#f7f4f2;border-radius:16px;border:1px solid #e8ddd9;padding:24px 28px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#9bb068;margin-bottom:16px;">Getting started</div>

            <!-- Step 1 -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:14px;">
                <tr>
                    <td style="vertical-align:top;width:28px;">
                        <div style="width:22px;height:22px;border-radius:50%;background-color:#9bb068;text-align:center;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:800;color:#fff;line-height:22px;">1</div>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;line-height:1.3;">Complete your profile</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;line-height:1.5;margin-top:2px;">Set your preferences to personalize your experience from day one.</div>
                    </td>
                </tr>
            </table>

            <!-- Step 2 -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:14px;">
                <tr>
                    <td style="vertical-align:top;width:28px;">
                        <div style="width:22px;height:22px;border-radius:50%;background-color:#fe814b;text-align:center;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:800;color:#fff;line-height:22px;">2</div>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;line-height:1.3;">Explore & breathe</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;line-height:1.5;margin-top:2px;">Try a guided soundscape or quick mindfulness session — anytime, anywhere.</div>
                    </td>
                </tr>
            </table>

            <!-- Step 3 -->
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="vertical-align:top;width:28px;">
                        <div style="width:22px;height:22px;border-radius:50%;background-color:#ffce5c;text-align:center;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:800;color:#4b3425;line-height:22px;">3</div>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;line-height:1.3;">Book a session when ready</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;line-height:1.5;margin-top:2px;">Connect with a licensed therapist on your schedule, no pressure.</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- CTA Button --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius:123px;background-color:#9bb068;">
                        <a href="{{ $loginUrl ?? config('frontend.url') }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            Open {{ config('app.name') }} →
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Note --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;text-align:center;color:#bda193;">
    Questions? Just reply to this email — a real human reads every message.
</div>

@endsection
