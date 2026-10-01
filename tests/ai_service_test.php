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
 * AI service tests.
 *
 * @package    tool_moodledoctor
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodledoctor;

use advanced_testcase;

/**
 * Tests exact preview message construction without calling a provider.
 */
final class ai_service_test extends advanced_testcase {
    /**
     * The preview payload must already be sanitized before it reaches preview_store.
     *
     * @return void
     */
    public function test_build_messages_sanitizes_facts_and_manual_context(): void {
        $messages = ai_service::build_messages(
            'error',
            ['version' => '4.5', 'config' => ['password' => 'fact-secret']],
            ['trace' => 'Authorization: Bearer manual-secret']
        );
        $encoded = json_encode($messages);
        $this->assertStringNotContainsString('fact-secret', $encoded);
        $this->assertStringNotContainsString('manual-secret', $encoded);
        $this->assertStringContainsString(sanitizer::REDACTED, $encoded);
        $this->assertStringContainsString('Symptoms; Hypotheses; Evidence;', $messages[0]['content']);
        $this->assertStringContainsString('Never claim a definitive root cause', $messages[0]['content']);
    }

    /**
     * The required purpose must remain stable.
     *
     * @return void
     */
    public function test_purpose_is_fixed(): void {
        $this->assertSame('moodledoctor-diagnose', ai_service::PURPOSE);
    }
}
