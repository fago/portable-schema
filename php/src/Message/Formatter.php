<?php

declare(strict_types=1);

namespace PortableSchema\Message;

use PortableSchema\Json;
use PortableSchema\Pointer;
use PortableSchema\Result;
use PortableSchema\Unchecked;
use PortableSchema\ValidationError;

/**
 * Turns errors into message requests, using the catalog and the annotations of the result.
 */
final class Formatter
{
    /** Message id for codes without a catalog entry. */
    public const FALLBACK = 'invalid';

    /** Label of the root value, which has no property name. */
    public const ROOT_LABEL = 'Value';

    public function __construct(private readonly Catalog $catalog = new Catalog([]))
    {
    }

    public static function create(): self
    {
        return new self(Catalog::default());
    }

    public function format(ValidationError $error, Result $result): MessageRequest
    {
        $params = $error->params;
        $location = $error->instanceLocation;
        if (in_array($error->code, ['required', 'dependentRequired'], true)) {
            $params['label'] = $this->label($result, Pointer::append($location, $params['property']));
            if (isset($params['dependency'])) {
                $params['dependencyLabel'] = $this->label($result, Pointer::append($location, $params['dependency']));
            }
        } else {
            $params['label'] = $this->label($result, $location);
        }
        if (isset($params['allowed'])) {
            $params['allowed'] = $result->annotations($location)->allowedTitles ?? $params['allowed'];
        }

        $id = $this->catalog->has($error->code) ? $error->code : self::FALLBACK;
        $custom = $result->annotations($location)->messages[$error->code] ?? $error->message;
        if ($custom !== null) {
            return new MessageRequest($id, $custom, $this->texts($params));
        }
        [$source, $count] = $this->catalog->source($id, $params);
        return new MessageRequest($id, $source, $this->texts($params), $count);
    }

    public function formatUnchecked(Unchecked $unchecked, Result $result): MessageRequest
    {
        $params = ['label' => $this->label($result, $unchecked->instanceLocation)];
        [$source] = $this->catalog->source('unchecked', $params);
        return new MessageRequest('unchecked', $source, $this->texts($params));
    }

    /**
     * The English text of every error, in order.
     *
     * @return list<string>
     */
    public function messages(Result $result, Translator $translator = new StandaloneTranslator()): array
    {
        return array_map(function (ValidationError $error) use ($result, $translator) {
            $request = $this->format($error, $result);
            return $translator->translate($request->id, $request->source, $request->params, $request->count);
        }, $result->errors);
    }

    private function label(Result $result, string $location): string
    {
        $title = $result->annotations($location)->title;
        if ($title !== null) {
            return $title;
        }
        $segments = Pointer::segments($location);
        return $segments === [] ? self::ROOT_LABEL : end($segments);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, string>
     */
    private function texts(array $params): array
    {
        return array_map(self::text(...), $params);
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_int($value), is_float($value) => json_encode($value),
            is_array($value) => implode(', ', array_map(self::text(...), $value)),
            default => implode(', ', array_map(self::text(...), array_values(get_object_vars($value)))),
        };
    }
}
