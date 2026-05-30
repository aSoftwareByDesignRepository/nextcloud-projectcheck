(function () {
	'use strict';

	const MUTATION_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

	function csrfToken() {
		if (window.OC && OC.requestToken) {
			return OC.requestToken;
		}
		const input = document.querySelector('input[name="requesttoken"]');
		return input ? input.value : '';
	}

	function buildUrl(path, params) {
		const built = OC.generateUrl(path);
		const query = new URLSearchParams();
		Object.entries(params || {}).forEach(([key, value]) => {
			if (value === undefined || value === null || value === '') {
				return;
			}
			query.append(key, String(value));
		});
		const suffix = query.toString();
		return suffix ? `${built}?${suffix}` : built;
	}

	async function request(path, options) {
		const opts = options || {};
		const method = (opts.method || 'GET').toUpperCase();
		const isMutation = MUTATION_METHODS.has(method);
		const headers = Object.assign({ Accept: 'application/json' }, opts.headers || {});
		if (isMutation) {
			const token = csrfToken();
			if (!token) {
				throw new Error(t('projectcheck', 'Missing CSRF request token.'));
			}
			headers.requesttoken = token;
		}
		if (opts.body !== undefined) {
			headers['Content-Type'] = 'application/json';
		}
		const response = await fetch(buildUrl(path, opts.params), {
			method,
			credentials: 'same-origin',
			headers,
			body: opts.body === undefined ? undefined : JSON.stringify(opts.body),
			signal: opts.signal,
		});
		const isJson = (response.headers.get('content-type') || '').toLowerCase().includes('application/json');
		const data = isJson ? await response.json().catch(() => null) : await response.text();
		if (!response.ok) {
			const message = (data && typeof data === 'object' && (data.error || data.message))
				? String(data.error || data.message)
				: t('projectcheck', 'Request failed.');
			const err = new Error(message);
			err.status = response.status;
			err.payload = data;
			throw err;
		}
		return data;
	}

	/**
	 * POST delete (Nextcloud CSRF). Appends /delete when the path has no action suffix.
	 */
	function postDelete(path, params) {
		const normalized = String(path).replace(/\/?$/, '/delete');
		return request(normalized, { method: 'POST', params });
	}

	window.ProjectCheckApi = {
		get: (path, params) => request(path, { method: 'GET', params }),
		post: (path, body, params) => request(path, { method: 'POST', body, params }),
		put: (path, body, params) => request(path, { method: 'PUT', body, params }),
		/** @deprecated Use postDelete — DELETE + query token is unreliable under CSP. */
		del: (path, params) => postDelete(path, params),
		postDelete,
		request,
	};

	/**
	 * Accessible user feedback — never blocks with window.alert (WCAG / audit).
	 */
	function notifyUser(message, type) {
		const msg = String(message || '').trim();
		if (msg === '') {
			return;
		}
		if (typeof window.OC !== 'undefined' && window.OC.Notification) {
			window.OC.Notification.showTemporary(
				msg,
				type === 'error' ? { type: 'error' } : undefined,
			);
			return;
		}
		const region = document.getElementById('pc-alert-region');
		if (region) {
			region.textContent = msg;
		}
	}

	window.ProjectCheckNotify = {
		show: notifyUser,
		error: (message) => notifyUser(message, 'error'),
	};
})();
