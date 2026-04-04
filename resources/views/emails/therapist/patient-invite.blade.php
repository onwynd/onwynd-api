@component('mail::message')

{{-- Logo --}}
<div style="text-align:center;margin-bottom:24px;">
@if ($logoUrl)
  <img src="{{ $logoUrl }}" alt="{{ config('app.name', 'Onwynd') }}" style="height:40px;object-fit:contain;">
@else
  <div style="font-size:22px;font-weight:700;color:#2A7A6A;">{{ config('app.name', 'Onwynd') }}</div>
@endif
</div>

# You've been invited to Onwynd

Hi there,

**{{ $therapistName }}**, a licensed therapist on the Onwynd platform, has personally invited you to begin your wellness journey together.

@if ($invite->message)
> *"{{ $invite->message }}"*
> — {{ $therapistName }}
@endif

Onwynd is a mental wellness platform that connects you with qualified therapists for confidential, personalised support — on your terms.

@component('mail::button', ['url' => $signupUrl, 'color' => 'success'])
Accept Invitation & Create Account
@endcomponent

This invitation is valid for **14 days** and is tied to your email address ({{ $invite->email }}). Once you create your account, you'll be automatically connected with {{ $therapistName }} so you can schedule your first session right away.

---

**Your privacy matters.** Your information is only shared with your therapist and never sold to third parties.

If you weren't expecting this, you can safely ignore this email.

Thanks,<br>
The {{ config('app.name', 'Onwynd') }} Team

@endcomponent
