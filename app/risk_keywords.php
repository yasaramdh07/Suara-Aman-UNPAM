<?php
declare(strict_types=1);

/**
 * Kata kunci risiko (huruf kecil, tanpa tanda baca). Teks laporan dinormalisasi
 * dulu, lalu dicocokkan sebagai potongan kata. Kecocokan hanya MENGUSULKAN
 * urgensi "Mendesak" dan memberi penanda; BK tetap yang memutuskan.
 */
return [
    // Menyakiti diri sendiri
    'bunuh diri', 'ingin mati', 'mau mati', 'pengen mati', 'pengin mati', 'lebih baik mati',
    'akhiri hidup', 'mengakhiri hidup', 'menyakiti diri', 'melukai diri', 'self harm',
    'menyayat', 'gantung diri', 'overdosis', 'loncat dari',

    // Ancaman menyakiti atau membunuh orang lain, senjata
    'bunuh', 'senjata', 'pisau', 'golok', 'celurit', 'silet', 'cutter',

    // Kekerasan fisik berat
    'berdarah', 'pendarahan', 'pingsan', 'patah tulang', 'keroyok', 'cekik',
    'dihajar', 'babak belur', 'gegar otak', 'rumah sakit',

    // Kekerasan atau pelecehan seksual
    'perkosa', 'pelecehan seksual', 'cabul', 'dipaksa telanjang', 'foto telanjang', 'video telanjang',
];

