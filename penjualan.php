<?php
// Koneksi ke database
include 'koneksi.php';
session_start();

// Pastikan search_keyword memiliki nilai sebelum digunakan
$search_keyword = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : "";

// Fungsi untuk menghasilkan idpenjualan baru
function generate_penjualan_id($conn) {
    $current_year = date('y');
    $current_month = date('m');

    $sql = "SELECT IFNULL(MAX(CAST(SUBSTR(idpenjualan, 7, 5) AS UNSIGNED)), 0) as last_number
            FROM penjualan
            WHERE SUBSTR(idpenjualan, 3, 4) = CONCAT('$current_year', '$current_month')";

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        die("Query error: " . mysqli_error($conn));
    }

    $row = mysqli_fetch_assoc($result);
    $last_number = $row['last_number'];
    $new_sequence = str_pad($last_number + 1, 5, '0', STR_PAD_LEFT);
    
    return "PJ" . $current_year . $current_month . $new_sequence;
}

$new_penjualan_id = generate_penjualan_id($conn);
$now = date('Y-m-d H:i:s');
$today = date('Y-m-d\TH:i');

// Hitung total item untuk kategori Makanan (hanya stok > 0)
$sql_count_makanan = "SELECT COUNT(*) AS total FROM menu WHERE jenis = 'Makanan' 
                      AND stok > 0 
                      AND (nama LIKE '%$search_keyword%' OR jenis LIKE '%$search_keyword%')";
$result_count_makanan = mysqli_query($conn, $sql_count_makanan);
if (!$result_count_makanan) {
    die("Query error: " . mysqli_error($conn));
}
$row_count_makanan = mysqli_fetch_assoc($result_count_makanan);
$total_items_makanan = $row_count_makanan['total'];

// Hitung total item untuk kategori Minuman (hanya stok > 0)
$sql_count_minuman = "SELECT COUNT(*) AS total FROM menu WHERE jenis = 'Minuman' 
                      AND stok > 0 
                      AND (nama LIKE '%$search_keyword%' OR jenis LIKE '%$search_keyword%')";
$result_count_minuman = mysqli_query($conn, $sql_count_minuman);
if (!$result_count_minuman) {
    die("Query error: " . mysqli_error($conn));
}
$row_count_minuman = mysqli_fetch_assoc($result_count_minuman);
$total_items_minuman = $row_count_minuman['total'];

// Query untuk mengambil data menu (hanya menu dengan stok > 0)
$query_menu = "SELECT * FROM menu WHERE stok > 0";
$result_menu = mysqli_query($conn, $query_menu);
if (!$result_menu) {
    die("Query error: " . mysqli_error($conn));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari form
    $idpenjualan = $_POST['idpenjualan'];
    $tanggal = $_POST['tanggal'];
    $nama = $_POST['nama'];

    // Konversi grandtotal dan bayar menjadi angka yang benar
    $grandtotal = (float) str_replace(',', '.', str_replace('.', '', $_POST['grandtotal']));
    $bayar = (float) str_replace(',', '.', str_replace('.', '', $_POST['bayar']));
    
    // Hitung kembalian
    $kembalian = $bayar - $grandtotal;

    // Jika kembalian negatif, jadikan 0 (untuk menghindari kesalahan input)
    if ($kembalian < 0) {
        $kembalian = 0;
    }

    // Proses upload gambar bukti transaksi
    $target_dir = "gambar/buktitransaksi/";
    $buktiNamaFile = "";

    if (isset($_FILES["buktitransaksi"]) && $_FILES["buktitransaksi"]["error"] == 0) {
        $file_ext = pathinfo($_FILES["buktitransaksi"]["name"], PATHINFO_EXTENSION);
        $buktiNamaFile = $idpenjualan . "." . $file_ext;
        $target_file = $target_dir . $buktiNamaFile;

        // Validasi jenis file
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array(strtolower($file_ext), $allowed_types)) {
            die("Error: Hanya file JPG, JPEG, PNG, dan GIF yang diperbolehkan.");
        }

        // Upload file
        if (!move_uploaded_file($_FILES["buktitransaksi"]["tmp_name"], $target_file)) {
            die("Error: Gagal mengunggah gambar.");
        }
    } else {
        die("Error: File bukti transaksi tidak diunggah.");
    }

    // Simpan data ke tabel penjualan
    $query = "INSERT INTO penjualan (idpenjualan, tanggal, nama, grandtotal, bayar, kembalian, buktitransaksi) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        die("Error: " . mysqli_error($conn));
    }

    // Binding parameter
    mysqli_stmt_bind_param($stmt, "sssddds", $idpenjualan, $tanggal, $nama, $grandtotal, $bayar, $kembalian, $buktiNamaFile);
    mysqli_stmt_execute($stmt);

    // Simpan detail penjualan ke tabel detilpenjualan dan kurangi stok
    foreach ($_POST['idmenu'] as $key => $idmenu) {
        $namamenu = $_POST['namamenu'][$key];

        // Pastikan harga diformat dengan benar sebelum disimpan
        $harga = (float) str_replace(',', '.', str_replace('.', '', $_POST['harga'][$key])); 
        $jumlah = (int) $_POST['jumlah'][$key];
        $total = $harga * $jumlah;

        // Simpan detail penjualan
        $query_detail = "INSERT INTO detilpenjualan (idpenjualan, idmenu, namamenu, harga, jumlah, total) 
                         VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_detail = mysqli_prepare($conn, $query_detail);
        if (!$stmt_detail) {
            die("Error: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt_detail, "sssdds", $idpenjualan, $idmenu, $namamenu, $harga, $jumlah, $total);
        mysqli_stmt_execute($stmt_detail);

        // Kurangi stok menu
        $query_kurangi_stok = "UPDATE menu SET stok = stok - ? WHERE idmenu = ?";
        $stmt_kurangi_stok = mysqli_prepare($conn, $query_kurangi_stok);
        if (!$stmt_kurangi_stok) {
            die("Error: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt_kurangi_stok, "is", $jumlah, $idmenu);
        mysqli_stmt_execute($stmt_kurangi_stok);
    }

    // Redirect ke halaman sukses
    echo "<script> window.location.href='berhasil.php'; </script>";
    exit();
}
?>

<!-- PHP 
        HTML -->


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penjualan</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="w3.css">
    <link rel="icon" type="image/png" href="jujurmart.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

<style>
    
/*                      CSS WARNA MENU MAKANAN DAN MINUMAN                  */
.filter-buttons {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 20px;
}

/* Tombol Default */
.filter-buttons button {
    position: relative;
    z-index: 1;
    border-radius: 30px; /* Bentuk lebih bulat */
    overflow: hidden;
    color: #218838; /* Warna hijau gelap untuk teks */
    border: 2px solid #218838; /* Border hijau */
    padding: 12px 24px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease-in-out;
    background: #fff; /* Background putih */
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Hover Effect */
.filter-buttons button:hover {
    background: linear-gradient(45deg, #28a745, #32cd32); /* Gradasi hijau */
    color: #fff; /* Teks putih saat hover */
    border: 2px solid #28a745;
    transform: scale(1.05);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
}

/* Efek Cahaya Hover */
.filter-buttons button:after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(40, 167, 69, 0.3), transparent 70%);
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
    animation: glowEffect 3s linear infinite;
}

.filter-buttons button:hover:after {
    opacity: 1;
}

/* Keyframes Animasi Cahaya */
@keyframes glowEffect {
    0% {
        transform: scale(1);
        opacity: 0.6;
    }
    50% {
        transform: scale(1.1);
        opacity: 0.8;
    }
    100% {
        transform: scale(1);
        opacity: 0.6;
    }
}

/* Tombol Aktif */
.filter-buttons button.active {
    background: linear-gradient(45deg, #218838, #28a745);
    color: #fff;
    border: 2px solid #218838;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transform: scale(1.05);
}

/* Hover pada Tombol Aktif */
.filter-buttons button.active:hover {
    background: linear-gradient(45deg, #1e7e34, #218838);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
}

/*                      CSS WARNA MENU MAKANAN DAN MINUMAN                   */



/*                       CSS JUMLAH ITEM DI KERANJANG        HALAMAN UTAMA             */
.cart-buttonn {
    position: relative;
    font-size: 24px;
    background-color: transparent;
    border: none;
    cursor: pointer;
}

.cart-buttonn i {
    color: #000; /* Warna ikon keranjang */
}

#total-items {
    position: absolute;
    top: -8px; /* Sesuaikan posisi agar di atas ikon */
    right: -10px; /* Sesuaikan posisi agar di sebelah kanan ikon */
    background-color: green; /* Warna merah seperti di gambar */
    color: white; /* Warna teks putih */
    padding: 2px 6px;
    border-radius: 50%;
    font-size: 12px;
    font-weight: bold;
    display: inline-block;
}
/*                       CSS JUMLAH ITEM DI KERANJANG     HALAMAN UTAMA                */

/*                  CSS HEADER KERANJANG JUMLAH ITEM            */
#cart-total-header-items {
    background-color:rgb(0, 139, 2); /* Warna merah seperti di gambar */
    color: white; /* Warna teks putih */
    padding: 3px 12px;
    border-radius: 50%;
    font-size: 20px;
    font-weight: bold;
    display: inline-block;
}
/*                   CSS HEADER KERANJANG JUMLAH ITEM            */


 /*                                                 CSS MODAL KERANJANG                                */
    .cart-button {
    position: relative;
    background-color: transparent;
    border: none;
    font-size: 24px;
    cursor: pointer;
}

.cart-button i {
    color: #000; /* Sesuaikan warna */
}

#cart-total-items {
    position: absolute;
    top: -8px; /* Atur posisi agar pas di atas icon keranjang */
    right: -10px; /* Atur posisi agar pas di kanan icon keranjang */
    background-color: green;
    color: white;
    padding: 2px 6px;
    border-radius: 50%;
    font-size: 12px;
    font-weight: bold;
    display: inline-block;
}

.custom-cart-modal-size {
    width: 60%;  /* Lebar modal default 40% pada layar besar */
    max-height: 80vh; /* Batas maksimal tinggi 80% dari viewport */
    margin: auto;
    border-radius: 8px;
    position: fixed;
    top: 10%;  /* Jarak dari atas layar */
    left: 50%; /* Pusatkan secara horizontal */
    transform: translateX(-50%);
    overflow-y: auto; /* Scroll jika konten terlalu tinggi */
    background-color: white;
    box-shadow: 0px 4px 8px rgba(0,0,0,0.2);
}

.cart-checkout-container {
    width: 100%; /* Lebar checkout-container sesuai dengan lebar modal */
    background-color: white;
    padding: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid #ddd;
}


.cart-checkout-button {
    background-color: #ff5722;
    color: white;
    padding: 10px 20px;
    border: none;
    cursor: pointer;
    font-size: 16px;
    border-radius: 25px;
    display: flex;
    justify-content: center;
    align-items: center;
}

@media (max-width: 768px) {
    .custom-cart-modal-size {
        width: 60%;  /* Lebar modal 60% pada layar tablet */
        top: 5%;  
        left: 50%;
        transform: translateX(-50%);
        max-height: 85vh; 
    }

    .cart-checkout-container {
        flex-direction: column; /* Tampilkan dalam kolom pada layar tablet */
        align-items: stretch;
    }

    .cart-checkout-button {
        width: 100%; /* Tombol checkout lebar penuh */
        margin-top: 10px;
    }
}

@media (max-width: 600px) {
    .custom-cart-modal-size {
        width: 95%;  /* Lebar modal 95% pada layar HP */
        top: 5%;
        left: 50%;
        transform: translateX(-50%);
        max-height: 85vh;
    }

    .cart-checkout-button {
        width: 100%;  /* Tombol checkout lebar penuh */
    }
}

.cart-checkout-button .total-price {
    margin-left: 15px;
    font-size: 18px;
    font-weight: bold;
}

.cart-item-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px;
    border: 1px solid #ddd;
    margin-bottom: 10px;
    border-radius: 8px;
    background-color: white;
}

.cart-item-details {
    flex: 1;
    padding-left: 10px;
}

.cart-item-details h6 {
    margin: 0;
    font-weight: bold;
    font-size: 16px;
}

.cart-item-details .item-price {
    color: red;
    font-weight: bold;
}

.cart-item-controls {
    display: flex;
    align-items: center;
}

.cart-item-controls button {
    background-color: transparent;
    border: none;
    font-size: 17px;
    margin: 0 5px;
}

.cart-item-controls .quantity {
    font-weight: bold;
    margin: 0 10px;
}

.cart-item-controls .fa-trash {
    color: red;
    cursor: pointer;
}
/*                                       CSS MODAL KERANJANG                           */




/*                                                    CSS MODAL TAMBAH BARANG                               */
/* Memperkecil tinggi modal */
.custommm-modal-size {
    width: 90%; /* Lebar modal lebih responsif */
    max-width: 400px;
    max-height: 500px;
    overflow-y: auto;
    border-radius: 10px;
    justify-content: center; /* Mengatur agar isi modal berada di tengah secara vertikal */
    margin: auto; /* Margin otomatis untuk memusatkan modal */
}


/* Ukuran gambar produk diperkecil */
.modall-item-image {
    display: flex;
    justify-content: center; /* Pusatkan secara horizontal */
    align-items: center; /* Pusatkan secara vertikal */
    margin-bottom: 10px;
}

.modall-item-image img {
    border-radius: 8px;
    width: 100%; /* Lebar gambar mengikuti kontainer */
    height: auto; /* Tinggi otomatis menjaga proporsi */
    max-width: 150px; /* Max width for larger screens */
    max-height: 150px; /* Max height for larger screens */
}

/* Mengurangi margin dan padding */
.modall-item-details {
    text-align: center;
    padding: 5px 0;
}

.itemm-price {
    font-size: 16px;
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
}

/* Kontrol kuantitas lebih kecil */
.quantity-controlss {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin: 5px 0;
}

.quantity-controlss button {
    padding: 8px;
    font-size: 16px;
}

.quantity-input {
    width: 40px;
    text-align: center;
}

/* Opsi bungkus lebih kecil */
.bungkus-option {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 5px;
    margin-top: 10px;
}

textarea#w3-input {
    margin-top: 5px;
    resize: none;
    height: 60px;
    border: 1px solid #ddd;
    padding: 5px;
    border-radius: 5px;
}

