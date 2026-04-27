<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace qtype_multianswer\task;

use context_system;
use html_writer;
use question_bank;

/**
 * Unit tests for copy_legacy_answer_files.
 *
 * @package     qtype_multianswer
 * @copyright   2026 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \qtype_multianswer\task\copy_legacy_answer_files
 */
final class copy_legacy_answer_files_test extends \advanced_testcase {
    /**
     * The task copy helper should be immutable, idempotent and collision-safe.
     */
    public function test_copy_legacy_answer_files_to_questiontext(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a test question category and a multianswer question with two subquestions.
        $syscontext = context_system::instance();
        /** @var \core_question_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $syscontext->id]);
        $question = $generator->create_question('multianswer', 'twosubq', ['category' => $category->id]);
        $questiondata = question_bank::load_question_data($question->id);

        // Inject dummy legacy image references into the answer and feedback of a subquestion.
        $subquestion = end($questiondata->options->questions);
        $answer = reset($subquestion->options->answers);
        $answer->answer = html_writer::img('@@PLUGINFILE@@/legacy.png', 'Legacy');
        $answer->feedback = html_writer::img('@@PLUGINFILE@@/legacy.png', 'Feedback');
        $DB->update_record('question_answers', $answer);

        // Create dummy files in the legacy answer and answerfeedback file areas.
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $questiondata->contextid,
            'component' => 'question',
            'filearea' => 'answer',
            'itemid' => $answer->id,
            'filepath' => '/',
            'filename' => 'legacy.png',
        ], 'answer image contents');
        $fs->create_file_from_string([
            'contextid' => $questiondata->contextid,
            'component' => 'question',
            'filearea' => 'answerfeedback',
            'itemid' => $answer->id,
            'filepath' => '/',
            'filename' => 'legacy.png',
        ], 'different feedback image contents');

        // Verify the file does not initially exist in the parent questiontext file area.
        $this->assertFalse($fs->file_exists(
            $questiondata->contextid,
            'question',
            'questiontext',
            $question->id,
            '/',
            'legacy.png'
        ));

        // Verify only one of the dummy files with colliding filenames is copied to the parent questiontext area.
        $copied = copy_legacy_answer_files::copy_legacy_answer_files_to_questiontext();
        $this->assertEquals(1, $copied);
        $this->assertTrue($fs->file_exists(
            $questiondata->contextid,
            'question',
            'questiontext',
            $question->id,
            '/',
            'legacy.png'
        ));

        // Verify that a second run makes no additional copies because the target file already exists.
        $copied = copy_legacy_answer_files::copy_legacy_answer_files_to_questiontext();
        $this->assertEquals(0, $copied);

        // Verify the task reports files with conflicting source content at the same parent target path.
        $this->expectOutputString(
            "Legacy Cloze file collision for question {$question->id}: /legacy.png; only one source file can be copied.\n" .
            "Copied 0 legacy Cloze answer files to parent questiontext areas.\n"
        );
        $task = new copy_legacy_answer_files();
        $task->execute();
    }

    /**
     * The task should copy legacy answer files to the parent questiontext file area.
     */
    public function test_execute(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a test question category and a multianswer question with a single legacy answer image.
        $syscontext = context_system::instance();
        /** @var \core_question_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $syscontext->id]);
        $question = $generator->create_question('multianswer', 'twosubq', ['category' => $category->id]);
        $questiondata = question_bank::load_question_data($question->id);

        $subquestion = end($questiondata->options->questions);
        $answer = reset($subquestion->options->answers);
        $answer->answer = html_writer::img('@@PLUGINFILE@@/legacy.png', 'Legacy');
        $DB->update_record('question_answers', $answer);

        // Create the dummy file in the legacy answer file area.
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $questiondata->contextid,
            'component' => 'question',
            'filearea' => 'answer',
            'itemid' => $answer->id,
            'filepath' => '/',
            'filename' => 'legacy.png',
        ], 'answer image contents');

        // Ensure the file is initially missing from the parent area.
        $this->assertFalse($fs->file_exists(
            $questiondata->contextid,
            'question',
            'questiontext',
            $question->id,
            '/',
            'legacy.png'
        ));

        // Assert that the task reports the number of files it copied.
        $this->expectOutputRegex('~Copied 1 legacy Cloze answer files to parent questiontext areas~');

        // Execute the task and verify it performs the migration end-to-end.
        $task = new copy_legacy_answer_files();
        $task->execute();

        // Verify the file was correctly copied to the parent questiontext file area.
        $this->assertTrue($fs->file_exists(
            $questiondata->contextid,
            'question',
            'questiontext',
            $question->id,
            '/',
            'legacy.png'
        ));
    }

    /**
     * Deleting a Cloze question should delete both retained legacy files and their migrated copies.
     */
    public function test_deleting_question_deletes_legacy_and_migrated_files(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a multianswer question whose second child is a multichoice subquestion.
        $syscontext = context_system::instance();
        /** @var \core_question_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $syscontext->id]);
        $question = $generator->create_question('multianswer', 'twosubq', ['category' => $category->id]);
        $questiondata = question_bank::load_question_data($question->id);
        $subquestion = end($questiondata->options->questions);
        $answer = reset($subquestion->options->answers);

        // Simulate historic files stored in the generated multichoice child answer areas.
        $fs = get_file_storage();
        $files = [
            'answer' => 'legacy-answer.png',
            'answerfeedback' => 'legacy-feedback.png',
        ];
        foreach ($files as $filearea => $filename) {
            $fs->create_file_from_string([
                'contextid' => $questiondata->contextid,
                'component' => 'question',
                'filearea' => $filearea,
                'itemid' => $answer->id,
                'filepath' => '/',
                'filename' => $filename,
            ], "{$filearea} image contents");
        }

        copy_legacy_answer_files::copy_legacy_answer_files_to_questiontext([$question->id]);

        foreach ($files as $filename) {
            $this->assertTrue($fs->file_exists(
                $questiondata->contextid,
                'question',
                'questiontext',
                $question->id,
                '/',
                $filename
            ));
        }

        question_delete_question($question->id);

        foreach ($files as $filearea => $filename) {
            $this->assertFalse($fs->file_exists(
                $questiondata->contextid,
                'question',
                $filearea,
                $answer->id,
                '/',
                $filename
            ));
            $this->assertFalse($fs->file_exists(
                $questiondata->contextid,
                'question',
                'questiontext',
                $question->id,
                '/',
                $filename
            ));
        }
    }
}
