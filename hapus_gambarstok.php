<?php
$folder_gambar = 'gambar/stok/'; // Folder penyimpanan gambar

// Ambil semua file di folder
$files = glob($folder_gambar . '*');

// Waktu sekarang
$sekarang = time();

// Loop melalui setiap file
foreach ($files as $file) {
    if (is_file($file)) {
        // Ambil nama file tanpa path
        $nama_file = basename($file);

        // Ekstrak tanggal dari nama file (format: YYYYMMDD)
        if (preg_match('/\d{8}/', $nama_file, $matches)) {
            $tanggal_file = $matches[0]; // Ambil tanggal dari nama file

            // Konversi tanggal file ke format timestamp
            $tahun = substr($tanggal_file, 0, 4);
            $bulan = substr($tanggal_file, 4, 2);
            $hari = substr($tanggal_file, 6, 2);
            $waktu_file = strtotime("$tahun-$bulan-$hari");

            // Hitung selisih waktu (dalam detik)
            $selisih_waktu = $sekarang - $waktu_file;

            // 30 hari dalam detik
            $batas_waktu = 30 * 24 * 60 * 60;

            // Jika file lebih tua dari 30 hari, hapus file
            if ($selisih_waktu > $batas_waktu) {
                if (unlink($file)) {
                    echo "File $nama_file berhasil dihapus.<br>";
                } else {
                    echo "Gagal menghapus file $nama_file. Periksa izin file.<br>";
                }
            }
        } else {
            echo "File $nama_file tidak memiliki format tanggal yang valid.<br>";
        }
    }
}
?>