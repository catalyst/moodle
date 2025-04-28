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

namespace core_grades;

use advanced_testcase;
use backup;
use backup_controller;
use base_setting;
use context_course;
use context_module;
use context_system;
use html_writer;
use restore_controller;
use restore_dbops;

/**
 * Unit tests for penalty_exemption class.
 *
 * @package   core_grades
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \core_grades\penalty_exemption
 */
final class penalty_exemption_test extends advanced_testcase {

    /**
     * Reset the test environment after each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Load the backup and restore classes.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        parent::setUpBeforeClass();
    }

    /**
     * Test the CRUD operations for user exemptions.
     *
     * @return void
     */
    public function test_user_exemption_crud(): void {
        global $DB, $USER;

        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);

        // Create a new user exemption.
        $reason = 'Medical certificate';
        $reasonformat = FORMAT_PLAIN;
        $exemption = penalty_exemption::exempt_user(
            $user->id,
            $context->id,
            $reason,
            $reasonformat
        );

        $this->assertInstanceOf(penalty_exemption::class, $exemption);
        $this->assertNotNull($exemption->get_id());
        $this->assertEquals(penalty_exemption::TYPE_USER, $exemption->get_itemtype());
        $this->assertEquals($user->id, $exemption->get_itemid());
        $this->assertEquals($context->id, $exemption->get_contextid());
        $this->assertEquals($reason, $exemption->get_reason());
        $this->assertEquals($reasonformat, $exemption->get_reasonformat());
        $this->assertEquals($exemption->get_usermodified(), $USER->id);

        // Save changes to the exemption.
        $exemption->set_reason(html_writer::span('Updated reason'), FORMAT_HTML);
        $exemption->save();

        // Test retrieval by id.
        $fetched = penalty_exemption::get($exemption->get_id());
        $this->assertEquals($exemption, $fetched);

        // Test retrieval by itemtype, itemid and contextid.
        $fetched = penalty_exemption::find_by([
            'itemtype' => penalty_exemption::TYPE_USER,
            'itemid' => $user->id,
            'contextid' => $context->id,
        ]);
        $this->assertCount(1, $fetched);
        $this->assertEquals($exemption, reset($fetched));

