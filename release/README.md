# Release folder (ProjectCheck)

Documentation for **shipping** ProjectCheck to the Nextcloud App Store and for **GitHub Releases** on **[`nextcloud-projectcheck`](https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck)** — the public repo that contains **only** this app.

| File | Purpose |
|------|---------|
| [APPSTORE-RELEASE.md](./APPSTORE-RELEASE.md) | Nextcloud App Store: build tarball, checksums, OpenSSL signature, GitHub Release |
| [STANDALONE_REPO.md](./STANDALONE_REPO.md) | **Optional:** sync `apps/projectcheck` from a **private monorepo** into `nextcloud-projectcheck` (`git subtree`) |

**Suggested public repo:** `https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck`

Update **`appinfo/info.xml`** `<repository>` / `<bugs>` if your fork uses different URLs.

**Generated** (gitignored — see app `.gitignore`):

- `projectcheck-*.tar.gz`, signatures, local `SIGNATURE-*.txt`

## Quick tarball

From a clone of **`nextcloud-projectcheck`** (app at repo root):

```bash
./release/build-appstore-archive.sh X.Y.Z
```

From a **monorepo** (app at `apps/projectcheck`):

```bash
./apps/projectcheck/release/build-appstore-archive.sh X.Y.Z
```

Manual `tar` examples: **APPSTORE-RELEASE.md**.

Stricter “upload-only” bundles may be documented in the private monorepo under `ready4upload/` (maintainers only).

Details: **APPSTORE-RELEASE.md**.
