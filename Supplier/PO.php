<?php
include '_loggedindatabase.php';
//include 'iuploads.php'; 
// session_start();

if(!isset($_SESSION['loggedin'])|| $_SESSION['loggedin'] != true){
    header("location: ../index.php");
    exit;
}
 
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>iNotes -<?php echo $_SESSION['username']?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

    <link rel="stylesheet" href="//cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  </head>
  <body>
    <?php 
    require '../_nav_afterLogin.php';
    ?>

    <?php 
    require '../_vnav.php';
    ?>
    <div class ="main-content">
      <div class = "container my-4">
        <h2>Requested Purchase Orders</h2> 
      </div>
        <form id="receivedPO" class="mt-4">
            <div class="container">
                <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-group">
                    <label for="startDate" class="form-label">Start Date:</label>
                    <input type="date" name="startDate" id="startDate" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                    <label for="endDate" class="form-label">End Date:</label>
                    <input type="date" name="endDate" id="endDate" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                    <label for="requestID" class="form-label">Request ID:</label>
                    <input type="text" name="requestID" id="requestID" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                    <label for="buyerName" class="form-label">Requested By:</label>
                    <input type="text" name="buyerName" id="buyerName" class="form-control">
                    </div>
                </div>
                </div>
                <div class="row mt-3">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
                </div>
            </div>
        </form>
        <div id="result"></div> <!-- Container to display the results -->
    </div>
    <hr>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
    <script src = "//cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src = "../vnavdropdown.js"></script>
    <script>let table = new DataTable('#myTable');</script>
    <script>
      $(document).ready(function(){
        console.log("jquery");
        $('#receivedPO').on('submit', function(e){
          e.preventDefault(); //Prevent the default form submission

          //Gather form data
          var formData = {
            requestID: $('#requestID').val(),
            supplierName: $('#buyerName').val(),
            fromDate: $('#startDate').val(),
            toDate: $('#endDate').val()
          };
          console.log('Form Data: ', formData); // Debugging the captured form data

          //Ajax call
          $.ajax({
            type: 'POST',
            url: 'ajax/Received_PO.php',
            data: formData,
            success: function(response){
              //show the server response in the "result" div
              $('#result').html(response);
            },
            error: function(xhr, status, error){
              //Handle errors
              alert('Error: '+ error);
            }
          });
        });
      });
    </script>
  </body>
</html>
