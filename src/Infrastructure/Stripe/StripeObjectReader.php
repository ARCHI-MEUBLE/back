<?php

declare(strict_types=1);

namespace App\Infrastructure\Stripe;

final class StripeObjectReader
{
    public static function get(object $object, string $property): mixed
    {
        if ($object instanceof \ArrayAccess) {
            return $object->offsetExists($property) ? $object->offsetGet($property) : null;
        }
        return get_object_vars($object)[$property] ?? null;
    }

    public static function string(object $object, string $property): ?string
    {
        $value = self::get($object, $property);
        return is_string($value) ? $value : null;
    }

    public static function metadataArray(object $object): array
    {
        $metadata = self::get($object, 'metadata');
        if (is_object($metadata) && method_exists($metadata, 'toArray')) {
            return $metadata->toArray();
        }
        return is_object($metadata) ? (array) $metadata : [];
    }
}
