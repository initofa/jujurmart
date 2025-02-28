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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Id menu tidak ditemukan.");
}

$idmenu = $_GET['id'];

// Fetch current stock from the database
$query_stok = "SELECT stok FROM menu WHERE idmenu = '$idmenu'";
$result_stok = mysqli_query($conn, $query_stok);

if (!$result_stok || mysqli_num_rows($result_stok) == 0) {
    die("Data stok menu tidak ditemukan.");
}

$row_stok = mysqli_fetch_assoc($result_stok);
$stok = $row_stok['stok']; // Store the current stock

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $stok = mysqli_real_escape_string($conn, $_POST['stok']);
    $folder_gambar = 'gambar/stok/';
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif']; // Ekstensi gambar yang diperbolehkan
    $pesan_error = "";

    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        // Dapatkan informasi file gambar yang di-upload
        $file_info = pathinfo($_FILES['gambar']['name']);
        $extension = strtolower($file_info['extension']);

        // Periksa apakah ekstensi gambar diizinkan
        if (in_array($extension, $allowed_extensions)) {
            // Hapus gambar lama jika ada
            $gambar_lama = glob($folder_gambar . $idmenu . '.*'); // Ambil semua file gambar yang sesuai dengan idmenu
            if (!empty($gambar_lama)) {
                unlink($gambar_lama[0]); // Hapus gambar lama
            }

            // Simpan gambar baru dengan nama sesuai idmenu
            $gambar_baru = $folder_gambar . $idmenu . '_' . date('Ymd') . '.' . $extension;
            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $gambar_baru)) {
                // Update stok dalam database
                $update_query = "UPDATE menu SET stok = '$stok' WHERE idmenu = '$idmenu'";
                if (mysqli_query($conn, $update_query)) {
                    header("Location: list_menu.php");
                    exit;
                } else {
                    $pesan_error = "Terjadi kesalahan saat mengupdate data: " . mysqli_error($conn);
                }
            } else {
                $pesan_error = "Gagal meng-upload gambar.";
            }
        } else {
            $pesan_error = "Format gambar tidak valid. Hanya gambar dengan ekstensi JPG, JPEG, PNG, atau GIF yang diizinkan.";
        }
    } else {
        $pesan_error = "Gambar belum dipilih atau terjadi kesalahan.";
    }
}
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah stok</title>
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
    <a href="list_menu.php" class="w3-bar-item w3-button w3-border w3-hover-green">
        <i class="fas fa-utensils"></i> <span class="menu-text">List Menu</span>
    </a>
    <a href="list_penjualan.php" class="w3-bar-item w3-button w3-border w3-hover-green">
        <i class="fas fa-clipboard-list"></i> <span class="menu-text">List Penjualan</span>
    </a>
    <a href="dashboard.php" class="w3-bar-item w3-button w3-border w3-hover-green">
        <i class="fas fa-chart-bar"></i> <span class="menu-text">Dashboard</span>
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
            <h1 style="margin: 0; line-height: 3.5rem; margin-bottom:10px;"><b>Tambah Stok</b></h1>
        </div>
    </div>

<div id="mainContent" class="w3-padding-16">
    <form action="tambah_stok.php?id=<?php echo htmlspecialchars($idmenu); ?>" 
          method="post" enctype="multipart/form-data" 
          class="w3-container w3-card-4 w3-light-grey w3-padding-16 w3-margin">
        <input type="hidden" name="idmenu" value="<?php echo htmlspecialchars($idmenu); ?>" readonly><br>
        
        <label>Stok</label>
        <input type="number" name="stok" class="w3-input w3-border w3-light-grey" 
               value="<?php echo htmlspecialchars($stok); ?>" oninput="checkChanges()" required><br>

        <label>Bukti Stok</label>
        <input type="file" class="w3-input w3-border w3-light-grey" 
               name="gambar" id="gambarUpload" accept="image/*" capture="camera" oninput="checkChanges()" required><br>

       <!-- Preview Gambar -->
