<?php

declare(strict_types=1);

namespace PortableSchema;

/**
 * The pinned format checks of spec/profile.md.
 */
final class Formats
{
    private const URI = <<<'RE'
        (?:[A-Za-z][A-Za-z0-9+.\-]*:(?://(?:(?:[A-Za-z0-9\-._~!$&'()*+,;=:]|%[0-9A-Fa-f]{2})*@)?(?:\[(?:[0-9A-Fa-f:.]+|v[0-9A-Fa-f]+\.[A-Za-z0-9\-._~!$&'()*+,;=:]+)\]|(?:[A-Za-z0-9\-._~!$&'()*+,;=]|%[0-9A-Fa-f]{2})*)(?::[0-9]*)?(?:/(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*|/(?:(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})+(?:/(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*)?|(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})+(?:/(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*|)(?:\?(?:[A-Za-z0-9\-._~!$&'()*+,;=:@/?]|%[0-9A-Fa-f]{2})*)?(?:#(?:[A-Za-z0-9\-._~!$&'()*+,;=:@/?]|%[0-9A-Fa-f]{2})*)?)
        RE;

    private const RELATIVE_REF = <<<'RE'
        (?:(?://(?:(?:[A-Za-z0-9\-._~!$&'()*+,;=:]|%[0-9A-Fa-f]{2})*@)?(?:\[(?:[0-9A-Fa-f:.]+|v[0-9A-Fa-f]+\.[A-Za-z0-9\-._~!$&'()*+,;=:]+)\]|(?:[A-Za-z0-9\-._~!$&'()*+,;=]|%[0-9A-Fa-f]{2})*)(?::[0-9]*)?(?:/(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*|/(?:(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})+(?:/(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*)?|(?:[A-Za-z0-9\-._~!$&'()*+,;=@]|%[0-9A-Fa-f]{2})+(?:/(?:[A-Za-z0-9\-._~!$&'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*|)(?:\?(?:[A-Za-z0-9\-._~!$&'()*+,;=:@/?]|%[0-9A-Fa-f]{2})*)?(?:#(?:[A-Za-z0-9\-._~!$&'()*+,;=:@/?]|%[0-9A-Fa-f]{2})*)?)
        RE;

    private const PATTERNS = [
        'duration' => '^P(?:(?:[0-9]+D|[0-9]+M(?:[0-9]+D)?|[0-9]+Y(?:[0-9]+M(?:[0-9]+D)?)?)(?:T(?:[0-9]+H(?:[0-9]+M(?:[0-9]+S)?)?|[0-9]+M(?:[0-9]+S)?|[0-9]+S))?|T(?:[0-9]+H(?:[0-9]+M(?:[0-9]+S)?)?|[0-9]+M(?:[0-9]+S)?|[0-9]+S)|[0-9]+W)$',
        'ipv4' => '^(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])(?:\.(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])){3}$',
        'uuid' => '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$',
        'json-pointer' => '^(?:/(?:[^~/]|~[01])*)*$',
        'relative-json-pointer' => '^(?:0|[1-9][0-9]*)(?:#|(?:/(?:[^~/]|~[01])*)*)$',
        'decimal' => '^[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)$',
        'uri-template' => '^(?:[^\x00-\x20"\'<>\\\\^`{|}\x7f]|%[0-9A-Fa-f]{2}|\{[+#./;?&=,!@|]?(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2})(?:\.?(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2}))*(?::[1-9][0-9]{0,3}|\*)?(?:,(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2})(?:\.?(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2}))*(?::[1-9][0-9]{0,3}|\*)?)*\})*$',
    ];

    /**
     * The formats allowed by the profile.
     */
    public const NAMES = [
        'date', 'time', 'date-time', 'duration', 'email', 'hostname', 'ipv4', 'ipv6', 'uri', 'uri-reference',
        'uri-template', 'uuid', 'json-pointer', 'relative-json-pointer', 'regex', 'decimal', 'int32', 'html', 'commonmark',
    ];

    /**
     * Whether $value passes the format. Values of other types pass.
     */
    public static function check(string $format, mixed $value): bool
    {
        if ($format === 'int32') {
            return !(is_int($value) || is_float($value)) || Json::typeOf($value) !== 'integer' || ($value >= -2147483648 && $value <= 2147483647);
        }
        if (!is_string($value)) {
            return true;
        }
        return match ($format) {
            'date' => self::date($value),
            'time' => self::time($value),
            'date-time' => (bool) preg_match('/^([^Tt]+)[Tt](.+)$/D', $value, $m) && self::date($m[1]) && self::time($m[2]),
            'email' => self::email($value),
            'hostname' => self::hostname($value),
            'ipv6' => self::ipv6($value),
            'uri' => self::match('^' . self::URI . '$', $value),
            'uri-reference' => self::match('^(?:' . self::URI . '|' . self::RELATIVE_REF . ')$', $value),
            'regex' => @preg_match('/' . str_replace('/', '\/', $value) . '/u', '') !== false,
            'html', 'commonmark' => true,
            default => isset(self::PATTERNS[$format]) ? self::match(self::PATTERNS[$format], $value) : throw new \InvalidArgumentException("Unknown format $format"),
        };
    }

    private static function match(string $pattern, string $value): bool
    {
        return (bool) preg_match('~' . str_replace('~', '\~', $pattern) . '~uD', $value);
    }

    private static function date(string $value): bool
    {
        if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $m)) {
            return false;
        }
        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($mo < 1 || $mo > 12 || $d < 1) {
            return false;
        }
        $leap = ($y % 4 === 0 && $y % 100 !== 0) || $y % 400 === 0;
        $days = [31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        return $d <= $days[$mo - 1];
    }

    private static function time(string $value): bool
    {
        if (!preg_match('/^([0-9]{2}):([0-9]{2}):([0-9]{2})(\.[0-9]+)?([Zz]|[+\-]([0-9]{2}):([0-9]{2}))$/D', $value, $m)) {
            return false;
        }
        if ((int) $m[1] > 23 || (int) $m[2] > 59 || (int) $m[3] > 59) {
            return false;
        }
        return !isset($m[6]) || ((int) $m[6] <= 23 && (int) $m[7] <= 59);
    }

    private const ATEXT = '[A-Za-z0-9!#$%&\'*+\/=?^_`{|}~\-]';

    /**
     * RFC 5321 Snum: 1-3 digits, 0-255, leading zeros allowed.
     */
    private const SNUM = '(?:[01]?[0-9]{1,2}|2[0-4][0-9]|25[0-5])';

    /**
     * RFC 5321 Mailbox, ASCII: a local part of at most 64 characters, `@`, a hostname or an address literal.
     */
    private static function email(string $value): bool
    {
        $at = strrpos($value, '@');
        if ($at === false || strlen($value) > 254) {
            return false;
        }
        $local = substr($value, 0, $at);
        $domain = substr($value, $at + 1);
        // RFC 5321 Dot-string or Quoted-string.
        $localPart = '/^(?:' . self::ATEXT . '+(?:\.' . self::ATEXT . '+)*|"(?:[ !#-\[\]-~]|\\[ -~])*")$/D';
        if (strlen($local) > 64 || !preg_match($localPart, $local)) {
            return false;
        }
        if (!str_starts_with($domain, '[')) {
            return self::hostname($domain);
        }
        if (!str_ends_with($domain, ']')) {
            return false;
        }
        $literal = substr($domain, 1, -1);
        return self::ipv4Literal($literal) || (preg_match('/^ipv6:/i', $literal) && self::ipv6Literal(substr($literal, 5)));
    }

    private static function ipv4Literal(string $value): bool
    {
        return (bool) preg_match('/^' . self::SNUM . '(?:\.' . self::SNUM . '){3}$/D', $value);
    }

    /**
     * RFC 5321 IPv6-addr: 8 groups, or `::` with at most 6 groups; an IPv4 tail counts as 2 groups.
     */
    private static function ipv6Literal(string $value): bool
    {
        if (preg_match('/^(.*:)((?:[0-9]{1,3}\.){3}[0-9]{1,3})$/D', $value, $m)) {
            if (!self::ipv4Literal($m[2])) {
                return false;
            }
            $value = $m[1] . '0:0';
        }
        $parts = explode('::', $value);
        if (count($parts) > 2) {
            return false;
        }
        $count = 0;
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            foreach (explode(':', $part) as $group) {
                if (!preg_match('/^[0-9A-Fa-f]{1,4}$/D', $group)) {
                    return false;
                }
                $count++;
            }
        }
        return count($parts) === 2 ? $count <= 6 : $count === 8;
    }

    private static function hostname(string $value): bool
    {
        if ($value === '' || strlen($value) > 253) {
            return false;
        }
        foreach (explode('.', $value) as $label) {
            if (!preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?$/D', $label)) {
                return false;
            }
        }
        return true;
    }

    private static function ipv6(string $value): bool
    {
        $groups = 8;
        if (preg_match('/^(.*:)((?:[0-9]{1,3}\.){3}[0-9]{1,3})$/D', $value, $m)) {
            if (!self::match(self::PATTERNS['ipv4'], $m[2])) {
                return false;
            }
            $value = $m[1] . '0:0';
        }
        $parts = explode('::', $value);
        if (count($parts) > 2) {
            return false;
        }
        $hex = '/^[0-9A-Fa-f]{1,4}$/D';
        $count = 0;
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            foreach (explode(':', $part) as $group) {
                if (!preg_match($hex, $group)) {
                    return false;
                }
                $count++;
            }
        }
        return count($parts) === 2 ? $count < $groups : $count === $groups;
    }
}
