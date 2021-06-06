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
 * Debate response class for mod_debate.
 *
 * @package     mod_debate
 * @copyright   2021 Safat Shahin <safatshahin@yahoo.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_debate;

defined('MOODLE_INTERNAL') || die;

use context_module;
use mod_debate\event\debate_response_added;
use mod_debate\event\debate_response_updated;
use mod_debate\event\debate_response_deleted;
use mod_debate\event\debate_response_error;

/**
 * Class debate_response
 *
 * @package mod_debate
 * @copyright   2021 Safat Shahin <safatshahin@yahoo.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class debate_response {

    /**
     * Response id.
     *
     * @var int
     */
    public $id;

    /**
     * Course id.
     *
     * @var int
     */
    public $courseid;

    /**
     * Debate id.
     *
     * @var int
     */
    public $debateid;

    /**
     * User id.
     *
     * @var int
     */
    public $userid;

    /**
     * Response from the user.
     *
     * @var string
     */
    public $response;

    /**
     * Type of response, positive/negative.
     *
     * @var int
     */
    public $responsetype;

    /**
     * Time response created.
     *
     * @var int
     */
    public $timecreated = 0;

    /**
     * Time response modified.
     *
     * @var int
     */
    public $timemodified = 0;

    /**
     * Course module id.
     *
     * @var int
     */
    public $cmid;

    /**
     * debate_response constructor.
     * Builds object if $id provided.
     * @param int $id
     */
    public function __construct(int $id = 0) {
        if (!empty($id)) {
            $this->load_debate_response($id);
        }
    }

    /**
     * Gets the specified debate_response and loads it into the object.
     *
     * @param int $id
     */
    public function load_debate_response(int $id): void {
        global $DB;
        $debateresponse = $DB->get_record('debate_response', array('id' => $id));
        if (!empty($debateresponse)) {
            $this->id = $debateresponse->id;
            $this->courseid = $debateresponse->courseid;
            $this->debateid = $debateresponse->debateid;
            $this->userid = $debateresponse->userid;
            $this->response = $debateresponse->response;
            $this->responsetype = $debateresponse->responsetype;
            $this->timecreated = $debateresponse->timecreated;
            $this->timemodified = $debateresponse->timemodified;
        }
    }

    /**
     * Constructs the actual debate_response object given either a $DB object or Moodle form data.
     *
     * @param \stdClass $debateresponse
     */
    public function construct_debate_response(\stdClass $debateresponse): void {
        if (!empty($debateresponse)) {
            $this->courseid = $debateresponse->courseid;
            $this->debateid = $debateresponse->debateid;
            $this->response = $debateresponse->response;
            $this->responsetype = $debateresponse->responsetype;
        }
    }

    /**
     * Delete the debate_response.
     * @return bool
     */
    public function delete(): bool {
        global $DB;
        $deletesuccess = false;
        if (!empty($this->id)) {
            $deletesuccess = $DB->delete_records('debate_response', array('id' => $this->id));
            if ($deletesuccess) {
                $eventsuccess = self::calculate_completion(true);
                if ($eventsuccess) {
                    self::after_delete();
                } else {
                    self::update_error();
                }
                $deletesuccess = $eventsuccess;
            }
        }
        return $deletesuccess;
    }

    /**
     * Save or create debate_response.
     * @return bool
     */
    public function save(): bool {
        global $DB, $USER;
        $savesuccess = false;
        if (!empty($this->id)) {
            $this->timemodified = time();
            $savesuccess = $DB->update_record('debate_response', $this);
            if ($savesuccess) {
                $eventsuccess = self::calculate_completion();
                if ($eventsuccess) {
                    self::after_update();
                } else {
                    self::update_error();
                }
                $savesuccess = $eventsuccess;
            }
        } else {
            $this->userid = $USER->id;
            $this->timecreated = time();
            $this->id = $DB->insert_record('debate_response', $this);
            if (!empty($this->id)) {
                $eventsuccess = self::calculate_completion();
                if ($eventsuccess) {
                    self::after_create();
                } else {
                    self::update_error();
                }
                $savesuccess = $eventsuccess;
            }
        }
        return $savesuccess;
    }

    /**
     * calculate completion for debate instance.
     * @param bool $delete
     * @return bool
     */
    public function calculate_completion($delete = false): bool {
        global $DB;
        $result = false;
        $debate = $DB->get_record('debate', array('id' => (int)$this->debateid), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => (int)$this->courseid), '*', MUST_EXIST);
        $coursemodule = get_coursemodule_from_instance('debate', $debate->id, $course->id, false, MUST_EXIST);
        if ($coursemodule) {
            $this->cmid = $coursemodule->id;
            $userresponsecount = $DB->count_records_select('debate_response',
                'debateid = :debateid AND courseid = :courseid AND userid = :userid',
                array('debateid' => (int)$debate->id, 'courseid' => (int)$course->id, 'userid' => $this->userid), 'COUNT("id")');
            $completion = new \completion_info($course);
            if ($delete) {
                if ($completion->is_enabled($coursemodule) == COMPLETION_TRACKING_AUTOMATIC &&
                    (int)$debate->debateresponsecomcount > 0) {
                    if ($userresponsecount >= (int)$debate->debateresponsecomcount) {
                        $completion->update_state($coursemodule, COMPLETION_COMPLETE, $this->userid);
                    } else {
                        $current = $completion->get_data($coursemodule, false, $this->userid);
                        $current->completionstate = COMPLETION_INCOMPLETE;
                        $current->timemodified    = time();
                        $current->overrideby      = null;
                        $completion->internal_set_data($coursemodule, $current);
                    }
                }
            } else {
                if ($completion->is_enabled($coursemodule) == COMPLETION_TRACKING_AUTOMATIC
                    && (int)$debate->debateresponsecomcount > 0 &&
                        $userresponsecount >= (int)$debate->debateresponsecomcount) {
                    $completion->update_state($coursemodule, COMPLETION_COMPLETE, $this->userid);
                }
            }
            $result = true;
        }

        return $result;
    }

    /**
     * create event for debate_response.
     */
    public function after_create(): void {
        global $USER;
        $event = debate_response_added::create(
            array(
                'context' => context_module::instance($this->cmid),
                'userid' => $USER->id,
                'objectid' => $this->id,
                'other' => array(
                    'debateid' => $this->debateid
                )
            )
        );
        $event->trigger();
    }

    /**
     * update event for debate_response.
     */
    public function after_update(): void {
        global $USER;
        $event = debate_response_updated::create(
            array(
                'context' => context_module::instance($this->cmid),
                'userid' => $USER->id,
                'objectid' => $this->id,
                'other' => array(
                    'debateid' => $this->debateid
                )
            )
        );
        $event->trigger();
    }

    /**
     * delete event for debate_response.
     */
    public function after_delete(): void {
        global $USER;
        $event = debate_response_deleted::create(
            array(
                'context' => context_module::instance($this->cmid),
                'userid' => $USER->id,
                'objectid' => $this->id,
                'other' => array(
                    'debateid' => $this->debateid
                )
            )
        );
        $event->trigger();
    }

    /**
     * error event for debate_response.
     */
    public function update_error(): void {
        global $USER;
        $event = debate_response_error::create(
            array(
                'userid' => $USER->id
            )
        );
        $event->trigger();
    }

    /**
     * find matching responses for debate_response.
     *
     * @param array $params
     * @return array
     */
    public static function find_matching_response(array $params): array {
        global $DB;
        $datas = $DB->get_records('debate_response', array('courseid' => $params['courseid'],
            'debateid' => $params['debateid'], 'responsetype' => $params['responsetype']), '', 'response');

        $excludewords = array('i', 'a', 'about', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'com', 'de', 'en', 'for',
                              'from', 'how', 'in', 'is', 'it', 'la', 'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was',
                              'what', 'when', 'where', 'who', 'will', 'with', 'und', 'the', 'www', "such", "have", "then");

        $cleanresponse = preg_replace('/\s\s+/i', '', $params['response']);
        $cleanresponse = trim($cleanresponse);
        $cleanresponse = preg_replace('/[^a-zA-Z0-9 -]/', '', $cleanresponse);
        $cleanresponse = strtolower($cleanresponse);

        // All the words from typed response.
        preg_match_all('/\b.*?\b/i', $cleanresponse, $responsewords);
        $responsewords = $responsewords[0];

        // Remove invalid words.
        foreach ($responsewords as $key => $word) {
            if ( $word == '' || in_array(strtolower($word), $excludewords) || strlen($word) <= 2 ) {
                unset($responsewords[$key]);
            }
        }

        $responsewordcounter = count($responsewords);
        if (!empty($datas)) {
            foreach ($datas as $key => $data) {
                $datacounter = 0;
                foreach ($responsewords as $responseword) {
                    if (strpos($data->response, $responseword) == false) {
                        $datacounter++;
                    }
                }
                if ($datacounter == $responsewordcounter) {
                    unset($datas[$key]);
                }
            }
        }
        $finaldata = array();
        foreach ($datas as $dt) {
            $finaldata[] = $dt;
        }
        return $finaldata;
    }

}