/* Tombol aksi lebih kecil */
.modall-actions {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 5px;
    position: sticky;
    bottom: 0;
    background-color: white; /* Warna latar belakang agar konsisten dengan modal */
    z-index: 100; /* Pastikan tombol ada di atas konten lainnya */
    width: 100%; /* Atur lebar agar tombol mengisi kontainer */
}

/* Ukuran tombol */
.modall-actions button {
    width: 80%;
    padding: 10px;
    font-size: 14px;
    margin: 5px 0;
    border-radius: 20px;
}
/* Switch untuk opsi Bungkus */
.switch {
    position: relative;
    display: inline-block;
    width: 30px;
    height: 16px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.4s;
    border-radius: 20px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 12px;
    width: 12px;
    left: 4px;
    bottom: 2px;
    background-color: white;
    transition: 0.4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #4CAF50;
}

input:checked + .slider:before {
    transform: translateX(12px);
}

/* Media query untuk responsif */
@media (max-width: 600px) {
    .custommm-modal-size {
        width: 80%; /* Modal lebih kecil di perangkat kecil */
        max-width: 350px; /* Maximum width untuk perangkat kecil */
       /* Tambahkan padding agar konten tidak terlalu dekat dengan tepi */
    }

    .modall-item-image img {
        max-width: 100px; /* Mengurangi ukuran gambar di perangkat kecil */
        max-height: 110px; /* Mengurangi ukuran gambar di perangkat kecil */
    }

    .quantity-input {
        width: 25px; /* Lebar input kuantitas lebih kecil di perangkat kecil */
    }

    .modall-actions button {
        font-size: 12px; /* Mengurangi ukuran font tombol di perangkat kecil */
        padding: 13px; /* Mengurangi padding tombol di perangkat kecil */
        border-radius: 20px;
    }
}
/*                                                       CSS MODAL TAMBAH BARANG                    */

/*                                      CSS UNTUK INPUT TANGGAL FORM                  */
    .w3-section {
    margin-bottom: 16px;
  }

  .w3-section strong {
    font-size: 16px;
    color: #333;
  }

  .w3-section input[type="datetime-local"] {
    width: 180px;
    padding: 10px;
    border: none; /* Hilangkan border */
    font-size: 14px;
    color: #333;
    box-sizing: border-box;
  }

  .w3-section input[type="datetime-local"]:focus {
    outline: none; /* Hilangkan outline saat fokus */
  }

  .w3-section input[readonly] {
    cursor: not-allowed;
  }
      /*                                 CSS UNTUK INPUT TANGGAL FORM                         */    


    /*                  CSS UNTUK INPUT GRANDTOTAL FORM               */
    .w3-section {
    margin-bottom: 16px;
    display: flex; /* Menjadikan elemen dalam satu baris */
    align-items: center; /* Mengatur agar label dan input sejajar secara vertikal */
  }

  .w3-section strong {
    font-size: 16px;
    color: #333;
    margin-right: 10px; /* Memberikan jarak antara label dan input */
  }

  .w3-section input[type="text"] {
    width: 180px; /* Atur lebar sesuai kebutuhan */
    padding: 10px;
    border: none; /* Hilangkan border */
    font-size: 14px;
    color: #333;
    box-sizing: border-box;
    background: transparent; /* Menghilangkan background */
  }

  .w3-section input[type="text"]:focus {
    outline: none; /* Hilangkan outline saat fokus */
  }

  .w3-section input[readonly] {
    cursor: not-allowed; /* Tunjukkan bahwa input tidak dapat diedit */
  }
      /*                          $$$$$       CSS UNTUK INPUT GRANDTOTAL FORM     $$$$          */
      
        /*                                  ====== CSS UNTUK MENU ======               */
  /* Gaya yang dimodifikasi untuk sidebar */
  .w3-sidebar {
            z-index: 1100;
            position: fixed;
            left: -250px;
            top: 0;
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
        /* Gaya untuk overlay sidebar */
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
        @media (max-width: 300px) {
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
        .w3-overlay.show {
            display: block;
        }
      /*                      ====== CSS UNTUK MENU ======               */


/*              --------------------    CSS UNTUK ORDER ATAU TABLE  ------------------      */
.order-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 1px 0;
}
.item-details {
  flex-grow: 1;
}
.top-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 5px;
}
.action-buttons {
  display: flex;
  flex-direction: row; /* Align buttons side by side */
  gap: 10px; /* Space between the buttons */

}

.action-buttons button {
    background-color: transparent;
    border: none;
    font-size: 20px;
    margin: 0 5px;
}

.action-buttons .fa-trash {
    color: red;
    cursor: pointer;
}

.bottom-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.left-section {
  display: flex;
  align-items: center;
  gap: 3px; /* Keep price and quantity close */
}
.price-input {
  margin-right: 3px;
}
.quantity-x {
  margin-left: 2px; /* Reduced space around the 'x' */
}
.quantity-input {
  width: 40px;
  text-align: center;
}
.total-input {
  min-width: 2px;
  text-align: right;
  font-weight: bold; /* Highlight the total */
}
.w3-button {
  padding: 5px 10px;
}
/*              --------------------    CSS UNTUK ORDER ATAU TABLE  ------------------      */

