<?php
$folder_gambar = 'gambar/stok/'; // Folder penyimpanan gambar
$files = glob($folder_gambar . '*'); // Ambil semua file di folder
$sekarang = time(); // Waktu saat ini
$bulan_sebelumnya = 30 * 24 * 60 * 60; // 30 hari dalam detik

foreach ($files as $file) {
    if (is_file($file)) {
        $waktu_modifikasi = filemtime($file); // Ambil waktu terakhir file diubah
        if (($sekarang - $waktu_modifikasi) > $bulan_sebelumnya) {
            unlink($file); // Hapus file jika sudah lebih dari 30 hari
        }
    }
}
?>
