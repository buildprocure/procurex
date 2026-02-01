<?php
namespace App\Modules\Buyer\BOQ;
//load autoload 

use App\Core\ExcelParser;
use App\Core\FileUploader;
use App\Core\Auth;
use App\Modules\Buyer\BOQ\BOQModel;
use Exception;
class BOQController {
    private $boqmodel;
    public function __construct() {
        $this->boqmodel = new BOQModel();

    }
    public function uploadBOQ($post, $files) {
        Auth::checkBuyer();  // Ensure only buyers can upload BOQ
        self::handle($post, $files);
    }
    public function handle($post, $files) {
        self::validate($post, $files);
        $uploadedfilpath = (new FileUploader())->uploadBOQ($files['boq_file']);
    
        //excel parsing
        $excelParser = new ExcelParser();
        $rows = $excelParser->parseBOQ($uploadedfilpath);
        if (empty($rows)) {
            throw new Exception("BOQ file is empty or invalid");
        }
    
        
        //getbuyercompanyid
        $buyerCompanyID = $this->boqmodel->getBuyerCompanyID($_SESSION['username']);
        //insest to project table
        $projectID = $this->boqmodel->getOrCreateProject($buyerCompanyID, $post['project_name'], $post['location']??"unknown", $_SESSION['username'], date('Y-m-d H:i:s'));
        
        //insert into BOQ table
        $boqID = $this->boqmodel->insertBOQMaster( $projectID, $uploadedfilpath, $_SESSION['username'], date('Y-m-d H:i:s'));

        //insert into BOQ items table
        $this->boqmodel->insertBOQItems($boqID, $rows);
        
    }

    private static function validate(array $post, array $files): void
    {
        if (empty($post['project_name'])) {
            throw new Exception("Project name is required");
        }

        if (!isset($files['boq_file']) || $files['boq_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("BOQ Excel file is required");
        }

        $ext = pathinfo($files['boq_file']['name'], PATHINFO_EXTENSION);
        if (!in_array($ext, ['xls', 'xlsx'])) {
            throw new Exception("Only Excel files are allowed");
        }
    }
    public function viewBOQItems($boqId) {
        Auth::checkBuyer(); // Ensure user is logged in
        return $this->boqmodel->getBOQItemsByBOQId($boqId);
    }
    public function getBOQListByUser($username) {
        Auth::checkBuyer(); // Ensure user is logged in
        $result = $this->boqmodel->getBOQListByUser($username);
        return $result;
    }

    public function checkownership($boqId, $username) {
        Auth::checkBuyer(); // Ensure user is logged in
        return $this->boqmodel->checkBOQOwnership($boqId, $username);
    }
    public function getBOQItemsbyBOQId($boqId) {
        Auth::checkBuyer(); // Ensure user is logged in
        return $this->boqmodel->getBOQItemsByBOQId($boqId);
    }
    public function getBOQListByID($boqId) {
        Auth::checkBuyer(); // Ensure user is logged in
        return $this->boqmodel->getBOQByID($boqId);
    }

    //post method to save edited boq
    public function saveEditedBOQ($boqId, $items, $username) {
        Auth::checkBuyer(); // Ensure user is logged in
        $newBoqId = $this->boqmodel->saveEditedBOQ($boqId, $items, $username);
        return $newBoqId;
        
    }
    //publish boq
    public function publishBOQ($boqId, $username) {
        Auth::checkBuyer(); // Ensure user is logged in
        if (!$this->checkownership($boqId, $username)) {
            throw new Exception("You do not have permission to publish this BOQ.");
        }
        $this->boqmodel->publishBOQ($boqId, $username);
    }
    //delete boq
    public function deleteBOQ($boqId, $username) {      
        Auth::checkBuyer(); // Ensure user is logged in
        if (!$this->checkownership($boqId, $username)) {
            throw new Exception("You do not have permission to delete this BOQ.");
        }
        $this->boqmodel->deleteBOQ($boqId, $username);
    }
}