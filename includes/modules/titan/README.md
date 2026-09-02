# TITAN Application & Browser Confinement

TITAN is GeDefense's local, zero-dependency policy and WordPress hardening plane. It does not claim operating-system browser isolation. It constrains compatible browser behavior through response headers, validates deployment state and coordinates findings with Trinity XDR.

## Architecture

- `class-titan-surface-resolver.php`: deterministic request-surface classification.
- `class-titan-policy-compiler.php`: canonical per-surface policy compilation and semantic validation.
- `class-titan-policy-store.php`: candidate, active, last-known-good, confirmation and rollback state.
- `class-titan-assurance.php`: static checks, local probes, compatibility analysis and enforcement eligibility.
- `class-titan-runtime.php`: header delivery, Fetch Metadata decisions, health and typed learning observations.
- `class-titan-violation-collector.php`: bounded, redacted CSP report aggregation.
- `class-titan-learning.php`: allowlisted script/style observations to report-only candidates.
- `class-titan-sandbox.php`: Airlock-receipt-bound active-content previews with signed short-lived URLs.
- `class-titan-server-rules.php`: path-safe Apache/Nginx deployment artifacts and authenticated export.
- `class-titan-login-gate.php`: short-lived, replay-resistant login access tokens.
- `class-titan-recovery.php`: local WP-CLI rollback and compatible report-only recovery.

## State model

`CONFIGURED → VALIDATED → STAGED → ACTIVATED → CONFIRMED`

Failed candidates become `REJECTED`. A post-activation critical-header mismatch invokes the last-known-good rollback. Learning data never mutates the active policy directly.

## Rollout and recovery

Begin with a compatible/balanced report-only policy, exercise all WordPress surfaces, inspect local violations, generate a candidate from typed observations, validate it and only then enable enforcement. HSTS preload and experimental COEP/Trusted Types require separate compatibility review.

Local recovery commands:

```bash
wp gedefense titan rollback
wp gedefense titan recover
```

The recovery command is unavailable over HTTP, activates compatible report-only behavior, disables TITAN's login gate and file-modification lockdown, and emits an XDR response event.

## Security boundaries

- No remote telemetry or external runtime dependency.
- CSP reports are size-bounded, rate-limited, aggregated and URL-redacted.
- External header conflicts default to observation and preservation; CSP values are never concatenated unsafely.
- Active preview records require a matching Airlock SHA-256 inspection receipt.
- Dedicated preview origins require HTTPS, a distinct host and no shared WordPress cookie domain.
- Generated server rules are not labeled active until separately observed or validated.
