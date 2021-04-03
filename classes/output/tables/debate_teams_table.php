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
 * Manage teams table of mod_debate.
 *
 * @package     mod_debate
 * @copyright   2021 Safat Shahin <safatshahin@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_debate\output\tables;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir.'/tablelib.php');

use table_sql;

/**
 * Class debate_teams_table.
 * An extension of your regular Moodle table.
 */
class debate_teams_table extends table_sql {

    public $search = '';
    public $cmid = 0;

    /**
     * debate_teams_table constructor.
     * Sets the SQL for the table and the pagination.
     * @param $uniqueid
     * @param $response
     * @param $debateid
     * @throws coding_exception
     */
    public function __construct($uniqueid, $response, $debateid, $cmid) {
        global $PAGE;
        $this->cmid = $cmid;
        parent::__construct($uniqueid);

        $columns = array('id', 'name', 'active', 'timemodified', 'actions');
        $headers = array(
            get_string('id', 'mod_debate'),
            get_string('name','mod_debate'),
            get_string('status','mod_debate'),
            get_string('timemodified', 'mod_debate'),
            get_string('actions','mod_debate')
        );
        $this->no_sorting('actions');
        $this->no_sorting('samplerequest');
        $this->is_collapsible = false;
        $this->define_columns($columns);
        $this->define_headers($headers);
        $fields = "id,
        name,
        active,
        responsetype,
        timemodified,
        '' AS actions";
        $from = "{debate_teams}";
        $where = 'id > 0 AND responsetype='.$response.' AND debateid='.$debateid;
        $params = array();
        $this->set_sql($fields, $from, $where, $params);
        $this->set_count_sql("SELECT COUNT(id) FROM " . $from . " WHERE " . $where, $params);
        $this->define_baseurl($PAGE->url);
    }

    /**
     * @param $values
     * @return string
     * @throws moodle_exception
     */
    public function col_name($values) {
        $urlparams = array('id' => $values->id, 'sesskey' => sesskey());
        $editurl = new moodle_url('/mod/debate/debate_teams_form_page.php', $urlparams);
        return '<a href = "' . $editurl . '">' . $values->name . '</a>';
    }

    /**
     * @param $values
     * @return string
     * @throws coding_exception
     */
    public function col_active($values) {
        $status = get_string('active', 'mod_debate');
        $css = 'success';
        if (!$values->active) {
            $status = get_string('inactive', 'mod_debate');
            $css = 'danger';
        }
        return '<div class = "text-' . $css . '"><i class = "fa fa-circle"></i>' . $status . '</div>';
    }

    /**
     * convert invalid to '-'
     * @param $values
     * @return string
     */
    public function col_timemodified($values) {
        if (!empty($values->timemodified)) {
            $dt = new DateTime("@$values->timemodified");  // convert UNIX timestamp to PHP DateTime
            $result = $dt->format('d/m/Y H:i:s'); // output = 2017-01-01 00:00:00
        } else {
            $result = '-';
        }
        return $result;
    }

    /**
     * @param $values
     * @return string Renderer template
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function col_actions($values) {
        global $PAGE;

        $urlparams = array('id' => $values->id, 'response' => $values->responsetype, 'cmid' => $this->cmid, 'sesskey' => sesskey());
        $editurl = new moodle_url('/mod/debate/debate_teams_form_page.php', $urlparams);
        $deleteurl = new moodle_url('/mod/debate/debate_teams_page.php', $urlparams + array('action' => 'delete'));
        // Decide to activate or deactivate.
        if ($values->active) {
            $toggleurl = new moodle_url('/mod/debate/debate_teams_page.php', $urlparams + array('action' => 'hide'));
            $togglename = get_string('inactive', 'mod_debate');
            $toggleicon = 'fa fa-eye';
        } else {
            $toggleurl = new moodle_url('/mod/debate/debate_teams_page.php', $urlparams + array('action' => 'show'));
            $togglename = get_string('active', 'mod_debate');
            $toggleicon = 'fa fa-eye-slash';
        }

        $renderer = $PAGE->get_renderer('mod_debate');
        $params = array(
            'id' => $values->id,
            'buttons' => array(
                array(
                    'name' => get_string('edit'),
                    'icon' => 'fa fa-edit',
                    'url' => $editurl
                ),
                array(
                    'name' => get_string('delete'),
                    'icon' => 'fa fa-trash',
                    'url' => $deleteurl
                ),
                array(
                    'name' => $togglename,
                    'icon' => $toggleicon,
                    'url' => $toggleurl
                )
            )
        );

        return $renderer->render_action_buttons($params);
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @return string
     */
    public function export_for_template() {

        ob_start();
        $this->out(20, true);
        $tablehtml = ob_get_contents();
        ob_end_clean();

        return $tablehtml;
    }
}
