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
 * prints the form so the user can fill out the apply
 *
 * @package apply
 * @author  Fumi.Iseki
 * @license GNU Public License
 * @attention modified from mod_feedback that by Andreas Grabs
 */

require_once('../../config.php');
require_once('lib.php');

apply_init_session();

$id 			= required_param('id', PARAM_INT);
$courseid 	  	= optional_param('courseid', 0, PARAM_INT);
$submit_id		= optional_param('submit_id', 0, PARAM_INT);
$prev_values 	= optional_param('prev_values', 0, PARAM_INT);
$go_page 		= optional_param('go_page', -1, PARAM_INT);
$last_page 	  	= optional_param('last_page', false, PARAM_INT);
$start_itempos	= optional_param('start_itempos', 0, PARAM_INT);
$last_itempos  	= optional_param('last_itempos',  0, PARAM_INT);

$highlightrequired = false;

if (($formdata = data_submitted()) and !confirm_sesskey()) {
	print_error('invalidsesskey');
}

// Page
if ( isset($formdata->sesskey)	  	and
	!isset($formdata->save_values) 	and
	!isset($formdata->go_next_page) and
	!isset($formdata->go_prev_page) and
	 isset($formdata->last_page)) {

	$go_page = $formdata->last_page;
}

if (isset($formdata->save_values)) {
	$save_values = true;
} 
else {
	$save_values = false;
}

// page
if ($go_page<0 AND !$save_values) {
	if (isset($formdata->go_next_page)) {
		$go_page = $last_page + 1;
		$go_next_page = true;
		$go_prev_page = false;
	}
	else if (isset($formdata->go_prev_page)) {
		$go_page = $last_page - 1;
		$go_next_page = false;
		$go_prev_page = true;
	}
	else {
		print_error('missingparameter');
	}
}
else {
	$go_next_page = $go_prev_page = false;
}


//
if (! $cm = get_coursemodule_from_id('apply', $id)) {
	print_error('invalidcoursemodule');
}
if (! $course = $DB->get_record('course', array('id'=>$cm->course))) {
	print_error('coursemisconf');
}
if (! $apply  = $DB->get_record('apply', array('id'=>$cm->instance))) {
	print_error('invalidcoursemodule');
}
if (!$courseid) $courseid = $course->id;

$context = context_module::instance($cm->id);


//
if (!has_capability('mod/apply:submit', $context)) {
	apply_print_error_messagebox('apply_is_disable', $courseid);
	exit;
}

//
require_login($course, true, $cm);


/// Print the page header
$strapplys = get_string('modulename_plural', 'apply');
$strapply  = get_string('modulename', 'apply');

$PAGE->navbar->add(get_string('apply:submit', 'apply'));
$urlparams = array('id'=>$cm->id, 'go_page'=>$go_page, 'courseid'=>$courseid);
$PAGE->set_url('/mod/apply/submit.php', $urlparams);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(format_string($apply->name));
echo $OUTPUT->header();


/////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Check
if ((empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context))) {
	notice(get_string("activityiscurrentlyhidden"));
}

if (!$apply->multiple_submit) {
	if (apply_get_valid_submits_count($apply->id, $USER->id)>0) {
		apply_print_error_messagebox('apply_is_already_submitted', $courseid);
		exit;
	}
}

$checktime = time();
$apply_is_not_open = $apply->time_open>$checktime;
$apply_is_closed   = $apply->time_close<$checktime and $apply->time_close>0;
if ($apply_is_not_open or $apply_is_closed) {
	if ($apply_is_not_open) apply_print_error_messagbox('apply_is_not_open', $courseid);
	else 					apply_print_error_messagbox('apply_is_closed',   $courseid);
	exit;
}

