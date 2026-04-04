<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Onwynd Pilot Ends in 14 Days</title>
</head>
<body style="font-family:sans-serif;color:#333;max-width:600px;margin:0 auto;padding:20px;background:#fff;">

  <div style="margin-bottom:24px;">
    <img src="{{ config('app.url') }}/img/logo.png" alt="Onwynd" style="height:36px;" onerror="this.style.display='none'">
  </div>

  <h2 style="color:#122420;margin-bottom:8px;">Hi {{ $hrName }},</h2>
  <p>Your Onwynd pilot for <strong>{{ $orgName }}</strong> ends on <strong>{{ $pilotEnd->format('d M Y') }}</strong> — that's 14 days away.</p>

  <div style="background:#f8fffe;border:1px solid #c8e6e0;border-radius:8px;padding:20px;margin:24px 0;">
    <h3 style="color:#2A7A6A;margin:0 0 16px;">Pilot Summary</h3>
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
      <tr>
        <td style="padding:8px 0;border-bottom:1px solid #e0f0ec;color:#555;width:55%;">Sessions Used</td>
        <td style="padding:8px 0;border-bottom:1px solid #e0f0ec;font-weight:500;">{{ $sessionsUsed }} / {{ $sessionsTotal }}</td>
      </tr>
      <tr>
        <td style="padding:8px 0;color:#555;">Pilot End Date</td>
        <td style="padding:8px 0;font-weight:500;">{{ $pilotEnd->format('d M Y') }}</td>
      </tr>
    </table>
  </div>

  <p style="font-size:15px;font-weight:500;color:#122420;">Ready to continue?</p>
  <p style="font-size:14px;color:#555;">
    Don't let your team's mental wellness support lapse. Renew now to keep access uninterrupted and lock in your current pricing.
  </p>

  <p style="margin:24px 0;">
    <a href="{{ $renewalUrl }}"
       style="display:inline-block;background:#2A7A6A;color:#fff;text-decoration:none;padding:14px 28px;border-radius:6px;font-size:15px;font-weight:600;">
      Renew Your Subscription
    </a>
  </p>

  <p style="font-size:13px;color:#666;">
    Prefer to talk first? Reply to this email or contact your account manager.
  </p>

  <p style="margin-top:32px;font-size:13px;color:#666;">
    Questions? Reach us at
    <a href="mailto:{{ config('onwynd.support_email', 'hello@onwynd.com') }}" style="color:#2A7A6A;">
      {{ config('onwynd.support_email', 'hello@onwynd.com') }}
    </a>
  </p>

  <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
  <p style="font-size:12px;color:#999;">
    &copy; {{ date('Y') }} Onwynd · Pre-renewal reminder for your corporate pilot.
  </p>

</body>
</html>
