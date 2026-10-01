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
 * Sanitizer tests.
 *
 * @package    tool_moodledoctor
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodledoctor;

use advanced_testcase;

/**
 * Security tests for redaction before AI calls.
 *
 * @covers \\tool_moodledoctor\\sanitizer
 */
final class sanitizer_test extends advanced_testcase {
    /**
     * Secret text cases.
     *
     * @return array
     */
    public static function secret_text_provider(): array {
        return [
            'password equals' => ['password=hunter2', 'hunter2'],
            'password colon' => ['password: hunter2', 'hunter2'],
            'quoted json password' => ['{"password":"hunter2"}', 'hunter2'],
            'token equals' => ['token=tok_123456', 'tok_123456'],
            'access token' => ['access_token=access-secret', 'access-secret'],
            'refresh token' => ['refresh-token=refresh-secret', 'refresh-secret'],
            'api key' => ['api_key=key-secret', 'key-secret'],
            'client secret' => ['client_secret=client-secret', 'client-secret'],
            'sesskey' => ['sesskey=abcdef0123456789', 'abcdef0123456789'],
            'authorization bearer' => ['Authorization: Bearer abc.def.ghi', 'abc.def.ghi'],
            'authorization basic' => ['Authorization: Basic dXNlcjpwYXNz', 'dXNlcjpwYXNz'],
            'standalone bearer' => ['Provider returned Bearer abc123xyz', 'abc123xyz'],
            'cookie header' => ['Cookie: MoodleSession=verysecret; theme=boost', 'verysecret'],
            'set cookie header' => ['Set-Cookie: PHPSESSID=session-secret; Secure', 'session-secret'],
            'moodle session' => ['MoodleSession=session-secret', 'session-secret'],
            'php session' => ['PHPSESSID=session-secret', 'session-secret'],
            'mysql url credentials' => ['mysql://dbuser:dbpass@example.test/moodle', 'dbpass'],
            'https url credentials' => ['https://alice:s3cret@example.test/path', 's3cret'],
            'pdo password' => ['mysql:host=db;dbname=moodle;user=alice;password=dbsecret', 'dbsecret'],
            'pdo user' => ['mysql:host=db;dbname=moodle;user=alice;password=dbsecret', 'alice'],
            'libpq password' => ['host=db user=alice password=dbsecret dbname=moodle', 'dbsecret'],
            'query token' => ['https://example.test/cb?token=querysecret&x=1', 'querysecret'],
            'query sesskey' => ['https://example.test/?sesskey=querysecret&x=1', 'querysecret'],
            'cli password' => ['tool --password hunter2 --verbose', 'hunter2'],
            'cli token' => ['tool --token=tok_secret --verbose', 'tok_secret'],
        ];
    }

    /**
     * Method test_text_secrets_are_removed.
     *
     * @dataProvider secret_text_provider
     * @param string $input Parameter input.
     * @param string $secret Parameter secret.
     * @return void Return value.
     */
    public function test_text_secrets_are_removed(string $input, string $secret): void {
        $clean = sanitizer::sanitize_text($input);
        $this->assertStringNotContainsString($secret, $clean);
        $this->assertStringContainsString(sanitizer::REDACTED, $clean);
    }

    /**
     * Sanitization must be idempotent because payloads are sanitized at preview and send time.
     *
     * @return void
     */
    public function test_sanitization_is_idempotent(): void {
        $input = "Authorization: Bearer abc123\nCookie: MoodleSession=session123\npassword=secret";
        $once = sanitizer::sanitize_text($input);
        $twice = sanitizer::sanitize_text($once);
        $this->assertSame($once, $twice);
    }

    /**
     * Structured secret keys must be redacted recursively.
     *
     * @return void
     */
    public function test_structured_data_is_redacted_recursively(): void {
        $input = [
            'password' => 'one',
            'nested' => [
                'api_key' => 'two',
                'Authorization' => 'Bearer three',
                'safe' => 'kept',
            ],
            'object' => (object)[
                'dbpass' => 'four',
                'token' => 'five',
            ],
        ];

        $clean = sanitizer::sanitize($input);
        $encoded = json_encode($clean);
        foreach (['one', 'two', 'three', 'four', 'five'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
        $this->assertSame('kept', $clean['nested']['safe']);
    }

    /**
     * Safe stack trace context should remain useful after sanitization.
     *
     * @return void
     */
    public function test_non_secret_diagnostic_text_is_preserved(): void {
        $input = "coding_exception: Invalid state\n#0 /var/www/moodle/lib/classes/foo.php(123): bar()";
        $this->assertSame($input, sanitizer::sanitize_text($input));
    }
}
