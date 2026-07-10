<?php

declare(strict_types=1);

namespace Box\Mod\Orderanalytics;

use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getModulePermissions(): array
    {
        return [
            'view' => [
                'type' => 'bool',
                'display_name' => __trans('View order analytics'),
                'description' => __trans('Allows the staff member to view order analytics dashboard and charts.'),
            ],
        ];
    }

    private function parseDateRange(array $data): array
    {
        $startDateStr = $data['start_date'] ?? date('Y-m-01');
        $endDateStr = $data['end_date'] ?? date('Y-m-d');
        
        $preset = $data['preset'] ?? 'custom';
        
        $start = strtotime($startDateStr);
        $end = strtotime($endDateStr);
        if (!$start || !$end) {
            $start = strtotime('-30 days');
            $end = time();
        }
        if ($start > $end) {
            $tmp = $start;
            $start = $end;
            $end = $tmp;
        }

        $startDate = date('Y-m-d 00:00:00', $start);
        $endDate = date('Y-m-d 23:59:59', $end);
        
        // Calculate previous period
        if ($preset === 'this_month') {
            $prevStart = strtotime(date('Y-m-d', $start) . ' -1 month');
            $prevEnd = strtotime(date('Y-m-d', $end) . ' -1 month');
        } elseif ($preset === 'last_month') {
            $prevStart = strtotime(date('Y-m-d', $start) . ' -1 month');
            $prevEnd = strtotime(date('Y-m-t', $prevStart)); // Last day of that month
        } elseif ($preset === 'this_year' || $preset === 'year_to_date') {
            $prevStart = strtotime(date('Y-m-d', $start) . ' -1 year');
            $prevEnd = strtotime(date('Y-m-d', $end) . ' -1 year');
        } else {
            $diffDays = round(($end - $start) / 86400) + 1;
            $prevStart = strtotime("-{$diffDays} days", $start);
            $prevEnd = strtotime("-1 day", $start);
        }
        
        $prevStartDate = date('Y-m-d 00:00:00', $prevStart);
        $prevEndDate = date('Y-m-d 23:59:59', $prevEnd);
        
        return [
            'start' => $startDate,
            'end' => $endDate,
            'prev_start' => $prevStartDate,
            'prev_end' => $prevEndDate
        ];
    }

    private function getGrowth(float $current, float $previous): float
    {
        if ($previous > 0) {
            return round((($current - $previous) / $previous) * 100, 1);
        }
        return $current > 0 ? 100.0 : 0.0;
    }

    private function getStatusFilter(array $data, string $alias = ''): string
    {
        if (isset($data['exclude_pending']) && ($data['exclude_pending'] === true || $data['exclude_pending'] === 'true' || $data['exclude_pending'] === 1 || $data['exclude_pending'] === '1')) {
            $prefix = $alias ? $alias . '.' : 'client_order.';
            return " AND {$prefix}status != 'pending_setup' ";
        }
        return "";
    }

    private function getHasInvoiceFilter(array $data, string $alias = ''): string
    {
        if (isset($data['has_invoice']) && ($data['has_invoice'] === true || $data['has_invoice'] === 'true' || $data['has_invoice'] === 1 || $data['has_invoice'] === '1')) {
            $prefix = $alias ? $alias . '.' : 'client_order.';
            return " AND EXISTS (SELECT 1 FROM invoice_item ii WHERE ii.rel_id = {$prefix}id AND ii.type = 'order') ";
        }
        return "";
    }

    private function getCategoryFilter(array $data, string $alias = ''): string
    {
        if (!empty($data['category_id']) && $data['category_id'] !== 'all') {
            $catId = (int) $data['category_id'];
            $prefix = $alias ? $alias . '.' : 'client_order.';
            return " AND {$prefix}product_id IN (SELECT id FROM product WHERE product_category_id = {$catId}) ";
        }
        return "";
    }

    private function getInvoiceCategoryFilter(array $data, string $alias = '', string $invoiceColumn = 'id'): string
    {
        if (!empty($data['category_id']) && $data['category_id'] !== 'all') {
            $catId = (int) $data['category_id'];
            $prefix = $alias ? $alias . '.' : '';
            return " AND {$prefix}{$invoiceColumn} IN (
                SELECT ii.invoice_id 
                FROM invoice_item ii 
                JOIN client_order co ON co.id = ii.rel_id 
                WHERE ii.type = 'order' AND co.product_id IN (
                    SELECT id FROM product WHERE product_category_id = {$catId}
                )
            ) ";
        }
        return "";
    }

    public function getOrderSummary(array $data = []): array
    {
        $dbal = $this->di['dbal'];
        $dates = $this->parseDateRange($data);
        
        $summary = [];
        $statusFilter = $this->getStatusFilter($data);
        $categoryFilter = $this->getCategoryFilter($data);
        $hasInvoiceFilter = $this->getHasInvoiceFilter($data);

        // Current period
        $sql = "SELECT COUNT(1) FROM client_order WHERE created_at BETWEEN :start AND :end" . $statusFilter . $categoryFilter . $hasInvoiceFilter;
        $summary['total'] = (int) $dbal->executeQuery($sql, ['start' => $dates['start'], 'end' => $dates['end']])->fetchOne();
        
        $sqlActive = "SELECT COUNT(1) FROM client_order WHERE status = 'active' AND created_at BETWEEN :start AND :end" . $categoryFilter . $hasInvoiceFilter;
        $summary['active'] = (int) $dbal->executeQuery($sqlActive, ['start' => $dates['start'], 'end' => $dates['end']])->fetchOne();
        
        $sqlPending = "SELECT COUNT(1) FROM client_order WHERE status = 'pending_setup' AND created_at BETWEEN :start AND :end" . $categoryFilter . $hasInvoiceFilter;
        $summary['pending_setup'] = (int) $dbal->executeQuery($sqlPending, ['start' => $dates['start'], 'end' => $dates['end']])->fetchOne();

        $sqlCanceled = "SELECT COUNT(1) FROM client_order WHERE status = 'canceled' AND created_at BETWEEN :start AND :end" . $categoryFilter . $hasInvoiceFilter;
        $summary['canceled'] = (int) $dbal->executeQuery($sqlCanceled, ['start' => $dates['start'], 'end' => $dates['end']])->fetchOne();
        
        // Previous period
        $prevTotal = (int) $dbal->executeQuery($sql, ['start' => $dates['prev_start'], 'end' => $dates['prev_end']])->fetchOne();
        $prevActive = (int) $dbal->executeQuery($sqlActive, ['start' => $dates['prev_start'], 'end' => $dates['prev_end']])->fetchOne();
        
        $summary['total_growth'] = $this->getGrowth($summary['total'], $prevTotal);
        $summary['active_growth'] = $this->getGrowth($summary['active'], $prevActive);

        // Today orders (Fixed bounds)
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $summary['today'] = (int) $dbal->executeQuery($sql, ['start' => $todayStart, 'end' => $todayEnd])->fetchOne();

        return $summary;
    }

    public function getRevenueStats(array $data = []): array
    {
        $dbal = $this->di['dbal'];
        $dates = $this->parseDateRange($data);
        
        $stats = [];
        $invoiceCatFilter = $this->getInvoiceCategoryFilter($data);

        // Revenue current
        $sqlRev = "SELECT COALESCE(SUM(base_income), 0) FROM invoice WHERE status = 'paid' AND paid_at BETWEEN :start AND :end" . $invoiceCatFilter;
        $stats['total'] = round((float) $dbal->executeQuery($sqlRev, ['start' => $dates['start'], 'end' => $dates['end']])->fetchOne(), 2);
        
        // Refunds current
        $sqlRef = "SELECT COALESCE(SUM(base_refund), 0) FROM invoice WHERE base_refund > 0 AND created_at BETWEEN :start AND :end" . $invoiceCatFilter;
        $stats['refunds'] = round((float) $dbal->executeQuery($sqlRef, ['start' => $dates['start'], 'end' => $dates['end']])->fetchOne(), 2);

        // Previous period
        $prevRev = round((float) $dbal->executeQuery($sqlRev, ['start' => $dates['prev_start'], 'end' => $dates['prev_end']])->fetchOne(), 2);
        $prevRef = round((float) $dbal->executeQuery($sqlRef, ['start' => $dates['prev_start'], 'end' => $dates['prev_end']])->fetchOne(), 2);
        
        $stats['growth'] = $this->getGrowth($stats['total'], $prevRev);
        $stats['refunds_growth'] = $this->getGrowth($stats['refunds'], $prevRef);
        
        // AOV current
        $statusFilter = $this->getStatusFilter($data);
        $categoryFilter = $this->getCategoryFilter($data);
        $sqlOrders = "SELECT COUNT(1) FROM client_order WHERE created_at BETWEEN :start AND :end" . $statusFilter . $categoryFilter;
        $totalOrders = (int) $dbal->executeQuery($sqlOrders, ['start' => $dates['start'], 'end' => $dates['end']])->fetchOne();
        $stats['aov'] = $totalOrders > 0 ? round($stats['total'] / $totalOrders, 2) : 0;
        
        // AOV previous
        $prevOrders = (int) $dbal->executeQuery($sqlOrders, ['start' => $dates['prev_start'], 'end' => $dates['prev_end']])->fetchOne();
        $prevAov = $prevOrders > 0 ? round($prevRev / $prevOrders, 2) : 0;
        
        $stats['aov_growth'] = $this->getGrowth($stats['aov'], $prevAov);
        
        // Today vs Yesterday (Fixed bounds for today widgets)
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $yestStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $yestEnd = date('Y-m-d 23:59:59', strtotime('-1 day'));
        
        $stats['today'] = round((float) $dbal->executeQuery($sqlRev, ['start' => $todayStart, 'end' => $todayEnd])->fetchOne(), 2);
        $stats['yesterday'] = round((float) $dbal->executeQuery($sqlRev, ['start' => $yestStart, 'end' => $yestEnd])->fetchOne(), 2);

        return $stats;
    }

    public function getOrdersByPeriod(array $data = []): array
    {
        $dbal = $this->di['dbal'];
        $dates = $this->parseDateRange($data);

        $startTs = strtotime($dates['start']);
        $endTs = strtotime($dates['end']);
        $prevStartTs = strtotime($dates['prev_start']);
        
        $currentDays = [];
        $previousDays = [];
        $labels = [];
        
        $diffDays = round(($endTs - $startTs) / 86400);
        $groupByMonth = $diffDays > 90;
        
        if ($groupByMonth) {
            $diffMonths = (date('Y', $endTs) - date('Y', $startTs)) * 12 + (date('m', $endTs) - date('m', $startTs));
            for ($i = 0; $i <= $diffMonths; $i++) {
                // Use first day of the month for reliable "+$i months" calculation
                $startMonth = date('Y-m-01', $startTs);
                $prevStartMonth = date('Y-m-01', $prevStartTs);
                
                $currDate = date('Y-m', strtotime("+$i months", strtotime($startMonth)));
                $prevDate = date('Y-m', strtotime("+$i months", strtotime($prevStartMonth)));
                
                $currentDays[$currDate] = 0;
                $previousDays[$prevDate] = 0;
                $labels[] = date('M Y', strtotime($currDate . '-01'));
            }
            $sqlDateFormat = "'%Y-%m'";
        } else {
            for ($i = 0; $i <= $diffDays; $i++) {
                $currDate = date('Y-m-d', strtotime("+$i days", $startTs));
                $prevDate = date('Y-m-d', strtotime("+$i days", $prevStartTs));
                
                $currentDays[$currDate] = 0;
                $previousDays[$prevDate] = 0;
                $labels[] = date('d M', strtotime($currDate));
            }
            $sqlDateFormat = "'%Y-%m-%d'";
        }

        $statusFilter = $this->getStatusFilter($data);
        $categoryFilter = $this->getCategoryFilter($data);
        $hasInvoiceFilter = $this->getHasInvoiceFilter($data);

        $sqlCurr = "SELECT DATE_FORMAT(created_at, {$sqlDateFormat}) as period_key, COUNT(1) as count
                    FROM client_order WHERE created_at BETWEEN :start AND :end" . $statusFilter . $categoryFilter . $hasInvoiceFilter . " GROUP BY period_key";
        $rowsCurr = $dbal->executeQuery($sqlCurr, ['start' => $dates['start'], 'end' => $dates['end']])->fetchAllAssociative();
        foreach ($rowsCurr as $row) {
            if (isset($currentDays[$row['period_key']])) {
                $currentDays[$row['period_key']] = (int) $row['count'];
            }
        }

        $sqlPrev = "SELECT DATE_FORMAT(created_at, {$sqlDateFormat}) as period_key, COUNT(1) as count
                    FROM client_order WHERE created_at BETWEEN :start AND :end" . $statusFilter . $categoryFilter . $hasInvoiceFilter . " GROUP BY period_key";
        $rowsPrev = $dbal->executeQuery($sqlPrev, ['start' => $dates['prev_start'], 'end' => $dates['prev_end']])->fetchAllAssociative();
        foreach ($rowsPrev as $row) {
            if (isset($previousDays[$row['period_key']])) {
                $previousDays[$row['period_key']] = (int) $row['count'];
            }
        }

        return ['labels' => $labels, 'data' => array_values($currentDays), 'prev_data' => array_values($previousDays)];
    }

    public function getRevenueByPeriod(array $data = []): array
    {
        $dbal = $this->di['dbal'];
        $dates = $this->parseDateRange($data);

        $startTs = strtotime($dates['start']);
        $endTs = strtotime($dates['end']);
        $prevStartTs = strtotime($dates['prev_start']);
        
        $currentDays = [];
        $previousDays = [];
        $labels = [];
        
        $diffDays = round(($endTs - $startTs) / 86400);
        $groupByMonth = $diffDays > 90;
        
        if ($groupByMonth) {
            $diffMonths = (date('Y', $endTs) - date('Y', $startTs)) * 12 + (date('m', $endTs) - date('m', $startTs));
            for ($i = 0; $i <= $diffMonths; $i++) {
                $startMonth = date('Y-m-01', $startTs);
                $prevStartMonth = date('Y-m-01', $prevStartTs);
                
                $currDate = date('Y-m', strtotime("+$i months", strtotime($startMonth)));
                $prevDate = date('Y-m', strtotime("+$i months", strtotime($prevStartMonth)));
                
                $currentDays[$currDate] = 0;
                $previousDays[$prevDate] = 0;
                $labels[] = date('M Y', strtotime($currDate . '-01'));
            }
            $sqlDateFormat = "'%Y-%m'";
        } else {
            for ($i = 0; $i <= $diffDays; $i++) {
                $currDate = date('Y-m-d', strtotime("+$i days", $startTs));
                $prevDate = date('Y-m-d', strtotime("+$i days", $prevStartTs));
                
                $currentDays[$currDate] = 0;
                $previousDays[$prevDate] = 0;
                $labels[] = date('d M', strtotime($currDate));
            }
            $sqlDateFormat = "'%Y-%m-%d'";
        }

        $invoiceCatFilter = $this->getInvoiceCategoryFilter($data);

        $sqlCurr = "SELECT DATE_FORMAT(paid_at, {$sqlDateFormat}) as period_key, SUM(base_income) as amount
                    FROM invoice WHERE status = 'paid' AND paid_at BETWEEN :start AND :end" . $invoiceCatFilter . " GROUP BY period_key";
        $rowsCurr = $dbal->executeQuery($sqlCurr, ['start' => $dates['start'], 'end' => $dates['end']])->fetchAllAssociative();
        foreach ($rowsCurr as $row) {
            if (isset($currentDays[$row['period_key']])) {
                $currentDays[$row['period_key']] = round((float) $row['amount'], 2);
            }
        }

        $sqlPrev = "SELECT DATE_FORMAT(paid_at, {$sqlDateFormat}) as period_key, SUM(base_income) as amount
                    FROM invoice WHERE status = 'paid' AND paid_at BETWEEN :start AND :end" . $invoiceCatFilter . " GROUP BY period_key";
        $rowsPrev = $dbal->executeQuery($sqlPrev, ['start' => $dates['prev_start'], 'end' => $dates['prev_end']])->fetchAllAssociative();
        foreach ($rowsPrev as $row) {
            if (isset($previousDays[$row['period_key']])) {
                $previousDays[$row['period_key']] = round((float) $row['amount'], 2);
            }
        }

        return ['labels' => $labels, 'data' => array_values($currentDays), 'prev_data' => array_values($previousDays)];
    }

    public function getTopProducts(array $data = []): array
    {
        $dbal = $this->di['dbal'];
        $dates = $this->parseDateRange($data);
        $limit = isset($data['limit']) ? (int) $data['limit'] : 10;
        $statusFilter = $this->getStatusFilter($data, 'co');
        $categoryFilter = $this->getCategoryFilter($data, 'co');
        $hasInvoiceFilter = $this->getHasInvoiceFilter($data, 'co');

        $sql = "SELECT co.title as product_title,
                       pc.title as category_title,
                       COUNT(co.id) as order_count,
                       COALESCE(SUM(co.price), 0) as total_revenue
                FROM client_order co
                LEFT JOIN product p ON co.product_id = p.id
                LEFT JOIN product_category pc ON p.product_category_id = pc.id
                WHERE (co.group_master = 1 OR co.group_id IS NULL) AND co.created_at BETWEEN :start AND :end" . $statusFilter . $categoryFilter . $hasInvoiceFilter . "
                GROUP BY co.title, pc.title
                ORDER BY total_revenue DESC
                LIMIT " . $limit;

        $result = $dbal->executeQuery($sql, ['start' => $dates['start'], 'end' => $dates['end']]);
        return $result->fetchAllAssociative();
    }

    public function getTopClients(array $data = []): array
    {
        $dbal = $this->di['dbal'];
        $dates = $this->parseDateRange($data);
        $limit = isset($data['limit']) ? (int) $data['limit'] : 10;
        $statusFilter = $this->getStatusFilter($data, 'co');
        $categoryFilter = $this->getCategoryFilter($data, 'co');
        $hasInvoiceFilter = $this->getHasInvoiceFilter($data, 'co');

        $sql = "SELECT c.id as client_id,
                       CONCAT(c.first_name, ' ', c.last_name) as client_name,
                       c.email as client_email,
                       COUNT(co.id) as order_count,
                       COALESCE(SUM(co.price), 0) as total_spent
                FROM client_order co
                JOIN client c ON co.client_id = c.id
                WHERE co.created_at BETWEEN :start AND :end" . $statusFilter . $categoryFilter . $hasInvoiceFilter . "
                GROUP BY c.id, c.first_name, c.last_name, c.email
                ORDER BY total_spent DESC
                LIMIT " . $limit;

        $result = $dbal->executeQuery($sql, ['start' => $dates['start'], 'end' => $dates['end']]);
        return $result->fetchAllAssociative();
    }

    public function getRecentOrders(array $data = []): array
    {
        $dbal = $this->di['dbal'];
        $dates = $this->parseDateRange($data);
        $limit = isset($data['limit']) ? (int) $data['limit'] : 10;
        $statusFilter = $this->getStatusFilter($data, 'co');
        $categoryFilter = $this->getCategoryFilter($data, 'co');
        $hasInvoiceFilter = $this->getHasInvoiceFilter($data, 'co');

        $sql = "SELECT co.id,
                       co.title,
                       co.status,
                       co.price,
                       co.period,
                       co.created_at,
                       CONCAT(c.first_name, ' ', c.last_name) as client_name,
                       c.email as client_email
                FROM client_order co
                JOIN client c ON co.client_id = c.id
                WHERE co.created_at BETWEEN :start AND :end" . $statusFilter . $categoryFilter . $hasInvoiceFilter . "
                ORDER BY co.created_at DESC
                LIMIT " . $limit;

        $result = $dbal->executeQuery($sql, ['start' => $dates['start'], 'end' => $dates['end']]);
        return $result->fetchAllAssociative();
    }

    public function getProductCategoryDistribution(array $data = []): array
    {
        $dbal = $this->di['dbal'];
        $dates = $this->parseDateRange($data);
        $statusFilter = $this->getStatusFilter($data, 'co');
        $categoryFilter = $this->getCategoryFilter($data, 'co');
        $hasInvoiceFilter = $this->getHasInvoiceFilter($data, 'co');

        // Join client_order with product and product_category
        $sql = "SELECT pc.title as category_title, COUNT(co.id) as count
                FROM client_order co
                JOIN product p ON co.product_id = p.id
                JOIN product_category pc ON p.product_category_id = pc.id
                WHERE co.created_at BETWEEN :start AND :end
                  AND (co.group_master = 1 OR co.group_id IS NULL)" . $statusFilter . $categoryFilter . $hasInvoiceFilter . "
                GROUP BY pc.title
                ORDER BY count DESC";

        $result = $dbal->executeQuery($sql, ['start' => $dates['start'], 'end' => $dates['end']]);
        $rows = $result->fetchAllAssociative();

        $labels = [];
        $chartData = [];
        // Preset beautiful colors for categories
        $colors = [
            '#206bc4', '#4299e1', '#4eb690', '#f76707', '#f59f00', 
            '#ae3ec9', '#d63939', '#6c757d', '#2fb344', '#66ccff'
        ];
        $backgroundColors = [];

        $colorIndex = 0;
        foreach ($rows as $row) {
            $labels[] = $row['category_title'];
            $chartData[] = (int) $row['count'];
            $backgroundColors[] = $colors[$colorIndex % count($colors)];
            $colorIndex++;
        }

        // If no data, maybe return empty
        if (empty($labels)) {
            $labels[] = 'No Categories';
            $chartData[] = 1; // dummy for chart
            $backgroundColors[] = '#e0e5ec';
        }

        return [
            'labels' => $labels,
            'data' => $chartData,
            'colors' => $backgroundColors,
        ];
    }

    public function getPaymentMethods(array $data = []): array
    {
        $dbal = $this->di['dbal'];
        $dates = $this->parseDateRange($data);
        $invoiceCatFilter = $this->getInvoiceCategoryFilter($data, '', 'invoice_id');

        $sql = "SELECT gateway_id, COUNT(1) as count, SUM(amount) as total
                FROM transaction
                WHERE status = 'received' AND created_at BETWEEN :start AND :end" . $invoiceCatFilter . "
                GROUP BY gateway_id
                ORDER BY total DESC";

        $result = $dbal->executeQuery($sql, ['start' => $dates['start'], 'end' => $dates['end']]);
        $rows = $result->fetchAllAssociative();

        $labels = [];
        $chartData = [];

        foreach ($rows as $row) {
            $gatewayId = $row['gateway_id'];
            if (!$gatewayId) {
                $labels[] = 'Balance / Unknown';
            } else {
                $gwSql = "SELECT name FROM pay_gateway WHERE id = :id";
                $gwName = $dbal->executeQuery($gwSql, ['id' => $gatewayId])->fetchOne();
                $labels[] = $gwName ?: 'Gateway ' . $gatewayId;
            }
            $chartData[] = (int) $row['count'];
        }

        return [
            'labels' => $labels,
            'data' => $chartData,
        ];
    }
}
