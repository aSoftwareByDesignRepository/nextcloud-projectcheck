# Optional: push from a private monorepo into `nextcloud-projectcheck`

**[`nextcloud-projectcheck`](https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck)** on GitHub is the **canonical public repository** for ProjectCheck: it contains **only** this app (source, issues, releases). Most contributors and users never need this document.

Use the workflow below **only if** you develop ProjectCheck inside a **larger private repository** (e.g. a Nextcloud “all apps” monorepo) and want to **publish** the same tree to **`nextcloud-projectcheck`** without maintaining two codebases by hand.

Develop in the private repo (canonical GitHub name **`nextcloud-development`**; local folder may differ). Publish **source** to **`aSoftwareByDesignRepository/nextcloud-projectcheck`** with `git subtree`. Further policy (visibility, layout): see monorepo `ready2publish/REPOSITORY-LAYOUT.md` if present.

**Convenience script** (run from **monorepo root**, not inside `nextcloud-projectcheck`):

`scripts/push-public-app-subtree.sh projectcheck aSoftwareByDesignRepository/nextcloud-projectcheck`

That runs `git subtree split` on `apps/projectcheck` and pushes to `main` on the standalone repo.

Suggested remote (adjust org/name if you prefer):

| | |
|--|--|
| **Public app repo** | `aSoftwareByDesignRepository/nextcloud-projectcheck` |
| **Clone URL** | `https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck.git` |

**`appinfo/info.xml`** should list **`nextcloud-projectcheck`** for **`<repository>`** and **`<bugs>`** (already the case in this tree).

Create an **empty** repository on GitHub if needed (no README/license if you want a clean first push from subtree; or keep README and merge carefully).

---

## One-time setup (monorepo)

From the **monorepo root** (parent of `apps/`):

```bash
cd /path/to/nextcloud-development

# SSH or HTTPS — your choice
git remote add projectcheck-public git@github.com:aSoftwareByDesignRepository/nextcloud-projectcheck.git
# git remote add projectcheck-public https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck.git
```

---

## Push app sources: `git subtree push`

This publishes **only** `apps/projectcheck/` history to the default branch of `projectcheck-public` (usually `main`).

```bash
cd /path/to/nextcloud-development
git subtree push --prefix=apps/projectcheck projectcheck-public main
```

- First push can take a while on a large monorepo.
- If the remote already has commits (e.g. README), you may need a **force** push after coordinating, or use the **split + push branch** variant below.

### Variant: split to a branch, then push (more control)

```bash
cd /path/to/nextcloud-development
git subtree split --prefix=apps/projectcheck -b split-projectcheck
git push projectcheck-public split-projectcheck:main --force
# optional: delete local branch
git branch -D split-projectcheck
```

Use `--force` only when you intend to replace the remote history (e.g. empty repo or you own the branch).

---

## After monorepo changes

Whenever you want **`nextcloud-projectcheck`** to match the monorepo’s `apps/projectcheck`:

1. Commit and push your work on **`master`** (or your main branch) in the monorepo.
2. Run **`git subtree push`** again (same command as above).

---

## Releases (tarball + GitHub Release)

Build the `.tar.gz` as in [APPSTORE-RELEASE.md](./APPSTORE-RELEASE.md), then attach it to a **tag on `nextcloud-projectcheck`**, not on the private monorepo.

```bash
export GH_REPO=aSoftwareByDesignRepository/nextcloud-projectcheck
gh release create "v${VERSION}" --title "v${VERSION}" "projectcheck-${VERSION}.tar.gz"
```

---

## `info.xml` URLs

Point **repository** and **bugs** at the standalone repo, e.g.:

- `https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck`
- `https://github.com/aSoftwareByDesignRepository/nextcloud-projectcheck/issues`
