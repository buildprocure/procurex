<?php
				require_once('lib/config.php');
				include('inc/header.php');
				include(SITE_ROOT .'inc/PO_Func.php');
				include(SITE_ROOT .'inc/Functions.php');
				
					$buyer_files_path = BASE_URL.BUYER_WEB_FILES;
					$user_id = $_SESSION['User']['ID'];
					$user_name = $_SESSION['User']['FullName'];
					$supplierParticipantID = $_SESSION['User']['SupplierParticipantID'];
					$supplierExternalID = $_SESSION['User']['SupplierExtID'];
                if(isset($_COOKIE['InvDocFileName'])){
                    setcookie('InvDocFileName', '',time() - 3600,"/",$env['COOKIE_URL'],FALSE,FALSE);
                }
						$mappingheaders = getAllMappingHeaders($_SESSION['User']['SupplierExtID']);
				?>
				<link href="<?php echo SITE_URL;?>css/file-uploader/uploadfile.css" rel="stylesheet">
     <link href="<?php echo SITE_URL;?>css/bootstrap-select.min.css" rel="stylesheet">
    <link href="EarlyPayment/css/epp.css" rel="stylesheet">
 	 <style>


        #startDate{
            width: 54% !important;


        }

        .inpcntrlrw .col-md-6, .inpcntrlrw .col-lg-6, .inpcntrlrw .col-lg-3, .inpcntrlrw .col-md-3 {
            margin-bottom: 3px !important;

        }
        .bootstrap-select:not([class*=col-]):not([class*=form-control]):not(.input-group-btn) {
            width: 54% !important;

        }
        div.bs-searchbox>input {
            width: 100% !important;

        }

        .bootstrap-select .status {
            background: #f0f0f0 !important;
            clear: both !important;
            color: #999 !important;
            font-size: 11px !important;
            font-style: italic !important;
            font-weight: 500 !important;
            line-height: 1 !important;
            margin-bottom: -5px !important;
            padding: 10px 20px !important;
        }
.multiselect-wrapper ul
{
display:block !important;
 color: #999 !important;
}
.multiselect-wrapper ul li a
{
color: #000 !important;
font-weight: 400 !important;

}
.multiselect-wrapper ul a:hover
{
background: #f0f0f0 !important;
}

