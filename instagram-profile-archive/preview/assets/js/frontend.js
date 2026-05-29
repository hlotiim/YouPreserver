/**
 * YouPreserver — Frontend JavaScript
 */
(function () {
	'use strict';

	var cfg = window.ipaFrontend || {};
	var root = document.querySelector('.ipa-instagram-archive');
	if (!root) return;

	function parseJsonEl(id) {
		var el = document.getElementById(id);
		if (!el) return [];
		try {
			return JSON.parse(el.textContent || '[]');
		} catch (e) {
			return [];
		}
	}

	var postsData = parseJsonEl('ipa-media-data');
	var reelsData = parseJsonEl('ipa-reels-data');
	var highlightsData = parseJsonEl('ipa-highlights-data');
	var activeMediaData = postsData;
	var activeFilter = root.getAttribute('data-active-filter') || 'grid';

	var modal = document.getElementById('ipa-modal');
	var modalMedia = document.getElementById('ipa-modal-media');
	var modalCaption = document.getElementById('ipa-modal-caption');
	var modalDate = document.getElementById('ipa-modal-date');
	var modalLink = document.getElementById('ipa-modal-link');
	var modalPrev = document.getElementById('ipa-modal-prev');
	var modalNext = document.getElementById('ipa-modal-next');
	var modalDots = document.getElementById('ipa-modal-dots');
	var loadMoreWrap = document.getElementById('ipa-load-more-wrap');
	var loadMoreSentinel = document.getElementById('ipa-load-more-sentinel');
	var loadMoreStatus = document.getElementById('ipa-load-more-status');
	var postsGrid = document.getElementById('ipa-grid');
	var reelsGrid = document.getElementById('ipa-reels-grid');
	var postsEmpty = document.getElementById('ipa-grid-empty');
	var reelsEmpty = document.getElementById('ipa-reels-empty');

	var currentPostIndex = 0;
	var currentSlideIndex = 0;
	var lastFocused = null;
	var loadingMore = false;
	var loadMoreObserver = null;
	var viewerMode = 'posts';

	if (modal && modal.parentNode !== document.body) {
		document.body.appendChild(modal);
	}

	function qs(sel, ctx) {
		return (ctx || document).querySelector(sel);
	}

	function qsa(sel, ctx) {
		return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
	}

	function formatDate(dateStr) {
		if (!dateStr) return '';
		var d = new Date(dateStr.replace(' ', 'T'));
		if (isNaN(d.getTime())) return dateStr;
		return d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
	}

	function isVideoType(type) {
		return type === 'VIDEO' || type === 'REELS';
	}

	function getActiveGrid() {
		return activeFilter === 'reels' ? reelsGrid : postsGrid;
	}

	function hasMoreForActiveFilter() {
		return activeFilter === 'reels'
			? root.getAttribute('data-reels-has-more') === '1'
			: root.getAttribute('data-has-more') === '1';
	}

	function setLoadMoreLoading(isLoading) {
		if (loadMoreWrap) {
			loadMoreWrap.classList.toggle('ipa-load-more-loading', isLoading);
		}
		if (loadMoreStatus) {
			loadMoreStatus.textContent = isLoading ? (cfg.loadingText || 'Loading…') : '';
		}
	}

	function updateLoadMoreVisibility() {
		if (!loadMoreWrap) return;
		loadMoreWrap.classList.toggle('ipa-hidden', !hasMoreForActiveFilter());
		if (!hasMoreForActiveFilter()) {
			setLoadMoreLoading(false);
		}
	}

	function sentinelNearViewport() {
		if (!loadMoreSentinel) return false;
		var rect = loadMoreSentinel.getBoundingClientRect();
		return rect.top <= window.innerHeight + 240;
	}

	function maybeLoadMore() {
		if (loadingMore || !hasMoreForActiveFilter() || !loadMoreSentinel) {
			return;
		}
		if (!sentinelNearViewport()) {
			return;
		}
		loadNextPage();
	}

	function renderSlide(slide) {
		if (!modalMedia) return;
		modalMedia.innerHTML = '';

		if (isVideoType(slide.type)) {
			var video = document.createElement('video');
			video.src = slide.url;
			video.controls = false;
			video.autoplay = true;
			video.muted = true;
			video.loop = true;
			video.playsInline = true;
			video.setAttribute('playsinline', '');
			video.setAttribute('webkit-playsinline', '');
			video.setAttribute('preload', 'auto');
			video.setAttribute('disablepictureinpicture', '');
			video.setAttribute('controlsList', 'nodownload noplaybackrate noremoteplayback');
			if (slide.thumb) video.poster = slide.thumb;
			modalMedia.appendChild(video);

			var playPromise = video.play();
			if (playPromise && typeof playPromise.catch === 'function') {
				playPromise.catch(function () {
					// Autoplay can be blocked until the viewer interaction; retry muted.
					video.muted = true;
					video.play().catch(function () {});
				});
			}
		} else {
			var img = document.createElement('img');
			img.src = slide.url;
			img.alt = '';
			img.loading = 'lazy';
			modalMedia.appendChild(img);
		}
	}

	function updateModalContent() {
		var post = activeMediaData[currentPostIndex];
		if (!post) return;

		var slides = post.slides || [];
		var slide = slides[currentSlideIndex] || slides[0];
		if (!slide) return;

		renderSlide(slide);

		if (modalCaption) {
			var captionText = cfg.showCaptions !== false ? (post.caption || '') : '';
			modalCaption.textContent = captionText;
			modalCaption.style.display = captionText ? '' : 'none';
		}

		if (modalDate) {
			var formatted = cfg.showDates !== false ? formatDate(post.posted_at) : '';
			modalDate.textContent = formatted;
			modalDate.style.display = formatted ? '' : 'none';
		}

		if (modalLink) {
			if (cfg.showInstagramLink !== false && post.permalink) {
				modalLink.href = post.permalink;
				modalLink.style.display = '';
			} else {
				modalLink.style.display = 'none';
			}
		}

		var showNav = slides.length > 1;
		if (modalPrev) modalPrev.classList.toggle('ipa-hidden', !showNav);
		if (modalNext) modalNext.classList.toggle('ipa-hidden', !showNav);

		if (modalDots) {
			modalDots.innerHTML = '';
			if (showNav) {
				slides.forEach(function (_, i) {
					var dot = document.createElement('span');
					dot.className = 'ipa-modal-dot' + (i === currentSlideIndex ? ' ipa-modal-dot-active' : '');
					modalDots.appendChild(dot);
				});
			}
		}

		var footer = qs('.ipa-modal-footer', modal);
		if (footer) {
			var hasCaption = modalCaption && modalCaption.style.display !== 'none' && modalCaption.textContent;
			var hasDate = modalDate && modalDate.style.display !== 'none' && modalDate.textContent;
			var hasLink = modalLink && modalLink.style.display !== 'none';
			footer.classList.toggle('ipa-hidden', viewerMode === 'highlights' && !hasCaption && !hasDate && !hasLink);
		}
	}

	function openModal(postIndex, mediaSet) {
		if (!modal) return;
		if (mediaSet) {
			activeMediaData = mediaSet;
		}
		if (!activeMediaData[postIndex]) return;

		viewerMode = mediaSet === highlightsData ? 'highlights' : 'posts';
		currentPostIndex = postIndex;
		currentSlideIndex = 0;
		lastFocused = document.activeElement;

		modal.classList.remove('ipa-hidden');
		modal.classList.toggle('ipa-modal-highlights', viewerMode === 'highlights');
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('ipa-viewer-open');

		updateModalContent();

		var closeBtn = qs('[data-ipa-close]', modal);
		if (closeBtn) closeBtn.focus();
	}

	function closeModal() {
		if (!modal) return;

		modal.classList.add('ipa-hidden');
		modal.classList.remove('ipa-modal-highlights');
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('ipa-viewer-open');

		if (modalMedia) {
			var video = modalMedia.querySelector('video');
			if (video) video.pause();
			modalMedia.innerHTML = '';
		}

		if (lastFocused && lastFocused.focus) {
			lastFocused.focus();
		}
	}

	function nextSlide() {
		var post = activeMediaData[currentPostIndex];
		if (!post || !post.slides) return;
		currentSlideIndex = (currentSlideIndex + 1) % post.slides.length;
		updateModalContent();
	}

	function prevSlide() {
		var post = activeMediaData[currentPostIndex];
		if (!post || !post.slides) return;
		currentSlideIndex = (currentSlideIndex - 1 + post.slides.length) % post.slides.length;
		updateModalContent();
	}

	function setActiveTab(filter) {
		qsa('.ipa-tab', root).forEach(function (tab) {
			var isActive = tab.getAttribute('data-filter') === filter;
			tab.classList.toggle('ipa-tab-active', isActive);
			tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
		});
	}

	function applyFilter(filter) {
		activeFilter = filter || 'grid';
		root.setAttribute('data-active-filter', activeFilter);
		setActiveTab(activeFilter);

		var showReels = activeFilter === 'reels';
		activeMediaData = showReels ? reelsData : postsData;

		if (postsGrid) postsGrid.classList.toggle('ipa-hidden', showReels);
		if (postsEmpty) postsEmpty.classList.toggle('ipa-hidden', showReels);
		if (reelsGrid) reelsGrid.classList.toggle('ipa-hidden', !showReels);
		if (reelsEmpty) reelsEmpty.classList.toggle('ipa-hidden', !showReels || (reelsGrid && reelsGrid.children.length > 0));

		updateLoadMoreVisibility();
		requestAnimationFrame(maybeLoadMore);
	}

	function bindGridClicks(container, mediaSet) {
		if (!container) return;

		container.addEventListener('click', function (e) {
			var item = e.target.closest('.ipa-grid-item');
			if (!item || item.classList.contains('ipa-hidden-filter')) return;
			var index = parseInt(item.getAttribute('data-index'), 10);
			if (!isNaN(index)) openModal(index, mediaSet);
		});
	}

	function bindHighlights() {
		var dragState = null;
		var suppressHighlightClick = false;

		qsa('.ipa-highlight:not(.ipa-highlight-static)', root).forEach(function (btn) {
			btn.addEventListener('pointerdown', function (event) {
				event.stopPropagation();
			});

			btn.addEventListener('click', function (event) {
				if (suppressHighlightClick) {
					event.preventDefault();
					event.stopImmediatePropagation();
					return;
				}
				var index = parseInt(btn.getAttribute('data-highlight-index'), 10);
				if (!isNaN(index)) {
					openModal(index, highlightsData);
				}
			});
		});

		qsa('.ipa-highlights', root).forEach(function (strip) {
			function canScroll() {
				return strip.scrollWidth > strip.clientWidth + 2;
			}

			function isHighlightTarget(event) {
				return !!(event.target && event.target.closest && event.target.closest('.ipa-highlight'));
			}

			strip.addEventListener('wheel', function (event) {
				if (!canScroll()) {
					return;
				}

				var delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
				if (!delta) {
					return;
				}

				strip.scrollLeft += delta;
				event.preventDefault();
			}, { passive: false });

			strip.addEventListener('pointerdown', function (event) {
				if (isHighlightTarget(event) || !canScroll() || event.pointerType !== 'mouse' || event.button !== 0) {
					return;
				}

				dragState = {
					strip: strip,
					pointerId: event.pointerId,
					startX: event.clientX,
					scrollLeft: strip.scrollLeft,
					moved: false
				};

				strip.classList.add('ipa-highlights-dragging');
				if (strip.setPointerCapture) {
					strip.setPointerCapture(event.pointerId);
				}
			});

			strip.addEventListener('pointermove', function (event) {
				if (!dragState || dragState.strip !== strip || event.pointerId !== dragState.pointerId) {
					return;
				}

				var deltaX = event.clientX - dragState.startX;
				if (Math.abs(deltaX) > 4) {
					dragState.moved = true;
				}

				strip.scrollLeft = dragState.scrollLeft - deltaX;
				event.preventDefault();
			});

			function endDrag(event) {
				if (!dragState || dragState.strip !== strip || event.pointerId !== dragState.pointerId) {
					return;
				}

				if (dragState.moved) {
					suppressHighlightClick = true;
					window.setTimeout(function () {
						suppressHighlightClick = false;
					}, 300);
				}

				strip.classList.remove('ipa-highlights-dragging');
				if (strip.releasePointerCapture) {
					try {
						strip.releasePointerCapture(event.pointerId);
					} catch (e) {
						// Pointer may already be released.
					}
				}
				dragState = null;
			}

			strip.addEventListener('pointerup', endDrag);
			strip.addEventListener('pointercancel', endDrag);
		});
	}

	function bindTabs() {
		qsa('.ipa-tab', root).forEach(function (tab) {
			tab.addEventListener('click', function () {
				applyFilter(tab.getAttribute('data-filter'));
			});
		});
	}

	function bindModal() {
		if (!modal) return;

		qsa('[data-ipa-close]', modal).forEach(function (el) {
			el.addEventListener('click', closeModal);
		});

		if (modalPrev) modalPrev.addEventListener('click', prevSlide);
		if (modalNext) modalNext.addEventListener('click', nextSlide);

		document.addEventListener('keydown', function (e) {
			if (modal.classList.contains('ipa-hidden')) return;

			if (e.key === 'Escape') {
				closeModal();
			} else if (e.key === 'ArrowRight') {
				nextSlide();
			} else if (e.key === 'ArrowLeft') {
				prevSlide();
			}
		});

		var touchStartX = 0;
		modal.addEventListener('touchstart', function (e) {
			touchStartX = e.changedTouches[0].screenX;
		}, { passive: true });

		modal.addEventListener('touchend', function (e) {
			var diff = e.changedTouches[0].screenX - touchStartX;
			if (Math.abs(diff) < 40) return;
			if (diff < 0) nextSlide();
			else prevSlide();
		}, { passive: true });
	}

	function loadNextPage() {
		if (loadingMore || !hasMoreForActiveFilter()) {
			return;
		}

		var isReels = activeFilter === 'reels';
		var offsetAttr = isReels ? 'data-reels-offset' : 'data-offset';
		var hasMoreAttr = isReels ? 'data-reels-has-more' : 'data-has-more';
		var offset = parseInt(root.getAttribute(offsetAttr) || '0', 10);
		var targetGrid = getActiveGrid();

		loadingMore = true;
		setLoadMoreLoading(true);

		var body = new FormData();
		body.append('action', 'ipa_load_more');
		body.append('nonce', cfg.nonce || '');
		body.append('offset', String(offset));
		body.append('filter', isReels ? 'reels' : 'posts');

		fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(function (json) {
				if (!json.success) return;

				if (json.data.html && targetGrid) {
					targetGrid.insertAdjacentHTML('beforeend', json.data.html);
				}

				if (json.data.modal && json.data.modal.length) {
					if (isReels) {
						reelsData = reelsData.concat(json.data.modal);
						activeMediaData = reelsData;
					} else {
						postsData = postsData.concat(json.data.modal);
						activeMediaData = postsData;
					}
				}

				root.setAttribute(offsetAttr, String(json.data.offset || offset));
				root.setAttribute(hasMoreAttr, json.data.has_more ? '1' : '0');
				updateLoadMoreVisibility();
			})
			.catch(function () {})
			.finally(function () {
				loadingMore = false;
				setLoadMoreLoading(false);
				if (hasMoreForActiveFilter()) {
					requestAnimationFrame(maybeLoadMore);
				}
			});
	}

	function bindInfiniteScroll() {
		if (!loadMoreSentinel || typeof IntersectionObserver === 'undefined') {
			window.addEventListener('scroll', maybeLoadMore, { passive: true });
			window.addEventListener('resize', maybeLoadMore, { passive: true });
			return;
		}

		loadMoreObserver = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						maybeLoadMore();
					}
				});
			},
			{
				root: null,
				rootMargin: '240px 0px 240px 0px',
				threshold: 0
			}
		);

		loadMoreObserver.observe(loadMoreSentinel);
	}

	bindGridClicks(postsGrid, postsData);
	bindGridClicks(reelsGrid, reelsData);
	bindHighlights();
	bindTabs();
	bindModal();
	bindInfiniteScroll();
	applyFilter(activeFilter);
})();
