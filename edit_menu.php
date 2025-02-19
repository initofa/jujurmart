<?php
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

session_start();

if (!isset($_SESSION["username"])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION["username"];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $now = date('Y-m-d H:i:s');

    if ($action == 'edit') {
        $id_menu = $_POST['idmenu'];
        $nama = $_POST['nama'];
        $jenis = $_POST['jenis'];
        $harga = str_replace(',', '', $_POST['harga']); // Menghapus format ribuan
        $user_modified = $username;

        $extensions = ['jpg', 'jpeg', 'png', 'gif']; // Daftar ekstensi gambar yang diizinkan
        $gambar_baru = NULL; // Nama file gambar baru dari upload
        $folder_gambar = 'gambar/menu/';

        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
            // Dapatkan nama asli file gambar
            $gambar_baru = $_FILES['gambar']['name']; // Menyimpan nama asli file

            // Dapatkan ekstensi file
            $file_info = pathinfo($gambar_baru);
            $extension = strtolower($file_info['extension']); // Ekstensi file gambar
            
            // Periksa apakah ekstensi valid
            if (in_array($extension, $extensions)) {
                // Hapus gambar lama berdasarkan data dari database
                $query_get_gambar = "SELECT gambar FROM menu WHERE idmenu = '$id_menu'";
                $result_get_gambar = mysqli_query($conn, $query_get_gambar);
                $row_gambar = mysqli_fetch_assoc($result_get_gambar);
                
                if (!empty($row_gambar['gambar'])) {
                    $gambar_lama_path = $folder_gambar . $row_gambar['gambar'];
                    if (file_exists($gambar_lama_path)) {
                        unlink($gambar_lama_path); // Hapus gambar lama
                    }
                }

                // Pindahkan gambar baru ke folder dengan nama aslinya
                if (!move_uploaded_file($_FILES['gambar']['tmp_name'], $folder_gambar . $gambar_baru)) {
                    echo "Gagal meng-upload gambar.";
                    exit;
                }
            } else {
                echo "Format gambar tidak valid. Hanya JPG, JPEG, PNG, atau GIF yang diizinkan.";
                exit;
            }
        }

        // Query UPDATE untuk tabel menu
        $sql_menu = "UPDATE menu 
                     SET nama = '$nama', 
                         jenis = '$jenis', 
                         harga = '$harga', 
                         usermodified = '$user_modified', 
                         datemodified = '$now'";

        // Jika ada gambar baru, update nama file gambar di database
        if ($gambar_baru) {
            $sql_menu .= ", gambar = '$gambar_baru'";
        }

        $sql_menu .= " WHERE idmenu = '$id_menu'";

        if (mysqli_query($conn, $sql_menu)) {
            header("Location: list_menu.php");
            exit;
        } else {
            echo "Update Error Menu: " . mysqli_error($conn);
        }
    }
}


// Query untuk mendapatkan data pengguna
$query = "SELECT * FROM pengguna WHERE username = '$username'";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query error: " . mysqli_error($conn));
}

if (mysqli_num_rows($result) > 0) {
    $user_data = mysqli_fetch_assoc($result);
    $user_modified = $user_data['username'];
} else {
    $user_modified = '';
}

$idmenu = isset($_GET['idmenu']) ? $_GET['idmenu'] : null;

if (!$idmenu) {
    die("ID Menu tidak ditemukan.");
}

// Query untuk mendapatkan data menu
$sql_menu = "SELECT * FROM menu WHERE idmenu = '$idmenu'";
$result_menu = mysqli_query($conn, $sql_menu);

if (!$result_menu) {
    die("Query error: " . mysqli_error($conn));
}

