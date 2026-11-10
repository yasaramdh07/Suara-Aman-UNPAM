# PROMPTS.md: Suara Aman (per bagian)

Cara pakai:

1. Taruh `PRD.md` (ekspor dari dokumen PRD) dan `DESIGN.md` di root folder proyek.
2. Paste **Prompt 0** sekali di awal (atau simpan sebagai `CLAUDE.md` / `AGENTS.md` kalau tool-nya mendukung).
3. Jalankan Prompt 1 sampai 5 **berurutan**, satu per sesi atau satu per giliran. Jangan lanjut sebelum tahap sebelumnya lolos uji.
4. Kalau ada yang salah, perbaiki di tahap itu dulu, jangan menumpuk.

---

## Prompt 0: Konteks dasar (paste sekali)

```
Kamu senior PHP developer yang fokus pada keamanan dan privasi. Kita membangun "Suara Aman": aplikasi web pengaduan bullying anonim untuk sekolah (MA YABIKA). Siswa (korban/saksi) melapor tanpa login ke guru BK. Pelapor mendapat kode tiket acak untuk cek status dan membalas pesan BK tanpa membuka identitas.

Baca PRD.md (isi dan aturan bisnis) dan DESIGN.md (tampilan) sebelum menulis kode, dan patuhi keduanya.

Batasan teknis wajib:
- PHP 8.1+ native, TANPA framework, TANPA Composer atau dependensi eksternal.
- SQLite via PDO (mode WAL, foreign_keys ON), satu file database.
- Frontend: HTML + Bootstrap 5 lokal (bukan CDN) + JavaScript vanilla. Mobile-first, Bahasa Indonesia sederhana.
- Target shared hosting/cPanel. Tanpa build step. Ekstensi: pdo_sqlite, gd, sodium, mbstring, fileinfo.
- Struktur folder: public/ (document root: index.php dan assets), app/ (logika), data/ (database dan uploads), config.php di luar public/. Beri .htaccess "deny all" untuk app/ dan data/ sebagai cadangan.

Aturan privasi yang tidak boleh dilanggar:
- Jangan simpan IP, user agent, atau identitas perangkat di tabel reports atau messages.
- Isi laporan, pesan, dan catatan internal dienkripsi (sodium_crypto_secretbox).
- Admin tidak boleh bisa membaca isi laporan dari UI atau query mana pun.
- Tanpa analytics, font, atau skrip pihak ketiga.

Cara kerja:
- Kerjakan hanya tahap yang diminta, tanpa fitur tambahan.
- Setelah selesai, jelaskan apa yang dibuat, daftar file, dan cara mengujinya. Lalu berhenti dan tunggu konfirmasi.
- Kode rapi, komentar singkat di bagian keamanan, tanpa kode mati.
```

---

## Prompt 1: Fondasi

```
TAHAP 1: FONDASI. Kerjakan hanya ini.

1. Buat struktur folder sesuai konteks (public/, app/, data/, config.example.php).
2. app/db.php: koneksi PDO SQLite (WAL, foreign_keys ON, error mode exception, fetch assoc).
3. app/migrate.php: migrasi idempoten untuk tabel users, categories, reports, messages, attachments, internal_notes, access_log, rate_limits sesuai skema di PRD.md bagian 9 (kolom *_enc berisi ciphertext base64). Tambahkan CHECK constraint untuk kolom enum dan indeks untuk filter yang umum (status, urgency, created_at).
4. app/crypto.php: fungsi encrypt($plain) dan decrypt($cipher) dengan sodium_crypto_secretbox, nonce acak, base64; fungsi ticket_hash($code) dengan HMAC-SHA256 memakai pepper dari config; fungsi generate_ticket() format XXXX-XXXX-XXXX dari alfabet 23456789ABCDEFGHJKLMNPQRSTUVWXYZ memakai random_int.
5. app/security.php: session aman (HttpOnly, Secure bila HTTPS, SameSite=Strict untuk area BK), CSRF token (buat dan verifikasi), helper e() untuk htmlspecialchars, helper header keamanan (CSP tanpa sumber eksternal, X-Frame-Options DENY, X-Content-Type-Options nosniff, Referrer-Policy no-referrer).
6. app/router.php + public/index.php: router sederhana untuk rute di PRD.md bagian 8 (halaman boleh masih kosong/placeholder), 404 yang rapi.
7. Layout dasar: public/assets/ berisi Bootstrap lokal (kamu tulis instruksi untuk mengunduhnya, jangan memakai CDN) dan theme.css persis seperti token di DESIGN.md bagian 10. Buat templat layout (header wordmark, footer sederhana) sesuai DESIGN.md.
8. config.example.php berisi: path database, kunci enkripsi (32 byte), pepper, mode debug (false), zona waktu Asia/Jakarta.

Uji: jalankan migrasi pada database kosong dua kali tanpa galat; tes kecil untuk encrypt/decrypt dan ticket_hash; buka / dan rute lain di server PHP bawaan (php -S) dan pastikan layout tampil sesuai DESIGN.md.
```

