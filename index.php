<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BuildProcure - Home</title>
    <link rel="stylesheet" href="global_bp.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    --
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f9fafb;
        }

        .navbar-brand {
            font-weight: 600;
            font-size: 1.5rem;
        }

        .hero {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            color: white;
            padding: 5rem 0;
            text-align: center;
        }

        .hero h1 {
            font-size: 3rem;
            font-weight: bold;
        }

        .hero p {
            font-size: 1.25rem;
            margin-top: 1rem;
        }

        .features {
            padding: 4rem 0;
        }

        .features .card {
            border: none;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.06);
            transition: transform 0.3s ease;
        }

        .features .card:hover {
            transform: translateY(-5px);
        }

        .footer {
            background-color: #f1f3f5;
            padding: 2rem 0;
            text-align: center;
            color: #6c757d;
            margin-top: 4rem;
        }
    </style> -->

     <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.getElementById('userDropdownToggle');
            const menu = document.getElementById('userDropdownMenu');

            toggle.addEventListener('click', function(e) {
                console.log('Dropdown toggle clicked');
                e.stopPropagation(); // Prevent click from bubbling up
                menu.classList.toggle('show');
            });

            // Close dropdown if clicking outside
            document.addEventListener('click', function() {
                menu.classList.remove('show');
            });
        });
</script>

</head>
<body>

<nav class="navbar-horizontal">
    <div class="nav-container">
        <a class="company-name" href="#">BuildProcure</a>
        <div class="nav-actions">
            <?php if (isset($_SESSION['username'])): ?>
                <span class="welcome-text">Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
                <div class="user-dropdown">
                    <i id="userDropdownToggle" class="fas fa-user-circle user-icon"></i>
                    <ul id="userDropdownMenu" class="dropdown-menu">
                        <li><a href="#">Profile</a></li>
                        <li><a href="#">Settings</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary btn-sm">Log In</a>
                <a href="Sign_up.php" class="btn btn-outline-primary btn-sm">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</nav>


<section class="hero">
    <div class="container">
        <h1>Revolutionizing Construction Procurement</h1>
        <p>Streamline your material sourcing, supplier management, and project procurement with confidence.</p>
        <a href="Sign_up.php" class="btn btn-light btn-lg mt-4">Get Started</a>
    </div>
</section>
<section class="features">
    <div class="features-header">
        <h2>Why BuildProcure?</h2>
        <p class="subtitle">A platform built for the future of construction procurement.</p>
    </div>

    <div class="features-grid">
        <div class="card">
            <h5>Centralized Procurement</h5>
            <p>Manage all your purchasing needs in one place – from RFQs to final orders.</p>
        </div>
        <div class="card">
            <h5>Supplier Transparency</h5>
            <p>Compare supplier ratings, pricing, and delivery timelines for smarter decisions.</p>
        </div>
        <div class="card">
            <h5>Data-Driven Insights</h5>
            <p>Get real-time analytics on your spending, usage patterns, and supplier performance.</p>
        </div>
    </div>
</section>


<footer class="footer">
    <div class="container">
        <p>&copy; <?php echo date("Y"); ?> BuildProcure. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
