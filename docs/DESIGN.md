# DESIGN.md: Suara Aman

Panduan tampilan untuk seluruh halaman Suara Aman (pelapor, guru BK, admin). Baca file ini sebelum menulis HTML/CSS apa pun dan patuhi tanpa menambah gaya sendiri.

## 1. Karakter

Tenang, bersih, dan dapat dipercaya. Pengguna utama adalah siswa yang mungkin sedang takut atau cemas, jadi tampilan harus terasa **sederhana dan aman**, bukan ramai atau main-main. Rasanya seperti formulir resmi yang ramah, bukan landing page startup.

Prinsip:

1. **Datar dan lega.** Banyak ruang kosong, sedikit elemen, satu tindakan utama per halaman.
2. **Isi nyata, bukan hiasan.** Tidak ada ilustrasi dekoratif, gradasi, atau ikon yang tidak punya fungsi.
3. **Tegas di hal penting.** Kode tiket, status, dan urgensi harus langsung terbaca.
4. **Bahasa biasa.** Kalimat pendek dan jelas, tanpa seruan, tanpa slogan.

## 2. Warna

Proporsi: sekitar 60% putih, 30% biru muda (latar dan permukaan), 10% aksen (biru utama dan kuning-hijau).

| Token | Hex | Pemakaian |
|---|---|---|
| `--sa-bg` | `#FFFFFF` | Latar halaman |
| `--sa-tint` | `#F3F8FC` | Latar seksi, header tabel, kolom samping |
| `--sa-blue-100` | `#E8F3FA` | Latar badge, hover baris, kotak info |
| `--sa-blue-200` | `#BFDDF0` | Garis penekanan, border aktif |
| `--sa-blue-500` | `#5BA8D6` | Elemen dekoratif kecil (garis, penanda) |
| `--sa-blue-600` | `#2B77A8` | Tombol utama, tautan, fokus (kontras putih 4,8:1) |
| `--sa-blue-700` | `#1D5A83` | Hover tombol utama, teks pada latar biru muda |
| `--sa-lime-400` | `#BFD84A` | Aksen kuning-hijau: penanda, indikator terpilih |
| `--sa-lime-100` | `#F4F8DC` | Latar kotak kode tiket, sorotan lembut |
| `--sa-green-700` | `#3F6B1F` | Teks di atas aksen hijau, status berhasil |
| `--sa-yellow-300` | `#F4D35E` | Urgensi Sedang, peringatan |
| `--sa-yellow-100` | `#FBF3C9` | Latar peringatan lembut |
| `--sa-ink` | `#1E2A33` | Teks utama |
| `--sa-muted` | `#5A6B78` | Teks sekunder, label, keterangan |
| `--sa-line` | `#DCE6EE` | Border dan pemisah |
| `--sa-danger` | `#B8472F` | **Hanya** urgensi Mendesak dan galat |

Aturan warna:

- Aksen kuning-hijau dipakai hemat: garis kecil di bawah judul, status Selesai/Ditindaklanjuti, kotak kode tiket, penanda menu aktif. Jangan dipakai sebagai latar besar.
- `--sa-danger` sengaja di luar palet karena urgensi harus tidak salah dibaca. Jangan dipakai untuk dekorasi.
- Jangan ada ungu, nila, atau gradasi apa pun.
- Teks gelap di atas lime, teks putih di atas biru 600 atau lebih gelap. Kontras minimum 4,5:1.

## 3. Tipografi

Tanpa font eksternal (tidak boleh CDN). Pakai font sistem:

```css
--sa-font: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
--sa-mono: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
```

| Elemen | Ukuran | Berat | Line-height |
|---|---|---|---|
| H1 | 28px (24px di HP) | 600 | 1,25 |
| H2 | 20px | 600 | 1,3 |
| H3 | 16px | 600 | 1,4 |
| Isi | 16px | 400 | 1,6 |
| Kecil/label | 14px | 400-500 | 1,5 |
| Kode tiket | 24px mono | 500 | 1,2, letter-spacing 0,08em |

Teks rata kiri. Lebar baris isi maksimal 65 karakter. Tidak ada huruf kapital semua kecuali pada kode tiket.

## 4. Jarak, bentuk, dan efek

- Skala jarak (kelipatan 4px): 4, 8, 12, 16, 24, 32, 48, 64.
- Padding seksi: 48px (desktop), 32px (HP). Jarak antar field form: 20px.
- Radius: **6px** untuk tombol, input, badge; **8px** untuk kartu umum; **10–12px** untuk ubin/kartu interaktif di halaman pelapor dan chip ikon (`10px`). Tidak ada radius 16px ke atas, tidak ada bentuk pil kecuali badge status.
- Pemisah dengan **border 1px `--sa-line`**, bukan bayangan tebal. Bayangan ringan saat hover ubin pelapor: `0 2px 8px rgba(30,42,51,0.06)`; bayangan untuk dropdown/modal: `0 4px 16px rgba(30,42,51,0.08)`.
- Tanpa gradasi, glassmorphism, blur, atau latar bergambar.
- Animasi: transisi halus 120–160ms. Hormati `prefers-reduced-motion`.

