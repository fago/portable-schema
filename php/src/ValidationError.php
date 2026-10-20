<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * One problem, in the result format of spec/errors.md.
 */
final class ValidationError implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public readonly string $instanceLocation,
        public readonly string $keywordLocation,
        public readonly string $absoluteKeywordLocation,
        public readonly string $code,
        public readonly array $params = [],
        public readonly ?string $message = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = [
            'instanceLocation' => $this->instanceLocation,
            'keywordLocation' => $this->keywordLocation,
            'absoluteKeywordLocation' => $this->absoluteKeywordLocation,
            'code' => $this->code,
            'params' => $this->params,
        ];
        if ($this->message !== null) {
            $array['message'] = $this->message;
        }
        return $array;
    }

    public function jsonSerialize(): mixed
    {
        return array_replace($this->toArray(), ['params' => (object) $this->params]);
    }
}