/*                                    +++++++++     CSS UNTUK QUANTITY   ++++++++++                       */

.quantity {
    display: flex;
    align-items: center;
}
.quantity-button {
    background-color: 	#228B22;
    color: white;
    border: none;
    padding: 7px 14px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}
.quantity-button:disabled {
    background-color: white;
    cursor: not-allowed;
}
.quantity-button:hover {
    background-color: grey;
}
.quantity-input {
    width: 50px;
    text-align: center;
    border: 1px solid #ced4da;
    border-radius: 4px;
    margin: 0 5px;
    font-size: 16px;
    padding: 5px;
    background-color: #ffffff;
    color: #495057;
    cursor: default;
}
.quantity-input:read-only {
    background-color: #f8f9fa;
}
/*                                    +++++++++     CSS UNTUK QUANTITY   ++++++++++                       */
/*                                  \\\\\\\\    CSS UNTUK SELECT ITEMS     \\\\\\\\\\                     */
.selected-items {
        margin-right: 10px;
        max-height: 100px;
        overflow-y: auto;
        font-size: 12px;
    }
.left-options, .right-options {
        display: flex;
        align-items: center;
    }
.left-options {
        gap: 100px;
    }
.left-options input[type="checkbox"] {
        margin-right: 100px;
    }
.left-options a {
        color: #ff5722;
        text-decoration: none;
    }
/*                                  \\\\\\\\    CSS UNTUK SELECT ITEMS     \\\\\\\\\\                     */
/*                           CSS UNTUK TOTAL DEKAT CHECK OUT               */
.total-section {
        margin-right: 10px;
        font-weight: bold;
    }
.total-section span {
        color: green;
        align-items: right;
    }
/*                           CSS UNTUK TOTAL DEKAT CHECK OUT               */
/*                       CSS UNTUK TOMBOL CHECK OUT               */
.checkout-button {
        background-color: #ff5722;
        color: white;
        padding: 10px 20px;
        border: none;
        cursor: pointer;
        font-size: 16px;
    }
.checkout-container {
        position: fixed;
        bottom: 0;
        width: 100%;
        background-color: white;
        padding: 1px;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: right;
        align-items: right;
        z-index: 1000;
    }
.checkout-button {
        background-color: green;
        color: white;
        border: none;
        padding: 15px 30px;
        border-radius: 25px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: right;
    }
.checkout-button .total-price {
        margin-left: 15px;
        font-size: 18px;
        font-weight: bold;
    }
.custom-modal-size {
        width: 100%;
        height: 100vh;
        max-width: 100%;
        max-height: 100%;
        margin: 0;
        border-radius: 0;
        position: fixed;
        top: 0;
        left: 0;
        overflow: hidden;
    }
.table-container {
        max-height: calc(79vh - 60px);
        overflow-y: auto;
        padding: 10px;
    }

    /* Responsive layout untuk layar dengan lebar maksimal 600px */
@media (max-width: 600px) {
  .table-container {
    max-height: calc(76.3vh - 50px); /* Mengurangi tinggi untuk layar kecil */
    padding: 1px; /* Kurangi padding untuk ruang lebih */
    width: 100%; /* Memastikan kontainer mengambil seluruh lebar layar */
  }
}

/* Responsive layout untuk layar dengan lebar maksimal 400px */
@media (max-width: 400px) {
  .table-container {
    max-height: calc(76.3vh - 50px); /* Lebih rendah lagi untuk layar yang sangat kecil */
    padding: 1px; /* Padding lebih kecil di layar sangat kecil */
    width: 100%; /* Tetap menggunakan seluruh lebar layar */
  }
}


.w3-modal {
        z-index: 9999;
    }
/*                       CSS UNTUK TOMBOL CHECK OUT               */
/*                 ;;;;;;;;         CSS UNTUK HEADER TETAP  ;;;;;               */
.fixed-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 3.5rem;
        z-index: 1000;
        }
        /* Menyesuaikan padding container agar tidak tertutup header */
.content-container {
        padding-top: 3.5rem;
        }
/*                 ;;;;;;;;         CSS UNTUK HEADER TETAP  ;;;;;               */
 /*                                  CSS UNTUK CHECK BOX                  */
.checkbox-container {
    display: flex;
    align-items: center;
    }
.custom-checkbox {
    width: 30px;
    height: 30px;
    margin-left: 10px;
/* Sesuaikan jarak antara label dan checkbox */
    }
 /*                                  CSS UNTUK CHECK BOX                  */


/*              CSS UNTUK TOTAL INPUT DI ORDER ITEM ATAU TOTAL TABLE                */
    .total-input {
  width: 110px; /* Atur lebar sesuai kebutuhan */
  padding: 1px;
  border: none; /* Hilangkan border */
  font-size: 14px;
  color: green; /* Menggunakan warna hijau untuk teks */
  box-sizing: border-box;
  background: transparent; /* Menghilangkan background */
}

.total-input:focus {
  outline: none; /* Hilangkan outline saat fokus */
}

.total-input[readonly] {
  cursor: not-allowed; /* Tunjukkan bahwa input tidak dapat diedit */
}
/*              CSS UNTUK TOTAL INPUT DI ORDER ITEM ATAU TOTAL TABLE                */
    


/*                      CSS UNTUK JUMLAH DI TABLE ATAU ORDER ITEMS                */
.quantity-input {
 /* Atur lebar sesuai kebutuhan */
  padding: 10px;
  border: none; /* Hilangkan border */
  font-size: 14px;
  color: green; /* Warna teks sesuai permintaan */
  font-weight: bold; /* Mengatur font menjadi tebal */
  box-sizing: border-box;
  background: transparent; /* Menghilangkan background */
}

.quantity-input:focus {
  outline: none; /* Hilangkan outline saat fokus */
}

.quantity-input[readonly] {
  cursor: not-allowed; /* Tunjukkan bahwa input tidak dapat diedit */
}
/*                      CSS UNTUK JUMLAH DI TABLE ATAU ORDER ITEMS                */





.button {
    padding: 12px 24px;
    border: none;
    border-radius: 9px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.3s ease, box-shadow 0.3s ease;
    outline: none; /* Menghilangkan outline ketika tombol ditekan */
}

.button.w3-green {
    background-color: #4CAF50;
    color: white;
}

.button.w3-green:hover {
    background-color: #45a049;
    transform: scale(1.05); /* Membesarkan sedikit tombol saat hover */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Menambahkan shadow saat hover */
}

.button.w3-green:active {
    background-color: #3e8e41; /* Warna saat tombol ditekan */
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); /* Shadow lebih kecil saat tombol ditekan */
    transform: scale(1); /* Mengembalikan ukuran tombol ke normal saat ditekan */
}

.button i {
    margin-right: 1px;
}

.button span {
    font-size: 14px;
    display: block;
    margin-top: 1px;
    font-weight: normal;
}

/* Responsive Styling */
@media screen and (max-width: 600px) {
    .button {
        font-size: 14px;
        padding: 4px 20px;
    }

    .button span {
        font-size: 12px;
    }
}

</style>

</head>


<!--HTML
      HAlAMAN UTAMA -->


<!-- MODAL HALAMAN UTAMA -->
<div id="barangModal" class="w3-modal">
    <div class="w3-modal-content w3-card-4 custom-modal-size">
    <header class="w3-container w3-green" style="text-align: center;">
    <h2><b>DAFTAR MENU</b></h2>
    <div class="filter-buttons" style="display: flex; justify-content: center; gap: 20px; align-items: center;">
        <button type="button" class="w3-button w3-light-grey" style="font-weight: bold;" id="makananButton">
            Makanan <span class="w3-item">(<?php echo $total_items_makanan; ?>)</span>
        </button>
        <button type="button" class="w3-button w3-light-grey" style="font-weight: bold;" id="minumanButton">
            Minuman <span class="w3-item">(<?php echo $total_items_minuman; ?>)</span>
        </button>
    </div>
    </header>
        <div class="w3-container">
        <div class="table-container">
    <table class="w3-table-all">
        <tbody id="barangTableBody">
            <?php while ($row_menu = mysqli_fetch_assoc($result_menu)) { 
                $image_name = $row_menu['gambar']; // Nama file gambar dari database
                $image_path = "gambar/menu/" . $image_name; // Path lengkap gambar
                
                // Cek apakah gambar ada
                if (!empty($image_name) && file_exists($image_path)) {
                    $display_image = $image_path;
                } else {
                    $display_image = 'gambar/menu/default_image.png'; // Gambar default jika tidak ditemukan
                }
            ?>
            <tr>
                <td style="text-align: left;">
                    <img src="<?php echo htmlspecialchars($display_image); ?>" class="item-image" 
                         style="width: 80px; height: auto; cursor: pointer;" 
                         onclick="openModal('<?php echo htmlspecialchars($display_image); ?>')">
                </td>
                <td style="font-size: 14px; text-align: left; word-wrap: break-word; max-width: 180px; overflow: hidden; text-overflow: ellipsis;">
                    <div class="item-details">
                        <span style="color: red; font-weight: bold; display: none;"> <?php echo htmlspecialchars($row_menu['idmenu']); ?> </span>
                        <span style="font-weight: bold;"> <?php echo htmlspecialchars($row_menu['nama']); ?> </span><br>
                        <span class="item-price" style="font-size: 16px; font-weight: bold; color: green;" 
                              data-price="<?php echo $row_menu['harga']; ?>">
                            Rp <?php echo number_format($row_menu['harga'], 0, ',', '.'); ?>
                        </span>
                        <span id="hiddenText" style="color: #009688; font-weight: bold; display: none;"> 
                            <?php echo htmlspecialchars($row_menu['jenis']); ?> 
                        </span><br>
                    </div>
                </td>
                <td style="text-align: center;">
                    <button style="font-weight: bold;" type="button" class="button w3-green" 
                        onclick="openProductModal('<?php echo htmlspecialchars($row_menu['idmenu']); ?>', 
                                                  '<?php echo htmlspecialchars($row_menu['nama']); ?>', 
                                                  '<?php echo number_format($row_menu['harga'], 0, ',', '.'); ?>', 
                                                  '<?php echo htmlspecialchars($display_image); ?>')">
                        <i class="fa fa-shopping-cart"></i> Tambah <br><span>Keranjang</span>
                    </button>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
