/**
 * .access-my-sessions button — magic link popup or redirect.
 */
(function ($) {
	'use strict';

	var $modal, $form, $email, $submit, $message, $close;

	function wpesAjaxUrl() {
		return wpesAccess.ajaxUrl + (wpesAccess.ajaxUrl.indexOf('?') > -1 ? '&' : '?') + '_=' + Date.now() + Math.random().toString(36).slice(2);
	}

	function buildModal() {
		if ($modal && $modal.length) {
			return;
		}

		var html =
			'<div class="access-my-sessions-modal" id="wpesAccessModal" hidden>' +
				'<div class="access-my-sessions-modal__backdrop" data-close></div>' +
				'<div class="access-my-sessions-modal__dialog box">' +
					'<button type="button" class="access-my-sessions-modal__close" data-close aria-label="Close">&times;</button>' +
					'<h3 class="title is-5">' + wpesAccess.i18n.title + '</h3>' +
					'<p class="subtitle is-6 has-text-grey">' + wpesAccess.i18n.subtitle + '</p>' +
					'<form id="wpesAccessForm">' +
						'<div class="field">' +
							'<label class="label" for="wpesAccessEmail">Email</label>' +
							'<div class="control">' +
								'<input class="input" type="email" id="wpesAccessEmail" name="email" required placeholder="you@example.com" />' +
							'</div>' +
						'</div>' +
						'<div class="field">' +
							'<div class="control">' +
								'<button type="submit" class="button is-link is-fullwidth" id="wpesAccessSubmit">' + wpesAccess.i18n.sendLink + '</button>' +
							'</div>' +
						'</div>' +
						'<div class="access-my-sessions-modal__message" id="wpesAccessMessage" hidden></div>' +
					'</form>' +
				'</div>' +
			'</div>';

		$('body').append(html);
		$modal   = $('#wpesAccessModal');
		$form    = $('#wpesAccessForm');
		$email   = $('#wpesAccessEmail');
		$submit  = $('#wpesAccessSubmit');
		$message = $('#wpesAccessMessage');
		$close   = $modal.find('[data-close]');

		$close.on('click', closeModal);
		$form.on('submit', onSubmit);
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' && !$modal.prop('hidden')) {
				closeModal();
			}
		});
	}

	function openModal() {
		buildModal();
		$message.prop('hidden', true).removeClass('is-success is-danger');
		$email.val('');
		$submit.removeClass('is-loading').prop('disabled', false);
		$modal.prop('hidden', false);
		$email.trigger('focus');
	}

	function closeModal() {
		if ($modal) {
			$modal.prop('hidden', true);
		}
	}

	function showMessage(text, type) {
		$message.text(text).removeClass('is-success is-danger').addClass('is-' + type).prop('hidden', false);
	}

	function onSubmit(e) {
		e.preventDefault();
		var val = $.trim($email.val());

		if (!val) {
			showMessage(wpesAccess.i18n.invalidEmail, 'danger');
			return;
		}

		$submit.addClass('is-loading').prop('disabled', true);
		$message.prop('hidden', true);

		$.post(wpesAjaxUrl(), {
			action: 'wpes_request_magic_link',
			nonce: wpesAccess.nonce,
			email: val
		}).done(function (res) {
			if (res.success) {
				showMessage(res.data.message || wpesAccess.i18n.sent, 'success');
			} else {
				showMessage((res.data && res.data.message) || wpesAccess.i18n.error, 'danger');
			}
		}).fail(function () {
			showMessage(wpesAccess.i18n.error, 'danger');
		}).always(function () {
			$submit.removeClass('is-loading').prop('disabled', false);
		});
	}

	$(document).on('click', '.access-my-sessions', function (e) {
		e.preventDefault();
		if (wpesAccess.isLoggedIn) {
			window.location.href = wpesAccess.accountUrl;
			return;
		}
		openModal();
	});
})(jQuery);
