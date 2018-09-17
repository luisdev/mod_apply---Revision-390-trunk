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

defined('MOODLE_INTERNAL') OR die('not allowed');
require_once($CFG->dirroot.'/mod/apply/item/apply_item_class.php');

class apply_item_captcha extends apply_item_base
{
    protected $type = "captcha";
    private $commonparams;
    private $item_form = false;
    private $item = false;
    private $apply = false;


    public function init()
    {
    }


    public function build_editform($item, $apply, $cm)
    {
        global $DB;

        $editurl = new moodle_url('/mod/apply/edit.php', array('id'=>$cm->id));

        //ther are no settings for recaptcha
        if (isset($item->id) AND $item->id > 0) {
            notice(get_string('no_settings_captcha', 'apply'), $editurl->out());
            exit;
        }

        //only one recaptcha can be in a apply
        $params = array('apply_id'=>$apply->id, 'typ'=>$this->type);
        if ($DB->record_exists('apply_item', $params)) {
            notice(get_string('only_one_captcha_allowed', 'apply'), $editurl->out());
            exit;
        }

        $this->item  = $item;
        $this->apply = $apply;
        $this->item_form = true; //dummy

        $lastposition = $DB->count_records('apply_item', array('apply_id'=>$apply->id));

        $this->item->apply_id = $apply->id;
        $this->item->template = 0;
        $this->item->name  = get_string('captcha', 'apply');
//      $this->item->label = get_string('captcha', 'apply');
        $this->item->label = APPLY_SUBMIT_ONLY_TAG;
        $this->item->presentation = '';
        $this->item->typ = $this->type;
        $this->item->hasvalue = $this->get_hasvalue();
        $this->item->position = $lastposition + 1;
        $this->item->required = 1;
        $this->item->dependitem = 0;
        $this->item->dependvalue = '';
        $this->item->options = '';
    }


    public function show_editform()
    {
    }


    public function is_cancelled()
    {
        return false;
    }


    public function get_data()
    {
        return true;
    }


    public function save_item()
    {
        global $DB;

        if (!$this->item) {
            return false;
        }

        if (empty($this->item->id)) {
            $this->item->id = $DB->insert_record('apply_item', $this->item);
        } else {
            $DB->update_record('apply_item', $this->item);
        }

        return $DB->get_record('apply_item', array('id'=>$this->item->id));
    }


    //liefert eine Struktur ->name, ->data = array(mit Antworten)
    public function get_analysed($item, $groupid = false, $courseid = false)
    {
        return null;
    }


    public function get_printval($item, $value)
    {
        return '';
    }


    public function print_analysed($item, $itemnr = '', $groupid = false, $courseid = false)
    {
        return $itemnr;
    }


    public function excelprint_item(&$worksheet, $row_offset,
                             $xls_formats, $item,
                             $groupid, $courseid = false)
    {
        return $row_offset;
    }


    /**
     * print the item at the edit-page of apply
     *
     * @global object
     * @param object $item
     * @return void
     */
    public function print_item_preview($item)
    {
        global $DB;

        $align = right_to_left() ? 'right' : 'left';
        $cmid = 0;
        $apply_id = $item->apply_id;
        if ($apply_id>0) {
            $apply = $DB->get_record('apply', array('id'=>$apply_id));
            $cm = get_coursemodule_from_instance('apply', $apply->id, $apply->course);
            if ($cm) {
                $cmid = $cm->id;
            }
        }
        $requiredmark = '<span class="apply_required_mark">*</span>';

        //print the question and label
        $output  = '';
        $output .= '<div class="apply_item_label_'.$align.'">';
        $output .= '('.$item->label.') ';
        $output .= format_text($item->name.$requiredmark, true, false, false);
        $output .= '</div>';
        echo $output;

        apply_open_table_item_tag($output);
        apply_close_table_item_tag();
    }


