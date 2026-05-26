/**
 * Project detail page: team picker, status modal, member removal.
 */
(function () {
    'use strict';
    const cfg = window.projectDetailConfig || {};
if (cfg.bulkAddSuccess && window.history && typeof window.history.replaceState === 'function') {
            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('bulk_add_success');
            cleanUrl.searchParams.delete('added_count');
            window.history.replaceState({}, '', cleanUrl.toString());
        }
const projectcheckToken = cfg.requestToken || '';
        const changeStatusPostUrl = cfg.urls.changeStatusPost;
        const addTeamUrl = cfg.urls.addTeam;
        const addAllTeamUrl = cfg.urls.addAllTeam;
        const searchUsersUrl = cfg.urls.searchUsers;
        const errorStatusMsg = cfg.messages.errorStatus;
        const selectStatusMsg = cfg.messages.selectStatus;
        const errorGeneric = cfg.messages.errorGeneric;
        const addMemberError = cfg.messages.addMemberError;
        const noUsersFoundMsg = cfg.messages.noUsersFound;
        const chooseUserMsg = cfg.messages.chooseUser;
        const loadingUsersMsg = cfg.messages.loadingUsers;
        const enterMoreCharsMsg = cfg.messages.enterMoreChars;
        const searchUsersErrorMsg = cfg.messages.searchUsersError;
        const addAllMembersError = cfg.messages.addAllMembers;
        const memberRateRequiredMsg = cfg.messages.memberRateRequired;
        const requiresMemberRate = cfg.requiresMemberRate === true;
        let lastFocusedElement = null;
        let memberSearchAbort = null;
        let memberPickerTeardown = null;
        let memberAddSubmitting = false;
        let memberAddAllSubmitting = false;
        let statusChangeSubmitting = false;

        function isMemberRateValid() {
            if (!requiresMemberRate) {
                return true;
            }
            const rateInput = document.getElementById('teamMemberHourlyRate');
            if (!(rateInput instanceof HTMLInputElement)) {
                return true;
            }
            const rateVal = parseFloat(rateInput.value);
            return Number.isFinite(rateVal) && rateVal > 0;
        }

        function updateAddMemberSubmitState() {
            const submitButton = document.getElementById('submit-add-team-member');
            if (!(submitButton instanceof HTMLButtonElement)) {
                return;
            }
            const hasUser = resolveSelectedUserId() !== '';
            const rateOk = isMemberRateValid();
            const canSubmit = hasUser && rateOk;
            submitButton.disabled = !canSubmit || memberAddSubmitting;
            if (!hasUser) {
                submitButton.title = chooseUserMsg;
            } else if (requiresMemberRate && !rateOk) {
                submitButton.title = memberRateRequiredMsg || chooseUserMsg;
            } else {
                submitButton.title = '';
            }

            const addAllButton = document.getElementById('submit-add-all-team-members');
            if (addAllButton instanceof HTMLButtonElement) {
                addAllButton.disabled = memberAddSubmitting || memberAddAllSubmitting;
            }
        }

        function updateSelectedUserSummary(uid, label) {
            const summary = document.getElementById('teamMemberSelected');
            const summaryText = document.getElementById('teamMemberSelectedText');
            if (!summary || !summaryText) {
                return;
            }
            if (!uid) {
                summary.hidden = true;
                summaryText.textContent = '';
                return;
            }
            summaryText.textContent = label || uid;
            summary.hidden = false;
        }

        function setModalOpen(modal, open, openBtn) {
            if (!modal) {
                return;
            }
            if (open) {
                lastFocusedElement = document.activeElement;
            }
            modal.style.display = open ? 'block' : 'none';
            modal.setAttribute('aria-hidden', open ? 'false' : 'true');
            if (openBtn) {
                openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            if (!open && lastFocusedElement instanceof HTMLElement) {
                lastFocusedElement.focus();
                lastFocusedElement = null;
            }
        }

        function isModalOpen(modal) {
            return modal && modal.getAttribute('aria-hidden') === 'false';
        }

        function getOpenProjectcheckModal() {
            const addMemberModal = document.getElementById('addTeamMemberModal');
            if (isModalOpen(addMemberModal)) {
                return addMemberModal;
            }
            const statusModal = document.getElementById('statusChangeModal');
            if (isModalOpen(statusModal)) {
                return statusModal;
            }
            return null;
        }

        function getFocusableElements(container) {
            if (!container) {
                return [];
            }
            return Array.from(container.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
                .filter((el) => el instanceof HTMLElement && !el.hidden && el.offsetParent !== null);
        }

        function trapFocusInModal(e) {
            if (e.key !== 'Tab') {
                return;
            }
            const modal = getOpenProjectcheckModal();
            if (!modal) {
                return;
            }
            const focusable = getFocusableElements(modal);
            if (focusable.length === 0) {
                e.preventDefault();
                return;
            }
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }

        function showError(message) {
            const msg = message || errorGeneric;
            const errorSlot = document.getElementById('add-team-member-error');
            const uidInput = document.getElementById('teamMemberSearch');
            if (errorSlot) {
                errorSlot.textContent = msg;
            }
            if (uidInput) {
                uidInput.setAttribute('aria-invalid', 'true');
            }
            if (typeof window.ProjectCheckNotify !== 'undefined') {
                window.ProjectCheckNotify.error(msg);
                return;
            }
            if (typeof OC !== 'undefined' && OC.Notification) {
                OC.Notification.showTemporary(msg);
                return;
            }
            const region = document.getElementById('pc-alert-region');
            if (region) {
                region.textContent = msg;
            }
        }

        function clearMemberSearchResults() {
            const results = document.getElementById('teamMemberSearchResults');
            const searchInput = document.getElementById('teamMemberSearch');
            if (results) {
                results.replaceChildren();
                results.hidden = true;
            }
            if (searchInput) {
                searchInput.removeAttribute('aria-activedescendant');
                searchInput.setAttribute('aria-expanded', 'false');
            }
            updateAddMemberSubmitState();
        }

        function setSelectedUser(uid, label) {
            const hiddenInput = document.getElementById('teamMemberUserId');
            const searchInput = document.getElementById('teamMemberSearch');
            if (hiddenInput) {
                hiddenInput.value = uid;
            }
            if (searchInput) {
                searchInput.value = label;
                searchInput.setAttribute('aria-invalid', 'false');
            }
            updateSelectedUserSummary(uid, label);
            clearMemberSearchResults();
            updateAddMemberSubmitState();
            if (requiresMemberRate) {
                const rateInput = document.getElementById('teamMemberHourlyRate');
                if (rateInput instanceof HTMLInputElement) {
                    rateInput.removeAttribute('aria-invalid');
                    rateInput.focus();
                }
            }
        }

        function bindMemberSearch() {
            const searchInput = document.getElementById('teamMemberSearch');
            const hiddenInput = document.getElementById('teamMemberUserId');
            const suggest = document.getElementById('teamMemberSearchResults');
            const picker = window.ProjectCheckEntityPicker;
            if (!searchInput || !hiddenInput || !suggest || !picker || typeof picker.bindCombobox !== 'function') {
                return;
            }
            if (memberPickerTeardown) {
                memberPickerTeardown();
            }
            memberPickerTeardown = picker.bindCombobox({
                input: searchInput,
                suggest: suggest,
                isTaken: function () {
                    return false;
                },
                strings: {
                    noResults: noUsersFoundMsg,
                    searchErrorServer: searchUsersErrorMsg,
                    searchErrorNetwork: searchUsersErrorMsg,
                },
                onQueryChange: function (query) {
                    hiddenInput.value = '';
                    searchInput.removeAttribute('aria-invalid');
                    updateSelectedUserSummary('', '');
                    if (memberSearchAbort) {
                        memberSearchAbort.abort();
                    }
                    if (query.length > 0 && query.length < 2) {
                        suggest.replaceChildren();
                        const hint = document.createElement('p');
                        hint.className = 'projectcheck-entity-picker__noresult';
                        hint.setAttribute('role', 'status');
                        hint.textContent = enterMoreCharsMsg;
                        suggest.appendChild(hint);
                        suggest.hidden = false;
                        searchInput.setAttribute('aria-expanded', 'true');
                    }
                    updateAddMemberSubmitState();
                },
                fetchItems: function (query) {
                    if (memberSearchAbort) {
                        memberSearchAbort.abort();
                    }
                    memberSearchAbort = new AbortController();
                    return fetch(searchUsersUrl + '?q=' + encodeURIComponent(query), {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'requesttoken': projectcheckToken,
                            'Accept': 'application/json',
                        },
                        signal: memberSearchAbort.signal,
                    })
                        .then(function (r) {
                            return r.json().then(function (d) {
                                return { ok: r.ok, d: d };
                            });
                        })
                        .then(function (data) {
                            if (data.ok && data.d && data.d.success) {
                                const items = (Array.isArray(data.d.items) ? data.d.items : []).map(function (row) {
                                    return {
                                        id: String(row.uid || ''),
                                        displayName: String(row.label || row.displayName || row.uid || ''),
                                    };
                                }).filter(function (row) {
                                    return row.id !== '';
                                });
                                return { items: items, error: null };
                            }
                            return {
                                items: [],
                                error: 'server',
                            };
                        })
                        .catch(function (err) {
                            if (err && err.name === 'AbortError') {
                                return { items: [], error: null };
                            }
                            return { items: [], error: 'network' };
                        });
                },
                onPick: function (item) {
                    const label = item.displayName && item.displayName !== item.id
                        ? item.displayName
                        : item.id;
                    setSelectedUser(item.id, label);
                },
            });
        }

        function resolveSelectedUserId() {
            const hiddenInput = document.getElementById('teamMemberUserId');
            const selectedUid = hiddenInput ? String(hiddenInput.value || '').trim() : '';
            return selectedUid;
        }

        function showStatusChangeModal() {
            const m = document.getElementById('statusChangeModal');
            const b = document.getElementById('open-status-modal-btn');
            setModalOpen(m, true, b);
            const sel = document.getElementById('newStatus');
            if (sel) {
                sel.focus();
            }
        }

        function closeStatusChangeModal() {
            const m = document.getElementById('statusChangeModal');
            const b = document.getElementById('open-status-modal-btn');
            setModalOpen(m, false, b);
        }

        function showAddTeamMemberModal() {
            const m = document.getElementById('addTeamMemberModal');
            const b = document.getElementById('add-team-member-btn');
            setModalOpen(m, true, b);
            const errorSlot = document.getElementById('add-team-member-error');
            if (errorSlot) {
                errorSlot.textContent = '';
            }
            const u = document.getElementById('teamMemberUserId');
            const s = document.getElementById('teamMemberSearch');
            if (u) {
                u.value = '';
            }
            if (s) {
                s.value = '';
                s.removeAttribute('aria-invalid');
                s.focus();
            }
            updateSelectedUserSummary('', '');
            clearMemberSearchResults();
            const rateInput = document.getElementById('teamMemberHourlyRate');
            if (rateInput instanceof HTMLInputElement) {
                rateInput.value = '';
                rateInput.removeAttribute('aria-invalid');
            }
            updateAddMemberSubmitState();
        }

        function closeAddTeamMemberModal() {
            const m = document.getElementById('addTeamMemberModal');
            const b = document.getElementById('add-team-member-btn');
            if (memberPickerTeardown) {
                memberPickerTeardown();
                memberPickerTeardown = null;
            }
            if (memberSearchAbort) {
                memberSearchAbort.abort();
            }
            const rateInput = document.getElementById('teamMemberHourlyRate');
            if (rateInput instanceof HTMLInputElement) {
                rateInput.value = '';
                rateInput.removeAttribute('aria-invalid');
            }
            setModalOpen(m, false, b);
        }

        function submitStatusChangeFunc() {
            if (statusChangeSubmitting) {
                return;
            }
            const form = document.getElementById('statusChangeForm');
            if (!form) {
                return;
            }
            const statusSelect = document.getElementById('newStatus');
            const statusVal = statusSelect ? String(statusSelect.value || '').trim() : '';
            if (!statusVal) {
                if (typeof window.ProjectCheckNotify !== 'undefined') {
                    window.ProjectCheckNotify.error(selectStatusMsg);
                } else {
                    const region = document.getElementById('pc-alert-region');
                    if (region) {
                        region.textContent = selectStatusMsg;
                    }
                }
                if (statusSelect) {
                    statusSelect.focus();
                }
                return;
            }
            const formData = new FormData(form);
            formData.append('requesttoken', projectcheckToken);

            const submitBtn = document.getElementById('submit-status-change');
            statusChangeSubmitting = true;
            if (submitBtn instanceof HTMLButtonElement) {
                submitBtn.disabled = true;
                submitBtn.setAttribute('aria-busy', 'true');
                submitBtn.textContent = submitBtn.dataset.busyLabel || submitBtn.textContent;
            }

            fetch(changeStatusPostUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'requesttoken': projectcheckToken
                    },
                    credentials: 'same-origin'
                })
                .then((r) => r.text().then((text) => {
                    let d = {};
                    if (text) {
                        try {
                            d = JSON.parse(text);
                        } catch (parseErr) {
                            d = {};
                        }
                    }
                    return { ok: r.ok, d, status: r.status };
                }))
                .then((res) => {
                    if (res.d && res.d.success === true) {
                        window.location.reload();
                        return;
                    }
                    const detail = (res.d && (res.d.error || res.d.message)) ? String(res.d.error || res.d.message) : '';
                    if (typeof window.ProjectCheckNotify !== 'undefined') {
                        window.ProjectCheckNotify.error(detail || errorStatusMsg);
                    } else {
                        const region = document.getElementById('pc-alert-region');
                        if (region) {
                            region.textContent = detail || errorStatusMsg;
                        }
                    }
                })
                .catch(() => {
                    if (typeof window.ProjectCheckNotify !== 'undefined') {
                        window.ProjectCheckNotify.error(errorGeneric);
                    } else {
                        const region = document.getElementById('pc-alert-region');
                        if (region) {
                            region.textContent = errorGeneric;
                        }
                    }
                })
                .finally(() => {
                    statusChangeSubmitting = false;
                    if (submitBtn instanceof HTMLButtonElement) {
                        submitBtn.disabled = false;
                        submitBtn.removeAttribute('aria-busy');
                        submitBtn.textContent = submitBtn.dataset.defaultLabel || submitBtn.textContent;
                    }
                });
        }

        function submitAddTeamMember() {
            if (memberAddSubmitting || memberAddAllSubmitting) {
                return;
            }
            const uid = resolveSelectedUserId();
            if (!uid) {
                showError(chooseUserMsg);
                const searchInput = document.getElementById('teamMemberSearch');
                if (searchInput) {
                    searchInput.setAttribute('aria-invalid', 'true');
                    searchInput.focus();
                }
                return;
            }
            const hiddenInput = document.getElementById('teamMemberUserId');
            if (hiddenInput) {
                hiddenInput.value = uid;
            }
            const errorSlot = document.getElementById('add-team-member-error');
            if (errorSlot) {
                errorSlot.textContent = '';
            }
            const rateInput = document.getElementById('teamMemberHourlyRate');
            if (rateInput instanceof HTMLInputElement && requiresMemberRate) {
                const rateVal = parseFloat(rateInput.value);
                if (!Number.isFinite(rateVal) || rateVal <= 0) {
                    const msg = memberRateRequiredMsg || addMemberError;
                    const errorSlot = document.getElementById('add-team-member-error');
                    if (errorSlot) {
                        errorSlot.textContent = msg;
                    }
                    rateInput.setAttribute('aria-invalid', 'true');
                    rateInput.focus();
                    if (typeof window.ProjectCheckNotify !== 'undefined') {
                        window.ProjectCheckNotify.error(msg);
                    }
                    return;
                }
                rateInput.removeAttribute('aria-invalid');
            }
            const formData = new FormData();
            formData.append('user_id', uid);
            if (rateInput && rateInput.value) {
                formData.append('hourly_rate', rateInput.value);
            }
            formData.append('requesttoken', projectcheckToken);
            const submitButton = document.getElementById('submit-add-team-member');
            memberAddSubmitting = true;
            if (submitButton instanceof HTMLButtonElement) {
                submitButton.disabled = true;
                submitButton.setAttribute('aria-busy', 'true');
                submitButton.textContent = submitButton.dataset.busyLabel || submitButton.textContent;
            }

            fetch(addTeamUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'requesttoken': projectcheckToken
                    },
                    credentials: 'same-origin'
                })
                .then(r => r.json().then(d => ({ ok: r.ok, d })).catch(() => ({ ok: r.ok, d: {} })))
                .then(res => {
                    if (res.d && res.d.success) {
                        window.location.reload();
                        return;
                    } else {
                        const err = (res.d && (res.d.error)) ? res.d.error : addMemberError;
                        showError(err);
                    }
                })
                .catch(() => showError(errorGeneric))
                .finally(() => {
                    memberAddSubmitting = false;
                    if (submitButton instanceof HTMLButtonElement) {
                        submitButton.textContent = submitButton.dataset.defaultLabel || submitButton.textContent;
                        submitButton.removeAttribute('aria-busy');
                    }
                    updateAddMemberSubmitState();
                });
        }

        function submitAddAllTeamMembers() {
            if (memberAddSubmitting || memberAddAllSubmitting) {
                return;
            }

            memberAddAllSubmitting = true;
            updateAddMemberSubmitState();

            const addAllButton = document.getElementById('submit-add-all-team-members');
            if (addAllButton instanceof HTMLButtonElement) {
                addAllButton.setAttribute('aria-busy', 'true');
                addAllButton.textContent = addAllButton.dataset.busyLabel || addAllButton.textContent;
            }

            // Requested UX: close modal immediately, run bulk action, then refresh.
            closeAddTeamMemberModal();

            fetch(addAllTeamUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'requesttoken': projectcheckToken
                    },
                    credentials: 'same-origin'
                })
                .then(r => r.json().then(d => ({ ok: r.ok, d })).catch(() => ({ ok: r.ok, d: {} })))
                .then(res => {
                    if (res.d && res.d.success) {
                        const addedCount = Number((res.d && res.d.added_count) || 0);
                        const reloadUrl = new URL(window.location.href);
                        reloadUrl.searchParams.set('bulk_add_success', '1');
                        reloadUrl.searchParams.set('added_count', String(Number.isFinite(addedCount) ? Math.max(0, Math.trunc(addedCount)) : 0));
                        window.location.href = reloadUrl.toString();
                        return;
                    }
                    const err = (res.d && (res.d.error || res.d.message)) ? (res.d.error || res.d.message) : addAllMembersError;
                    showError(err);
                })
                .catch(() => showError(errorGeneric))
                .finally(() => {
                    memberAddAllSubmitting = false;
                    if (addAllButton instanceof HTMLButtonElement) {
                        addAllButton.removeAttribute('aria-busy');
                        addAllButton.textContent = addAllButton.dataset.defaultLabel || addAllButton.textContent;
                    }
                    updateAddMemberSubmitState();
                });
        }

        function handleAddMemberFormSubmit(e) {
            e.preventDefault();
            submitAddTeamMember();
        }

        function closeOnBackdropClick(e, closeHandler, modalId) {
            const modal = document.getElementById(modalId);
            if (modal && e.target === modal) {
                closeHandler();
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-width]').forEach(function(el) {
            el.style.width = el.getAttribute('data-width') + '%';
        });

        const createdBanner = document.querySelector('.pc-created-banner');
        const teamSection = document.getElementById('team-section');
        if (createdBanner && teamSection) {
            const goTeamLink = createdBanner.querySelector('a[href="#team-section"]');
            if (goTeamLink) {
                goTeamLink.addEventListener('click', function (event) {
                    event.preventDefault();
                    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    teamSection.scrollIntoView({
                        behavior: reducedMotion ? 'auto' : 'smooth',
                        block: 'start',
                    });
                    if (!teamSection.hasAttribute('tabindex')) {
                        teamSection.setAttribute('tabindex', '-1');
                    }
                    teamSection.focus({ preventScroll: true });
                });
            }
        }

        const addTeamMemberBtn = document.getElementById('add-team-member-btn');
        if (addTeamMemberBtn) {
            addTeamMemberBtn.addEventListener('click', showAddTeamMemberModal);
        }
        // Empty-state CTA(s) that should open the same dialog (e.g. the
        // in-context "Add first team member" button). We bind via event
        // delegation so any future placement of the action still works.
        document.addEventListener('click', function (event) {
            const target = event.target instanceof Element ? event.target.closest('[data-action="open-add-team-member"]') : null;
            if (!target) {
                return;
            }
            event.preventDefault();
            showAddTeamMemberModal();
        });
        const openStatusBtn = document.getElementById('open-status-modal-btn');
        if (openStatusBtn) {
            openStatusBtn.addEventListener('click', showStatusChangeModal);
        }
        const closeAddMember = document.getElementById('close-add-member-modal');
        if (closeAddMember) {
            closeAddMember.addEventListener('click', closeAddTeamMemberModal);
        }
        const cancelAddMember = document.getElementById('cancel-add-member');
        if (cancelAddMember) {
            cancelAddMember.addEventListener('click', closeAddTeamMemberModal);
        }
        const subAdd = document.getElementById('submit-add-team-member');
        if (subAdd) {
            subAdd.addEventListener('click', submitAddTeamMember);
        }
        const subAddAll = document.getElementById('submit-add-all-team-members');
        if (subAddAll) {
            subAddAll.addEventListener('click', submitAddAllTeamMembers);
        }
        const addMemberForm = document.getElementById('addTeamMemberForm');
        if (addMemberForm) {
            addMemberForm.addEventListener('submit', handleAddMemberFormSubmit);
        }
        const clearSelectedUserBtn = document.getElementById('teamMemberSelectedClear');
        if (clearSelectedUserBtn) {
            clearSelectedUserBtn.addEventListener('click', function() {
                const hiddenInput = document.getElementById('teamMemberUserId');
                const searchInput = document.getElementById('teamMemberSearch');
                if (hiddenInput) {
                    hiddenInput.value = '';
                }
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.removeAttribute('aria-invalid');
                    searchInput.focus();
                }
                updateSelectedUserSummary('', '');
                clearMemberSearchResults();
                updateAddMemberSubmitState();
            });
        }
        bindMemberSearch();
        const memberRateInput = document.getElementById('teamMemberHourlyRate');
        if (memberRateInput instanceof HTMLInputElement) {
            memberRateInput.addEventListener('input', function () {
                memberRateInput.removeAttribute('aria-invalid');
                const errorSlot = document.getElementById('add-team-member-error');
                if (errorSlot && errorSlot.textContent !== '') {
                    errorSlot.textContent = '';
                }
                updateAddMemberSubmitState();
            });
            memberRateInput.addEventListener('change', updateAddMemberSubmitState);
        }
        const closeStatusModal = document.getElementById('close-status-modal');
        if (closeStatusModal) {
            closeStatusModal.addEventListener('click', closeStatusChangeModal);
        }
        const cancelStatusChange = document.getElementById('cancel-status-change');
        if (cancelStatusChange) {
            cancelStatusChange.addEventListener('click', closeStatusChangeModal);
        }
        const submitStatusChange = document.getElementById('submit-status-change');
        if (submitStatusChange) {
            submitStatusChange.addEventListener('click', submitStatusChangeFunc);
        }
        const teamModal = document.getElementById('addTeamMemberModal');
        if (teamModal) {
            teamModal.addEventListener('click', e => closeOnBackdropClick(e, closeAddTeamMemberModal, 'addTeamMemberModal'));
        }
        const statusModal = document.getElementById('statusChangeModal');
        if (statusModal) {
            statusModal.addEventListener('click', e => closeOnBackdropClick(e, closeStatusChangeModal, 'statusChangeModal'));
        }
        document.addEventListener('keydown', function(e) {
            trapFocusInModal(e);
            if (e.key === 'Escape') {
                const modal = getOpenProjectcheckModal();
                if (!modal) {
                    return;
                }
                if (modal.id === 'addTeamMemberModal') {
                    closeAddTeamMemberModal();
                    return;
                }
                if (modal.id === 'statusChangeModal') {
                    closeStatusChangeModal();
                }
            }
        });
    });
    
