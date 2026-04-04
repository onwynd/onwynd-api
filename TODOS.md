# TODOS

Deferred items from the HOLD SCOPE plan review (2026-03-14).

---

## P1 — Critical / Blocking

### [GIT] Initialize version control
**What:** Run `git init` at the monorepo root and make an initial commit.
**Why:** There is currently no git history anywhere in the project. A single accidental delete or bad deploy has no recovery path.
**Effort:** S
**Action:** Must be done manually by the developer — run `git init` in `C:\Users\mudassar\Documents\onwynd`.

---

### [EARN] Dynamic commission rates
**What:** Replace the hardcoded `session_rate * 0.80` in `EarningsController` with a per-therapist tier lookup.
**Why:** All therapists currently earn 80% regardless of tier. When tiers are introduced, payouts will be wrong.
**File:** `app/Http/Controllers/API/V1/Therapist/EarningsController.php`
**Effort:** M
**Depends on:** Therapist tier model/column existing in DB.

---

## P2 — Important / Soon

### [CHAT] Audio transcription
**What:** Integrate OpenAI Whisper (or equivalent) to transcribe audio messages in `ChatController::store()`.
**Why:** Audio messages are currently stored as `[Audio Message]` — the AI cannot respond meaningfully to them.
**File:** `app/Http/Controllers/API/V1/Patient/ChatController.php:87`
**Effort:** M

---

### [AUDIO] Therapy session outcome recording
**What:** Create a `TherapySessionOutcome` model/migration and wire it into `AudioSessionController::end()`.
**Why:** The `end()` method has dead-commented outcome recording code. Post-session data is never captured.
**File:** `app/Http/Controllers/API/V1/Therapy/AudioSessionController.php:95`
**Effort:** M

---

### [ORG] Notify RM when deal closes
**What:** Implement notification to the Relationship Manager (Builder/RM) when `CloserDashboardController::markWon()` creates an Organization.
**Why:** The comment "Notify the Builder (RM) to begin account management" is currently skipped. RM is never told about new accounts.
**File:** `app/Http/Controllers/API/V1/Sales/CloserDashboardController.php:140`
**Effort:** M
**Depends on:** RM assignment logic being defined.

---

## P3 — Nice to Have / Later

### [REFERRAL] Streak and best-month calculation
**What:** Implement `current_streak` and `best_month` in `ReferralController::getStats()`.
**Why:** Currently returns `null` for both. Ambassadors have no gamification signal.
**File:** `app/Http/Controllers/API/V1/Patient/ReferralController.php:125`
**Effort:** S–M

---

### [REFERRAL] Click tracking
**What:** Implement `trackClick()` in `ReferralController` — increment a counter on `ReferralCode` or insert a `ReferralClick` event row.
**Why:** Currently returns 501. Ambassadors cannot see how many people clicked their link.
**File:** `app/Http/Controllers/API/V1/Patient/ReferralController.php:220`
**Effort:** S

---

### [REFERRAL] Email invite sending
**What:** Implement `shareViaEmail()` in `ReferralController` — queue a mailable to each address in the `emails` array.
**Why:** Currently returns 501. Ambassadors cannot share their link via email from within the app.
**File:** `app/Http/Controllers/API/V1/Patient/ReferralController.php:192`
**Effort:** S

---

### [CHAT] PDF export
**What:** Implement PDF export in `ChatController::export()` using a library like DomPDF or Browsershot.
**Why:** Currently returns 501 for `?format=pdf`. Only JSON export works.
**File:** `app/Http/Controllers/API/V1/Patient/ChatController.php:173`
**Effort:** M

---

### [REFERRAL] Reward redemption / payout disbursement
**What:** Implement actual payout disbursement in `ReferralController::redeemReward()`.
**Why:** Currently returns 501. Rewards accumulate but can never be claimed.
**File:** `app/Http/Controllers/API/V1/Patient/ReferralController.php:155`
**Effort:** L
**Depends on:** Payment provider integration (Paystack / Flutterwave).

---

### [CLOSER] Pipeline scoped to current closer
**What:** In `CloserDashboardController::index()`, filter `awaiting_action` and `pipeline` to the authenticated closer's assigned deals (add `->where('closer_id', $user->id)` or equivalent assignment check).
**Why:** Currently returns ALL deals in closer stages to every closer. A closer sees other closers' pipelines.
**File:** `app/Http/Controllers/API/V1/Sales/CloserDashboardController.php:24`
**Effort:** S
