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
 * print the single entries
 *
 * @author  Fumi.Iseki
 * @license GNU Public License
 * @package mod_apply (modified from mod_feedback that by Andreas Grabs)
 */

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->libdir.'/tablelib.php');


////////////////////////////////////////////////////////
//get the params
$id		  = required_param('id', PARAM_INT);
$user_id  = optional_param('user_id', false, PARAM_INT);
$do_show  = required_param('do_show', PARAM_ALPHAEXT);
$perpage  = optional_param('perpage', APPLY_DEFAULT_PAGE_COUNT, PARAM_INT);  // how many per page
$show_all = optional_param('show_all', false, PARAM_INT);  // should we show all users
$courseid = optional_param('courseid', 0, PARAM_INT);

$current_tab = $do_show;


////////////////////////////////////////////////////////
//get the objects
if (! $cm = get_coursemodule_from_id('apply', $id)) {
	print_error('invalidcoursemodule');
}
if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
	print_error('coursemisconf');
}
if (! $apply = $DB->get_record("apply", array("id"=>$cm->instance))) {
	print_error('invalidcoursemodule');
}
if (!$courseid) $courseid = $course->id;

$url = new moodle_url('/mod/apply/show_entries.php', array('id'=>$cm->id, 'do_show'=>$do_show));
$PAGE->set_url($url);

$context = context_module::instance($cm->id);


// Check
require_login($course, true, $cm);

$formdata = data_submitted();
if ($formdata) {
	if (!confirm_sesskey()) {
		print_error('invalidsesskey');
	}
	if ($user_id) {
		$formdata->user_id = intval($user_id);
	}
}

require_capability('mod/apply:viewreports', $context);


////////////////////////////////////////////////////////
//get the responses of given user
if ($do_show=='show_one_entry') {
	$params = array('apply_id'=>$apply->id, 'user_id'=>$user_id);
	$apply_items   = $DB->get_records('apply_item', array('apply_id'=>$apply->id), 'position');
	$apply_submits = $DB->get_records('apply_submit', $params); 
}

/// Print the page header
$strapplys = get_string('modulenameplural', 'apply');
$strapply  = get_string('modulename', 'apply');

$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(format_string($apply->name));
echo $OUTPUT->header();

require('tabs.php');


///////////////////////////////////////////////////////////////////////////
/// Print the main part of the page

