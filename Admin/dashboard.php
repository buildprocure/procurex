<?php
libxml_use_internal_errors(true);
ob_start();
include '_loggedindatabase.php';
//include 'iuploads.php'; 
// session_start();

if(!isset($_SESSION['loggedin'])|| $_SESSION['loggedin'] != true){
    header("location: ../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous"> -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="//cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <!-- <link rel="stylesheet" href="../custom.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php require '../header.php'; ?>
    <div class = "main-content" >Hi, <?php echo $_SESSION['username'];?><br><br>- Welcome  to Admin dashboard of BuildProcure</div>
    <!-- <script src = "../vnavdropdown.js"></script> -->
     <?php include __DIR__ .'/../footer.php'; ?>
</body>
</html>
<?php
$html = ob_get_clean();

$dom = new DOMDocument('1.0', 'UTF-8');
libxml_use_internal_errors(true);

// Trick DOMDocument into UTF-8 safely (NO deprecation)
$dom->loadHTML(
    '<?xml encoding="UTF-8">' . $html,
    LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
);

// Remove stray invalid text nodes
$xpath = new DOMXPath($dom);
foreach ($xpath->query('//text()') as $textNode) {
    if (preg_match('/^[\'"\x{200B}\x{FEFF}]+$/u', trim($textNode->nodeValue))) {
        $textNode->parentNode->removeChild($textNode);
    }
}

echo $dom->saveHTML();

