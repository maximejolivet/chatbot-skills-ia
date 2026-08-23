<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @implements DataTransformerInterface<array<mixed>, string>
 */
final class JsonToArrayTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): string
    {
        if (null === $value || [] === $value) {
            return '';
        }

        return json_encode($value, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) ?: '';
    }

    public function reverseTransform(mixed $value): array
    {
        if (null === $value || '' === trim((string) $value)) {
            return [];
        }

        $decoded = json_decode((string) $value, true);
        if (\JSON_ERROR_NONE !== json_last_error() || !is_array($decoded)) {
            throw new TransformationFailedException('This value is not valid JSON.');
        }

        return $decoded;
    }
}
