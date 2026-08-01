# ProjectCheck Mobile — Admin-Lizenz

Die offizielle App **ProjectCheck Mobile** (`de.softwarebydesign.projectcheck`) ist **verfügbar** und
verbindet sich per Login Flow / App-Passwort mit Ihrer Nextcloud. Die Web-Oberfläche von ProjectCheck bleibt
kostenlos. Nur die Companion-API unter `/apps/projectcheck/mobile/v1/*` braucht einen
signierten **PC2**-Organisationsschlüssel und einen **benannten Mobile-Sitz** je Mitarbeiter.
Server **2.0.86+** für Offline-Erfassung, Push und Abrechnen auf dem Telefon.

## Lizenz einspielen

1. Sitze bestellen (Rechnung / Angebot) — Sie erhalten einen Schlüssel der Form `PC2.<payload>.<signature>`.
2. **ProjectCheck → Einstellungen → Lizenz** öffnen (oder Nextcloud-Administration → ProjectCheck).
3. Schlüssel vollständig einfügen und speichern. Gültigkeit, Sitzkontingent und belegte Sitze erscheinen im Panel.
4. Sitze den Nextcloud-Benutzern zuweisen, die die App nutzen sollen.

Leerzeichen und Zeilenumbrüche beim Einfügen werden ignoriert.

## Mitarbeiter kommt nicht in die App

| Symptom | Ursache | Lösung |
|---------|---------|--------|
| Inoffizieller / nicht lizenzierter Server | Envelope fehlt oder Signatur ungültig | Gültigen PC2-Schlüssel einspielen; Lizenzprüfung nicht aus Forks entfernen |
| „IT muss Sitz zuweisen“ / HTTP 402 `seat_required` | Benutzer nicht in der Sitzliste (oder über Limit nach Downgrade) | Sitz zuweisen oder Kontingent erhöhen |
| HTTP 402 `license_expired` | `validUntil` überschritten (Datum des Servers) | Verlängern und neuen Schlüssel einfügen |

## Sicherheit

- Ein gemeinsamer Ed25519-Vendor-Public-Key steckt in App und Server. Schlüssel sind Offline-Aussagen — keine Aktivierung online.
- Bereits ausgegebene **AZC2 / DKC2 / MCC2 / MN2 / IV2**-Schlüssel anderer Produkte bleiben gültig; PC2 entsperrt sie nicht und umgekehrt.
- Stundensätze werden immer serverseitig ermittelt. Die App zeigt höchstens eine Vorschau.

## Links

- Produkt: https://nextcloud.software-by-design.de/de/
- Kauf / Support: info@software-by-design.de
