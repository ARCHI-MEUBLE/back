<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class Orders
{
    public static function validatedConfigurationId(ApiClient $customer, ApiClient $admin, string $name): int
    {
        $created = $customer->post('/backend/api/configurations/save.php', [
            'name' => $name,
            'model_id' => 1,
            'prompt' => 'M1(1600,450,720)EFbV3(,T,)',
            'config_data' => ['dimensions' => ['width' => 1600]],
            'glb_url' => '/models/' . $name . '.glb',
            'dxf_url' => null,
            'price' => 850.75,
            'thumbnail_url' => null,
            'status' => 'en_attente_validation',
        ]);
        if ($created->status !== 201 && $created->status !== 200) {
            throw new RuntimeException('Configuration save failed: ' . $created->body);
        }
        $id = (int) $created->data()['configuration']['id'];
        $validated = $admin->post('/backend/api/admin/update-configuration-status.php', ['id' => $id, 'status' => 'validee']);
        if ($validated->status !== 200) {
            throw new RuntimeException('Configuration validation failed: ' . $validated->body);
        }
        return $id;
    }

    public static function createOrder(ApiClient $customer, ApiClient $admin, string $name): int
    {
        $configurationId = self::validatedConfigurationId($customer, $admin, $name);
        $added = $customer->post('/backend/api/cart/index.php', ['configuration_id' => $configurationId, 'quantity' => 1]);
        if ($added->status !== 201 && $added->status !== 200) {
            throw new RuntimeException('Cart add failed: ' . $added->body);
        }
        $created = $customer->post('/backend/api/orders/create.php', ['shipping_address' => '1 rue des Tests, 59000 Lille, France']);
        if ($created->status !== 201) {
            throw new RuntimeException('Order creation failed: ' . $created->body);
        }
        return (int) $created->data()['order']['id'];
    }
}