    /**
     * print the item at the complete-page of apply
     *
     * @global object
     * @param object $item
     * @param string $value
     * @param bool $highlightrequire
     * @return void
     */
    public function print_item_submit($item, $value = '', $highlightrequire = false)
    {
        global $SESSION, $CFG, $DB, $USER;
        global $Table_in;

        require_once($CFG->libdir.'/recaptchalib_v2.php');

        $align = right_to_left() ? 'right' : 'left';
        if ($highlightrequire AND !$this->check_value($value, $item)) {
            $highlight = 'missingrequire';
        } else {
            $highlight = '';
        }

        if (!$Table_in) {
            $requiredmark = '<span class="apply_required_mark">*</span>';
            if (isset($SESSION->apply->captchacheck) AND
                    $SESSION->apply->captchacheck == $USER->sesskey AND
                    $value == $USER->sesskey) {
                //print the question and label
                echo '<div class="apply_item_label_'.$align.'">';
                echo '('.$item->label.') ';
                echo format_text($item->name.$requiredmark, true, false, false);
                $inputname = 'name="'.$item->typ.'_'.$item->id.'"';
                echo '<input type="hidden" value="'.$USER->sesskey.'" '.$inputname.' />';
                echo '</div>';
                return;
            }
        }

        $strincorrectpleasetryagain = get_string('incorrectpleasetryagain', 'auth');
        $strenterthewordsabove = get_string('enterthewordsabove', 'auth');
        $strenterthenumbersyouhear = get_string('enterthenumbersyouhear', 'auth');
        $strgetanothercaptcha = get_string('getanothercaptcha', 'auth');
        $strgetanaudiocaptcha = get_string('getanaudiocaptcha', 'auth');
        $strgetanimagecaptcha = get_string('getanimagecaptcha', 'auth');

        $recaptureoptions = Array('theme'=>'custom', 'custom_theme_widget'=>'recaptcha_widget');

        $html = html_writer::script(js_writer::set_variable('RecaptchaOptions', $recaptureoptions));
        $html .= '
        <div  class="'.$highlight.'" id="recaptcha_widget" style="display:none">
        <div id="recaptcha_image"></div>
        <div class="recaptcha_only_if_incorrect_sol" style="color:red">'.
        $strincorrectpleasetryagain.
        '</div>
        <span class="recaptcha_only_if_image">
        <label for="recaptcha_response_field">'.$strenterthewordsabove.$requiredmark.'</label>
        </span>
        <span class="recaptcha_only_if_audio">
        <label for="recaptcha_response_field">'.$strenterthenumbersyouhear.'</label>
        </span>
        <input type="text" id="recaptcha_response_field" name="'.$item->typ.'_'.$item->id.'" />
        <div><a href="javascript:Recaptcha.reload()">' . $strgetanothercaptcha . '</a></div>
        <div class="recaptcha_only_if_image">
        <a href="javascript:Recaptcha.switch_type(\'audio\')">' . $strgetanaudiocaptcha . '</a>
        </div>
        <div class="recaptcha_only_if_audio">
        <a href="javascript:Recaptcha.switch_type(\'image\')">' . $strgetanimagecaptcha . '</a>
        </div>
        </div>';

        //we have to rename the challengefield
        apply_open_table_item_tag();
        if (!empty($CFG->recaptchaprivatekey) AND !empty($CFG->recaptchapublickey)) {
            $captchahtml = recaptcha_get_challenge_html(RECAPTCHA_API_URL, $CFG->recaptchapublickey);
            echo $html.$captchahtml;
        }
        apply_close_table_item_tag();
    }


    /**
     * print the item at the complete-page of apply
     *
     * @global object
     * @param object $item
     * @param string $value
     * @return void
     */
    public function print_item_show_value($item, $value = '')
    {
        global $DB;
        global $Table_in;

        $align = right_to_left() ? 'right' : 'left';

        if (!$Table_in) {
            $requiredmark = '<span class="apply_required_mark">*</span>';
            //print the question and label
            echo '<div class="apply_item_label_'.$align.'">';
            echo format_text($item->name.$requiredmark, true, false, false);
            echo '</div>';
        }

        apply_open_table_item_tag($output);
        apply_close_table_item_tag();
    }


    public function check_value($value, $item)
    {
        global $SESSION, $CFG, $USER;

        require_once($CFG->libdir.'/recaptchalib_v2.php');

        //is recaptcha configured in moodle?
        if (empty($CFG->recaptchaprivatekey) OR empty($CFG->recaptchapublickey)) {
            return true;
        }
        $challenge = required_param('g-recaptcha-response', PARAM_RAW);

        if ($value == $USER->sesskey AND $challenge == '') {
            return true;
        }
        $remoteip = getremoteaddr(null);
        //
        $response = recaptcha_check_response(RECAPTCHA_VERIFY_URL, $CFG->recaptchaprivatekey, $remoteip, $challenge);
        if ($response['isvalid']) {
            $SESSION->apply->captchacheck = $USER->sesskey;
            return true;
        }
        unset($SESSION->apply->captchacheck);

        return false;
    }


    public function create_value($data)
    { 
        global $USER;
        return $USER->sesskey;
    }


    //compares the dbvalue with the dependvalue
    //dbvalue is value stored in the db
    //dependvalue is the value to check
    public function compare_value($item, $dbvalue, $dependvalue)
    {
        if ($dbvalue == $dependvalue) {
            return true;
        }
        return false;
    }


    public function get_presentation($data)
    {
        return '';
    }


    public function get_hasvalue()
    {
        global $CFG;

        //is recaptcha configured in moodle?
        if (empty($CFG->recaptchaprivatekey) OR empty($CFG->recaptchapublickey)) {
            return 0;
        }
        return 1;
    }


    public function can_switch_require()
    {
        return false;
    }


    public function value_type()
    {
        return PARAM_RAW;
    }


    public function clean_input_value($value)
    {
        return clean_param($value, $this->value_type());
    }
}
