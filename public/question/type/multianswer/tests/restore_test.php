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

namespace qtype_multianswer;

use backup;
use backup_controller;
use core\task\manager;
use html_writer;
use qtype_multianswer\task\copy_legacy_answer_files;
use question_bank;
use restore_controller;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Unit tests for restore behaviour of the multianswer question type.
 *
 * @package   qtype_multianswer
 * @copyright 2025 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Mark Johnson <mark.johnson@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \restore_qtype_multianswer_plugin
 * @covers \qtype_multianswer\task\copy_legacy_answer_files
 */
final class restore_test extends \advanced_testcase {
    /**
     * Duplicate a quiz containing a multianswer question with no multianswer record.
     */
    public function test_restore_quiz_with_edited_questions(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course and a user with editing teacher capabilities.
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $qbank = $generator->get_plugin_generator('mod_qbank')->create_instance(['course' => $course1->id]);
        $context = \context_module::instance($qbank->cmid);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $initialcount = $DB->count_records('question');

        // Create a question category.
        $cat = $questiongenerator->create_question_category(['contextid' => $context->id]);

        // Create a quiz containing a multianswer question from the qbank.
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance(['course' => $course1->id]);
        $question = $questiongenerator->create_question('multianswer', 'twosubq', ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz);

        // Delete the multianswer record.
        $DB->delete_records('question_multianswer', ['question' => $question->id]);

        // Confirm we have created 3 additional questions (one parent, 2 children).
        $this->assertEquals($initialcount + 3, $DB->count_records('question'));

        // Backup quiz.
        $bc = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $quiz->cmid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Restore the backup into the same course.
        $rc = new \restore_controller(
            $backupid,
            $course1->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_CURRENT_ADDING,
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Both quizzes should refer to the same original question.
        $quizzes = get_fast_modinfo($course1->id)->get_instances_of('quiz');
        $this->assertCount(2, $quizzes);
        foreach ($quizzes as $quiz) {
            $structure = \mod_quiz\question\bank\qbank_helper::get_question_structure($quiz->instance, $quiz->context);
            $this->assertEquals($structure[1]->questionid, $question->id);
        }

        // There should be no additional questions created during the restore.
        $this->assertEquals($initialcount + 3, $DB->count_records('question'));
    }

    /**
     * Test legacy answer area files in multianswer question are migrated during restore.
     */
    public function test_restore_queues_legacy_files_migration_task(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $questiongenerator = $generator->get_plugin_generator('core_question');

        // Add the question to a quiz to ensure it is included directly within the backup scope.
        $quiz = $generator->get_plugin_generator('mod_quiz')->create_instance(['course' => $course1->id]);
        $quizcontext = \context_module::instance($quiz->cmid);

        // Create a question category and multianswer question in the quiz context so a module duplicate copies the question.
        $cat = $questiongenerator->create_question_category(['contextid' => $quizcontext->id]);
        $question = $questiongenerator->create_question('multianswer', 'twosubq', ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz);

        $questiondata = question_bank::load_question_data($question->id);

        // Inject dummy file into legacy answer file area (simulating a pre-patch backup source).
        $subquestion = end($questiondata->options->questions);
        $answer = reset($subquestion->options->answers);
        $answer->answer = html_writer::img('@@PLUGINFILE@@/legacy.png', 'Legacy');
        $DB->update_record('question_answers', $answer);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $questiondata->contextid,
            'component' => 'question',
            'filearea' => 'answer',
            'itemid' => $answer->id,
            'filepath' => '/',
            'filename' => 'legacy.png',
        ], 'answer image contents');

        // Confirm the source legacy file exists only in the child answer area before restore.
        $this->assertTrue($fs->file_exists(
            $questiondata->contextid,
            'question',
            'answer',
            $answer->id,
            '/',
            'legacy.png'
        ));
        $this->assertFalse($fs->file_exists(
            $questiondata->contextid,
            'question',
            'questiontext',
            $question->id,
            '/',
            'legacy.png'
        ));

        // Clear adhoc tasks queue to ensure a clean slate before backup/restore steps.
        $DB->delete_records('task_adhoc');
        $this->assertEmpty($DB->get_records('task_adhoc'));

        // Duplicate the quiz, duplicating its context and therefore restoring the legacy question.
        duplicate_module($course1, get_fast_modinfo($course1)->get_cm($quiz->cmid));

        // The question table should now possess both the original question and one restored copy.
        $restoredquestions = $DB->get_records('question', ['qtype' => 'multianswer']);
        $this->assertCount(2, $restoredquestions);
        $restoredquestion = array_values(
            array_filter(
                $restoredquestions,
                fn($questionrecord) => (int) $questionrecord->id !== (int) $question->id
            )
        );
        $this->assertCount(1, $restoredquestion);
        $newquestion = reset($restoredquestion);
        $newquestiondata = question_bank::load_question_data($newquestion->id);

        // Before the adhoc task runs, restored legacy files should still only be in child answer areas.
        $restoredsubquestion = end($newquestiondata->options->questions);
        $restoredanswer = reset($restoredsubquestion->options->answers);
        $this->assertTrue($fs->file_exists(
            $newquestiondata->contextid,
            'question',
            'answer',
            $restoredanswer->id,
            '/',
            'legacy.png'
        ));
        $this->assertFalse($fs->file_exists(
            $newquestiondata->contextid,
            'question',
            'questiontext',
            $newquestion->id,
            '/',
            'legacy.png'
        ));

        // Verify restore queued the migration task and run it.
        $task = manager::get_next_adhoc_task(
            time(),
            true,
            copy_legacy_answer_files::class,
        );
        $this->assertInstanceOf(copy_legacy_answer_files::class, $task);

        // Assert that expected migration output is produced.
        $this->expectOutputRegex('~Copied \d+ legacy Cloze answer files to parent questiontext areas~');

        $taskid = $task->get_id();
        $task->execute();
        manager::adhoc_task_complete($task);
        $this->assertFalse($DB->record_exists('task_adhoc', ['id' => $taskid]));

        // Verify the target restored question had its child files fully migrated to the parent area.
        $this->assertTrue($fs->file_exists(
            $newquestiondata->contextid,
            'question',
            'questiontext',
            $newquestion->id,
            '/',
            'legacy.png'
        ));
    }
}
