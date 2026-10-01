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
 * Deterministic Moodle diagnostic collectors.
 *
 * @package    tool_moodledoctor
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodledoctor;

use core\check\manager as check_manager;
use core\plugininfo\base;
use core_cache\config;
use core_plugin_manager;
use invalid_parameter_exception;
use Throwable;

/**
 * Read-only data collection. No method in this class changes Moodle state.
 */
class diagnostic_collector {
    /**
    * Maximum failed task log rows included.
    */
    private const MAX_TASK_FAILURES = 25;

    /**
    * Maximum problematic scheduled/adhoc task rows included.
    */
    private const MAX_TASK_PROBLEMS = 50;

    /**
     * Health-check facts.
     *
     * @return array
     */
    public function collect_health(): array {
        return sanitizer::sanitize([
            'versions' => $this->collect_versions(),
            'cron' => $this->collect_cron(),
            'tasks' => $this->collect_tasks(),
            'environment' => $this->collect_environment(),
            'health_checks' => $this->collect_check_api(),
            'plugins' => $this->collect_plugins(),
            'safe_configuration' => $this->collect_safe_configuration(),
            'cache_summary' => $this->collect_cache_summary(),
        ]);
    }

    /**
     * Environment-focused facts.
     *
     * @return array
     */
    public function collect_environment_diagnosis(): array {
        return sanitizer::sanitize([
            'versions' => $this->collect_versions(),
            'environment' => $this->collect_environment(),
            'health_checks' => $this->collect_check_api(),
            'safe_configuration' => $this->collect_safe_configuration(),
            'cache_summary' => $this->collect_cache_summary(),
        ]);
    }

    /**
     * Cron-focused facts.
     *
     * @return array
     */
    public function collect_cron_diagnosis(): array {
        return sanitizer::sanitize([
            'versions' => $this->collect_versions(),
            'cron' => $this->collect_cron(),
            'tasks' => $this->collect_tasks(),
            'task_configuration' => $this->collect_task_configuration(),
        ]);
    }

    /**
     * Plugin-focused facts.
     *
     * @param string $component Frankenstyle component.
     * @return array
     */
    public function collect_plugin_diagnosis(string $component): array {
        $plugin = $this->get_plugin_record($component);
        if ($plugin === null) {
            throw new invalid_parameter_exception(get_string('unknownplugin', 'tool_moodledoctor'));
        }

        return sanitizer::sanitize([
            'versions' => $this->collect_versions(),
            'plugin' => $plugin,
            'related_failed_tasks' => $this->collect_failed_tasks_for_component($component),
            'environment' => $this->collect_environment(),
        ]);
    }

    /**
     * Minimal context for an investigation checklist.
     *
     * @return array
     */
    public function collect_checklist_context(): array {
        return sanitizer::sanitize([
            'versions' => $this->collect_versions(),
            'cron' => $this->collect_cron(),
            'task_problem_summary' => $this->task_problem_summary(),
            'environment_summary' => $this->environment_summary(),
            'plugin_problem_summary' => $this->plugin_problem_summary(),
            'safe_configuration' => $this->collect_safe_configuration(),
        ]);
    }

    /**
     * Get plugin choices for the form.
     *
     * @return array
     */
    public function get_plugin_choices(): array {
        $choices = [];
        foreach ($this->flatten_plugins() as $component => $plugin) {
            $label = $component;
            try {
                $label .= ' — ' . $plugin->displayname;
            } catch (Throwable $e) {
                // Component remains a stable fallback label.
            }
            $choices[$component] = $label;
        }
        natcasesort($choices);
        return $choices;
    }

    /**
     * Core and platform versions without credentials or connection details.
     *
     * @return array
     */
    private function collect_versions(): array {
        global $CFG, $DB;

        $serverversion = null;
        try {
            if (method_exists($DB, 'get_server_info')) {
                $serverinfo = $DB->get_server_info();
                if (is_array($serverinfo) && array_key_exists('version', $serverinfo)) {
                    $serverversion = (string)$serverinfo['version'];
                } else if (is_scalar($serverinfo)) {
                    $serverversion = (string)$serverinfo;
                }
            }
        } catch (Throwable $e) {
            $serverversion = null;
        }

        return [
            'moodle_release' => (string)$CFG->release,
            'moodle_version' => (string)$CFG->version,
            'php_version' => PHP_VERSION,
            'database_family' => (string)$DB->get_dbfamily(),
            'database_server_version' => sanitizer::sanitize($serverversion),
        ];
    }