.dropdown-header
{
background: #fff !important;
color: #777 !important;
font-weight: 12px !important;
}
.dropdown-menu.open{overflow: hidden; position: relative; max-height:300px;}

    </style>

				<script type="text/javascript" src="<?php echo SITE_URL;?>js/file-uploader/jquery.uploadfile.min.js"></script>
				<script type="text/javascript" src="<?php echo SITE_URL;?>js/file-uploader/jquery.form.js"></script>
				<script src="<?php echo SITE_URL;?>js/nccs.js" type="text/javascript"></script>
				 <script src="<?php echo SITE_URL;?>js/bootstrap-tools.js" type="text/javascript"></script>
				 <script src="<?php echo SITE_URL;?>js/workflow/workflow-script.js"></script>
				 <script src="js/bootstrap-datetimepicker.min.js" type="text/javascript"></script>
    <script src="<?php echo SITE_URL;?>js/bootstrap-tools.js" type="text/javascript"></script>
    <script src="js/typeahead.jquery.js" type="text/javascript"></script>
    <script src="js/bloodhound.min.js" type="text/javascript"></script>
    <script src="js/typeahead.bundle.min.js" type="text/javascript"></script>
    <script src="<?php echo SITE_URL;?>js/bootstrap-select.js" type="text/javascript"></script>

    <script src="<?php echo SITE_URL;?>js/bootstrap-multiselect-search/js/ajax-bootstrap-select.min.js" type="text/javascript"></script>


    <script>





        $(document).ready(function () {
 			$(".bs-placeholder").css('border-color', '');
                $('#txt_cust_ext_id')
                .selectpicker({
                    liveSearch: true,
                    maxOptions:1
                })
                .ajaxSelectPicker({
                    ajax: {
                        url: 'ajax/getExternalID.php',
                        beforeSend:function(){  $(".error").removeClass("error");

						 },
                        data: function () {
                            var params = {
                                q: '{{{q}}}',

                            };
                            return params;
                        }
                    },
                    locale: {
                        emptyTitle: 'Search For Customer',
                        searchPlaceholder:'Please provide first 3 letters'
                    },
                    preprocessData: function(data){
                        return data;
                    },
                    minLength:3,
                    preserveSelected: true,
                    preserveSelectedPosition:"before"
                });


        });

    </script>
				<script type="text/javascript">

				$(document).ready(function(){

					 var url = $("#QBVideo").attr('src');
					 var iframe = document.getElementById('QBVideo');
					 iframe.src = "";
				 /* $('#myModal').on('hidden.bs.modal',function(){
						    	 $("#QBVideo").attr('src', '');
						   });

					      $("#myModal").on('shown.bs.modal', function(e){
				                alert("hello");
						        $("#QBVideo").attr('src', url);
						    }).modal('show'); */



						       $(document).on('click','#quickBook', function(e){
							      $("#QBVideo").attr('src', url);
				    		    });

						       $(".close").click(function(){
						    	   var iframe = document.getElementById('QBVideo');
						    	   iframe.src = "";
						    	 });

					$(document).on('click','#delete_mapping_btn', function(e){
				       var mappingId = $("#mappingheader").val();
				       if(mappingId != 'new'){
					   			BootstrapDialog.confirm({
						        title: 'Message',
						        message: "Do you really want to delete selected Mapping?",
						        type: BootstrapDialog.TYPE_DANGER,
						        btnCancelLabel:'Cancel',
                                btnOKLabel: 'Yes',
						        btnOKClass:'remitra_primary_button',
						            callback: function(result) {
						            if(result)
						            {
						                var settingLoader   =  new ajaxLoader('.ids_content');
						                $.ajax({
					                     url: '<?php echo SITE_URL;?>ajax/deleteInvoiceMapping.php',
					                     type: 'POST',
					                     dataType: 'json',
					                     data: {'mappingId':mappingId},
					                     success: function(data){
					                            if(data.status == 'success'){
					                                $('.messageBox').html('Mapping successfully deleted.');
					                                $("#mappingheader").html(data.html);
					                                $('.messageBox').show();
					                                setTimeout(function(){ $('.messageBox').html("");$('.messageBox').hide();},10000);
					                            }
					                            else{
					                                $('.messageBox').html('Mapping delete failed.');
					                                $('.messageBox').addClass("alert-danger");
					                                $('.messageBox').show();
					                                setTimeout(function(){ $('.messageBox').html("");$('.messageBox').removeClass("alert-danger");$('.messageBox').hide();},10000);
					                            }
					                            settingLoader.remove();
					                        }
						                });
						            }
						        }
						    });
						   }
						   else{
						     BootstrapDialog.alert({
						        title: 'Message',
						        message: 'Please select a mapping to delete.',
						        type: BootstrapDialog.TYPE_WARNING,
						        closable: true,
						        draggable: true,
						        btnOKLabel: 'OK',
						        btnOKClass: 'btn-primary btn-success',
						        callback: function(result) {

						        }
				    		});
				   		}

	    		    });


				});


				$(document).ready(function() {
					var uploadResult = '';
					$("#mulitplefileuploader").uploadFile({
						url: "vendor_doc.php",
						method: "POST",
						allowedTypes:"csv,xlsx,xls",
						autoSubmit:true,
						fileName: "myfile1",
						multiple: false,
						maxFileSize:1024*10240,
						onSuccess:function(files,data,xhr)
						{
							uploadResult = data;
							$("div.ajax-file-upload-green:contains('Done')").remove();
							if(data.trim()=='success'){
								$("#skipTo").show();
								$("#skipTo").attr('disabled', false);
								$("#skipNext").hide();
								$("#skipNext").attr('disabled', true);
								$("#uploadNext").hide();
								$("#uploadNext").attr('disabled', true);
								$( "#ConfirmMsg" ).html("Document Uploaded Successfully - Bottom Right Please click the Next Button to Continue.");

								alert_dialog('type-success','Document Uploaded Successfully - Bottom Right Please click the Next Button to Continue.',null,null,null);
								// $( "#dialog-confirm" ).dialog({
								// 	resizable: false,
								// 	height:200,
								// 	width:500,
								// 	modal: true,
								// 	buttons: {
								// 		"OK": function() {
								// 			$( this ).dialog( "close" );
								// 			return false;
								// 		}
								// 	}
								// });
							}else{
								$.ajax({
									url: 'ajax/getLogFileNames.php',
									type: 'POST',
									dataType: 'JSON',
									success: function(data){
										originFile = data.originFile;
										logFile = data.logFileName;
										$.ajax({
											url: 'ajax/sendZipMail.php',
											type: 'POST',
											data:{originFile:originFile, logFile:logFile},
											success: function(data1){
											}
										});
									}
								});
								$("#skipTo").show();
								$("#skipTo").attr('disabled', false);
								$("#skipNext").hide();
								$("#skipNext").attr('disabled', true);
								$("#uploadNext").hide();
								$("#uploadNext").attr('disabled', true);
								$('.model-headtext').text('ERROR');
								$( "#ConfirmMsg" ).html("The File cannot be uploaded. This might be due to hidden characters causing file corruption or invalid file format.<br><br> Please try the following:<br><br> &nbsp;&nbsp; 1. Copy complete data into a new file and upload the new file.<br>&nbsp;&nbsp; 2. Upload the file in a different format.<br>&nbsp;&nbsp; 3. If the above suggestions does not help, feel free to reach out to<br> &nbsp;&nbsp;&nbsp; <?php echo SENDER_DEFAULT_MAIL_ID; ?> with your attachment and issue.");
		                        $("#myModal").modal('show');
                                $(".ajax-file-upload-statusbar").html("");
								// $( "#dialog-confirm" ).model({
								// 	resizable: false,
								// 	height:300,
								// 	width:550,
								// 	modal: true,
								// 	buttons: {
								// 		"OK": function() {
								// 			$( this ).dialog( "close" );
								// 			return false;
								// 		}
								// 	}
								// });
							}
						},
						onError: function(files,status,errMsg)
						{

						alert_dialog('type-danger','Upload is Failed',null,null,null);
							// $( "#ConfirmMsg" ).html("<div class='error'>Upload is Failed</div>");
							// 	$( "#dialog-confirm" ).dialog({
							// 		resizable: false,
							// 		height:200,
							// 		width:500,
							// 		modal: true,
							// 		buttons: {
							// 			"OK": function() {
							// 				$( this ).dialog( "close" );
							// 				return false;
							// 			}
							// 		}
							// 	});





						},
						dynamicFormData: function()
						{
						},
						onSubmit:function(files)
						{
						}
					});


				$("input[type=radio][name='txt_cust_type']").click(function() {
					if (this.value == "Multiple") {
 					$("#cust_select_msg").hide('');
					$(".bs-placeholder").css('border-color', '');
				 	 $("#row_customer_select").hide('slow');
					  $("#txt_cust_ext_id").val('');
					  $("#txt_cust_ext_id").selectpicker("refresh");
					}else if (this.value == "Single") {
					$("#row_customer_select").show('slow');
					}
 				});




					$('#skipTo').click(function(){

					var  cuid = $("#txt_cust_ext_id").val();
						if(cuid==null)
						{
						cuid="";
 						}
 						 if(cuid!='')
						 {
						 var cust_name=$( "#txt_cust_ext_id option:selected" ).text();
  								var dialogInstance=new BootstrapDialog.show({
 								title:'Alert',
								message: "You have selected to send all of your invoices to <strong>"+cust_name+"</strong>. Please verify that this is correct before confirming this action.",
								type:'type-warning',
									buttons: [{label: 'Yes',
									cssClass:"remitra_primary_button",
										action: function(dialogRef){
										dialogRef.close();
										call_skipTo();
 										}
									},
									{
										label:'No',
										cssClass:"remitra_cancel_button",
										action: function(dialogRef){
										dialogRef.close();
 										}
									}
									]
								});

						}
						else
						{
						call_skipTo();
						}
					});

					function call_skipTo()
					{
						var mappingheader = $("#mappingheader").val();
 						var  cuid = $("#txt_cust_ext_id").val();
						if(cuid==null)
						{
						cuid="";
 						}
						var cust_type_single = $("input[name='txt_cust_type']:checked"). val();
 						 if(cust_type_single == 'Single' && cuid=='')
						{

						 $('#cust_select_msg').html('Please select customer name');
						 $('#cust_select_msg').show('slow');
 						 $(".bs-placeholder").css('border-color', 'red');
						 setTimeout(function() {
						  $('#cust_select_msg').hide('slow');
						  $(".bs-placeholder").css('border-color', '');
						    }, 5000);

 						}
						else
						{
						var additionaldocument = $("#additionaldocument").val();
						var editmode = $("input[name='editmapping']:checked"). val();

						if(mappingheader=="new" || editmode=="yes"){
							var settingLoader=  new ajaxLoader('.ids_content');
							window.location.href = "mapping_fields.php?h="+mappingheader+"&ad="+additionaldocument+"&cuid="+cuid;
							//setTimeout(function(){settingLoader.remove();},1000);
						}else{
							var settingLoader=  new ajaxLoader('.ids_content');
							$.ajax({
								type: "POST",
								url: "checkVendorDocExists.php",
								data: {mappingheader:mappingheader},
								dataType: "html",
								success: function(data)
								{
									settingLoader.remove();
									if(data.trim()=='equal'){

										window.location.href = "invoice_process.php?h="+mappingheader+'&e=false&ad='+additionaldocument+"&cuid="+cuid;

									}else{

										$( "#ConfirmMsg" ).html("We have noticed that you fields are mapped differently from what was saved. Do you want to change your mapping fields?");



								var dialogInstance=new BootstrapDialog.show({

								title:'Message',
								message: "We have noticed that you fields are mapped differently from what was saved. Do you want to change your mapping fields?",
								type:'type-success',
								buttons: [{label: 'Yes',
								cssClass:"btn-success",
								action: function(dialogRef){
								dialogRef.close();

								window.location.href = "mapping_fields.php?h="+mappingheader+"&ad="+additionaldocument+"&cuid="+cuid;
								}
								},
								{
									label:'No',
									cssClass:"remitra_cancel_button",
                                 	action: function(dialogRef){
									dialogRef.close();
									window.location.href = "invoice_process.php?h="+mappingheader+'&e=false&ad='+additionaldocument+"&cuid="+cuid;
								}
								}
								]	});



										}
								},
								cache: false
							});
					   }
					   }

					}
					$('#uploadNext').click(function(){
						var  cuid = $("#txt_cust_ext_id").val();
						if(cuid==null)
						{
						cuid="";
 						}
 						 if(cuid!='')
						 {
						 var cust_name=$( "#txt_cust_ext_id option:selected" ).text();
  								var dialogInstance=new BootstrapDialog.show({
 								title:'Alert',
								message:"You have selected to send all of your invoices to "+cust_name+". Please verify that this is correct before confirming this action.",
								type:'type-warning',
									buttons: [{label: 'Yes',
									cssClass:"btn-success",
										action: function(dialogRef){
										dialogRef.close();
										call_uploadNext();
 										}
									},
									{
										label:'No',
										cssClass:"btn-warning",
										action: function(dialogRef){
										dialogRef.close();
 										}
									}
									]
								});

						}
						else
						{
						call_uploadNext();
						}

					});

					function call_uploadNext()
					{
						var mappingheader = $("#mappingheader").val();
						var cuid = $("#txt_cust_ext_id").val();
 						if(cuid==null)
						{
						cuid="";
 						}
						var cust_type_single = $("input[name='txt_cust_type']:checked"). val();
 						 if(cust_type_single == 'Single' && cuid=='')
						{
						$('#cust_select_msg').html('Please select customer name');
						$('#cust_select_msg').show('slow');
						$(".bs-placeholder").css('border-color', 'red');
							 setTimeout(function() {
						  $('#cust_select_msg').hide('slow');
						  $(".bs-placeholder").css('border-color', '');
						    }, 5000);
						}
						else
						{

						var additionaldocument = $("#additionaldocument").val();
						var settingLoader=  new ajaxLoader('.ids_content');
						window.location.href = "mapping_fields.php?h="+mappingheader+"&ad="+additionaldocument+"&cuid="+cuid;
						//setTimeout(function(){settingLoader.remove();},1000);
						}
					}
					$('#skipNext').click(function(){
						 var  cuid = $("#txt_cust_ext_id").val();
						if(cuid==null)
						{
						cuid="";
 						}
 						 if(cuid!='')
						 {
						 var cust_name=$( "#txt_cust_ext_id option:selected" ).text();
  								var dialogInstance=new BootstrapDialog.show({
 								title:'Alert',
								message:"You have selected to send all of your invoices to "+cust_name+". Please verify that this is correct before confirming this action.\nFor invoices to be delivered to multiple customers, please choose 'No' and remove the 'Select Customer' option before resubmitting your invoice file.",
								type:'type-warning',
									buttons: [{label: 'Yes',
									cssClass:"btn-success",
										action: function(dialogRef){
										dialogRef.close();
										call_skipNext();
 										}
									},
									{
										label:'No',
										cssClass:"btn-warning",
										action: function(dialogRef){
										dialogRef.close();
 										}
									}
									]
								});

						}
						else
						{
						call_skipNext();
						}

					});
					function call_skipNext()
					{
						var mappingheader = $("#mappingheader").val();
						var  cuid = $("#txt_cust_ext_id").val();
						if(cuid==null)
						{
						cuid="";
 						}
						var additionaldocument = $("#additionaldocument").val();
						var settingLoader=  new ajaxLoader('.ids_content');
						window.location.href = "invoice_process.php?h="+mappingheader+'&e=false&ad='+additionaldocument+"&cuid="+cuid;
						//setTimeout(function(){settingLoader.remove();},1000);
					}

					$("input [type='file']").addClass('btn btn-primary btn-success');
				});
				 function alert_dialog(type,message,reload,timout,size){
								size=(typeof size !== 'undefined')?size:false;
								var dialogSize=BootstrapDialog.SIZE_NORMAL;
								if(size){
								dialogSize=BootstrapDialog.SIZE_WIDE;
								}
								var dialogInstance=new BootstrapDialog.show({
								size:dialogSize,
								title:'Message',
								message: message,
								type:type,
								buttons: [{label: 'Close',
                                cssClass:"remitra_cancel_button",
								action: function(dialogRef){
								dialogRef.close();
								if(reload==true){
								window.location.reload(true);
								}

								}
								}]
								});
								if(timout==true){
								setTimeout(function(){

								if(reload==true){
								window.location.reload(true);
								}else{
								dialogInstance.close();
								}

								},6000);
								}


						}
			</script>
			<style type="text/css">
				.panel-blue
				{
                    color: #000000 !important;
                    background-color: #d3e9ef !important;
                    border-color: #d3e9ef !important;

				}
				.p-list p {
		    text-indent:1em;
		    list-style-type:disc;
		    display:list-item;
		    margin-left: 2em;
		}
		.label-align
{
	vertical-align: middle !important;
	padding-left: 4px;
}
#myVideo{
   width: 200% !important;
    margin-left: -54% !important;
}

