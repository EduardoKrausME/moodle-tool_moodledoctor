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
 * Session-only AI preview storage.
 *
 * @package    tool_moodledoctor
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodledoctor;

/**
 * Stores sanitized AI messages only for the current Moodle session.
 */
class preview_store {
    /** Preview lifetime in seconds. */
    private const TTL = 900;

    /** Maximum previews kept per session. */
    private const MAX_PREVIEWS = 5;

    /**
     * Store messages and return an opaque nonce.
     *
     * @param array $messages AI messages.
     * @return string
     */
    public static function put(array $messages): string {
        global $SESSION, $USER;

        self::cleanup();
        $nonce = bin2hex(random_bytes(20));
        $SESSION->tool_moodledoctor_previews ??= [];
        $SESSION->tool_moodledoctor_previews[$nonce] = [
            'userid' => (int)$USER->id,
            'expires' => time() + self::TTL,
            'messages' => sanitizer::sanitize($messages),
        ];

        while (count($SESSION->tool_moodledoctor_previews) > self::MAX_PREVIEWS) {
            array_shift($SESSION->tool_moodledoctor_previews);
        }

        return $nonce;
    }

    /**
     * Read a preview without consuming it.
     *
     * @param string $nonce Nonce.
     * @return array|null
     */
    public static function get(string $nonce): ?array {
        global $SESSION, $USER;

        self::cleanup();
        $previews = $SESSION->tool_moodledoctor_previews ?? [];
        if (!isset($previews[$nonce])) {
            return null;
        }
        $preview = $previews[$nonce];
        if ((int)$preview['userid'] !== (int)$USER->id) {
            return null;
        }
        return $preview['messages'];
    }

    /**
     * Consume a preview once.
     *
     * @param string $nonce Nonce.
     * @return array|null
     */
    public static function consume(string $nonce): ?array {
        global $SESSION;

        $messages = self::get($nonce);
        if ($messages === null) {
            return null;
        }
        unset($SESSION->tool_moodledoctor_previews[$nonce]);
        return $messages;
    }

    /**
     * Remove expired entries.
     *
     * @return void
     */
    private static function cleanup(): void {
        global $SESSION;

        if (empty($SESSION->tool_moodledoctor_previews) || !is_array($SESSION->tool_moodledoctor_previews)) {
            $SESSION->tool_moodledoctor_previews = [];
            return;
        }
        $now = time();
        foreach ($SESSION->tool_moodledoctor_previews as $nonce => $preview) {
            if (empty($preview['expires']) || (int)$preview['expires'] < $now) {
                unset($SESSION->tool_moodledoctor_previews[$nonce]);
            }
        }
    }
}
