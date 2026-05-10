# Security Hardening Tracker

Findings from security sweep (2026-05-10). Items ordered by risk.

## Done

- [x] Move `laravel/tinker` to `require-dev` (composer.json)
- [x] Add `composer audit` step to CI (tests.yml)
- [x] **#3 Reduce signed URL expiry** — `addDay()` → `addHours(2)` across 8 call sites (3 controllers)
- [x] **#4 Use `storeAs()` for audio upload** — replace `file_get_contents()` in PieceController:419

- [x] **#5 Rate limit auth routes** — `throttle:10,1` on `/auth/*`
- [x] **#6 Rate limit webhook route** — `throttle:60,1` on `/api/webhooks/admin`

- [x] **#7 Authorization on `updateAudio`** — already covered by `EnsureUserIsEditor` middleware on route
- [x] **#8 Security headers / CSP** — custom `SecurityHeaders` middleware (matches admin app pattern)

## Next up (requires testing)
- [ ] **#9 Session encryption + secure cookies** — production `.env` only: `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`
- [ ] **#10 Stale session role re-validation** — add TTL check on `last_synced_at`
- [ ] **#11 GDPR-compliant IP logging** — hash/truncate IPs in `download_logs`, add retention policy

## Discovered during sweep

- [ ] **phpseclib/phpseclib** has a high-severity CVE (CVE-2026-44167, DoS via ASN1::decodeOID). Transitive dep via `laravel/socialite`. Update when patch is available.