</div>
        <div class="checkout-container">
            <div class="left-options"></div>
            <div class="right-options">
                <button class="cart-buttonn" onclick="openCartModal()">
                    <i class="fa fa-shopping-cart"></i>
                    <span id="total-items">0</span>
                </button>
                <div class="selected-items">
                    <span id="selected-items"></span>
                </div>
                <div class="total-section">
                    TOTAL : <span id="total-price">Rp.0</span>
                </div>
                <button id="checkout-button" class="checkout-button w3-button w3-green" onclick="closeBarangModal()" disabled>CHECK OUT</button>
            </div>
        </div>
    </div>
</div>
<!-- END MODAL HALAMAN UTAMA -->


<!--                                             MODAL KERANJANG                                         -->
<div id="cartModal" class="w3-modal" onclick="closeModalOnClickOutside(event)">
    <div class="w3-card-4 custom-cart-modal-size">
        <header class="w3-container w3-green">
            <span onclick="closeCartModal()" class="w3-button w3-display-topright">&times;</span>
            <h4><b>KERANJANG <span id="cart-total-header-items">0</span></b></h4>
        </header>

        <div class="w3-container">
            <div class="cart-items">
                <!-- Daftar item keranjang akan diisi oleh JavaScript -->
                <div id="cartItemsContainer"></div>
            </div>
        </div>
        
        <div class="cart-checkout-container">
            <div class="cart-left-options">
                <!-- Any additional options -->
            </div>
            <div class="cart-right-options">
                <div class="cart-total-section">
                    <button class="cart-button" onclick="openCartModal()">
                        <i class="fa fa-shopping-cart"></i>
                        <span id="cart-total-items">0</span> <!-- Ganti angka dengan jumlah dinamis -->
                    </button>

                    TOTAL: <span id="cart-total-price">Rp.0</span>
                </div>
                <button class="cart-checkout-button w3-button w3-green" 
                    id="checkoutButton" 
                    href="javascript:void(0);" 
                    onclick="closeAllModals();">
                    Bayar 
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function closeCartModal() {
    document.getElementById("cartModal").style.display = "none";
}

function closeModalOnClickOutside(event) {
    if (event.target.id === "cartModal") {
        closeCartModal();
    }
}
</script>

<!--                                             MODAL KERANJANG                                         -->


<!-- MODAL TAMBAH BARANG -->
<div id="productModal" class="w3-modal">
    <div class="w3-modal-content w3-card-4 custommm-modal-size w3-animate-zoom">
        <header class="w3-container w3-green">
            <span onclick="closeProductModal()" class="w3-button w3-display-topright">&times;</span>
            <h2><b><center>Detail Menu</center></b></h2>
        </header>
        <br>
        <div class="w3-container">
            <div class="modall-item-image">
                <img id="productImage" alt="Product Image"> <!-- Gambar produk diambil dari file -->
            </div>
            <div class="modall-item-details">
                <h3 id="productTitle">Nama</h3>

                <!-- ID Menu disembunyikan -->
                <p id="kodebarangContainer" style="display: none;">
                    <b>ID Menu: <span id="kodebarangDisplay"></span></b>
                </p>

                <p id="productPrice" class="itemm-price"><span id="productTotalPrice">0</span></p>
                
                <div class="quantity-controlss">
                    <button class="w3-button" onclick="decrement(this)">
                        <i class="fa fa-minus-circle"></i>
                    </button>
                    <input type="text" class="quantity-input w3-light-grey" value="1" readonly>
                    <button class="w3-button" onclick="increment(this)">
                        <i class="fa fa-plus-circle"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <div class="modall-actions">
            <button class="add-button w3-button w3-green" style="font-weight: bold;" onclick="addToCart()"> 
                <i class="fa fa-shopping-cart"></i> MASUKKAN KERANJANG
            </button>
            <button class="w3-button w3-grey" onclick="closeProductModal()">BATALKAN</button>
        </div>
    </div>
</div>
<!-- END MODAL TAMBAH BARANG -->


<!-- MODAL UNTUK MENGISI NAMA -->
<div id="id01" class="w3-modal" style="display: none;">
  <div class="w3-modal-content w3-animate-top w3-card-4" style="max-width: 400px; border-radius: 10px; overflow: hidden;">
    <header class="w3-container w3-green w3-padding-16" style="border-top-left-radius: 10px; border-top-right-radius: 10px;">
      <span onclick="document.getElementById('id01').style.display='none'" 
      class="w3-button w3-display-topright w3-hover-red" style="font-size: 20px; font-weight: bold;">&times;</span>
      <h2 class="w3-center">⚠️ Eitss, isi nama dulu!</h2>
    </header>

    <div class="w3-container w3-padding">
      <form id="modalForm" method="POST" action="create_transaction.php">
        <div class="w3-section">
          <label for="modalNama" class="w3-text-grey" style="font-weight: bold;">Nama :</label>
          <input type="text" class="w3-input w3-border w3-round-large" id="modalNama" name="nama" placeholder="Masukkan nama" required style="width: 82%;">
        </div>
        <button type="button" class="w3-button w3-green w3-round-large w3-block w3-hover-light-green" 
        id="submitModalButton" onclick="submitModalData()" style="font-size: 16px; padding: 10px;" disabled>
          Simpan
        </button>
      </form>
    </div>
  </div>
</div>
<!-- MODAL UNTUK MENGISI NAMA -->

<!-- SCRIPT UNTUK MENGISI NAMA -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Tambahkan event listener ke input nama untuk validasi
    document.getElementById('modalNama').addEventListener('input', validateModalFields);
});

function openModal() {
    document.getElementById('id01').style.display = 'block';
    validateModalFields(); // Pastikan tombol sesuai kondisi saat modal dibuka
}

function validateModalFields() {
    var nama = document.getElementById('modalNama').value.trim();
    var submitButton = document.getElementById('submitModalButton');

    // Aktifkan tombol jika nama terisi
    submitButton.disabled = !nama;
}

function submitModalData() {
    var nama = document.getElementById('modalNama').value.trim();

    if (nama) {
        // Pastikan ada input hidden dalam form utama
        var hiddenNama = document.getElementById('hiddenNama');
        if (!hiddenNama) {
            hiddenNama = document.createElement('input');
            hiddenNama.type = 'hidden';
            hiddenNama.id = 'hiddenNama';
            hiddenNama.name = 'nama';
            document.forms['mainForm'].appendChild(hiddenNama);
        }
        
        // Setel nilai input hidden dengan nama yang diisi
        hiddenNama.value = nama;

        // Tutup modal
        document.getElementById('id01').style.display = 'none';

        // Kirim form utama
        document.forms['mainForm'].submit();
    } else {
        alert('Nama harus diisi!');
    }
}
</script>
<!-- SCRIPT UNTUK MENGISI NAMA -->

<!--                                             TOMBOL UNTUK MENU DAN HEADER                              -->
    <!-- Overlay Sidebar -->
    <div id="sidebarOverlay" class="w3-overlay" onclick="w3_close()"></div>
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
    <a href="logout.php" class="w3-bar-item w3-button w3-red w3-center">
    <b>Log Out </b> <i class="fas fa-sign-out-alt" style="font-size:20px"></i>
    </a>
    </div>

    <!--                                   -------     HEADER          -------                         -->
    <div class="w3-green fixed-header" style="display: flex; align-items: center;">
        <button class="w3-button w3-xlarge" onclick="w3_open()">☰</button>
        <div style="flex-grow: 1; display: flex; justify-content: center;">
            <h1 style="margin: 0; line-height: 3.5rem; margin-bottom:10px; font-size: 30px;"><b>CHECK OUT</b></h1>
        </div>
    </div>
        <!--                             --------       HEADER        ---------                           -->
    <!--                                          TOMBOL UNTUK MENU DAN HEADER                              -->
<br>
<!--                                ======+    FORM UNTUK MENGIRIM DATA KE DATABSAE      +=====                  -->
<div class="w3-container content-container">
<form id="mainForm" method="POST" action="penjualan.php" enctype="multipart/form-data">
<input type="hidden" name="idpenjualan" value="<?php echo $new_penjualan_id; ?>">
    <div class="w3-section">
        <strong>TANGGAL:</strong>
        <input type="datetime-local" id="tanggal" name="tanggal" value="<?php echo $today; ?>" readonly>
    </div>

<!--                INPUT TERSEMBUNYI UNTUK MENGIRIMKAN DATA NAMA DAN KE DATABASE            -->
<input type="hidden" id="hiddenNama" name="nama">




<!-- ========    DIV UNTUK RESPONSIVE MY-2  ======   -->
<div class="table-responsive">
<div class="my-2">
</div>

<!--                    FORM TABLE              -->
<table class="table">
    <thead>
        <tr>
            <th>
                <div style="display: flex; font-size: 14px;">
                <div style="flex: 1; text-align: left;">
                <span style="font-size: 16px; color: black; font-weight: bold;">NAMA</span><br><span
                    style="font-size: 16px; color: red; font-weight: bold;">HARGA</span>
                    <span style="font-size: 16px; color: green; font-weight: bold;">JUMLAH</span> <br>
                </div>
            </th><!-- <th>Jumlah</th> -->
            <th style="text-align: right; color: black;"><b>AC</b><span style="color: black;"><b>TION</b></span><br>
                <span style="color: green;"><b>TOTAL</b></span>
            </th>
        </tr>
    </thead>
        <tbody id="orderItems">
            <tr>

            </tr>
        </tbody>
