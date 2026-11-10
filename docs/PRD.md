# PRD Suara Aman

Sistem Pengaduan Bullying Anonim untuk Sekolah (MA YABIKA) | Proyek Mata Kuliah Pancasila | Versi 1.0 (draft awal)

## 1. Ringkasan

**Suara Aman** adalah aplikasi web pengaduan bullying yang memungkinkan siswa (korban atau saksi) melapor secara anonim kepada guru BK tanpa login dan tanpa wajib menyebut identitas. Pelapor menerima **kode tiket acak** untuk mengecek status dan membaca balasan BK, sehingga komunikasi dua arah tetap mungkin tanpa membuka identitas.

Prinsip utama: **privasi pelapor nomor satu**. Semua keputusan teknis di dokumen ini tunduk pada prinsip itu.

Keterkaitan Pancasila: Sila 2 (kemanusiaan yang adil dan beradab) dan Sila 5 (keadilan sosial bagi seluruh siswa, termasuk yang takut bicara).

## 2. Masalah dan Tujuan

**Masalah:** siswa enggan melapor bullying karena takut dibalas pelaku, malu, atau tidak percaya laporan akan dirahasiakan. Akibatnya kasus tidak terdeteksi dan tidak ditindaklanjuti.

**Tujuan:**

- Menyediakan kanal lapor yang aman, cepat (kurang dari 3 menit), dan bisa diakses dari HP.
- Membantu guru BK mengelola laporan secara terstruktur: terdata, berstatus, dan dapat ditindaklanjuti.
- Menjaga kepercayaan siswa dengan jaminan teknis, bukan sekadar janji.
- Dapat dideploy cepat ke hosting biasa dan diuji coba 1-2 bulan di satu sekolah.

**Di luar tujuan (non-goals) untuk versi ini:** aplikasi mobile native, integrasi WhatsApp/email, akun siswa, analitik lanjutan, multi-sekolah.

## 3. Pengguna dan Peran

| Peran | Login | Bisa | Tidak bisa |
|---|---|---|---|
| Pelapor (korban/saksi) | Tidak | Kirim laporan, dapat kode tiket, cek status dan balasan BK, balas lewat kode tiket | Melihat laporan orang lain |
| Guru BK | Ya | Lihat dan proses semua laporan, ubah status dan urgensi, balas pelapor, catatan internal, rekap | Mengubah pengaturan sistem, mengelola akun lain |
| Admin (pengembang) | Ya | Kelola akun BK, kategori, pengaturan, lihat statistik agregat, maintenance | **Membaca isi laporan atau balasan** |

Catatan: kepala sekolah belum punya akun di v1. Eskalasi ditandai lewat status "Dieskalasi" oleh BK dan ditangani di luar sistem.

## 4. Stack dan Batasan Teknis

Asumsi: tanpa framework, target hosting murah (shared hosting/cPanel), deploy cepat.

- **Bahasa:** PHP 8.1+ native (tanpa Composer, tanpa dependensi eksternal).
- **Database:** SQLite satu file via PDO, mode WAL, foreign keys aktif.
- **Frontend:** HTML + Bootstrap 5 (file lokal, bukan CDN) + sedikit JavaScript vanilla. Mobile-first. Tampilan mengikuti `DESIGN.md`.
- **Ekstensi PHP yang dibutuhkan:** pdo_sqlite, gd, sodium, mbstring, fileinfo.
- **Penyimpanan:** database dan file bukti berada **di luar document root** atau diproteksi `.htaccess` (deny all).
- **Konfigurasi:** satu file `config.php` di luar document root (kunci enkripsi, pepper, pengaturan lain).
- **Deploy:** upload folder, buka `install.php` sekali (buat database dan akun admin pertama), lalu hapus file tersebut.

Catatan jujur: karena pengembang yang memasang server, ia secara teknis bisa mengakses kunci enkripsi di `config.php`. Perlindungan v1 adalah enkripsi di database, tidak ada fitur baca isi laporan untuk admin, dan komitmen etika bahwa pengembang tidak membuka data. Penguatan (v1.1): kunci diturunkan dari passphrase yang hanya diketahui guru BK.

## 5. Alur Pengguna

### 5.1 Pelapor

1. Buka link atau scan QR di sekolah, masuk ke halaman beranda Suara Aman.
2. Klik "Buat Laporan" dan isi form: peran (korban/saksi), jenis kejadian, waktu, lokasi, uraian, pihak terlibat (opsional), bukti (opsional).
3. Kirim. Sistem menampilkan **kode tiket** satu kali dengan peringatan untuk menyimpannya.
4. Kapan saja, buka "Cek Status", masukkan kode, lihat status dan balasan BK, dan bisa membalas.

### 5.2 Guru BK

