<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * RFC 6570 expansion of {var} and {+var}.
 */
final class UriTemplate
{
    private const UNRESERVED = 'A-Za-z0-9\-._~';
    private const RESERVED = ':\/?#\[\]@!$&\'()*+,;=';

    /** A comma-separated list of variable names. */
    private const NAMES = '[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*(?:,[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*)*';

    /**
     * @param array<string, string> $variables
     */
    public static function expand(string $template, array $variables): string
    {
        return preg_replace_callback('/\{(\+?)([^}]*)\}/', function (array $m) use ($variables): string {
            $values = [];
            foreach (explode(',', $m[2]) as $name) {
                if (isset($variables[$name])) {
                    $values[] = self::encode($variables[$name], $m[1] !== '');
                }
            }
            return implode(',', $values);
        }, $template);
    }

    /**
     * Refuses a portable-typeBy template the prototype can't select by: expressions beyond {var} and {+var}, and
     * fragments, since targets are registry ids.
     */
    public static function assertTypeBy(string $template): void
    {
        if (str_contains($template, '#') || preg_match_all('/\{[^}]*\}/', $template) !== preg_match_all('/\{\+?' . self::NAMES . '\}/', $template)) {
            throw new \InvalidArgumentException("portable-typeBy href $template: only {var} and {+var} without a fragment are supported");
        }
    }

    private static function encode(string $value, bool $allowReserved): string
    {
        $allowed = self::UNRESERVED . ($allowReserved ? self::RESERVED : '');
        return preg_replace_callback(
            '/' . ($allowReserved ? '%[0-9A-Fa-f]{2}|' : '') . '[^' . $allowed . ']/',
            fn (array $m): string => strlen($m[0]) === 3 && $m[0][0] === '%' ? $m[0] : strtoupper(implode('', array_map(fn ($b) => '%' . bin2hex($b), str_split($m[0])))),
            $value,
        );
    }
}
