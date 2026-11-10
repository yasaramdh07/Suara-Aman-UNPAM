# Suara Aman — MA YABIKA

Aplikasi web pengaduan perundungan (*bullying*) anonim untuk siswa MA YABIKA. Siswa (korban atau saksi) dapat melapor tanpa akun/login kepada Guru BK dan menerima kode tiket acak sekali tampil untuk mengecek status serta membalas pesan Guru BK secara dua arah tanpa membuka identitas.

---

## 1. Prasyarat Server (Shared Hosting / cPanel)

- **PHP 8.1** atau lebih baru (tanpa Composer, tanpa framework, tanpa build step).
- **Ekstensi PHP wajib:**
  - `pdo_sqlite` (database SQLite satu berkas, mode WAL)
  - `sodium` (enkripsi `sodium_crypto_secretbox`)
  - `gd` (re-encode gambar bukti untuk membuang EXIF dan metadata)
  - `mbstring` (pemrosesan teks UTF-8)
  - `fileinfo` (validasi MIME asli berkas unggahan)
- **Web server:** Apache / LiteSpeed dengan `mod_rewrite` aktif dan **HTTPS wajib**.

---

## 2. Struktur Folder

```text
SUARA-AMAN/
├── app/                # Logika aplikasi & template tampilan (dilindungi .htaccess)
├── data/               # Database SQLite, uploads, sessions, logs (dilindungi .htaccess)
├── public/             # Document root web server
│   ├── assets/         # Bootstrap 5 lokal, theme.css, app.css, app.js
│   ├── index.php       # Titik masuk utama aplikasi
│   ├── attachment.php  # Penyaji gambar bukti (khusus BK yang login)
│   └── install.php     # Skrip instalasi sekali jalan (HAPUS setelah selesai)
├── config.example.php  # Contoh struktur konfigurasi
└── config.php          # Dibuat otomatis oleh install.php (di luar public/)
```

---

## 3. Daftar Rute Aplikasi

| Rute | Akses | Fungsi |
|---|---|---|
| `/` | Publik | Beranda, tombol lapor/cek status, dan materi edukasi (`#jenis`, `#pancasila`, `#hukum`) |
| `/lapor` | Publik | Formulir laporan anonim |
| `/lapor/sukses` | Publik (sekali tampil) | Menampilkan kode tiket laporan |
| `/status` | Publik | Cek status laporan dan percakapan dua arah dengan Guru BK |
| `/privasi` | Publik | Penjelasan jaminan privasi untuk siswa |
| `/penyusun` | Publik | Daftar penyusun proyek Mata Kuliah Pendidikan Pancasila |
| `/bk/login` | Publik | Halaman masuk Guru BK dan Admin |
| `/bk/logout` | BK / Admin (POST) | Keluar dari sesi login |
| `/bk/laporan` | Guru BK | Daftar laporan dan filter |
| `/bk/laporan/{id}` | Guru BK | Detail laporan, balasan, status, urgensi, catatan internal, hapus permanen |
| `/bk/rekap` | Guru BK | Rekap bulanan dan tampilan cetak |
| `/akun` | Guru BK / Admin | Ubah username dan kata sandi akun sendiri |
| `/admin/akun` | Admin | Kelola akun Guru BK dan Admin |
| `/admin/kategori` | Admin | Kelola kategori jenis kejadian |
| `/admin/statistik` | Admin | Statistik agregat (tanpa isi laporan) |
| `/admin/backup` | Admin | Unduh salinan berkas database SQLite |
| `/admin/reset` | Admin | Factory reset seluruh data operasional dengan verifikasi berlapis |

---

## 4. Langkah Deploy ke cPanel

### A. Unggah Berkas
1. Unggah seluruh isi folder proyek ke server (misalnya ke `/home/username/suara-aman/`).
2. Pastikan berkas `config.php` lokal **tidak** ikut diunggah (biarkan `install.php` yang membuatnya di server).

### B. Arahkan Document Root ke `public/`
- **Cara terbaik (Subdomain / Addon Domain di cPanel):**
  Atur *Document Root* domain/subdomain langsung ke folder `/home/username/suara-aman/public`. Dengan cara ini, folder `app/`, `data/`, dan `config.php` berada sepenuhnya di luar document root.
- **Alternatif (bila memakai `public_html` utama dan tidak bisa mengubah Document Root):**
  Taruh isi proyek di dalam subfolder atau arahkan trafik ke `public/` dengan `.htaccess` di root, serta pastikan `.htaccess` (`Require all denied` / `Deny from all`) di dalam `app/` dan `data/` aktif. Uji bahwa membuka `https://domain-sekolah.sch.id/data/suara-aman.sqlite` menghasilkan `403 Forbidden` atau `404 Not Found`.

