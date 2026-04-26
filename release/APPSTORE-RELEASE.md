# Nextcloud App Store — release workflow (ProjectCheck)

This file is the **ProjectCheck-specific** checklist. The **canonical** procedure is the upstream **App Developer Guide** (same content as the App Store “Search docs” / Read the Docs):

**[App Developer Guide — Nextcloud App Store](https://nextcloudappstore.readthedocs.io/en/latest/developer.html)**

Sections you will use:

| Topic | Anchor |
|--------|--------|
| Obtaining a Certificate | [#obtaining-a-certificate](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#obtaining-a-certificate) |
| Registering an App | [#registering-an-app](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#registering-an-app) |
| Uploading an App Release | [#uploading-an-app-release](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#uploading-an-app-release) |
| App Metadata (`info.xml`, `CHANGELOG.md`) | [#app-metadata](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#app-metadata) |
| Blacklisted Files | [#blacklisted-files](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#blacklisted-files) |

Replace `X.Y.Z` with the real version (e.g. `2.0.27`). App id is **`projectcheck`** (lowercase, matches the top-level folder inside the `.tar.gz`).

**Repository:** build and release from **[`nextcloud-projectcheck`](https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck)** — that GitHub repo contains **only** ProjectCheck. A private monorepo path is optional for some teams (see §4).

---

## 1. Obtaining a certificate (upstream steps)

From the guide: store keys under `~/.nextcloud/certificates/`, then generate key + CSR (`CN` must equal the app id):

```bash
mkdir -p ~/.nextcloud/certificates/
cd ~/.nextcloud/certificates/
openssl req -nodes -newkey rsa:4096 -keyout projectcheck.key -out projectcheck.csr -subj "/CN=projectcheck"
```

Open a **pull request** on [nextcloud/app-certificate-requests](https://github.com/nextcloud/app-certificate-requests) with the contents of **`projectcheck.csr`**. After approval, save the signed public cert as **`projectcheck.crt`** next to **`projectcheck.key`**. Never commit the `.key` file.

---

## 2. Registering the app id (one-time)

After you have **`projectcheck.crt`**, use the [register app](https://apps.nextcloud.com/developer/apps/new) UI (or REST API). The guide asks for:

- **Certificate:** paste **`projectcheck.crt`**
- **Signature** over the app id (proves you hold the private key):

```bash
echo -n "projectcheck" | openssl dgst -sha512 -sign ~/.nextcloud/certificates/projectcheck.key | openssl base64
```

---

## 3. Version, `info.xml`, and `CHANGELOG.md`

1. Bump **`appinfo/info.xml`**: `<version>X.Y.Z</version>` and adjust **`<dependencies><nextcloud …/></dependencies>`** if needed.
2. Update **`CHANGELOG.md`** at the app root. The store imports changelog from **`CHANGELOG.md`**; the release heading must match the **semantic version** in `info.xml` (see [Changelog](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#changelog) — pattern `## X.Y.Z` / Keep a Changelog).
3. Optional: **`release/GITHUB_RELEASE_NOTES_X.Y.Z.md`** for GitHub Releases.

---

## 4. Build the installable `.tar.gz`

The uploaded archive must:

- Contain **exactly one** top-level folder named **`projectcheck`** (lowercase ASCII + underscores only).
- Contain **`projectcheck/appinfo/info.xml`**.
- **Not** contain **`.git`** ([blacklisted](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#blacklisted-files)).

**Recommended — clone of [`nextcloud-projectcheck`](https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck)** (app sources at repository root):

```bash
cd /path/to/nextcloud-projectcheck
./release/build-appstore-archive.sh X.Y.Z
```

**Same script from a private monorepo** (app under `apps/projectcheck/`):

```bash
./apps/projectcheck/release/build-appstore-archive.sh X.Y.Z
```

This runs `npm ci`, `npm run build`, `composer install --no-dev`, then packs with excludes for `node_modules`, `tests`, prior release tarballs, etc. The archive always has a top-level **`projectcheck/`** folder (required by the store).

**Manual pack** (if you already built `dist/` and production `vendor/`):

```bash
# From monorepo: parent of apps/
cd apps
VERSION=X.Y.Z
tar --exclude='projectcheck/node_modules' \
    --exclude='projectcheck/.git' \
    --exclude='projectcheck/release/projectcheck-*.tar.gz' \
    --exclude='projectcheck/tests' \
    -czf "projectcheck/release/projectcheck-${VERSION}.tar.gz" projectcheck
```

Do **not** commit the tarball (see app `.gitignore`).

---

## 5. Host the archive (Download URL)

**Uploading an App Release** expects a **Download** field: an **HTTPS URL** to your **`projectcheck-X.Y.Z.tar.gz`** — the store downloads the file and verifies it. Typical flow: attach the file to a **GitHub Release** on your public app repo and use the **browser download** URL for the asset (see §7).

---

## 6. Signature + checksums for the release archive

**Signature** (sign the **exact** `.tar.gz` bytes you host at the Download URL):

```bash
openssl dgst -sha512 -sign ~/.nextcloud/certificates/projectcheck.key \
  /path/to/projectcheck-X.Y.Z.tar.gz | openssl base64
```

(Add `| tr -d '\n'` if the form needs a single line.) If you change the file, regenerate the signature.

**Hashes** (for your records; the UI may ask for SHA-256):

```bash
sha256sum projectcheck-X.Y.Z.tar.gz
sha512sum projectcheck-X.Y.Z.tar.gz
```

---

## 7. Upload at apps.nextcloud.com

Use [upload app release](https://apps.nextcloud.com/developer/apps/releases/new) (or REST API). Match the guide:

| Field | Typical value |
|--------|----------------|
| **Download** | HTTPS URL to `projectcheck-X.Y.Z.tar.gz` |
| **Nightly** | Only for nightlies |
| **Signature** | Output of `openssl dgst -sha512 -sign … .tar.gz \| openssl base64` |

Metadata is taken from the archive (`info.xml`, `CHANGELOG.md`) as described under [App metadata](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#app-metadata).

---

## 8. GitHub Release — use `nextcloud-projectcheck`

App **tags** and **release assets** (`projectcheck-X.Y.Z.tar.gz`) belong on **[`nextcloud-projectcheck`](https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck)** — the public ProjectCheck-only repo — not on a private dev monorepo. Optional layout notes: [REPOSITORY-LAYOUT.md](../../../ready2publish/REPOSITORY-LAYOUT.md) (if present in your monorepo).

```bash
export GH_REPO=aSoftwareByDesignRepository/nextcloud-projectcheck
```

After building `projectcheck-${VERSION}.tar.gz` (output is under `release/` in the app tree):

```bash
VERSION=X.Y.Z
cd /path/to/nextcloud-projectcheck/release

gh release create "v${VERSION}" \
  --repo aSoftwareByDesignRepository/nextcloud-projectcheck \
  --title "v${VERSION}" \
  --notes-file "GITHUB_RELEASE_NOTES_${VERSION}.md" \
  "projectcheck-${VERSION}.tar.gz"
```

Replace asset:

```bash
gh release upload "v${VERSION}" "projectcheck-${VERSION}.tar.gz" \
  --repo aSoftwareByDesignRepository/nextcloud-projectcheck \
  --clobber
```

Source sync without tarball history: [STANDALONE_REPO.md](./STANDALONE_REPO.md) (`git subtree push`).

---

## Optional: GPG-sign the archive

Not required by the store:

```bash
gpg --detach-sign --armor "projectcheck-${VERSION}.tar.gz"
```

---

## What is committed vs ignored

| Artifact | Committed? |
|----------|------------|
| `README.md`, `APPSTORE-RELEASE.md`, `STANDALONE_REPO.md`, `GITHUB_RELEASE_NOTES_*.md` | Yes |
| `CHECKSUMS-X.Y.Z.txt` | Optional |
| `*.tar.gz`, `*.tar.gz.asc` | **No** (gitignored) |
| Private key `*.key` | **Never** |

---

## Quick checklist

- [ ] `info.xml` `<version>` = changelog release version = tarball intent
- [ ] `CHANGELOG.md` has a section for that version
- [ ] Tarball top folder is **`projectcheck/`** only; **no `.git`**
- [ ] Download URL is **HTTPS** and points at the **same** file you signed
- [ ] Release signature is from **`openssl dgst -sha512 -sign … projectcheck-X.Y.Z.tar.gz`**
- [ ] `gh` release commands use **`--repo aSoftwareByDesignRepository/nextcloud-projectcheck`** when that is your canonical remote
