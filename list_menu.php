<?php
include 'koneksi.php';
date_default_timezone_set('Asia/Jakarta'); // Ganti dengan zona waktu yang sesuai
session_start();

if (!isset($_SESSION["username"])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION["username"]; // Ambil username dari session
// Check if the user is admin
$is_admin = ($username === 'admin');

// Handle search keyword
$search_keyword = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$extensions = ['jpg', 'jpeg', 'png', 'gif']; // Allowed image file types

// Process barang deletion if 'action' and 'id' parameters are set
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_menu = mysqli_real_escape_string($conn, $_GET['id']);

    if (!empty($id_menu)) {
        // Set path to image
        $folder_gambar = 'gambar/menu/';
        
        // Loop through all allowed extensions and try to find the image
        $extensions = ['jpg', 'jpeg', 'png', 'gif']; // Daftar ekstensi yang diizinkan
        foreach ($extensions as $ext) {
            $gambar_lama = $folder_gambar . $id_menu . '.' . $ext;

            // Check if the image file exists
            if (file_exists($gambar_lama)) {
                // If image exists, delete it
                unlink($gambar_lama);
                break; // Exit loop once image is found and deleted
            }
        }

        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            // Delete menu
            $sql_delete_menu = "DELETE FROM menu WHERE idmenu = ?";
            $stmt_menu = mysqli_prepare($conn, $sql_delete_menu);
            if (!$stmt_menu) {
                throw new Exception("Error preparing statement: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt_menu, 's', $id_menu); // 's' untuk string
        
            // Eksekusi query
            if (!mysqli_stmt_execute($stmt_menu)) {
                throw new Exception("Error deleting from menu: " . mysqli_error($conn));
            }
        
            // Commit transaksi jika berhasil
            mysqli_commit($conn);
        
            // Redirect ke list_menu.php
            header('Location: list_menu.php');
            exit;
        } catch (Exception $e) {
            // Rollback transaksi jika terjadi error
            mysqli_rollback($conn);
        
            // Cek apakah error disebabkan oleh foreign key constraint
            if (strpos($e->getMessage(), "foreign key constraint fails") !== false) {
                $pesan_error = "Tidak bisa dihapus, menu sudah terdaftar di transaksi.";
            } else {
                $pesan_error = "Terjadi kesalahan: " . $e->getMessage();
            }
        }
    } else {
        $pesan_error = "ID menu tidak valid."; // Set error message for invalid ID
    }
}


// Count total items (with or without search filter)
if ($username === 'admin') {
    // For admin: count all items
    $sql_count = "SELECT COUNT(DISTINCT idmenu) AS total 
                  FROM menu";
} else {
    // For regular users: count items filtered by userrecord
    $sql_count = "SELECT COUNT(DISTINCT idmenu) AS total 
                  FROM menu
                  WHERE userrecord = '$username'";
}

$result_count = mysqli_query($conn, $sql_count);
$row_count = mysqli_fetch_assoc($result_count);
$total_records = $row_count['total'];

// Pagination
$records_per_page = 10;
$total_pages = ceil($total_records / $records_per_page);
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Retrieve items with pagination, search, and filtering by userrecord if not admin
if ($username === 'admin') {
    $sql = "SELECT idmenu, nama,gambar, jenis, stok, harga ,userrecord
            FROM menu
            WHERE (nama LIKE '%$search_keyword%' 
                   OR jenis LIKE '%$search_keyword%')
            ORDER BY daterecord DESC
            LIMIT $offset, $records_per_page";
} else {
    $sql = "SELECT idmenu, nama,gambar, jenis, stok, harga
            FROM menu
            WHERE (nama LIKE '%$search_keyword%' 
                   OR jenis LIKE '%$search_keyword%')
            AND userrecord = '$username'
            ORDER BY daterecord DESC
            LIMIT $offset, $records_per_page";
}

// Execute the query to retrieve items
$result = mysqli_query($conn, $sql);

// Get the total number of items (for display, unchanged by search)
$totalQuery = "SELECT COUNT(*) AS total_items 
               FROM menu";
if ($username !== 'admin') {
    // Only for regular users, filter by userrecord
    $totalQuery .= " WHERE userrecord = '$username'";
}

$totalResult = mysqli_query($conn, $totalQuery);
if ($totalResult) {
    $totalData = mysqli_fetch_assoc($totalResult);
    $totalItems = $totalData['total_items'];
} else {
    $totalItems = 0; // Fallback in case of an error
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List menu</title>
    <link rel="stylesheet" href="w3.css">
    <link rel="icon" type="image/png" href="jujurmart.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        /* Styling for specific elements */
        .action-icons {
            display: flex;
            justify-content: space-around;
            margin-top: 10px;
        }

        .bottom-right {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .bottom-right i {
            margin: 0;
        }

        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        /* Modified styles for sidebar */
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

        /* Styling for sidebar overlay */
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

        @media (max-width: 600px) {
            .w3-table-all {
                table-layout: fixed;
                width: 100%;
            }

            .w3-table-all th,
            .w3-table-all td {
                word-wrap: break-word;
            }

            .w3-table-all th:nth-of-type(1),
            .w3-table-all td:nth-of-type(1) {
                width: 40%;
            }

            .w3-table-all th:nth-of-type(2),
            .w3-table-all td:nth-of-type(2) {
                width: 30%;
            }

            .w3-table-all th:nth-of-type(3),
            .w3-table-all td:nth-of-type(3) {
                width: 30%;
            }
        }

        .action-icons a {
            margin: 5px;
        }
    </style>
</head>

<body class="w3-light-grey">
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
    <a href="#" class="w3-bar-item w3-button w3-border w3-hover-green">
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


    <!-- Page Content -->
    <div id="mainContent" style="margin-left: 0; transition: margin-left 0.5s;">
        <div class="w3-green sticky-header" style="display: flex; align-items: center; padding: 10px;">
            <button class="w3-button w3-xlarge" onclick="w3_open()">☰</button>
            <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: center;">
                <h1
                    style="margin: 0; line-height: 1.5rem; text-align: center; font-size: 30px; margin-top:5px; margin-bottom: 10px;">
                    <b>List Menu</b>
                </h1>
                <div style="display: flex; font-size: 14px;">
                    <div style="flex: 1; text-align: left;">
                    <span style="font-size: 14px;">
                    <span style=" color: red;text-shadow: 2px 2px 4px black;">Id</span> 
                    <span style=" color: #FFBF00;text-shadow: 2px 2px 4px black;">Jenis</span> 
                    </span> <br>
                        <span style="font-size: 15px; font-weight: bold;">NAMA</span> <br>
                        <span style="font-size: 15px; font-weight: bold;">STOK</span> <br>
                    </div>
                    <div style="flex: 1; text-align: center; margin-right: 5%">
                        <br> <span style="font-size: 15px; font-weight: bold;">HARGA</span> <br>
                        <?php if ($_SESSION['username'] == 'admin') { ?>
                        <span style="font-size: 15px; font-weight: bold;">PENJUAL</span>
                        <?php } ?>
                    </div>
                    <div style="flex: 1; text-align: center;">
                        <br> <span style="font-size: 15px; font-weight: bold;">GAMBAR</span>
                    </div>
                    <div style="flex: 1; text-align: center;">
                        <span style="font-size: 15px; font-weight: bold; margin-left:40%;">ACTION</span>
                    </div>
                </div>
            </div>
        </div>

<!-- Kotak Pencarian -->
<div style="display: flex; justify-content: center; margin: 20px;">
    <form method="GET" action="" style="width: 100%; max-width: 600px; display: flex; position: relative;">
        <!-- Input Field with Modern Style -->
        <input type="text" name="search" class="w3-input w3-border" placeholder="Cari menu..."
            value="<?php echo isset($_GET['search']) && !empty($_GET['search']) ? '' : htmlspecialchars($search_keyword); ?>" 
            style="width: 100%; padding: 12px 20px; padding-right: 60px; border-radius: 50px; border: 2px solid #ddd; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); font-size: 16px;">

        <!-- Modern "Cari" Button -->
        <button type="submit" class="w3-button w3-green"
                style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); height: 40px; width: 40px; border-radius: 50%; border: none; color: white; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); cursor: pointer; display: flex; align-items: center; justify-content: center;">
            <i class="fa fa-search" style="font-size: 18px;"></i> <!-- Increased Font Awesome icon size -->
        </button>
    </form>
</div>

<!-- Total Items -->
<div style="font-size: 15px; text-align: right; padding-right: 30px;">
    <span class="w3-bar-item">Total: <?php echo $totalItems; ?> menu</span>
</div>

        <!-- Table of Menu -->
        <div class="w3-responsive">
            <table class="w3-table-all w3-centered" border="1" style="border-collapse: collapse; width: 100%;">
                <?php while ($row = mysqli_fetch_assoc($result)) {
                
                    ?>
                    <tr>
                        <td style="font-size: 14px; text-align: left; width: 27%">
                            <span class="w3-text-red"><?php echo htmlspecialchars($row['idmenu']); ?></span>
                           <span class="w3-text-amber"><?php echo htmlspecialchars($row['jenis']); ?></span> <br>
                            <span
                                style="font-size: 18px; font-weight: bold;"><?php echo htmlspecialchars($row['nama']); ?></span>
                            <br>
                            <span style="font-size: 16px; font-weight: bold;">
                                <?php echo ($row['stok'] > 0) ? $row['stok'] : '<span class="w3-text-red">Stok Habis</span>'; ?>
                            </span>
                        </td>
                        <td style="font-size: 14px; text-align: center; width: 25%">
                            <br><span>Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></span> <br>
                            <?php if ($_SESSION['username'] == 'admin') { ?>
                            <span style="font-size: 14px; font-weight: bold;"><?php echo htmlspecialchars($row['userrecord']); ?></span>
                            <?php } ?>
                        </td>
                        <td style="font-size: 14px; text-align: center; width: 30%">
    <?php
    if (!empty($row['gambar'])) { 
        $image_path = "gambar/menu/" . $row['gambar']; // Ambil nama file dari database
        if (file_exists($image_path)) {
            echo "<img src='$image_path' alt='Gambar menu' style='width: 100px; height: auto; cursor: pointer;' onclick='openModal(\"$image_path\")'>";
        } else {
            echo "<p style='color: red;'>Gambar tidak ditemukan: $row[gambar]</p>"; // Debugging
        }
    } else {
        echo "<p style='color: red;'>Gambar tidak tersedia</p>";
    }
    ?>
</td>
                        <td class="action-icons" style="font-size: 14px; text-align: center;">
                        <a href="tambah_stok.php?id=<?php echo htmlspecialchars($row['idmenu']); ?>" class="fas fa-box-open w3-btn w3-button w3-round w3-green" style="font-size: 14px;"></a>
                            <a href="edit_menu.php?idmenu=<?php echo $row['idmenu']; ?>"
                                class="material-icons w3-yellow w3-btn w3-button w3-round"
                                style="font-size: 15px;">&#xe22b;</a>
                            <a href="#"
                                onclick="deleteMenu('<?php echo $row['idmenu']; ?>', '<?php echo htmlspecialchars($row['nama']); ?>')"
                                class="fa fa-trash w3-btn w3-button w3-round w3-red" style="font-size: 15px;"></a>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        </div>

        <!-- Modern Pagination with Slightly Rectangular Corners -->
<div class="pagination-container">
    <!-- Previous Button -->
    <a href="?page=<?php echo max(1, $page - 1); ?>" class="pagination-button">&laquo;</a>
    
    <!-- Page Number Buttons -->
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?php echo $i; ?>" class="pagination-button <?php echo ($i == $page) ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
    <?php endfor; ?>
    
    <!-- Next Button -->
    <a href="?page=<?php echo min($total_pages, $page + 1); ?>" class="pagination-button">&raquo;</a>
</div>

<!-- CSS Styles for Pagination with Slightly Rectangular Corners -->
<style>
.pagination-container {
    display: flex;
    align-items: center;
    margin: 20px 0;
    padding-left: 10px;
}

.pagination-button {
    margin: 0 5px;
    padding: 8px 16px;
    border-radius: 6px;
    border: 1px solid #ddd;
    background-color: #d9fbd9; /* Warna hijau muda untuk tombol default */
    color: #333;
    text-decoration: none;
    font-size: 14px;
    font-weight: normal;
    transition: background-color 0.3s, color 0.3s;
}

.pagination-button:hover {
    color: white;
    background-color: #4caf50; /* Warna hijau w3-green */
    border-color: #4caf50; /* Sesuai dengan warna hover */
}

.pagination-button.active {
    background-color: #4caf50; /* Warna hijau aktif */
    color: white;
    border-color: #4caf50;
    cursor: default;
    pointer-events: none;
}

.pagination-button:focus {
    outline: none;
}
</style>


        <!-- Floating Button -->
        <div class="bottom-right w3-green w3-hover-shadow"
            style="box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); border-radius: 50%; padding: 15px; position: fixed; bottom: 20px; right: 20px;"
            onclick="location.href='tambah_menu.php'">
            <i class="fa fa-plus w3-xlarge w3-text-white"></i>
        </div>

        <!-- Modal gambar -->
      <div id="imageModal" class="w3-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.4); justify-content: center; align-items: center; z-index: 1000;">
         <img id="modalImage" src="" style="padding: 5px; background-color: white; max-width: 80%; max-height: 80%; cursor: pointer; box-sizing: border-box;">
      </div>

          <!-- Modal untuk konfirmasi penghapusan -->
          <div id="deleteModal" class="w3-modal" onclick="closeModal(event)"
            style="align-items:center; padding-top: 15%;">
            <div class="w3-modal-content w3-animate-top w3-card-4">
                <header class="w3-container w3-red">
                    <span onclick="document.getElementById('deleteModal').style.display='none'"
                        class="w3-button w3-display-topright">&times;</span>
                    <h2>Konfirmasi</h2>
                </header>
                <div class="w3-container">
                    <p>Apakah Anda yakin ingin menghapus menu ini?</p>
                    <div class="w3-right">
                        <button class="w3-button w3-grey"
                            onclick="document.getElementById('deleteModal').style.display='none'">Batal</button>
                        <button class="w3-button w3-red" onclick="confirmDelete()">Hapus</button>
                    </div>
                </div>
            </div>
        </div>
      <!-- Error Modal -->
<?php if (isset($pesan_error)): ?>
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

    <style>
        .w3-modal {
            display: none;
            /* Sembunyikan modal secara default */
            position: fixed;
            z-index: 9999;
            /* Pastikan modal berada di atas semua elemen lain */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
            /* Latar belakang gelap semi-transparan */
        }
    </style>

    <script>
// Function to open the modal and display the image
function openModal(imageSrc) {
    document.getElementById("modalImage").src = imageSrc;
    document.getElementById("imageModal").style.display = "flex";  // Show modal
}

// Close modal if clicking outside the image
document.addEventListener('click', function (event) {
    var modal = document.getElementById("imageModal");
    if (modal.style.display === "flex" && event.target === modal) {
        modal.style.display = 'none';
    }
});
        function w3_open() {
            document.getElementById("mySidebar").classList.add("show");
            document.getElementById("sidebarOverlay").classList.add("show");
        }

        function w3_close() {
            document.getElementById("mySidebar").classList.remove("show");
            document.getElementById("sidebarOverlay").classList.remove("show");
        }

        function deleteMenu(idmenu, nama) {
                var modal = document.getElementById('deleteModal');
                modal.style.display = 'block'; // Display the delete confirmation modal
                var modalMessage = modal.querySelector('p');
                modalMessage.textContent = "Apakah Anda yakin ingin menghapus menu '" + nama + "'?";
                var confirmButton = modal.querySelector('.w3-button.w3-red');
                confirmButton.onclick = function () {
                    // Redirect to the PHP script for deletion
                    window.location.href = "list_menu.php?action=delete&id=" + encodeURIComponent(idmenu);
                };
            }

        function closeModal(event) {
            if (event.target === document.getElementById('deleteModal')) {
                document.getElementById('deleteModal').style.display = 'none';
            }
        }

        document.getElementById('errorModal').style.display = 'block';
        
        function closeModal1(event) {
            const modal = document.getElementById('errorModal');
            if (event && event.target !== modal) {
                // Clicked outside the modal content
                return;
            }
            modal.style.display = 'none';
        }
    </script>
</body>

</html>