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
 * Moodle Doctor administration UI.
 *
 * @package    tool_moodledoctor
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_moodledoctor\ai_service;
use tool_moodledoctor\diagnostic_collector;
use tool_moodledoctor\form\checklist_form;
use tool_moodledoctor\form\error_form;
use tool_moodledoctor\form\plugin_form;
use tool_moodledoctor\preview_store;
use tool_moodledoctor\sanitizer;

require_login();
admin_externalpage_setup('tool_moodledoctor');
require_capability('tool/moodledoctor:use', context_system::instance());

$tabs = ['health', 'error', 'cron', 'plugin', 'environment', 'checklist'];
$tab = optional_param('tab', 'health', PARAM_ALPHA);
if (!in_array($tab, $tabs, true)) {
    $tab = 'health';
}
$action = optional_param('action', '', PARAM_ALPHA);
$nonce = optional_param('nonce', '', PARAM_ALPHANUM);

$PAGE->set_url(new moodle_url('/admin/tool/moodledoctor/index.php', ['tab' => $tab]));
$PAGE->set_title(get_string('pluginname', 'tool_moodledoctor'));
$PAGE->set_heading(get_string('pluginname', 'tool_moodledoctor'));

$collector = new diagnostic_collector();
$previewnonce = null;
$resultmessages = null;
$airesult = null;
$displayfacts = null;
$error = null;

/**
 * Create and store a sanitized preview.
 *
 * @param string $mode Diagnostic mode.
 * @param array $facts Facts.
 * @param array $manual Manual context.
 * @return string
 */
function tool_moodledoctor_make_preview(string $mode, array $facts, array $manual = []): string {
    $messages = ai_service::build_messages($mode, $facts, sanitizer::sanitize($manual));
    return preview_store::put($messages);
}

try {
    if ($action === 'prepare') {
        require_sesskey();
        if (!in_array($tab, ['health', 'cron', 'environment'], true)) {
            throw new invalid_parameter_exception('Unsupported deterministic preview mode.');
        }
        if ($tab === 'health') {
            $displayfacts = $collector->collect_health();
        } else if ($tab === 'cron') {
            $displayfacts = $collector->collect_cron_diagnosis();
        } else {
            $displayfacts = $collector->collect_environment_diagnosis();
        }
        $previewnonce = tool_moodledoctor_make_preview($tab, $displayfacts);
    } else if ($action === 'confirm') {
        require_sesskey();
        $resultmessages = preview_store::consume($nonce);
        if ($resultmessages === null) {
            throw new moodle_exception('invalidpreview', 'tool_moodledoctor');
        }
        $airesult = ai_service::diagnose($resultmessages);
    }
} catch (Throwable $e) {
    $error = sanitizer::sanitize_text($e->getMessage());
}

$errorform = null;
$pluginform = null;
$checklistform = null;

if ($action === '' && $tab === 'error') {
    $errorform = new error_form(new moodle_url('/admin/tool/moodledoctor/index.php', ['tab' => 'error']));
    if ($data = $errorform->get_data()) {
        try {
            $displayfacts = $collector->collect_environment_diagnosis();
            $manual = [
                'error_or_stack_trace' => sanitizer::sanitize_text((string)$data->errorinput),
                'additional_context' => sanitizer::sanitize_text((string)$data->additionalcontext),
            ];
            $previewnonce = tool_moodledoctor_make_preview('error', $displayfacts, $manual);
        } catch (Throwable $e) {
            $error = sanitizer::sanitize_text($e->getMessage());
        }
    }
}

if ($action === '' && $tab === 'plugin') {
    $pluginform = new plugin_form(
        new moodle_url('/admin/tool/moodledoctor/index.php', ['tab' => 'plugin']),
        ['plugins' => $collector->get_plugin_choices()]
    );
    if ($data = $pluginform->get_data()) {
        try {
            $displayfacts = $collector->collect_plugin_diagnosis((string)$data->component);
            $manual = [
                'observed_problem' => sanitizer::sanitize_text((string)$data->plugincontext),
            ];
            $previewnonce = tool_moodledoctor_make_preview('plugin', $displayfacts, $manual);
        } catch (Throwable $e) {
            $error = sanitizer::sanitize_text($e->getMessage());
        }
    }
}

if ($action === '' && $tab === 'checklist') {
    $checklistform = new checklist_form(new moodle_url('/admin/tool/moodledoctor/index.php', ['tab' => 'checklist']));
    if ($data = $checklistform->get_data()) {
        try {
            $displayfacts = $collector->collect_checklist_context();
            $manual = [
                'investigation_goal' => sanitizer::sanitize_text((string)$data->question),
            ];
            $previewnonce = tool_moodledoctor_make_preview('checklist', $displayfacts, $manual);
        } catch (Throwable $e) {
            $error = sanitizer::sanitize_text($e->getMessage());
        }
    }
}

