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
 * Privacy provider.
 *
 * @package    tool_moodledoctor
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodledoctor\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as request_provider;
use core_privacy\local\request\userlist;

/**
 * Describes data which may be routed to an external AI provider by local_ai_bridge.
 *
 * Moodle Doctor does not persist these payloads in its own database tables.
 */
class provider implements metadata_provider, request_provider, core_userlist_provider {
    /**
     * Describe externally processed diagnostic data.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'ai_provider_via_local_ai_bridge',
            [
                'diagnosticfacts' => 'privacy:metadata:external:diagnosticfacts',
                'manualcontext' => 'privacy:metadata:external:manualcontext',
            ],
            'privacy:metadata:external'
        );
        return $collection;
    }

    /**
     * No user contexts contain data persisted by Moodle Doctor.
     *
     * @param int $userid User identifier.
     * @return contextlist Empty list of contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * The plugin does not store users in a context.
     *
     * @param userlist $userlist Users to populate.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
    }

    /**
     * No user data exists to export from this plugin.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
    }

    /**
     * No persisted user data exists to delete.
     *
     * @param \context $context Context to clear.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
    }

    /**
     * No persisted user data exists to delete.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
    }

    /**
     * No persisted user data exists to delete.
     *
     * @param approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
    }

}