---

## Prompt 2: Sisi pelapor

```
TAHAP 2: SISI PELAPOR (tanpa login). Tahap 1 sudah selesai. Kerjakan hanya ini.

Halaman dan perilaku (ikuti PRD.md bagian 5.1, 7, dan 8, serta DESIGN.md):
1. / : beranda ringkas, rata kiri: apa itu Suara Aman, tombol utama "Buat laporan", tautan sekunder "Cek status laporan" dan "Privasi". Tanpa hero besar atau kartu fitur.
2. /privasi : penjelasan privasi dalam bahasa sederhana: apa yang tidak disimpan, siapa yang bisa membaca laporan, apa arti kode tiket.
3. /lapor : form: peran (korban/saksi), kategori (dari tabel categories), tanggal dan jam kejadian, lokasi, uraian (wajib), pihak terlibat (opsional), bukti gambar (opsional, maks 3 file, 5 MB per file, jpg/png/webp). Validasi sisi server, pesan galat jelas. CSRF wajib.
4. Pemrosesan kirim: enkripsi uraian, lokasi, pihak terlibat; generate kode tiket; simpan HANYA ticket_hash; hitung urgensi otomatis dari daftar kata kunci di app/risk_keywords.php (usulan Mendesak jika cocok; BK tetap memutuskan).
5. Upload bukti: validasi MIME asli dengan finfo, re-encode lewat GD untuk membuang EXIF dan metadata, nama file diacak, simpan di data/uploads (di luar public/). Tolak file selain gambar.
6. /lapor/sukses : tampilkan kode tiket SEKALI (setelah itu hilang dari session) di kotak kode tiket sesuai DESIGN.md, tombol "Salin kode", dan peringatan bahwa kode tidak bisa dipulihkan.
7. /status : input kode; bila benar tampilkan status, kategori, tanggal, dan percakapan (pesan pelapor dan BK) serta form balasan; bila salah tampilkan pesan generik yang sama untuk semua kegagalan. Rate limit 10 percobaan gagal per 15 menit per sumber memakai hash IP bersalt harian di tabel rate_limits, dengan pembersihan otomatis entri kedaluwarsa. IP mentah tidak boleh tersimpan di mana pun.
8. CAPTCHA sederhana buatan sendiri (misal pertanyaan hitung acak yang disimpan di session) pada form laporan untuk menahan spam, tanpa layanan pihak ketiga.

Uji: kirim laporan tanpa dan dengan gambar berEXIF; pastikan EXIF hilang; periksa isi database dan pastikan tidak ada teks laporan, IP, atau user agent dalam bentuk biasa; kode benar vs salah; rate limit bekerja; tampilan rapi di lebar 375px.
```

---

## Prompt 3: Sisi guru BK

```
TAHAP 3: SISI GURU BK. Tahap 1-2 sudah selesai. Kerjakan hanya ini.

1. /bk/login : form login (username, password) untuk role bk dan admin; password_hash/password_verify, session_regenerate_id saat sukses, batas percobaan login (misal 5 gagal per 15 menit per username dan per sumber), pesan galat generik. Logout dengan CSRF. Setelah login, role bk ke /bk/laporan dan role admin ke /admin/statistik.
2. Middleware akses: area /bk/* hanya role bk; area /admin/* hanya role admin. Admin yang membuka /bk/* ditolak (403).
3. /bk/laporan : tabel laporan dengan kolom kode internal (ID), tanggal, kategori, peran pelapor, badge urgensi, badge status. Filter status, urgensi, kategori, dan rentang tanggal. Urut default: urgensi (Mendesak dulu) lalu terbaru. Laporan Mendesak ditandai mencolok sesuai DESIGN.md. Tampilan HP berupa daftar sederhana.
4. /bk/laporan/{id} : detail laporan (dekripsi saat ditampilkan), gambar bukti (disajikan lewat skrip PHP yang memeriksa login BK, bukan URL langsung), percakapan dengan pelapor, form balasan (disimpan terenkripsi sebagai pesan dari bk), ubah status (baru, ditinjau, ditindaklanjuti, selesai, dieskalasi), ubah urgensi, catatan internal terenkripsi. Setiap pembukaan halaman detail dicatat di access_log (user, laporan, aksi, waktu), tanpa isi.
5. Hapus permanen laporan berstatus Selesai (konfirmasi dua langkah, hapus juga messages, attachments, file fisik, internal_notes).
6. /bk/rekap : rekap per bulan (jumlah laporan per kategori, per status, rata-rata waktu dari laporan masuk sampai pertama kali dibalas), dengan gaya cetak yang rapi (CSS @media print).
7. Semua tampilan mengikuti DESIGN.md (badge, tabel, percakapan, tombol).

Uji: login benar dan salah, batas percobaan; BK melihat dan membalas laporan, balasan muncul di /status pelapor; filter bekerja; access_log terisi; admin tidak bisa membuka /bk/*; gambar bukti tidak bisa dibuka tanpa login; hapus permanen benar-benar menghapus file.
```

