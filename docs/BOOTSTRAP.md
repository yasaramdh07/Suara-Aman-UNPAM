# Memasang Bootstrap lokal

Bootstrap **tidak** dimuat dari CDN. Unduh sekali, lalu unggah bersama proyek. Versi yang dipakai: **5.3.x** (token di `theme.css` memakai variabel CSS Bootstrap 5.3).

Jalankan dari root proyek (Windows PowerShell memakai `curl.exe`, bukan alias `curl`):

```
curl.exe -L -o public/assets/bootstrap/bootstrap.min.css https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css
curl.exe -L -o public/assets/bootstrap/bootstrap.bundle.min.js https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js
```

Atau unduh `bootstrap-5.3.3-dist.zip` dari https://github.com/twbs/bootstrap/releases/tag/v5.3.3 dan salin dua berkas yang sama:

- `css/bootstrap.min.css` ke `public/assets/bootstrap/bootstrap.min.css`
- `js/bootstrap.bundle.min.js` ke `public/assets/bootstrap/bootstrap.bundle.min.js`

Layout tahap 1 hanya memuat CSS. File JS disiapkan untuk menu BK yang melipat (tahap 3).

