<?php

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/comment/lib.php');

list ($options, $others) = cli_get_params(array());

$id = array_shift($others);

$cm = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

$USER = get_admin();

$fs = get_file_storage();
$submissionitemid = file_get_unused_draft_itemid();

$filerec = new stdClass();
$filerec->contextid = context_user::instance($USER->id)->id;
$filerec->component = 'user';
$filerec->filearea = 'draft';
$filerec->itemid = $submissionitemid;
$filerec->filepath = '/';
$filerec->filename = 'sample.txt';
$fs->create_file_from_string($filerec, "Sample file content");

$filerec->filename = 'sample2.txt';
$fs->create_file_from_string($filerec, "More sample file content");


$assign = new assign($context, $cm, $course);
$feedbackcomments = $assign->get_feedback_plugin_by_type('comments');
$feedbackfile = $assign->get_feedback_plugin_by_type('file');
$users = get_enrolled_users($assign->get_context(), 'mod/assign:submit', 0, 'u.*', null, null, null, false);

cli_heading('Generating submissions for ' . count($users) . ' users');

foreach ($users as $user) {
    \core_php_time_limit::raise(0);

    $submission = $assign->get_user_submission($user->id, true);
    if ($submission->status == ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
        echo 'x';
        continue;
    }

    $data = new stdClass();
    $data->onlinetext_editor = array(
        'text' => '<p>Online text sample</p>',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    );
    $data->files_filemanager = $submissionitemid;
    $data->id = $id;
    $data->action = 'savesubmission';
    $data->userid = $user->id;
    $data->submitbutton = '';

    $notices = array();
    if (!$assign->save_submission($data, $notices)) {
        throw new coding_exception('error saving submission');
    }

    $cmt = new stdClass;
    $cmt->contextid = $context->id;
    $cmt->courseid  = $course->id;
    $cmt->cm        = $cm;
    $cmt->area      = 'submission_comments';
    $cmt->itemid    = $submission->id;
    $cmt->component = 'assignsubmission_comments';
    $comment = new comment($cmt);
    if ($comment->count() < 1) {
        $comment->add('Submission comment');
    }

    $data = new stdClass();
    $data->submissionstatement = 1;
    $data->userid = $user->id;
    $assign->submit_for_grading($data, $notices);

    $grade = $assign->get_user_grade($user->id, true);

    $data = new stdClass();
    $data->assignfeedbackcomments_editor = array(
        'text' => '<p>Feedback text</p>',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    );
    $feedbackcomments->save($grade, $data);

    if ($fs->is_area_empty($context->id, 'assignfeedback_file', ASSIGNFEEDBACK_FILE_FILEAREA, $grade->id)) {
        $filerec = new stdClass();
        $filerec->contextid = $context->id;
        $filerec->component = 'assignfeedback_file';
        $filerec->filearea = ASSIGNFEEDBACK_FILE_FILEAREA;
        $filerec->itemid = $grade->id;
        $filerec->filepath = '/';
        $filerec->filename = 'feedback.txt';
        $fs->create_file_from_string($filerec, "Sample feedback file content");

        $feedbackfile->update_file_count($grade);
    }

    echo '.';
}
echo "\n";
