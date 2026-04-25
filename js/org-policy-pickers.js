/**
 * User/group search + chips for the organization access form. Textareas are the
 * source of truth for save; this module only improves picking.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */
(function () {
	'use strict';

	function getRequestToken() {
		if (typeof OC !== 'undefined' && OC.requestToken) {
			return OC.requestToken;
		}
		var m = document.querySelector('meta[name="requesttoken"]');
		return m ? m.getAttribute('content') || '' : '';
	}

	/**
	 * @param {HTMLFormElement} form
	 * @returns {Record<string, string>}
	 */
	function getPickerStrings(form) {
		var raw = form.getAttribute('data-pc-form-strings') || '';
		if (!raw) {
			return {};
		}
		try {
			var o = JSON.parse(raw);
			if (o && o.picker && typeof o.picker === 'object') {
				return o.picker;
			}
		} catch (e) {
		}
		return {};
	}

	function toLines(text) {
		if (!text) {
			return [];
		}
		return text
			.split(/\r\n|\r|\n/g)
			.map(function (l) {
				return l.trim();
			})
			.filter(function (l) {
				return l.length > 0;
			});
	}

	/**
	 * @param {Map<string, {id: string, name: string}>} map
	 */
	function mapToText(map) {
		return Array.from(map.keys()).join('\n');
	}

	/**
	 * @param {HTMLLIElement} li
	 * @param {string} id
	 * @param {string} name
	 * @param {Record<string, string>} ps
	 */
	function fillChip(li, id, name, ps) {
		while (li.firstChild) {
			li.removeChild(li.firstChild);
		}
		li.setAttribute('data-entity-id', id);
		var w = document.createElement('div');
		w.className = 'projectcheck-entity-chip__text';
		var d = document.createElement('span');
		d.className = 'projectcheck-entity-chip__name';
		d.appendChild(document.createTextNode(name || id));
		w.appendChild(d);
		if (name && name !== id) {
			var s = document.createElement('span');
			s.className = 'projectcheck-entity-chip__id';
			s.appendChild(document.createTextNode(id));
			w.appendChild(s);
		}
		li.appendChild(w);
		var b = document.createElement('button');
		b.type = 'button';
		b.className = 'projectcheck-entity-chip__remove';
		b.appendChild(document.createTextNode('×'));
		b.setAttribute(
			'aria-label',
			(ps.removeItemAria || 'Remove') +
				' — ' +
				(name && name !== id ? name + ' (' + id + ')' : id)
		);
		li.appendChild(b);
	}

	/**
	 * @param {HTMLUListElement | null} listEl
	 * @param {Map<string, {id: string, name: string}>} map
	 * @param {string} taId
	 * @param {Record<string, string>} ps
	 */
	function drawChips(listEl, map, taId, ps) {
		var ta = document.getElementById(taId);
		if (ta) {
			ta.value = mapToText(map);
		}
		if (!listEl) {
			return;
		}
		while (listEl.firstChild) {
			listEl.removeChild(listEl.firstChild);
		}
		map.forEach(function (o, k) {
			if (!o) {
				return;
			}
			var li = document.createElement('li');
			li.className = 'projectcheck-entity-chip';
			fillChip(li, o.id, o.name, ps);
			var btn = li.querySelector('button');
			if (btn) {
				btn.addEventListener('click', function (e) {
					e.preventDefault();
					map.delete(k);
					drawChips(listEl, map, taId, ps);
				});
			}
			listEl.appendChild(li);
		});
	}

	/**
	 * @param {string} url
	 * @param {string} q
	 * @param {function({ items: *[], err: (null | string) }): void} done
	 */
	function fetchList(url, q, done) {
		/** @param {{ items: *[], err: (null | string) }} o */
		var finish = function (o) {
			if (typeof done === 'function') {
				done(o);
			}
		};
		if (!url) {
			finish({ items: [], err: null });
			return;
		}
		if (!getRequestToken()) {
			finish({ items: [], err: 'unauthorized' });
			return;
		}
		var u = new URL(url, window.location.origin);
		u.searchParams.set('q', q);
		fetch(u.toString(), {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				requesttoken: getRequestToken(),
				Accept: 'application/json'
			}
		})
			.then(function (r) {
				return r.text().then(function (raw) {
					return { s: r.status, ok: r.ok, body: raw };
				});
			})
			.then(function (res) {
				if (res.s === 401) {
					finish({ items: [], err: 'unauthorized' });
					return;
				}
				if (res.s === 403) {
					finish({ items: [], err: 'forbidden' });
					return;
				}
				if (res.s === 0) {
					finish({ items: [], err: 'network' });
					return;
				}
				if (res.s >= 500) {
					finish({ items: [], err: 'server' });
					return;
				}
				if (res.s >= 400) {
					finish({ items: [], err: 'server' });
					return;
				}
				if (!res.ok) {
					finish({ items: [], err: 'server' });
					return;
				}
				var j = null;
				try {
					j = res.body ? JSON.parse(res.body) : null;
				} catch (e) {
					j = null;
				}
				if (j && j.ok === true && Array.isArray(j.items)) {
					finish({ items: j.items, err: null });
					return;
				}
				if (j && j.ok === false) {
					var er = j.error;
					if (er === 'unauthorized') {
						finish({ items: [], err: 'unauthorized' });
						return;
					}
					if (er === 'forbidden') {
						finish({ items: [], err: 'forbidden' });
						return;
					}
				}
				finish({ items: [], err: 'server' });
			})
			.catch(function () {
				finish({ items: [], err: 'network' });
			});
	}

	/**
	 * @param {string} targetId
	 * @param {string} chipsId
	 * @param {string|undefined} qId
	 * @param {string|undefined} suggestId
	 * @param {string|undefined} searchBaseUrl
	 * @param {Map<string, {id: string, name: string}>} map
	 * @param {Record<string, string>} ps
	 * @param {string} [scope]
	 */
	function block(
		targetId,
		chipsId,
		qId,
		suggestId,
		searchBaseUrl,
		map,
		ps
	) {
		var t = document.getElementById(targetId);
		var listEl = document.getElementById(chipsId);
		var qin = qId ? document.getElementById(qId) : null;
		/** @type {HTMLDivElement | null} */
		var suggest = suggestId
			? (document.getElementById(suggestId))
			: null;
		if (!t) {
			return;
		}
		if (!map) {
			return;
		}
		toLines(t.value).forEach(function (id) {
			map.set(id, { id: id, name: id });
		});
		drawChips(/** @type {HTMLUListElement} */ (listEl) , map, targetId, ps);
		t.addEventListener('input', function () {
			map.clear();
			toLines(t.value).forEach(function (id) {
				map.set(id, { id: id, name: id });
			});
			drawChips(/** @type {HTMLUListElement} */ (listEl) , map, targetId, ps);
		});

		if (!searchBaseUrl || !qin || !suggest) {
			return;
		}
		/** @type {ReturnType<typeof setTimeout> | 0} */
		var tmr = 0;
		/**
		 * Bumped on every field change. Drop responses where start id !== end id
		 * (user typed again while a search was in flight).
		 */
		var inflight = 0;
		/** @type {number} */
		var ar = 0;

		/**
		 * @param {Record<string, string>} p
		 * @param {string} errK
		 */
		function errMsg(p, errK) {
			if (errK === 'unauthorized') {
				return typeof p.searchErrorAuth === 'string' && p.searchErrorAuth ? p.searchErrorAuth : null;
			}
			if (errK === 'forbidden') {
				return typeof p.searchErrorPermission === 'string' && p.searchErrorPermission
					? p.searchErrorPermission
					: null;
			}
			if (errK === 'network') {
				return typeof p.searchErrorNetwork === 'string' && p.searchErrorNetwork
					? p.searchErrorNetwork
					: null;
			}
			return typeof p.searchErrorServer === 'string' && p.searchErrorServer
				? p.searchErrorServer
				: null;
		}

		function setSuggestVisible(visible) {
			if (qin) {
				qin.setAttribute('aria-expanded', visible ? 'true' : 'false');
			}
			if (!visible && qin) {
				qin.removeAttribute('aria-activedescendant');
			}
		}

		function setActive(opts, idx) {
			if (!opts || !opts.length) {
				if (qin) {
					qin.removeAttribute('aria-activedescendant');
				}
				return;
			}
			if (typeof idx !== 'number' || isNaN(idx) || idx < 0) {
				idx = 0;
			}
			if (idx >= opts.length) {
				idx = opts.length - 1;
			}
			ar = idx;
			if (!qin) {
				return;
			}
			for (var i = 0; i < opts.length; i++) {
				if (i === ar) {
					opts[i].setAttribute('aria-selected', 'true');
					if (opts[i].id) {
						qin.setAttribute('aria-activedescendant', opts[i].id);
					}
				} else {
					opts[i].setAttribute('aria-selected', 'false');
				}
			}
		}

		/**
		 * @param {Array<{id:string, displayName?:string}>} items
		 * @param {string | null} err
		 */
		function showOpts(items, err) {
			if (!suggest) {
				return;
			}
			suggest.innerHTML = '';
			if (err) {
				if (qin) {
					qin.setAttribute('aria-controls', suggestId);
					qin.removeAttribute('aria-activedescendant');
				}
				var pe = errMsg(ps, err);
				if (!pe && typeof ps.searchErrorServer === 'string' && ps.searchErrorServer) {
					pe = ps.searchErrorServer;
				}
				if (pe) {
					var perr = document.createElement('p');
					perr.className = 'projectcheck-entity-picker__noresult projectcheck-entity-picker__noresult--err';
					perr.setAttribute('role', 'alert');
					perr.appendChild(document.createTextNode(pe));
					suggest.appendChild(perr);
				}
				suggest.hidden = !suggest.hasChildNodes();
				setSuggestVisible(!!(suggest && !suggest.hidden));
				if (suggest.hidden) {
					ar = 0;
				}
				return;
			}
			/** @type {Array<{id:string, displayName?:string}>} */
			var pick = (items || []).filter(function (x) {
				return x && x.id && !map.has(x.id);
			});
			if (!pick.length) {
				if (qin) {
					qin.setAttribute('aria-controls', suggestId);
				}
				if ((items && items.length) || toLines((qin && qin.value) || '').join('').length >= 2) {
					var p = document.createElement('p');
					p.className = 'projectcheck-entity-picker__noresult';
					p.setAttribute('role', 'status');
					p.appendChild(
						document.createTextNode(
							typeof ps.noResults === 'string' ? ps.noResults : ''
						)
					);
					if (p.textContent) {
						suggest.appendChild(p);
					}
				}
				suggest.hidden = suggest.innerHTML === '';
				setSuggestVisible(!!(suggest && !suggest.hidden));
				if (suggest.hidden) {
					ar = 0;
				}
				return;
			}
			var listbox = document.createElement('ul');
			var lbId = suggestId + '-lb';
			listbox.setAttribute('class', 'projectcheck-entity-picker__listbox');
			listbox.setAttribute('id', lbId);
			listbox.setAttribute('role', 'listbox');
			if (qin) {
				qin.setAttribute('aria-controls', lbId);
			}
			for (var oi = 0; oi < pick.length; oi++) {
				var it = pick[oi];
				var o = document.createElement('li');
				o.setAttribute('role', 'option');
				o.setAttribute('id', suggestId + '-o' + oi);
				if (it.displayName && it.displayName !== it.id) {
					var t1 = document.createElement('div');
					t1.className = 'projectcheck-entity-suggest__line';
					t1.appendChild(
						document.createTextNode(String(it.displayName))
					);
					var t2 = document.createElement('div');
					t2.className = 'projectcheck-entity-suggest__id';
					t2.appendChild(document.createTextNode(String(it.id)));
					o.appendChild(t1);
					o.appendChild(t2);
				} else {
					var t0 = document.createElement('div');
					t0.className = 'projectcheck-entity-suggest__line';
					t0.appendChild(document.createTextNode(String(it.id)));
					o.appendChild(t0);
				}
				o.setAttribute('aria-selected', oi === 0 ? 'true' : 'false');
				o.addEventListener('mousedown', function (ev) {
					if (ev.button !== 0) {
						return;
					}
					ev.preventDefault();
				});
				(function (I) {
					o.addEventListener('click', function () {
						if (!I.id) {
							return;
						}
						map.set(I.id, { id: I.id, name: I.displayName || I.id });
						drawChips(/** @type {HTMLUListElement} */ (listEl) , map, targetId, ps);
						if (suggest) {
							suggest.innerHTML = '';
							suggest.hidden = true;
						}
						if (qin) {
							qin.setAttribute('aria-controls', suggestId);
						}
						setSuggestVisible(false);
						ar = 0;
						if (qin) {
							qin.value = '';
							qin.focus();
						}
					});
				})(it);
				listbox.appendChild(o);
			}
			suggest.appendChild(listbox);
			suggest.hidden = false;
			setSuggestVisible(true);
			/** @type {HTMLLIElement[]} */
			var opts = [].slice.call(
				listbox.querySelectorAll('li[role=option]')
			) ;
			setActive(opts, 0);
		}

		qin.addEventListener('input', function () {
			if (tmr) {
				clearTimeout(tmr);
			}
			inflight += 1;
			var v = (qin && qin.value) ? (qin && qin.value) .trim() : '';
			if (v.length < 2) {
				if (suggest) {
					suggest.innerHTML = '';
					suggest.hidden = true;
				}
				if (qin) {
					qin.setAttribute('aria-controls', suggestId);
				}
				setSuggestVisible(false);
				ar = 0;
				return;
			}
			tmr = setTimeout(function () {
				var my = inflight;
				fetchList(searchBaseUrl, (qin && qin.value) ? (qin && qin.value) .trim() : '', function (result) {
					if (my !== inflight) {
						return;
					}
					showOpts(result.items, result.err || null);
				});
			}, 320);
		});
		qin.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				/** @type {HTMLLIElement[]} */
				var forEnter = [].slice.call(
					(suggest && !suggest.hidden) ? (suggest.querySelectorAll('li[role=option]'))  : []
				) ;
				if (forEnter.length) {
					(forEnter[ar] || forEnter[0]).click();
				}
				return;
			}
			/** @type {HTMLLIElement[]} */
			var optLis = [].slice.call(
				(suggest && !suggest.hidden) ? (suggest.querySelectorAll('li[role=option]'))  : []
			) ;
			if (e.key === 'Escape' && suggest) {
				suggest.innerHTML = '';
				suggest.hidden = true;
				if (qin) {
					qin.setAttribute('aria-controls', suggestId);
				}
				setSuggestVisible(false);
				ar = 0;
				if (e.preventDefault) {
					e.preventDefault();
				}
				return;
			}
			if (!optLis.length) {
				return;
			}
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				setActive(optLis, (ar + 1) % optLis.length);
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				setActive(optLis, (ar - 1 + optLis.length) % optLis.length);
			}
		});
	}

	/**
	 * @param {HTMLFormElement} form
	 * @param {string|undefined} usersU
	 * @param {string|undefined} groupsU
	 */
	function bindForm(form, usersU, groupsU) {
		if (form.getAttribute('data-pc-pickers-v1') === '1') {
			return;
		}
		var ps = getPickerStrings(form);
		/** @type {Map<string, {id:string, name:string}>} */
		var mUsers = new Map();
		/** @type {Map<string, {id:string, name:string}>} */
		var mGroups = new Map();
		/** @type {Map<string, {id:string, name:string}>} */
		var mAdm = new Map();
		block(
			'pc_allowed_users',
			'pc_allowed_users_chips',
			'pc_allowed_users_q',
			'pc_allowed_users_suggest',
			usersU,
			mUsers,
			ps
		);
		block(
			'pc_allowed_groups',
			'pc_allowed_groups_chips',
			'pc_allowed_groups_q',
			'pc_allowed_groups_suggest',
			groupsU,
			mGroups,
			ps
		);
		block(
			'pc_app_admins',
			'pc_app_admins_chips',
			'pc_app_admins_q',
			'pc_app_admins_suggest',
			usersU,
			mAdm,
			ps
		);
		form.setAttribute('data-pc-pickers-v1', '1');
	}

	function ready() {
		[].forEach.call(
			document.querySelectorAll('form.projectcheck-admin-form') ,
			function (form) {
				if (form.getAttribute('data-pc-skip-pickers') === '1') {
					return;
				}
				var u = form.getAttribute('data-pc-search-users-url') || '';
				var g = form.getAttribute('data-pc-search-groups-url') || '';
				bindForm(/** @type {HTMLFormElement} */(form) , u, g);
			}
		);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', ready);
	} else {
		ready();
	}
})();
