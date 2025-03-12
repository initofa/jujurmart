<?php
// Koneksi ke database
include 'koneksi.php';
session_start(); 

if (!isset($_SESSION["username"])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION["username"];

// Definisi daftar bulan dalam bahasa Indonesia
$months = [
    1 => "Januari", 2 => "Februari", 3 => "Maret", 4 => "April",
    5 => "Mei", 6 => "Juni", 7 => "Juli", 8 => "Agustus",
    9 => "September", 10 => "Oktober", 11 => "November", 12 => "Desember"
];

// Get available years from penjualan table
$sql_years = "SELECT DISTINCT YEAR(tanggal) AS year FROM penjualan ORDER BY year DESC";
$result_years = mysqli_query($conn, $sql_years);
$years = [];
while ($row = mysqli_fetch_assoc($result_years)) {
    $years[] = $row['year'];
}

// Default values for month and year if not set
$bulan_terpilih = isset($_POST['bulan']) ? (int)$_POST['bulan'] : date('n');
$tahun_terpilih = isset($_POST['tahun']) ? (int)$_POST['tahun'] : date('Y');
$tanggal_terpilih = isset($_POST['tanggal']) ? $_POST['tanggal'] : null;

$query = "
    SELECT 
        p.tanggal, 
        m.nama AS menu_name, 
        SUM(dp.jumlah) AS jumlah_terjual, 
        SUM(dp.jumlah * dp.harga) AS total_pendapatan 
    FROM penjualan p
    JOIN detilpenjualan dp ON p.idpenjualan = dp.idpenjualan
    JOIN menu m ON dp.idmenu = m.idmenu
    WHERE YEAR(p.tanggal) = '$tahun_terpilih'
      AND MONTH(p.tanggal) = '$bulan_terpilih'
";

if ($tanggal_terpilih) {
    $query .= " AND DAY(p.tanggal) = '$tanggal_terpilih'";
}

// Jika bukan admin, hanya tampilkan menu sesuai dengan userrecord
if ($username !== 'admin') {
    $query .= " AND m.userrecord = '$username'";
}

$query .= " GROUP BY p.tanggal, m.idmenu"; // Group by date and menu

$result = mysqli_query($conn, $query);
$penjualan_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $penjualan_data[] = $row;
}

// Ambil total transaksi dengan memperbaiki query
$total_penjualan_query = "
    SELECT COUNT(DISTINCT p.idpenjualan) AS total_penjualan
    FROM penjualan p
    JOIN detilpenjualan dp ON p.idpenjualan = dp.idpenjualan
    JOIN menu m ON dp.idmenu = m.idmenu
    WHERE YEAR(p.tanggal) = '$tahun_terpilih' 
      AND MONTH(p.tanggal) = '$bulan_terpilih'
";

if ($tanggal_terpilih) {
    $total_penjualan_query .= " AND DAY(p.tanggal) = '$tanggal_terpilih'";
}

if ($username !== 'admin') {
    $total_penjualan_query .= " AND m.userrecord = '$username'";
}

$total_penjualan_result = mysqli_query($conn, $total_penjualan_query);
$total_penjualan_row = mysqli_fetch_assoc($total_penjualan_result);
$total_penjualan = $total_penjualan_row['total_penjualan'] ?? 0; // Total unique sales

// Inisialisasi variabel untuk total pcs dan total pendapatan
$total_pcs = 0;
$total_rp = 0;
$labels = [];
$jumlah_terjual = [];
$total_pendapatan = [];

// Query untuk mendapatkan jumlah penjualan per hari dari tabel penjualan
$total_penjualan_per_hari_query = "
    SELECT 
        DATE(tanggal) AS tanggal, 
        COUNT(idpenjualan) AS total_penjualan
    FROM penjualan
    WHERE YEAR(tanggal) = '$tahun_terpilih' 
      AND MONTH(tanggal) = '$bulan_terpilih'
";

if ($username !== 'admin') {
    // Jika bukan admin, filter berdasarkan userrecord di tabel menu
    $total_penjualan_per_hari_query .= "
        AND idpenjualan IN (
            SELECT p.idpenjualan
            FROM penjualan p
            JOIN detilpenjualan dp ON p.idpenjualan = dp.idpenjualan
            JOIN menu m ON dp.idmenu = m.idmenu
            WHERE m.userrecord = '$username'
        )
    ";
}

