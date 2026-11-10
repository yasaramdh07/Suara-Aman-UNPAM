<h1 class="sa-title">Privasi</h1>

<div class="sa-prose">
    <p>Halaman ini menjelaskan apa yang kami simpan, apa yang kami lindungi, siapa yang bisa membaca laporan kamu, dan apa arti kode tiket.</p>

    <h2 class="mt-4">Yang tidak kami simpan</h2>
    <ul class="sa-icon-list sa-icon-list-no">
        <li>
            <i class="bi bi-x-circle" aria-hidden="true"></i>
            <span>Nama kamu. Kamu hanya perlu menulis nama bila kamu sendiri ingin.</span>
        </li>
        <li>
            <i class="bi bi-x-circle" aria-hidden="true"></i>
            <span>Alamat IP, jenis HP, atau jenis browser kamu di data laporan dan pesan.</span>
        </li>
        <li>
            <i class="bi bi-x-circle" aria-hidden="true"></i>
            <span>Data tersembunyi di foto, seperti lokasi GPS dan jenis kamera. Data itu dibuang otomatis saat gambar diunggah.</span>
        </li>
        <li>
            <i class="bi bi-x-circle" aria-hidden="true"></i>
            <span>Pelacak, iklan, analitik, atau font dari pihak luar di situs ini.</span>
        </li>
    </ul>

    <h2 class="mt-4">Yang kami lindungi</h2>
    <ul class="sa-icon-list sa-icon-list-yes">
        <li>
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            <span>Isi cerita, lokasi kejadian, pihak terlibat, dan seluruh percakapan dienkripsi di dalam database.</span>
        </li>
        <li>
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            <span>Kode tiket hanya disimpan dalam bentuk acak satu arah (hash), sehingga hanya pemegang kode asli yang dapat mengecek balasan.</span>
        </li>
        <li>
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            <span>Gambar bukti disimpan di luar folder publik dan hanya dapat dibuka oleh Guru BK yang sudah masuk.</span>
        </li>
        <li>
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            <span>Setiap pembukaan laporan oleh Guru BK dicatat waktunya di log akses tanpa menyimpan ulang isi laporan.</span>
        </li>
    </ul>

    <p class="mt-3">Satu hal yang perlu kamu tahu: saat kamu memasukkan kode di halaman Cek status, kami menyimpan penanda acak (bukan alamat IP asli) paling lama 15 menit. Tujuannya menahan orang yang menebak-nebak kode. Penanda ini tidak terhubung ke laporan mana pun dan terhapus otomatis.</p>
    <p>Penyedia hosting mungkin mencatat kunjungan di log servernya sendiri. Aplikasi ini tidak membaca atau menyimpan log itu.</p>

    <h2 class="mt-4">Siapa yang bisa membaca laporan</h2>
    <p>Guru BK. Isi laporan, pesan, dan catatan disimpan dalam bentuk terenkripsi. Setiap kali BK membuka laporan, sistem mencatat siapa dan kapan, tanpa isi laporan.</p>
    <p>Pengelola sistem (admin) hanya mengatur akun dan melihat jumlah laporan. Admin tidak punya halaman untuk membaca isi laporan.</p>
    <p>Catatan jujur: pengembang yang memasang server secara teknis memegang kunci enkripsi. Perlindungan kamu adalah tidak adanya fitur baca isi laporan untuk admin, ditambah komitmen bahwa pengembang tidak akan membukanya.</p>

    <h2 class="mt-4">Arti kode tiket</h2>
    <p>Setelah mengirim laporan, kamu mendapat kode acak 12 karakter, misalnya <span class="sa-mono">ABCD-EFGH-JKLM</span>. Kode ini dibuat oleh sistem dan hanya tampil satu kali.</p>
    <ul>
        <li>Kode dipakai untuk melihat status laporan dan membalas pesan guru BK.</li>
        <li>Kami hanya menyimpan versi acak dari kode itu, jadi kami tidak bisa melihat atau memulihkannya.</li>
        <li>Bila kode hilang, kamu tidak bisa membuka laporan itu lagi. Guru BK tetap bisa memprosesnya. Kamu bisa membuat laporan baru.</li>
        <li>Jangan bagikan kode ke siapa pun. Siapa pun yang memegang kode bisa membaca percakapan.</li>
    </ul>

    <p class="mt-4">Memakai komputer bersama? Tutup halaman setelah selesai.</p>

    <div class="sa-actions">
        <a class="btn btn-primary" href="/lapor">
            <i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Buat laporan
        </a>
        <a class="sa-link" href="/status">
            <i class="bi bi-search me-1" aria-hidden="true"></i>Cek status laporan
        </a>
    </div>
</div>