    /**
     * Environment.xml checks using Moodle's own environment API.
     *
     * @return array
     */
    private function collect_environment(): array {
        global $CFG;

        require_once($CFG->libdir . '/environmentlib.php');
        $checks = [];
        try {
            [$overall, $results] = check_moodle_environment($CFG->release, ENV_SELECT_NEWER);
            foreach ($results as $result) {
                $checks[] = [
                    'part' => (string)$result->getPart(),
                    'passed' => (bool)$result->getStatus(),
                    'level' => (string)$result->getLevel(),
                    'current_version' => (string)$result->getCurrentVersion(),
                    'needed_version' => (string)$result->getNeededVersion(),
                    'info' => clean_param((string)$result->getInfo(), PARAM_TEXT),
                    'error_code' => (int)$result->getErrorCode(),
                    'plugin' => property_exists($result, 'plugin') ? (string)($result->plugin ?? '') : '',
                ];
            }
            return [
                'overall_passed' => (bool)$overall,
                'checks' => $checks,
            ];
        } catch (Throwable $e) {
            return [
                'overall_passed' => null,
                'collection_error' => get_class($e) . ': ' . sanitizer::sanitize_text($e->getMessage()),
                'checks' => [],
            ];
        }
    }

    /**
     * Moodle Check API status, security and performance summaries.
     *
     * Details are deliberately omitted because they may contain paths or operational data.
     *
     * @return array
     */
    private function collect_check_api(): array {
        $out = [];
        foreach (['status', 'security', 'performance'] as $type) {
            $out[$type] = [];
            try {
                foreach (check_manager::get_checks($type) as $check) {
                    try {
                        $result = $check->get_result();
                        $out[$type][] = [
                            'ref' => $check->get_ref(),
                            'component' => $check->get_component(),
                            'name' => clean_param($check->get_name(), PARAM_TEXT),
                            'status' => $result->get_status(),
                            'summary' => clean_param(strip_tags($result->get_summary()), PARAM_TEXT),
                        ];
                    } catch (Throwable $e) {
                        $out[$type][] = [
                            'ref' => method_exists($check, 'get_ref') ? $check->get_ref() : get_class($check),
                            'status' => 'collection_error',
                            'error' => get_class($e),
                        ];
                    }
                }
            } catch (Throwable $e) {
                $out[$type][] = [
                    'status' => 'collection_error',
                    'error' => get_class($e),
                ];
            }
        }
        return $out;
    }

    /**
     * Cron timing and state.
     *
     * @return array
     */
    private function collect_cron(): array {
        global $CFG;

        $now = time();
        $laststart = (int)(get_config('tool_task', 'lastcronstart') ?: 0);
        $lastinterval = (int)(get_config('tool_task', 'lastcroninterval') ?: 0);
        $expected = isset($CFG->expectedcronfrequency) ? (int)$CFG->expectedcronfrequency : MINSECS;
        $delay = $laststart > 0 ? max(0, $now - $laststart) : null;
        $threshold = $expected + MINSECS;

        return [
            'cron_cli_only' => !empty($CFG->cronclionly),
            'cron_never_seen' => $laststart === 0,
            'last_cron_start' => $laststart ?: null,
            'seconds_since_last_cron_start' => $delay,
            'last_observed_cron_interval_seconds' => $lastinterval ?: null,
            'expected_frequency_seconds' => $expected,
            'late_threshold_seconds' => $threshold,
            'is_late' => $delay === null ? true : $delay > $threshold,
        ];
    }

    /**
     * Scheduled, adhoc and recent failed task metadata.
     *
     * @return array
     */
    private function collect_tasks(): array {
        global $CFG, $DB;

        $now = time();
        $expected = isset($CFG->expectedcronfrequency) ? (int)$CFG->expectedcronfrequency : MINSECS;
        $overdue = $now - max(600, $expected * 3);

        $scheduled = $DB->get_records_select(
            'task_scheduled',
            'disabled = 0 AND (faildelay > 0 OR (nextruntime > 0 AND nextruntime < :overdue AND timestarted IS NULL))',
            ['overdue' => $overdue],
            'nextruntime ASC',
            'id,component,classname,lastruntime,nextruntime,faildelay,timestarted',
            0,
            self::MAX_TASK_PROBLEMS
        );

        $adhoc = $DB->get_records_select(
            'task_adhoc',
            'faildelay > 0 OR (nextruntime > 0 AND nextruntime < :overdue AND timestarted IS NULL)',
            ['overdue' => $overdue],
            'nextruntime ASC',
            'id,component,classname,nextruntime,faildelay,timecreated,timestarted,attemptsavailable,firststartingtime',
            0,
            self::MAX_TASK_PROBLEMS
        );

        $since = $now - WEEKSECS;
        $failed = $DB->get_records_select(
            'task_log',
            'result <> 0 AND timestart >= :since',
            ['since' => $since],
            'timestart DESC',
            'id,type,component,classname,timestart,timeend,result',
            0,
            self::MAX_TASK_FAILURES
        );

        return [
            'problematic_scheduled' => array_values(array_map([$this, 'normalize_task_record'], $scheduled)),
            'problematic_adhoc' => array_values(array_map([$this, 'normalize_task_record'], $adhoc)),
            'recent_failed_runs_last_7_days' => array_values(array_map([$this, 'normalize_task_log_record'], $failed)),
            'important_note' => 'task_log.output and task_adhoc.customdata are intentionally never collected.',
        ];
    }

