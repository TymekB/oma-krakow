<?php

declare(strict_types=1);

namespace App\SiteContent\UI\Http\Request;

use App\SiteContent\UI\Http\Exception\MalformedPayload;
use Symfony\Component\HttpFoundation\Request;

final readonly class ContentPayload
{
    public function singleValue(Request $request): string
    {
        $value = $this->decode($request)['value'] ?? null;

        if (!is_string($value)) {
            throw MalformedPayload::valueIsNotAString();
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    public function values(Request $request): array
    {
        $values = $this->decode($request)['values'] ?? null;

        if (!is_array($values)) {
            throw MalformedPayload::notAnObject();
        }

        $parsed = [];

        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                throw MalformedPayload::keyIsNotAString();
            }

            if (!is_string($value)) {
                throw MalformedPayload::valueIsNotAStringFor($key);
            }

            $parsed[$key] = $value;
        }

        return $parsed;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(Request $request): array
    {
        $content = $request->getContent();

        if ('' === $content) {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw MalformedPayload::notAnObject();
        }

        return is_array($decoded) ? $decoded : [];
    }
}
