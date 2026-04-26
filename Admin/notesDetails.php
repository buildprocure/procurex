<?php
include '_loggedindatabase.php';

if(!isset($_SESSION['loggedin'])|| $_SESSION['loggedin'] != true){
    header("location: index.php");
    exit;
}

require '../_nav.php';

if(isset($_GET['noteID'])){
    $nid = $_GET['noteID'];
    echo $nid;
} else{
    echo "Not passed";
}

$username = $_SESSION['username'];
$sql1 = "SELECT * FROM `notes` WHERE Username ='$username' && NoteID = '$nid'";
$result = mysqli_query ($conn, $sql1);
$row = mysqli_fetch_assoc($result);
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>iNotes-NoteDetails</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://getbootstrap.com/docs/5.3/assets/css/docs.css" rel="stylesheet">
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </head>
  <body>
    <h2>Note Details Form <h2>
    <p>This line is edited for MCP testing </p>
    <div class="container text-center">
        <div class="row">
            <div class="col">
                <form>
                    <label for='noteid'>Note ID:</label>
                    <input type = "text" value = "<?php echo $nid; ?> " readonly>
                    <label for='Title'>Note Title:</label>
                    <input type = "text" value = "<?php echo $row['Title']; ?> " readonly>  
                    <label for='Description'>Decription:</label>
                    <input type = "text" value = "<?php echo $row['Description']; ?> " readonly>           
                </form> 
            </div>
            <div class="col">
            <iframe src="/mnt/useruploads/rabin/456.pdf"><iframe>
                <?php 
                $_SERVER['DOCUMENT']
                ?>
            </div>
            </div>
        </div>
    </div>
  </body>
</html>