    /**
     * Relevant task configuration values that are safe to expose.
     *
     * @return array
     */
    private function collect_task_configuration(): array {
        global $CFG;

        $keys = [
            'expectedcronfrequency',
            'adhoctaskagewarn',
            'adhoctaskageerror',
            'taskruntimewarn',
            'taskruntimeerror',
            'task_scheduled_concurrency_limit',
            'task_scheduled_max_runtime',
            'task_adhoc_concurrency_limit',
            'task_adhoc_max_runtime',
            'cron_keepalive',
        ];
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = property_exists($CFG, $key) ? $CFG->{$key} : null;
        }
        return $values;
    }


    /**
     * Cache store summary without store configuration values.
     *
     * Raw MUC store configuration is intentionally excluded because Redis/Memcached
     * configuration may contain credentials or internal connection data.
     *
     * @return array
     */
    private function collect_cache_summary(): array {
        try {
            $stores = config::instance()->get_all_stores();
            $out = [];
            foreach ($stores as $name => $store) {
                $out[] = [
                    'name' => (string)$name,
                    'plugin' => isset($store['plugin']) ? (string)$store['plugin'] : null,
                    'class' => isset($store['class']) ? (string)$store['class'] : null,
                    'default' => !empty($store['default']),
                    'mappings_only' => !empty($store['mappingsonly']),
                ];
            }
            return $out;
        } catch (Throwable $e) {
            return [[
                'collection_error' => get_class($e),
            ]];
        }
    }

    /**
     * Safe non-secret configuration useful to diagnosis.
     *
     * @return array
     */
    private function collect_safe_configuration(): array {
        global $CFG;

        $keys = [
            'debug',
            'debugdisplay',
            'cachejs',
            'langstringcache',
            'themedesignermode',
            'slasharguments',
            'reverseproxy',
            'sslproxy',
            'preventexecpath',
        ];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = property_exists($CFG, $key) ? $CFG->{$key} : null;
        }
        $out['session_handler_class'] = property_exists($CFG, 'session_handler_class')
            ? (string)$CFG->session_handler_class
            : null;
        $out['alternative_cache_factory_class'] = property_exists($CFG, 'alternative_cache_factory_class')
            ? (string)$CFG->alternative_cache_factory_class
            : null;
        return $out;
    }

    /**
     * Plugin inventory including declared dependency checks.
     *
     * @return array
     */
    private function collect_plugins(): array {
        global $CFG;

        $plugins = [];
        foreach ($this->flatten_plugins() as $component => $plugin) {
            $dependencies = [];
            foreach ($plugin->get_other_required_plugins() as $requiredcomponent => $requiredversion) {
                $required = $this->get_plugin_info($requiredcomponent);
                $installed = $required && $required->versiondb !== null ? (string)$required->versiondb : null;
                $satisfied = false;
                if ($required !== null && $installed !== null) {
                    $satisfied = $requiredversion === ANY_VERSION || (float)$installed >= (float)$requiredversion;
                }
                $dependencies[] = [
                    'component' => $requiredcomponent,
                    'required_version' => $requiredversion === ANY_VERSION ? 'ANY_VERSION' : (string)$requiredversion,
                    'installed_version' => $installed,
                    'satisfied' => $satisfied,
                ];
            }

            $plugins[] = [
                'component' => $component,
                'version_db' => $plugin->versiondb === null ? null : (string)$plugin->versiondb,
                'version_disk' => $plugin->versiondisk === null ? null : (string)$plugin->versiondisk,
                'requires_moodle' => $plugin->versionrequires === null ? null : (string)$plugin->versionrequires,
                'core_requirement_satisfied' => $plugin->is_core_dependency_satisfied($CFG->version),
                'status' => $plugin->get_status(),
                'enabled' => $plugin->is_enabled(),
                'dependencies' => $dependencies,
            ];
        }
        return $plugins;
    }

    /**
     * Get one plugin record from the deterministic inventory.
     *
     * @param string $component Component.
     * @return array|null
     */
    private function get_plugin_record(string $component): ?array {
        foreach ($this->collect_plugins() as $plugin) {
            if ($plugin['component'] === $component) {
                return $plugin;
            }
        }
        return null;
    }

    /**
     * Failed task log records for one plugin component.
     *
     * @param string $component Component.
     * @return array
     */
    private function collect_failed_tasks_for_component(string $component): array {
        global $DB;

        $records = $DB->get_records_select(
            'task_log',
            'component = :component AND result <> 0 AND timestart >= :since',
            ['component' => $component, 'since' => time() - WEEKSECS],
            'timestart DESC',
            'id,type,component,classname,timestart,timeend,result',
            0,
            self::MAX_TASK_FAILURES
        );
        return array_values(array_map([$this, 'normalize_task_log_record'], $records));
    }

    /**
     * Summarize tasks for checklist mode.
     *
     * @return array
     */
    private function task_problem_summary(): array {
        $tasks = $this->collect_tasks();
        return [
            'problematic_scheduled_count' => count($tasks['problematic_scheduled']),
            'problematic_adhoc_count' => count($tasks['problematic_adhoc']),
            'failed_runs_last_7_days_count' => count($tasks['recent_failed_runs_last_7_days']),
        ];
    }

    /**
     * Summarize environment result counts.
     *
     * @return array
     */
    private function environment_summary(): array {
        $environment = $this->collect_environment();
        $failed = 0;
        foreach ($environment['checks'] ?? [] as $check) {
            if (!$check['passed']) {
                $failed++;
            }
        }
        return [
            'overall_passed' => $environment['overall_passed'] ?? null,
            'failed_checks' => $failed,
            'total_checks' => count($environment['checks'] ?? []),
        ];
    }

    /**
     * Summarize plugin status and dependency problems.
     *
     * @return array
     */
    private function plugin_problem_summary(): array {
        $plugins = $this->collect_plugins();
        $statusproblems = 0;
        $dependencyproblems = 0;
        foreach ($plugins as $plugin) {
            if (!in_array($plugin['status'], [core_plugin_manager::PLUGIN_STATUS_UPTODATE, core_plugin_manager::PLUGIN_STATUS_NODB], true)) {
                $statusproblems++;
            }
            foreach ($plugin['dependencies'] as $dependency) {
                if (!$dependency['satisfied']) {
                    $dependencyproblems++;
                }
            }
        }
        return [
            'plugin_count' => count($plugins),
            'plugins_with_non_normal_status' => $statusproblems,
            'unsatisfied_dependency_count' => $dependencyproblems,
        ];
    }

    /**
     * Flatten plugin manager tree.
     *
     * @return array<string,base>
     */
    private function flatten_plugins(): array {
        $out = [];
        foreach (core_plugin_manager::instance()->get_plugins() as $type => $plugins) {
            foreach ($plugins as $name => $plugin) {
                $out[$type . '_' . $name] = $plugin;
            }
        }
        ksort($out);
        return $out;
    }

    /**
     * Resolve plugin info by component.
     *
     * @param string $component Component.
     * @return base|null
     */
    private function get_plugin_info(string $component): ?base {
        $plugins = $this->flatten_plugins();
        return $plugins[$component] ?? null;
    }

    /**
     * Normalize a task queue record without customdata, host or PID.
     *
     * @param object $record Record.
     * @return array
     */
    private function normalize_task_record(object $record): array {
        return array_filter([
            'id' => isset($record->id) ? (int)$record->id : null,
            'component' => $record->component ?? null,
            'classname' => $record->classname ?? null,
            'last_runtime' => isset($record->lastruntime) ? (int)$record->lastruntime : null,
            'next_runtime' => isset($record->nextruntime) ? (int)$record->nextruntime : null,
            'fail_delay_seconds' => isset($record->faildelay) ? (int)$record->faildelay : null,
            'time_created' => isset($record->timecreated) ? (int)$record->timecreated : null,
            'time_started' => isset($record->timestarted) ? (int)$record->timestarted : null,
            'attempts_available' => isset($record->attemptsavailable) ? (int)$record->attemptsavailable : null,
            'first_starting_time' => isset($record->firststartingtime) ? (int)$record->firststartingtime : null,
        ], static fn($value) => $value !== null);
    }

    /**
     * Normalize task log metadata. Output is intentionally excluded.
     *
     * @param object $record Record.
     * @return array
     */
    private function normalize_task_log_record(object $record): array {
        return [
            'id' => (int)$record->id,
            'type' => (int)$record->type === 0 ? 'scheduled' : 'adhoc',
            'component' => (string)$record->component,
            'classname' => (string)$record->classname,
            'time_start' => (float)$record->timestart,
            'time_end' => (float)$record->timeend,
            'result' => (int)$record->result,
        ];
    }
}
