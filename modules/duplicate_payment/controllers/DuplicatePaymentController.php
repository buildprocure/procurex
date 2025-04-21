<?php
require_once __DIR__ . '/../models/DuplicatePaymentModel.php';

class DuplicatePaymentController {
    private $model;

    public function __construct($db) {
        $this->model = new DuplicatePaymentModel($db);
    }

    public function index() {
        $duplicates = $this->model->getDuplicatePayment();
        $possibleDuplicates = $this->model->getPossibleDuplicatePayment();
        include __DIR__ . '/../view/Duplicate_Payment_List.php';
    }
}
