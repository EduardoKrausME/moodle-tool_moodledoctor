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
 * Diagnostic collector security tests.
 *
 * @package    tool_moodledoctor
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodledoctor;

use advanced_testcase;

/**
 * Verifies that dangerous task payload fields are never collected.
 *
 * @covers \\tool_moodledoctor\\diagnostic_collector
 */
final class diagnostic_collector_test extends advanced_testcase {
    /**
     * task_log.output and task_adhoc.customdata must never enter AI-bound facts.
     *
     * @return void
     */
    public function test_task_payload_fields_are_not_collected(): void {
        global $DB, $USER;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $now = time();
        $DB->insert_record('task_log', (object)[
            'type' => 0,
            'component' => 'tool_moodledoctor',
            'classname' => '\\tool_moodledoctor\\task\\does_not_exist',
            'userid' => (int)$USER->id,
            'timestart' => $now - 5,
            'timeend' => $now - 1,
            'dbreads' => 1,
            'dbwrites' => 0,
            'result' => 1,
            'output' => 'password=task-log-secret',
        ]);

        $DB->insert_record('task_adhoc', (object)[
            'component' => 'tool_moodledoctor',
            'classname' => '\\tool_moodledoctor\\task\\does_not_exist',
            'nextruntime' => $now - HOURSECS,
            'faildelay' => 60,
            'customdata' => '{"token":"adhoc-secret"}',
            'userid' => null,
            'timecreated' => $now - HOURSECS,
            'timestarted' => null,
            'hostname' => null,
            'pid' => null,
            'attemptsavailable' => 1,
            'firststartingtime' => $now - HOURSECS,
        ]);

        $facts = (new diagnostic_collector())->collect_cron_diagnosis();
        $encoded = json_encode($facts);
        $this->assertStringNotContainsString('task-log-secret', $encoded);
        $this->assertStringNotContainsString('adhoc-secret', $encoded);
        $this->assertStringNotContainsString('"output"', $encoded);
        $this->assertStringNotContainsString('"customdata"', $encoded);
        $this->assertStringContainsString('task_log.output and task_adhoc.customdata are intentionally never collected', $encoded);
    }
}
