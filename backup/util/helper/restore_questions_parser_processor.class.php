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
 * @package moodlecore
 * @subpackage backup-helper
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/backup/util/xml/parser/processors/grouped_parser_processor.class.php');

/**
 * helper implementation of grouped_parser_processor that will
 * load all the categories and questions (header info only) from the questions.xml file
 * to the backup_ids table storing the whole structure there for later processing.
 * Note: only "needed" categories are loaded (must have question_categoryref record in backup_ids)
 * Note: parentitemid will contain the category->contextid for categories
 * Note: parentitemid will contain the category->id for questions
 *
 * TODO: Complete phpdocs
 */
class restore_questions_parser_processor extends grouped_parser_processor {
    /** @var string XML path in the questions.xml backup file to question categories. */
    protected const CATEGORY_PATH = '/question_categories/question_category';

    /** @var string XML path in the questions.xml backup file to question versions (Moodle 4.0+). */
    protected const QUESTION_VERSIONS_SUBPATH = '/question_bank_entries/question_bank_entry/question_version/question_versions';

    /** @var string XML path in the questions.xml to question elements within question_category (Moodle 4.0+). */
    protected const QUESTION_SUBPATH =
        '/question_bank_entries/question_bank_entry/question_version/question_versions/questions/question';

    /** @var string XML path in the questions.xml to question elements within question_category (before Moodle 4.0). */
    protected const LEGACY_QUESTION_SUBPATH = '/questions/question';

    /** @var string identifies the current restore. */
    protected string $restoreid;

    /** @var int during the restore, this tracks the last category we saw. Any questions we see will be in here. */
    protected int $lastcatid;

    /** @var int During restore, this tracks the last question version seen. */
    protected int $lastversion;

    /** @var int During restore, this tracks the last question id seen for associating answer hashes. */
    protected int $lastquestionid;

    public function __construct($restoreid) {
        $this->restoreid = $restoreid;
        $this->lastcatid = 0;
        $this->lastversion = 1;
        $this->lastquestionid = 0;
        parent::__construct();
        // Set the paths we are interested on
        $this->add_path(self::CATEGORY_PATH);
        $this->add_path(self::CATEGORY_PATH . self::QUESTION_VERSIONS_SUBPATH);
        $this->add_path(self::CATEGORY_PATH . self::QUESTION_SUBPATH);
        $this->add_path(self::CATEGORY_PATH . self::LEGACY_QUESTION_SUBPATH);

        // Snoop on answers within question-type plugins to gauge their textual similarity.
        foreach (\core\plugin_manager::instance()->get_plugins_of_type('qtype') as $qtype) {
            $pluginpath = "/plugin_{$qtype->component}_question/answers";
            $this->add_path(self::CATEGORY_PATH . self::QUESTION_SUBPATH . $pluginpath, true);
            $this->add_path(self::CATEGORY_PATH . self::LEGACY_QUESTION_SUBPATH . $pluginpath, true);
            $this->add_path(self::CATEGORY_PATH . self::QUESTION_SUBPATH . $pluginpath . '/answer');
            $this->add_path(self::CATEGORY_PATH . self::LEGACY_QUESTION_SUBPATH . $pluginpath . '/answer');
        }
    }

    protected function dispatch_chunk($data) {
        if ($data['path'] == self::CATEGORY_PATH . self::QUESTION_VERSIONS_SUBPATH) {
            $this->lastversion = $data['tags']['version'];
            return;
        } else if (fnmatch(self::CATEGORY_PATH . self::QUESTION_SUBPATH . '/plugin_qtype_*_question/answers', $data['path']) ||
                fnmatch(self::CATEGORY_PATH . self::LEGACY_QUESTION_SUBPATH . '/plugin_qtype_*_question/answers', $data['path'])) {
            $hashes = array_map(fn($a) => sha1($a['answertext']), $data['tags']['answer'] ?? []);
            sort($hashes);
            restore_dbops::set_backup_ids_record($this->restoreid, 'question_answerhash',
                $this->lastquestionid, 0, $this->lastcatid, sha1(implode($hashes)));
            return;
        }

        // Prepare question_category record
        if ($data['path'] == self::CATEGORY_PATH) {
            $info     = (object)$data['tags'];
            $itemname = 'question_category';
            $itemid   = $info->id;
            $parentitemid = $info->contextid;
            $this->lastcatid = $itemid;

        // Prepare question record
        } else if ($data['path'] == self::CATEGORY_PATH . self::QUESTION_SUBPATH ||
                $data['path'] == self::CATEGORY_PATH . self::LEGACY_QUESTION_SUBPATH) {
            $info = (object)$data['tags'];
            $info->version = $this->lastversion;
            $itemname = 'question';
            $itemid   = $info->id;
            $parentitemid = $this->lastcatid;
            $this->lastquestionid = $itemid;

        // Not question_category nor question, impossible. Throw exception.
        } else {
            throw new progressive_parser_exception('restore_questions_parser_processor_unexpected_path', $data['path']);
        }

        // Only load it if needed (exist same question_categoryref itemid in table)
        if (restore_dbops::get_backup_ids_record($this->restoreid, 'question_categoryref', $this->lastcatid)) {
            restore_dbops::set_backup_ids_record($this->restoreid, $itemname, $itemid, 0, $parentitemid, $info);
        }
    }

    protected function notify_path_start($path) {
        // nothing to do
    }

    protected function notify_path_end($path) {
        // nothing to do
    }

    /**
     * Provide NULL decoding
     */
    public function process_cdata($cdata) {
        if ($cdata === '$@NULL@$') {
            return null;
        }
        return $cdata;
    }
}