## 5. Tata letak

- **Halaman pelapor:** satu kolom, lebar maksimal 640px, rata kiri, dengan margin kiri-kanan yang lega. Bukan hero di tengah.
- **Dashboard BK dan admin:** header tipis (tinggi 56px), konten lebar maksimal 1100px, tabel padat tapi lega, daftar di kiri dan detail di halaman terpisah (tanpa panel melayang).
- HP lebih dulu: semua tombol dan input minimal tinggi 44px. Navigasi BK berupa menu sederhana yang melipat di layar kecil.

## 6. Komponen

**Header.** Latar putih, border bawah 1px. Wordmark teks "Suara Aman": "Suara" berat 600 warna ink, "Aman" berat 600 warna `--sa-blue-600`, dengan titik kecil lime di ujung. Tanpa logo gambar.

**Tombol.**
- Utama: latar `--sa-blue-600`, teks putih, tinggi 44px, padding 0 20px, hover `--sa-blue-700`.
- Sekunder: latar putih, border 1px `--sa-blue-600`, teks `--sa-blue-600`.
- Berbahaya (hapus): teks dan border `--sa-danger`, hanya terisi saat hover.
- Satu tombol utama per layar.

**Input.** Border 1px `--sa-line`, radius 6px, tinggi 44px, latar putih. Fokus: border `--sa-blue-600` dan ring 2px `--sa-blue-200`. Label di atas input, 14px, berat 500. Teks bantuan 14px `--sa-muted` di bawah input. Pesan galat `--sa-danger` dengan teks jelas, bukan hanya warna.

**Kotak kode tiket** (halaman sukses): latar `--sa-lime-100`, border 1px dashed `--sa-lime-400`, radius 8px, padding 24px, kode di tengah memakai font mono 24px. Di bawahnya tombol sekunder "Salin kode" dan teks peringatan 14px: kode hanya tampil sekali.

**Badge status** (pill kecil, 13px, berat 500, padding 2px 10px):

| Status | Latar | Teks |
|---|---|---|
| Baru | `#E8F3FA` | `#1D5A83` |
| Ditinjau | `#FBF3C9` | `#6B5A0A` |
| Ditindaklanjuti | `#F4F8DC` | `#4A5E0F` |
| Selesai | `#DDEFD3` | `#2F5A1A` |
| Dieskalasi | `#E3E8EE` | `#33414D` |

**Badge urgensi:** Ringan = outline `--sa-blue-200` teks `--sa-blue-700`; Sedang = latar `--sa-yellow-300` teks `#3D3200`; Mendesak = latar `--sa-danger` teks putih, ditambah ikon segitiga peringatan kecil (agar tidak hanya bergantung warna).

**Tabel.** Header latar `--sa-tint`, teks 14px berat 600 `--sa-muted`. Baris dipisah border 1px, hover latar `--sa-blue-100`. Tanpa zebra stripe. Pada HP berubah menjadi daftar kartu sederhana.

**Percakapan (cek status).** Dua jenis gelembung datar tanpa ekor: pesan pelapor di kanan latar `--sa-blue-100`, pesan BK di kiri latar `--sa-tint` dengan border 1px. Tampilkan waktu 13px `--sa-muted`.

**Pesan sistem/peringatan.** Kotak dengan garis kiri 3px (biru untuk info, kuning untuk peringatan, danger untuk galat), latar tint lembut, tanpa ikon besar.

**Ikon.** Gunakan **Bootstrap Icons** yang di-host **lokal** di `/assets/vendor/bootstrap-icons/` (`bootstrap-icons.min.css` dan `fonts/bootstrap-icons.woff2`, tanpa CDN dan tanpa file `.woff`). Gunakan satu keluarga ikon (**varian outline**), ukuran `18–24px`, dengan warna mengikuti teks atau palet. Untuk ikon penjelas berukuran besar, tempatkan di dalam **chip** berukuran `40–44px` dengan radius `10px`, latar `--sa-blue-100` (ikon `--sa-blue-700`) atau `--sa-lime-100` (ikon `--sa-green-700`). Tanpa emoji.

**Keadaan kosong.** Satu kalimat polos, misal "Belum ada laporan." Tanpa ilustrasi.

## 7. Nada tulisan (copywriting)

