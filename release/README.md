# Release folder (ProjectCheck)

This directory holds **release documentation** for the **public ProjectCheck app repository** workflow (same pattern as ArbeitszeitCheck).

| File | Purpose |
|------|---------|
| [APPSTORE-RELEASE.md](./APPSTORE-RELEASE.md) | Nextcloud App Store: build tarball, checksums, OpenSSL signature, GitHub Release |
| [STANDALONE_REPO.md](./STANDALONE_REPO.md) | Sync **`apps/projectcheck`** from this monorepo to the **standalone GitHub repo** (`git subtree`) |

**Suggested public repo** (create on GitHub if it does not exist yet):

- `https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck`

Update **`appinfo/info.xml`** `<repository>` / `<bugs>` when the standalone repo is live.

**Generated** (gitignored — see app `.gitignore`):

- `projectcheck-*.tar.gz`, signatures, local `SIGNATURE-*.txt`

## Quick tarball (from monorepo `apps/`)

```bash
cd apps
VERSION=2.0.21
tar --exclude='projectcheck/node_modules' \
    --exclude='projectcheck/.git' \
    --exclude='projectcheck/release/projectcheck-*.tar.gz' \
    -czf "projectcheck/release/projectcheck-${VERSION}.tar.gz" projectcheck
```

Stricter “upload-only” bundles are documented in the monorepo `ready4upload/BUILD_INSTRUCTIONS.txt`.

Details: **APPSTORE-RELEASE.md**.
