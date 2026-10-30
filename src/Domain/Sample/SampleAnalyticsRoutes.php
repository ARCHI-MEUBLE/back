<?php

declare(strict_types=1);

namespace App\Domain\Sample;

use App\Db\Connection;
use App\Http\Guard\AdminGuard;
use App\Http\Response;
use App\Http\RouteCollection;

final class SampleAnalyticsRoutes
{
    public function __construct(private readonly Connection $db) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('admin/samples/analytics', [new AdminGuard()])->get(null, fn(): Response => Response::json([
            'success' => true,
            'analytics' => [
                'total_ordered' => (int) ($this->db->scalar('SELECT COUNT(*) FROM order_sample_items') ?? 0),
                'orders_with_samples' => (int) ($this->db->scalar('SELECT COUNT(DISTINCT order_id) FROM order_sample_items') ?? 0),
                'samples_in_cart' => (int) ($this->db->scalar('SELECT COUNT(*) FROM cart_sample_items') ?? 0),
                'top_samples' => $this->db->query(
                    'SELECT sample_name, material, COUNT(*) as order_count FROM order_sample_items
                     GROUP BY sample_name, material ORDER BY order_count DESC LIMIT 5',
                ),
                'material_distribution' => $this->db->query(
                    'SELECT material, COUNT(*) as count FROM order_sample_items GROUP BY material ORDER BY count DESC',
                ),
                'recent_orders' => $this->db->query(
                    "SELECT DATE(o.created_at) as date, COUNT(DISTINCT o.id) as order_count
                     FROM orders o JOIN order_sample_items osi ON o.id = osi.order_id
                     WHERE o.created_at >= CURRENT_DATE - INTERVAL '30 days'
                     GROUP BY DATE(o.created_at) ORDER BY date ASC",
                ),
            ],
        ]));
    }
}
