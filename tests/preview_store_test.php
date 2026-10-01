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
 * Preview store tests.
 *
 * @package    tool_moodledoctor
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodledoctor;

use advanced_testcase;

/**
 * Tests session-only, one-time preview behaviour.
 */
final class preview_store_test extends advanced_testcase {
    /**
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        global $SESSION;
        $SESSION->tool_moodledoctor_previews = [];
    }

    /**
     * @return void
     */
    public function test_preview_is_sanitized_and_consumed_once(): void {
        $nonce = preview_store::put([
            ['role' => 'user', 'content' => 'token=supersecret'],
        ]);
        $preview = preview_store::get($nonce);
        $this->assertNotNull($preview);
        $this->assertStringNotContainsString('supersecret', json_encode($preview));

        $consumed = preview_store::consume($nonce);
        $this->assertSame($preview, $consumed);
        $this->assertNull(preview_store::get($nonce));
        $this->assertNull(preview_store::consume($nonce));
    }

    /**
     * Previews are bound to the current user in the current session.
     *
     * @return void
     */
    public function test_preview_is_user_bound(): void {
        $nonce = preview_store::put([['role' => 'user', 'content' => 'safe']]);
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($other);
        $this->assertNull(preview_store::get($nonce));
    }
}
