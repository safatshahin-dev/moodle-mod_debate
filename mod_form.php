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

/**
 * The main mod_debate configuration form.
 *
 * @package     mod_debate
 * @copyright   2021 Safat Shahin <safatshahin@yahoo.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/debate/locallib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/formslib.php');

use mod_debate\debate_constants;

/**
 * Class mod_debate_mod_form
 *
 * @package     mod_debate
 * @copyright   2021 Safat Shahin <safatshahin@yahoo.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_debate_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('debatename', 'debate'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255),
                'maxlength', 255, 'client');

        $mform->addElement('text', 'debate', get_string('debate_topic', 'debate'), array('size' => '64'));
        $mform->setType('debate', PARAM_RAW);
        $mform->addRule('debate', get_string('required'), 'required', null, 'client');

        $this->standard_intro_elements();
        $mform->addElement('advcheckbox', 'debateformat', get_string('showinmodule', 'mod_debate'));

        $responsetype = array(
            debate_constants::MOD_DEBATE_RESPONSE_UNLIMITED => get_string('unlimited_response', 'mod_debate'),
            debate_constants::MOD_DEBATE_RESPONSE_ONLY_ONE => get_string('one_response', 'mod_debate'),
            debate_constants::MOD_DEBATE_RENPONSE_ONE_PER_SECTIOM => get_string('two_response', 'mod_debate'),
            debate_constants::MOD_DEBATE_RESPONSE_USE_TEAMS => get_string('use_teams', 'mod_debate')
        );
        $mform->addElement('select', 'responsetype',
                get_string('user_response', 'mod_debate'), $responsetype);
        $mform->setDefault('responsetype', debate_constants::MOD_DEBATE_RESPONSE_UNLIMITED);

        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * set pre processing of data.
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        $defaultvalues['debateresponsecom'] =
            !empty($defaultvalues['debateresponsecomcount']) ? 1 : 0;
        if (empty($defaultvalues['debateresponsecomcount'])) {
            $defaultvalues['debateresponsecomcount'] = 1;
        }
    }

    /**
     * Add custom completion rules.
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform =& $this->_form;

        $group = array();
        $group[] =& $mform->createElement('checkbox', 'debateresponsecom', '',
                get_string('debateresponsecom', 'mod_debate'));
        $group[] =& $mform->createElement('text', 'debateresponsecomcount', '', array('size' => 3));
        $mform->setType('debateresponsecomcount', PARAM_INT);
        $mform->addGroup($group, 'debateresponsecomgroup', get_string('debateresponsecomgroup', 'mod_debate'), array(' '), false);
        $mform->disabledIf('debateresponsecomcount', 'debateresponsecom', 'notchecked');
        return array('debateresponsecomgroup');
    }

    /**
     * Check if completion rule enabled.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return (!empty($data['debateresponsecom']) && $data['debateresponsecomcount'] != 0);
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     * Only available on moodleform_mod.
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        // Turn off completion settings if the checkboxes aren't ticked.
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->debateresponsecom) || !$autocompletion) {
                $data->debateresponsecomcount = 0;
            }
        }
    }

}
