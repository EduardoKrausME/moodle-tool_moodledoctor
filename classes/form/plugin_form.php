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
 * Plugin diagnosis form.
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
 * Plugin selection and optional symptom text.
 */
class plugin_form extends moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $choices = $this->_customdata['plugins'] ?? [];
        $mform->addElement('autocomplete', 'component', get_string('plugincomponent', 'tool_moodledoctor'), $choices);
        $mform->setType('component', PARAM_COMPONENT);
        $mform->addRule('component', null, 'required', null, 'client');

        $mform->addElement('textarea', 'plugincontext', get_string('plugincontext', 'tool_moodledoctor'), [
            'rows' => 8,
            'cols' => 100,
        ]);
        $mform->setType('plugincontext', PARAM_RAW);
        $mform->addHelpButton('plugincontext', 'plugincontext', 'tool_moodledoctor');

        $mform->addElement('hidden', 'tab', 'plugin');
        $mform->setType('tab', PARAM_ALPHA);
        $this->add_action_buttons(false, get_string('previewmanual', 'tool_moodledoctor'));
    }

    /**
     * Validate component and manual context.
     *
     * @param array $data Form data.
     * @param array $files Files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $choices = $this->_customdata['plugins'] ?? [];
        if (!empty($data['component']) && !array_key_exists($data['component'], $choices)) {
            $errors['component'] = get_string('unknownplugin', 'tool_moodledoctor');
        }
        if (isset($data['plugincontext']) && core_text::strlen((string)$data['plugincontext']) > sanitizer::MAX_MANUAL_LENGTH) {
            $errors['plugincontext'] = get_string('manualtoolarge', 'tool_moodledoctor', sanitizer::MAX_MANUAL_LENGTH);
        }
        return $errors;
    }
}
