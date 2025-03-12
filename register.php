<?php
include 'koneksi.php';
session_start();
if (isset($_SESSION['username'])) {
    echo "<script> window.location.href='dashboard.php' </script>";
    exit();
}

// Memeriksa koneksi
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}

// Define variables and set them to empty values
$username = $nama = $password = $password_confirm = "";
$error_message = $success_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use mysqli_real_escape_string for input sanitization
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = mysqli_real_escape_string($conn, trim($_POST['password']));
    $password_confirm = mysqli_real_escape_string($conn, trim($_POST['password_confirm']));
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama']));

    if ($password !== $password_confirm) {
        $error_message = "Password dan konfirmasi password tidak sesuai.";
    } else {
        // Memeriksa apakah username sudah ada
        $check_query = $conn->prepare("SELECT username FROM pengguna WHERE username = ?");
        $check_query->bind_param("s", $username);
        $check_query->execute();
        $check_query->store_result();

        if ($check_query->num_rows > 0) {
            $error_message = "Username sudah ada. Silakan pilih username lain.";
        } else {
            // Query SQL untuk menyimpan data pengguna ke dalam tabel 'pengguna' tanpa hashing password
            $query = $conn->prepare("INSERT INTO pengguna (username, password, nama) VALUES (?, ?, ?)");
            $query->bind_param("sss", $username, $password, $nama);

            if ($query->execute() === TRUE) {
                $success_message = "Data Berhasil Disimpan.";
            } else {
                $error_message = "Error: " . htmlspecialchars($query->error);
            }
            $query->close();
        }
        $check_query->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" type="image/png" href="jujurmart.png">
</head>
<style>
     .password-field {
        border: none; /* Remove all borders */
        border-bottom: 1px solid #ccc; /* Add bottom border */
        border-radius: 0; /* Remove rounded corners */
        padding-right: 30px; /* Space for the icon */
        background: none; /* Transparent background */
        box-shadow: none; /* Remove shadow */
    }

    .password-field:focus {
        border-bottom: 1px solid #000; /* Highlighted bottom border on focus */
        outline: none; /* Remove focus outline */
    }

    .w3-input-container {
        margin-top: 20px;
        position: relative; /* Ensure relative positioning */
    }

    i.fa-eye, i.fa-eye-slash {
        position: absolute; /* Absolute positioning for the icon */
        right: 10px; /* Right margin for positioning */
        top: 35px; /* Adjust to align with input */
        cursor: pointer; /* Cursor pointer for better UX */
        z-index: 1; /* Higher z-index to ensure clickability */
    }
    @keyframes heartbeat {
  0% {
    transform: scale(1); /* Normal size */
  }
  25% {
    transform: scale(1.1); /* Slightly bigger */
  }
  50% {
    transform: scale(1); /* Back to normal size */
  }
  75% {
    transform: scale(1.1); /* Slightly bigger */
  }
  100% {
    transform: scale(1); /* Normal size again */
  }
}

.w3-center img {
  animation: heartbeat 2s infinite; /* 1.5s duration and repeat forever */
  width: 120px; /* Increased image size */
}
</style>
<body>
    <br>
    <div class="w3-center">
    <img src="jujurmart.png" alt="Logo" style="vertical-align: middle; width: 140px;">
        <h2 class="w3-container w3-center" style="font-family: 'Arial', sans-serif; color: #333; font-weight: bold;">
        </h2>
    </div>

    <?php if ($error_message): ?>
        <div id="idgagal" class="w3-modal" style="display:block;" onclick="this.style.display='none'">
            <div class="w3-modal-content">
                <header class="w3-container w3-red">
                    <span class="w3-button w3-hover-red w3-xlarge w3-display-topright">&times;</span>
                    <h2>Informasi</h2>
                </header>
                <div class="w3-container">
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            </div>
        </div>
    <?php elseif ($success_message): ?>
        <div id="idberhasil" class="w3-modal" style="display:block;">
            <div class="w3-modal-content">
                <header class="w3-container w3-yellow">
                    <span class="w3-button w3-hover-red w3-xlarge w3-display-topright"
                        onclick="document.getElementById('idberhasil').style.display='none'">&times;</span>
                    <h2>Konfirmasi</h2>
                </header>
                <div class="w3-container">
                    <p><?php echo htmlspecialchars($success_message); ?></p>
                </div>
                <footer class="w3-container w3-white" style="text-align: right;">
                    <button id="okButton" class="w3-button w3-green">OK</button>
                </footer>
            </div>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                var modal = document.getElementById("idberhasil");
                var okButton = document.getElementById("okButton");

                okButton.addEventListener("click", function () {
                    modal.style.display = "none";
                    window.location.href = "index.php";
                });
            });
        </script>
    <?php endif; ?>

    <form class="w3-container w3-card-4 w3-light-grey w3-padding-16 w3-margin" action="" method="post" onsubmit="return trimInputs()">
        <h2 class="w3-container w3-center"><b>Register</b></h2>
        <label for="username">Username</label>
        <input type="text" id="username" class="w3-input w3-light-grey" name="username"
            value="<?php echo htmlspecialchars($username); ?>" required>

        <label for="nama">Nama</label>
        <input type="text" id="nama" class="w3-input w3-light-grey" name="nama"
            value="<?php echo htmlspecialchars($nama); ?>" required>

            <!-- Password Field -->
