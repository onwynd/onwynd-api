# Firebase Authentication Troubleshooting Guide

## Errors Observed
- ❌ `The token was not issued by the given issuers`
- ❌ `The token is not allowed to be used by this audience`

## Root Cause Analysis

The Firebase token verification is failing because one of these conditions is true:

### 1. **Service Account Credentials Mismatch** (MOST LIKELY)
The service account JSON file used by the backend is from a **different Firebase project** than what generated the ID token.

**How to verify:**
```bash
# SSH into production server
ssh user@api.onwynd.com

# Check if credentials file exists
file /www/wwwroot/api.onwynd.com/storage/app/firebase-service-account.json

# Extract project ID from credentials
cat /www/wwwroot/api.onwynd.com/storage/app/firebase-service-account.json | grep "project_id"

# Expected output: "project_id": "onwynd-ee8e9"
```

### 2. **Client and Backend Project ID Mismatch**
If the frontend is initialized with a different Firebase project configuration.

**How to verify:**
```bash
# Check backend
cat api/.env | grep FIREBASE_PROJECT_ID
# Expected: FIREBASE_PROJECT_ID=onwynd-ee8e9

# Check frontend
cat web/.env.local | grep FIREBASE_PROJECT_ID
# Expected: NEXT_PUBLIC_FIREBASE_PROJECT_ID=onwynd-ee8e9

cat onwynd-dashboard/.env.local | grep FIREBASE_PROJECT_ID
# Expected: NEXT_PUBLIC_FIREBASE_PROJECT_ID=onwynd-ee8e9
```

### 3. **Token Issued with Different Audience**
The Firebase client SDK on the frontend may be misconfigured to use a different app ID or audience.

**How this happens:**
- Token signed by Firebase project A
- Backend trying to verify with project B credentials
- Verification fails because issuer doesn't match

## Solution Steps

### Step 1: Verify Service Account Credentials
```bash
# SSH to production
cd /www/wwwroot/api.onwynd.com/storage/app

# Check if file exists
ls -la firebase-service-account.json

# If missing, you need to:
# 1. Go to Google Cloud Console
# 2. Select project: onwynd-ee8e9
# 3. Go to Service Accounts
# 4. Find/Create account for Firebase Admin SDK
# 5. Create a new JSON key
# 6. Download and upload to server
```

### Step 2: Verify All Clients Have Matching Configuration

**Backend (Laravel API):**
```bash
grep FIREBASE_PROJECT_ID api/.env
# Should be: onwynd-ee8e9
```

**Web (Next.js):**
```bash
grep FIREBASE_PROJECT_ID web/.env.local
# Should be: NEXT_PUBLIC_FIREBASE_PROJECT_ID=onwynd-ee8e9
```

**Dashboard (Next.js):**
```bash
grep FIREBASE_PROJECT_ID onwynd-dashboard/.env.local
# Should be: NEXT_PUBLIC_FIREBASE_PROJECT_ID=onwynd-ee8e9
```

### Step 3: Verify Firebase Project Configuration

Go to: https://console.firebase.google.com/project/onwynd-ee8e9

1. Open **Authentication** → **Settings** → **Authorized Domains**
   - Add your backend API domain if not listed

2. Go to **Firestore Database** or **Realtime Database**
   - Ensure service account has proper permissions

3. Go to **Service Accounts** (in project settings)
   - Verify the service account key matches what's in credentials file

### Step 4: Force Token Regeneration

After fixing the configuration:
```bash
# Clear any cached tokens on clients
# Restart the frontend applications
# Have users log out and log back in
```

## Temporary Mitigation (Not Recommended)

If you need to quickly restore service while troubleshooting:
1. You could add lenient token verification (NOT SECURE)
2. Or bypass Firebase auth temporarily (NOT SECURE)
3. These are ONLY for troubleshooting, not production

## Permanent Fix Checklist

- [ ] Service account file exists and is valid
- [ ] Service account is from project `onwynd-ee8e9`
- [ ] Backend `FIREBASE_PROJECT_ID=onwynd-ee8e9`
- [ ] Frontend `NEXT_PUBLIC_FIREBASE_PROJECT_ID=onwynd-ee8e9`
- [ ] All Frontend Firebase configs use same project
- [ ] Users able to generate Firebase ID tokens
- [ ] Backend can verify those tokens
- [ ] Test authentication end-to-end

## Quick Test

```php
// In a Laravel tinker session or command:
php artisan tinker

$credentials = file_get_contents(config('services.firebase.credentials'));
$json = json_decode($credentials, true);
echo "Project ID: " . $json['project_id'];

// Should output: onwynd-ee8e9
```

## References

- Firebase Admin SDK Documentation: https://firebase.google.com/docs/auth/admin/verify-id-tokens
- Token Verification Error Codes: https://github.com/kreait/firebase-php/issues
- Kreait Firebase Library: https://github.com/kreait/firebase-php

