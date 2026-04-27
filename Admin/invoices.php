<?php
include '_loggedindatabase.php';

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
    
  </head>
  <body style="background-image: linear-gradient(to bottom right, rgb(0, 238, 255), rgb(184, 22, 220)  );">
    <!-- Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h1 class="modal-title fs-5" id="editModalLabel">Edit this notes</h1>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action = "loggedin.php" method = "post" >
            <div class="modal-body">
              <input type="hidden" name="snoEdit" id = "snoEdit">
              <div class="mb-3">
                <label for="title" class="form-label">Note title</label>
                <input type="text" name = "titleEdit" class="form-control" id="titleEdit" aria-describedby="emailHelp">
              </div>
              <div class="mb-3">
                <label for="Desc" class="form-label">Note Description</label>
                <textarea name = "DescEdit" class="form-control" rows = "3" id="DescEdit"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <?php
      echo '<nav class="navbar navbar-expand-lg navbar-dark bg-primary ">
      <div class="container-fluid">
          <a class="navbar-brand" href="#">iNotes</a>
          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
              <span class="navbar-toggler-icon"></span>
          </button>
          <div class="collapse navbar-collapse" id="navbarSupportedContent">
              <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                  <li class="nav-item">
                  <a class="nav-link active" aria-current="page" href="./loggedinhome.php">Home</a>
                  </li>
                  <li class="nav-item">
                  <a class="nav-link" href="#">About</a>
                  </li>
                  <li class="nav-item">
                      <a class="nav-link" href="#">Contact us</a>
                  </li>
                  <form class="d-flex" role="search">
                  
              </form>
              </ul>
              <!--  <form class="d-flex" role="search">
                  <li>
                  <a  style ="color:white;"><u>Hi '.$_SESSION['username'].'</u></a>
                  </li>
                  <li>
                  <a class="navbar-brand" href="./logout.php">
                  <img src="Logout.png" alt="Logo" width="30" height="24" class="d-inline-block align-text-top">                    
                  </a>
                  </li>
              </form> -->
              <ul class="navbar-nav col-1  mb-2 mb-lg-0">
                  <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                      <img src="../img/user.png" style="width: 30px;" class="rounded-pill">
                    </a>
                    <ul class="dropdown-menu dropdown-right">
                      <li><a class="dropdown-item" href="#">Hi '.$_SESSION['username'].'</a></li>
                      <li><a class="dropdown-item" href="#">Another action</a></li>
                      <li><hr class="dropdown-divider"></li>
                      <li><a class="dropdown-item" href="./logout.php">Logout</a></li>
                    </ul>
                  </li>
              </ul>
          </div>
      </div>
  </nav>'; 
    
    if($insert){
      echo '<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>Success! </strong> your notes has been submitted successfully.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
      }
    if($update){
      echo '<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>Success! </strong> your notes has been updated successfully.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
      }
    if($delete){
      echo '<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>Success! </strong> your notes has been deleted successfully.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
      }
    if ($emptyerror){
      echo '<div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>All the fields marked with <span style="color: red;">*</span> are mandatory.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    if ($noteIDerror){
      echo '<div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>This noteID has already been used. Please try another noteID.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    ?>
    <div class = "container my-3">
      <h2>Add a note</h2>
      <form action = "loggedin.php" method = "post" enctype="multipart/form-data">
          <div class="mb-3">
            <label for="noteid" class="form-label">Note ID <span style="color: red;">*</span></label>
            <input type="text" name = "noteid" class="form-control" id="noteid" aria-describedby="emailHelp">
          </div>
          <div class="mb-3">
            <label for="title" class="form-label">Note title <span style="color: red;">*</span></label>
            <input type="text" name = "title" class="form-control" id="title" aria-describedby="emailHelp">
          </div>
          
          <div class="mb-3">
            <label for="Desc" class="form-label">Note Description <span style="color: red;">*</span></label>
            <textarea name = "Desc" class="form-control" rows = "3" id="Desc"></textarea>
          </div>
          
          <?php
            if($fileExistError){
                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">Sorry, file already exists.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            }
            if($fileSizeError){
                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">File is too large.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            }
            if($fileTypeError){
                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">Sorry, only .pdf, .doc, .docx, .docm files are allowed.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            }
          ?>

          <div class="mb-3">
            <label for="formFile" class="form-label">Please choose file to upload.</label>
            <input class="form-control" type="file" id="formFile" name = "my_file">
          </div>
                        
          <button type="submit" class="btn btn-primary">Add Note</button>
      </form>
    </div>
    <div class = "container my-4">
      <table class="table table-bordered table-striped" color="white;" id = "myTable">
        <thead>
          <tr>
            <th scope="col">SN</th>
            <th scope="col">NoteID</th>
            <th scope="col">Title</th>
            <th scope="col">Description</th>
            <th scope="col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $username = $_SESSION['username'];
            
            $sql = "SELECT * FROM `notes` WHERE username = '$username'";
            $result = mysqli_query ($conn, $sql);
              
            if($result){
              $num = mysqli_num_rows($result);
            }
            
            $SNo = 0;
            while ($row = mysqli_fetch_assoc($result)){
              $SNo=$SNo+1;
              echo " <tr>
                        <th scope='row'>".$SNo."</th>
                        <td> <a href = 'notesDetails.php?noteID=".$row['NoteID']."'> ".$row['NoteID']." </a></td>
                        <td>".$row['Title']."</td>
                        <td>".$row['Description']."</td>
                        <td><button type='button' class='edit btn btn-primary' data-bs-toggle='modal' data-bs-target='#editModal' id=".$row['SN'].">Edit</button>
                        <button type='button' class='delete btn btn-primary'  id=d".$row['SN'].">Delete</button></td>
                      </tr>";
            }
          ?> 
        </tbody>
      </table>
    </div>
    <hr>
  
    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
    <script src = "//cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script>let table = new DataTable('#myTable');</script>
    <script> 
    edits = document.getElementsByClassName('edit');
      Array.from(edits).forEach((element)=>{
        element.addEventListener("click", (e)=>{
          console.log("edit", );
          tr = e.target.parentNode.parentNode;
          title = tr.getElementsByTagName("td")[1].innerText;
          Desc = tr.getElementsByTagName("td")[2].innerText;
          console.log(title, Desc);
          titleEdit.value = title;
          DescEdit.value = Desc;
          snoEdit.value = e.target.id;
          console.log(e.target.id);
          $('#editModal').modal('toggle');
        })
      }) 

       deletes = document.getElementsByClassName('delete');
        Array.from(deletes).forEach((element)=>{
          element.addEventListener("click", (e)=>{
            console.log("edit", );
            //SN.value = e.target.id;
            SN = e.target.id.substr(1,);
            //console.log(SN);
            if(confirm("Are you sure you want to delete this note!")){
              console.log("yes--");
              console.log(SN);
              window.location = `/loggedin.php?delete=${SN}`;
            } else {
              console.log("No");
            }
          }) 
        }) 
    </script>
  </body>
</html>
