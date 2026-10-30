<?php

declare(strict_types=1);

namespace Tests\Contract\Content;

use Tests\Support\ContractTestCase;

final class EmailTemplatesTest extends ContractTestCase
{
    public function testRequiresAdmin(): void
    {
        $this->assertSnapshot('email-templates.unauthorized', $this->client()->get('/backend/api/admin/email-templates.php'));
    }

    public function testListAndUpdate(): void
    {
        $admin = $this->admin();
        $list = $admin->get('/backend/api/admin/email-templates.php');
        $this->assertSnapshot('email-templates.list', $list);
        $templates = $list->data()['templates'];
        self::assertNotEmpty($templates);
        $first = $templates[0];
        $this->assertSnapshot('email-templates.update', $admin->put('/backend/api/admin/email-templates.php', [
            'id' => $first['id'],
            'subject' => 'Sujet modifié',
            'header_text' => 'En-tête',
            'footer_text' => 'Pied',
            'show_logo' => true,
            'show_gallery' => false,
            'gallery_images' => ['biblio.jpg'],
            'custom_css' => '',
        ]));
        $this->assertSnapshot('email-templates.update.invalid', $admin->put('/backend/api/admin/email-templates.php', ['subject' => 'Sans id']));
    }

    public function testTemplateAsset(): void
    {
        $response = $this->client()->get('/backend/api/calendly/assets/logo.png');

        self::assertSame(200, $response->status);
        self::assertStringStartsWith('image/', $response->contentType());
    }
}
