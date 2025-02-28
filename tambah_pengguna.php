<?php
include 'koneksi.php';
session_start();

if (!isset($_SESSION["username"])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $nama = $_POST['nama'];
    $password = $_POST['password'];

    // Periksa apakah username sudah ada sebelumnya
    $check_query = "SELECT COUNT(*) as count FROM pengguna WHERE username = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if ($count > 0) {
        // Jika username sudah ada, tampilkan pesan error
        echo '<script>';
        echo 'document.getElementById("myModal").style.display="block";';
        echo '</script>';
    } else {
        // Jika username belum ada, lakukan penambahan data
        $sql = "INSERT INTO pengguna (username, nama, password) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $username, $nama, $password);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: list_pengguna.php");
            exit;
        } else {
            echo "Error: " . mysqli_error($conn);
        }

        mysqli_stmt_close($stmt);
    }

    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Tambah Pengguna</title>
    <link rel="stylesheet" href="w3.css">
    <link rel="icon" type="image/png" href="jujurmart.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            <h1 style="margin: 0; line-height: 3.5rem; margin-bottom:10px;"><b>Tambah Pengguna</b></h1>
        </div>
    </div>

    <!-- Modal untuk menampilkan pesan error -->
    <div id="myModal" class="w3-modal" style="display:none">
        <div class="w3-modal-content w3-animate-top w3-card-4">
            <header class="w3-container w3-red">
                <span onclick="document.getElementById('myModal').style.display='none'" class="w3-button w3-display-topright">&times;</span>
                <h2>Error</h2>
            </header>
            <div class="w3-container">
                <p>Username sudah ada. Silakan gunakan Username yang lain.</p>
            </div>
        </div>
    </div>

    <div class="w3-container w3-padding-16">
        <form action="" method="post" class="w3-container w3-card-4 w3-light-grey w3-padding-16 w3-margin">
            <label>Username</label>
            <input type="text" class="w3-input w3-border w3-light-grey" name="username" required><br>
            <label>Nama</label>
            <input type="text" class="w3-input w3-border w3-light-grey" name="nama" required><br>
            <label>Password</label>
            <input type="password" class="w3-input w3-border w3-light-grey" name="password" required><br>
            <div class="w3-half">
                <a href="list_pengguna.php" class="w3-button w3-container w3-orange w3-padding-16" style="width: 100%;">Kembali</a>
            </div>
            <div class="w3-half">
                <input type="submit" class="w3-button w3-green w3-container w3-padding-16" style="width: 100%;" value="Simpan">
            </div>
        </form>
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

    document.getElementById('sidebarOverlay').addEventListener('click', w3_close);

    var modal = document.getElementById("myModal");
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

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
