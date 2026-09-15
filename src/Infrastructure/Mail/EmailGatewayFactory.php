<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Config\Settings;
use App\Db\Connection;
use App\Domain\Email\ItemDisplayNameResolver;
use App\Domain\Model\ModelRepository;
use App\Domain\Order\OrderRepository;

final class EmailGatewayFactory
{
    public static function create(Settings $settings, Connection $db): EmailGateway
    {
        return new EmailGateway(
            new ResendMailer($settings->mail->resendApiKey),
            new ItemDisplayNameResolver(new ModelRepository($db)),
            new OrderRepository($db),
            $settings->mail,
            $settings->frontendUrl,
        );
    }
}