</table>
 <!--                               FORM TABLE                      -->            

<div class="my-2">
<!-- ========    DIV UNTUK RESPONSIVE MY-2  ======   -->

</div>
</div>

<!--            TOMBOL TAMBAH PESANAN LAINNYA                -->
    <div>
    <button type="button" class="w3-button w3-green" onclick="openBarangModal()" style="border-radius: 9px;">
        <i class="fa fa-shopping-cart"></i> TAMBAH LAINNYA
    </button>
    </div>
<!--            TOMBOL TAMBAH PESANAN LAINNYA                -->

    <div class="w3-section">
        <strong>GRAND TOTAL: Rp</strong>
        <input type="text" class="form-control" id="grandtotal" name="grandtotal" readonly>
    </div>

    <div class="form-group">
    <label for="bayar">BAYAR:</label>
    <input type="text" id="bayar" name="bayar" class="form-control" oninput="formatInputBayar(this)" onchange="togglePayButton()">
    </div>

    <div class="form-group">
    <label for="kembalian">KEMBALIAN</label>
    <input type="text" id="kembalian" name="kembalian" class="form-control" readonly>
    </div>
    
    <label>BUKTI TRANSAKSI :</label>
    <input type="file" class="w3-input w3-border w3-light-grey" 
       name="buktitransaksi" id="gambarUpload" accept="image/*" capture="camera" 
       onchange="previewFile(); togglePayButton()" required><br>

    <!-- Preview Gambar -->
    <div style="display: flex; flex-direction: column; align-items: center;">
    <img id="previewImage" src="" alt="Preview Gambar Baru" 
         style="max-width: 200px; height: auto; display: none; margin-bottom: 10px;">
    <div id="noImageText" class="w3-text-red" style="text-align: center;">
        Harap unggah bukti transaksi: <br>
        <small>- Foto produk yang telah dibeli</small> <br>
        <small>- Foto uang pembayaran beserta kembaliannya</small>
    </div>
    </div><br>

    <!-- Tombol SELESAI -->
    <div style="display: flex; justify-content: center; margin-top: 20px;">
    <button type="button" id="payButton" onclick="openModal()" disabled
        style="background-color: #28a745; color: white; font-size: 18px; font-weight: bold; padding: 12px 24px; border: none; border-radius: 10px; 
              cursor: not-allowed; opacity: 0.5; transition: all 0.3s ease-in-out; box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);"
        onmouseover="this.style.backgroundColor='#218838'; this.style.transform='scale(1.05)';" 
        onmouseout="this.style.backgroundColor='#28a745'; this.style.transform='scale(1)';">
        SELESAI
    </button>
</div>

 </form>
        <br>
<!--                                    ======+    FORM UNTUK MENGIRIM DATA KE DATABSAE      +=====                  --> 
<script> 
/*                    ==============  SCRIPT UNTUK MODAL BARANG ATAU HALAMAN UTAMA -=========               */
function w3_open() {
            document.getElementById("mySidebar").classList.add("show");
            document.getElementById("sidebarOverlay").classList.add("show");
        }

        function w3_close() {
            document.getElementById("mySidebar").classList.remove("show");
            document.getElementById("sidebarOverlay").classList.remove("show");
        }
function openBarangModal() {
    document.getElementById('barangModal').style.display = 'block';
    localStorage.setItem('modalState', 'open'); // Save modal state as 'open' in localStorage
}
// Function to close the barang modal
function closeBarangModal() {
    document.getElementById('barangModal').style.display = 'none';
    localStorage.setItem('modalState', 'closed'); // Save modal state as 'closed' in localStorage
}
// Check the modal state on page load and open the modal if needed
function checkModalState() {
    const modalState = localStorage.getItem('modalState');
    if (modalState === 'open') {
        openBarangModal();
    }
}
// Call the function to check the modal state when the page loads
window.addEventListener('load', checkModalState);


// script untuk refresh
window.onload = function() {
        var modal = document.getElementById("barangModal");
        var makananButton = document.getElementById("makananButton");
        var span = document.getElementsByClassName("close")[0];

        // Tampilkan modal ketika halaman dimuat
        modal.style.display = "block";

        // Tutup modal ketika pengguna menekan tombol close (x)
        if (span) {
            span.onclick = function() {
                modal.style.display = "none";
            }
        }
        // Tutup modal jika pengguna mengklik di luar modal
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
        // Klik otomatis pada tombol Makanan setelah modal sepenuhnya dimuat
        setTimeout(function() {
            if (makananButton) {
                makananButton.click();
            }
        }, 100); // Delay in milliseconds
    }

    function filterCategory(category) {
        var table, tr, td, i, itemCategory;

        table = document.querySelector('.w3-table-all');
        tr = table.getElementsByTagName('tr');

        for (i = 0; i < tr.length; i++) {
            td = tr[i].getElementsByTagName('td');
            if (td.length > 0) {
                itemCategory = td[1] ? td[1].textContent.toLowerCase() : ''; // Asumsi kategori ada di kolom kedua

                if (category === '' || itemCategory.includes(category.toLowerCase())) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }

    document.getElementById('makananButton').addEventListener('click', function() {
        filterCategory('makanan');
    });

    document.getElementById('minumanButton').addEventListener('click', function() {
        filterCategory('minuman');
    }); 

// script untuk modal 1 dan 2
/*                  ============     SCRIPT UNTUK MODAL BARANG ATAU HALAMAN UTAMA    =========                */



//                      FUNGSI UNTUK MENGHAPUS DATA CART
function clearCart() {
    // Hapus HTML item yang dipilih
    document.getElementById('selected-items').innerHTML = '';

    // Reset jumlah item dan harga di UI
    document.getElementById('total-items').textContent = '0';
    document.getElementById('total-price').textContent = 'Rp.0';

    // Hapus cart dari localStorage
    localStorage.removeItem('cart');
    
    // Pastikan untuk memanggil updateTotal() untuk memperbarui tampilan checkout
    updateTotal();
}

//                      FUNGSI UNTUK MENGHAPUS DATA CART

//              FUNGSI YANG DI JALANKAN SAAT HALAMAN DI MUAT
function onPageLoad() {
    clearCart(); // Hapus cart saat halaman dimuat
}

// Panggil fungsi onPageLoad ketika halaman siap
window.addEventListener('load', onPageLoad);
//              FUNGSI YANG DI JALANKAN SAAT HALAMAN DI MUAT


 

//          #####   FUNGSI UNTUK MENAMPILKAN DATA BARANG HARGA JUMLAH DAN TOTAL DAN DI KIRIM KE DATABASE     ######
function addSelectedItemToOrder() {
    // Ambil detail item dari modal
    var idmenu = document.getElementById('kodebarangDisplay').textContent; // Ambil kode barang (idmenu)
    var namamenu = document.getElementById('productTitle').textContent; // Nama menu
    var harga = parseFloat(document.getElementById('productTotalPrice').getAttribute('data-price').replace('.', '').replace(',', '.')); // Harga per item
    var jumlah = parseInt(document.querySelector('.quantity-input').value) || 1; // Jumlah item
    var total = harga * jumlah; // Total harga untuk item tersebut
    var imageUrl = document.getElementById('productImage') ? document.getElementById('productImage').src : 'default-image.jpg'; // Ambil URL gambar

    // Referensi ke tbody orderItems
    var orderItems = document.getElementById('orderItems');

    // Cek jika orderItems element ada
    if (!orderItems) {
        console.error('Order items table body not found.');
        return;
    }

    // Cek jika item sudah ada di tabel
    var existingRow = Array.from(orderItems.rows).find(row => {
        return row.querySelector('.item-title') && row.querySelector('.item-title').textContent.includes(namamenu);
    });

    if (existingRow) {
        // Update kuantitas dan total untuk item yang sudah ada
        var quantityInput = existingRow.querySelector('.quantity-input');
        var totalInput = existingRow.querySelector('.total-input');

        // Update kuantitas
        var newQuantity = parseInt(quantityInput.value) + jumlah;
        quantityInput.value = newQuantity;

        // Hitung total baru
        var newTotal = harga * newQuantity;
        totalInput.value = newTotal.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }); // Format tanpa desimal

        // Update judul item dengan kuantitas baru
        var itemTitle = existingRow.querySelector('.item-title');
        itemTitle.textContent = `(${newQuantity}x) ${namamenu}`;

        // Update hidden input untuk idmenu
        existingRow.querySelector('input[name="idmenu[]"]').value = idmenu;

        // Hitung ulang total untuk baris
        calculateRowTotal(quantityInput);
    } else {
        // Buat baris baru dalam form order
        var newRow = orderItems.insertRow();

        // Tambahkan baris dengan input tersembunyi untuk 'idmenu', 'harga', 'jumlah', dan 'total'
        newRow.innerHTML = `
            <td colspan="4">
                <div class="order-row">
                    <div class="item-details">
                        <div class="top-row">
                            <!-- Gambar produk -->
                            <img src="${imageUrl}" alt="${namamenu}" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;">
                            
                            <!-- Nama Item dengan Kuantitas -->
                            <span class="item-title" style="font-weight: bold;">
                                (${jumlah}x) ${namamenu}
                            </span>
                            <!-- Tombol Hapus -->
                            <div class="action-buttons" style="margin-left: auto;">
                                <button type="button" onclick="handleRemoveItem('${namamenu}', this)"><i class="fa fa-trash"></i></button>
                            </div>
                        </div>

                        <div class="bottom-row">
                            <div class="left-section">
                                <!-- Harga & Kuantitas -->
                                <span class="price-input" style="color: red; font-weight: bold; font-size: 14px;">
                                    ${harga.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                                </span>
                                <span class="quantity-x"> x </span>
                                
                                <button type="button" class="w3-button" onclick="decrementQuantity(this)">
                                    <i class="fa fa-minus-circle"></i>
                                </button>
                                
                                <input class="quantity-input" name="jumlah[]" value="${jumlah}" oninput="calculateRowTotal(this)" readonly>

                                <button type="button" class="w3-button" onclick="incrementQuantity(this)">
                                    <i class="fa fa-plus-circle"></i>
                                </button>

                                <!-- Hidden Input untuk Data Item -->
                                <input type="hidden" name="idmenu[]" value="${idmenu}">
                                <input type="hidden" name="namamenu[]" value="${namamenu}">
                                <input type="hidden" name="harga[]" value="${harga.toFixed(2).replace('.', ',')}">
                                <input type="hidden" name="total[]" value="${total.toFixed(2).replace('.', ',')}">
                            </div>
                            
                            <!-- Total Harga -->
                            <input type="text" class="total-input" name="total[]" style="color: green;" value="${total.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}" readonly>
                        </div>
                    </div>
                </div>
            </td>
        `;
    }

    // Update grand total
    calculateTotal();
    togglePayButton();  // Panggil fungsi ini untuk mengecek jumlah item
    // Tutup modal setelah menambahkan item ke form
    closeProductModal();
}

