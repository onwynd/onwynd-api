@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background-color:#e5ead7;display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <span style="font-size:32px;line-height:72px;display:block;">🌿</span>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    You were so close!
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    Your session is still available. It takes just a minute to confirm.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Hi <strong style="color:#4b3425;">{{ $intent->user->first_name ?? 'there' }}</strong>,<br><br>
    We noticed you started booking
    @if($intent->therapist_name)
        a session with <strong style="color:#4b3425;">{{ $intent->therapist_name }}</strong>
    @else
        a therapy session
    @endif
    but didn't quite finish. Life gets busy — we get it.<br><br>
    Your spot hasn't been taken. Come back and take that step for yourself.
</div>

{{-- CTA --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:32px;">
    <tr>
        <td align="center">
            <a href="{{ $intent->return_url ?? config('frontend.url').'/booking' }}"
               style="display:inline-block;padding:16px 40px;background-color:#9bb068;border-radius:50px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:800;color:#ffffff;text-decoration:none;letter-spacing:0.3px;">
                Complete My Booking →
            </a>
        </td>
    </tr>
</table>

{{-- Trust note --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:1px solid #e8ddd9;border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:20px 24px;background-color:#f7f4f2;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;color:#926247;text-align:center;">
                🔒 &nbsp;Your information is safe. &nbsp;|&nbsp; 💚 &nbsp;Confidential & judgment-free. &nbsp;|&nbsp; ⚡ &nbsp;Book in under 2 minutes.
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;line-height:24px;color:#926247;margin-bottom:8px;">
    If you have questions or need help choosing a therapist, reply to this email — we're happy to help.
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;line-height:24px;color:#926247;">
    Take care of yourself,<br>
    <strong style="color:#4b3425;">The Onwynd Team</strong>
</div>

@endsection