1. Login, lihat dashboard laporan (urut urgensi dan waktu).
2. Buka detail laporan, tentukan urgensi dan status.
3. Balas pelapor lewat kolom pesan (muncul saat pelapor cek status), tambah catatan internal.
4. Tindak lanjut di dunia nyata (panggil pihak terkait, konseling, koordinasi wali kelas/kepala sekolah), catat hasilnya.
5. Tutup kasus (status Selesai) dan lihat rekap.

### 5.3 Admin

Login, kelola akun BK (buat, nonaktifkan, reset password), kelola kategori jenis kejadian, lihat statistik agregat (jumlah laporan per bulan/kategori/status), unduh backup database terenkripsi.

## 6. Fitur

### MVP (wajib untuk deploy)

- Form laporan anonim tanpa login.
- Kode tiket otomatis, cek status, dan percakapan dua arah pelapor-BK.
- Upload bukti (gambar), metadata dibersihkan.
- Dashboard BK: daftar, filter (status, urgensi, kategori), detail, ubah status dan urgensi, balasan, catatan internal.
- Penanda urgensi otomatis berdasarkan kata kunci (misalnya ancaman menyakiti diri/orang lain, kekerasan fisik berat) sebagai saran, BK tetap memutuskan.
- Panel admin: akun BK, kategori, statistik agregat.
- Rekap bulanan sederhana (tampilan, bisa dicetak dari browser).
- Halaman "Tentang dan Privasi" dalam bahasa mudah dipahami siswa.

### Nanti (setelah uji coba)

- Kunci enkripsi berbasis passphrase BK.
- Peran kepala sekolah dan alur eskalasi formal.
- Ekspor rekap PDF/Excel.
- Notifikasi untuk BK (email/WhatsApp) tanpa membocorkan isi laporan.
- Retensi data otomatis yang bisa diatur dari panel.

## 7. Aturan Bisnis Penting

**Kode tiket**

- Digenerate sistem, bukan dipilih pelapor. Format `XXXX-XXXX-XXXX` (12 karakter), alfabet tanpa karakter mirip: `23456789ABCDEFGHJKLMNPQRSTUVWXYZ`.
- Sumber acak kriptografis (`random_int` atau `random_bytes`).
- Disimpan **hanya sebagai hash** (HMAC-SHA256 dengan pepper dari config). Kode asli ditampilkan sekali.
- Kode hilang berarti pelapor tidak bisa mengakses laporan itu lagi (BK tetap bisa memprosesnya).
- Percobaan cek status dibatasi (misalnya 10 percobaan gagal per 15 menit per sumber).

**Anonimitas**

- Tidak menyimpan IP, user agent, atau identitas perangkat di tabel laporan atau pesan.
- Untuk rate limit, boleh menyimpan **hash IP dengan salt yang berganti harian**, hanya di tabel sementara yang dibersihkan otomatis tiap 24 jam, dan tidak pernah terhubung ke laporan.
- Waktu laporan disimpan dengan ketelitian tanggal dan jam, tanpa data lain yang bisa menunjuk perangkat.
- File gambar di-reencode lewat GD untuk membuang EXIF dan metadata lain.

**Enkripsi dan akses**

- Uraian laporan, pihak terlibat, dan isi pesan dienkripsi di database dengan `sodium_crypto_secretbox` (kunci dari config).
- File bukti disimpan di luar document root dan hanya bisa diunduh lewat skrip PHP yang mewajibkan login BK.
- Admin tidak punya halaman maupun query yang menampilkan isi laporan.
- Log akses: setiap kali BK membuka laporan dicatat (siapa, kapan), tanpa isi.

**Status dan urgensi**

- Status: Baru, Ditinjau, Ditindaklanjuti, Selesai, Dieskalasi.
- Urgensi: Ringan, Sedang, Mendesak. Laporan dengan kata kunci risiko otomatis diusulkan Mendesak dan diberi penanda mencolok.

**Retensi**

- Laporan berstatus Selesai dapat dihapus permanen oleh BK. Default saran: dihapus setelah 12 bulan (bisa diatur di v1.1).

## 8. Halaman dan Rute

| Rute | Akses | Fungsi |
|---|---|---|
| `/` | Publik | Beranda, penjelasan singkat dan jaminan privasi |
| `/lapor` | Publik | Form laporan |
| `/lapor/sukses` | Publik (sekali tampil) | Menampilkan kode tiket |
| `/status` | Publik | Input kode tiket, lihat status dan percakapan |
| `/privasi` | Publik | Penjelasan privasi untuk siswa |
| `/bk/login` | Publik | Login BK dan admin |
| `/bk/laporan` | BK | Daftar laporan dan filter |
| `/bk/laporan/{id}` | BK | Detail, balasan, status, catatan internal |
| `/bk/rekap` | BK | Rekap bulanan |
| `/admin/akun` | Admin | Kelola akun BK |
| `/admin/kategori` | Admin | Kelola kategori |
| `/admin/statistik` | Admin | Statistik agregat |
| `/admin/backup` | Admin | Unduh backup database |

## 9. Skema Data (SQLite)

