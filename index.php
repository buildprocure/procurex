<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BuildProcure - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="#">BuildProcure</a>
        <div class="ms-auto">
            <?php if (isset($_SESSION['username'])): ?>
                <span class="me-3">Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Log Out</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary btn-sm me-2">Log In</a>
                <a href="signup.php" class="btn btn-outline-primary btn-sm">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<section class="hero">
    <div class="container">
        <h1>Revolutionizing Construction Procurement</h1>
        <p>Streamline your material sourcing, supplier management, and project procurement with confidence.</p>
        <a href="signup.php" class="btn btn-light btn-lg mt-4">Get Started</a>
    </div>
</section>

<section class="features container">
    <div class="row text-center mb-5">
        <div class="col">
            <h2 class="fw-bold">Why BuildProcure?</h2>
            <p class="text-muted">A platform built for the future of construction procurement.</p>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card p-4 h-100">
                <h5 class="fw-bold">Centralized Procurement</h5>
                <p class="text-muted">Manage all your purchasing needs in one place – from RFQs to final orders.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-4 h-100">
                <h5 class="fw-bold">Supplier Transparency</h5>
                <p class="text-muted">Compare supplier ratings, pricing, and delivery timelines for smarter decisions.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-4 h-100">
                <h5 class="fw-bold">Data-Driven Insights</h5>
                <p class="text-muted">Get real-time analytics on your spending, usage patterns, and supplier performance.</p>
            </div>
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