- Bahasa Indonesia sederhana, langsung, dan sopan. Contoh judul: "Buat laporan", "Cek status laporan", bukan "Ayo lapor sekarang!".
- Jelaskan jaminan privasi dengan fakta: "Kami tidak menyimpan nama, alamat IP, atau data perangkat kamu."
- Tombol memakai kata kerja: "Kirim laporan", "Salin kode", "Simpan perubahan".
- Tanpa seruan berlebihan, tanpa emoji, tanpa kata promosi.

## 8. Hal yang harus dihindari (supaya tidak terlihat buatan AI)

- Gradasi ungu/nila, blob warna, latar dengan mesh gradient.
- Kartu dengan radius besar (16px ke atas) dan bayangan tebal di mana-mana.
- Hero tengah dengan judul besar, subjudul, dua tombol, lalu grid tiga kartu fitur berikon.
- Bintang percikan, roket, atau emoji sebagai dekorasi (ikon hanya dari Bootstrap Icons varian outline yang punya fungsi memperjelas konteks).
- Pola "glass" (latar transparan buram) dan animasi memantul.
- Teks pemasaran generik ("Solusi modern untuk sekolahmu").
- Semua elemen rata tengah. Gunakan rata kiri dan hierarki yang jelas.

## 9. Aksesibilitas

- Kontras teks minimal 4,5:1 (3:1 untuk teks besar).
- Fokus keyboard selalu terlihat: outline 2px `--sa-blue-600` dengan offset 2px.
- Setiap input punya `<label>`; galat dikaitkan dengan `aria-describedby`.
- Status dan urgensi tidak hanya dibedakan lewat warna (ada teks, dan ikon untuk Mendesak).
- Target sentuh minimal 44x44px.

## 10. Token CSS (taruh di `public/assets/theme.css`, dimuat setelah Bootstrap)

```css
:root {
  --sa-bg: #FFFFFF;
  --sa-tint: #F3F8FC;
  --sa-blue-100: #E8F3FA;
  --sa-blue-200: #BFDDF0;
  --sa-blue-500: #5BA8D6;
  --sa-blue-600: #2B77A8;
  --sa-blue-700: #1D5A83;
  --sa-lime-100: #F4F8DC;
  --sa-lime-400: #BFD84A;
  --sa-green-700: #3F6B1F;
  --sa-yellow-100: #FBF3C9;
  --sa-yellow-300: #F4D35E;
  --sa-ink: #1E2A33;
  --sa-muted: #5A6B78;
  --sa-line: #DCE6EE;
  --sa-danger: #B8472F;
  --sa-font: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  --sa-mono: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;

  /* Override Bootstrap 5 */
  --bs-body-font-family: var(--sa-font);
  --bs-body-color: var(--sa-ink);
  --bs-body-bg: var(--sa-bg);
  --bs-primary: #2B77A8;
  --bs-primary-rgb: 43, 119, 168;
  --bs-link-color: var(--sa-blue-600);
  --bs-link-hover-color: var(--sa-blue-700);
  --bs-border-color: var(--sa-line);
  --bs-border-radius: 6px;
  --bs-border-radius-lg: 8px;
}

body { font-size: 16px; line-height: 1.6; }

.btn { min-height: 44px; padding: 0 20px; border-radius: 6px; font-weight: 500; box-shadow: none; }
.btn-primary { --bs-btn-bg: #2B77A8; --bs-btn-border-color: #2B77A8;
  --bs-btn-hover-bg: #1D5A83; --bs-btn-hover-border-color: #1D5A83;
  --bs-btn-active-bg: #1D5A83; --bs-btn-active-border-color: #1D5A83; }
.btn-outline-primary { --bs-btn-color: #2B77A8; --bs-btn-border-color: #2B77A8;
  --bs-btn-hover-bg: #E8F3FA; --bs-btn-hover-color: #1D5A83; --bs-btn-hover-border-color: #2B77A8; }

.form-control, .form-select { min-height: 44px; border-color: var(--sa-line); border-radius: 6px; box-shadow: none; }
.form-control:focus, .form-select:focus { border-color: var(--sa-blue-600); box-shadow: 0 0 0 2px var(--sa-blue-200); }
.form-label { font-size: 14px; font-weight: 500; }

.card { border: 1px solid var(--sa-line); border-radius: 8px; box-shadow: none; }
.table > :not(caption) > * > * { padding: 12px 14px; }
.table thead th { background: var(--sa-tint); color: var(--sa-muted); font-size: 14px; font-weight: 600; }
.table-hover > tbody > tr:hover > * { background: var(--sa-blue-100); }

.ticket-box { background: var(--sa-lime-100); border: 1px dashed var(--sa-lime-400); border-radius: 8px;
  padding: 24px; text-align: center; font-family: var(--sa-mono); font-size: 24px; letter-spacing: .08em; }

a:focus-visible, button:focus-visible { outline: 2px solid var(--sa-blue-600); outline-offset: 2px; }
@media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
```