if (mysqli_num_rows($result_menu) > 0) {
    $menu_data = mysqli_fetch_assoc($result_menu);
    $user_modified = $menu_data['usermodified']; // Ambil usermodified dari tabel menu
} else {
    die("Data menu dengan ID $idmenu tidak ditemukan.");
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit menu</title>
    <link rel="stylesheet" href="w3.css"> <!-- Adjust to your CSS location -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="icon" type="image/png" href="jujurmart.png">
    <style>
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
    </style>
</head>

<body>
    <!-- Sidebar Overlay -->
    <div id="sidebarOverlay" class="w3-sidebar-overlay" onclick="w3_close()"></div>

    <!-- Sidebar -->
<div class="w3-sidebar w3-bar-block w3-border-right w3-light-grey" id="mySidebar">
    <button onclick="w3_close()" class="w3-bar-item w3-button w3-red w3-center close-button">
        <b>Close</b> <i class="fa fa-close" style="font-size:20px"></i>
    </button>
    <a href="list_menu.php" class="w3-bar-item w3-button w3-border w3-hover-green">
        <i class="fas fa-utensils"></i> <span class="menu-text">List Menu</span>
    </a>
    <a href="list_pesanan.php" class="w3-bar-item w3-button w3-border w3-hover-green">
        <i class="fas fa-clipboard-list"></i> <span class="menu-text">List Pesanan</span>
    </a>
    <a href="dashboard.php" class="w3-bar-item w3-button w3-border w3-hover-green">
        <i class="fas fa-chart-line"></i> <span class="menu-text">Dashboard</span>
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
            <h1 style="margin: 0; line-height: 3.5rem; margin-bottom:10px;"><b>Edit Menu</b></h1>
        </div>
    </div>

    <div id="mainContent">
    <form action="edit_menu.php" method="post" class="w3-container w3-card-4 w3-light-grey w3-padding-16 w3-margin" enctype="multipart/form-data">
        <input type="hidden" name="idmenu" value="<?php echo htmlspecialchars($menu_data['idmenu']); ?>">

        <label for="nama">Nama</label>
        <input type="text" id="namabarang" name="nama" class="w3-input w3-border w3-light-grey"
            value="<?php echo htmlspecialchars($menu_data['nama']); ?>" oninput="checkChanges()" required><br>

        <label for="jenis">Jenis</label>
        <select id="jenis" name="jenis" class="w3-input w3-border w3-light-grey" oninput="checkChanges()" required>
        <option value="Makanan" <?php echo ($menu_data['jenis'] == 'Makanan') ? 'selected' : ''; ?>>Makanan</option>
        <option value="Minuman" <?php echo ($menu_data['jenis'] == 'Minuman') ? 'selected' : ''; ?>>Minuman</option>
        </select><br>

        <label for="harga">Harga</label>
        <input type="text" id="harga" name="harga_display" class="w3-input w3-border w3-light-grey"
    value="<?php echo isset($menu_data['harga']) && $menu_data['harga'] !== '' ? htmlspecialchars(number_format($menu_data['harga'], 0, ',', '.')) : ''; ?>" 
    oninput="handleInput(this);" required>
<input type="hidden" id="harga_hidden" name="harga"><br>

        <!-- Image upload field for multiple images -->
        <label>Gambar</label>
        <input type="file" id="gambar" name="gambar" class="w3-input w3-border w3-light-grey" multiple oninput="checkChanges()"><br>

        <?php
// Pastikan mengambil gambar berdasarkan nama file di kolom 'gambar'
if (!empty($menu_data['gambar'])) {
    $image_path = "gambar/menu/" . $menu_data['gambar'];
    if (file_exists($image_path)) {
        echo "<img src='$image_path' alt='Gambar menu' style='max-width: 100px; height: auto;'><br>";
    } else {
        echo "<p style='color: red;'>Gambar tidak ditemukan: " . htmlspecialchars($menu_data['gambar']) . "</p>";
    }
} else {
    echo "<p style='color: red;'>Gambar tidak tersedia</p>";
}
?>
        <br>
         <!-- Input User Modified -->
        <label for="usermodified">User Modified</label>
        <input type="text" id="usermodified" name="usermodified" class="w3-input w3-border w3-light-grey"
          value="<?php echo htmlspecialchars($user_modified); ?>" readonly><br>

        <div class="w3-half">
            <a href="list_menu.php" class="w3-button w3-orange w3-padding-16" style="width: 100%;">Kembali</a>
        </div>
        <div class="w3-half">
            <input type="hidden" name="action" value="edit">
            <input type="submit" id="updateButton" class="w3-button w3-green w3-padding-16" style="width: 100%;" value="Simpan" disabled>
        </div>
    </form>
</div>

    <!-- JavaScript -->
    <script>
        function w3_open() {
            document.getElementById("mySidebar").classList.add("show");
            document.getElementById("sidebarOverlay").classList.add("show");
        }

        function w3_close() {
            document.getElementById("mySidebar").classList.remove("show");
            document.getElementById("sidebarOverlay").classList.remove("show");
        }

    function handleInput(input) {
    checkChanges();
    formatCurrency(input);
    }

    function checkChanges() {
    let updateButton = document.getElementById("updateButton");
    if (updateButton) {
        updateButton.disabled = false;
    }
}

function formatCurrency(input) {
    // Hapus semua karakter non-numerik
    let value = input.value.replace(/\D/g, '');

    if (value === '') {
        input.value = '';
        return;
    }

    // Format angka dengan titik sebagai pemisah ribuan
    let formattedValue = parseInt(value, 10).toLocaleString('id-ID');

    // Simpan nilai asli tanpa titik di atribut data-value untuk dikirim ke server
    input.setAttribute('data-value', value);

    // Tampilkan nilai yang sudah diformat di input
    input.value = formattedValue;
}
document.addEventListener('DOMContentLoaded', function () {
    let hargaInput = document.getElementById('harga');
    let hargaHidden = document.getElementById('harga_hidden');

    // Simpan nilai awal harga saat halaman dimuat
    if (hargaInput.value !== '') {
        hargaHidden.value = hargaInput.getAttribute('data-value') || hargaInput.value.replace(/\D/g, '');
    }

    document.querySelector('form').addEventListener('submit', function() {
        // Pastikan hanya memperbarui harga jika telah diubah
        let hargaValue = hargaInput.getAttribute('data-value');
        
        if (hargaValue) {
            hargaHidden.value = hargaValue;
        }
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
        
    </script>
</body>

</html>