.cust-btn-chng {
	margin-bottom: 7px;
    margin-top: -7px;
    padding: 0px;
    margin-left: -50px;
}

.cust-title {
	margin-left: -15px;
    margin-bottom: 10px;
}
			</style>
		<div class="ids_content">
			<div class="row" >

				<div class="col-sm-12 pspd">
					<div class="page-header header-cus-s">
						<div class="col-sm-3 cust-title">
							<h1>Invoice File Upload</h1>
						</div>
						<?php if(is_billingadmin()){ ?>
							<div class="col-sm-2 cust-btn-chng">
								<a href="dashboard.php" class="btn remitra_primary_button btn-sm">Accelerate Invoice Payments</a>
							</div>
						<?php } ?>
					</div>
				</div>
				<div class="col-sm-12 pspd">
				    <div class="row f-w">


							<div class="alert alert-info remitra_panel-heading" >
							<p style="text-align: :center" >
							Please be aware that you can use this tool to send EDI or electronic invoices to ANY healthcare customers. Please contact <a href="mailto:<?php echo REMITRA_SUPPLIER_SUPPORT_EMAIL;?>"><?php echo REMITRA_SUPPLIER_SUPPORT_EMAIL;?></a> to get a new customer enabled.
							</p>
							</div>

					</div>


				</div>
				<div class="col-sm-8 col-sm-offset-2">
							<div class="panel panel-default">
								<div class="panel-heading panel-blue" style="margin-bottom: 1px;">
									<h4 class="panel-title">
										<a data-toggle="collapse" href="#help_guide1" aria-expanded="true" class="">Invoice Entry Form</a>
									</h4>
								</div>
								<div id="help_guide1" class="panel-collapse collapse in" style="text-align: center; margin-top: 20px; margin-bottom: 20px;" aria-expanded="true">	
										<a target="_blank" class="media" href="/docs/InvoiceEntryForm.pdf">
											<button onclick="" style="padding: 8px 10px;margin: 0 10px;border: none; width:150px; white-space:normal; height:auto;" class="btn btn-md remitra_primary_button" id="invoce_entry_form_pdf">
											Invoice Entry Form Guide </button>
										</a> 
								</div>
								
								<div class="panel-heading panel-blue" style="margin-bottom: 1px;">
									<h4 class="panel-title">
										<a data-toggle="collapse" href="#help_guide2" aria-expanded="true" class="">PO Flip</a>
									</h4>
								</div>
								<div id="help_guide2" class="panel-collapse collapse in" style="text-align: center; margin-top: 20px; margin-bottom: 20px;" aria-expanded="true">	
										<a target="_blank" class="media" href="/docs/POFlip.pdf">
											<button onclick="" style="padding: 8px 10px;margin: 0 10px;border: none; width:80px; white-space:normal; height:auto;" class="btn btn-md remitra_primary_button" id="invoce_entry_form_pdf">
											PO Flip Guide </button>
										</a> 
								</div>
								
								<div class="panel-heading panel-blue" style="margin-bottom: 1px;">
									<h4 class="panel-title">
										<a data-toggle="collapse" href="#help_guide3" aria-expanded="false" class="collapsed">Invoice File Upload</a>
									</h4>
								</div>
								<div id="help_guide3" class="panel-collapse collapse in" style="text-align: center; margin-top: 20px; margin-bottom: 20px;" aria-expanded="true">	
										<a target="_blank" class="media" href="/docs/RemitraInvoiceUpload.pdf">
											<button onclick="" style="padding: 8px 10px;margin: 0 10px;border: none; width:150px; white-space:normal; height:auto;" class="btn btn-md remitra_primary_button" id="invoce_entry_form_pdf">
											Invoice File Upload Guide </button>
										</a> 
								</div>
								
							</div> 
						</div>

			    <div class="col-sm-12 pspd">
				<div>
				

				  <div class="panel-group" id="accordion">
					  
					<div class="panel panel-default">
					  <div class="panel-heading remitra_panel-heading">
						<h4 class="panel-title">
						  <a data-toggle="collapse" data-parent="#accordion" href="#collapse1">Step 1: Export your file</a>
						</h4>
					  </div>
					  <div id="collapse1" class="panel-collapse collapse in">
						<div class="panel-body"><p>Export your file from your existing Accounts Receivable system or a Excel. Your file must be formatted as a .CSV,
							.XLS or .XLSX file.</p>
							<p style="padding-bottom: 7px !important;">Guides for exporting invoices: </p>
							<div style="padding-left: 31px;" class="p-list">
							<p>





					<a id="quickBook" href="#myModal1"  data-toggle="modal">Intuit / Quickbooks</a>

					</p>
							<p><a href="https://help.sap.com/saphelp_nw73/helpdata/en/d2/11a28fc26d4042a6d230a9783152f2/content.htm" target="_blank">SAP Business One</a></p>
							<p><a href="https://docs.oracle.com/cd/E26228_01/doc.93/e21959/ch_over_imp_exp.htm#WEATT110" target="_blank">Oracle JD Edwards</a></p>
							</div>



				<div id="myModal1" class="modal">
						<div class="modal-dialog">
							<div class="modal-content">
								<div class="modal-header">
									<button type="button" class="close" data-dismiss="modal" aria-hidden="true">x</button>
									<h4 class="modal-title">Export to CSV - Quick Books</h4>
								</div>
								<div class="modal-body">
								    <video src="<?php echo $buyer_files_path; ?>QuickBooks - Export CSV.mp4"  id="QBVideo" width="560" height="315" allowfullscreen preload controls>
								</div>
							</div>
						</div>
					</div>
							</div>
					  </div>
					</div>
					<div class="panel panel-default">
					  <div class="panel-heading remitra_panel-heading">
						<h4 class="panel-title">
						  <a data-toggle="collapse" data-parent="#accordion" href="#collapse2">Step 2: Format properly</a>
						</h4>
					  </div>
					  <div id="collapse2" class="panel-collapse collapse in">
						<div class="panel-body"><p>Please review the example file to be sure your file is formatted properly. Do not include special characters in your file: - / , ' , ( , ) , _  as well as comma(,). You will not be able to submit your invoice file without the following fields.<p>
							<div class="col-sm-12">
							<div class="col-sm-4 p-list" >
							<p>Bill To Address</p>
							<p>Bill To City</p>
							<p>Bill To State</p>
							<p>Bill To Zip Code</p>
							<p>Invoice Number</p>
							</div>
							<div class="col-sm-4 p-list">
							<p>Ship To Address</p>
							<p>Ship To City</p>
							<p>Ship To State</p>
							<p>Ship To Zip Code</p>
							<p>Quantity</p>
							</div>
							<div class="col-sm-4 p-list">
							<p>Unit Price</p>
							<p>Extended Amount</p>
							</div>
							</div>

							<div class="col-sm-12">
							<div class="row">
							<p>1. Note that both <b>Bill To</b> and <b>Ship To</b> addresses are not mandatory at the same time. In case you have only one address, you may proceed by updating the address in either of the <b>Bill To</b> OR <b>Ship To</b>.
							</p>
							</div>
							<div class="row">
							<p>
							2. <b>Extended Amount</b> will be calculated automatically from <b>Quantity</b> and <b>Unit Price</b> if it is missing in the file. </br>
							</p>
							</div>
							<div class="row">
							<p>

							3. For invoices having <b>Extended Amount</b> but missing <b>Quantity</b> and <b>Unit Price</b>, <b>Quantity</b> will be 1 and <b>Unit Price</b> is considered same as <b>Extended amount</b>.</br>You can also download the template directly.</p>
							</div>
							<div class="row" style="text-align: center;">
							<a href="downloadSample.php" class="btn remitra_primary_button btn-sm">See Example Download Template</a>
                                <div class="alert alert-info remitra_panel-heading" style="margin-bottom: 0; margin-top: .5%" >
                                    <p style="text-align: :center" >
                                        The sample template provided is just a basic example of what your file should look like. Please be sure to include the same level of detail as you have in your live invoice's, by adding additional columns to the file if necessary. Reach out to <?php echo REMITRA_NOREPLY_EMAIL; ?> if you have questions, or require additional assistance                        </p>
                                </div>
							</div>
							</div>
							</div>
					  </div>
					</div>
					<div class="panel panel-default">
					  <div class="panel-heading remitra_panel-heading">
						<h4 class="panel-title">
						  <a data-toggle="collapse" data-parent="#accordion" href="#collapse3">Step 3: Upload your file</a>
						</h4>
					  </div>
					  <div id="collapse3" class="panel-collapse collapse in">
						<div class="panel-body">
						    <div class="messageBox alert alert-success alerttoremove" style="display:none;"></div>



						<div>
							<div class="mappingheader-mainclass" style="float: left;    padding-top: 5px; padding-bottom:4px;padding-right: 10px;">
								<label>Invoice Type</label>
							</div>
							<div class="mappingheader-child" style="padding-top: 4px;padding-bottom:4px;">

								<input type="radio" name="txt_cust_type" id="txt_cust_type_single" value="Single" checked /><label class="mappingheader-label label-align" for="singlecustomerinvoice" style="padding-right:8px;">Single customer invoice</label>

								<input type="radio" name="txt_cust_type" id="txt_cust_type_multi" value="Multiple" /><label for="multiplecustomerinvoice" class="mappingheader-label label-align" >Multiple customer invoices</label>
							</div>

						</div>


       <div class="form-group multiselect-wrapper customer-select" id="row_customer_select">
                                    <label>Select Customer <span style="color:red;font-size: 20px;" title="Mandatory field">*</span> <a style="cursor:pointer"><span  data-html='true' data-placement='right' data-template='<div class="tooltip f-l-tooltip" role="tooltip"><div class="tooltip-arrow"></div><div class="tooltip-inner"></div></div>' class=" glyphicon glyphicon-info-sign" data-toggle="tooltip" title="The 'Select Customer' option is available if all uploaded invoices are to be delivered to a single customer."></span></a></label>
                                     <select id="txt_cust_ext_id" name="txt_cust_ext_id" multiple class="selectpicker" data-live-search="true">
                                     </select>
									 <div id="cust_select_msg" class="form-group" style="color:#FF0000; font-weight:bold "></div>
                                 </div>

