<?php
namespace App\Modules\Buyer\RFQ;
require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\Core\Auth;
use App\Core\DB;
use App\Modules\Buyer\RFQ;
use Exception;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

class RFQController {

    private RFQModel $model;

    public function __construct() {
        $this->model = new RFQModel();
    }
    public function checkBOQLocked(int $boqId): bool {
        return $this->model->isBOQLocked($boqId);
    }
    /**
     * Handle incoming requests
     */
    public function handle($post) {
        Auth::checkBuyer();

        if ($post['action'] === 'create') {
            $this->createRFQ($post);
        }
    }

    private function createRFQ(array $data): void {

        $boqId = (int)$data['boq_id'];
        if (!$this->model->isBOQLocked($boqId)) {
            throw new Exception("BOQ must be locked");
        }

        $rfqId = $this->model->createRFQ(
            $boqId,
            $data['delivery_location'],
            $data['instructions'],
            $data['required_delivery_date'],
            $data['quote_deadline'],
            $_SESSION['user_id']
        );

        $this->model->copyBOQItemsToRFQ($boqId, $rfqId);
        $this->model->updateStatus('boqs', $boqId, 'RFQ_CREATED'); // Update BOQ status to indicate RFQ has been created

        $this->model->updateStatus('rfqs', $rfqId, 'GROUPING_IN_PROGRESS');
        $this->model->autoCreateGroups($rfqId);
        $this->model->updateStatus('rfqs', $rfqId, 'GROUPED');
        $this->model->autoAssignSuppliers($rfqId);
        $this->model->updateStatus('rfqs', $rfqId, 'SUPPLIER_ASSIGNED');
        header("Location: /Buyer/RFQ/rfq_view.php?rfq_id=" . $rfqId);
        exit;
    }
    public function getRFQsByBuyer(int $buyerId): array {
        return $this->model->getRFQsByBuyer($buyerId);
    }
}