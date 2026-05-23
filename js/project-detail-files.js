/**
 * Project detail: file upload dropzone and delete handlers.
 */
(function () {
	'use strict';

	const cfg = window.projectDetailFilesConfig || {};

	function fileNotify(message) {
		if (typeof window.ProjectCheckNotify !== 'undefined') {
			window.ProjectCheckNotify.error(message);
			return;
		}
		const region = document.getElementById('pc-alert-region');
		if (region) {
			region.textContent = message;
		}
	}

	document.addEventListener('DOMContentLoaded', () => {
		const filesList = document.querySelector('.project-files-list');
		const requestTokenInput = document.querySelector('input[name="requesttoken"]');
		const requestToken = requestTokenInput ? requestTokenInput.value : (typeof OC !== 'undefined' ? OC.requestToken : '');
		const fileInput = document.getElementById('project_files_upload');
		const uploadForm = document.getElementById('project-file-upload-form');
		const dropzone = document.getElementById('project-files-dropzone');
		const maxFilesPerUpload = 20;
		const maxFileSizeBytes = 52428800;
		const msg = cfg.messages || {};

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
							const row = button.closest('.project-file-item, li, tr');
							if (row) {
								row.remove();
							}
						},
						onCancel: function () {}
					});
					return;
				}
				const url = new URL(deleteUrl, window.location.origin);
				if (requestToken) {
					url.searchParams.set('requesttoken', requestToken);
				}
				try {
					const response = await fetch(url.toString(), {
						method: 'DELETE',
						headers: {
							requesttoken: requestToken,
							Accept: 'application/json',
							'X-Requested-With': 'XMLHttpRequest',
						},
						credentials: 'same-origin',
					});

					if (!response.ok) {
						const data = await response.json().catch(() => ({}));
						throw new Error(data.error || msg.deleteFailed || 'Failed to delete');
					}

					const row = button.closest('.project-file-row');
					if (row) {
						row.remove();
					}
				} catch (error) {
					console.error(error);
					fileNotify(error?.message || msg.deleteFailed || 'Could not delete the file.');
				}
			});
		}

		async function submitFiles(files) {
			if (!uploadForm || !files || files.length === 0) {
				return;
			}

			if (files.length > maxFilesPerUpload) {
				fileNotify(msg.tooManyFiles || 'You can upload up to 20 files at once.');
				return;
			}
			const hasTooLargeFile = Array.from(files).some((file) => file.size > maxFileSizeBytes);
			if (hasTooLargeFile) {
				fileNotify(msg.fileTooLarge || 'One or more files exceed the 50 MB limit.');
				return;
			}

			const formData = new FormData();
			Array.from(files).forEach((file) => formData.append('project_files[]', file, file.name));
			if (requestToken) {
				formData.append('requesttoken', requestToken);
			}

			if (dropzone) {
				dropzone.classList.add('is-uploading');
				dropzone.setAttribute('aria-busy', 'true');
				dropzone.dataset.originalText = dropzone.textContent || '';
				dropzone.textContent = msg.uploading || 'Uploading files…';
			}

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
					throw new Error(payload.error || msg.uploadFailed || 'Upload failed.');
				}

				window.location.reload();
			} catch (error) {
				console.error(error);
				fileNotify(error?.message || msg.uploadFailed || 'Upload failed.');
			} finally {
				if (dropzone) {
					dropzone.classList.remove('is-uploading');
					dropzone.removeAttribute('aria-busy');
					if (dropzone.dataset.originalText) {
						dropzone.textContent = dropzone.dataset.originalText;
					}
				}
				if (fileInput) {
					fileInput.value = '';
				}
			}
		}

		if (fileInput && uploadForm) {
			fileInput.addEventListener('change', () => {
				if (fileInput.files && fileInput.files.length > 0) {
					submitFiles(fileInput.files);
				}
			});
		}

		if (dropzone && fileInput) {
			const activateInput = () => fileInput.click();
			dropzone.addEventListener('click', activateInput);
			dropzone.addEventListener('keydown', (event) => {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					activateInput();
				}
			});

			['dragenter', 'dragover'].forEach((name) => {
				dropzone.addEventListener(name, (event) => {
					event.preventDefault();
					event.stopPropagation();
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
				const files = event.dataTransfer?.files;
				if (files && files.length > 0) {
					submitFiles(files);
				}
			});
		}
	});
})();
