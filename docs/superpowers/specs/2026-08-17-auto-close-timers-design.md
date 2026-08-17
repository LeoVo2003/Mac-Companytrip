# Auto-close timers for voting and check-in

## Goal

Automatically close event windows at a server-defined deadline while showing an accurate countdown to participants and operators.

## Timing model

- Each art-voting round stores `closes_at`.
- Each check-in checkpoint stores `closes_at`.
- Opening a voting round defaults to 5 minutes; opening a checkpoint defaults to 15 minutes.
- A Super admin can replace either default with a whole-number minute value before opening.
- Reopening starts a new timer using the selected duration.

## Automatic close

- Every relevant request first checks whether the active round or checkpoint has passed `closes_at`.
- An expired voting round changes to `CLOSED` and rejects new ballots.
- An expired checkpoint uses the existing close-and-finalize flow so rankings and check-in points remain correct.
- The dashboard polls periodically, so an operator page observes and displays the automatically closed status without a page reload.

## User interface

- Opening controls show the default duration and a “Tùy chỉnh” minute field.
- Active dashboard cards show remaining time and an explicit expiry time.
- The public voting page shows a prominent countdown while a round is open.
- Countdown values derive from the server-supplied deadline, not the browser clock alone. Once it reaches zero, the UI refreshes state and disables submissions.

## Data and compatibility

- A database upgrade adds nullable `closes_at` columns to rounds and checkpoints.
- Existing open records without a deadline remain usable until an administrator closes them; newly opened or reopened records always receive one.
- Reset clears both opening and closing timestamps.

## Validation and safety

- Duration accepts whole minutes from 1 to 120.
- Manual early close remains available to Super admins.
- Server-side expiry enforcement is authoritative; countdown JavaScript is presentation only.

## Verification

- Static package checks assert timer schema, server-side expiry guards, and countdown UI hooks.
- Build validates PHP, JavaScript, CSS, and creates the release archive.
