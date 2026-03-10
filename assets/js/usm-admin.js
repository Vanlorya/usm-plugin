/**
 * USM Admin JS – Focus Mode + UI enhancements.
 *
 * @package USM
 */

(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {

		// ── Focus Mode Toggle ────────────────────────
		var focusBtn = document.getElementById('usm-focus-toggle');
		if (focusBtn && typeof usmData !== 'undefined') {
			focusBtn.addEventListener('click', function () {
				var btn = this;
				btn.disabled = true;
				btn.textContent = '⏳ ...';

				var xhr = new XMLHttpRequest();
				xhr.open('POST', usmData.ajaxUrl);
				xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				xhr.onload = function () {
					if (xhr.status === 200) {
						var resp = JSON.parse(xhr.responseText);
						if (resp.success) {
							// Reload to apply/remove focus CSS
							window.location.reload();
						}
					}
					btn.disabled = false;
				};
				xhr.send('action=usm_toggle_focus&nonce=' + usmData.focusNonce);
			});
		}

		// ── Confirm delete actions ───────────────────
		var deleteLinks = document.querySelectorAll('.usm-confirm-delete');
		deleteLinks.forEach(function (link) {
			link.addEventListener('click', function (e) {
				if (!confirm('Bạn có chắc chắn muốn xoá? Hành động này không thể hoàn tác.')) {
					e.preventDefault();
				}
			});
		});

		// ── Toggle form visibility ──────────────────
		var toggleBtns = document.querySelectorAll('[data-toggle-form]');
		toggleBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var targetId = this.getAttribute('data-toggle-form');
				var target = document.getElementById(targetId);
				if (target) {
					target.style.display = target.style.display === 'none' ? 'block' : 'none';
				}
			});
		});

		// ── Client-side search for tables ────────────
		var searchInput = document.getElementById('usm-search-input');
		if (searchInput) {
			searchInput.addEventListener('input', function () {
				var query = this.value.toLowerCase();
				var table = document.querySelector('.usm-table');
				if (!table) return;
				var rows = table.querySelectorAll('tbody tr');
				rows.forEach(function (row) {
					var text = row.textContent.toLowerCase();
					row.style.display = text.includes(query) ? '' : 'none';
				});
			});
		}
		// ── Auto-upgrade empty table states ─────────
		var tables = document.querySelectorAll('.usm-table');
		tables.forEach(function (table) {
			var rows = table.querySelectorAll('tbody tr');
			if (rows.length === 1) {
				var cell = rows[0].querySelector('td');
				if (cell && cell.getAttribute('colspan') && cell.textContent.trim().indexOf('Chưa có') !== -1) {
					var colSpan = cell.getAttribute('colspan');
					cell.innerHTML = '<div class="usm-empty-state">' +
						'<div class="usm-empty-icon">📭</div>' +
						'<p>' + cell.textContent.trim() + '</p>' +
						'</div>';
					cell.style.padding = '0';
					cell.style.border = 'none';
				}
			}

			// Add row count badge for tables with data.
			var dataRows = table.querySelectorAll('tbody tr:not([style*="display: none"])');
			if (dataRows.length > 1 || (dataRows.length === 1 && !dataRows[0].querySelector('.usm-empty-state'))) {
				var countBadge = document.createElement('div');
				countBadge.style.cssText = 'margin-top:8px; font-size:12px; color:#646970;';
				countBadge.textContent = '📊 Hiển thị ' + dataRows.length + ' dòng';
				table.parentNode.insertBefore(countBadge, table.nextSibling);
			}
		});

		// ── Highlight active sidebar menu ────────────
		var currentPage = new URLSearchParams(window.location.search).get('page');
		if (currentPage) {
			var sideLinks = document.querySelectorAll('#adminmenu a[href*="page=' + currentPage + '"]');
			sideLinks.forEach(function (link) {
				link.style.fontWeight = '700';
			});
		}
	});
})();
