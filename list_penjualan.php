<?php
// Koneksi ke database
include 'koneksi.php';
session_start();

if (!isset($_SESSION["username"])) {
    header('Location: index.php');
    exit;
}

// Cek apakah pengguna adalah admin atau user biasa
$username = $_SESSION['username'];

// Mengambil input pencarian dari user
$search_keyword = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Pagination setup
$limit = 10; // Number of records per page
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$start = ($page > 1) ? ($page * $limit) - $limit : 0;

// Query untuk menghitung total data
$total_records_query = "SELECT COUNT(*) AS total FROM penjualan WHERE idpenjualan LIKE '%$search_keyword%'";
$total_records_result = mysqli_query($conn, $total_records_query);
$total_records = mysqli_fetch_assoc($total_records_result)['total'];
$total_pages = ceil($total_records / $limit);

// Query untuk mendapatkan data penjualan tanpa IDTOKO dan void
$query = "SELECT * FROM penjualan WHERE idpenjualan LIKE '%$search_keyword%' ORDER BY idpenjualan DESC LIMIT $start, $limit";
$result = mysqli_query($conn, $query);

// Prepare an array to store the order details
$penjualan_details = [];

// Fetch all the orders
while ($row = mysqli_fetch_assoc($result)) {
    $idpenjualan = $row['idpenjualan'];

    // Get detailed order information from detilpenjualan for each order
    $details_query = "SELECT dp.idmenu, m.userrecord, m.nama AS namamenu, dp.harga, dp.jumlah, dp.total
                    FROM detilpenjualan dp
                    JOIN menu m ON dp.idmenu = m.idmenu
                    WHERE dp.idpenjualan = '$idpenjualan'";
    $details_result = mysqli_query($conn, $details_query);

    $details = [];
    while ($detail_row = mysqli_fetch_assoc($details_result)) {
        $details[] = $detail_row; // Store each detail in an array
    }

    // Store the order along with its details
    $penjualan_details[] = [
        'penjualan' => $row,
        'details' => $details
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List Penjualan</title>
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
    <a href="list_menu.php" class="w3-bar-item w3-button w3-border w3-hover-green">
        <i class="fas fa-utensils"></i> <span class="menu-text">List Menu</span>
    </a>
    <?php if ($_SESSION['username'] == 'admin') { ?>
    <a href="history_stok.php" class="w3-bar-item w3-button w3-border w3-hover-green">
        <i class="fas fa-history"></i> <span class="menu-text">History Stok</span>
    </a>
    <?php } ?>
    <a href="#" class="w3-bar-item w3-button w3-border w3-hover-green">
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


    <!-- Page Content -->
    <div id="mainContent" style="margin-left: 0; transition: margin-left 0.5s;">
        <div class="w3-green sticky-header" style="display: flex; align-items: center; padding: 10px;">
            <button class="w3-button w3-xlarge" onclick="w3_open()">☰</button>
            <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: center;">
                <h1
                    style="margin: 0; line-height: 1.5rem; text-align: center; font-size: 30px; margin-top:5px; margin-bottom: 10px;">
                    <b>List Penjualan</b>
                </h1>
                <div style="display: flex; font-size: 14px;">
                    <div style="flex: 1; text-align: left;">
                    <span style="font-size: 14px;">
                    <span style=" color: red;text-shadow: 2px 2px 4px black;">No Nota</span> 
                    </span> <br>
                        <span style="font-size: 15px; font-weight: bold;">NAMA</span> <br>
                        <span style="font-size: 15px; font-weight: bold;">TANGGAL</span> <br>
                    </div>
                    <div style="flex: 1; text-align: center; margin-right: 5%">
                        <br> <span style="font-size: 15px; font-weight: bold;">TOTAL</span>
                    </div>
                    <div style="flex: 1; text-align: center;">
                        <br> <span style="font-size: 15px; font-weight: bold;">BUKTI</span>
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
        <input type="text" name="search" class="w3-input w3-border" placeholder="Cari No Nota..."
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
    <span class="w3-bar-item">Total: <?php echo $total_records; ?> Penjualan</span>
</div>

       <!-- Table of penjualan -->
<div class="w3-responsive">
    <table class="w3-table-all w3-centered" border="1" style="border-collapse: collapse; width: 100%;">
        <?php foreach ($penjualan_details as $order_data): ?>
            <?php $row = $order_data['penjualan']; ?>

            <tr>
                <td style="font-size: 14px; text-align: left; width: 27%">
                    <span class="w3-text-red"><?php echo htmlspecialchars($row['idpenjualan']); ?></span> <br>
                    <span style="font-size: 20px; font-weight: bold;"><?php echo htmlspecialchars($row['nama']); ?></span><br>
                    <span><?php echo date('d-m-Y H:i:s', strtotime($row['tanggal'])); ?></span>
                </td>
                <td style="font-size: 14px; text-align: center; width: 25%">
                    <br>Rp <?php echo number_format($row['grandtotal'], 0, ',', '.'); ?>
                </td>
                <td style="font-size: 14px; text-align: center; width: 30%">
                <?php
    if (!empty($row['buktitransaksi'])) { 
        $image_path = "gambar/buktitransaksi/" . $row['buktitransaksi']; // Ambil nama file dari database
        if (file_exists($image_path)) {
            echo "<img src='$image_path' alt='Gambar menu' style='width: 100px; height: auto; cursor: pointer;' onclick='openModal(\"$image_path\")'>";
        } else {
            echo "<p style='color: red;'>Gambar tidak ditemukan: $row[gambar]</p>"; // Debugging
        }
    } else {
        echo "<p style='color: red;'>Bukti transaksi tidak tersedia</p>";
    }
    ?>
                </td>
                <td class="action-icons" style="font-size: 14px; text-align: center; vertical-align: middle;">
                <div style="display: flex; justify-content: center; align-items: center; height: 100%;">
                <button class="accessButton w3-button w3-round w3-yellow"
                onclick="openViewModal(<?php echo htmlspecialchars(json_encode($order_data), ENT_QUOTES, 'UTF-8'); ?>)">
                 Detail
                </button>
                </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>


<!-- View Details Modal -->
<div id="viewModal" class="w3-modal" style="display: none;">
    <div class="w3-modal-content w3-animate-top w3-card-4" style="max-width: 800px;">
        <header class="w3-container w3-green">
            <h2 class="w3-center"><b>Detail Penjualan</b></h2>
        </header>
        <div class="w3-container">
            <div id="modalContent" class="w3-padding">
                <!-- Content will be inserted dynamically -->
            </div>
        </div>
        <footer class="w3-container w3-light-grey w3-padding">
            <button class="w3-button w3-red w3-round w3-right" onclick="closeViewModal()">Tutup</button>
        </footer>
    </div>
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
            onclick="location.href='penjualan.php'">
            <i class="fa fa-plus w3-xlarge w3-text-white"></i>
        </div>

         <!-- Modal gambar -->
      <div id="imageModal" class="w3-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.4); justify-content: center; align-items: center; z-index: 1000;">
         <img id="modalImage" src="" style="padding: 5px; background-color: white; max-width: 80%; max-height: 80%; cursor: pointer; box-sizing: border-box;">
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

        function updateButtonText() {
    const buttons = document.querySelectorAll(".accessButton"); // Select all buttons with the class accessButton
    buttons.forEach(button => {
        if (window.innerWidth <= 600) {
            button.innerHTML = "Det<br>ail"; // Update text for small screens
        } else {
            button.innerHTML = "Detail"; // Original text for larger screens
        }
    });
}

// Initial call to set the button text
updateButtonText();
// Update text on window resize
window.addEventListener("resize", updateButtonText);
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
        
        function closeModal1(event) {
            const modal = document.getElementById('errorModal');
            if (event && event.target !== modal) {
                // Clicked outside the modal content
                return;
            }
            modal.style.display = 'none';
        }
        function openViewModal(orderData) {
    var modal = document.getElementById("viewModal");
    var modalContent = document.getElementById("modalContent");

    // Clear previous modal content
    modalContent.innerHTML = '';

    // Cek apakah ada bukti transaksi
    var imagePath = orderData.penjualan.buktitransaksi ? `gambar/buktitransaksi/${orderData.penjualan.buktitransaksi}` : null;
    
    // Buat tampilan informasi transaksi
var htmlContent = `
    <div class="w3-card w3-padding">
        <p><strong>No Nota:</strong> ${orderData.penjualan.idpenjualan}</p>
        <p><strong>Tanggal:</strong> ${orderData.penjualan.tanggal}</p>
        <p><strong>Nama:</strong> ${orderData.penjualan.nama}</p>
    </div>

    <h3>Detail Penjualan</h3>
    <table class="w3-table w3-bordered w3-striped w3-hoverable">
        <tr class="w3-green">
            <th>Penjual</th>
            <th>Nama Menu</th>
            <th>Total</th>
        </tr>
`;

orderData.details.forEach(detail => {
    let harga = parseFloat(detail.harga);
    let jumlah = parseInt(detail.jumlah);
    let total = harga * jumlah;

    htmlContent += `
        <tr>
            <td>${detail.userrecord}</td>
            <td>${detail.namamenu}</td>
            <td>${harga.toLocaleString('id-ID')} × ${jumlah} <br> <strong>Rp ${total.toLocaleString('id-ID')}</strong></td>
        </tr>
    `;
});

// Tambahkan Grand Total di baris bawah
htmlContent += `
    <tr class="w3-green">
        <td colspan="2" style="text-align: left;"><strong>Grand Total:</strong></td>
        <td><strong>Rp ${parseFloat(orderData.penjualan.grandtotal).toLocaleString('id-ID')}</strong></td>
    </tr>
`;

htmlContent += '</table>';

    // Tambahkan gambar bukti transaksi di bawah tabel
    if (imagePath) {
        htmlContent += `
            <h3>Bukti Transaksi</h3>
            <div style="text-align: center;">
                <img src="${imagePath}" alt="Bukti Transaksi" style="width: 300px; height: auto; cursor: pointer;" onclick="openImageModal('${imagePath}')">
            </div>
        `;
    } else {
        htmlContent += '<p style="color: red; text-align: center;">Bukti transaksi tidak tersedia</p>';
    }

    modalContent.innerHTML = htmlContent;

    // Display the modal
    modal.style.display = "block";
}

function closeViewModal() {
    document.getElementById("viewModal").style.display = "none";
}

    </script>
</body>

</html>