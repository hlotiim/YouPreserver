'use strict';

chrome.runtime.onMessage.addListener(function (message, sender, sendResponse) {
	if (message.action !== 'fetchBlob' || !message.url) {
		return false;
	}

	fetch(message.url, {
		credentials: 'omit',
		headers: {
			'Accept': '*/*',
			'Referer': 'https://www.instagram.com/',
			'User-Agent':
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
		},
	})
		.then(function (response) {
			if (!response.ok) {
				throw new Error('Media download failed (HTTP ' + response.status + ')');
			}
			const mime = response.headers.get('content-type') || 'application/octet-stream';
			return response.arrayBuffer().then(function (buffer) {
				return { buffer: buffer, mime: mime };
			});
		})
		.then(function (payload) {
			sendResponse({
				ok: true,
				data: Array.from(new Uint8Array(payload.buffer)),
				mime: payload.mime,
			});
		})
		.catch(function (error) {
			sendResponse({
				ok: false,
				error: error?.message || 'Media download failed.',
			});
		});

	return true;
});
