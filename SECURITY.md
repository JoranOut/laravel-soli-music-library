# Security Hardening Tracker

Findings from security sweep (2026-05-10). Items ordered by risk.

## Done

- [x] Move `laravel/tinker` to `require-dev` (composer.json)
- [x] Add `composer audit` step to CI (tests.yml)
- [x] **#3 Reduce signed URL expiry** — `addDay()` → `addHours(2)` across 8 call sites (3 controllers)
- [x] **#4 Use `storeAs()` for audio upload** — replace `file_get_contents()` in PieceController:419

## Next up (requires testing)
- [ ] **#5 Rate limit auth routes** — `/auth/redirect`, `/auth/callback`, `/auth/logout`
- [ ] **#6 Rate limit webhook route** — `/api/webhooks/admin`
- [ ] **#7 Authorization on `updateAudio`** — verify user can edit the specific piece
- [ ] **#8 Security headers / CSP** — consider `bepsvpt/secure-headers` (used in admin app)
- [ ] **#9 Session encryption + secure cookies** — production `.env` only: `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`
- [ ] **#10 Stale session role re-validation** — add TTL check on `last_synced_at`
- [ ] **#11 GDPR-compliant IP logging** — hash/truncate IPs in `download_logs`, add retention policy

## Discovered during sweep

- [ ] **phpseclib/phpseclib** has a high-severity CVE (CVE-2026-44167, DoS via ASN1::decodeOID). Transitive dep via `laravel/socialite`. Update when patch is available.
