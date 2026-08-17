# Auto-close Timers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Automatically close voting rounds and check-in checkpoints after configurable server-side deadlines and display synchronized countdowns.

**Architecture:** Persist nullable `closes_at` timestamps beside existing `opened_at` fields. Server-side request paths enforce expiry and call the existing close/finalize routines. Dashboard and public clients render countdowns from the server deadline, with the server as the authority.

**Tech Stack:** WordPress/PHP 7.4, MySQL via `$wpdb`, vanilla JavaScript, existing REST/AJAX endpoints.

## Global Constraints

- PHP 7.4 compatible: no union types or PHP 8-only APIs.
- Voting defaults to 5 minutes; check-in defaults to 15 minutes.
- Custom duration must be an integer from 1 to 120 minutes.
- Super admin can close early; expiry is enforced server-side.
- Team #7 Hoa tiêu remains excluded from scores and check-in rankings.
- Use the MAC visual system and 44px minimum touch targets for controls.

---

### Task 1: Persist deadlines and add expiry helpers

**Files:**
- Modify: `mac-companytrip-voting/includes/class-mac-voting-db.php`
- Modify: `mac-companytrip-voting/includes/class-mac-checkin.php`
- Test: `tools/check-plugin.mjs`

**Interfaces:**
- Produces: `MAC_Voting_DB::DEFAULT_VOTE_DURATION_MINUTES`
- Produces: `MAC_Voting_DB::DEFAULT_CHECKIN_DURATION_MINUTES`
- Produces: `MAC_Voting_DB::deadline_from_minutes(int $minutes): string`
- Produces: `MAC_Checkin::expire_active_checkpoint(): void`

- [ ] **Step 1: Write the failing package invariants**

Add these checks to `tools/check-plugin.mjs`:

```js
for (const invariant of [
  "closes_at datetime NULL",
  "DEFAULT_VOTE_DURATION_MINUTES = 5",
  "DEFAULT_CHECKIN_DURATION_MINUTES = 15",
  "expire_active_checkpoint",
]) {
  if (!databaseFile.includes(invariant) && !checkinFile.includes(invariant)) {
    throw new Error(`Missing timer invariant: ${invariant}`);
  }
}
```

- [ ] **Step 2: Run the failing check**

Run: `npm run check`

Expected: failure naming the first missing timer invariant.

- [ ] **Step 3: Add schema fields and deadline helpers**

Add `closes_at datetime NULL` to the existing `rounds` and `checkpoints` table definitions. Define:

```php
public const DEFAULT_VOTE_DURATION_MINUTES = 5;
public const DEFAULT_CHECKIN_DURATION_MINUTES = 15;

public static function deadline_from_minutes(int $minutes): string {
    return gmdate('Y-m-d H:i:s', time() + ($minutes * MINUTE_IN_SECONDS));
}
```

Add `MAC_Checkin::expire_active_checkpoint()` to close an active checkpoint only when `closes_at` is non-empty and is not later than `MAC_Voting_DB::utc_now()`.

- [ ] **Step 4: Run the package check**

Run: `npm run check`

Expected: `Plugin source OK`.

### Task 2: Enforce automatic closure through server requests

**Files:**
- Modify: `mac-companytrip-voting/includes/class-mac-voting-admin.php`
- Modify: `mac-companytrip-voting/includes/class-mac-voting-rest.php`
- Modify: `mac-companytrip-voting/includes/class-mac-checkin-rest.php`
- Test: `tools/check-plugin.mjs`

**Interfaces:**
- Consumes: `MAC_Voting_DB::deadline_from_minutes(int $minutes): string`
- Consumes: `MAC_Checkin::expire_active_checkpoint(): void`
- Produces: open AJAX actions accepting `durationMinutes`
- Produces: voting state containing `closesAt`

- [ ] **Step 1: Write failing expiry invariants**

Require these strings in `tools/check-plugin.mjs`:

```js
for (const invariant of ["durationMinutes", "closes_at", "expire_active_checkpoint", "closesAt"]) {
  if (!adminFile.includes(invariant) && !restFile.includes(invariant) && !checkinRest.includes(invariant)) {
    throw new Error(`Missing auto-close flow: ${invariant}`);
  }
}
```

- [ ] **Step 2: Run the failing check**

Run: `npm run check`

Expected: failure for a missing auto-close flow invariant.

- [ ] **Step 3: Add duration validation and deadline persistence**

In `MAC_Voting_Admin`, parse `durationMinutes` with:

```php
$minutes = max(1, min(120, absint($_POST['durationMinutes'] ?? MAC_Voting_DB::DEFAULT_VOTE_DURATION_MINUTES)));
```

When opening/reopening a round, write `closes_at => MAC_Voting_DB::deadline_from_minutes($minutes)`. Include `durationMinutes` and `closesAt` in the audit detail.

In `MAC_Checkin`, make `open_checkpoint()` and `reopen_checkpoint()` receive optional `$minutes`, clamp it from 1 to 120, and persist `closes_at`.

- [ ] **Step 4: Add authoritative expiry calls**