```
users(id, username UNIQUE, password_hash, role CHECK IN ('bk','admin'), is_active, created_at)
categories(id, name, is_active)
reports(id, ticket_hash UNIQUE, category_id, reporter_role CHECK IN ('korban','saksi'),
        incident_date, location_enc, description_enc, involved_enc,
        urgency CHECK IN ('ringan','sedang','mendesak'), urgency_auto_flag,
        status CHECK IN ('baru','ditinjau','ditindaklanjuti','selesai','dieskalasi'),
        created_at, updated_at)
messages(id, report_id, sender CHECK IN ('pelapor','bk'), body_enc, created_at)
attachments(id, report_id, stored_name, mime, size, created_at)
internal_notes(id, report_id, user_id, note_enc, created_at)
access_log(id, user_id, report_id, action, created_at)
rate_limits(key_hash, bucket, count, expires_at)   -- dibersihkan otomatis
```

Semua kolom `_enc` berisi ciphertext (nonce + secretbox, base64).

## 10. Kebutuhan Non-Fungsional

- **Keamanan:** query selalu prepared statement; CSRF token di semua form; password dengan `password_hash` (Argon2id atau bcrypt); session cookie HttpOnly, Secure, SameSite=Strict untuk BK; `session_regenerate_id` saat login; batas percobaan login; header keamanan (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy); `display_errors` off di produksi; wajib HTTPS.
- **Validasi upload:** hanya JPG/PNG/WebP, maksimal 3 file dan 5 MB per file, cek MIME asli, nama file diacak.
- **Performa:** halaman ringan (di bawah 300 KB tanpa gambar bukti), nyaman di HP dan sinyal lemah.
- **Aksesibilitas dan bahasa:** Bahasa Indonesia yang sederhana, kontras jelas, tombol besar.
- **Tanpa pelacak:** tidak ada analytics pihak ketiga, tidak ada font atau skrip dari CDN.
- **Backup:** unduhan manual dari panel admin dan saran backup berkala dari cPanel.
- **Kemudahan deploy:** tanpa build step, tanpa Composer, satu folder bisa langsung diunggah.

## 11. Kriteria Penerimaan MVP

1. Pelapor dapat mengirim laporan tanpa login dan menerima kode tiket sekali tampil.
2. Kode tiket yang benar menampilkan status dan percakapan; kode salah tidak membocorkan apa pun dan terkena rate limit.
3. Di database tidak ada IP, user agent, atau isi laporan dalam bentuk teks biasa.
4. File bukti yang diunggah tidak memuat EXIF setelah diproses.
5. BK dapat melihat, memfilter, memproses, dan membalas laporan; setiap akses tercatat di log.
6. Admin tidak bisa membuka isi laporan dari UI mana pun.
7. Folder database dan upload tidak bisa diakses langsung lewat URL.
8. Aplikasi berjalan di shared hosting PHP 8.1 tanpa Composer.

## 12. Indikator Keberhasilan Uji Coba (1-2 bulan)

- Jumlah laporan masuk dan persentase yang ditindaklanjuti.
- Rata-rata waktu respons BK terhadap laporan baru.
- Survei singkat siswa: merasa lebih aman melapor (skala 1-5).
- Umpan balik guru BK tentang kemudahan penggunaan.

## 13. Risiko dan Mitigasi

| Risiko | Mitigasi |
|---|---|
| Izin sekolah dan kekhawatiran data | Dapatkan persetujuan tertulis kepala sekolah dan BK sebelum uji coba; jelaskan alur data |
| Laporan palsu atau spam | Rate limit, CAPTCHA sederhana buatan sendiri (tanpa pihak ketiga), BK dapat menandai spam |
| Siswa kehilangan kode tiket | Peringatan jelas di layar sukses; tawarkan buat laporan baru |
| Laporan berisiko menyakiti diri | Penanda urgensi otomatis; SOP BK untuk penanganan cepat |
| Pengembang adalah bagian dari sekolah | Admin tanpa akses isi, enkripsi di database, komitmen tertulis tidak membuka data |
| Hosting murah kurang aman | HTTPS wajib, update PHP, folder data di luar document root |

## 14. Tahapan Pengerjaan

1. **Fondasi:** struktur folder, config, koneksi SQLite, migrasi skema, helper enkripsi, CSRF, session, layout Bootstrap.
2. **Sisi pelapor:** form laporan, generator dan hash kode tiket, upload dan sanitasi gambar, halaman cek status dan percakapan.
3. **Sisi BK:** login, daftar dan filter, detail, balasan, status dan urgensi, catatan internal, log akses, rekap.
4. **Sisi admin:** akun BK, kategori, statistik agregat, backup, install.php.
5. **Pengerasan dan uji:** rate limit, header keamanan, pengecekan kriteria penerimaan, uji di HP, uji deploy di hosting.

Prompt per tahap ada di `PROMPTS.md`, dan panduan tampilan di `DESIGN.md`.