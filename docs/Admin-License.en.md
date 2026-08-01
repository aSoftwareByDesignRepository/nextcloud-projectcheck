# ProjectCheck Mobile — administrator license guide

Official **ProjectCheck Mobile** (`de.softwarebydesign.projectcheck`) is **available** and talks to your Nextcloud
over Login Flow / app passwords. The web ProjectCheck UI stays free. Only the companion
JSON API under `/apps/projectcheck/mobile/v1/*` requires a signed **PC2** organisation key
and a **named mobile seat** for each employee. Server **2.0.86+** required for offline create,
push, and settle-on-phone.

## Install a license

1. Purchase seats (invoice / quote) — you receive a key shaped like `PC2.<payload>.<signature>`.
2. Open **ProjectCheck → Settings → License** (or Nextcloud Administration → ProjectCheck).
3. Paste the full key and save. The panel shows validity, seat limit, and used seats.
4. Assign seats to Nextcloud users who should use the app.

Whitespace and line breaks in pasted keys are ignored.

## Employee cannot open the app

| Symptom | Cause | Fix |
|---------|--------|-----|
| Unofficial / unlicensed server | Envelope missing or signature invalid | Paste a valid PC2 key; do not strip license checks on forks |
| “Ask IT for a seat” / HTTP 402 `seat_required` | User not in seat list (or over limit after downgrade) | Assign a seat or upgrade the seat count |
| HTTP 402 `license_expired` | `validUntil` passed (server local date) | Renew and paste a new key |

## Security notes

- One vendor Ed25519 public key is embedded in the app and the server. Keys are offline statements — there is no phone-home activation.
- Already-issued **AZC2 / DKC2 / MCC2 / MN2 / IV2** keys for other products are unaffected; PC2 cannot unlock them and vice versa.
- Rates on time entries are always resolved on the server. The mobile app may show a preview only.

## Related

- Product: https://nextcloud.software-by-design.de/
- Purchase / support: info@software-by-design.de
