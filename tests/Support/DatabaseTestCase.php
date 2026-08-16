<?php
declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;
use mysqli;

/**
 * Base class for tests that hit the database.
 *
 * Each test starts from empty tables, so tests cannot leak state into
 * one another. Truncation (rather than a wrapping transaction) is
 * deliberate: awardSupplier() runs its own begin_transaction/commit, and
 * MySQL does not support nested transactions ??? an outer transaction would
 * be silently committed by the inner one and the isolation would be a
 * fiction.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected mysqli $db;

    /** Tables cleared before each test, children first. */
    private const TABLES = [
        'purchase_order_items',
        'purchase_orders',
        'rfq_group_quote_items',
        'rfq_group_quotes',
        'rfq_group_suppliers',
        'rfq_items',
        'rfq_item_groups',
        'rfqs',
        'boq_items',
        'boqs',
        'supplier_item_groups',
        'projects',
        'item_groups',
        'companies',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = test_db();

        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLES as $table) {
            $this->db->query("TRUNCATE TABLE `{$table}`");
        }
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    // ---------------------------------------------------------------
    // Fixture builders
    // ---------------------------------------------------------------

    protected function makeCompany(string $name, string $type = 'Supplier'): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO companies (name, type, address) VALUES (?, ?, ?)"
        );
        $address = '1 Test Street';
        $stmt->bind_param('sss', $name, $type, $address);
        $stmt->execute();

        return (int) $this->db->insert_id;
    }

    protected function makeProject(int $buyerCompanyId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO projects (buyer_company_id, project_name, location, status, created_by)
             VALUES (?, ?, ?, 'Active', ?)"
        );
        $name = 'Test Project';
        $loc  = 'Test Location';
        $by   = 'tests';
        $stmt->bind_param('isss', $buyerCompanyId, $name, $loc, $by);
        $stmt->execute();

        return (int) $this->db->insert_id;
    }

    protected function makeBoq(int $projectId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO boqs (project_id, version_no, uploaded_file, status, created_by)
             VALUES (?, 1, ?, 'LOCKED', ?)"
        );
        $file = 'test_boq.xlsx';
        $by   = 'tests';
        $stmt->bind_param('iss', $projectId, $file, $by);
        $stmt->execute();

        return (int) $this->db->insert_id;
    }

    protected function makeRfq(int $projectId, int $boqId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO rfqs
                (project_id, boq_id, delivery_location, required_delivery_date,
                 quote_deadline, status, created_user_id, rfq_title)
             VALUES (?, ?, ?, ?, ?, 'SUPPLIER_ASSIGNED', ?, ?)"
        );
        $loc      = 'Site A';
        $required = date('Y-m-d H:i:s', strtotime('+30 days'));
        $deadline = date('Y-m-d', strtotime('+7 days'));
        $userId   = 1;
        $title    = 'Test RFQ';
        $stmt->bind_param('iisssis', $projectId, $boqId, $loc,
            $required, $deadline, $userId, $title);
        $stmt->execute();

        return (int) $this->db->insert_id;
    }

    protected function makeItemGroup(string $name = 'Civil'): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO item_groups (group_name) VALUES (?)"
        );
        $stmt->bind_param('s', $name);
        $stmt->execute();

        return (int) $this->db->insert_id;
    }

    protected function makeRfqItemGroup(int $rfqId, int $itemGroupId, string $status = 'SENT'): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO rfq_item_groups (rfq_id, item_group_id, status)
             VALUES (?, ?, ?)"
        );
        $stmt->bind_param('iis', $rfqId, $itemGroupId, $status);
        $stmt->execute();

        return (int) $this->db->insert_id;
    }

    protected function makeRfqItem(
        int $rfqId,
        int $groupId,
        string $material = 'Portland Cement',
        float $qty = 100.0,
        string $unit = 'Bag'
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO rfq_items
                (rfq_id, boq_item_id, rfq_item_group_id, material_name,
                 specification, unit, quantity)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $spec      = 'OPC 43 Grade';
        $boqItemId = 0;
        $stmt->bind_param('iiisssd', $rfqId, $boqItemId, $groupId,
            $material, $spec, $unit, $qty);
        $stmt->execute();

        return (int) $this->db->insert_id;
    }

    /**
     * A submitted quote plus one priced line per item.
     *
     * @param array<int,float> $itemPrices rfq_item_id => unit_price
     */
    protected function makeQuote(int $groupId, int $supplierId, array $itemPrices): int
    {
        $total = 0.0;

        $stmt = $this->db->prepare(
            "INSERT INTO rfq_group_quotes
                (rfq_item_group_id, supplier_company_id, total_amount, status, submitted_at)
             VALUES (?, ?, 0, 'SUBMITTED', NOW())"
        );
        $stmt->bind_param('ii', $groupId, $supplierId);
        $stmt->execute();
        $quoteId = (int) $this->db->insert_id;

        $insertItem = $this->db->prepare(
            "INSERT INTO rfq_group_quote_items
                (rfq_group_quote_id, rfq_item_id, unit_price, total_price)
             VALUES (?, ?, ?, ?)"
        );

        foreach ($itemPrices as $itemId => $unitPrice) {
            $qty = $this->quantityOf((int) $itemId);
            $lineTotal = $qty * $unitPrice;
            $total += $lineTotal;

            $insertItem->bind_param('iidd', $quoteId, $itemId, $unitPrice, $lineTotal);
            $insertItem->execute();
        }

        $upd = $this->db->prepare(
            "UPDATE rfq_group_quotes SET total_amount = ? WHERE id = ?"
        );
        $upd->bind_param('di', $total, $quoteId);
        $upd->execute();

        return $quoteId;
    }

    protected function quantityOf(int $rfqItemId): float
    {
        $stmt = $this->db->prepare("SELECT quantity FROM rfq_items WHERE id = ?");
        $stmt->bind_param('i', $rfqItemId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        return $row ? (float) $row['quantity'] : 0.0;
    }

    // ---------------------------------------------------------------
    // Assertion helpers
    // ---------------------------------------------------------------

    /** @return array<string,mixed>|null */
    protected function fetchRow(string $sql, array $params = [], string $types = ''): ?array
    {
        $stmt = $this->db->prepare($sql);

        if ($params) {
            $stmt->bind_param($types ?: str_repeat('i', count($params)), ...$params);
        }

        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    protected function countRows(string $table, string $where = '1', array $params = []): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS c FROM `{$table}` WHERE {$where}");

        if ($params) {
            $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        }

        $stmt->execute();

        return (int) $stmt->get_result()->fetch_assoc()['c'];
    }

    protected function decisionStatusOf(int $quoteId): ?string
    {
        $row = $this->fetchRow(
            "SELECT decision_status FROM rfq_group_quotes WHERE id = ?",
            [$quoteId]
        );

        return $row['decision_status'] ?? null;
    }
}
