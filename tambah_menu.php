<?php
include 'koneksi.php'; // Include database connection file
date_default_timezone_set('Asia/Jakarta'); // Set the timezone

session_start(); // Start session for user data access

if (!isset($_SESSION["username"])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'];
$query = "SELECT * FROM pengguna WHERE username = '" . mysqli_real_escape_string($conn, $username) . "'";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query error: " . mysqli_error($conn));
}

if (mysqli_num_rows($result) > 0) {
    $pengguna = mysqli_fetch_assoc($result);
    $user_record = $pengguna['username'];
} else {
    $user_record = '';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'tambah') {
    // Ambil data dari form
    $id_menu = mysqli_real_escape_string($conn, $_POST['idmenu']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $jenis = mysqli_real_escape_string($conn, $_POST['jenis']);
    $harga = mysqli_real_escape_string($conn, $_POST['harga']);
    $user_record = mysqli_real_escape_string($conn, $_POST['userrecord']);
    $user_modified = mysqli_real_escape_string($conn, $_POST['usermodified']);
    $now = date('Y-m-d H:i:s');

    // Cek apakah ID menu sudah ada
    $check_query = "SELECT gambar FROM menu WHERE idmenu = '$id_menu'";
    $check_result = mysqli_query($conn, $check_query);

    if ($check_result) {
        $row = mysqli_fetch_assoc($check_result);
        if ($row) {
            // Jika ID menu sudah ada, hapus data lama
            $gambar_lama = $row['gambar'];
            if (!empty($gambar_lama) && file_exists('gambar/menu/' . $gambar_lama)) {
                unlink('gambar/menu/' . $gambar_lama); // Hapus gambar lama jika ada
            }

            // Hapus data lama dari database
            $delete_query = "DELETE FROM menu WHERE idmenu = '$id_menu'";
            mysqli_query($conn, $delete_query);
        }
    }

    // Handle image upload jika ada
    $gambar_nama = NULL;
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $image_name = $_FILES['gambar']['name']; // Gunakan nama asli
        $image_tmp_name = $_FILES['gambar']['tmp_name'];
        $image_folder = 'gambar/menu/';

        // Pastikan folder gambar/menu ada dan bisa ditulis
        if (!is_dir($image_folder)) {
            mkdir($image_folder, 0777, true);
        }

        // Pindahkan gambar ke folder yang sudah ditentukan
        $gambar_nama = $image_name; // Tetap gunakan nama asli tanpa perubahan
        if (!move_uploaded_file($image_tmp_name, $image_folder . $gambar_nama)) {
            echo "Error: Gagal mengupload file gambar.";
            exit;
        }
    }

    // Simpan data menu ke database
    $sql_menu = "INSERT INTO menu (idmenu, nama, gambar, jenis, harga, userrecord, usermodified, daterecord, datemodified)
                 VALUES ('$id_menu', '$nama', '$gambar_nama', '$jenis', '$harga', '$user_record', '$user_modified', '$now', '$now')";

    if (mysqli_query($conn, $sql_menu)) {
        header("Location: list_menu.php");
        exit;
    } else {
        echo "Error: " . $sql_menu . "<br>" . mysqli_error($conn);
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Menu</title>
    <link rel="stylesheet" href="w3.css"> <!-- Sesuaikan dengan lokasi CSS Anda -->
    <link rel="icon" type="image/png" href="jujurmart.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .w3-sidebar {
            z-index: 1100;
            position: fixed;
            left: -250px;
            width: 250px;
            height: 100%;
            overflow-x: hidden;
            transition: 0.5s;
            padding-top: 0;
            background-color: #f4f4f4;
            border-right: 1px solid #ccc;
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

        /* Sticky Header */
        .w3-blue {
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
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
    <a href="history_stok.php" class="w3-bar-item w3-button w3-border w3-hover-green">
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
    <div class="w3-green" style="display: flex; align-items: center;">
        <button class="w3-button w3-xlarge" onclick="w3_open()">☰</button>
        <div style="flex-grow: 1; display: flex; justify-content: center;">
            <h1 style="margin: 0; line-height: 3.5rem; margin-bottom:10px;"><b>Tambah Menu</b></h1>
        </div>
    </div>

    <!-- Modal untuk menampilkan pesan error -->
    <div id="myModal" class="w3-modal" style="display:<?php echo isset($showModal) && $showModal ? 'block' : 'none'; ?>">
        <div class="w3-modal-content w3-animate-top w3-card-4">
            <header class="w3-container w3-red">
                <span onclick="document.getElementById('myModal').style.display='none'" class="w3-button w3-display-topright">&times;</span>
                <h2>Error</h2>
            </header>
            <div class="w3-container">
                <p>Id Menu sudah ada. Silakan gunakan Id Menu yang lain.</p>
            </div>
        </div>
    </div>

   <!-- Form Tambah menu -->
<div id="mainContent" class="w3-padding-16">
    <form action="tambah_menu.php" method="post" enctype="multipart/form-data" class="w3-container w3-card-4 w3-light-grey w3-padding-16 w3-margin">
        <label>Id Menu</label>
        <input type="text" class="w3-input w3-border w3-light-grey" name="idmenu" required><br>

        <label>Nama</label>
        <input type="text" class="w3-input w3-border w3-light-grey" name="nama" required><br>

        <label>Jenis</label>
        <select class="w3-select w3-border w3-light-grey" name="jenis" required>
        <option value="" disabled selected>Pilih Jenis</option>
        <option value="Makanan">Makanan</option>
        <option value="Minuman">Minuman</option>
        </select> <br><br>

        <label>Harga</label>
        <input type="text" class="w3-input w3-border w3-light-grey" name="harga" required><br>

        <label>Gambar</label>
        <input type="file" id="gambarUpload" class="w3-input w3-border w3-light-grey" name="gambar" required onchange="previewFile()"><br>

        <!-- Preview Gambar -->
        <div>
        <img id="previewImage" src="" alt="Preview Gambar Baru" 
         style="max-width: 200px; height: auto; display: none; margin-bottom: 10px;">
        <div id="noImageText" class="w3-text-red">Masukkan Gambar Menu!</div>
        </div>

        <input type="hidden" name="userrecord" value="<?php echo htmlspecialchars($user_record); ?>" required readonly><br>
        <input type="hidden" name="usermodified" value="<?php echo htmlspecialchars($user_record); ?>">

        <div class="w3-half">
            <a href="list_menu.php" class="w3-button w3-container w3-orange w3-padding-16" style="width: 100%;">Kembali</a>
        </div>
        <div class="w3-half">
            <input type="hidden" name="action" value="tambah">
            <input type="submit" class="w3-button w3-green w3-container w3-padding-16" style="width: 100%;" value="Simpan">
        </div>
    </form>
</div>

    <script>
        function w3_open() {
            document.getElementById("mySidebar").classList.add("show");
            document.getElementById("sidebarOverlay").classList.add("show");
            document.getElementById("mainContent").classList.add("with-sidebar");
        }

        function w3_close() {
            document.getElementById("mySidebar").classList.remove("show");
            document.getElementById("sidebarOverlay").classList.remove("show");
            document.getElementById("mainContent").classList.remove("with-sidebar");
        }

        function closeSidebarOutside(event) {
            if (!event.target.closest('#mySidebar') && !event.target.closest('.w3-xlarge')) {
                w3_close();
            }
        }

        var modal = document.getElementById("myModal");
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        function formatRupiah(angka, prefix) {
            var number_string = angka.replace(/[^,\d]/g, '').toString(),
                split = number_string.split(','),
                sisa = split[0].length % 3,
                rupiah = split[0].substr(0, sisa),
                ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }

            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            return prefix == undefined ? rupiah : (rupiah ? 'Rp. ' + rupiah : '');
        }

        function removeFormatRupiah(angka) {
            return angka.replace(/[^,\d]/g, '');
        }

        document.addEventListener('DOMContentLoaded', function() {
            var hargaInput = document.getElementsByName('harga')[0];

            hargaInput.addEventListener('keyup', function(e) {
                hargaInput.value = formatRupiah(this.value);
            });

            var form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                hargaInput.value = removeFormatRupiah(hargaInput.value);
            });
        });

        document.addEventListener("DOMContentLoaded", function () {
            var inputs = document.querySelectorAll('input[required]');
            inputs.forEach(input => {
                input.addEventListener('invalid', function (event) {
                    event.preventDefault();
                    // Custom validation message
                    let message = "Mohon diisi, tidak boleh kosong";
                    input.setCustomValidity(message);
                    // Display the message
                    if (input.validity.valueMissing) {
                        input.reportValidity();
                    }
                });

                input.addEventListener('input', function () {
                    input.setCustomValidity(""); // Reset custom message on input
                });
            });
        });

        function previewFile() {
    const input = document.getElementById('gambarUpload');
    const preview = document.getElementById('previewImage');
    const noImageText = document.getElementById('noImageText');

    // Cek apakah file dipilih
    if (input.files && input.files[0]) {
        const file = input.files[0];

        // Validasi tipe file (hanya gambar)
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            alert("Hanya file gambar (JPEG, PNG, GIF) yang diperbolehkan.");
            input.value = ""; // Reset input file
            preview.style.display = "none";
            noImageText.style.display = "block";
            return;
        }

        // Tampilkan preview gambar
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            preview.style.display = "block";
            noImageText.style.display = "none";
        };
        reader.readAsDataURL(file);
    } else {
        // Jika tidak ada file, sembunyikan preview dan tampilkan pesan
        preview.style.display = "none";
        noImageText.style.display = "block";
    }
}
    </script>
</body>

</html>