At the start of voting state and ballot submission paths, close an expired active round before querying state. At the start of check-in REST bootstrap and scan paths, call `MAC_Checkin::expire_active_checkpoint()`.

For vote expiry, update the expired round to `CLOSED` with `closed_at=MAC_Voting_DB::utc_now()` before accepting no more ballots. For check-in expiry, reuse `close_checkpoint()` so it retains ranking and point finalization behavior.

- [ ] **Step 5: Return `closesAt`**

Select `closes_at` with open round queries and expose:

```php
'closesAt' => $round['closes_at'] ?: null,
```

Include checkpoint `closes_at` in the existing overview/check-in bootstrap payload.

- [ ] **Step 6: Run the package check**

Run: `npm run check`

Expected: `Plugin source OK`.

### Task 3: Add duration selection and operator countdowns

**Files:**
- Modify: `mac-companytrip-voting/assets/admin.js`
- Modify: `mac-companytrip-voting/assets/admin.css`
- Test: `tools/check-plugin.mjs`

**Interfaces:**
- Consumes: `round.closes_at`, `checkpoint.closes_at`
- Consumes: `durationMinutes` AJAX parameter
- Produces: `formatRemainingTime(closesAt)` and `durationPrompt(defaultMinutes, label)`

- [ ] **Step 1: Write failing dashboard UI invariants**

Add:

```js
for (const invariant of ["durationMinutes", "formatRemainingTime", "Tự động đóng", "closes_at"]) {
  if (!adminJs.includes(invariant)) throw new Error(`Missing timer dashboard UI: ${invariant}`);
}
```

- [ ] **Step 2: Run the failing check**

Run: `npm run check`

Expected: failure for a missing timer dashboard UI invariant.

- [ ] **Step 3: Add duration UI**

Before open/reopen requests, use a focused modal or `prompt()` to accept whole minutes. The prompt must show `5` for art voting and `15` for check-in. Reject values outside 1–120 with the message `Nhập thời lượng từ 1 đến 120 phút.`.

Send:

```js
await ajax("mac_vote_round", { roundId, operation, durationMinutes });
```

and:

```js
await ajax("mac_vote_checkpoint", { checkpointId, operation, durationMinutes });
```

- [ ] **Step 4: Add countdown renderer**

Implement:

```js
const formatRemainingTime = (closesAt) => {
  const seconds = Math.max(0, Math.ceil((new Date(closesAt).getTime() - Date.now()) / 1000));
  return `${String(Math.floor(seconds / 60)).padStart(2, "0")}:${String(seconds % 60).padStart(2, "0")}`;
};
```

Show the timer and explicit deadline on active round and checkpoint cards. Refresh overview when it reaches zero.

- [ ] **Step 5: Style the active timer**

Use an accessible status badge with red-to-orange gradient only for the live timer, 12–14px radius, and clear non-color text (`Còn 04:59`). Preserve reduced-motion behavior.

- [ ] **Step 6: Run the package check**

Run: `npm run check`

Expected: `Plugin source OK`.

### Task 4: Add public vote countdown

**Files:**
- Modify: `mac-companytrip-voting/assets/public.js`
- Modify: `mac-companytrip-voting/assets/public.css`
- Test: `tools/check-plugin.mjs`

**Interfaces:**
- Consumes: `state.round.closesAt`
- Produces: an `mv-vote-timer` status element and refresh-on-expiry behavior

- [ ] **Step 1: Write failing public timer invariants**

Add:

```js
for (const invariant of ["mv-vote-timer", "closesAt", "formatRemainingTime"]) {
  if (!publicJs.includes(invariant)) throw new Error(`Missing public vote timer: ${invariant}`);
}
```

- [ ] **Step 2: Run the failing check**

Run: `npm run check`

Expected: failure for a missing public vote timer invariant.

- [ ] **Step 3: Render the countdown**

When the vote state has `round.closesAt`, render:

```html
<p class="mv-vote-timer" role="status" aria-live="polite">
  Còn <strong>04:59</strong> để gửi phiếu
</p>
```

Use an interval only while the timer exists. At zero, clear the interval and call the existing state refresh function so the server closes the round and the screen changes from voting to closed.

- [ ] **Step 4: Style the timer**

Place it above the score form in a subtle neutral panel with a red-orange progress indicator. Ensure it wraps on small screens and remains readable without color.

- [ ] **Step 5: Run the package check and build**

Run: `npm run build`

Expected: `Plugin source OK` followed by a `Built ...zip` line.

### Task 5: Release documentation and verification

**Files:**
- Modify: `mac-companytrip-voting/readme.txt`
- Modify: `mac-companytrip-voting/mac-companytrip-voting.php`
- Modify: `package.json`

- [ ] **Step 1: Update version and changelog**

Increment the plugin header, `MAC_VOTING_VERSION`, and `package.json` together. Add release notes specifying 5-minute voting default, 15-minute check-in default, custom 1–120 minute durations, and auto-close.

- [ ] **Step 2: Verify clean release build**

Run: `npm run check && npm run build && git status --short`

Expected: package checks pass, a versioned ZIP appears in ignored `dist/`, and only intended source changes remain.
