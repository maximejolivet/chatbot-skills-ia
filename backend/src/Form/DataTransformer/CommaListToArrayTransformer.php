<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<array<int, string>, string>
 */
final class CommaListToArrayTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): string
    {
        return is_array($value) ? implode(', ', $value) : '';
    }

    public function reverseTransform(mixed $value): array
    {
        if (null === $value || '' === trim((string) $value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), static fn (string $v) => '' !== $v));
    }
}
