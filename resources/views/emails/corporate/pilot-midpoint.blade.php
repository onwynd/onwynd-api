<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Midpoint Check-in: Your Onwynd Pilot</title>
</head>
<body style="font-family:sans-serif;color:#333;max-width:600px;margin:0 auto;padding:20px;background:#fff;">

  <div style="margin-bottom:24px;">
    <img src="{{ config('app.url') }}/img/logo.png" alt="Onwynd" style="height:36px;" onerror="this.style.display='none'">
  </div>

  <h2 style="color:#122420;margin-bottom:8px;">Hi {{ $hrName }},</h2>
  <p>You've reached the halfway point of your Onwynd pilot for <strong>{{ $orgName }}</strong>. Here's how things are going:</p>

  <div style="background:#f8fffe;border:1px solid #c8e6e0;border-radius:8px;padding:20px;margin:24px 0;">
    <h3 style="color:#2A7A6A;margin:0 0 16px;">Pilot Usage Summary</h3>
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
      <tr>
        <td style="padding:8px 0;border-bottom:1px solid #e0f0ec;color:#555;width:55%;">Sessions Used</td>
        <td style="padding:8px 0;border-bottom:1px solid #e0f0ec;font-weight:500;">{{ $sessionsUsed }} / {{ $sessionsTotal }}</td>
      </tr>
      <tr>
        <td style="padding:8px 0;border-bottom:1px solid #e0f0ec;color:#555;">Sessions Remaining</td>
        <td style="padding:8px 0;border-bottom:1px solid #e0f0ec;font-weight:500;">{{ $sessionsRemaining }}</td>
      </tr>
      <tr>
        <td style="padding:8px 0;border-bottom:1px solid #e0f0ec;color:#555;">Usage Rate</td>
        <td style="padding:8px 0;border-bottom:1px solid #e0f0ec;font-weight:500;">{{ number_format($usageRatePct, 1) }}%</td>
      </tr>
      <tr>
        <td style="padding:8px 0;color:#555;">Pilot Ends</td>
        <td style="padding:8px 0;font-weight:500;">{{ $pilotEnd->format('d M Y') }}</td>
      </tr>
    </table>
  </div>

  @if($usageRatePct < 30)
  <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:6px;padding:12px 16px;margin-bottom:20px;">
    <p style="margin:0;font-size:13px;color:#5f4600;">
      <strong>Usage is below 30%.</strong> Consider sending a reminder to your employees — early engagement leads to better outcomes.
    </p>
  </div>
  @elseif($usageRatePct >= 80)
  <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:6px;padding:12px 16px;margin-bottom:20px;">
    <p style="margin:0;font-size:13px;color:#1b5e20;">
      <strong>Excellent uptake!</strong> Your team is making great use of the platform. Consider discussing a full subscription before the pilot ends.
    </p>
  </div>
  @endif

  <p>
    <a href="{{ rtrim(config('frontend.dashboard_url'), '/') }}/institutional/dashboard"
       style="display:inline-block;background:#2A7A6A;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;font-size:14px;font-weight:600;">
      View Full Dashboard
    </a>
  </p>

  <p style="margin-top:32px;font-size:13px;color:#666;">
    Questions? Contact us at
    <a href="mailto:{{ config('onwynd.support_email', 'hello@onwynd.com') }}" style="color:#2A7A6A;">
      {{ config('onwynd.support_email', 'hello@onwynd.com') }}
    </a>
  </p>

  <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
  <p style="font-size:12px;color:#999;">
    &copy; {{ date('Y') }} Onwynd · This is your pilot midpoint check-in.
  </p>

</body>
</html>
