(function () {
	'use strict';

	const statusEl = document.getElementById('status');
	const startBtn = document.getElementById('start-btn');
	const logEl = document.getElementById('log');
	const progressWrap = document.getElementById('progress-wrap');
	const progressBar = document.getElementById('progress-bar');
	const progressText = document.getElementById('progress-text');

	let loggedLines = [];

	function setStatus(message, type) {
		statusEl.textContent = message;
		statusEl.className = 'status' + (type ? ' ' + type : '');
	}

	function showProgress(active) {
		progressWrap.hidden = !active;
		if (!active) {
			progressBar.style.width = '0%';
			progressText.textContent = '';
		}
	}

	function setProgress(message, percent) {
		showProgress(true);
		progressText.textContent = message;
		if (typeof percent === 'number') {
			progressBar.style.width = Math.max(0, Math.min(100, percent)) + '%';
		} else {
			progressBar.classList.add('is-indeterminate');
		}
	}

	function log(message, replace) {
		if (!message) {
			return;
		}
		logEl.hidden = false;
		if (replace) {
			loggedLines = [message];
		} else if (loggedLines[loggedLines.length - 1] !== message) {
			loggedLines.push(message);
		}
		logEl.textContent = loggedLines.join('\n');
		logEl.scrollTop = logEl.scrollHeight;
	}

	function isInstagramProfileUrl(url) {
		try {
			const parsed = new URL(url);
			if (parsed.hostname !== 'www.instagram.com' && parsed.hostname !== 'instagram.com') {
				return false;
			}
			const parts = parsed.pathname.replace(/^\/+|\/+$/g, '').split('/').filter(Boolean);
			if (parts.length !== 1) {
				return false;
			}
			const blocked = ['p', 'reel', 'reels', 'stories', 'explore', 'accounts', 'direct', 'tv'];
			return blocked.indexOf(parts[0].toLowerCase()) === -1;
		} catch (error) {
			return false;
		}
	}

	function usernameFromUrl(url) {
		const parts = new URL(url).pathname.replace(/^\/+|\/+$/g, '').split('/').filter(Boolean);
		return parts[0] || '';
	}

	async function getActiveInstagramTab() {
		const tabs = await chrome.tabs.query({ active: true, currentWindow: true });
		const tab = tabs[0];
		if (!tab?.id || !tab.url || !isInstagramProfileUrl(tab.url)) {
			return null;
		}
		return tab;
	}

	async function refreshReadyState() {
		const tab = await getActiveInstagramTab();
		if (!tab) {
			setStatus('Open your Instagram profile page, then click Start.');
			return null;
		}
		setStatus('Ready — @' + usernameFromUrl(tab.url));
		return tab;
	}

	function readPayload(raw) {
		if (!raw) {
			return {};
		}
		try {
			return JSON.parse(raw);
		} catch (error) {
			return {};
		}
	}

	async function readExportState(tabId) {
		const results = await chrome.scripting.executeScript({
			target: { tabId: tabId },
			func: function () {
				return {
					state: document.documentElement.getAttribute('data-ipa-export-state') || '',
					progress: document.documentElement.getAttribute('data-ipa-export-progress') || '',
					payloadRaw: document.documentElement.getAttribute('data-ipa-export-payload') || '',
				};
			},
		});
		return results?.[0]?.result || { state: '', progress: '', payloadRaw: '' };
	}

	async function kickoffExport(tabId) {
		await chrome.scripting.executeScript({
			target: { tabId: tabId },
			func: function () {
				const root = document.documentElement;
				root.removeAttribute('data-ipa-export-state');
				root.removeAttribute('data-ipa-export-progress');
				root.removeAttribute('data-ipa-export-payload');
				root.setAttribute('data-ipa-export-state', 'loading');
				root.setAttribute('data-ipa-export-progress', 'Loading exporter…');
			},
		});

		await chrome.scripting.executeScript({
			target: { tabId: tabId },
			files: ['lib/jszip.min.js', 'exporter-isolated.js'],
		});

		await chrome.scripting.executeScript({
			target: { tabId: tabId },
			func: function () {
				const root = document.documentElement;
				if (typeof window.__ipaStartExport === 'function') {
					root.setAttribute('data-ipa-export-progress', 'Starting export…');
					window.__ipaStartExport();
					return;
				}
				root.setAttribute('data-ipa-export-state', 'error');
				root.setAttribute('data-ipa-export-progress', 'Exporter failed to initialize.');
				root.setAttribute(
					'data-ipa-export-payload',
					JSON.stringify({ error: 'Exporter failed to initialize.' })
				);
			},
		});
	}

	function guessPercent(progress) {
		const match = /highlight\s+(\d+)\s*\/\s*(\d+)/i.exec(progress || '');
		if (!match) {
			return null;
		}
		const current = Number(match[1]);
		const total = Number(match[2]);
		if (!total) {
			return null;
		}
		return Math.round((current / total) * 100);
	}

	function waitForExport(tabId) {
		return new Promise(function (resolve) {
			const startedAt = Date.now();
			const timeoutMs = 15 * 60 * 1000;
			let lastProgress = '';

			const pollId = setInterval(async function () {
				try {
					if (Date.now() - startedAt > timeoutMs) {
						clearInterval(pollId);
						resolve({ ok: false, error: 'Export timed out. Try again.' });
						return;
					}

					const snapshot = await readExportState(tabId);
					const state = snapshot.state;
					const progress = snapshot.progress || '';
					const payload = readPayload(snapshot.payloadRaw);

					if (progress && progress !== lastProgress) {
						lastProgress = progress;
						setProgress(progress, guessPercent(progress));
						log(progress);
					}

					if (payload.updates?.length) {
						payload.updates.forEach(function (line) {
							log(line);
						});
					}

					if (state === 'loading' && Date.now() - startedAt > 20000 && !progress) {
						clearInterval(pollId);
						resolve({
							ok: false,
							error: 'Export did not start. Reload the Instagram tab and try again.',
						});
						return;
					}

					if (state === 'complete') {
						clearInterval(pollId);
						progressBar.classList.remove('is-indeterminate');
						progressBar.style.width = '100%';
						resolve({
							ok: true,
							result: payload.result || null,
							updates: payload.updates || [],
						});
						return;
					}

					if (state === 'error') {
						clearInterval(pollId);
						resolve({
							ok: false,
							error: payload.error || progress || 'Export failed.',
							updates: payload.updates || [],
						});
					}
				} catch (error) {
					clearInterval(pollId);
					resolve({ ok: false, error: error?.message || 'Could not read export progress.' });
				}
			}, 400);
		});
	}

	startBtn.addEventListener('click', async function () {
		const tab = await getActiveInstagramTab();
		if (!tab) {
			setStatus('Open your Instagram profile page first.', 'error');
			return;
		}

		startBtn.disabled = true;
		loggedLines = [];
		logEl.textContent = '';
		logEl.hidden = false;
		progressBar.classList.add('is-indeterminate');
		setStatus('Working…');
		setProgress('Starting…', null);
		log('Starting export for @' + usernameFromUrl(tab.url) + '…', true);

		try {
			await kickoffExport(tab.id);
			const payload = await waitForExport(tab.id);

			if (!payload.ok) {
				throw new Error(payload.error || 'Export failed.');
			}

			const exportResult = payload.result;
			if (!exportResult) {
				throw new Error('Export finished without a result.');
			}

			setStatus('Exported ' + exportResult.count + ' highlights for @' + exportResult.username + '.', 'success');
			setProgress('ZIP saved — ' + exportResult.filename, 100);
			log('Saved: ' + exportResult.filename);
			log('Upload the ZIP in WordPress → YouPreserver → Sync Settings.');
		} catch (error) {
			const message = error?.message || 'Export failed.';
			setStatus(message, 'error');
			setProgress(message, 0);
			log(message);
		} finally {
			startBtn.disabled = false;
		}
	});

	document.addEventListener('DOMContentLoaded', refreshReadyState);
})();
