<?php
// Koneksi ke database
include 'koneksi.php';
session_start(); 

if (!isset($_SESSION["username"])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION["username"];

// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['username'] != 'admin') {
    header('Location: dashboard.php'); // Redirect jika pengguna bukan admin
    exit;
}

// Ambil data dari tabel menu untuk dropdown
$query_menu = "SELECT idmenu, nama FROM menu ORDER BY idmenu DESC";
$result_menu = mysqli_query($conn, $query_menu);

// Cek apakah ada parameter idmenu yang dikirim (untuk pencarian)
if (isset($_GET['idmenu'])) {
    $idmenu = $_GET['idmenu'];

    // Path folder gambar
    $folder_gambar = 'gambar/stok/';

    // Cari semua file gambar yang sesuai dengan idmenu
    $files = glob($folder_gambar . $idmenu . '_*.*');

    // Hapus file yang lebih dari 30 hari berdasarkan nama file
    foreach ($files as $file) {
        $nama_file = basename($file);
        $info = explode('_', $nama_file);

        if (count($info) >= 4) {
            // Ambil bagian tanggal dari nama file (DDMMYYYY)
            $tanggal_string = pathinfo($info[3], PATHINFO_FILENAME); // Ambil tanpa ekstensi
            if (strlen($tanggal_string) >= 8) {
                $tanggal_file = substr($tanggal_string, 4, 4) . '-' . substr($tanggal_string, 2, 2) . '-' . substr($tanggal_string, 0, 2);
                
                // Konversi ke timestamp
                $tanggal_file_timestamp = strtotime($tanggal_file);
                $sekarang_timestamp = time();

                // Hitung selisih hari
                $selisih_hari = ($sekarang_timestamp - $tanggal_file_timestamp) / (60 * 60 * 24);

                if ($selisih_hari > 30) {
                    unlink($file); // Hapus file
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>history Stok</title>
    <link rel="stylesheet" href="w3.css"> <!-- Adjust to your CSS location -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="icon" type="image/png" href="jujurmart.png">
    <style>
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        /* Sidebar styling */
        .w3-sidebar {
            z-index: 1100;
            position: fixed;
            left: -250px;
            width: 250px;
            height: 100%;
            overflow-x: hidden;
            transition: 0.5s;
            padding-top: 0;
        }

        .w3-sidebar.show {
            left: 0;
        }

        .w3-sidebar.hide {
            left: -250px;
        }

        .w3-sidebar a {
            padding: 10px;
            text-decoration: none;
            font-size: 18px;
            color: black;
            display: block;
        }

        .w3-sidebar a:hover {
            background-color: #ddd;
        }

        .w3-sidebar .close-button {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            padding: 15px;
            background-color: #f44336;
            color: white;
            font-size: 20px;
            text-align: center;
            border: none;
            cursor: pointer;
        }

        /* Sidebar overlay styling */
        .w3-sidebar-overlay {
            display: none;
            position: fixed;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .w3-sidebar-overlay.show {
            display: block;
        }
        .search-container {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        .search-box {
            width: 100%;
            max-width: 600px;
            display: flex;
            position: relative;
        }
        .search-box select {
            width: 100%;
            padding: 12px 20px;
            padding-right: 60px;
            border-radius: 50px;
            border: 2px solid #ddd;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            font-size: 16px;
            appearance: none;
        }
        .search-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            height: 40px;
            width: 40px;
            border-radius: 50%;
            border: none;
            background-color: #4caf50;
            color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        /* Media query untuk lebar layar maksimal 600px */
        @media (max-width: 600px) {
        .search-box {
        width: 90%; 
        }
        }
    </style>
</head>
<body>
      <!-- Sidebar Overlay -->
      <div id="sidebarOverlay" class="w3-sidebar-overlay" onclick="w3_close()"></div>

<!-- Sidebar -->
<div class="w3-sidebar w3-bar-block w3-border-right w3-light-grey" id="mySidebar">
<button onclick="w3_close()" class="w3-bar-item w3-button w3-red w3-center close-button">
<b>Close</b> <i class="fa fa-close" style="font-size:20px; margin-left:5px;"></i>
</button>
<a href="dashboard.php" class="w3-bar-item w3-button w3-border w3-hover-green">
    <i class="fas fa-chart-bar"></i> <span class="menu-text">Dashboard</span>
</a>
<a href="list_menu.php" class="w3-bar-item w3-button w3-border w3-hover-green">
    <i class="fas fa-utensils"></i> <span class="menu-text">List Menu</span>
</a>
<?php if ($_SESSION['username'] == 'admin') { ?>
<a href="#" class="w3-bar-item w3-button w3-border w3-hover-green">
    <i class="fas fa-history"></i> <span class="menu-text">History Stok</span>
</a>
<?php } ?>
<a href="list_penjualan.php" class="w3-bar-item w3-button w3-border w3-hover-green">
    <i class="fas fa-clipboard-list"></i> <span class="menu-text">List Penjualan</span>
</a>
<?php if ($_SESSION['username'] == 'admin') { ?>
    <a href="list_pengguna.php" class="w3-bar-item w3-button w3-border w3-hover-green">
        <i class="fas fa-users"></i> <span class="menu-text">List Pengguna</span>
    </a>
<?php } ?>
<a href="logout.php" class="w3-bar-item w3-button w3-red w3-center">
<b>Log Out </b> <i class="fas fa-sign-out-alt" style="font-size:20px"></i>
</a>
</div>
<!-- End Sidebar -->

   <!-- Header -->
   <div class="w3-green sticky-header" style="display: flex; align-items: center;">
        <button class="w3-button w3-xlarge" onclick="w3_open()">☰</button>
        <div style="flex-grow: 1; display: flex; justify-content: center;">
            <h1 style="margin: 0; line-height: 3.5rem; margin-bottom:10px;"><b>History Stok</b></h1>
        </div>
    </div>

<br>
<div class="search-container">
    <div class="search-box">
        <select id="searchPenjualan" class="w3-input w3-border" required>
            <option value="" disabled selected>Pilih Menu</option>
            <?php
            // Mendapatkan idmenu dari URL
            $idmenu_selected = isset($_GET['idmenu']) ? $_GET['idmenu'] : '';

            // Tampilkan opsi select dari data menu
            while ($row = mysqli_fetch_assoc($result_menu)) {
                $selected = ($row['idmenu'] == $idmenu_selected) ? 'selected' : '';
                echo "<option value='" . $row['idmenu'] . "' $selected>" . $row['nama'] . "</option>";
            }
            ?>
        </select>
        <button type="button" id="search-btn" class="search-btn">
            <i class="fa fa-search" style="font-size: 18px;"></i>
        </button>
    </div>
</div>

<!-- Tempat untuk menampilkan hasil pencarian -->
<div id="hasil-pencarian" class="w3-center" style="margin-top: 20px;">
    <?php
    // Pastikan menu telah dipilih sebelum menampilkan hasil
    if (!empty($idmenu_selected)) {

        // Tampilkan hasil pencarian jika ada
        if (isset($files) && count($files) > 0) {

            // Loop melalui setiap file
            foreach ($files as $file) {
                // Periksa apakah file masih ada sebelum ditampilkan
                if (!file_exists($file)) {
                    continue; // Lewati jika file sudah dihapus
                }

                // Ambil nama file tanpa path
                $nama_file = basename($file);

                // Ekstrak informasi dari nama file
                // Format: idmenu_stok_HH-MM-SS_DDMMYYYY.extension
                $info = explode('_', $nama_file);
                if (count($info) >= 4) {
                    $stok = $info[1]; // Ambil stok

                    // Ambil waktu dan tanggal
                    $waktu_tanggal = explode('.', $info[3]); // Pisahkan ekstensi file
                    if (count($waktu_tanggal) >= 2) {
                        // Format waktu: HH-MM-SS
                        $waktu = implode(':', array_slice(explode('-', $info[2]), 0, 3));

                        // Format tanggal: DD-MM-YYYY
                        $tanggal = substr($info[3], 0, 2) . "-" . substr($info[3], 2, 2) . "-" . substr($info[3], 4, 4);

                        // Tampilkan gambar dan informasi stok
                        echo "<div class='w3-margin-bottom'>";
                        echo "<img src='$folder_gambar$nama_file' alt='Gambar Stok' class='w3-image' style='width: 250px;'><br>"; 
                        echo "<p>Stok: $stok</p>";
                        echo "<p>Waktu: $waktu $tanggal</p>";
                        echo "</div>";
                    }
                }
            }
        } else {
            // Hanya tampilkan pesan jika menu telah dipilih
            echo "<p><i class='fas fa-database'></i> History stok kosong. Silahkan pilih menu lain.</p>";
        }
    }
    ?>
</div>
<script>
       function w3_open() {
            document.getElementById("mySidebar").classList.add("show");
            document.getElementById("sidebarOverlay").classList.add("show");
        }

        function w3_close() {
            document.getElementById("mySidebar").classList.remove("show");
            document.getElementById("sidebarOverlay").classList.remove("show");
        }
   document.getElementById('search-btn').addEventListener('click', function() {
    searchMenu();
});

document.getElementById('searchPenjualan').addEventListener('keypress', function(event) {
    if (event.key === "Enter" || event.keyCode === 13) {
        event.preventDefault(); // Mencegah submit default
        searchMenu();
    }
});

function searchMenu() {
    var idmenu = document.getElementById('searchPenjualan').value;
    if (idmenu) {
        window.location.href = 'history_stok.php?idmenu=' + idmenu;
    } else {
        alert("Silakan pilih menu terlebih dahulu.");
    }
}
</script>
</body>
</html>
