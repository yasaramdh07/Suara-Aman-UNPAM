(function () {
  'use strict';

  // 1. Salin kode tiket ke clipboard (berubah "Tersalin" selama 2 detik).
  document.querySelectorAll('[data-copy-target]').forEach(function (button) {
    var labelEl = button.querySelector('[data-copy-label]');
    var iconEl = button.querySelector('[data-copy-icon]');
    var defaultLabel = labelEl ? labelEl.textContent : 'Salin kode';
    var copyTimer = null;

    button.addEventListener('click', function () {
      var targetEl = document.getElementById(button.dataset.copyTarget);
      if (!targetEl) { return; }
      var text = targetEl.textContent.trim();
      var status = document.getElementById(button.dataset.copyStatus);

      var done = function (ok) {
        if (status) {
          status.textContent = ok ? 'Kode disalin ke papan klip.' : 'Gagal menyalin. Salin kode secara manual.';
        }
        if (ok && labelEl) {
          labelEl.textContent = 'Tersalin';
          if (iconEl) {
            iconEl.classList.remove('bi-clipboard');
            iconEl.classList.add('bi-check2');
          }
          if (copyTimer) { clearTimeout(copyTimer); }
          copyTimer = setTimeout(function () {
            labelEl.textContent = defaultLabel;
            if (iconEl) {
              iconEl.classList.remove('bi-check2');
              iconEl.classList.add('bi-clipboard');
            }
          }, 2000);
        }
      };

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(false); });
        return;
      }
      var area = document.createElement('textarea');
      area.value = text;
      area.setAttribute('readonly', '');
      area.style.position = 'fixed';
      area.style.opacity = '0';
      document.body.appendChild(area);
      area.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
      document.body.removeChild(area);
      done(ok);
    });
  });

  // 2. Unduh kode tiket sebagai berkas .txt di sisi browser.
  document.querySelectorAll('[data-download-ticket]').forEach(function (button) {
    button.addEventListener('click', function () {
      var targetEl = document.getElementById(button.dataset.downloadTicket);
      if (!targetEl) { return; }
      var code = targetEl.textContent.trim();
      var content = 'SUARA AMAN - MA YABIKA\r\n'
        + 'Kode Tiket Laporan: ' + code + '\r\n\r\n'
        + 'Simpan kode ini baik-baik. Gunakan kode ini di halaman Cek Status (/status)\r\n'
        + 'untuk melihat perkembangan laporan dan membaca balasan Guru BK.\r\n';
      var encoded = 'data:text/plain;charset=utf-8,' + encodeURIComponent(content);
      var link = document.createElement('a');
      link.setAttribute('href', encoded);
      link.setAttribute('download', 'kode-tiket-' + code + '.txt');
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    });
  });

  // 3. Lipat/buka menu navigasi di layar kecil.
  document.querySelectorAll('[data-nav-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
      var target = document.getElementById(button.dataset.navToggle);
      if (!target) { return; }
      var open = target.classList.toggle('is-open');
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  // 4. Sembunyikan gambar logo bila gagal dimuat agar tidak menampilkan ikon gambar rusak.
  document.querySelectorAll('img[data-hide-broken]').forEach(function (img) {
    img.addEventListener('error', function () {
      img.hidden = true;
      if (img.nextElementSibling && img.nextElementSibling.classList.contains('sa-brand-sep')) {
        img.nextElementSibling.hidden = true;
      }
    });
  });

  // 5. Tombol cetak rekap (tanpa inline onclick agar sesuai CSP).
  document.querySelectorAll('[data-print]').forEach(function (button) {
    button.addEventListener('click', function () {
      window.print();
    });
  });

  // 6. Sinkronisasi kelas .is-selected pada kartu radio (.sa-seg-option & .sa-cat-card).
  var syncRadioCards = function () {
    document.querySelectorAll('.sa-seg-option, .sa-cat-card').forEach(function (label) {
      var input = label.querySelector('input[type="radio"]');
      if (input) {
        label.classList.toggle('is-selected', input.checked);
      }
    });
  };
  document.querySelectorAll('.sa-seg-option input, .sa-cat-card input').forEach(function (input) {
    input.addEventListener('change', syncRadioCards);
  });
  syncRadioCards();

  // 7. Penghitung karakter untuk textarea uraian.
  document.querySelectorAll('[data-char-counter]').forEach(function (textarea) {
    var counterEl = document.getElementById(textarea.dataset.charCounter);
    if (!counterEl) { return; }
    var max = parseInt(textarea.getAttribute('maxlength') || '5000', 10);
    var updateCount = function () {
      counterEl.textContent = textarea.value.length + ' / ' + max;
    };
    textarea.addEventListener('input', updateCount);
    updateCount();
  });

  // 8. Tombol tampilkan/sembunyikan kata sandi (/akun).
  document.querySelectorAll('[data-pw-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.getElementById(button.dataset.pwToggle);
      if (!input) { return; }
      var icon = button.querySelector('i');
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      if (icon) {
        icon.classList.toggle('bi-eye', !show);
        icon.classList.toggle('bi-eye-slash', show);
      }
    });
  });

  // 9. Petunjuk kekuatan kata sandi sederhana (/akun).
  document.querySelectorAll('[data-pw-strength]').forEach(function (input) {
    var meter = document.getElementById(input.dataset.pwStrength);
    if (!meter) { return; }
    input.addEventListener('input', function () {
      var val = input.value;
      meter.classList.remove('is-weak', 'is-fair', 'is-strong');
      if (val.length === 0) {
        meter.textContent = 'Kekuatan sandi: gunakan minimal 10 karakter dengan kombinasi huruf besar, kecil, dan angka.';
        return;
      }
      if (val.length < 10) {
        meter.classList.add('is-weak');
        meter.textContent = 'Kekuatan sandi: terlalu pendek (minimal 10 karakter, saat ini ' + val.length + ').';
        return;
      }
      var variety = (/[a-z]/.test(val) ? 1 : 0)
        + (/[A-Z]/.test(val) ? 1 : 0)
        + (/\d/.test(val) ? 1 : 0)
        + (/[^a-zA-Z0-9]/.test(val) ? 1 : 0);
      if (val.length >= 12 && variety >= 3) {
        meter.classList.add('is-strong');
        meter.textContent = 'Kekuatan sandi: kuat.';
      } else {
        meter.classList.add('is-fair');
        meter.textContent = 'Kekuatan sandi: cukup (tambahkan huruf besar, angka, atau simbol agar lebih kuat).';
      }
    });
  });

  // 10. Cegah kirim ganda & tampilkan indikator memuat pada tombol submit.
  document.querySelectorAll('form[data-once], form[data-single-submit]').forEach(function (form) {
    var button = form.querySelector('[type="submit"]');
    var labelEl = button ? button.querySelector('[data-submit-label]') : null;
    var iconEl = button ? button.querySelector('[data-submit-icon]') : null;
    var origLabel = labelEl ? labelEl.textContent : '';
    var origIconClass = iconEl ? iconEl.className : '';

    form.addEventListener('submit', function (event) {
      if (form.dataset.sending === '1') {
        event.preventDefault();
        return;
      }
      form.dataset.sending = '1';
      if (button) {
        button.disabled = true;
        if (labelEl && button.dataset.loadingText) {
          labelEl.textContent = button.dataset.loadingText;
        }
        if (iconEl) {
          iconEl.className = 'bi bi-arrow-repeat sa-spin me-2';
        }
      }
    });

    window.addEventListener('pageshow', function () {
      form.dataset.sending = '0';
      if (button) {
        button.disabled = false;
        if (labelEl && origLabel) {
          labelEl.textContent = origLabel;
        }
        if (iconEl && origIconClass) {
          iconEl.className = origIconClass;
        }
      }
    });
  });

  // 11. Pemeriksaan file bukti gambar, pratinjau miniatur, dan tombol hapus per file.
  var fileInput = document.querySelector('input[data-max-files]');
  if (fileInput) {
    var errEl = document.getElementById(fileInput.dataset.errorId);
    var previewEl = fileInput.dataset.previewId ? document.getElementById(fileInput.dataset.previewId) : null;
    var selectedFiles = [];

    var syncInputFiles = function () {
      if (typeof DataTransfer === 'undefined') { return; }
      var dt = new DataTransfer();
      selectedFiles.forEach(function (f) { dt.items.add(f); });
      fileInput.files = dt.files;
    };

    var renderPreviews = function () {
      if (!previewEl) { return; }
      previewEl.innerHTML = '';
      selectedFiles.forEach(function (file, idx) {
        var card = document.createElement('div');
        card.className = 'sa-preview-card';

        var img = document.createElement('img');
        img.className = 'sa-preview-thumb';
        img.alt = 'Pratinjau ' + file.name;

        var reader = new FileReader();
        reader.onload = function (e) {
          if (e.target && typeof e.target.result === 'string') {
            img.src = e.target.result;
          }
        };
        reader.readAsDataURL(file);

        var meta = document.createElement('div');
        meta.className = 'sa-preview-meta';
        meta.textContent = file.name + ' (' + Math.max(1, Math.round(file.size / 1024)) + ' KB)';

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn sa-btn-danger sa-preview-remove';
        removeBtn.setAttribute('aria-label', 'Hapus gambar ' + file.name);
        removeBtn.innerHTML = '<i class="bi bi-trash3" aria-hidden="true"></i>';
        removeBtn.addEventListener('click', function () {
          selectedFiles.splice(idx, 1);
          syncInputFiles();
          renderPreviews();
        });

        card.appendChild(img);
        card.appendChild(meta);
        card.appendChild(removeBtn);
        previewEl.appendChild(card);
      });
    };

    fileInput.addEventListener('change', function () {
      var maxFiles = parseInt(fileInput.dataset.maxFiles, 10);
      var maxBytes = parseInt(fileInput.dataset.maxBytes, 10);
      var incoming = Array.prototype.slice.call(fileInput.files || []);
      var problem = '';

      if (incoming.length > maxFiles) {
        problem = 'Pilih maksimal ' + maxFiles + ' gambar.';
      } else if (incoming.some(function (f) { return f.size > maxBytes; })) {
        problem = 'Ukuran tiap gambar maksimal 5 MB.';
      }

      if (problem) {
        fileInput.value = '';
        selectedFiles = [];
        if (previewEl) { previewEl.innerHTML = ''; }
      } else {
        selectedFiles = incoming;
      }

      if (errEl) {
        errEl.textContent = problem;
        errEl.hidden = problem === '';
      }
      renderPreviews();
    });
  }
})();
