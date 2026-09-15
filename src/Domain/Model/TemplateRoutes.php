<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Db\Connection;
use App\Domain\Shared\NotFoundException;
use App\Http\ErrorStyle;
use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class TemplateRoutes
{
    public function __construct(private readonly Connection $db) {}

    public function register(RouteCollection $routes): void
    {
        $routes->script('templates', errorStyle: ErrorStyle::Success)->get(null, function (Request $r): Response {
            if ($r->queryString('id') !== null) {
                $template = $this->db->queryOne('SELECT * FROM templates WHERE id = ?', [(int) $r->queryString('id')]);
                if ($template === null) {
                    throw new NotFoundException('Template non trouvé');
                }
                return Response::json(['success' => true, 'data' => $template]);
            }
            $templates = $this->db->query('SELECT * FROM templates ORDER BY created_at DESC');
            return Response::json(['success' => true, 'count' => count($templates), 'data' => $templates]);
        });
    }
}
