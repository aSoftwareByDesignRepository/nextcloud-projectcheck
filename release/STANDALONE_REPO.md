# Standalone repository: ProjectCheck

Develop in the **Nextcloud monorepo** (`nextcloud-dev`), publish **source + releases** from a **dedicated public repo**, same idea as **ArbeitszeitCheck** / `ArbeitszeitCheck`.

**Convenience script** (from monorepo root): `scripts/push-public-app-subtree.sh projectcheck aSoftwareByDesignRepository/nextcloud-projectcheck` — runs `git subtree split` and pushes to `main`.

Suggested remote (adjust org/name if you prefer):

| | |
|--|--|
| **Public repo** | `aSoftwareByDesignRepository/nextcloud-projectcheck` |
| **Clone URL** | `https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck.git` |

**`appinfo/info.xml`** should list **`nextcloud-projectcheck`** for **`<repository>`** and **`<bugs>`** (App Store and clone URLs).

Create an **empty** repository on GitHub (no README/license if you want a clean first push from subtree; or keep README and merge carefully).

---

## One-time setup

From the **monorepo root** (parent of `apps/`):

```bash
cd /path/to/nextcloud-dev

# SSH or HTTPS — your choice
git remote add projectcheck-public git@github.com:aSoftwareByDesignRepository/nextcloud-projectcheck.git
# git remote add projectcheck-public https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck.git
```

---

## Push app sources: `git subtree push`

This publishes **only** `apps/projectcheck/` history to the default branch of `projectcheck-public` (usually `main`).

```bash
cd /path/to/nextcloud-dev
git subtree push --prefix=apps/projectcheck projectcheck-public main
```

- First push can take a while on a large monorepo.
- If the remote already has commits (e.g. README), you may need a **force** push after coordinating, or use the **split + push branch** variant below.

### Variant: split to a branch, then push (more control)

```bash
cd /path/to/nextcloud-dev
git subtree split --prefix=apps/projectcheck -b split-projectcheck
git push projectcheck-public split-projectcheck:main --force
# optional: delete local branch
git branch -D split-projectcheck
```

Use `--force` only when you intend to replace the remote history (e.g. empty repo or you own the branch).

---

## After monorepo changes

Whenever you want the public repo to match the monorepo’s `apps/projectcheck`:

1. Commit and push your work on **`master`** (or your main branch) in the monorepo.
2. Run **`git subtree push`** again (same command as above).

---

## Releases (tarball + GitHub Release)

Build the `.tar.gz` as in [APPSTORE-RELEASE.md](./APPSTORE-RELEASE.md), then attach it to a **tag on `projectcheck-public`**, not on the monorepo.

```bash
export GH_REPO=aSoftwareByDesignRepository/nextcloud-projectcheck
gh release create "v${VERSION}" --title "v${VERSION}" "projectcheck-${VERSION}.tar.gz"
```

---

## `info.xml` URLs

Point **repository** and **bugs** at the standalone repo once it exists, e.g.:

- `https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck`
- `https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck/issues`
