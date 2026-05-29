/**
 * YouPreserver — Admin JavaScript
 */
(function () {
	'use strict';

	function formatProgress(text, current, total) {
		return text.replace('%1$d', String(current)).replace('%2$d', String(total));
	}

	function formatBytes(bytes) {
		if (!bytes || bytes <= 0) {
			return '0 B';
		}

		var units = ['B', 'KB', 'MB', 'GB'];
		var size = bytes;
		var unitIndex = 0;

		while (size >= 1024 && unitIndex < units.length - 1) {
			size /= 1024;
			unitIndex += 1;
		}

		return (unitIndex === 0 ? size.toFixed(0) : size.toFixed(1)) + ' ' + units[unitIndex];
	}

	function formatUploadStatus(i18n, loaded, total) {
		if (total > 0) {
			var percent = Math.round((loaded / total) * 100);
			return (i18n.importUploadProgress || 'Uploading ZIP… %1$s of %2$s (%3$d%%)')
				.replace('%1$s', formatBytes(loaded))
				.replace('%2$s', formatBytes(total))
				.replace('%3$d', String(percent));
		}

		return (i18n.importUploadSent || 'Uploading ZIP… %1$s sent')
			.replace('%1$s', formatBytes(loaded));
	}

	function overallPercent(phase, phasePercent) {
		var uploadWeight = 15;
		var extractWeight = 10;

		if (phase === 'upload') {
			return Math.round((phasePercent / 100) * uploadWeight);
		}
		if (phase === 'extract') {
			return uploadWeight + Math.round((phasePercent / 100) * extractWeight);
		}

		return uploadWeight + extractWeight + Math.round((phasePercent / 100) * (100 - uploadWeight - extractWeight));
	}

	function appendLog(logEl, entry) {
		if (!logEl || !entry || !entry.message) {
			return;
		}

		var line = document.createElement('div');
		line.className = 'ipa-import-log-line ipa-import-log-' + (entry.type || 'info');
		line.textContent = entry.message;
		logEl.appendChild(line);
		logEl.scrollTop = logEl.scrollHeight;
	}

	function setProgress(progressEl, barEl, statusEl, percent, statusText) {
		if (progressEl) {
			progressEl.hidden = false;
		}
		if (barEl) {
			barEl.classList.remove('is-indeterminate');
			barEl.style.width = Math.max(0, Math.min(100, percent || 0)) + '%';
			barEl.setAttribute('aria-valuenow', String(Math.max(0, Math.min(100, percent || 0))));
		}
		if (statusEl && statusText) {
			statusEl.textContent = statusText;
		}
	}

	function setIndeterminateProgress(progressEl, barEl, statusEl, statusText) {
		if (progressEl) {
			progressEl.hidden = false;
		}
		if (barEl) {
			barEl.classList.add('is-indeterminate');
			barEl.style.width = '100%';
			barEl.removeAttribute('aria-valuenow');
		}
		if (statusEl && statusText) {
			statusEl.textContent = statusText;
		}
	}

	function postFormDataWithProgress(action, formData, onProgress, i18n) {
		formData.append('action', action);
		formData.append('nonce', ipaAdmin.nonce);

		return new Promise(function (resolve, reject) {
			var xhr = new XMLHttpRequest();
			xhr.open('POST', ipaAdmin.ajaxUrl, true);
			xhr.withCredentials = true;

			xhr.upload.addEventListener('progress', function (event) {
				if (typeof onProgress === 'function') {
					onProgress(event.loaded || 0, event.lengthComputable ? event.total : 0);
				}
			});

			xhr.addEventListener('load', function () {
				var payload;

				try {
					payload = JSON.parse(xhr.responseText);
				} catch (error) {
					reject(new Error(i18n.importServerError || 'Server returned an invalid response.'));
					return;
				}

				if (xhr.status >= 200 && xhr.status < 300) {
					resolve(payload);
					return;
				}

				reject(new Error((payload && payload.data && payload.data.message) || (i18n.importFailed || 'Import failed.')));
			});

			xhr.addEventListener('error', function () {
				reject(new Error(i18n.importNetworkError || 'Network error during upload.'));
			});

			xhr.addEventListener('abort', function () {
				reject(new Error(i18n.importCancelled || 'Import cancelled.'));
			});

			xhr.send(formData);
		});
	}

	function postJobRequest(action, jobId) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', ipaAdmin.nonce);
		body.append('job_id', jobId);

		return fetch(ipaAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		}).then(function (response) {
			return response.text().then(function (text) {
				try {
					return JSON.parse(text);
				} catch (error) {
					throw new Error('Invalid server response.');
				}
			});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.ipa-toggle-token').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var wrap = btn.closest('.ipa-password-field');
				if (!wrap) return;
				var input = wrap.querySelector('input');
				if (!input) return;

				if (input.type === 'password') {
					input.type = 'text';
					btn.textContent = btn.dataset.hideLabel || 'Hide';
				} else {
					input.type = 'password';
					btn.textContent = btn.dataset.showLabel || 'Show';
				}
			});
		});

		document.querySelectorAll('.ipa-copy-button').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var targetId = btn.getAttribute('data-copy-target');
				var target = targetId ? document.getElementById(targetId) : null;
				if (!target) return;

				var text = target.textContent || '';
				var copyLabel = btn.dataset.copyLabel || 'Copy';
				var copiedLabel = btn.dataset.copiedLabel || 'Copied';

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(function () {
						btn.textContent = copiedLabel;
						setTimeout(function () {
							btn.textContent = copyLabel;
						}, 1500);
					});
				}
			});
		});

		var connectBtn = document.querySelector('.ipa-btn-connect');
		var connectForm = document.getElementById('ipa-connection-form');

		if (connectBtn && connectForm) {
			connectForm.addEventListener('submit', function (event) {
				var submitter = event.submitter;
				if (!submitter || submitter.value !== 'connect') {
					return;
				}

				connectBtn.disabled = true;
				connectBtn.textContent = connectBtn.dataset.loadingText || 'Connecting…';
			});
		}

		var importForm = document.getElementById('ipa-highlights-import-form');
		if (importForm && typeof ipaAdmin !== 'undefined') {
		var fileInput = document.getElementById('ipa_highlights_zip');
		var replaceInput = document.getElementById('ipa_replace_highlights');
		var importBtn = document.getElementById('ipa-highlights-import-btn');
		var cancelBtn = document.getElementById('ipa-highlights-import-cancel');
		var progressWrap = document.getElementById('ipa-highlights-import-progress');
		var progressBar = document.getElementById('ipa-import-progress-bar');
		var progressStatus = document.getElementById('ipa-import-progress-status');
		var logEl = document.getElementById('ipa-import-log');
		var i18n = ipaAdmin.i18n || {};
		var activeJobId = '';
		var importRunning = false;
		var cancelRequested = false;

		function setImportUiState(running) {
			importRunning = running;
			if (importBtn) {
				importBtn.disabled = running;
				importBtn.textContent = running
					? (i18n.importWorking || 'Importing…')
					: (importBtn.dataset.defaultLabel || importBtn.textContent);
			}
			if (fileInput) {
				fileInput.disabled = running;
			}
			if (replaceInput) {
				replaceInput.disabled = running;
			}
			if (cancelBtn) {
				cancelBtn.hidden = !running;
			}
		}

		if (importBtn && !importBtn.dataset.defaultLabel) {
			importBtn.dataset.defaultLabel = importBtn.textContent;
		}

		function resetProgressPanel() {
			if (logEl) {
				logEl.innerHTML = '';
			}
			setProgress(progressWrap, progressBar, progressStatus, 0, i18n.importPreparing || 'Preparing import…');
		}

		function handleStepPayload(data, total) {
			var stepTotal = typeof data.total === 'number' ? data.total : total;
			var current = Math.min(data.index || 0, stepTotal || 0);
			var phase = data.phase || 'import';
			var phasePercent = typeof data.percent === 'number' ? data.percent : 0;
			var statusText;

			if (Array.isArray(data.logs)) {
				data.logs.forEach(function (entry) {
					appendLog(logEl, entry);
				});
			}

			if (data.done) {
				setProgress(
					progressWrap,
					progressBar,
					progressStatus,
					100,
					data.message || (i18n.importComplete || 'Import complete.')
				);
				appendLog(logEl, { type: 'success', message: data.message || (i18n.importComplete || 'Import complete.') });
				setImportUiState(false);
				activeJobId = '';
				return true;
			}

			if (phase === 'extract') {
				statusText = i18n.importExtracting || 'Extracting and validating ZIP…';
			} else if (stepTotal > 0) {
				statusText = formatProgress(i18n.importProgress || 'Importing highlight %1$d of %2$d…', current, stepTotal);
			} else {
				statusText = i18n.importStarting || 'Starting import…';
			}

			setProgress(
				progressWrap,
				progressBar,
				progressStatus,
				overallPercent(phase, phasePercent),
				statusText
			);

			return false;
		}

		function runImportSteps(jobId, total) {
			return new Promise(function (resolve, reject) {
				function nextStep() {
					if (cancelRequested) {
						resolve();
						return;
					}

					postJobRequest('ipa_highlights_import_step', jobId)
						.then(function (payload) {
							if (cancelRequested) {
								resolve();
								return;
							}

							if (!payload || !payload.success) {
								throw new Error((payload && payload.data && payload.data.message) || (i18n.importFailed || 'Import failed.'));
							}

							var done = handleStepPayload(payload.data || {}, total);
							if (done) {
								resolve();
								return;
							}

							nextStep();
						})
						.catch(reject);
				}

				setIndeterminateProgress(
					progressWrap,
					progressBar,
					progressStatus,
					i18n.importExtracting || 'Extracting and validating ZIP…'
				);
				nextStep();
			});
		}

		importForm.addEventListener('submit', function (event) {
			event.preventDefault();

			if (importRunning) {
				return;
			}

			if (!fileInput || !fileInput.files || !fileInput.files.length) {
				window.alert(i18n.importNoFile || 'Please choose a highlights ZIP file first.');
				return;
			}

			cancelRequested = false;
			resetProgressPanel();
			setImportUiState(true);

			var file = fileInput.files[0];
			var formData = new FormData();
			formData.append('ipa_highlights_zip', file);
			if (replaceInput && replaceInput.checked) {
				formData.append('ipa_replace_highlights', '1');
			}

			appendLog(logEl, {
				type: 'info',
				message: (file.name || 'highlights.zip') + ' (' + formatBytes(file.size) + ')'
			});

			postFormDataWithProgress(
				'ipa_highlights_import_start',
				formData,
				function (loaded, total) {
					if (total > 0) {
						setProgress(
							progressWrap,
							progressBar,
							progressStatus,
							overallPercent('upload', Math.round((loaded / total) * 100)),
							formatUploadStatus(i18n, loaded, total)
						);
						return;
					}

					setIndeterminateProgress(
						progressWrap,
						progressBar,
						progressStatus,
						formatUploadStatus(i18n, loaded, total)
					);
				},
				i18n
			)
				.then(function (payload) {
					if (!payload || !payload.success) {
						var errorMessage = (payload && payload.data && payload.data.message) || (i18n.importFailed || 'Import failed.');
						throw new Error(errorMessage);
					}

					var data = payload.data || {};
					activeJobId = data.job_id || '';
					var total = data.total || 0;

					appendLog(logEl, { type: 'info', message: data.message || (i18n.importStarting || 'Starting import…') });
					setProgress(
						progressWrap,
						progressBar,
						progressStatus,
						overallPercent('upload', 100),
						i18n.importExtracting || 'Extracting and validating ZIP…'
					);

					return runImportSteps(activeJobId, total);
				})
				.catch(function (error) {
					appendLog(logEl, {
						type: 'error',
						message: error && error.message ? error.message : (i18n.importFailed || 'Import failed.')
					});
					setProgress(
						progressWrap,
						progressBar,
						progressStatus,
						0,
						error && error.message ? error.message : (i18n.importFailed || 'Import failed.')
					);
					setImportUiState(false);
					activeJobId = '';
				});
		});

		if (cancelBtn) {
			cancelBtn.addEventListener('click', function () {
				if (!importRunning || !activeJobId) {
					return;
				}

				if (!window.confirm(i18n.cancelConfirm || 'Cancel the highlights import?')) {
					return;
				}

				cancelRequested = true;
				cancelBtn.disabled = true;

				postJobRequest('ipa_highlights_import_cancel', activeJobId)
					.finally(function () {
						appendLog(logEl, { type: 'warning', message: i18n.importCancelled || 'Import cancelled.' });
						setImportUiState(false);
						activeJobId = '';
						cancelBtn.disabled = false;
					});
			});
		}
		}

	document.querySelectorAll('.ipa-pinned-posts-form').forEach(function (form) {
		var maxPins = parseInt(form.querySelector('.ipa-pinned-posts-count')?.getAttribute('data-max') || '3', 10);
		var countEl = form.querySelector('.ipa-pinned-posts-count');
		var boxes = form.querySelectorAll('input[type="checkbox"][name="ipa_pinned_ids[]"]');

		function refreshPinLimit() {
			var checked = Array.prototype.filter.call(boxes, function (box) {
				return box.checked;
			});

			if (countEl) {
				countEl.textContent = String(checked.length);
			}

			if (checked.length >= maxPins) {
				boxes.forEach(function (box) {
					if (!box.checked) {
						box.disabled = true;
					}
				});
			} else {
				boxes.forEach(function (box) {
					box.disabled = false;
				});
			}
		}

		boxes.forEach(function (box) {
			box.addEventListener('change', refreshPinLimit);
		});

		refreshPinLimit();
	});

	document.querySelectorAll('.ipa-delete-section').forEach(function (section) {
		var selectAllBtn = section.querySelector('.ipa-select-all');
		var deselectAllBtn = section.querySelector('.ipa-deselect-all');
		var masterCheckbox = section.querySelector('.ipa-select-all-checkbox');
		var itemCheckboxes = function () {
			return section.querySelectorAll('tbody input[type="checkbox"][name="ipa_delete_ids[]"]');
		};

		function setAll(checked) {
			itemCheckboxes().forEach(function (checkbox) {
				checkbox.checked = checked;
			});
			if (masterCheckbox) {
				masterCheckbox.checked = checked;
				masterCheckbox.indeterminate = false;
			}
		}

		function syncMasterCheckbox() {
			if (!masterCheckbox) {
				return;
			}
			var boxes = Array.prototype.slice.call(itemCheckboxes());
			var checkedCount = boxes.filter(function (checkbox) {
				return checkbox.checked;
			}).length;
			masterCheckbox.checked = boxes.length > 0 && checkedCount === boxes.length;
			masterCheckbox.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
		}

		if (selectAllBtn) {
			selectAllBtn.addEventListener('click', function () {
				setAll(true);
			});
		}

		if (deselectAllBtn) {
			deselectAllBtn.addEventListener('click', function () {
				setAll(false);
			});
		}

		if (masterCheckbox) {
			masterCheckbox.addEventListener('change', function () {
				setAll(masterCheckbox.checked);
			});
		}

		itemCheckboxes().forEach(function (checkbox) {
			checkbox.addEventListener('change', syncMasterCheckbox);
		});
	});
	});
})();
