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
 * the first page to view the apply
 *
 * @author  Fumi Iseki
 * @license GNU Public License
 * @package mod_apply (modified from mod_feedback that by Andreas Grabs)
 */

require_once('../../config.php');
require_once('lib.php');

//
$id = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', false, PARAM_INT);

$current_tab = 'view';

if (! $cm = get_coursemodule_from_id('apply', $id)) {
	print_error('invalidcoursemodule');
}
if (! $course = $DB->get_record('course', array('id'=>$cm->course))) {
	print_error('coursemisconf');
}
if (! $apply = $DB->get_record('apply', array('id'=>$cm->instance))) {
	print_error('invalidcoursemodule');
}
if (!$courseid) $courseid = $course->id;

$context = context_module::instance($cm->id);

$apply_submit_cap = false;
if (has_capability('mod/apply:submit', $context)) {
	$apply_submit_cap = true;
}

//
require_login($course, true, $cm);
add_to_log($course->id, 'apply', 'view', 'view.php?id='.$cm->id, $apply->id, $cm->id);


///////////////////////////////////////////////////////////////////////////
// Print the page header

$strapplys = get_string('modulename_plural', 'apply');
$strapply  = get_string('modulename', 'apply');

$PAGE->set_url('/mod/apply/view.php', array('id'=>$cm->id, 'do_show'=>'view'));
$PAGE->set_title(format_string($apply->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();


$cap_viewhiddenactivities = has_capability('moodle/course:viewhiddenactivities', $context);
if ((empty($cm->visible) and !$cap_viewhiddenactivities)) {
	notice(get_string('activityiscurrentlyhidden'));
}
if ((empty($cm->visible) and !$cap_viewhiddenactivities)) {
	notice(get_string('activityiscurrentlyhidden'));
}


///////////////////////////////////////////////////////////////////////////
// Print the main part of the page

/// print the tabs
require('tabs.php');

$previewimg = $OUTPUT->pix_icon('t/preview', get_string('preview'));
$previewlnk = '<a href="'.$CFG->wwwroot.'/mod/apply/print.php?id='.$id.'">'.$previewimg.'</a>';

echo $OUTPUT->heading(format_text($apply->name.' '.$previewlnk));

//show some infos to the apply
echo $OUTPUT->heading(get_string('description', 'apply'), 4);
echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
echo format_module_intro('apply', $apply, $cm->id);

require('view_info.php');

echo $OUTPUT->box_end();


$SESSION->apply->is_started = false;


///////////////////////////////////////////////////////////////////////////
// Check
if (!$apply_submit_cap) {
	apply_print_error_messagebox('apply_is_disable', $courseid);
	exit;
}

$continue_link = $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id='.$courseid);
//
$apply_can_submit = true;
if ($apply->multiple_submit==0 ) {
	if (apply_get_valid_submits_count($apply->id, $USER->id)>0) {
		$apply_can_submit = false;
		apply_print_messagebox('apply_is_already_submitted', $continue_link);
	}
}

// Date
if ($apply_can_submit) {
	$checktime = time();
	$apply_is_not_open = $apply->time_open>$checktime;
	$apply_is_closed   = $apply->time_close<$checktime and $apply->time_close>0;
	if ($apply_is_not_open or $apply_is_closed) {
    	if ($apply_is_not_open) apply_print_messagebox('apply_is_not_open', $continue_link);
    	else                    apply_print_messagebox('apply_is_closed',   $continue_link);
		$apply_can_submit = false;
	}
}

//
if ($apply_can_submit) {
	$submit_file = 'submit.php';
	$url_params  = array('id'=>$id, 'courseid'=>$courseid, 'go_page'=>0);
	$submit_url  = new moodle_url('/mod/apply/'.$submit_file, $url_params);
	$submit_link = '<div align="center">'.$OUTPUT->single_button($submit_url->out(), get_string('submit_form_button', 'apply')).'</div>';
    apply_print_messagebox('submit_new_apply', $submit_link);
}



///////////////////////////////////////////////////////////////////////////
//

$submits = apply_get_all_submits($apply->id, $USER->id);
if ($submits) {
	echo '<br />';
	echo $OUTPUT->heading(get_string('entries_list_title', 'apply'), 2);
	echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
	foreach ($submits as $submit) {
		echo $submit->id.'<br />';
	}
	echo $OUTPUT->box_end();
}


///////////////////////////////////////////////////////////////////////////
/// Finish the page
echo $OUTPUT->footer();