//          #####   FUNGSI UNTUK MENAMPILKAN DATA BARANG HARGA JUMLAH DAN TOTAL DAN DI KIRIM KE DATABASE     ######

//                 ======       FUNGSI UNTUK MENAMBAH BARANG DAN MENGUANGI BARANG DI TABLE  ======
function incrementQuantity(button) {
    // Mendapatkan input quantity yang terkait
    const quantityInput = button.closest('.left-section').querySelector('.quantity-input');
    const itemTitleElement = button.closest('.order-row').querySelector('.item-title'); // Elemen judul item
    const itemTitle = button.closest('.bottom-row').querySelector('input[type="hidden"][name="namamenu[]"]').value; // ID barang

    // Menambahkan 1 pada nilai quantity
    quantityInput.value = parseInt(quantityInput.value) + 1;

    // Perbarui item di localStorage
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    let item = cart.find(i => i.title === itemTitle);
    if (item) {
        item.quantity += 1;
    } else {
        let price = parseFloat(button.closest('.bottom-row').querySelector('input[type="hidden"][name="harga[]"]').value.replace(/\./g, '').replace(',', '.'));
        cart.push({ title: itemTitle, price: price, quantity: 1 });
    }
    localStorage.setItem('cart', JSON.stringify(cart));

    // Panggil fungsi untuk menghitung ulang total baris
    calculateRowTotal(quantityInput);

    // Panggil fungsi untuk memperbarui total
    updateTotal();

    // Update jumlah di sebelah nama item
    itemTitleElement.textContent = `(${quantityInput.value}x) ${itemTitleElement.textContent.split(') ')[1]}`;
}

function decrementQuantity(button) {
    // Mendapatkan input quantity yang terkait
    const quantityInput = button.closest('.left-section').querySelector('.quantity-input');
    const itemTitleElement = button.closest('.order-row').querySelector('.item-title'); // Elemen judul item
    const itemTitle = button.closest('.bottom-row').querySelector('input[type="hidden"][name="namamenu[]"]').value; // ID barang

    // Mengurangi 1 pada nilai quantity, tetapi tidak boleh kurang dari 1
    if (quantityInput.value > 1) {
        quantityInput.value = parseInt(quantityInput.value) - 1;

        // Perbarui item di localStorage
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        let itemIndex = cart.findIndex(i => i.title === itemTitle);
        if (itemIndex !== -1) {
            cart[itemIndex].quantity -= 1;
            if (cart[itemIndex].quantity <= 0) {
                cart.splice(itemIndex, 1); // Hapus item jika jumlahnya 0
            }
        }

        localStorage.setItem('cart', JSON.stringify(cart));

        // Panggil fungsi untuk menghitung ulang total baris
        calculateRowTotal(quantityInput);

        // Panggil fungsi untuk memperbarui total
        updateTotal();

        // Update jumlah di sebelah nama item
        itemTitleElement.textContent = `(${quantityInput.value}x) ${itemTitleElement.textContent.split(') ')[1]}`;
    }
}

//                  ======       FUNGSI UNTUK MENAMBAH BARANG DAN MENGUANGI BARANG DI TABLE  =====

//           FUNGSI UNTUK MENGHAPUS KEDUA DATA SECARA BERSAMA
function handleRemoveItem(title, button) {
    removeItemFromCart(title);
    deleteRow(button);
}
//          FUNGSI UNTUK MENGHAPUS KEDUA DATA SECARA BERSAMA

//          FUNGSI UNTUK MENGHAPUS DATA DI KOLOM TABEL
function deleteRow(button) {
    // Find the row to delete
    let row = button.closest('tr');
    
    // Get the item title from the row
    let itemTitle = row.querySelector('.item-title').textContent.trim();

    // Remove the row from the table
    row.parentNode.removeChild(row);

    // Also remove the corresponding item from the cart in localStorage
    removeItemFromCart(itemTitle);

    // Recalculate and update the grand total
    updateGrandTotal();
    updateTotal();
      // Cek apakah tombol BAYAR perlu dinonaktifkan
      togglePayButton();
}
//          FUNGSI UNTUK MENGHAPUS DATA DI KOLOM TABEL

// FUNGSI UNTUK MENGHAPUS DATA DI MODAL ATAU LOCALSTORGE CART
function removeItemFromCart(title) {
    // Retrieve existing cart from localStorage
    let cart = JSON.parse(localStorage.getItem('cart')) || [];

    // Filter out the item with the specified title
    cart = cart.filter(item => item.title !== title);

    // Save updated cart to localStorage
    localStorage.setItem('cart', JSON.stringify(cart));

    // Update the total items and price
    updateTotal();
}
// Initial update for the checkout container
updateTotal();
// FUNGSI UNTUK MENGHAPUS DATA DI MODAL ATAU LOCALSTORGE CART

// FUNGSI UNTUK BARANG KE TABEL (ADD)
document.querySelector('.add-button').addEventListener('click', addSelectedItemToOrder);
// FUNGSI UNTUK BARANG KE TABEL (ADD)

// FUNGSI UNTUK MENUTUP MODAL HALAMAN UTAMA DAN MASUK KE FORM CHECKOUT
function checkoutAndRedirect() {
    // Tutup modal
    document.getElementById('barangModal').style.display = 'none';
    
    // Redirect ke halaman penjualan.php
    window.location.href = 'penjualan.php';
}
// FUNGSI UNTUK MENUTUP MODAL HALAMAN UTAMA DAN MASUK KE FORM CHECKOUT

//      FUNGSI UNTUK MEMUNCULKAN BARANG DI PRODUCT MODAL DAN MENUTUP MODAL 
// Fungsi untuk membuka modal produk dengan data yang sesuai
function openProductModal(idmenu, nama, harga, imageUrl) {
    // Set gambar produk, nama, dan harga di dalam modal
    document.getElementById('productImage').src = imageUrl; // Gambar produk
    document.getElementById('productTitle').textContent = nama; // Nama produk
    document.getElementById('productTotalPrice').setAttribute('data-price', harga); // Harga dalam atribut data
    document.getElementById('kodebarangDisplay').textContent = idmenu; // Menampilkan ID menu di modal

    // Atur jumlah awal dan harga total di dalam modal
    document.querySelector('.quantity-input').value = 1; // Set jumlah awal ke 1
    updateProductPrice(); // Perbarui harga berdasarkan jumlah awal

    // Tampilkan modal
    document.getElementById('productModal').style.display = 'block';
}

// Fungsi untuk menutup modal produk
function closeProductModal() {
    document.getElementById('productModal').style.display = 'none';
}

// Fungsi untuk menambah jumlah produk di modal
function increment(button) {
    var input = button.parentElement.querySelector('.quantity-input');
    var currentValue = parseInt(input.value);
    input.value = currentValue + 1;
    updateProductPrice(); // Perbarui harga total
}

// Fungsi untuk mengurangi jumlah produk di modal
function decrement(button) {
    var input = button.parentElement.querySelector('.quantity-input');
    var currentValue = parseInt(input.value);
    if (currentValue > 1) { // Pastikan jumlah tidak kurang dari 1
        input.value = currentValue - 1;
        updateProductPrice(); // Perbarui harga total
    }
}


//          FUNGSI INI DI GUNAKAN UNTUK MEMPERBARUI HARGA TOTAL PRODUK BERDASARKAN JUMLAH
   // Fungsi untuk memperbarui harga total berdasarkan jumlah yang dipilih
function updateProductPrice() {
    var hargaSatuan = parseFloat(document.getElementById('productTotalPrice').getAttribute('data-price').replace(/\./g, '').replace(',', '.')); 
    var jumlah = parseInt(document.querySelector('.quantity-input').value);
    var totalHarga = hargaSatuan * jumlah;
    document.getElementById('productTotalPrice').textContent = 'Rp ' + totalHarga.toLocaleString('id-ID');
}
//          FUNGSI INI DI GUNAKAN UNTUK MEMPERBARUI HARGA TOTAL PRODUK BERDASARKAN JUMLAH

//       FUNGSI INI DI GUNAKAN UNTUK MENAMBAH BARANG KE TABEL ATAU ORDER ITEMS
  // Fungsi ini digunakan untuk menambah barang ke tabel atau order items