if ($action === '' && $previewnonce === null && $airesult === null) {
    try {
        if ($tab === 'health') {
            $displayfacts = $collector->collect_health();
        } else if ($tab === 'cron') {
            $displayfacts = $collector->collect_cron_diagnosis();
        } else if ($tab === 'environment') {
            $displayfacts = $collector->collect_environment_diagnosis();
        }
    } catch (Throwable $e) {
        $error = sanitizer::sanitize_text($e->getMessage());
    }
}

$tabobjects = [];
foreach ($tabs as $tabname) {
    $tabobjects[] = new tabobject(
        $tabname,
        new moodle_url('/admin/tool/moodledoctor/index.php', ['tab' => $tabname]),
        get_string('tab:' . $tabname, 'tool_moodledoctor')
    );
}

echo $OUTPUT->header();
echo $OUTPUT->tabtree($tabobjects, $tab);
echo $OUTPUT->notification(get_string('dangerousactionsdisabled', 'tool_moodledoctor'), 'info');
echo $OUTPUT->notification(get_string('sanitizednotice', 'tool_moodledoctor'), 'info');

if ($error !== null) {
    echo $OUTPUT->notification($error, 'error');
}

if ($resultmessages !== null && $airesult !== null) {
    echo html_writer::tag('h3', get_string('previewheading', 'tool_moodledoctor'));
    echo tool_moodledoctor_json_panel($resultmessages);
    echo $OUTPUT->notification(get_string('ainotfact', 'tool_moodledoctor'), 'warning');
    echo html_writer::tag('h3', get_string('aiheading', 'tool_moodledoctor'));
    echo html_writer::tag('pre', s($airesult), ['class' => 'border rounded p-3 bg-light text-wrap']);
} else if ($previewnonce !== null) {
    $messages = preview_store::get($previewnonce);
    if ($displayfacts !== null) {
        echo html_writer::tag('h3', get_string('factsheading', 'tool_moodledoctor'));
        echo html_writer::tag('p', get_string('factsexplanation', 'tool_moodledoctor'));
        echo tool_moodledoctor_json_panel($displayfacts);
    }
    echo html_writer::tag('h3', get_string('previewheading', 'tool_moodledoctor'));
    echo html_writer::tag('p', get_string('previewexplanation', 'tool_moodledoctor'));
    echo tool_moodledoctor_json_panel($messages ?? []);

    $confirmurl = new moodle_url('/admin/tool/moodledoctor/index.php', [
        'tab' => $tab,
        'action' => 'confirm',
        'nonce' => $previewnonce,
        'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->single_button($confirmurl, get_string('confirmsend', 'tool_moodledoctor'), 'post', ['class' => 'btn-primary']);
    echo html_writer::link(
        new moodle_url('/admin/tool/moodledoctor/index.php', ['tab' => $tab]),
        get_string('cancelpreview', 'tool_moodledoctor'),
        ['class' => 'btn btn-secondary ms-2']
    );
} else {
    if ($displayfacts !== null) {
        echo html_writer::tag('h3', get_string('factsheading', 'tool_moodledoctor'));
        echo html_writer::tag('p', get_string('factsexplanation', 'tool_moodledoctor'));
        echo tool_moodledoctor_json_panel($displayfacts);
    }

    if ($tab === 'health' || $tab === 'cron' || $tab === 'environment') {
        $prepareurl = new moodle_url('/admin/tool/moodledoctor/index.php', [
            'tab' => $tab,
            'action' => 'prepare',
            'sesskey' => sesskey(),
        ]);
        echo $OUTPUT->single_button($prepareurl, get_string('prepareai', 'tool_moodledoctor'), 'post');
    } else if ($tab === 'error' && $errorform !== null) {
        $errorform->display();
    } else if ($tab === 'plugin' && $pluginform !== null) {
        $pluginform->display();
    } else if ($tab === 'checklist' && $checklistform !== null) {
        $checklistform->display();
    }
}

echo $OUTPUT->footer();

/**
 * Render structured data as escaped JSON.
 *
 * @param mixed $data Data.
 * @return string
 */
function tool_moodledoctor_json_panel(mixed $data): string {
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    return html_writer::tag('pre', s((string)$json), ['class' => 'border rounded p-3 bg-light text-wrap']);
}
