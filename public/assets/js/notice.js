(function () {
  'use strict';

  var STORAGE_KEY = 'sa_notice_v1';
  var modalEl = document.getElementById('sa-notice-modal');
  var acceptBtn = document.getElementById('sa-notice-accept');

  if (!modalEl || !acceptBtn) {
    return;
  }

  try {
    if (window.sessionStorage && window.sessionStorage.getItem(STORAGE_KEY) === '1') {
      return;
    }
  } catch (e) {
    // Lanjutkan menampilkan modal bila sessionStorage diblokir.
  }

  var previousActive = document.activeElement;
  var bsModal = null;

  if (window.bootstrap && window.bootstrap.Modal) {
    bsModal = new window.bootstrap.Modal(modalEl, {
      backdrop: 'static',
      keyboard: false,
      focus: true
    });
    modalEl.addEventListener('shown.bs.modal', function () {
      acceptBtn.focus();
    });
    bsModal.show();
  } else {
    // Fallback bila Bootstrap JS belum siap
    modalEl.classList.add('show', 'd-block');
    modalEl.removeAttribute('aria-hidden');
    document.body.classList.add('modal-open');
    acceptBtn.focus();
  }

  // Kunci fokus keyboard (Tab / Shift+Tab) di dalam modal dan cegah tombol Escape.
  modalEl.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      return;
    }
    if (event.key !== 'Tab') {
      return;
    }
    var focusable = modalEl.querySelectorAll(
      'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    if (!focusable.length) {
      return;
    }
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  acceptBtn.addEventListener('click', function () {
    try {
      if (window.sessionStorage) {
        window.sessionStorage.setItem(STORAGE_KEY, '1');
      }
    } catch (e) {}

    if (bsModal) {
      bsModal.hide();
    } else {
      modalEl.classList.remove('show', 'd-block');
      modalEl.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('modal-open');
    }

    if (previousActive && typeof previousActive.focus === 'function') {
      previousActive.focus();
    }
  });
})();
