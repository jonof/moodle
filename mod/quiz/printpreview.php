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
 * This script displays all questions of a quiz in a form more suitable for printing.
 *
 * @package   mod_quiz
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

// Get submitted parameters.
$attemptid = required_param('attempt', PARAM_INT);

$attemptobj = quiz_attempt::create($attemptid);
$PAGE->set_url($attemptobj->printpreview_url());

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
    if ($attemptobj->has_capability('mod/quiz:viewreports')) {
        redirect($attemptobj->review_url());
    } else {
        throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'notyourattempt');
    }
}

// Check capabilities and block settings.
if (!$attemptobj->is_preview_user()) {
    $attemptobj->require_capability('mod/quiz:attempt');
}

// Check the access rules.
$accessmanager = $attemptobj->get_access_manager(time());
$accessmanager->setup_attempt_page($PAGE);
$output = $PAGE->get_renderer('mod_quiz');
$messages = $accessmanager->prevent_access();
if (!$attemptobj->is_preview_user() && $messages) {
    print_error('attempterror', 'quiz', $attemptobj->view_url(),
            $output->access_messages($messages));
}
if ($accessmanager->is_preflight_check_required($attemptobj->get_attemptid())) {
    redirect($attemptobj->start_attempt_url(null, $page));
}

// Check that the navigation method allows previewing for print, or that the user can preview.
if ($attemptobj->get_navigation_method() != QUIZ_NAVMETHOD_FREE && !$attemptobj->is_preview_user()) {
    print_error('printpreviewnavmethodwrong', 'quiz');
}

// Log this page view.
add_to_log($attemptobj->get_courseid(), 'quiz', 'print preview',
        'printpreview.php?attempt=' . $attemptobj->get_attemptid(),
        $attemptobj->get_quizid(), $attemptobj->get_cmid());

// Load the questions and states needed by this page.
$slots = $attemptobj->get_slots();

// Check.
if (empty($slots)) {
    throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'noquestionsfound');
}

$PAGE->set_title(format_string($attemptobj->get_quiz_name()));
$PAGE->set_heading($attemptobj->get_course()->fullname);
$PAGE->set_pagelayout('print');

echo $output->header();
echo $output->heading($attemptobj->get_quiz_name());

if ($attemptobj->get_navigation_method() != QUIZ_NAVMETHOD_FREE) {
    echo $output->notification(get_string('printpreviewnavmethodwrong', 'quiz'));
}
echo $output->single_button($attemptobj->attempt_url(), get_string('closepreview', 'quiz'), 'get', array('class' => 'close-preview'));

// Print all the questions.
foreach ($slots as $slot) {
    echo $attemptobj->render_question_for_print($slot, true);
}

echo $output->footer();
