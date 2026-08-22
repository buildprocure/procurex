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
    /**
     * Proxy to model.  The underlying query now returns every RFQ created
     * by users in the same company as $buyerId (not just RFQs directly
     * created by $buyerId).  This allows buyers to see company-wide
     * RFQs rather than only their own.
     */
    public function getRFQsByBuyer(int $buyerId): array {
        return $this->model->getRFQsByBuyer($buyerId);
    }

    /**
     * Award one or more line-item quantities to suppliers. Delegates to
     * the model and returns po_ids + logs. See RFQModel::awardItems()
     * for the validation this enforces.
     *
     * @param array<int,array{rfq_item_id:int,quote_id:int,quantity:float}> $awards
     */
    public function awardItems(int $rfqId, array $awards, int $userId): array
    {
        Auth::checkBuyer();
        return $this->model->awardItems($rfqId, $awards, $userId);
    }

    /**
     * After any change to a group, make sure the RFQ status is adjusted if
     * every group is finished (decision made or closed with no award).
     *
     * This is a thin wrapper around the corresponding model helper.
     *
     * @deprecated Groups carry no award decision - use evaluateRFQByItems()
     * for new code.
     */
    public function evaluateRFQ(int $rfqId): void
    {
        Auth::checkBuyer();
        $this->model->updateRFQStatusIfAllGroupsDecided($rfqId);
    }

    /**
     * Set aside a single line item for a later decision, without touching
     * any other item on the RFQ.
     */
    public function postponeItem(int $rfqId, int $itemId): void
    {
        Auth::checkBuyer();
        $this->model->postponeItem($rfqId, $itemId);
    }

    /**
     * Close a single line item with no award.
     */
    public function closeItem(int $rfqId, int $itemId): void
    {
        Auth::checkBuyer();
        $this->model->closeItem($rfqId, $itemId);
        $this->evaluateRFQByItems($rfqId);
    }

    /**
     * After any change to a line item, make sure the RFQ status is
     * adjusted if every item is finished (fully awarded or closed with no
     * award). Thin wrapper around the corresponding model helper.
     */
    public function evaluateRFQByItems(int $rfqId): void
    {
        Auth::checkBuyer();
        $this->model->updateRFQStatusIfAllItemsDecided($rfqId);
    }
}