function addToCart() {
    // Ambil detail item
    const title = document.getElementById('productTitle').textContent.trim();
    const price = parseFloat(document.getElementById('productTotalPrice').getAttribute('data-price').replace('.', '').replace(',', '.'));
    const quantity = parseInt(document.querySelector('.quantity-input').value, 10);
    const imageUrl = document.getElementById('productImage').src; // Ambil URL gambar produk

    // Logging untuk debugging
    console.log(`Title: ${title}, Price: ${price}, Quantity: ${quantity}`);

    if (isNaN(price) || isNaN(quantity) || quantity <= 0) {
        console.error('Harga atau jumlah tidak valid.');
        return;
    }

    // Ambil keranjang yang ada dari localStorage atau inisialisasi
    let cart = JSON.parse(localStorage.getItem('cart')) || [];

    // Cek jika item sudah ada di keranjang
    const itemIndex = cart.findIndex(item => item.title === title);
    if (itemIndex > -1) {
        // Perbarui kuantitas jika item sudah ada di keranjang
        cart[itemIndex].quantity += quantity;
    } else {
        // Tambahkan item baru ke keranjang tanpa kodebarang, bungkus, dan keterangan
        cart.push({ title, price, quantity, image: imageUrl });
    }

    // Simpan keranjang yang diperbarui ke localStorage
    localStorage.setItem('cart', JSON.stringify(cart));

    // Perbarui total item dan harga di modal
    updateTotal();

    // Perbarui tampilan keranjang langsung setelah menambahkan item
    updateCartDisplay(cart);

    // Tutup modal produk
    closeProductModal();
}

//       FUNGSI INI DI GUNAKAN UNTUK MENAMBAH BARANG KE TABEL ATAU ORDER ITEMS

// FUNGSI INI DI GUNAKAN UNTUK MENGHITUNG DAN MEMPERBARUI TAMPILAN TOTAL BARANG SERTA TOTAL HARGA DI KERANJANG BELANJA CART
function updateTotal() {
    // Retrieve cart from localStorage
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    
    // Logging the retrieved cart for debugging
    console.log('Keranjang saat ini:', cart);
    
    let totalItems = 0; // Total jumlah barang berdasarkan jumlah item unik
    let totalPrice = 0; // Total harga dari semua item

    // Hitung total harga dan total jumlah barang
    totalItems = cart.length; // Jumlah barang unik
    cart.forEach(item => {
        totalPrice += item.price * item.quantity; // Hitung total harga
    });

    // Logging the total items and total price for debugging
    console.log('Jumlah total :', totalItems);
    console.log('Total harga:', totalPrice);

    // Update total section
    document.getElementById('total-items').textContent = totalItems; // Total jumlah barang unik
    document.getElementById('total-price').textContent = 'Rp ' + totalPrice.toLocaleString('id-ID');

    // Show or hide the checkout container based on the number of items
    const checkoutContainer = document.querySelector('.checkout-container');
    if (totalItems > 0) {
        checkoutContainer.style.display = 'flex'; // Show the checkout container
        document.getElementById('checkout-button').disabled = false; // Enable the checkout button
    } else {
        checkoutContainer.style.display = 'none'; // Hide the checkout container
        document.getElementById('checkout-button').disabled = true; // Disable the checkout button
    }
}

// Initial update for the checkout container
updateTotal();
// FUNGSI INI DI GUNAKAN UNTUK MENGHITUNG DAN MEMPERBARUI TAMPILAN TOTAL BARANG SERTA TOTAL HARGA DI KERANJANG BELANJA CART

// FUNGSI INI DI GUNAKAN UNTUK MENGHITUNG JUMLAH DI FORM PENGIRIM DATA KE DATABASE
function calculateRowTotal(input) {
    var row = input.closest('tr'); // Mendapatkan baris terkait
    var priceElement = row.querySelector('.price-input'); // Elemen harga satuan
    var totalInput = row.querySelector('.total-input'); // Input untuk total per item

    // Ambil harga satuan dan jumlah item
    var price = parseFloat(priceElement.textContent.replace(/\./g, '').replace(',', '.')); // Ubah format ID ke angka
    var quantity = parseInt(input.value);

    // Hitung total untuk baris ini
    var rowTotal = price * quantity;

    // Tampilkan hasilnya ke input total (tanpa desimal)
    totalInput.value = rowTotal.toLocaleString('id-ID');

    // Hitung ulang grand total
    calculateTotal();
}
// FUNGSI INI DI GUNAKAN UNTUK MENGHITUNG JUMLAH DI FORM PENGIRIM DATA KE DATABASE

// FUNGSI INI DI GUNAKAN UNTUK MENGHITUNG TOTAL KESELURUHAN DARI SEMUA ELEMEN INPUT YANG MEMILIKI NAMA TOTAL[] DI MODAL CART
function updateGrandTotal() {
    let total = 0;

    // Select all input elements with the name 'total[]'
    const totalElements = document.querySelectorAll('input[name="total[]"]');

    // Check if there are any 'total' elements left
    if (totalElements.length > 0) {
        // Loop through each element and add up the total
        totalElements.forEach(function (element) {
            // Convert the value from text format to a number
            let value = parseFloat(element.value.replace(/\./g, '').replace(',', '.'));
            if (!isNaN(value)) {
                total += value;
            }
        });

        // Format the total into Indonesian currency format
        let formattedTotal =  total.toLocaleString('id-ID');

        // Display the grand total in the input field
        document.getElementById('grandtotal').value = formattedTotal;
    } else {
        // If no 'total' elements remain, set the grand total to 0
        document.getElementById('grandtotal').value = 'Rp 0';
    }
}
// FUNGSI INI DI GUNAKAN UNTUK MENGHITUNG TOTAL KESELURUHAN DARI SEMUA ELEMEN INPUT YANG MEMILIKI NAMA TOTAL[] DI MODAL CART

// FUNGSI INI DI GUNAKAN UNTUK MENGHITUNG GRAND TOTAL DI TABLE ORDER ITEMS
function calculateTotal() {
    var orderItems = document.getElementById('orderItems');
    var totalInputs = orderItems.querySelectorAll('.total-input');
    var grandTotal = 0;

    totalInputs.forEach(function(totalInput) {
        // Pastikan untuk membersihkan format angka sebelum melakukan perhitungan
        var rowTotal = parseFloat(totalInput.value.replace(/\./g, '').replace(',', '.')) || 0;
        grandTotal += rowTotal;
    });

    // Tampilkan nilai grand total
    document.getElementById('grandtotal').value =  grandTotal.toLocaleString('id-ID');
}
// FUNGSI INI DI GUNAKAN UNTUK MENGHITUNG GRAND TOTAL DI TABLE ORDER ITEMS




// FUNGSI INI DI GUNAKAN UNTUK MELANJUTKAN MENGIRIMKAN DETA KETIKA NAMA DAN NO TELEPON TERISI 
function validateForm() {
    var form = document.getElementById('mainForm');
    if (form.checkValidity()) {
        openModal(); // Call your function to open the modal
    } else {
        form.reportValidity(); // Trigger the validation messages
    }
}
// FUNGSI INI DI GUNAKAN UNTUK MELANJUTKAN MENGIRIMKAN DETA KETIKA NAMA DAN NO TELEPON TERISI 

function openCartModal() {
        console.log('Mencoba membuka modal keranjang...');
        const modal = document.getElementById('cartModal');
        if (modal) {
            modal.style.display = 'block';
            updateCartModal();
            console.log('Modal keranjang seharusnya sudah terbuka.');
        } else {
            console.log('Elemen modal tidak ditemukan.');
        }
    }

  // Pastikan ini dipanggil saat tombol keranjang ditekan
  document.getElementById('openCartButton').addEventListener('click', openCartModal);    
// FUGSI INI DI GUNAKAN UNTUK MEMBUKA DAN MENUTUP MODAL KERANJANG

// Menutup modal ketika klik di luar area modal
window.onclick = function(event) {
    const modal = document.getElementById('cartModal'); // Mengambil elemen modal dengan id 'cartModal'.
    if (event.target === modal) { // Memeriksa apakah elemen yang diklik adalah modal itu sendiri.
        closeCartModal(); // Jika ya, panggil fungsi untuk menutup modal.
    }
}

// FUNGSI UNTUK MENAMPILKAN BARANG HARGA DAN JUMLAH DI MODAL KERANJANG
function updateCartModal() {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const cartItemsContainer = document.getElementById('cartItemsContainer');
    const cartTotalItems = document.getElementById('cart-total-items'); // Total barang di footer
    const cartTotalHeaderItems = document.getElementById('cart-total-header-items'); // Total barang di header
    const cartTotalPrice = document.getElementById('cart-total-price');
    const checkoutButton = document.querySelector('.cart-checkout-button'); // Ambil tombol Bayar

    cartItemsContainer.innerHTML = ''; // Clear existing items
    let totalItems = 0;
    let totalPrice = 0;

    if (cart.length === 0) {
        // Jika keranjang kosong, nonaktifkan tombol Bayar
        checkoutButton.disabled = true;
        checkoutButton.classList.add('disabled');
    } else {
        // Jika ada barang, aktifkan tombol Bayar
        checkoutButton.disabled = false;
        checkoutButton.classList.remove('disabled');
        cart.forEach(item => {
            const cartItemCard = document.createElement('div');
            cartItemCard.classList.add('cart-item-card');

            cartItemCard.innerHTML = `
                <div class="cart-item-image">
                    <img src="${item.image ? item.image : 'default_image.png'}" 
                         style="width: 50px; height: auto;" 
                         alt="${item.title}" 
                         onerror="this.onerror=null; this.src='default_image.png';" />
                </div>
                <div class="cart-item-details">
                    <h6>${item.title}</h6>
                    <p class="item-price">Rp ${item.price.toLocaleString('id-ID')}</p>
                </div>
                <div class="cart-item-controls">
                  <button onclick="decreaseQuantity('${item.title}')"><i class="fa fa-minus-circle"></i></button>
                  <span class="quantity">${item.quantity}</span>
                  <button onclick="increaseQuantity('${item.title}')"><i class="fa fa-plus-circle"></i></button>
                  <button onclick="removeFromCart('${item.title}')"><i class="fa fa-trash"></i></button>
                </div>
            `;
            cartItemsContainer.appendChild(cartItemCard);

            totalItems += item.quantity;
            totalPrice += item.price * item.quantity;
        });
    }

    // Update jumlah barang unik (bukan jumlah item) di footer dan header modal
    const totalUniqueItems = cart.length;  // Jumlah barang unik

    cartTotalItems.textContent = totalUniqueItems;
    cartTotalHeaderItems.textContent = totalUniqueItems;

    // Update total price tanpa desimal
    cartTotalPrice.textContent = `Rp ${totalPrice.toLocaleString('id-ID')}`;

    // Panggil calculateTotal untuk memperbarui grand total setelah keranjang diperbarui
    calculateTotal();
}


