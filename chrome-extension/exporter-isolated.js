(function () {
	'use strict';

	const IG_APP_ID = '936619743392459';
	const EXPORT_FORMAT = 'ipa-highlights-export';
	const EXPORT_VERSION = 1;

	function sanitizeFilename(value) {
		return String(value || 'file').replace(/[^a-zA-Z0-9._-]+/g, '_').slice(0, 80);
	}

	function usernameFromLocation() {
		const pathname = window.location.pathname.replace(/^\/+|\/+$/g, '');
		const parts = pathname.split('/').filter(Boolean);
		if (!parts.length) {
			throw new Error('Open your Instagram profile page first.');
		}
		const blocked = ['p', 'reel', 'reels', 'stories', 'explore', 'accounts', 'direct', 'tv'];
		if (blocked.indexOf(parts[0].toLowerCase()) !== -1) {
			throw new Error('Open your Instagram profile page first (not a post or reel).');
		}
		return parts[0];
	}

	function igHeaders(referer) {
		return {
			'X-IG-App-ID': IG_APP_ID,
			'Accept': '*/*',
			'Referer': referer || window.location.href,
		};
	}

	async function fetchJson(url, referer) {
		const response = await fetch(url, {
			credentials: 'include',
			headers: igHeaders(referer),
		});
		const body = await response.json().catch(function () {
			return {};
		});
		if (!response.ok) {
			throw new Error(body.message || ('HTTP ' + response.status));
		}
		return body;
	}

	async function fetchBlob(url) {
		return new Promise(function (resolve, reject) {
			chrome.runtime.sendMessage({ action: 'fetchBlob', url: url }, function (response) {
				if (chrome.runtime.lastError) {
					reject(new Error(chrome.runtime.lastError.message));
					return;
				}
				if (!response?.ok) {
					reject(new Error(response?.error || 'Media download failed.'));
					return;
				}
				resolve(new Blob([new Uint8Array(response.data)], { type: response.mime || 'application/octet-stream' }));
			});
		});
	}

	function extractCoverUrl(node) {
		return (
			node?.cover_media?.cropped_image_version?.url ||
			node?.cover_media?.thumbnail_url ||
			node?.cover_media?.thumbnail_src ||
			node?.cover_media_cropped_thumbnail?.url ||
			node?.display_url ||
			''
		);
	}

	function extractStoryMedia(story) {
		const isVideo = Array.isArray(story?.video_versions) && story.video_versions.length > 0;
		const mediaUrl = isVideo
			? story.video_versions[0].url
			: (story?.image_versions2?.candidates?.[0]?.url || '');
		const thumbUrl = story?.image_versions2?.candidates?.[0]?.url || mediaUrl;
		return {
			media_type: isVideo ? 'VIDEO' : 'IMAGE',
			url: mediaUrl,
			thumb: thumbUrl,
		};
	}

	function extensionFromUrl(url, fallback) {
		try {
			const pathname = new URL(url).pathname;
			const ext = pathname.split('.').pop().toLowerCase();
			if (['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov', 'm4v'].indexOf(ext) !== -1) {
				return ext === 'jpeg' ? 'jpg' : ext;
			}
		} catch (error) {
			// Ignore invalid URLs.
		}
		return fallback;
	}

	async function getProfile(username) {
		const referer = 'https://www.instagram.com/' + encodeURIComponent(username) + '/';
		const body = await fetchJson(
			'https://www.instagram.com/api/v1/users/web_profile_info/?username=' + encodeURIComponent(username),
			referer
		);
		const user = body?.data?.user;
		if (!user?.id) {
			throw new Error('Could not load profile for @' + username + '. Make sure you are logged in.');
		}
		return {
			username: user.username || username,
			userId: String(user.id),
			highlightCount: Number(user.highlight_reel_count || 0),
		};
	}

	async function getHighlightsTray(userId, username) {
		const referer = 'https://www.instagram.com/' + encodeURIComponent(username) + '/';
		const body = await fetchJson(
			'https://i.instagram.com/api/v1/highlights/' + encodeURIComponent(userId) + '/highlights_tray/',
			referer
		);
		return Array.isArray(body.tray) ? body.tray : [];
	}

	async function getHighlightStories(highlightId, username) {
		const referer = 'https://www.instagram.com/' + encodeURIComponent(username) + '/';
		const body = await fetchJson(
			'https://i.instagram.com/api/v1/feed/reels_media/?reel_ids=' + encodeURIComponent(highlightId),
			referer
		);
		const reel = body?.reels_media?.[0];
		return Array.isArray(reel?.items) ? reel.items : [];
	}

	async function runExport(onProgress) {
		if (typeof JSZip === 'undefined') {
			throw new Error('ZIP library failed to load.');
		}

		const username = usernameFromLocation();
		onProgress('Loading @' + username + '…');

		const profile = await getProfile(username);
		if (profile.highlightCount === 0) {
			throw new Error('This profile has no highlights.');
		}

		onProgress('Fetching highlights…');
		const tray = await getHighlightsTray(profile.userId, profile.username);
		if (!tray.length) {
			throw new Error('Could not load highlights. Stay logged in to Instagram and try again.');
		}

		const zip = new JSZip();
		const exportHighlights = [];

		for (let index = 0; index < tray.length; index += 1) {
			const item = tray[index];
			const highlightId = String(item.id || '');
			if (!highlightId) {
				continue;
			}

			onProgress('Exporting highlight ' + (index + 1) + ' / ' + tray.length + '…');

			const coverUrl = extractCoverUrl(item);
			if (!coverUrl) {
				continue;
			}

			const coverExt = extensionFromUrl(coverUrl, 'jpg');
			const coverPath = 'files/covers/' + sanitizeFilename(highlightId) + '.' + coverExt;
			zip.file(coverPath, await fetchBlob(coverUrl));

			const stories = [];
			const storyItems = await getHighlightStories(highlightId, profile.username);
			for (const story of storyItems) {
				const storyId = String(story.id || story.pk || '');
				if (!storyId) {
					continue;
				}

				const media = extractStoryMedia(story);
				if (!media.url) {
					continue;
				}

				const mediaExt = extensionFromUrl(media.url, media.media_type === 'VIDEO' ? 'mp4' : 'jpg');
				const mediaPath = 'files/media/' + sanitizeFilename(storyId) + '.' + mediaExt;
				zip.file(mediaPath, await fetchBlob(media.url));

				let thumbPath = mediaPath;
				if (media.thumb && media.thumb !== media.url) {
					const thumbExt = extensionFromUrl(media.thumb, 'jpg');
					thumbPath = 'files/media/' + sanitizeFilename(storyId) + '_thumb.' + thumbExt;
					zip.file(thumbPath, await fetchBlob(media.thumb));
				}

				stories.push({
					id: storyId,
					media_type: media.media_type,
					file: mediaPath,
					thumb: thumbPath,
					posted_at: story.taken_at
						? new Date(story.taken_at * 1000).toISOString().slice(0, 19).replace('T', ' ')
						: '',
				});
			}

			exportHighlights.push({
				id: highlightId,
				title: String(item.title || ''),
				sort_order: index,
				cover: coverPath,
				stories: stories,
			});
		}

		if (!exportHighlights.length) {
			throw new Error('No highlights could be exported.');
		}

		zip.file(
			'highlights.json',
			JSON.stringify(
				{
					version: EXPORT_VERSION,
					format: EXPORT_FORMAT,
					exported_at: new Date().toISOString(),
					username: profile.username,
					user_id: profile.userId,
					highlights: exportHighlights,
				},
				null,
				2
			)
		);

		onProgress('Building ZIP…');
		const blob = await zip.generateAsync({
			type: 'blob',
			compression: 'DEFLATE',
			compressionOptions: { level: 6 },
		});

		const filename =
			'ipa-highlights-' +
			sanitizeFilename(profile.username) +
			'-' +
			new Date().toISOString().slice(0, 10) +
			'.zip';
		const url = URL.createObjectURL(blob);
		const anchor = document.createElement('a');
		anchor.href = url;
		anchor.download = filename;
		anchor.style.display = 'none';
		document.body.appendChild(anchor);
		anchor.click();
		anchor.remove();
		setTimeout(function () {
			URL.revokeObjectURL(url);
		}, 60000);

		return {
			username: profile.username,
			count: exportHighlights.length,
			filename: filename,
		};
	}

	window.__ipaStartExport = async function () {
		const updates = [];
		const root = document.documentElement;

		function setState(state, payload) {
			root.setAttribute('data-ipa-export-state', state);
			root.setAttribute('data-ipa-export-payload', JSON.stringify(payload));
		}

		root.setAttribute('data-ipa-export-state', 'running');
		root.setAttribute('data-ipa-export-progress', 'Starting export…');
		root.removeAttribute('data-ipa-export-payload');

		try {
			const result = await runExport(function (message) {
				updates.push(message);
				root.setAttribute('data-ipa-export-progress', message);
				root.setAttribute(
					'data-ipa-export-payload',
					JSON.stringify({ updates: updates.slice() })
				);
			});
			root.setAttribute('data-ipa-export-progress', 'Done');
			setState('complete', { result: result, updates: updates });
		} catch (error) {
			const message = error?.message || String(error);
			root.setAttribute('data-ipa-export-progress', message);
			setState('error', { error: message, updates: updates });
		}
	};
})();
