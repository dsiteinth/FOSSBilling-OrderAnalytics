# Order Analytics - FOSSBilling Extension

Advanced order analytics dashboard for FOSSBilling admin panel with interactive charts, revenue tracking, top products, top clients and recent orders overview.

## Features

- **Summary Cards** — Total orders, active orders, revenue this month, total revenue with growth indicator
- **Revenue Chart** — Interactive line chart showing revenue over time (30 days / 12 weeks / 12 months)
- **Orders Chart** — Bar chart showing order volume over time with period switching
- **Order Status Distribution** — Doughnut chart showing order breakdown by status
- **Top Products** — Ranked table of best-selling products by order count and revenue
- **Top Clients** — Ranked table of top clients by total spending
- **Recent Orders** — Latest orders with status badges, client info and quick links

## Screenshots

After installation, navigate to **Orders → Order Analytics** in the admin panel.

## Installation

1. Copy the `Orderanalytics` folder to your FOSSBilling `modules/` directory:
   ```
   your-fossbilling/modules/Orderanalytics/
   ```

2. Log in to your FOSSBilling admin panel

3. Go to **Extensions → Overview**

4. Find "Order Analytics" and click **Activate**

5. Navigate to **Orders → Order Analytics** to view the dashboard

## File Structure

```
Orderanalytics/
├── manifest.json              # Module metadata
├── icon.svg                   # Module icon
├── README.md                  # This file
├── Service.php                # Business logic & database queries
├── Api/
│   └── Admin.php              # Admin API endpoints
├── Controller/
│   └── Admin.php              # Admin routes & navigation
└── templates/
    └── admin/
        └── mod_orderanalytics_index.html.twig   # Dashboard template
```

## Requirements

- FOSSBilling 0.6.1 or later
- PHP 8.1 or later

## Technical Details

- Uses existing `client_order` and `invoice` database tables — no schema changes required
- Charts powered by [Chart.js](https://www.chartjs.org/) via CDN
- Follows FOSSBilling module conventions and Tabler CSS framework
- Supports staff member permissions (`view` permission for order analytics)
- All data loaded via AJAX calls for fast initial page load

## API Endpoints

| Endpoint | Description |
|----------|-------------|
| `admin/orderanalytics/get_summary` | Order counts by status |
| `admin/orderanalytics/get_revenue_stats` | Revenue statistics |
| `admin/orderanalytics/get_revenue_chart` | Revenue chart data (supports `period` param) |
| `admin/orderanalytics/get_orders_chart` | Orders chart data (supports `period` param) |
| `admin/orderanalytics/get_top_products` | Top selling products |
| `admin/orderanalytics/get_top_clients` | Top clients by spending |
| `admin/orderanalytics/get_recent_orders` | Recent orders list |
| `admin/orderanalytics/get_status_distribution` | Order status distribution |

## License

Apache-2.0