<div style="clear:both;"></div>



							<p>Select your .CSV, XLS or XLSX file to import</p>
								<div id="mulitplefileuploader" class="btn btn-success btn-sm"><span class="glyphicon glyphicon-cloud-upload"><font style="font-family:sans-serif;"> Upload File</font></span></div>

						<div style="clear:both;"></div>
						<div>
							<div class="mappingheader-mainclass" style="float: left;    padding-top: 12px; padding-right: 10px;">
								<label>View Mapping</label>
							</div>
							<div class="mappingheader-child" style="padding-top: 10px;">
								<input type="radio" name="editmapping" id="editmappingOn" value="yes" /><label for="editmappingOn" class="mappingheader-label label-align" style="padding-right:8px;"> Yes</label>
								<input type="radio" name="editmapping" id="editmappingOff" value="no" checked /><label class="mappingheader-label label-align" for="editmappingOff"> No</label>
							</div>

						</div>
						<div>
							<div class="mappingheader-mainclass" style="float: left;    padding-top: 10px;padding-right: 10px;">
								<label>Mapping Type</label>
							</div>
							<div class="mappingheader-child">
								<select name="mappingheader" id="mappingheader" class="fieldsList mapping-select form-control" style="width: 10%;float: left;">
								<option value="new">New Mapping </option>
								<?php
								foreach($mappingheaders as $list){
									?>
									<option value="<?php echo $list->ID; ?>" <?php echo ($list->defaultMapping == 'Y')?'selected="selected"':""; ?>><?php echo $list->Name; ?></option>
									<?php
								}
								?>
								</select>
								<button onclick="" style="padding: 8px 10px;margin: 0 10px;border: none;" class="btn btn-sm remitra_primary_button" id="delete_mapping_btn" >
                                                    Delete Mapping </button>
							</div>

						</div>
						<div>
							<div class="mappingheader-mainclass" style="float: left;    padding-top: 10px;padding-right: 10px;">
								<label>Do your invoices require time-sheets/additional documentation?</label>
							</div>
							<div class="mappingheader-child">
								<select name="additionaldocument" id="additionaldocument" class="fieldsList form-control" style="width: 10%;">
									<option value="no">No</option>
									<option value="yes">Yes</option>
								</select>
							</div>

						</div>
					  </div>
					</div>
				  </div>
				</div>




				<div class="col-sm-12">
			                <button id="skipNext" name="skipNext" value="Next" class="wf-Report btn btn-success pull-right btn-sm remitra_primary_button" disabled="disabled" style="display:block;margin-right:-12px;">
			                Next <span class="glyphicon glyphicon-arrow-right"></span> </button>
							<button href="javascript:void(0);" id="skipTo" name="skipTo" value="Next"  class="wf-Report btn btn-success pull-right btn-sm remitra_primary_button" disabled style="display:none;
							margin-right:-12px;">
							Next <span class="glyphicon glyphicon-arrow-right"></span></button>
							<button href="javascript:void(0);" id="uploadNext" name="uploadNext" value="Next" class="wf-Report btn btn-success pull-right btn-sm remitra_primary_button" disabled style="display:none;margin-right:-12px;">
							Next <span class="glyphicon glyphicon-arrow-right"></span></button>
				</div>

			</div>
		</div>

	
		<div class="modal fade" id="myModal" role="dialog">
		    <div class="modal-dialog">
		      <!-- Modal content-->
		      <div class="modal-content">
		        <div class="modal-header">
		          <button type="button" class="close" data-dismiss="modal">&times;</button>
		          <h4 class="model-headtext"> </h4>
		        </div>
		        <div class="modal-body" id="ConfirmMsg" style="padding:40px 50px;"></div>
		       </div>
		    </div>
		</div>
       </div>
      </div>
	  <script>
var coll = document.getElementsByClassName("collapsible");
var i;

f
}
</script>
		<?php
		include(SITE_ROOT.'inc/footer.php');
		?>
