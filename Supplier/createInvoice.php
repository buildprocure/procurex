<?php 
    include '../_dbconnect.php';

    //include './nocatche.php';
    if(session_status() == PHP_SESSION_NONE) {
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
    <title>Create Invoice</title>
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
        
         /* Style for the dropdown container */
         .dropdown-container {
            position: relative;
            width: 300px;
            font-family: Arial, sans-serif;
        }

        /* Style for the input field */
        input {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Style for the dropdown list */
        .dropdown-list {
            position: relative;
            top: 100%;
            left: 0;
            width: 100%;
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        /* Style for each dropdown item */
        .dropdown-item {
            padding: 10px;
            font-size: 14px;
            color: #333;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background-color 0.3s, color 0.3s;
        }

        /* Last item should not have bottom border */
        .dropdown-item:last-child {
            border-bottom: none;
        }

        /* Hover effect for dropdown items */
        .dropdown-item:hover {
            background-color: #f0f0f0;
            color: #007BFF;
        }

        /* Scrollbar styling for dropdown */
        .dropdown-list::-webkit-scrollbar {
            width: 8px;
        }

        .dropdown-list::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

     
     /* Style for the dropdown list */
     .dropdown-list1 {
            position: relative;
            top: 100%;
            left: 0;
            width: 100%;
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        /* Style for each dropdown item */
        .dropdown-item1 {
            padding: 10px;
            font-size: 14px;
            color: #333;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background-color 0.3s, color 0.3s;
        }

        /* Last item should not have bottom border */
        .dropdown-item1:last-child {
            border-bottom: none;
        }

        /* Hover effect for dropdown items */
        .dropdown-item1:hover {
            background-color: #f0f0f0;
            color: #007BFF;
        }

        /* Scrollbar styling for dropdown */
        .dropdown-list1::-webkit-scrollbar {
            width: 8px;
        }

        .dropdown-list1::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

    </style>
</head>
<body>
    <?php 
        //include './nocatche.php';  
        require 'createInvoice_controller.php';      
        require '../_nav_afterLogin.php';
        require '../_vnav.php';
        include_once 'functions.php';
        include_once '../_config.php';
        
        $tableName = 'Invoice';
        $prefix = 'INV';
        $invoiceID = generateNewId($conn, $tableName, $prefix);
        //q: why I'm getting generate
    ?>
    <div class = "main-content">
        <?php
            if($submitted){
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>Success! </strong>your request has been submitted successfully.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            }
        ?>
        <div class="form-container">
            <h1>Create Invoice Form</h1>
            <form action="./createInvoice.php" method="POST">
                <div class="form-group">
                    <label for="invoiceID">Invoice ID</label>
                    <input type="text" id="invoiceID" name="invoiceID" value = '<?php echo htmlspecialchars($invoiceID) ?>'readonly>
                </div>
                <div class="form-group" id="dropdown-container">
                    <label for="buyer_name">Buyer Name</label>
                    <input type="text" id="buyer_name" name="buyer_name" placeholder = "Buyer Name" autocomplete="off" required>
                    
                    <div class="dropdown-list1" id="dropdown_list1"></div>
                </div>
                <div class="form-group">
                    <label for="serviceType">Service Type</label>
                    <select id="serviceType" name="serviceType" required>
                        <option value="">Select a product</option>
                        <option value="Design and Consulting">Design and Consulting</option>
                    </select>
                </div>                
                <div class="form-group">
                    <label for="description">Add Approved Timesheet</label> 
                    <input type="hidden" name="timesheetIds" id="timesheetIds" value=""> 
                    <div id="descriptionFields">
                        
                    </div>
                    <button type="button" class="btn btn-secondary" id="addRowBtn">+</button>
                   <button type="button" class="btn btn-primary" id="saveDescriptions">Save</button>
                </div>
                
                <div class="form-group row">
                    <div class = "col-4">
                        <label for="hours">Hours</label>
                        <input type="Number" id="hours" name="hours" placeholder = "Claimed Hours" required>
                    </div>
                    <div class = "col-4">
                        <label for="hours">Rate</label>
                        <input type="Number" id="rate" name="rate" placeholder = "Rate" required>
                    </div>
                    <div class = "col-4">
                        <label for="hours">Total Invoice Amount $</label>
                        <input type="Number" id="amount" name="amount" placeholder = "Amount" require>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" name="submit">Submit Order</button>
                </div>
            </form>
        </div>
    </div>    

    <script src = "../vnavdropdown.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>

    <script>
        $(document).ready(function () {
            // When the user types in the input field
            $("#buyer_name").on("keyup", function () {
                let query = $(this).val();

                if (query.length >= 1) {
                    // AJAX request to fetch buyer list
                    $.ajax({
                        url: "ajax/getbuyerlist.php", // Backend script to fetch buyer names
                        method: "GET",
                        data: { query: query },
                        success: function (response) {
                            let buyers = JSON.parse(response);
                            let dropdownList = $("#dropdown_list1");
                            dropdownList.empty(); // Clear previous results

                            if (buyers.length > 0) {
                                // Populate dropdown with buyer names
                                buyers.forEach(function (buyer) {
                                    dropdownList.append(
                                        `<div class="dropdown-item1" data-name="${buyer.username}">${buyer.username}</div>`
                                    );
                                });

                                // Show the dropdown list
                                dropdownList.show();
                            } else {
                                // Hide the dropdown if no results
                                dropdownList.hide();
                            }
                        },
                        error: function () {
                            console.error("Error fetching buyer list");
                        }
                    });
                } else {
                    // Hide dropdown if input is less than 3 characters
                    $("#dropdown_list1").hide();
                }
            });

            // Event to select a buyer from the dropdown
            $(document).on("click", ".dropdown-item1", function () {
                let selectedName = $(this).data("name");
                $("#buyer_name").val(selectedName); // Set selected value to input field
                $("#dropdown_list1").hide(); // Hide the dropdown list
            });

            // Close the dropdown when clicking outside the dropdown
            $(document).on("click", function (e) {
                if (!$(e.target).closest("#buyer_name, #dropdown_list1").length) {
                    $("#dropdown_list1").hide(); // Hide dropdown if clicked outside
                }
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const addRowBtn = document.getElementById('addRowBtn');
            const saveDescriptionsBtn = document.getElementById('saveDescriptions');
            const descriptionFields = document.getElementById('descriptionFields');            
            let currentSN = 1; // Initialize the serial number
            const hoursInput = document.getElementById('hours');
            const rateInput = document.getElementById('rate');
            const amountInput = document.getElementById('amount');

             // Add the first row when the page is loaded
             createRow();

            // Event listener for the button to add a new row
            addRowBtn.addEventListener('click', function () {
                createRow();
            });
            // Function to create a new row
            function createRow() {
                const newRow = document.createElement('div');
                newRow.className = 'row mb-3';
                newRow.innerHTML = `
                    <div class="col-2">
                        <input type="number" class="form-control" name="number" value="${currentSN++}" required readonly>
                    </div>

                    <div class="col-8">
                        <input type="text" class="form-control" id ="descriptionText" name="descriptionText" placeholder="Enter Timesheet ID" required>
                    </div>
                    <div class="dropdown-list" id="timesheetDropdown"></div>
                    <div class="col-2 d-flex align-items-center ">
                        <button type="button" class="btn btn-sm" id="removeRow">-</button>
                    </div>
                    
                `;
                descriptionFields.appendChild(newRow);                
                addTimesheetDropdownLogic(newRow);

                newRow.querySelector('#removeRow').addEventListener('click', function () {
                removeRow(newRow);
                });
            }

            // Function to remove the row
            function removeRow(row) {
                console.log('Removing row:', row);
                descriptionFields.removeChild(row);

                // Update the serial number after removing a row
                let serialNumber = 1; // Reset serial number
                const rows = descriptionFields.getElementsByClassName('row mb-3');
                for (let row of rows) {
                    const numInput = row.querySelector('input[name="number"]');
                    numInput.value = serialNumber++;
                }
                currentSN = serialNumber; // Update the current serial number for the next row
            }
            // function updateSerialNumbers() {
            //     let serialNumber = 1; // Reset serial number
            //     const rows = descriptionFields.getElementsByClassName('row mb-3');
            //     for (let row of rows) {
            //         const numInput = row.querySelector('input[name="number[]"]');
            //         numInput.value = serialNumber++;
            //     }
            //     currentSN = serialNumber; // Update the current serial number for the next row
            // }
            
            function addTimesheetDropdownLogic(row) {                
                const dropdown =  row.querySelector('.dropdown-list');                
                const descriptionText = row.querySelector('input[name="descriptionText"]');


                descriptionText.addEventListener('keyup', function () {
                    const query = descriptionText.value.trim();
                    const buyer_name = document.getElementById('buyer_name').value;
                    if (query.length >= 1) {
                        // Make AJAX call to fetch Timesheet IDs
                        $.ajax({
                            url: 'ajax/getTimesheetList.php',
                            method: 'GET',
                            data: {
                                query: query, 
                                buyer_name: buyer_name                               
                            },
                            success: function (response) {
                                console.log("Response from server:", response);
                                const timesheetIds = JSON.parse(response);
                                dropdown.innerHTML = ''; // Clear previous dropdown items

                                if (timesheetIds.length > 0) {
                                    timesheetIds.forEach(function (id) {
                                        const dropdownItem = document.createElement('div');
                                        dropdownItem.className = 'dropdown-item';
                                        dropdownItem.textContent = id;
                                        dropdownItem.setAttribute('data-id', id); // Store ID in data attribute

                                dropdownItem.addEventListener('click', function () {
                                    descriptionText.value = dropdownItem.getAttribute('data-id'); // Set the value of descriptionText to selected Timesheet ID
                                    dropdown.style.display = 'none'; // Hide the dropdown
                                });
                                        dropdown.appendChild(dropdownItem);
                                    });
                                    dropdown.style.display = 'block';
                                } else {
                                    dropdown.style.display = 'none';
                                }
                            },
                            error: function () {
                                console.error('Error fetching Timesheet IDs');
                            }
                        });
                    } else {
                        dropdown.style.display = 'none';
                    }
                });
                // Hide dropdown on outside click
                document.addEventListener('click', function (e) {
                    if (!e.target.closest('.timesheetInput, .timesheetDropdown')) {
                        dropdown.style.display = 'none';
                    }
                });
            }
           

            // Event listener for saving descriptions
            saveDescriptionsBtn.addEventListener('click', function () {
                console.log("Save button clicked");

                // Hide "+" and "Save" buttons
                addRowBtn.style.display = 'none';
                saveDescriptionsBtn.style.display = 'none';

                // Clear any existing descriptions before appending
                let descriptions = '';
                let timesheetIds = []; // Array to hold Timesheet IDs

                // Loop through the description fields and add them to the main form
                const rows = descriptionFields.getElementsByClassName('row mb-3');
                
                for (let row of rows) {
                    const numInput = row.querySelector('input[name="number"]').value;                
                    const descriptionText = row.querySelector('input[name="descriptionText"]').value;

                    // Add Timesheet ID to the array
                    timesheetIds.push(descriptionText.trim());
                }
                // Set the textarea value to the concatenated descriptions
                console.log(timesheetIds);
                // Convert the timesheetIds array to a comma-separated string
                document.getElementById('timesheetIds').value = timesheetIds.join(',');    

                // Send Timesheet IDs to backend for calculating hours
                $.ajax({
                    url: 'ajax/getTotalHours.php', // Backend script to fetch hours
                    method: 'POST',
                    data: { timesheet_ids: timesheetIds }, // Send array of Timesheet IDs
                    success: function (response) {
                        $('#hours').val(response); // Update Hours field dynamically
                    },
                    error: function () {
                        console.error('Error fetching total hours');
                        $('#hours').val('Error'); // Fallback for errors
                    }
                });
            });
            function updateTotalInvoiceAmount() {
                const hours = parseFloat(hoursInput.value) || 0; // Get the value of hours, default to 0 if invalid
                const rate = parseFloat(rateInput.value) || 0; // Get the value of rate, default to 0 if invalid
                const totalAmount = hours * rate; // Calculate total

                amountInput.value = totalAmount.toFixed(2); // Update the total amount input field
                amountInput.value = totalAmount.toFixed(2); // Show the amount with two decimal points
            }
            rateInput.addEventListener('input', updateTotalInvoiceAmount);
        });

   </script>

</body>
</html>
