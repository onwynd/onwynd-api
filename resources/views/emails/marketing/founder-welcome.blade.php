@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')

{{-- Personal touch icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:32px;">
            <div style="width:56px;height:56px;border-radius:50%;background-color:#4b3425;display:inline-block;line-height:56px;text-align:center;margin:0 auto;">
                <svg width="26" height="26" viewBox="0 0 97 88" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <circle cx="48.5" cy="14" r="14" fill="#9bb068"/>
                    <circle cx="26.5" cy="36" r="14" fill="#fe814b"/>
                    <circle cx="70.5" cy="36" r="14" fill="#ffce5c"/>
                    <circle cx="48.5" cy="58" r="14" fill="rgba(255,255,255,0.6)"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:18px;line-height:32px;color:#3d2e22;">
    <p style="margin:0 0 20px 0;">Hi <strong style="color:#4b3425;">{{ $name }}</strong>,</p>

    <p style="margin:0 0 20px 0;">I'm Johnbosco, Founder of {{ config('app.name') }}. I wanted to personally welcome you — not just to a product, but to something we've built with real intention: a calm, modern space where mental wellness feels human, not clinical.</p>

    <p style="margin:0 0 20px 0;">We created {{ config('app.name') }} to close the gap between needing support and actually getting it. Whether that's through your own reflection, a guided session, or a conversation with a therapist — it's all here, at your pace.</p>

    <p style="margin:0 0 20px 0;">Whenever you feel like sharing feedback, ideas, or even frustrations — simply reply to this email. I read every note personally, and your perspective genuinely shapes what we build next.</p>

    <p style="margin:0 0 28px 0;">Thank you for being here. It means more than you know.</p>
</div>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:8px;">
    <tr>
        <td style="border-top:1px solid #e8ddd9;padding-top:24px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#4b3425;">Johnbosco</div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#926247;margin-top:2px;">Founder &amp; CEO, {{ config('app.name') }}</div>
        </td>
    </tr>
</table>

@endsection
