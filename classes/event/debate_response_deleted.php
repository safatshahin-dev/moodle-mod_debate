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
 * The mod_debate response deleted event.
 *
 * @package     mod_debate
 * @copyright   2021 Safat Shahin <safatshahin@yahoo.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_debate\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The mod_debate response deleted event class.
 *
 * @package     mod_debate
 * @copyright   2021 Safat Shahin <safatshahin@yahoo.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class debate_response_deleted extends \core\event\course_module_viewed {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'debate_response';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $a = new \stdClass();
        $a->userid = $this->userid;
        $a->objectid = $this->objectid;
        $a->debateid = $this->other['debateid'];
        return get_string('event_response_deleted_desc', 'mod_debate', $a);
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_response_deleted', 'mod_debate');
    }

    /**
     * This is used when restoring course logs where it is required that we map the objectid to it's new value in the new course.
     *
     * @return string[]
     */
    public static function get_objectid_mapping() {
        return array('db' => 'debate_response', 'restore' => 'debate_response');
    }

    /**
     * This is used when restoring course logs where it is required that we map the information in 'other'
     * to it's new value in the new course.
     *
     * @return array
     */
    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['relateduserid'] = array('db' => 'user', 'restore' => 'user');
        return $othermapped;
    }
}
