<?php
namespace App\Modules\Buyer\RFQ;

use App\Modules\Buyer\RFQ\RFQModel;
use App\Core\Auth;
use Exception;
class RFQController {
    private $rfqModel;

    public function __construct() {
        $this->rfqModel = new RFQModel();
    }

   public function createRFQ(array $post)
{
    Auth::checkBuyer();

    $boqId = (int)$post['boq_id'];
    $title = trim($post['title']);

    // Ensure BOQ is published
    if (!$this->rfqModel->isBOQPublished($boqId)) {
        throw new Exception("BOQ must be published before creating RFQ");
    }

    $projectId = $this->rfqModel->getProjectIdFromBOQ($boqId);

    $rfqId = $this->rfqModel->createRFQFromBOQ(
        $boqId,
        $projectId,
        $title,
        $_SESSION['username']
    );

    $this->rfqModel->copyBOQItemsToRFQ($boqId, $rfqId);

    return $rfqId;
}

}