/**
 * Project detail: file upload (dropzone + native picker) and delete.
 *
 * Hardening goals (audit reference, "files-ux-2026"):
 *  - One coherent upload surface: a single dropzone plus the hidden file
 *    input. The header "Add files" label and any in-list dropzone all
 *    trigger the same input, so we have one source of truth for state.
 *  - Concurrency safe: only one upload is in flight at a time; all
 *    triggers (label, dropzone click, drag-drop) are disabled while
 *    busy, so a double-click cannot enqueue duplicate uploads.
 *  - Client-side validation mirrors the server limits without leaking
 *    secrets — the limits come from window.projectDetailFilesConfig.
 *    The server remains authoritative.
 *  - Document-wide drag highlight gives a visible target anywhere on
 *    the page, but drops only count when they land on the dropzone.
 *  - After a successful upload we redirect to `?uploaded=N` so the
 *    page reload renders a confirmation banner; we strip the param
 *    from the URL right after to keep the address bar clean and to
 *    avoid double-rendering on later reloads.
 *  - All status messages route through ProjectCheckNotify (or the
 *    aria-live #pc-alert-region fallback) so screen readers are kept
 *    in the loop.
 */
(function () {
	'use strict';

	const cfg = window.projectDetailFilesConfig || {};
	const msg = cfg.messages || {};
	const limits = cfg.limits || {};
	const MAX_FILES_PER_UPLOAD = Number.isFinite(Number(limits.maxFiles)) && Number(limits.maxFiles) > 0
		? Number(limits.maxFiles)
		: 20;
	const MAX_FILE_SIZE_BYTES = Number.isFinite(Number(limits.maxBytes)) && Number(limits.maxBytes) > 0
		? Number(limits.maxBytes)
		: 52428800;

	function fileNotify(message, type) {
		if (!message) {
			return;
		}
		if (typeof window.ProjectCheckNotify !== 'undefined') {
			if (type === 'error') {
				window.ProjectCheckNotify.error(message);
			} else if (typeof window.ProjectCheckNotify[type] === 'function') {
				window.ProjectCheckNotify[type](message);
			} else {
				window.ProjectCheckNotify.show(message, type || 'info');
			}
			return;
		}
		if (typeof OC !== 'undefined' && OC.Notification) {
			OC.Notification.showTemporary(message);
		}
		const region = document.getElementById('pc-alert-region');
		if (region) {
			region.textContent = message;
		}
	}

	/**
	 * Show the "uploaded N files" success banner immediately on a fresh
	 * page load that follows a successful upload, then strip the URL
	 * parameter so a manual refresh does not replay the banner.
	 */
	function consumeUploadedParam() {
		if (!window.history || typeof window.history.replaceState !== 'function') {
			return;
		}
		const url = new URL(window.location.href);
		if (!url.searchParams.has('uploaded')) {
			return;
		}
		url.searchParams.delete('uploaded');
		// Preserve fragment & path; just drop the marker.
		window.history.replaceState({}, '', url.toString());
	}

	document.addEventListener('DOMContentLoaded', () => {
		consumeUploadedParam();

		const filesList = document.querySelector('.project-files-list');
		const requestTokenInput = document.querySelector('input[name="requesttoken"]');
		const requestToken = requestTokenInput
			? requestTokenInput.value
			: (typeof OC !== 'undefined' ? OC.requestToken : '');
		const fileInput = document.getElementById('project_files_upload');
		const uploadForm = document.getElementById('project-file-upload-form');
		const dropzone = document.getElementById('project-files-dropzone');
		const filesSection = document.getElementById('files-section');
		const headerAddButton = document.querySelector('label.pc-section__primary-action[for="project_files_upload"]');

		let isUploading = false;
		let documentDragDepth = 0;

		// ===== Delete handlers =====
		if (filesList) {
			filesList.addEventListener('click', async (event) => {
				const button = event.target.closest('.delete-file-btn');
				if (!button) {
					return;
				}

				const fileName = button.dataset.fileName || '';
				const confirmPrefix = msg.deleteConfirm || t('projectcheck', 'Delete this file?');
				const confirmMessage = fileName
					? confirmPrefix + ' ' + fileName
					: confirmPrefix;
				const deleteUrl = button.dataset.deleteUrl;

				if (!deleteUrl) {
					return;
				}

				if (typeof window.projectcheckDeletionModal !== 'undefined') {
					window.projectcheckDeletionModal.show({
						entityType: 'file',
						entityId: deleteUrl,
						entityName: fileName || t('projectcheck', 'File'),
						deleteUrl: deleteUrl,
						simpleConfirm: true,
						confirmMessage: confirmMessage,
						onSuccess: function () {
							const row = button.closest('.project-file-row');
							if (row) {
								row.remove();
							}
						},
						onCancel: function () {}
					});
					return;
				}
				fileNotify(
					msg.deleteFailed || t('projectcheck', 'Could not open the confirmation dialog. Reload the page and try again.'),
					'error'
				);
			});
		}

		// ===== Upload triggers + state =====
		function setUploading(state) {
			isUploading = !!state;
			if (dropzone) {
				dropzone.classList.toggle('is-uploading', isUploading);
				if (isUploading) {
					dropzone.setAttribute('aria-busy', 'true');
					dropzone.setAttribute('aria-disabled', 'true');
					if (!dropzone.dataset.originalText) {
						dropzone.dataset.originalText = dropzone.textContent || '';
					}
				} else {
					dropzone.removeAttribute('aria-busy');
					dropzone.removeAttribute('aria-disabled');
				}
			}
			if (fileInput) {
				fileInput.disabled = isUploading;
			}
			if (headerAddButton) {
				headerAddButton.classList.toggle('is-disabled', isUploading);
				if (isUploading) {
					headerAddButton.setAttribute('aria-disabled', 'true');
				} else {
					headerAddButton.removeAttribute('aria-disabled');
				}
			}
		}

		function validateBatch(files) {
			if (!files || files.length === 0) {
				return msg.noFiles || 'No files selected.';
			}
			if (files.length > MAX_FILES_PER_UPLOAD) {
				return msg.tooManyFiles || ('You can upload up to ' + MAX_FILES_PER_UPLOAD + ' files at once.');
			}
			for (let i = 0; i < files.length; i++) {
				const f = files[i];
				if (!f) {
					continue;
				}
				if (f.size === 0) {
					return (msg.uploadFailed || 'Upload failed.');
				}
				if (f.size > MAX_FILE_SIZE_BYTES) {
					return msg.fileTooLarge || 'One or more files are too large.';
				}
			}
			return null;
		}

		async function submitFiles(files) {
			if (!uploadForm || !fileInput) {
				return;
			}
			if (isUploading) {
				fileNotify(msg.inProgress || 'An upload is already in progress.', 'info');
				return;
			}
			const error = validateBatch(files);
			if (error) {
				fileNotify(error, 'error');
				if (fileInput) {
					fileInput.value = '';
				}
				return;
			}

			const formData = new FormData();
			Array.from(files).forEach((file) => formData.append('project_files[]', file, file.name));
			if (requestToken) {
				formData.append('requesttoken', requestToken);
			}

			setUploading(true);
			fileNotify(msg.uploadStart || msg.uploading || 'Uploading…', 'info');

			try {
				const response = await fetch(uploadForm.action, {
					method: 'POST',
					body: formData,
					headers: {
						'X-Requested-With': 'XMLHttpRequest',
						Accept: 'application/json',
						requesttoken: requestToken,
					},
					credentials: 'same-origin',
				});

				const payload = await response.json().catch(() => ({}));
				if (!response.ok || payload.success !== true) {
					throw new Error((payload && payload.error) || msg.uploadFailed || 'Upload failed.');
				}

				const uploadedCount = Math.max(1, files.length);
				const reloadUrl = new URL(window.location.href);
				reloadUrl.searchParams.set('uploaded', String(uploadedCount));
				// Drop any prior marker(s) so the banner shows the new total only.
				reloadUrl.hash = '#files-section';
				window.location.href = reloadUrl.toString();
			} catch (err) {
				console.error(err);
				fileNotify((err && err.message) || msg.uploadFailed || 'Upload failed.', 'error');
				setUploading(false);
				if (fileInput) {
					fileInput.value = '';
				}
			}
		}

		// Native picker -> submit
		if (fileInput && uploadForm) {
			fileInput.addEventListener('change', () => {
				if (fileInput.files && fileInput.files.length > 0) {
					submitFiles(fileInput.files);
				}
			});
		}

		// Dropzone keyboard / pointer activation + drag-and-drop
		if (dropzone && fileInput) {
			const activateInput = () => {
				if (isUploading) {
					return;
				}
				fileInput.click();
			};
			dropzone.addEventListener('click', (e) => {
				// Avoid double-firing when an inner label propagates a click.
				e.preventDefault();
				activateInput();
			});
			dropzone.addEventListener('keydown', (event) => {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					activateInput();
				}
			});

			['dragenter', 'dragover'].forEach((name) => {
				dropzone.addEventListener(name, (event) => {
					if (isUploading) {
						return;
					}
					event.preventDefault();
					event.stopPropagation();
					if (event.dataTransfer) {
						event.dataTransfer.dropEffect = 'copy';
					}
					dropzone.classList.add('is-dragover');
				});
			});

			['dragleave', 'dragend'].forEach((name) => {
				dropzone.addEventListener(name, (event) => {
					event.preventDefault();
					event.stopPropagation();
					if (!dropzone.contains(event.relatedTarget)) {
						dropzone.classList.remove('is-dragover');
					}
				});
			});

			dropzone.addEventListener('drop', (event) => {
				event.preventDefault();
				event.stopPropagation();
				dropzone.classList.remove('is-dragover');
				if (isUploading) {
					return;
				}
				const files = event.dataTransfer?.files;
				if (files && files.length > 0) {
					submitFiles(files);
				}
			});
		}

		// Document-wide drag visual cue, so users find the target anywhere on the page.
		if (filesSection && dropzone) {
			const documentHasFiles = (event) => {
				if (!event || !event.dataTransfer) {
					return false;
				}
				const types = event.dataTransfer.types;
				if (!types) {
					return false;
				}
				for (let i = 0; i < types.length; i++) {
					if (types[i] === 'Files' || types[i] === 'application/x-moz-file') {
						return true;
					}
				}
				return false;
			};

			window.addEventListener('dragenter', (event) => {
				if (isUploading || !documentHasFiles(event)) {
					return;
				}
				documentDragDepth += 1;
				filesSection.classList.add('is-document-dragover');
			});

			window.addEventListener('dragleave', () => {
				documentDragDepth = Math.max(0, documentDragDepth - 1);
				if (documentDragDepth === 0) {
					filesSection.classList.remove('is-document-dragover');
				}
			});

			window.addEventListener('drop', () => {
				documentDragDepth = 0;
				filesSection.classList.remove('is-document-dragover');
			});

			// Block default "open file in browser" behaviour when the drop
			// misses the dropzone, so a stray release does not navigate away
			// from the project page.
			window.addEventListener('dragover', (event) => {
				if (!documentHasFiles(event)) {
					return;
				}
				event.preventDefault();
			});
			window.addEventListener('drop', (event) => {
				if (!documentHasFiles(event)) {
					return;
				}
				const targetIsInsideDropzone = dropzone.contains(event.target);
				if (!targetIsInsideDropzone) {
					event.preventDefault();
				}
			});
		}

		// Warn the user before accidental navigation while an upload is in flight.
		window.addEventListener('beforeunload', (event) => {
			if (!isUploading) {
				return undefined;
			}
			event.preventDefault();
			event.returnValue = '';
			return '';
		});
	});
})();