### C. Izin Folder (`Permissions`)
- Pastikan folder proyek dan `data/` dapat ditulis oleh proses PHP (`chmod 0750` atau `0700` untuk `data/`, `data/uploads/`, `data/sessions/`, `data/logs/`).
- Setelah instalasi, pastikan `config.php` memiliki izin ketat (`chmod 0600`).

### D. Jalankan `install.php` Sekali
1. Buka `https://domain-sekolah.sch.id/install.php` lewat browser.
2. Pastikan seluruh pemeriksaan versi PHP dan 5 ekstensi berstatus **Siap**.
3. Isi username, kata sandi untuk **akun Admin pertama**, serta **PIN reset 6 digit**, lalu klik **Pasang Suara Aman**. (Jika aplikasi sudah terpasang sebelumnya, hash PIN reset dapat diatur melalui CLI dengan menjalankan `php tools/set_reset_pin.php`, lalu hapus skrip tersebut).
4. Sistem secara otomatis:
   - Menggenerate kunci enkripsi 32 byte (`encryption_key`), `pepper` acak, dan menyimpan hash PIN reset (`reset_pin_hash`) ke `config.php`.
   - Membuat database SQLite (`data/suara-aman.sqlite`) dan menjalankan migrasi skema.
   - Mengisi 6 kategori awal (*Kekerasan fisik*, *Perundungan verbal*, *Perundungan online (siber)*, *Pengucilan*, *Pemerasan atau pemalakan*, *Lainnya*).
   - Mendaftarkan akun Admin pertama.
5. **WAJIB:** Segera hapus berkas `public/install.php` dari server setelah instalasi selesai.

---

## 5. Membuat Akun Guru BK Pertama

1. Buka `/bk/login` dan masuk menggunakan akun **Admin** yang dibuat saat instalasi.
2. Admin akan diarahkan ke halaman `/admin/statistik`.
3. Buka menu **Akun BK** (`/admin/akun`).
4. Pada formulir **Buat akun baru**, isi:
   - **Username** untuk Guru BK
   - **Kata sandi awal** (minimal 8 karakter)
   - **Peran:** pilih `Guru BK`
5. Klik **Buat akun**, lalu berikan kredensial tersebut kepada Guru BK.
6. Guru BK kini dapat masuk melalui `/bk/login` untuk mengakses `/bk/laporan` dan `/bk/rekap`.

---

## 6. Cara Backup dan Pemulihan

1. Masuk sebagai **Admin** di `/bk/login`, lalu buka menu **Backup** (`/admin/backup`).
2. Klik **Unduh database (.sqlite)** untuk mengunduh salinan utuh berkas database SQLite (dibuat secara konsisten menggunakan `VACUUM INTO`).
3. Untuk cadangan penuh (termasuk foto bukti), unduh juga isi folder `data/uploads/` secara berkala melalui File Manager cPanel.
4. **Simpan cadangan `config.php` di tempat terpisah yang aman.**

---

## 7. Catatan Keamanan dan Privasi Penting

1. **Wajib HTTPS:** Aktifkan SSL/TLS (mis. AutoSSL / Let's Encrypt di cPanel) agar pengiriman laporan, kode tiket, dan cookie sesi terlindungi.
2. **Jangan pernah commit `config.php` ke Git:** Berkas `config.php` memuat `encryption_key` dan `pepper`. Sudah dimasukkan ke `.gitignore`.
3. **Cadangkan kunci enkripsi (`config.php`) secara terpisah:** Seluruh isi laporan, lokasi, pihak terlibat, pesan percakapan, dan catatan internal di database dienkripsi dengan `sodium_crypto_secretbox`. **Tanpa `encryption_key` di `config.php`, seluruh isi laporan di database maupun di file backup tidak akan pernah bisa dibaca kembali.**
4. **Pemisahan peran ketat:** Admin hanya dapat mengelola akun, kategori, statistik agregat, dan backup; Admin tidak dapat membaca isi laporan atau membuka halaman `/bk/*`.
5. **Tanpa jejak identitas pelapor:** Sistem tidak menyimpan alamat IP mentah, user agent, atau identitas perangkat pada laporan maupun pesan; waktu laporan dibulatkan ke jam; dan setiap foto bukti di-reencode lewat GD untuk menghapus metadata EXIF/GPS.

