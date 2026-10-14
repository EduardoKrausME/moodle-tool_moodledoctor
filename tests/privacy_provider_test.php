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
 * Privacy provider compliance tests.
 *
 * @package    tool_moodledoctor
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodledoctor;

use advanced_testcase;

/**
 * Ensures external-data metadata coexists with the data request interfaces.
 *
 * @covers \tool_moodledoctor\privacy\provider
 */
final class privacy_provider_test extends advanced_testcase {
    /**
     * Moodle's privacy manager must accept our provider.
     *
     * @return void
     */
    public function test_privacy_provider_is_compliant(): void {
        $manager = new \core_privacy\manager();
        $this->assertTrue($manager->component_is_compliant('tool_moodledoctor'));
    }

    /**
     * No contexts are persisted by the plugin.
     *
     * @return void
     */
    public function test_no_persisted_user_contexts(): void {
        $contexts = \tool_moodledoctor\privacy\provider::get_contexts_for_userid(123);
        $this->assertEmpty($contexts->get_contextids());
    }
}
