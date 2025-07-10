<?php
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');
session_start();

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
$pesan_error = "";

// Tampilkan stok sebelumnya
$stok_lama_query = "SELECT stok FROM menu WHERE idmenu = '$idmenu'";
$stok_lama_result = mysqli_query($conn, $stok_lama_query);
$stok_sebelumnya = 0;
if ($stok_lama_result && mysqli_num_rows($stok_lama_result) > 0) {
    $stok_lama_data = mysqli_fetch_assoc($stok_lama_result);
    $stok_sebelumnya = (int)$stok_lama_data['stok'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $stok = mysqli_real_escape_string($conn, $_POST['stok']);
    $folder_gambar = __DIR__ . '/gambar/stok/';
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

    if (!is_dir($folder_gambar)) {
        mkdir($folder_gambar, 0755, true);
    }

    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $file_info = pathinfo($_FILES['gambar']['name']);
        $extension = strtolower($file_info['extension']);

        if (in_array($extension, $allowed_extensions)) {
            $waktu = date('H-i-s_dmY');
            $nama_file_baru = $idmenu . '_' . $stok . '_' . $waktu . '.' . $extension;
            $gambar_baru = $folder_gambar . $nama_file_baru;

            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $gambar_baru)) {
                $update_query = "UPDATE menu SET stok = '$stok' WHERE idmenu = '$idmenu'";
                if (mysqli_query($conn, $update_query)) {
                    header("Location: list_menu.php");
                    exit;
                } else {
                    $pesan_error = "Terjadi kesalahan saat mengupdate stok: " . mysqli_error($conn);
                }
            } else {
                $pesan_error = "Gagal meng-upload gambar.";
            }
        } else {
            $pesan_error = "Format gambar tidak valid. Hanya JPG, JPEG, PNG, atau GIF.";
        }
    } else {
        $pesan_error = "Gambar belum dipilih atau terjadi kesalahan.";
    }
}
mysqli_close($conn);
?>

<!DOCTYPE html>
<html>
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

       .sticky-header {
    position: sticky;
    top: 0;
    z-index: 999; /* pastikan lebih tinggi dari sidebar */
    display: flex;
    align-items: center;
}

        .center-img {
            display: block;
            margin: 10px auto;
            max-width: 200px;
            height: auto;
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
<div class="w3-green sticky-header">
    <button class="w3-button w3-xlarge" onclick="w3_open()">☰</button>
    <div style="flex-grow: 1; display: flex; justify-content: center;">
        <h1 style="margin: 0; line-height: 3.5rem; margin-bottom:10px;"><b>Tambah Stok</b></h1>
    </div>
</div>


<div id="mainContent" class="w3-container w3-padding-16">
   <!--  Riwayat Gambar Bukti Stok -->
<div class="w3-container w3-card-4 w3-light-grey w3-padding-16 w3-margin">
    <div class="w3-center">
        <div class='w3-panel w3-light-grey'>
            Sisa stok sekarang:<?php echo $stok_sebelumnya; ?>
        </div>
        <h4>Bukti Stok sebelumnya</h4>
        <?php
            $image_displayed = false;
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $folder_gambar = 'gambar/stok/';
            $pattern = $folder_gambar . $idmenu . '_*.{jpg,jpeg,png,gif}';
            $files = glob($pattern, GLOB_BRACE);

            if (!empty($files)) {
                usort($files, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });

                $latest_image = $files[0];
                if (file_exists($latest_image)) {
                    echo "<img src='$latest_image' alt='Bukti stok terbaru' class='center-img'>";
                    $image_displayed = true;
                }
            }

            if (!$image_displayed) {
                echo "<div style='text-align: center;'>Belum ada gambar bukti stok yang tersedia.</div>";
            }
        ?>
    </div>
</div>

    <!--  Form Input Stok -->
    <form action="tambah_stok.php?id=<?php echo htmlspecialchars($idmenu); ?>" 
          method="post" enctype="multipart/form-data" 
          class="w3-container w3-card-4 w3-light-grey w3-padding-16 w3-margin">

        <input type="hidden" name="idmenu" value="<?php echo htmlspecialchars($idmenu); ?>">

        <label>Tambahan Stok</label>
        <input type="number" name="stok" class="w3-input w3-border w3-light-grey" 
               placeholder="Masukkan stok" oninput="checkChanges()" required><br>

        <label>Bukti Stok</label>
        <input type="file" name="gambar" class="w3-input w3-border w3-light-grey" 
               id="gambarUpload" accept="image/*" capture="camera" oninput="checkChanges()" required><br>

        <!-- Preview gambar sebelum upload -->
        <img id="previewImage" src="" alt="Preview Gambar Baru" class="center-img" style="display: none;"><br>

        <?php if (!empty($pesan_error)): ?>
            <div class="w3-panel w3-red w3-padding"><?php echo $pesan_error; ?></div>
        <?php endif; ?>

        <div class="w3-half">
            <a href="list_menu.php" class="w3-button w3-orange w3-padding-16" style="width: 100%;">Kembali</a>
        </div>
        <div class="w3-half">
            <input type="hidden" name="action" value="update">
            <input type="submit" id="updateButton" class="w3-button w3-green w3-padding-16" style="width: 100%;" value="Simpan" disabled>
        </div>
    </form>
</div>

<!--  JavaScript Preview & Button Activation -->
<script>
       function w3_open() {
        document.getElementById("mySidebar").classList.add("show");
        document.getElementById("sidebarOverlay").classList.add("show");
    }

    function w3_close() {
        document.getElementById("mySidebar").classList.remove("show");
        document.getElementById("sidebarOverlay").classList.remove("show");
    }
function checkChanges() {
    const stokInput = document.querySelector('input[name="stok"]');
    const gambarInput = document.getElementById('gambarUpload');
    const submitBtn = document.getElementById('updateButton');

    // Preview gambar
    if (gambarInput.files && gambarInput.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('previewImage');
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(gambarInput.files[0]);
    }

    // Enable tombol submit
    submitBtn.disabled = !(stokInput.value && gambarInput.files.length > 0);
}
</script>

</body>
</html>
