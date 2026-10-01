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
 * Checklist request form.
 *
 * @package    tool_moodledoctor
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodledoctor\form;

use core_text;
use moodleform;
use tool_moodledoctor\sanitizer;

defined('MOODLE_INTERNAL') || die;

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Optional investigation goal for checklist generation.
 */
class checklist_form extends moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('textarea', 'question', get_string('checklistquestion', 'tool_moodledoctor'), [
            'rows' => 6,
            'cols' => 100,
        ]);
        $mform->setType('question', PARAM_RAW);
        $mform->addHelpButton('question', 'checklistquestion', 'tool_moodledoctor');
        $mform->addElement('hidden', 'tab', 'checklist');
        $mform->setType('tab', PARAM_ALPHA);
        $this->add_action_buttons(false, get_string('generatechecklist', 'tool_moodledoctor'));
    }

    /**
     * Validate text length.
     *
     * @param array $data Form data.
     * @param array $files Files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (isset($data['question']) && core_text::strlen((string)$data['question']) > sanitizer::MAX_MANUAL_LENGTH) {
            $errors['question'] = get_string('manualtoolarge', 'tool_moodledoctor', sanitizer::MAX_MANUAL_LENGTH);
        }
        return $errors;
    }
}
