<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Onwynd Pilot Has Ended</title>
</head>
<body style="font-family:sans-serif;color:#333;max-width:600px;margin:0 auto;padding:20px;background:#fff;">

  <div style="margin-bottom:24px;">
    <img src="{{ config('app.url') }}/img/logo.png" alt="Onwynd" style="height:36px;" onerror="this.style.display='none'">
  </div>

  <h2 style="color:#122420;margin-bottom:8px;">Hi {{ $hrName }},</h2>
  <p>Your Onwynd pilot for <strong>{{ $orgName }}</strong> ended on <strong>{{ $expiryDate->format('d M Y') }}</strong>.</p>

  <div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin:24px 0;">
    <h3 style="color:#555;margin:0 0 16px;">Final Pilot Summary</h3>
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
      <tr>
        <td style="padding:8px 0;border-bottom:1px solid #eee;color:#555;width:55%;">Total Sessions Used</td>
        <td style="padding:8px 0;border-bottom:1px solid #eee;font-weight:500;">{{ $sessionsUsed }} / {{ $sessionsTotal }}</td>
      </tr>
      <tr>
        <td style="padding:8px 0;color:#555;">Pilot End Date</td>
        <td style="padding:8px 0;font-weight:500;">{{ $expiryDate->format('d M Y') }}</td>
      </tr>
    </table>
  </div>

  <p style="font-size:14px;color:#555;">
    Your employees' access to individual therapy sessions and the Onwynd platform has been paused.
    Group sessions and AI companion access may be available under a free tier — please check your account.
  </p>

  <p style="font-size:15px;font-weight:500;color:#122420;margin-top:20px;">Continue your employees' wellbeing journey</p>
  <p style="font-size:14px;color:#555;">
    Subscribe to a full corporate plan to restore full access and keep your team supported.
  </p>

  <p style="margin:24px 0;">
    <a href="{{ rtrim(config('frontend.url'), '/') }}/pricing/corporate"
       style="display:inline-block;background:#2A7A6A;color:#fff;text-decoration:none;padding:14px 28px;border-radius:6px;font-size:15px;font-weight:600;">
      View Corporate Plans
    </a>
  </p>

  <p style="font-size:13px;color:#666;">
    Need a custom quote or have questions? Reply to this email or contact
    <a href="mailto:{{ config('onwynd.support_email', 'hello@onwynd.com') }}" style="color:#2A7A6A;">
      {{ config('onwynd.support_email', 'hello@onwynd.com') }}
    </a>
  </p>

  <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
  <p style="font-size:12px;color:#999;">
    &copy; {{ date('Y') }} Onwynd · Your corporate pilot has concluded.
  </p>

</body>
</html>
