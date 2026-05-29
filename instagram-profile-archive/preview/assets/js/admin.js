/**
 * YouPreserver — Admin JavaScript
 */
(function () {
	'use strict';

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
	});
})();