<div class="w3-input-container" style="position: relative; margin-bottom: 30px;">
    <label for="password">Password</label>
    <input type="password" id="password" class="w3-input w3-border-bottom w3-light-grey password-field" name="password" required>
    <!-- Eye icon inside the input field -->
    <i id="togglePassword" class="fa fa-eye" style="position: absolute; right: 10px; top: 35px; cursor: pointer; z-index: 1;"></i>
</div>

<!-- Confirm Password Field -->
<div class="w3-input-container" style="position: relative; margin-bottom: 30px;">
    <label for="password_confirm">Konfirmasi Password</label>
    <input type="password" id="password_confirm" class="w3-input w3-border-bottom w3-light-grey password-field" name="password_confirm" required>
    <!-- Eye icon inside the input field -->
    <i id="togglePasswordConfirm" class="fa fa-eye" style="position: absolute; right: 10px; top: 35px; cursor: pointer; z-index: 1;"></i>
</div>


        <button type="submit" class="w3-input w3-button w3-round-large w3-yellow w3-margin-top">Register</button>
        <a href="index.php" class="w3-input w3-button w3-round-large w3-green w3-margin-top">Login</a>
    </form>

    <script>
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

        function trimInputs() {
            document.getElementById('username').value = document.getElementById('username').value.trim();
            document.getElementById('nama').value = document.getElementById('nama').value.trim();
            return true;
        }

        document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordField = document.getElementById('password');
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        this.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        passwordField.type = 'password';
        this.classList.replace('fa-eye-slash', 'fa-eye');
    }
});

document.getElementById('togglePasswordConfirm').addEventListener('click', function() {
    const confirmPasswordField = document.getElementById('password_confirm');
    if (confirmPasswordField.type === 'password') {
        confirmPasswordField.type = 'text';
        this.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        confirmPasswordField.type = 'password';
        this.classList.replace('fa-eye-slash', 'fa-eye');
    }
});

 document.getElementById('togglePassword').addEventListener('click', function () {
        const passwordField = document.getElementById('password');
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            this.classList.replace('fa-eye', 'fa-eye-slash'); // Change icon to "eye-slash"
        } else {
            passwordField.type = 'password';
            this.classList.replace('fa-eye-slash', 'fa-eye'); // Change icon back to "eye"
        }
    });

    document.getElementById('togglePasswordConfirm').addEventListener('click', function () {
        const confirmPasswordField = document.getElementById('password_confirm');
        if (confirmPasswordField.type === 'password') {
            confirmPasswordField.type = 'text';
            this.classList.replace('fa-eye', 'fa-eye-slash'); // Change icon to "eye-slash"
        } else {
            confirmPasswordField.type = 'password';
            this.classList.replace('fa-eye-slash', 'fa-eye'); // Change icon back to "eye"
        }
    });
     // Toggle Password Visibility for Password Field
     document.getElementById('togglePassword').addEventListener('click', function () {
        const passwordField = document.getElementById('password');
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            this.classList.replace('fa-eye', 'fa-eye-slash'); // Change icon to "eye-slash"
        } else {
            passwordField.type = 'password';
            this.classList.replace('fa-eye-slash', 'fa-eye'); // Change icon back to "eye"
        }
    });

    // Toggle Password Visibility for Confirm Password Field
    document.getElementById('togglePasswordConfirm').addEventListener('click', function () {
        const confirmPasswordField = document.getElementById('password_confirm');
        if (confirmPasswordField.type === 'password') {
            confirmPasswordField.type = 'text';
            this.classList.replace('fa-eye', 'fa-eye-slash'); // Change icon to "eye-slash"
        } else {
            confirmPasswordField.type = 'password';
            this.classList.replace('fa-eye-slash', 'fa-eye'); // Change icon back to "eye"
        }
    });
    </script>
</body>

</html>
