// @ts-check
/**
 * Shared Nextcloud e2e instance lease — serializes concurrent Playwright runs
 * across farm agents on the one shared instance at localhost:8081.
 *
 * WHY: sibling suites mutate the same app-config keys, fixture users and
 * shared rows; a sustained concurrent writer defeats Playwright retries
 * entirely (observed 2026-10-17: a farm proof sweep overwrote azc's
 * hr_notification_recipients between a POST and its GET re-read — a phantom
 * "product" failure). Contract + lease file: see
 * .cursor/scripts/nc-e2e-instance-lock.sh and
 * .cursor/scripts/nc-e2e-instance-lock.mjs.
 *
 * Best-effort by design: this shim upward-searches for the canonical farm
 * implementation and dynamic-imports it. Outside the farm checkout
 * (published/standalone repo) the module is absent and both functions no-op —
 * a single-orchestrator environment needs no lease.
 */
import { existsSync } from 'node:fs'
import { dirname, parse, resolve } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

let implPromise = null

function canonicalModule() {
	if (!implPromise) {
		implPromise = (async () => {
			let dir = dirname(fileURLToPath(import.meta.url))
			const { root } = parse(dir)
			while (dir !== root) {
				const candidate = resolve(dir, '.cursor/scripts/nc-e2e-instance-lock.mjs')
				if (existsSync(candidate)) {
					return import(pathToFileURL(candidate).href)
				}
				dir = dirname(dir)
			}
			return null
		})()
	}
	return implPromise
}

/** Acquire the lease; waits up to NC_E2E_LOCK_WAIT_SEC (default 90 min). */
export async function acquireSharedInstanceLock(app) {
	try {
		const m = await canonicalModule()
		if (m) {
			m.acquireInstanceLock(app)
		}
	} catch (e) {
		if (process.env.NC_E2E_LOCK_STRICT === '1') {
			throw e
		}
		// eslint-disable-next-line no-console
		console.warn(`[e2e shared-instance-lock] acquire failed (non-fatal): ${e.message}`)
	}
}

/** Release the lease held by this process (token-matched; no-op otherwise). */
export async function releaseSharedInstanceLock() {
	try {
		const m = await canonicalModule()
		if (m) {
			m.releaseInstanceLock()
		}
	} catch {
		/* best-effort */
	}
}