// first time view
if (!$SESSION->apply->is_started) {
	$itemscount = $DB->count_records('apply_item', array('apply_id'=>$apply->id, 'hasvalue'=>1));
	if ($itemscount<=0) {
		apply_print_error_messagebox('apply_is_not_ready', $courseid);
		exit;
	}
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
if ($prev_values==1) {
	//if (!isset($SESSION->apply->is_started) or !$SESSION->apply->is_started==true) {
	if (!$SESSION->apply->is_started) {
		print_error('error', '', $CFG->wwwroot.'/course/view.php?id='.$courseid);
	}

	if (apply_check_values($start_itempos, $last_itempos)) {
		$user_id   = $USER->id;
		$submit_id = apply_save_draft_values($apply->id, $submit_id, $user_id);	// save to draft

		if ($submit_id) {
			if ($user_id>0) {
				add_to_log($course->id, 'apply', 'start_apply', 'view.php?id='.$cm->id, $apply->id, $cm->id, $user_id);
			}
			if (!$go_next_page and !$go_prev_page) {
				$prev_values = false;
			}
		}
		else {
			$save_return = 'failed';
			if (isset($last_page)) {
				$go_page = $last_page;
			}
			else {
				print_error('missingparameter');
			}
		}
	}
	//
	else {
		$save_return = 'missing';
		$highlightrequired = true;
		if (isset($last_page)) {
			$go_page = $last_page;
		}
		else {
			print_error('missingparameter');
		}
	}
}

//saving the items
if ($save_values and !$prev_values) {
	//
	$user_id   = $USER->id; 
	$submit_id = apply_save_draft_values($apply->id, $submit_id, $user_id);
	apply_exec_submit($submit_id);

	if ($submit_id) {
		$save_return = 'saved';
		add_to_log($course->id, 'apply', 'submit', 'view.php?id='.$cm->id, $apply->id, $cm->id, $user_id);
		apply_send_email($cm, $apply, $course, $user_id);
	}
	else {
		$save_return = 'failed';
	}
}

//
if ($allbreaks = apply_get_all_break_positions($apply->id)) {
	if ($go_page<=0) {
		$start_position = 0;
	}
	else {
		if (!isset($allbreaks[$go_page-1])) $go_page = count($allbreaks);
		$start_position = $allbreaks[$go_page-1];
	}
	$is_pagebreak = true;
} 
else {
	$start_position = 0;
	$newpage = 0;
	$is_pagebreak = false;
}

//
//get the apply_items after the last shown pagebreak
$select = 'apply_id = ? AND position > ?';
$params = array($apply->id, $start_position);
$apply_items = $DB->get_records_select('apply_item', $select, $params, 'position');

//get the first pagebreak
$params = array('apply_id' => $apply->id, 'typ' => 'pagebreak');
if ($pagebreaks = $DB->get_records('apply_item', $params, 'position')) {
	$pagebreaks = array_values($pagebreaks);
	$first_pagebreak = $pagebreaks[0];
}
else {
	$first_pagebreak = false;
}
$max_item_count = $DB->count_records('apply_item', array('apply_id'=>$apply->id));



///////////////////////////////////////////////////////////////////////////
// Print the main part of the page
//
$SESSION->apply->is_started = false;
echo $OUTPUT->heading(format_text($apply->name));

//
if (isset($save_return) && $save_return=='saved') {
	echo '<p align="center">';
	echo '<b><font color="green">';
	echo get_string('entry_saved', 'apply');
	echo '</font></b>';
	echo '</p>';

	$url = $CFG->wwwroot.'/course/view.php?id='.$courseid;
	echo $OUTPUT->continue_button($url);
}

else {
	// Error
	if (isset($save_return)) {
		if ($save_return=='failed') {
 				echo $OUTPUT->box_start('mform error');
			echo get_string('saving_failed', 'apply');
			echo $OUTPUT->box_end();
		}
		else if ($save_return=='missing') {
			echo $OUTPUT->box_start('mform error');
			echo get_string('saving_failed_because_missing_or_false_values', 'apply');
			echo $OUTPUT->box_end();
		}
	}
	//
	if (is_array($apply_items)) {
		//
		require('submit_page.php');
		//
		$SESSION->apply->is_started = true;
	}
}


///////////////////////////////////////////////////////////////////////////
/// Finish the page
echo $OUTPUT->footer();