---

## Prompt 4: Sisi admin, instalasi, dan README

```
TAHAP 4: ADMIN, INSTALASI, DAN README. Tahap 1-3 sudah selesai. Kerjakan hanya ini.

1. /admin/akun : daftar akun BK; buat akun baru (username, password awal), nonaktifkan/aktifkan, reset password. Admin tidak boleh menghapus dirinya sendiri atau menonaktifkan admin terakhir.
2. /admin/kategori : tambah, ubah nama, nonaktifkan kategori jenis kejadian. Seed awal: Kekerasan fisik, Perundungan verbal, Perundungan online (siber), Pengucilan, Pemerasan atau pemalakan, Lainnya.
3. /admin/statistik : HANYA agregat: jumlah laporan per bulan, per kategori, per status. Tidak ada daftar laporan, tidak ada isi, tidak ada tautan ke detail laporan. Pastikan tidak ada query di area admin yang men-SELECT kolom *_enc.
4. /admin/backup : unduh salinan file database (isinya sudah terenkripsi). Wajib CSRF dan login admin.
5. install.php (di public/, jalan sekali): cek versi PHP dan ekstensi yang dibutuhkan, tulis config.php (generate kunci enkripsi 32 byte dan pepper acak), jalankan migrasi, seed kategori, buat akun admin pertama dari form, tolak berjalan jika config.php sudah ada, dan tampilkan instruksi menghapus install.php setelah selesai.
6. README.md: langkah deploy ke cPanel (upload, set document root ke public/ atau pakai .htaccess, izin folder data/), daftar ekstensi PHP, cara membuat akun BK pertama, cara backup, catatan keamanan (HTTPS wajib, jangan commit config.php, simpan cadangan kunci enkripsi terpisah karena tanpa kunci laporan tidak bisa dibaca).
7. Tampilan mengikuti DESIGN.md.

Uji: instal dari nol di folder bersih; buat akun BK lewat admin lalu login sebagai BK; coba akses semua rute admin sebagai BK (harus 403); pastikan install.php menolak dijalankan kedua kali.
```

---

## Prompt 5: Pengerasan dan verifikasi

```
TAHAP 5: PENGERASAN DAN VERIFIKASI. Tahap 1-4 sudah selesai. Jangan menambah fitur; periksa dan perbaiki.

Lakukan audit pada seluruh kode, lalu perbaiki temuan dan laporkan tabel temuan (masalah, file, perbaikan):
1. Keamanan: semua query memakai prepared statement; semua output di-escape; CSRF pada setiap form POST; header keamanan aktif di semua respons; cookie session sesuai aturan; tidak ada display_errors di produksi; error ditulis ke log di luar public/.
2. Privasi: telusuri semua tempat yang menulis ke database, log, atau file. Pastikan tidak ada IP, user agent, atau isi laporan dalam teks biasa. Pastikan log server aplikasi tidak mencetak isi laporan.
3. Upload: pastikan hanya gambar yang lolos, EXIF hilang, file tidak dapat dieksekusi, folder upload tidak dapat diakses lewat URL.
4. Akses: uji matriks peran (pelapor, BK, admin) pada setiap rute; admin tidak dapat membaca isi laporan dari rute apa pun.
5. Ketahanan: input kosong, input sangat panjang (batasi uraian maksimal 5000 karakter), karakter khusus/Unicode, upload ganda, tombol kirim ditekan dua kali (cegah duplikat laporan).
6. Kompatibilitas: berjalan di PHP 8.1 tanpa Composer; tidak memakai fungsi yang dinonaktifkan umumnya di shared hosting.
7. Tampilan: bandingkan setiap halaman dengan DESIGN.md (warna, radius, tanpa bayangan berlebihan, tanpa emoji), cek lebar 375px dan 1280px, kontras dan fokus keyboard.
8. Jalankan seluruh kriteria penerimaan di PRD.md bagian 11 satu per satu dan laporkan lolos/gagal beserta buktinya.

Keluaran akhir: daftar temuan dan perbaikan, hasil 8 kriteria penerimaan, dan daftar hal yang perlu dicek manual di hosting sebenarnya (HTTPS, versi PHP, izin folder).
```

---

## Prompt cadangan: perbaikan tampilan

Pakai bila hasil terlihat "terlalu AI" atau menyimpang dari desain.

```
Tinjau semua halaman terhadap DESIGN.md. Buang apa pun yang tidak ada di sana: gradasi, bayangan tebal, radius besar, emoji, ikon dekoratif, hero tengah, kartu fitur, animasi memantul, teks pemasaran. Ganti dengan elemen datar sesuai token warna dan komponen di DESIGN.md. Jangan mengubah logika atau fungsi. Tampilkan daftar perubahan per halaman.
```