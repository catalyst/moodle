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

/**
 * Migrate legacy Cloze files from child answer areas to the parent questiontext file area.
 *
 * Historically, files embedded in multichoice subquestions were stored within the child
 * question itself. Modern versions consolidate these files into the parent question.
 *
 * Filenames must be unique within the new parent questiontext area. If a single
 * question contains identically named but differing files across its child areas,
 * only the first unique filename is preserved. As a result, the affected question
 * may inadvertently render a duplicated file in place of the intended one.
 *
 * @package     qtype_multianswer
 * @copyright   2026 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class copy_legacy_answer_files extends \core\task\adhoc_task {
    #[\Override]
    public function execute() {
        $customdata = (array) $this->get_custom_data();
        $questionids = $customdata['questionids'] ?? [];

        self::log_legacy_file_collisions($questionids);
        $copied = self::copy_legacy_answer_files_to_questiontext($questionids);
        mtrace("Copied {$copied} legacy Cloze answer files to parent questiontext areas.");
    }

    /**
     * Log legacy file paths that cannot represent all source content in the parent area.
     *
     * The parent questiontext area can only contain one file for a given path and filename.
     * When legacy child areas contain different content at the same target path, the copy
     * operation deterministically preserves one source file.
     *
     * @param array $questionids Optionally limit the query to specific questions for performance.
     * @return void
     */
    private static function log_legacy_file_collisions(array $questionids = []): void {
        global $DB;

        if (!empty($questionids)) {
            [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
            $questionjoin = "AND parent.id {$insql}";
        } else {
            $params = [];
            $questionjoin = '';
        }

        $sql = "SELECT parent.id AS parentid, f.filepath, f.filename
                  FROM {files} f
                  JOIN {question_answers} qa ON qa.id = f.itemid
                  JOIN {question} child ON child.id = qa.question
                  JOIN {question} parent ON parent.id = child.parent
                 WHERE f.component = 'question'
                       AND f.filearea IN ('answer', 'answerfeedback')
                       AND f.filename <> '.'
                       AND parent.qtype = 'multianswer'
                       {$questionjoin}
              GROUP BY parent.id, f.filepath, f.filename
                HAVING COUNT(DISTINCT f.contenthash) > 1
              ORDER BY parent.id, f.filepath, f.filename";

        $collisions = $DB->get_recordset_sql($sql, $params);
        try {
            foreach ($collisions as $collision) {
                mtrace("Legacy Cloze file collision for question {$collision->parentid}: " .
                    "{$collision->filepath}{$collision->filename}; only one source file can be copied.");
            }
        } finally {
            $collisions->close();
        }
    }

    /**
     * Copy legacy files from embedded subquestion answer areas to the parent Cloze questiontext area.
     * This process is immutable and idempotent as questions and files are not modified.
     *
     * @param array $questionids Optionally limit the query to specific questions for performance.
     * @return int The number of files copied.
     */
    public static function copy_legacy_answer_files_to_questiontext(array $questionids = []): int {
        global $DB;

        if (!empty($questionids)) {
            [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
            $questionjoin = "AND parent.id {$insql}";
        } else {
            $params = [];
            $questionjoin = '';
        }

        // NOT EXISTS excludes already migrated and colliding filenames.
        // GROUP BY deduplicates cases where answer and answerfeedback both carry the same filename.
        $sql = "SELECT MIN(f.id) AS fileid,
                       f.filepath,
                       f.filename,
                       parent.id AS parentid,
                       parentcontext.contextid AS parentcontextid
                  FROM {files} f
                  JOIN {question_answers} qa ON qa.id = f.itemid
                  JOIN {question} child ON child.id = qa.question
                  JOIN {question} parent ON parent.id = child.parent
                  JOIN {question_versions} parentversion ON parentversion.questionid = parent.id
                  JOIN {question_bank_entries} parententry ON parententry.id = parentversion.questionbankentryid
                  JOIN {question_categories} parentcontext ON parentcontext.id = parententry.questioncategoryid
                 WHERE f.component = 'question'
                       AND f.filearea IN ('answer', 'answerfeedback')
                       AND f.filename <> '.'
                       AND parent.qtype = 'multianswer'
                       {$questionjoin}
                       AND NOT EXISTS (
                           SELECT 1
                             FROM {files} target
                            WHERE target.contextid = parentcontext.contextid
                                  AND target.component = 'question'
                                  AND target.filearea = 'questiontext'
                                  AND target.itemid = parent.id
                                  AND target.filepath = f.filepath
                                  AND target.filename = f.filename
                       )
              GROUP BY f.filepath, f.filename, parent.id, parentcontext.contextid
              ORDER BY f.filepath, f.filename, parent.id";

        $fs = get_file_storage();
        $copied = 0;
        $files = $DB->get_recordset_sql($sql, $params);
        try {
            foreach ($files as $filedata) {
                $file = $fs->get_file_by_id($filedata->fileid);
                if (!$file) {
                    continue;
                }
                $fs->create_file_from_storedfile([
                    'contextid' => $filedata->parentcontextid,
                    'component' => 'question',
                    'filearea' => 'questiontext',
                    'itemid' => $filedata->parentid,
                    'filepath' => $filedata->filepath,
                    'filename' => $filedata->filename,
                ], $file);
                $copied++;
            }
        } finally {
            $files->close();
        }

        return $copied;
    }
}