<div style="display: flex; flex-direction: column; align-items: center;">
    <?php
    $image_displayed = false;
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $folder_gambar = 'gambar/stok/';
    
    // Ambil semua file yang sesuai dengan pola (idmenu diikuti oleh _ dan tanggal)
    $pattern = $folder_gambar . $idmenu . '_*.{jpg,jpeg,png,gif,web}';
    $files = glob($pattern, GLOB_BRACE);

    // Urutkan file berdasarkan tanggal (dari nama file)
    if (!empty($files)) {
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a); // Urutkan berdasarkan tanggal terbaru
        });

        $latest_image = $files[0]; // Ambil gambar terbaru

        if (file_exists($latest_image)) {
            echo "<img id='previewImage' src='$latest_image' alt='Gambar Saat Ini' 
                  style='max-width: 200px; height: auto; margin-bottom: 10px; display: block; margin: 0 auto;'>";
            $image_displayed = true;
        }
    }

    if (!$image_displayed) {
        echo "<img id='previewImage' src='' alt='Preview Gambar Baru' 
              style='max-width: 200px; height: auto; display: none; margin-bottom: 10px;'>";
        echo "<div style='text-align: center;'>Gambar tidak tersedia.</div>";
    }
    ?>
</div><br>

        <div class="w3-half">
            <a href="list_menu.php" class="w3-button w3-container w3-orange w3-padding-16" 
               style="width: 100%;">Kembali</a>
        </div>
        <div class="w3-half">
            <input type="hidden" name="action" value="update">
            <input type="submit" id="updateButton" class="w3-button w3-green w3-container w3-padding-16" 
                   style="width: 100%;" value="Simpan" disabled>
        </div>
    </form>
</div>

<!-- Error Modal -->
<?php if (!empty($pesan_error)): ?>
    <div id="errorModal" class="w3-modal" onclick="closeModal1(event)" style="align-items:center; padding-top: 15%;">
        <div class="w3-modal-content w3-animate-top w3-card-4">
            <header class="w3-container w3-red">
                <span onclick="closeModal1()" class="w3-button w3-display-topright">&times;</span>
                <h2>Informasi</h2>
            </header>
            <div class="w3-container">
                <p><?php echo htmlspecialchars($pesan_error); ?></p>
                <div class="w3-right">
                    <button class="w3-button w3-grey" onclick="closeModal1()">Tutup</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- JavaScript untuk Preview Gambar Baru -->
<script>
     function w3_open() {
        document.getElementById("mySidebar").classList.add("show");
        document.getElementById("sidebarOverlay").classList.add("show");
    }

    function w3_close() {
        document.getElementById("mySidebar").classList.remove("show");
        document.getElementById("sidebarOverlay").classList.remove("show");
    }
    document.getElementById('gambarUpload').addEventListener('change', function(event) {
        const file = event.target.files[0];
        const preview = document.getElementById('previewImage');

        if (file) {
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
        } else {
            preview.src = '';
            preview.style.display = 'none';
        }
    });

    document.addEventListener("DOMContentLoaded", function () {
    const stokInput = document.querySelector("input[name='stok']");
    const gambarInput = document.querySelector("input[name='gambar']");
    const submitButton = document.querySelector("input[type='submit']");
    let initialStok = stokInput.value;
    let gambarChanged = false;

    stokInput.addEventListener("input", checkChanges);
    gambarInput.addEventListener("change", function () {
        gambarChanged = gambarInput.files.length > 0;
        checkChanges();
    });

    function checkChanges() {
        let stokChanged = stokInput.value !== initialStok;
        submitButton.disabled = !(stokChanged && gambarChanged);
    }

    checkChanges();
});
document.getElementById('errorModal').style.display = 'block';
        
        function closeModal1(event) {
            const modal = document.getElementById('errorModal');
            if (event && event.target !== modal) {
                return;
            }
            modal.style.display = 'none';
        }
</script>
</body>
</html>