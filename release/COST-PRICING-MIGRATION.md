# Cost pricing migration note (operators & auditors)

**Migration:** `Version2009Date20260522120000`  
**App:** ProjectCheck (`projectcheck`)

## What changes

1. **`pc_projects.cost_rate_mode`** — defaults to `project` for existing rows (unchanged billing behaviour).
2. **`pc_emp_rates`** — append-only employee rate history (admin-maintained). Legacy name `pc_employee_hourly_rates` is renamed by migration 2011.
3. **`pc_pm_rates`** — per-person project rates with effective-from dates. Legacy name `pc_project_member_hourly_rates` is renamed by migration 2011.
4. Legacy positive `pc_project_members.hourly_rate` values are **seeded** into history where applicable.

## After upgrade

- Run `occ upgrade` (or enable the app) so migrations apply.
- From **2.0.46**, a post-migration repair step (`EnsureProjectCheckSchema`) runs on **every** `occ upgrade` and idempotently ensures `pc_*` tables exist (including legacy `projects` → `pc_projects` renames). You do not need a separate manual SQL step for normal Hub/AIO installs.
- If upgrade still fails, check `occ migrations:status projectcheck` and PostgreSQL for `oc_pc_projects` / legacy `oc_projects`.
- Existing time entries keep their stored `hourly_rate` — **no retroactive recalculation**.
- **Pricing method** on a project can be changed only while **no time entries** exist on that project.
- Org default hourly rate in settings applies to **project-rate mode** planning only; employee mode requires explicit rate rows under **Employees**.

## Modes (operator)

| Mode | Billing source |
|------|----------------|
| `project` | Project hourly rate at save time |
| `employee` | Employee rate effective on work date |
| `project_member` | Per-person rate on this project (effective-dated) |

See `pm/app-ideas/projectcheck/README.md` §16 for the full operator cheat sheet.
