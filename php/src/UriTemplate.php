<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * RFC 6570 expansion, levels 1 and 2: {var}, {+var}, {#var}.
 */
final class UriTemplate
{
    private const UNRESERVED = 'A-Za-z0-9\-._~';
    private const RESERVED = ':\/?#\[\]@!$&\'()*+,;=';

    /**
     * @param array<string, string> $variables
     */
    public static function expand(string $template, array $variables): string
    {
        return preg_replace_callback('/\{([+#]?)([^}]*)\}/', function (array $m) use ($variables): string {
            $values = [];
            foreach (explode(',', $m[2]) as $name) {
                if (isset($variables[$name])) {
                    $values[] = self::encode($variables[$name], $m[1] !== '');
                }
            }
            if ($values === []) {
                return '';
            }
            return ($m[1] === '#' ? '#' : '') . implode(',', $values);
        }, $template);
    }

    /**
     * Variable names in the order they appear.
     *
     * @return list<string>
     */
    public static function variables(string $template): array
    {
        preg_match_all('/\{[+#]?([^}]*)\}/', $template, $m);
        $names = [];
        foreach ($m[1] as $list) {
            foreach (explode(',', $list) as $name) {
                $names[] = $name;
            }
        }
        return $names;
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