        // Test deletion.
        $id = $exemption->get_id();
        $exemption->delete();
        $this->assertNull($exemption->get_id());
        $this->assertNull(penalty_exemption::get($id));
        $this->assertCount(0, penalty_exemption::find_by([
            'itemtype' => penalty_exemption::TYPE_USER,
            'itemid' => $user->id,
            'contextid' => $context->id,
        ]));
    }

    /**
     * Test the CRUD operations for group exemptions.
     *
     * @return void
     */
    public function test_group_exemption_crud(): void {
        global $DB, $USER;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        // Create a group exemption.
        $reason = 'Medical certificate';
        $reasonformat = FORMAT_PLAIN;
        $exemption = penalty_exemption::exempt_group(
            $group->id,
            $context->id,
            $reason,
            $reasonformat
        );

        $this->assertInstanceOf(penalty_exemption::class, $exemption);
        $this->assertNotNull($exemption->get_id());
        $this->assertEquals(penalty_exemption::TYPE_GROUP, $exemption->get_itemtype());
        $this->assertEquals($group->id, $exemption->get_itemid());
        $this->assertEquals($context->id, $exemption->get_contextid());
        $this->assertEquals($reason, $exemption->get_reason());
        $this->assertEquals($reasonformat, $exemption->get_reasonformat());
        $this->assertEquals($exemption->get_usermodified(), $USER->id);

        // Save changes to the exemption.
        $exemption->set_reason(html_writer::span('Updated reason'), FORMAT_HTML);
        $exemption->save();

        // Test retrieval by id.
        $fetched = penalty_exemption::get($exemption->get_id());
        $this->assertEquals($exemption, $fetched);

        // Test retrieval by itemtype, itemid and contextid.
        $fetched = penalty_exemption::find_by([
            'itemtype' => penalty_exemption::TYPE_GROUP,
            'itemid' => $group->id,
            'contextid' => $context->id,
        ]);
        $this->assertCount(1, $fetched);
        $this->assertEquals($exemption, reset($fetched));

        // Test deletion.
        $id = $exemption->get_id();
        $exemption->delete();
        $this->assertNull($exemption->get_id());
        $this->assertNull(penalty_exemption::get($id));
        $this->assertCount(0, penalty_exemption::find_by([
            'itemtype' => penalty_exemption::TYPE_GROUP,
            'itemid' => $group->id,
            'contextid' => $context->id,
        ]));
    }

    /**
     * Test user exemption functionality.
     *
     * @return void
     */
    public function test_user_is_exempt(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        $sitectx = context_system::instance();
        $coursectx = context_course::instance($course->id);
        $assignctx = context_module::instance($assign->cmid);

        $admin = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        // Create site, course, and activity level exemptions for the admin, teacher, and student users respectively.
        penalty_exemption::exempt_user($admin->id, $sitectx->id);
        penalty_exemption::exempt_user($teacher->id, $coursectx->id);
        penalty_exemption::exempt_user($student->id, $assignctx->id);

        $this->assertTrue(penalty_exemption::is_user_exempt($admin->id, $sitectx->id));
        $this->assertTrue(penalty_exemption::is_user_exempt($admin->id, $coursectx->id));
        $this->assertTrue(penalty_exemption::is_user_exempt($admin->id, $assignctx->id));

        $this->assertFalse(penalty_exemption::is_user_exempt($teacher->id, $sitectx->id));
        $this->assertTrue(penalty_exemption::is_user_exempt($teacher->id, $coursectx->id));
        $this->assertTrue(penalty_exemption::is_user_exempt($teacher->id, $assignctx->id));

        $this->assertFalse(penalty_exemption::is_user_exempt($student->id, $sitectx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($student->id, $coursectx->id));
        $this->assertTrue(penalty_exemption::is_user_exempt($student->id, $assignctx->id));

        $this->assertFalse(penalty_exemption::is_user_exempt($student2->id, $sitectx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($student2->id, $coursectx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($student2->id, $assignctx->id));
    }

    /**
     * Test group exemption functionality.
     *
     * @return void
     */
    public function test_group_is_exempt(): void {
        global $DB;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        $sitectx = context_system::instance();
        $coursectx = context_course::instance($course->id);
        $assignctx = context_module::instance($assign->cmid);

        $group1 = $this->getDataGenerator()->create_group(['courseid' => SITEID]);
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $group3 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $this->assertFalse(penalty_exemption::is_group_exempt($group1->id, $sitectx->id));
        $this->assertFalse(penalty_exemption::is_group_exempt($group2->id, $coursectx->id));
        $this->assertFalse(penalty_exemption::is_group_exempt($group3->id, $assignctx->id));

        // Create site, course, and activity level exemptions for the groups.
        penalty_exemption::exempt_group($group1->id, $sitectx->id);
        penalty_exemption::exempt_group($group2->id, $coursectx->id);
        penalty_exemption::exempt_group($group3->id, $assignctx->id);

        $this->assertTrue(penalty_exemption::is_group_exempt($group1->id, $sitectx->id));
        $this->assertTrue(penalty_exemption::is_group_exempt($group1->id, $coursectx->id));
        $this->assertTrue(penalty_exemption::is_group_exempt($group1->id, $assignctx->id));

        $this->assertFalse(penalty_exemption::is_group_exempt($group2->id, $sitectx->id));
        $this->assertTrue(penalty_exemption::is_group_exempt($group2->id, $coursectx->id));
        $this->assertTrue(penalty_exemption::is_group_exempt($group2->id, $assignctx->id));

        $this->assertFalse(penalty_exemption::is_group_exempt($group3->id, $sitectx->id));
        $this->assertFalse(penalty_exemption::is_group_exempt($group3->id, $coursectx->id));
        $this->assertTrue(penalty_exemption::is_group_exempt($group3->id, $assignctx->id));

        // Create a student and test exemption status based on group membership.
        $student = $this->getDataGenerator()->create_and_enrol($course);

        $this->assertFalse(penalty_exemption::is_user_exempt($student->id, $sitectx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($student->id, $coursectx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($student->id, $assignctx->id));

        groups_add_member($group1, $student);
        $this->assertTrue(penalty_exemption::is_user_exempt($student->id, $sitectx->id));
        $this->assertTrue(penalty_exemption::is_user_exempt($student->id, $coursectx->id));
        $this->assertTrue(penalty_exemption::is_user_exempt($student->id, $assignctx->id));
        groups_remove_member($group1, $student);

        groups_add_member($group2, $student);
        $this->assertFalse(penalty_exemption::is_user_exempt($student->id, $sitectx->id));
        $this->assertTrue(penalty_exemption::is_user_exempt($student->id, $coursectx->id));
        $this->assertTrue(penalty_exemption::is_user_exempt($student->id, $assignctx->id));
        groups_remove_member($group2, $student);

        groups_add_member($group3, $student);
        $this->assertFalse(penalty_exemption::is_user_exempt($student->id, $sitectx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($student->id, $coursectx->id));
        $this->assertTrue(penalty_exemption::is_user_exempt($student->id, $assignctx->id));
    }

    /**
     * Test backup and restore for penalty exemptions.
     *
     * @return void
     */
    public function test_backup_restore(): void {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $coursectx = context_course::instance($course->id);
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $assignctx = context_module::instance($assign->cmid);
        $user = $this->getDataGenerator()->create_user();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Course group']);

        // Create user and group exemptions.
        $reasonformat = FORMAT_PLAIN;
        $this->assertEquals(0, penalty_exemption::count_by([]));
        penalty_exemption::exempt_user($user->id, $coursectx->id, "User exemption in course context", $reasonformat);
        penalty_exemption::exempt_user($user->id, $assignctx->id, "User exemption in module context", $reasonformat);
        penalty_exemption::exempt_group($group->id, $coursectx->id, "Group exemption in course context", $reasonformat);
        penalty_exemption::exempt_group($group->id, $assignctx->id, "Group exemption in module context", $reasonformat);
        $this->assertEquals(4, penalty_exemption::count_by([]));

        // Backup and restore the course.
        $backupid = $this->backup_course($course);
        $newcourseid = $this->restore_course($backupid);
        $newcoursectx = context_course::instance($newcourseid);
        $newgroupid = groups_get_group_by_name($newcourseid, $group->name);
        $modules = get_coursemodules_in_course('assign', $newcourseid);
        $this->assertCount(1, $modules);
        $newassignctx = context_module::instance(reset($modules)->id);

        // Verify all exemptions have been restored.
        $this->assertEquals(8, penalty_exemption::count_by([]));
        $this->assertEquals(1, penalty_exemption::count_by([
            'itemtype' => penalty_exemption::TYPE_USER,
            'itemid' => $user->id,
            'contextid' => $newcoursectx->id,
        ]));
        $this->assertEquals(1, penalty_exemption::count_by([
            'itemtype' => penalty_exemption::TYPE_USER,
            'itemid' => $user->id,
            'contextid' => $newassignctx->id,
        ]));
        $this->assertEquals(1, penalty_exemption::count_by([
            'itemtype' => penalty_exemption::TYPE_GROUP,
            'itemid' => $newgroupid,
            'contextid' => $newcoursectx->id,
        ]));
        $this->assertEquals(1, penalty_exemption::count_by([
            'itemtype' => penalty_exemption::TYPE_GROUP,
            'itemid' => $newgroupid,
            'contextid' => $newassignctx->id,
        ]));
    }

    /**
     * Makes a backup of the course.
     *
     * @param \stdClass $course The course object.
     * @return string Unique identifier for this backup.
     */
    protected function backup_course(\stdClass $course): string {
        global $CFG, $USER;

        // Disable file logging.
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );

        $this->assertTrue($bc->get_plan()->setting_exists('users'));

        // Set the backup plan to include users.
        $setting = $bc->get_plan()->get_setting('users');
        $setting->set_status(base_setting::NOT_LOCKED);
        $setting->set_value(1);

        // Execute the backup plan and return the backup id.
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        return $backupid;
    }

    /**
     * Restores a backup that has been made earlier.
     *
     * @param string $backupid The unique identifier of the backup.
     * @return int The new course id.
     */
    protected function restore_course(string $backupid): int {
        global $CFG, $DB, $USER;

        // Disable file logging.
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $defaultcategoryid = $DB->get_field('course_categories', 'id', ['parent' => 0], IGNORE_MULTIPLE);

        $newcourseid = restore_dbops::create_new_course('restored_course', 'restored_course', $defaultcategoryid);
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );

        // Execute the restore plan and return the new course id.
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
