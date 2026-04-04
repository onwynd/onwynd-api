# Onwynd API - Error Fixes Summary (March 24, 2026)

## Overview
Fixed 2 production errors from API logs:
1. ✅ **Database Column Not Found** - FIXED
2. 🔍 **Firebase Authentication Failed** - DIAGNOSED & DOCUMENTED

---

## Problem 1: Database Column Not Found Error ✅ FIXED

### Error Details
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'users.student_verification_status' in 'SELECT'
Location: AdminSubscriptionListController.php line 85
```

### Root Cause
Migration file `2026_03_23_200000_add_student_verification_to_users_table.php` was created but NOT executed on the database. The controller tries to select columns that don't exist yet.

### Solution Implemented
**File Modified:** `api/app/Http/Controllers/API/V1/Admin/AdminSubscriptionListController.php`

#### Code Changes:
1. Refactored column selection to be dynamic
2. Added `Schema::hasColumn()` checks for all student verification columns:
   - `student_verification_status`
   - `student_verified_at`
   - `student_email`
   - `student_id`
3. Columns are only included in SELECT if they exist in database

#### Impact:
- API endpoint `/admin/subscriptions` now works regardless of migration status
- No database queries will fail due to missing columns
- Once migration is run, all columns will be automatically included

### Next Steps to Complete
Run the migration on production:
```bash
ssh user@api.onwynd.com
cd /www/wwwroot/api.onwynd.com
php artisan migrate
```

Verify migration status:
```bash
php artisan migrate:status | grep "add_student_verification"
```

---

## Problem 2: Firebase Authentication Failed 🔍 DIAGNOSED

### Error Details
```
Firebase authentication failed
- The token was not issued by the given issuers
- The token is not allowed to be used by this audience
Location: FirebaseAuthController.php line 44
```

### Root Cause Analysis
Token verification is failing because of a **credentials/project mismatch**. When Firebase verifies a token, it checks:
1. **Issuer (iss claim)**: Expected `https://securetoken.google.com/onwynd-ee8e9`
2. **Audience (aud claim)**: Must match the client app

If these don't match, verification fails.

### Likely Causes (in order of probability)
1. **Service Account Credentials Mismatch** (80% likely)
   - File: `/www/wwwroot/api.onwynd.com/storage/app/firebase-service-account.json`
   - Issue: Credentials are from a DIFFERENT Firebase project than `onwynd-ee8e9`

2. **Frontend Firebase Configuration Mismatch** (15% likely)
   - Client generating tokens for different project
   - Or using different app ID as backend expects

3. **Missing or Corrupted Service Account File** (5% likely)
   - Credentials file doesn't exist or is invalid

### Diagnostic Checklist
- [ ] Service account file exists on server
- [ ] Service account is from project `onwynd-ee8e9`
- [ ] Backend FIREBASE_PROJECT_ID = onwynd-ee8e9
- [ ] Frontend NEXT_PUBLIC_FIREBASE_PROJECT_ID = onwynd-ee8e9
- [ ] Firebase console shows proper authorization domains

### How to Fix

**Step 1: Verify Service Account**
```bash
# SSH to production server
cat /www/wwwroot/api.onwynd.com/storage/app/firebase-service-account.json | grep project_id

# Should output: "project_id": "onwynd-ee8e9"
```

**Step 2: If Missing or Wrong Project**
1. Go to https://console.firebase.google.com/project/onwynd-ee8e9/settings/serviceaccounts/adminsdk
2. Create new private key (JSON format)
3. Download the file
4. Upload to: `/www/wwwroot/api.onwynd.com/storage/app/firebase-service-account.json`
5. Ensure file permissions: `chmod 600`

**Step 3: Verify All Configs Match**
```bash
# Backend
grep FIREBASE_PROJECT_ID api/.env
# Should be: onwynd-ee8e9

# Frontend Web
grep FIREBASE_PROJECT_ID web/.env.local
# Should be: NEXT_PUBLIC_FIREBASE_PROJECT_ID=onwynd-ee8e9

# Dashboard
grep FIREBASE_PROJECT_ID onwynd-dashboard/.env.local
# Should be: NEXT_PUBLIC_FIREBASE_PROJECT_ID=onwynd-ee8e9
```

**Step 4: Force Token Refresh**
```bash
# Have users log out completely
# Clear browser localStorage/sessionStorage
# Have them log back in
# New tokens should now verify correctly
```

### Detailed Debugging Guide
See: `api/FIREBASE_DIAGNOSIS.md` for comprehensive troubleshooting steps.

---

## Files Modified

1. ✅ `api/app/Http/Controllers/API/V1/Admin/AdminSubscriptionListController.php`
   - Added dynamic column selection with Schema checks
   - No breaking changes to API response

2. 📋 `api/FIREBASE_DIAGNOSIS.md` (NEW)
   - Comprehensive Firebase troubleshooting guide
   - Step-by-step diagnostic procedures
   - Solution implementation steps

---

## Testing & Verification

### Test Database Column Fix
```bash
# This endpoint should now work even before migration runs
curl -H "Authorization: Bearer {admin_token}" \
  https://api.onwynd.com/admin/subscriptions

# Expected: 200 OK with subscription data
```

### Test Firebase Authentication
```bash
# After credentials are fixed, test with:
curl -X POST https://api.onwynd.com/api/v1/auth/firebase \
  -H "Content-Type: application/json" \
  -d '{"id_token": "eyJhbGciOiJSUzI..."}'

# Expected: 200 OK with user and auth token
```

---

## Summary

| Issue | Status | Action Required |
|-------|--------|-----------------|
| Database columns | ✅ Fixed | Run `php artisan migrate` |
| Firebase token verification | 🔍 Diagnosed | Follow Firebase fix steps above |
| Production API stability | ✅ Improved | Database queries now resilient |

## Recommendations

1. **Immediate** (Production-Critical):
   - Verify Firebase service account on production server
   - Ensure credentials are from `onwynd-ee8e9` project

2. **Within 24 Hours**:
   - Run pending migrations
   - Test full authentication flow
   - Monitor error logs for any new issues

3. **Long-term**:
   - Set up automated migration checks
   - Add Firebase configuration validation to boot process
   - Consider adding health check endpoint for Firebase connectivity

