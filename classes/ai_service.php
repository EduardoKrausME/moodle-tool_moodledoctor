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
 * AI bridge integration.
 *
 * @package    tool_moodledoctor
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodledoctor;

use local_ai_bridge\api;

/**
 * Builds and sends read-only diagnostic prompts through local_ai_bridge.
 */
class ai_service {
    /** @var string */
    public const PURPOSE = 'moodledoctor-diagnose';

    /**
     * Build the exact messages to be previewed and later sent.
     *
     * @param string $mode Diagnostic mode.
     * @param array $facts Deterministic facts.
     * @param array $manual Manual context after sanitization.
     * @return array
     */
    public static function build_messages(string $mode, array $facts, array $manual = []): array {
        $payload = sanitizer::sanitize([
            'diagnostic_mode' => $mode,
            'facts' => $facts,
            'manual_context' => $manual,
        ]);

        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        $instruction = <<<TEXT
You are interpreting a Moodle diagnostic payload. Use only the supplied data as evidence.
Separate collected facts from hypotheses. Never claim a definitive root cause unless the evidence proves it.
Do not output executable shell commands or SQL statements. Do not ask to execute shell commands or SQL.
Do not ask to execute configuration changes, plugin upgrades, cache purges, or automatic fixes.
Do not invent log entries, settings, versions, task failures, dependencies, or environment results
that are not present.
Return these sections in this order: Symptoms; Hypotheses; Evidence; Recommended checks;
Possible solutions; Confidence.
For every hypothesis, cite the relevant field names or identifiers from the payload.
Confidence must be Low, Medium, or High and include one sentence explaining why.

DIAGNOSTIC_PAYLOAD_JSON:
{$json}
TEXT;

        return sanitizer::sanitize([
            ['role' => 'user', 'content' => $instruction],
        ]);
    }

    /**
     * Call local_ai_bridge using only the required purpose.
     *
     * @param array $messages Exact previewed messages.
     * @return string
     */
    public static function diagnose(array $messages): string {
        $messages = sanitizer::sanitize($messages);
        $response = api::generate(self::PURPOSE, $messages);
        return sanitizer::sanitize_text((string)$response->text);
    }
}
