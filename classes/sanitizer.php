<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Diagnostic payload sanitizer.
 *
 * @package    tool_moodledoctor
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodledoctor;

/**
 * Defence-in-depth sanitizer for every value that may be sent to AI.
 */
class sanitizer {
    /** @var string */
    public const REDACTED = '[REDACTED]';

    /** @var int */
    public const MAX_MANUAL_LENGTH = 100000;

    /**
     * Sanitize arbitrary structured data recursively.
     *
     * @param mixed $value Value to sanitize.
     * @param string|null $key Parent key, when available.
     * @return mixed
     */
    public static function sanitize(mixed $value, ?string $key = null): mixed {
        if ($key !== null && self::is_secret_key($key)) {
            return self::REDACTED;
        }

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $itemkey => $itemvalue) {
                $clean[$itemkey] = self::sanitize($itemvalue, is_string($itemkey) ? $itemkey : null);
            }
            return $clean;
        }

        if (is_object($value)) {
            $clean = [];
            foreach ((array)$value as $itemkey => $itemvalue) {
                $clean[$itemkey] = self::sanitize($itemvalue, (string)$itemkey);
            }
            return $clean;
        }

        if (is_string($value)) {
            return self::sanitize_text($value);
        }

        return $value;
    }

    /**
     * Sanitize a text blob such as a stack trace or pasted configuration excerpt.
     *
     * @param string $text Text.
     * @return string
     */
    public static function sanitize_text(string $text): string {
        if ($text === '') {
            return '';
        }

        // URLs containing user:password@host. Preserve scheme and destination, remove credentials.
        $text = preg_replace(
            '~\b([a-z][a-z0-9+.-]*://)([^\s/@:]+):([^\s/@]+)@~iu',
            '$1' . self::REDACTED . '@',
            $text
        ) ?? $text;

        // Authorization headers, including Basic and Bearer variants.
        $text = preg_replace(
            '/(^|\R)(\s*Authorization\s*:\s*)[^\r\n]+/i',
            '$1$2' . self::REDACTED,
            $text
        ) ?? $text;

        // Cookie and Set-Cookie headers are redacted as a whole because individual cookie names vary widely.
        $text = preg_replace(
            '/(^|\R)(\s*(?:Set-)?Cookie\s*:\s*)[^\r\n]+/i',
            '$1$2' . self::REDACTED,
            $text
        ) ?? $text;

        // Standalone Bearer credentials outside an Authorization header.
        $text = preg_replace(
            '/\bBearer\s+[A-Za-z0-9._~+\-\/=]+/i',
            'Bearer ' . self::REDACTED,
            $text
        ) ?? $text;

        // Common key/value forms: password=x, "token":"x", dbpass => 'x', api_key: x, etc.
        $keys = implode('|', [
            'password', 'passwd', 'passphrase', 'dbpass', 'dbpassword',
            'token', 'access[_-]?token', 'refresh[_-]?token', 'auth[_-]?token',
            'api[_-]?key', 'apikey', 'secret', 'client[_-]?secret',
            'sesskey', 'sessionid', 'session[_-]?id', 'moodlesession', 'phpsessid',
            'authorization', 'cookie', 'cookies',
        ]);

        $text = preg_replace_callback(
            '/(["\']?\b(?:' . $keys . ')\b["\']?\s*(?:=|:|=>)\s*)("[^"\r\n]*"|\'[^\'\r\n]*\'|[^\s,;\r\n]+)/i',
            static function (array $matches): string {
                $value = $matches[2];
                $first = $value[0] ?? '';
                if ($first === '"' || $first === "'") {
                    return $matches[1] . $first . self::REDACTED . $first;
                }
                return $matches[1] . self::REDACTED;
            },
            $text
        ) ?? $text;

        // Query-string and form-style secrets, preserving separators and parameter names.
        $text = preg_replace(
            '/([?&;](?:' . $keys . ')=)[^&#;\s]+/i',
            '$1' . self::REDACTED,
            $text
        ) ?? $text;

        // Command-line forms such as --password value and --token=value.
        $text = preg_replace(
            '/(--(?:password|passwd|dbpass|token|api[-_]?key|secret|sesskey))(?:=|\s+)\S+/i',
            '$1=' . self::REDACTED,
            $text
        ) ?? $text;

        // PDO/libpq style DSN credentials in free text.
        $text = preg_replace(
            '/\b(user|username|uid)\s*=\s*[^;\s]+/i',
            '$1=' . self::REDACTED,
            $text
        ) ?? $text;
        $text = preg_replace(
            '/\b(password|pwd)\s*=\s*[^;\s]+/i',
            '$1=' . self::REDACTED,
            $text
        ) ?? $text;

        return $text;
    }

    /**
     * Determine whether a structured key should always be redacted.
     *
     * @param string $key Key.
     * @return bool
     */
    private static function is_secret_key(string $key): bool {
        $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', $key) ?? $key);
        $secretkeys = [
            'password', 'passwd', 'passphrase', 'dbpass', 'dbpassword',
            'token', 'accesstoken', 'refreshtoken', 'authtoken',
            'apikey', 'secret', 'clientsecret', 'sesskey',
            'sessionid', 'moodlesession', 'phpsessid',
            'authorization', 'cookie', 'cookies',
        ];

        return in_array($normalized, $secretkeys, true);
    }
}