////////////////////////////////////////////////////////
/// Print the links to get responses and analysis
if ($do_show=='show_entries') {
	if (has_capability('mod/apply:viewreports', $context)) {
		// preparing the table for output
		$baseurl = new moodle_url('/mod/apply/show_entries.php');
		$baseurl->params(array('id'=>$id, 'do_show'=>$do_show, 'show_all'=>$show_all));

		$table_columns = array('userpic', 'fullname', 'title', 'time_modified', 'version', 'acked', 'applied', 'canceled', 'checkbox');
		$table_headers = array(get_string('userpic'), get_string('fullnameuser'), 'Title', get_string('date'), 'ver.',
							  'acked', 'applied', 'canceled', 'check');

		if (has_capability('mod/apply:deletesubmissions', $context)) {
			$table_columns[] = 'delete_entry';
			$table_headers[] = '';
		}

		$table = new flexible_table('apply-show_entry-list-'.$course->id);

		$table->define_columns($table_columns);
		$table->define_headers($table_headers);
		$table->define_baseurl($baseurl);

		$table->sortable(true, 'lastname', SORT_DESC);
		$table->set_attribute('cellspacing', '0');
		$table->set_attribute('id', 'show_entrytable');
		$table->set_attribute('class', 'generaltable generalbox');
		$table->set_control_variables(array(
					TABLE_VAR_SORT  => 'ssort',
					TABLE_VAR_IFIRST=> 'sifirst',
					TABLE_VAR_ILAST => 'silast',
					TABLE_VAR_PAGE	=> 'spage'
					));
		$table->setup();

		if ($table->get_sql_sort()) $sort = $table->get_sql_sort();
		else 						$sort = '';

		list($where, $params) = $table->get_sql_where();
		if ($where) $where .= ' AND';

		$matchcount = apply_get_submit_users_count($cm);
		$table->initialbars(true);

		if ($show_all) {
			$startpage = false;
			$pagecount = false;
		}
		else {
			$table->pagesize($perpage, $matchcount);
			$startpage = $table->get_page_start();
			$pagecount = $table->get_page_size();
		}
		//
		$students = apply_get_submit_users($cm, $where, $params, $sort, $startpage, $pagecount);

		echo $OUTPUT->box_start('mdl-align');
		echo "XXXXXXXXXXXXXXXXXXXXXXXXXXXXXX";
		echo $OUTPUT->box_end();
	}

	//####### viewreports-start
	if (has_capability('mod/apply:viewreports', $context)) {

		//print the list of students
		echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
		echo isset($groupselect) ? $groupselect : '';
		echo '<div class="clearer"></div>';
		echo $OUTPUT->box_start('mdl-align');

		if (!$students) {
			$table->print_html();
		} 
		else {
			foreach ($students as $student) {
				$params = array('user_id'=>$student->id, 'apply_id'=>$apply->id);
				$submit_count = $DB->count_records('apply_submit', $params);

				if ($submit_count>0) {
					//userpicture and link to the profilepage
					$fullname_url = $CFG->wwwroot.'/user/view.php?id='.$student->id.'&amp;course='.$course->id;
					$profilelink = '<strong><a href="'.$fullname_url.'">'.fullname($student).'</a></strong>';
					$data = array ($OUTPUT->user_picture($student, array('courseid'=>$course->id)), $profilelink);

					//link to the entry of the user
					$params  = array('apply_id'=>$apply->id, 'user_id'=>$student->id);
					$submits = $DB->get_record('apply_submit', $params);
					$show_entry_url_params = array('user_id'=>$student->id, 'do_show'=>'show_one_entry');
					$show_entry_url = new moodle_url($url, $show_entry_url_params);
					$show_entry_link = '<a href="'.$show_entry_url->out().'">'.userdate($submit->time_modified).'</a>';
					$data[] = $show_entry_link;

					//link to delete the entry
					if (has_capability('mod/apply:deletesubmissions', $context)) {
						$delete_url_params = array('id'=>$cm->id, 'completedid'=>$applycompleted->id, 'do_show'=>'show_one_entry');

						$deleteentry_url = new moodle_url($CFG->wwwroot.'/mod/apply/delete_completed.php', $delete_url_params);
						$deleteentry_link = '<a href="'.$deleteentry_url->out().'">'.get_string('delete_entry', 'apply').'</a>';
						$data[] = $deleteentry_link;
					}
					$table->add_data($data);
				}
			}
			$table->print_html();

			$allurl = new moodle_url($baseurl);

			if ($show_all) {
				$allurl->param('show_all', 0);
				echo $OUTPUT->container(html_writer::link($allurl, get_string('showperpage', '', APPLY_DEFAULT_PAGE_COUNT)), array(), 'show_all');

			}
			else if ($matchcount > 0 && $perpage < $matchcount) {
				$allurl->param('show_all', 1);
				echo $OUTPUT->container(html_writer::link($allurl, get_string('show_all', '', $matchcount)), array(), 'show_all');
			}
		}

		echo $OUTPUT->box_end();
		echo $OUTPUT->box_end();
	}

}


////////////////////////////////////////////////////////
/// Print the responses of the given user
if ($do_show=='show_one_entry') {
	echo $OUTPUT->heading(format_text($apply->name));

	//print the items
	if (is_array($applyitems)) {
		$align = right_to_left() ? 'right' : 'left';
		$usr = $DB->get_record('user', array('id'=>$user_id));

		if ($applycompleted) {
			echo $OUTPUT->heading(userdate($applycompleted->time_modified).' ('.fullname($usr).')', 3);
		}
		else {
			echo $OUTPUT->heading(get_string('not_completed_yet', 'apply'), 3);
		}

		echo $OUTPUT->box_start('apply_items');
		$itemnr = 0;
		foreach ($applyitems as $applyitem) {
			//get the values
			$params = array('submit_id'=>$applycompleted->id, 'item_id'=>$applyitem->id);
			$value = $DB->get_record('apply_value', $params);
			echo $OUTPUT->box_start('apply_item_box_'.$align);
			if ($applyitem->hasvalue==1) {
				$itemnr++;
				echo $OUTPUT->box_start('apply_item_number_'.$align);
				echo $itemnr;
				echo $OUTPUT->box_end();
			}

			if ($applyitem->typ != 'pagebreak') {
				echo $OUTPUT->box_start('box generalbox boxalign_'.$align);
				if (isset($value->value)) {
					apply_print_item_show_value($applyitem, $value->value);
				}
				else {
					apply_print_item_show_value($applyitem, false);
				}
				echo $OUTPUT->box_end();
			}
			echo $OUTPUT->box_end();
		}
		echo $OUTPUT->box_end();
	}
	echo $OUTPUT->continue_button(new moodle_url($url, array('do_show'=>'show_entries')));
}


///////////////////////////////////////////////////////////////////////////
/// Finish the page
echo $OUTPUT->footer();

