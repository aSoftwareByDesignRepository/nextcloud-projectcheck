# Nextcloud App Store — release workflow (ProjectCheck)

End-to-end steps to produce the **archive**, **checksums**, and **code signature** you need at [apps.nextcloud.com](https://apps.nextcloud.com) (developer account → your app → new version).

Replace `X.Y.Z` with the real version (e.g. `2.0.21`).

---

## 0. Prerequisites

- Registered app and **developer certificate** from Nextcloud (private key on your machine).
- Default key path used below: `~/.nextcloud/certificates/projectcheck.key` (same basename as app id).
- This monorepo: build the tarball from **`apps/`** so the archive root is `projectcheck/`.

---

## 1. Version and changelog

1. Bump **`appinfo/info.xml`**: `<version>X.Y.Z</version>` and any required `<dependencies>` / `<nextcloud min-version="…" max-version="…"/>`.
2. Update **`CHANGELOG.md`** (and localized changelog if present) for `X.Y.Z`.
3. Optionally add **`release/GITHUB_RELEASE_NOTES_X.Y.Z.md`** for GitHub.

---

## 2. Build the installable `.tar.gz`

From the repo root that contains `apps/projectcheck` (here: `nextcloud-development/apps/`; local folder name may differ):

```bash
cd apps
VERSION=X.Y.Z
tar --exclude='projectcheck/node_modules' \
    --exclude='projectcheck/.git' \
    --exclude='projectcheck/release/projectcheck-*.tar.gz' \
    -czf "projectcheck/release/projectcheck-${VERSION}.tar.gz" projectcheck
```

Add more `--exclude=` lines as needed (see monorepo `ready4upload/BUILD_INSTRUCTIONS.txt` for upload-only bundles).

**Do not commit** the tarball (see app `.gitignore`).

---

## 3. SHA-256 / SHA-512 (app store + checksum file)

```bash
cd apps/projectcheck/release
sha256sum "projectcheck-${VERSION}.tar.gz"
sha512sum "projectcheck-${VERSION}.tar.gz"
```

- The app store form usually asks for **SHA-256** of the uploaded archive.
- Copy the hashes into **`release/CHECKSUMS-X.Y.Z.txt`** (optional; see ArbeitszeitCheck `CHECKSUMS-*.txt` as template). Only commit the checksums file if you want them in git; the tarball stays **ignored**.

---

## 4. Code signature (base64) for the app store

The store expects a **base64-encoded** RSA signature over the **exact** `.tar.gz` bytes (SHA-512 digest signed with your app certificate key).

**One line** (copy output into the store’s signature field):

```bash
openssl dgst -sha512 -sign ~/.nextcloud/certificates/projectcheck.key \
  "projectcheck-${VERSION}.tar.gz" | openssl base64 | tr -d '\n'
```

If you prefer wrapped output, omit `| tr -d '\n'`.

**Important:** If you change the tarball or rebuild, **regenerate** the signature.

**Do not commit** the private key or ad-hoc signature dump files.

---

## 5. Optional: detached GPG sign the archive

Not required by the app store; useful for mirrors or GitHub releases.

```bash
gpg --detach-sign --armor "projectcheck-${VERSION}.tar.gz"
```

Produces `projectcheck-X.Y.Z.tar.gz.asc` — **ignored** by git.

---

## 6. Upload at apps.nextcloud.com

Typical fields:

| Field | Source |
|--------|--------|
| **Archive** | `release/projectcheck-X.Y.Z.tar.gz` |
| **SHA-256** | From `sha256sum` / `CHECKSUMS-X.Y.Z.txt` |
| **Signature** | Output of the `openssl dgst … \| openssl base64` command |
| **Changelog** | Paste from `CHANGELOG.md` (or shortened) |

Submit; fix any validation errors (wrong checksum/signature almost always means a wrong file or stale copy).

---

## 7. GitHub release — **standalone app repo** (not the monorepo)

Release tags and assets belong on **`nextcloud-projectcheck`**, not on the private development monorepo. Visibility: **private** app repo — see [REPOSITORY-LAYOUT.md](../../../ready2publish/REPOSITORY-LAYOUT.md).

| Repository | Role |
|------------|------|
| **This workspace** (`nextcloud-development` or e.g. `nextcloud-dev`, …) | Day-to-day development; **do not** create product releases here unless you explicitly want a monorepo release. |
| **`aSoftwareByDesignRepository/nextcloud-projectcheck`** | **Private** ProjectCheck repo — tags, GitHub Releases, and the `.tar.gz` asset (App Store workflow). |

**Canonical GitHub repo for releases**

- `https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck`
- Shorthand for `gh`: `--repo aSoftwareByDesignRepository/nextcloud-projectcheck`

Always pass **`--repo aSoftwareByDesignRepository/nextcloud-projectcheck`** (or set `GH_REPO` once) so `gh` never targets your monorepo remote by mistake.

```bash
export GH_REPO=aSoftwareByDesignRepository/nextcloud-projectcheck
```

Build the tarball **here** (monorepo `apps/`), then point `gh` at the file with an absolute or correct relative path.

### Create a new GitHub Release (tag + notes + asset)

From `apps/projectcheck/release` after building `projectcheck-${VERSION}.tar.gz`:

```bash
VERSION=X.Y.Z
cd /path/to/nextcloud-development/apps/projectcheck/release

gh release create "v${VERSION}" \
  --repo aSoftwareByDesignRepository/nextcloud-projectcheck \
  --title "v${VERSION}" \
  --notes-file "GITHUB_RELEASE_NOTES_${VERSION}.md" \
  "projectcheck-${VERSION}.tar.gz"
```

If the release **already exists** and you only need to **replace the asset**:

```bash
gh release upload "v${VERSION}" "projectcheck-${VERSION}.tar.gz" \
  --repo aSoftwareByDesignRepository/nextcloud-projectcheck \
  --clobber
```

### Source code on GitHub

Publishing the **tarball** does not push git history. To publish app sources to the standalone repo, use [STANDALONE_REPO.md](./STANDALONE_REPO.md) (`git subtree push`).

---

## What is committed vs ignored

| Artifact | Committed? |
|----------|------------|
| `README.md`, `APPSTORE-RELEASE.md`, `STANDALONE_REPO.md`, `GITHUB_RELEASE_NOTES_*.md` | Yes |
| `CHECKSUMS-X.Y.Z.txt` | Optional |
| `*.tar.gz`, `*.tar.gz.asc` | **No** (gitignored) |
| `SIGNATURE-*.txt` or local signature dumps | **No** |
| Private key `*.key` | **Never** in the repo |

---

## Quick checklist

- [ ] `info.xml` version = `X.Y.Z`
- [ ] Changelog updated
- [ ] Tarball built with correct excludes
- [ ] SHA-256 + SHA-512 recorded; store gets **SHA-256**
- [ ] OpenSSL base64 signature **from the same tarball file**
- [ ] Nothing uploaded to git except docs/checksums (no `.tar.gz`, no keys)
- [ ] GitHub Release (if used): **`gh` with `--repo aSoftwareByDesignRepository/nextcloud-projectcheck`**, not the monorepo
