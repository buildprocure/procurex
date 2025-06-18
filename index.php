<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BuildProcure - Home</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .header {
            background: linear-gradient(90deg, #007bff, #0056b3);
            color: white;
            padding: 3rem 0;
            text-align: center;
        }
        .intro {
            max-width: 800px;
            margin: 3rem auto;
            text-align: center;
        }
        .intro h2 {
            color: #333;
            font-weight: bold;
        }
        .intro p {
            color: #666;
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="display-4">Welcome to BuildProcure</h1>
        <?php if (isset($_SESSION['username'])): ?>
            <p class="lead">Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
        <?php endif; ?>
    </div>
    <div class="intro container">
        <h2>About BuildProcure</h2>
        <p class="lead">BuildProcure is your one-stop platform for seamless construction procurement. Whether you're sourcing materials, managing suppliers, or streamlining your project needs, we empower builders with efficiency and reliability. Join us to simplify your procurement process today!</p>
    </div>
    <div class="text-center mb-4">
        <?php if (isset($_SESSION['username'])): ?>
            <a href="logout.php" class="btn btn-outline-primary">Log Out</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-primary me-2">Log In</a>
            <a href="signup.php" class="btn btn-outline-primary">Sign Up</a>
        <?php endif; ?>
    </div>

    <!-- Bootstrap 5 JS (with Popper.js) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
