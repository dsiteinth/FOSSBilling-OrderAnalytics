<?php

declare(strict_types=1);

namespace Box\Mod\Orderanalytics\Api;

class Admin extends \FOSSBilling\Api\AbstractApi
{
    public function get_summary(array $data = []): array
    {
        $this->checkPermissions('orderanalytics', 'view');
        return $this->getService()->getOrderSummary($data);
    }

    public function get_revenue_stats(array $data = []): array
    {
        $this->checkPermissions('orderanalytics', 'view');
        return $this->getService()->getRevenueStats($data);
    }

    public function get_orders_chart(array $data = []): array
    {
        $this->checkPermissions('orderanalytics', 'view');
        return $this->getService()->getOrdersByPeriod($data);
    }

    public function get_revenue_chart(array $data = []): array
    {
        $this->checkPermissions('orderanalytics', 'view');
        return $this->getService()->getRevenueByPeriod($data);
    }

    public function get_top_products(array $data = []): array
    {
        $this->checkPermissions('orderanalytics', 'view');
        return $this->getService()->getTopProducts($data);
    }

    public function get_top_clients(array $data = []): array
    {
        $this->checkPermissions('orderanalytics', 'view');
        return $this->getService()->getTopClients($data);
    }

    public function get_recent_orders(array $data = []): array
    {
        $this->checkPermissions('orderanalytics', 'view');
        return $this->getService()->getRecentOrders($data);
    }

    public function get_category_distribution(array $data = []): array
    {
        $this->checkPermissions('orderanalytics', 'view');
        return $this->getService()->getProductCategoryDistribution($data);
    }

    public function get_payment_methods(array $data = []): array
    {
        $this->checkPermissions('orderanalytics', 'view');
        return $this->getService()->getPaymentMethods($data);
    }
}
