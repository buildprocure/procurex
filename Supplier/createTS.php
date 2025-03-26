<?php 
    include '../_dbconnect.php';
    
    // error_reporting(-1);
    // ini_set("display_errors", 1);
    // ini_set('error_reporting', E_ALL);

    //include './nocatche.php';
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if(!isset($_SESSION['loggedin'])|| $_SESSION['loggedin'] != true){
        header("location: index.php");
        exit;
    } 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Timesheet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="//cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>

        .form-container {
            max-width: 600px;

            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        .form-group button {
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .form-group button:hover {
            background-color: #45a049;
        }
        .message {
            margin-top: 20px;
        }
        textarea{
            width: -webkit-fill-available;
        }
        ::placeholder {
            opacity: 0.5;
        }

        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
</head>
<body>
    <?php 
        //include './nocatche.php';
        include 'createTS_function.php';
        require '../_nav_afterLogin.php';
        require '../_vnav.php';
        include_once 'functions.php';
        $tableName = 'Timesheet';
        $prefix = 'TS';
        $timesheetID = generateNewId($conn, $tableName, $prefix);
        //q: why I'm getting generate
    ?>
    <div class = "main-content">
        <?php
            if($submitted){
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>Success! </strong>your request has been submitted successfully.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            }
        ?>
        <div class="form-container">
            <h1>Create Timesheet Form</h1>
            <form action="createTS.php" method="POST">
                <div class="form-group">
                    <label for="timesheetID">Timesheet ID</label>
                    <input type="text" id="timesheetID" name="timesheetID" value = '<?php echo htmlspecialchars($timesheetID) ?>'readonly>
                </div>
                <div class="form-group">
                    <label for="buyerName">Request To</label>
                    <input type="text" id="buyerName" name="buyerName" placeholder = "Buyer Name" required>
                </div>
                <div class="form-group">
                    <label for="serviceType">Service Type</label>
                    <select id="serviceType" name="serviceType" required>
                        <option value="">Select a product</option>
                        <option value="Design and Consulting">Design and Consulting</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="fromDate">From Date:</label>
                    <input type="date" id="fromDate" name="fromDate" required>
                <!-- </div>
                <div class="form-group"> -->
                    <label for="toDate">To Date:</label>
                    <input type="date" id="toDate" name="toDate" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>                    
                    <textarea id="description" name="description" rows="4" class="form-control" placeholder="Descriptions will appear here..." hidden readonly></textarea>
                    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#descriptionModal">
                    <i class="fas fa-plus"></i> 
                    </button> Click here to add description
                </div>
                <div class="form-group">
                    <label for="hours">Hours</label>
                    <input type="text" id="hours" name="hours" placeholder = "Claimed Hours" required>
                </div>

                <div class="form-group">
                    <button type="submit" name="submit">Submit Order</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Modal -->
    <div class="modal fade" id="descriptionModal" tabindex="-1" aria-labelledby="descriptionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="descriptionModalLabel">Add Description</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="descriptionFields">
                        
                    </div>
                    <button type="button" class="btn btn-secondary" id="addRowBtn">+</button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveDescriptions">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src = "../vnavdropdown.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const addRowBtn = document.getElementById('addRowBtn');
        const descriptionFields = document.getElementById('descriptionFields');
        const descriptionTextarea = document.getElementById('description');
        let currentSN = 1; // Initialize the serial number

        // Function to create a new row
        function createRow() {
            const newRow = document.createElement('div');
            newRow.className = 'row mb-3';
            newRow.innerHTML = `
                <div class="col-2">
                    <input type="number" class="form-control" name="number[]" value="${currentSN++}" required readonly>
                </div>
                <div class="col-4">
                    <input type="date" class="form-control" name="descriptionDate[]" required>
                </div>
                <div class="col-4 d-flex align-items-center ">
                    <input type="text" class="form-control" name="descriptionText[]" placeholder="Description" required>
                </div>
                <div class="col-2 d-flex align-items-center ">
                    <input type="number" class="form-control" name="hour[]" min="0" max="24" required>
                    <button type="button" class="btn btn-sm" id="removeRow">-</button>
                </div>
            `;
            descriptionFields.appendChild(newRow);
            // Attach the remove event listener for the newly created row
            newRow.querySelector('#removeRow').addEventListener('click', function () {
            removeRow(newRow);
            });
        }

        // Function to remove the row
        function removeRow(row) {
            descriptionFields.removeChild(row);

            // Update the serial number after removing a row
            updateSerialNumbers();
        }

        // Function to update serial numbers
        function updateSerialNumbers() {
            let serialNumber = 1; // Reset serial number
            const rows = descriptionFields.getElementsByClassName('row mb-3');
            for (let row of rows) {
                const numInput = row.querySelector('input[name="number[]"]');
                numInput.value = serialNumber++;
                console.log(numInput.value);
            }
            currentSN = serialNumber; // Update the current serial number for the next row
        }

        // Add the first row when the modal is opened
        createRow();

        // Event listener for the button to add a new row
        addRowBtn.addEventListener('click', function () {
            createRow();
        });

        // Event listener for saving descriptions
        document.getElementById('saveDescriptions').addEventListener('click', function () {
            console.log("Save button clicked");

            // Clear any existing descriptions before appending
            descriptionTextarea.value = '';
            let descriptions = '';

            // Loop through the description fields and add them to the main form
            const rows = descriptionFields.getElementsByClassName('row mb-3');
            let totalhour = 0;
            //console.log(rows);
            for (let row of rows) {
                const numInput = row.querySelector('input[name="number[]"]').value;
                const dateInput = row.querySelector('input[name="descriptionDate[]"]').value;
                const textInput = row.querySelector('input[name="descriptionText[]"]').value;
                const hourInput = row.querySelector('input[name="hour[]"]').value;
                
                const hour = parseFloat(hourInput) || 0;
                 // Format the description and add to the string
                descriptions += `${numInput}. ${dateInput}:- ${textInput} -${hourInput}\n`;

                    // Append the new description to the main description container
                    //mainDescriptionContainer.appendChild(descriptionDiv);
                    console.log(totalhour);
                totalhour += hour;
            }
            // Set the textarea value to the concatenated descriptions
            descriptionTextarea.value = descriptions.trim(); // Remove trailing newline

            //Assign total hours to hours field
            document.getElementById('hours').value = totalhour;

            // Show the textarea after saving
            descriptionTextarea.removeAttribute('hidden');

            $('#descriptionModal').modal('hide');
        });

    });
</script>
</body>
</html>
