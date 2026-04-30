# ProjectCheck Permission Verification Report

Date: 2026-04-30

## Scope

This report verifies the role model from `projectcheck-permissions-hardening_d00cbffe.plan.md` against current route/controller/service/template behavior and automated tests.

## Rule-by-Rule Verification

### 1. Global admins can access/manage everything

- **Status:** PASS
- **Evidence:**
  - `AccessControlService::canManageSettings()` and `canManageOrganization()` map to app/system admin authority.
  - `ProjectService::canUserCreateProject()`, `canUserCreateCustomer()`, `canUserViewAllTimeEntries()`, `canManageSettings()`, `canManageOrganization()` all map to global access.

### 2. Non-admin members can view projects they belong to

- **Status:** PASS
- **Evidence:**
  - `ProjectService::canUserAccessProject()` includes active membership and creator.
  - project list/detail paths use scoped project IDs and access checks.

### 3. Non-admin members can create/edit/delete own time entries only

- **Status:** PASS
- **Evidence:**
  - `TimeEntryController` enforces `user_id` scoping for non-global users in list/export.
  - `TimeEntryControllerTest` includes filter-abuse and own-scope assertions.

### 4. Non-admin members cannot edit/delete projects or manage members

- **Status:** PASS
- **Evidence:**
  - `ProjectService::canUserEditProject()` / `canUserDeleteProject()` deny non-admin non-creator users.
  - team membership mutators enforce `canUserManageMembers()`.

### 5. Non-admin users can view linked customers, cannot create/edit/delete customers

- **Status:** PASS
- **Evidence:**
  - `CustomerController` create/store gated by `canUserCreateCustomer()`.
  - customer read paths use per-user visibility checks.
  - `CustomerControllerTest` includes deny/allow matrix for create/store and stats visibility.

### 6. Non-admin users can view employee list but only own detail page

- **Status:** PASS
- **Evidence:**
  - `EmployeeController::show()` redirects non-global viewers to own profile.
  - `EmployeeControllerTest::testShowRedirectsNonAdminToOwnProfile()`.

### 7. Non-admin users cannot access settings/organization pages

- **Status:** PASS
- **Evidence:**
  - `SettingsController` gated by `ProjectService::canManageSettings()`.
  - `AppConfigController` org endpoints gated by `AccessControlService::canManageOrganization()`.
  - navigation templates only render settings/org links with explicit `canManageSettings` / `canManageOrganization`.

## Automated Evidence

- PHPUnit: `OK (69 tests, 232 assertions)` after this verification pass.
- New middleware test suite added:
  - `tests/Unit/Middleware/AppAccessMiddlewareTest.php`
- E2E spec expanded:
  - `e2e/organization.spec.ts` includes additional route sanity checks.

## Residual Risks / Audit Notes

1. Mutating endpoints still include `NoCSRFRequired` on several controllers (explicitly deferred by request). This is a known audit risk.
2. E2E permission journeys requiring authenticated role accounts depend on environment variables and live test users.

## Go/No-Go (Current)

- **Permissions/Roles correctness:** GO (based on code + unit tests).
- **Full security audit readiness:** CONDITIONAL GO (blocked by CSRF exception policy unless risk-accepted).