// Member removal functionality
    document.addEventListener('click', function(e) {
        const button = e.target.closest('.remove-member-btn');
        if (!button) {
            return;
        }
        const memberId = button.getAttribute('data-member-id');
        const userId = button.getAttribute('data-user-id');
        const deleteUrl = button.getAttribute('data-delete-url');
        const impactUrl = button.getAttribute('data-impact-url');
        const memberName = button.getAttribute('data-member-name');
        if (!memberId || !userId || !deleteUrl) {
            return;
        }
        showMemberRemovalModal(button, memberId, userId, memberName || '', deleteUrl, impactUrl || '');
    });

    function notify(message, type) {
        if (typeof window.ProjectCheckNotify !== 'undefined') {
            window.ProjectCheckNotify.show(message, type);
            return;
        }
        if (typeof OC !== 'undefined' && OC.Notification) {
            if (type === 'error') {
                OC.Notification.showTemporary(message, { type: 'error' });
                return;
            }
            OC.Notification.showTemporary(message);
            return;
        }
        const region = document.getElementById('pc-alert-region');
        if (region) {
            region.textContent = message;
        }
    }

    function removeMemberRow(button) {
        const row = button.closest('.team-member-item');
        if (row) {
            row.remove();
        }
    }

    function showMemberRemovalModal(button, memberId, userId, memberName, deleteUrl, impactUrl) {
        if (typeof window.projectcheckDeletionModal === 'undefined') {
            notify(cfg.messages.removeError || t('projectcheck', 'Could not open the confirmation dialog. Reload the page and try again.'), 'error');
            return;
        }

        window.projectcheckDeletionModal.show({
            entityType: 'member',
            entityId: memberId,
            entityName: memberName,
            deleteUrl: deleteUrl,
            impactUrl: impactUrl,
            onSuccess: function() {
                removeMemberRow(button);
                notify(cfg.messages.removeSuccess);
            },
            onCancel: function() {}
        });
    }


})();
