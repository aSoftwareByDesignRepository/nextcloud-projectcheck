import { releaseSharedInstanceLock } from './shared-instance-lock.mjs'

export default async function globalTeardown() {
	await releaseSharedInstanceLock()
}