// FUNGSI UNTUK MENAMBAHKAN JUMLAH DAN MENGURANGI JUMLAH BARANG DI MODAL KERANJANG
function decreaseQuantity(itemTitle) {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    const itemIndex = cart.findIndex(item => item.title === itemTitle);

    if (itemIndex > -1) {
        if (cart[itemIndex].quantity > 1) {
            cart[itemIndex].quantity -= 1;
        } else {
            cart[itemIndex].quantity = 1;
        }
        localStorage.setItem('cart', JSON.stringify(cart));
        updateCartModal();
        updateTotal();
        updateOrderTable(itemTitle, cart[itemIndex].quantity);
        calculateTotal();
    }
}

function increaseQuantity(itemTitle) {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    const itemIndex = cart.findIndex(item => item.title === itemTitle);
    
    if (itemIndex > -1) {
        cart[itemIndex].quantity += 1;
        localStorage.setItem('cart', JSON.stringify(cart));
        updateCartModal();
        updateTotal();
        updateOrderTable(itemTitle, cart[itemIndex].quantity);
        calculateTotal();
    }
}
//UNTUK MENGUPDATE PERUBAHAN KE FROM
function updateOrderTable(itemTitle, newQuantity) {
    const orderItems = document.getElementById('orderItems');
    const existingRow = Array.from(orderItems.rows).find(row => {
        return row.querySelector('.item-title') && row.querySelector('.item-title').textContent.includes(itemTitle);
    });

    // Ambil harga dari cart untuk perhitungan total
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const itemInCart = cart.find(item => item.title === itemTitle);
    const price = itemInCart ? itemInCart.price : 0;

    if (existingRow) {
        if (newQuantity > 0) {
            // Perbarui kuantitas dan total
            const quantityInput = existingRow.querySelector('.quantity-input');
            const totalInput = existingRow.querySelector('.total-input');

            // Perbarui kuantitas
            quantityInput.value = newQuantity;

            // Perbarui total
            const newTotal = price * newQuantity;
            totalInput.value = newTotal.toLocaleString('id-ID'); // Format tanpa desimal

            // Update judul item dengan kuantitas baru
            const itemTitleElement = existingRow.querySelector('.item-title');
            itemTitleElement.textContent = `(${newQuantity}x) ${itemTitle}`;

        } else {
            // Jika kuantitas 0, hapus baris
            removeOrderRow(itemTitle);
        }
    } else if (newQuantity > 0) {
        // Jika baris tidak ditemukan, tambahkan baris baru
        const newRow = orderItems.insertRow();
        const total = price * newQuantity; // Total untuk baris baru
        newRow.innerHTML = `
            <td class="item-title">${itemTitle}</td>
            <td><input class="quantity-input" type="number" value="${newQuantity}" readonly /></td>
            <td class="price-input">${price.toLocaleString('id-ID')}</td>
            <td><input class="total-input" value="${total.toLocaleString('id-ID')}" readonly /></td>
        `;
    }
}
//UNTUK MENGUPDATE PERUBAHAN KE FROM

function removeOrderRow(itemTitle) {
    const orderItems = document.getElementById('orderItems');
    const existingRow = Array.from(orderItems.rows).find(row => {
        return row.querySelector('.item-title') && row.querySelector('.item-title').textContent.trim() === itemTitle;
    });
    if (existingRow) {
        existingRow.remove();
    }
    updateTotal();
}

// FUNGSI INI UNTUK MENGHAPUS DATA BARANG DI MODAL KERANJANG BESERTA DI TABEL
function removeFromCart(itemTitle) {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    cart = cart.filter(item => item.title !== itemTitle);
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartModal();
    updateTotal();
    removeOrderRow(itemTitle);
    const tableButton = document.querySelector(`button[onclick*="handleRemoveItem('${itemTitle}'"]`);
    if (tableButton) {
        handleRemoveItem(itemTitle, tableButton);
    }
}

//  FUNGSI INI UNTUK MENGHAPUS DATA BARANG DI MODAL KERANJANG BESERTA DITABEL

// FUNGSI INI DI GUNAKAN UNTUK CLOSE SEMUA MODAL
    function closeAllModals() {
        var modals = document.getElementsByClassName('w3-modal');
        for (var i = 0; i < modals.length; i++) {
            modals[i].style.display = 'none';
        }
    }

    
 //         <--------------                            SCRIPT UNTUK MODAL KERANJANG                        -------------->

// UNTUK BUKTI TRANSAKSI
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
// UNTUK BUKTI TRANSAKSI

//UNTUK MENGISI KEMBALIAN SECARA OTOMATIS DAN FORMAT BAYAR
function formatRupiah(angka) {
    let number_string = angka.replace(/[^,\d]/g, '').toString(),
        split = number_string.split(','),
        sisa = split[0].length % 3,
        rupiah = split[0].substr(0, sisa),
        ribuan = split[0].substr(sisa).match(/\d{3}/gi);

    if (ribuan) {
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }

    rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
    return rupiah;
}

// Fungsi untuk menghapus format rupiah agar bisa dihitung
function removeFormatRupiah(angka) {
    return angka.replace(/[^,\d]/g, '').replace(',', '.');
}

// Format otomatis saat user mengetik
function formatInputBayar(input) {
    let value = input.value;
    let formattedValue = formatRupiah(value);
    input.value = formattedValue;

    // Hitung kembalian setelah format
    hitungKembalian();
}

// Fungsi hitung kembalian
function hitungKembalian() {
    let bayarInput = document.getElementById('bayar');
    let grandtotalInput = document.getElementById('grandtotal');
    let kembalianInput = document.getElementById('kembalian');

    if (!bayarInput || !grandtotalInput || !kembalianInput) {
        console.error("Elemen input tidak ditemukan!");
        return;
    }

    // Ambil nilai bayar & grandtotal tanpa format rupiah
    let bayar = removeFormatRupiah(bayarInput.value);
    let grandtotal = removeFormatRupiah(grandtotalInput.value);

    bayar = parseFloat(bayar) || 0;
    grandtotal = parseFloat(grandtotal) || 0;

    let kembalian = bayar - grandtotal;

    // Pastikan kembalian tidak negatif
    if (kembalian < 0) {
        kembalian = 0;
    }

    // Tampilkan hasil kembalian dalam format angka biasa (tanpa "Rp")
    kembalianInput.value = kembalian.toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    });
}

//UNTUK MENGISI KEMBALIAN SECARA OTOMATIS DAN FORMAT BAYAR

//  FUNGSI INI DI GUNAKAN UNTUK MELANJUTKAN MEMBUKA MODAL NAMA DAN NO TELEPON KETIKA SUDAH TERISI SEMUA (TABEL)
function togglePayButton() {
    var bayar = document.getElementById('bayar').value.trim(); // Ambil nilai bayar
    var buktitransaksi = document.getElementById('gambarUpload').files.length > 0;
    var orderItems = document.getElementById('orderItems');
    var payButton = document.getElementById('payButton');
    var grandtotalInput = document.getElementById('grandtotal');

    // Hilangkan format Rp dan konversi ke angka
    var bayarValue = parseFloat(bayar.replace(/[^,\d]/g, '').replace(',', '.')) || 0;
    var grandtotalValue = parseFloat(grandtotalInput.value.replace(/[^,\d]/g, '').replace(',', '.')) || 0;

    console.log("Jumlah Bayar:", bayarValue); // Debugging
    console.log("Grand Total:", grandtotalValue); // Debugging
    console.log("Bukti Transaksi:", buktitransaksi); // Debugging
    console.log("Jumlah item di tabel:", orderItems ? orderItems.rows.length : 0); // Debugging

    // Cek apakah bayar >= grandtotal, ada bukti transaksi, dan ada item di tabel
    if (bayarValue >= grandtotalValue && buktitransaksi && orderItems && orderItems.rows.length > 1) {
        payButton.disabled = false;
        payButton.style.opacity = "1";
        payButton.style.cursor = "pointer";
    } else {
        payButton.disabled = true;
        payButton.style.opacity = "0.5";
        payButton.style.cursor = "not-allowed";
    }
}

//  FUNGSI INI DI GUNAKAN UNTUK MELANJUTKAN MEMBUKA MODAL NAMA DAN NO TELEPON KETIKA SUDAH TERISI SEMUA (TABEL)

// FUNGSI INI DI GUNAKAN UNTUK TANGGAL DAN WAKTU
document.addEventListener('DOMContentLoaded', function () {
            const datetimeInput = document.getElementById('tanggal');

            function formatDateTime() {
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0'); // Bulan adalah basis nol
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');

                return `${year}-${month}-${day}T${hours}:${minutes}`;
            }

            // Set nilai dan atribut min dari input ke tanggal dan waktu saat ini
            const now = formatDateTime();
            datetimeInput.value = now; // Set nilai input ke tanggal dan waktu saat ini
            datetimeInput.min = now; // Set nilai minimum yang diizinkan ke tanggal dan waktu saat ini
        });

</script>

</body>
</html> 