$total_penjualan_per_hari_query .= " GROUP BY DATE(tanggal)";

$total_penjualan_per_hari_result = mysqli_query($conn, $total_penjualan_per_hari_query);
$total_penjualan_per_hari = [];
while ($row = mysqli_fetch_assoc($total_penjualan_per_hari_result)) {
    $tanggal = date('d-m-Y', strtotime($row['tanggal']));
    $total_penjualan_per_hari[$tanggal] = $row['total_penjualan'];
}

foreach ($penjualan_data as $data) {
    $total_pcs += (int)$data['jumlah_terjual']; // Total pcs terjual
    $total_rp += (float)$data['total_pendapatan']; // Total pendapatan

    // Kumpulkan data untuk chart
    $labels[] = $data['menu_name']; 
    $jumlah_terjual[] = (int)$data['jumlah_terjual'];
    $total_pendapatan[] = (float)$data['total_pendapatan'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" type="image/png" href="jujurmart.png">
    <style>
      .sticky-header {
            position: -webkit-sticky; /* For Safari */
            position: sticky;
            top: 0;
            z-index: 1000;
            background-color: white; /* Ensure background is white to cover content below */
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
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.3);
        }

        .bottom-right i {
            margin: 0;
        }

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
        .chart-container {
            margin: 20px 0;
            padding: 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 99%; /* Lebar 80% dari kontainer */
            margin-left: auto;
            margin-right: auto;
        }
        .table-container {
         margin: 20px 0;
         padding: 20px;
         background: white;
         box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
         width: 60%;
         margin-left: auto;
         margin-right: auto;
        }

      @media (max-width: 600px) {
        .table-container {
        width: 100%;
        margin: 0; 
        padding: 0; 
        box-shadow: none;
        }
      }



/* General button styles */
.btn-toggle {
    padding: 12px 30px;
    font-size: 18px;
    font-weight: bold;
    border: none;
    border-radius: 25px;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease-in-out;
    outline: none;
    margin: 0 10px;
}

/* Button for Penjualan with green gradient */
#penjualanBtn {
    background: linear-gradient(to right, #66BB6A, #4CAF50); /* Lighter green gradient */
}

/* Button for Pembelian (Detil) with yellow gradient */
#detilBtn {
    background: linear-gradient(to right, #FFEB3B, #FBC02D); /* W3-yellow gradient */
}

/* Hover effect for buttons */
.btn-toggle:hover {
    transform: scale(1.1);
    box-shadow: 0 5px 15px rgba(66, 165, 245, 0.5);
}

/* Hover effect for Penjualan button */
#penjualanBtn:hover {
    background: linear-gradient(to right, #4CAF50, #388E3C); /* Slightly darker green on hover */
}

/* Hover effect for Pembelian button */
#detilBtn:hover {
    background: linear-gradient(to right, #FBC02D, #F9A825); /* Slightly darker yellow on hover */
}

/* Active state for the selected button */
.btn-toggle.active {
    box-shadow: 0 5px 15px rgba(30, 136, 229, 0.7);
}

/* Active state for Penjualan button */
#penjualanBtn.active {
    background: linear-gradient(to right, #388E3C, #2E7D32); /* Darker green for active state */
}

/* Active state for Pembelian button */
#detilBtn.active {
    background: linear-gradient(to right, #F9A825, #F57F17); /* Darker yellow for active state */
}


.w3-container form select, .w3-container form input[type="submit"] {
    padding: 8px 12px;
    margin: 5px 0; /* Uniform margin */
    font-size: 16px;
    border: 1px solid #ccc; /* Light gray border for non-submit elements */
    border-radius: 4px;
    transition: all 0.3s ease;
}

/* Hover effect */
.w3-container form select:hover, .w3-container form input[type="submit"]:hover {
    border-color: #0B3D02; /* Darker green border on hover */
    box-shadow: 0 0 5px rgba(11, 61, 2, 0.7); /* Darker green glow on hover */
}

/* Submit button styling */
.w3-container form input[type="submit"] {
    background-color: #0A2901; /* Very dark green */
    color: #fff; /* White text for contrast */
    cursor: pointer;
}

/* Select dropdown styling */
.w3-container form select {
    background-color: #fff; /* White background for select input */
    color: #333; /* Dark text for better readability */
}

    /* Additional mobile responsiveness */
    @media (max-width: 600px) {
        .btn-toggle {
            padding: 10px 20px;
            font-size: 16px;
        }
    }
        /* Styling untuk tampilan mirip gambar */
.info-box {
    background: white;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 15px;
    font-size: 18px;
}
.info-box p {
    margin: 5px 0;
    font-weight: bold;
}
.info-box span {
    font-weight: normal;
}
/* Pusatkan dropdown */
.dropdown-container {
    display: flex;
    justify-content: center;
    margin: 20px 0;
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
    <a href="#" class="w3-bar-item w3-button w3-border w3-hover-green">
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
 <div class="w3-green sticky-header" style="display: flex; flex-direction: column; align-items: center; justify-content: flex-start;">
    <!-- Menu Button and Title in a Row (Horizontal) -->
    <div style="display: flex; width: 100%; align-items: center; justify-content: center;">
        <!-- Menu Button on the Left (moved down) -->
        <button class="w3-button w3-xlarge" onclick="w3_open()" style="margin-left: 10px; margin-top: 30px;">☰</button>
        
        <!-- Title in the Center -->
        <h1 style="margin: 0; line-height: 1rem; flex-grow: 1; text-align: center; padding-right:60px;">
            <b>Dashboard</b>
        </h1>
    </div>
   <!-- Form untuk memilih bulan dan tahun -->
<div class="w3-container w3-center">
    <form method="POST" id="reportForm">
        <!-- Dropdown untuk memilih bulan -->
        <select name="bulan" style="padding: 5px; font-size: 14px;">
            <?php foreach ($months as $key => $value): ?>
                <option value="<?= $key ?>" <?= ($bulan_terpilih == $key) ? 'selected' : '' ?>><?= $value ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Dropdown untuk memilih tahun -->
        <select name="tahun" style="padding: 5px; font-size: 14px;">
            <?php foreach ($years as $year): ?>
                <option value="<?= $year ?>" <?= ($tahun_terpilih == $year) ? 'selected' : '' ?>><?= $year ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Tombol submit -->
        <input type="submit" value="Tampilkan" style="padding: 5px 10px; font-size: 14px;">
    </form>
</div>
</div>


<br>

<!-- Buttons to toggle between Penjualan and Detail -->
<div class="w3-center">
    <button class="btn-toggle active" id="penjualanBtn">Penjualan</button>
    <button class="btn-toggle" id="detilBtn">‎ ‎ ‎  ‎Detail‎ ‎ ‎  ‎</button>
</div>

<!-- Chart Container for Penjualan -->
<div class="chart-container" id="penjualanChartContainer">
    <canvas id="penjualanChart" height="400" width="800"></canvas>
</div>

<!-- Table for Penjualan -->
<div class="table-container" id="penjualanTableContainer">
    <h3 class="w3-center"><b>Detail Data Penjualan</b></h3>
    <table class="w3-table-all w3-card-4">
        <thead>
            <tr class="w3-green">
                <th style='text-align:center;'>Bulan</th>
                <th style='text-align:center;'>Jumlah Penjualan</th>
                <th style='text-align:center;'>Jumlah Pcs</th>
                <th style='text-align:center;'>Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <tr class="w3-light-grey">
                <td style='text-align:center;'><?php echo $months[$bulan_terpilih]; ?></td>
                <td style='text-align:center;'><?php echo number_format($total_penjualan, 0, ',', '.'); ?></td>
                <td style='text-align:center;'><?php echo number_format($total_pcs, 0, ',', '.'); ?></td>
                <td style='text-align:center;'><?php echo number_format($total_rp, 0, ',', '.'); ?></td>
            </tr>
        </tbody>
    </table>
</div>

<?php
// Mengelompokkan data berdasarkan menu_name
$grouped_data = [];

foreach ($penjualan_data as $data) {
    $tanggal = date('d-m-Y', strtotime($data['tanggal']));
    $menu = $data['menu_name'];

    if (!isset($grouped_data[$tanggal][$menu])) {
        $grouped_data[$tanggal][$menu] = [
            'jumlah_terjual' => 0,
            'total_pendapatan' => 0
        ];
    }

    // Menjumlahkan jumlah terjual dan total pendapatan
    $grouped_data[$tanggal][$menu]['jumlah_terjual'] += $data['jumlah_terjual'];
    $grouped_data[$tanggal][$menu]['total_pendapatan'] += $data['total_pendapatan'];
}
?>

<!-- Table for Detail Per Tanggal -->
<div class="table-container" id="detilTableContainer" style="display: none;">
    <div class="dropdown-container">
        <select id="tanggalSelect" class="w3-button w3-black w3-center" onchange="updateTable()">
            <option value="">Pilih Tanggal</option>
            <?php
            $tanggal_tercatat = [];
            foreach (array_keys($grouped_data) as $tgl) {
                $timestamp = strtotime(str_replace('-', '/', $tgl));
                $hari = date('l', $timestamp);

                $nama_hari = [
                    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
                    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
                ];
                $hari_id = $nama_hari[$hari];

                echo "<option value='$tgl'>$tgl - $hari_id</option>";
            }
            ?>
        </select>
    </div>

    <h3 class="w3-center"><b>Detail Data Penjualan Per Hari</b></h3>

    <div class="info-box">
        <p><strong>Tanggal:</strong> <span id="infoTanggal">-</span></p>
        <p><strong>Jumlah Penjualan:</strong> <span id="infoJumlah">0</span></p>
        <p><strong>Total (Rp):</strong> <span id="infoTotalRp">Rp 0</span></p>
    </div>

    <table class="w3-table-all w3-card-4" id="detailTable">
        <thead>
            <tr class="w3-green">
                <th style="text-align:center;">Menu</th>
                <th style="text-align:center;">Jumlah Pcs</th>
                <th style="text-align:center;">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody id="detilTableBody">
            <?php foreach ($grouped_data as $tanggal => $menus) { 
                foreach ($menus as $menu => $info) { ?>
                    <tr class="data-row" data-tanggal="<?= $tanggal; ?>" style="display: none;">
                        <td style="text-align:center;"><?= $menu; ?></td>
                        <td style="text-align:center;"><?= number_format($info['jumlah_terjual'], 0, ',', '.'); ?></td>
                        <td style="text-align:center;">Rp <?= number_format($info['total_pendapatan'], 0, ',', '.'); ?></td>
                    </tr>
            <?php } } ?>
        </tbody>
    </table>
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
    
        document.addEventListener('DOMContentLoaded', function() {
    const penjualanBtn = document.getElementById('penjualanBtn');
    const detilBtn = document.getElementById('detilBtn');
    
    const penjualanChartContainer = document.getElementById('penjualanChartContainer');
    const penjualanTableContainer = document.getElementById('penjualanTableContainer');
    const detilTableContainer = document.getElementById('detilTableContainer');

    // Pastikan elemen tidak null sebelum diproses
    if (penjualanBtn && detilBtn && penjualanChartContainer && penjualanTableContainer && detilTableContainer) {
        penjualanBtn.addEventListener('click', function() {
            penjualanChartContainer.style.display = 'block';
            penjualanTableContainer.style.display = 'block';
            detilTableContainer.style.display = 'none';
            
            penjualanBtn.classList.add('active');
            detilBtn.classList.remove('active');
        });

        detilBtn.addEventListener('click', function() {
            penjualanChartContainer.style.display = 'none';
            penjualanTableContainer.style.display = 'none';
            detilTableContainer.style.display = 'block';
            
            detilBtn.classList.add('active');
            penjualanBtn.classList.remove('active');
        });
    }
});
  // Inisialisasi Chart.js untuk menampilkan jumlah penjualan per bulan
var ctx = document.getElementById("penjualanChart").getContext("2d");

// Mengambil data dari PHP
var labels = <?php echo json_encode(array_values(array_unique($labels ?? []))); ?>;
var jumlahTerjual = [];
var totalPendapatan = [];

var dataMap = {}; // Menyimpan jumlah terjual per menu
var pendapatanMap = {}; // Menyimpan pendapatan per menu

<?php foreach ($labels ?? [] as $index => $menu) { ?>
    var menu = <?php echo json_encode($menu); ?>;
    var jumlah = <?php echo json_encode($jumlah_terjual[$index] ?? 0); ?>;
    var pendapatan = <?php echo json_encode($total_pendapatan[$index] ?? 0); ?>;
    
    // Jika menu sudah ada di dataMap, tambahkan jumlah dan pendapatan
    if (dataMap[menu]) {
        dataMap[menu] += jumlah;
        pendapatanMap[menu] += pendapatan;
    } else {
        dataMap[menu] = jumlah;
        pendapatanMap[menu] = pendapatan;
    }
<?php } ?>

// Memproses data ke dalam array untuk Chart.js
labels.forEach(menu => {
    jumlahTerjual.push(dataMap[menu]);
    totalPendapatan.push(pendapatanMap[menu]);
});

var penjualanChart = new Chart(ctx, {
    type: "bar",
    data: {
        labels: labels, // Label sumbu X (menu)
        datasets: [
            {
                label: "Jumlah Penjualan", // Label dataset
                backgroundColor:"#4CAF50", // Warna batang kuning (w3-green)
                borderColor: "#388E3C", // Warna border hijau lebih gelap
                borderWidth: 2,
                data: jumlahTerjual, // Data jumlah penjualan per menu
                barThickness: 35 // Ukuran batang lebih kecil
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: "Jumlah Penjualan per Bulan", // Judul utama chart
                font: {
                    size: 20
                }
            },
            legend: {
                display: true,
                position: "top",
                labels: {
                    font: {
                        size: 14
                    },
                    color: "black"
                }
            },
            tooltip: {
                callbacks: {
                    label: function(tooltipItem) {
                        var valuePenjualan = tooltipItem.raw; // Nilai penjualan
                        var pendapatan = pendapatanMap[tooltipItem.label] || 0; // Nilai pendapatan

                        return [
                            "Jumlah: " + valuePenjualan + " pcs", // Jumlah penjualan
                            "Rp " + pendapatan.toLocaleString('id-ID') // Format pendapatan
                        ];
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: "Jumlah", // Keterangan sumbu Y
                    font: {
                        size: 14
                    }
                },
                ticks: {
                    stepSize: 1,
                    font: {
                        size: 14
                    },
                    color: "black"
                }
            },
            x: {
                title: {
                    display: true,
                    text: "Menu", // Keterangan sumbu X
                    font: {
                        size: 14
                    }
                },
                ticks: {
                    font: {
                        size: 14
                    },
                    color: "black"
                }
            }
        }
    }
});

// Menampilkan keterangan total penjualan dan pendapatan di bawah chart
var keteranganContainer = document.getElementById("penjualanKeterangan");
var totalPendapatanSum = totalPendapatan.reduce((a, b) => a + b, 0);
var totalJumlahTerjual = jumlahTerjual.reduce((a, b) => a + b, 0);

keteranganContainer.innerHTML = `
    <b>Jumlah Penjualan:<br>${totalJumlahTerjual} pcs</b><br>
    <b>Jumlah Pendapatan:<br>Rp ${totalPendapatanSum.toLocaleString('id-ID')}</b>
`;

function updateTable() {
    let selectedDate = document.getElementById("tanggalSelect").value;
    let rows = document.querySelectorAll(".data-row");
    let infoTanggal = document.getElementById("infoTanggal");
    let infoJumlah = document.getElementById("infoJumlah");
    let infoTotalRp = document.getElementById("infoTotalRp");

    let totalRp = 0;

    if (selectedDate === "") {
        infoTanggal.innerText = "-";
        infoJumlah.innerText = "0";
        infoTotalRp.innerText = "Rp 0";
        rows.forEach(row => row.style.display = "none");
        return;
    }

    infoTanggal.innerText = selectedDate;

    // Ambil jumlah penjualan untuk tanggal yang dipilih dari data PHP
    let totalPenjualanHari = <?php echo json_encode($total_penjualan_per_hari); ?>;
    let jumlahPenjualan = totalPenjualanHari[selectedDate] || 0;
    infoJumlah.innerText = jumlahPenjualan.toLocaleString("id-ID");

    // Hitung total pendapatan (Rp) untuk tanggal yang dipilih
    rows.forEach(row => {
        let rowDate = row.getAttribute("data-tanggal");
        if (rowDate === selectedDate) {
            row.style.display = "table-row";
            totalRp += parseInt(row.cells[2].innerText.replace(/[^\d]/g, "")) || 0;
        } else {
            row.style.display = "none";
        }
    });

    infoTotalRp.innerText = "Rp " + totalRp.toLocaleString("id-ID");
}
</script>
</body>
</html>
