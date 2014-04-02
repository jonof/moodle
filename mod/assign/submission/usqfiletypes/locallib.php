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
 * File types filtering submission plugin
 *
 * @package   assignsubmission_usqfiletypes
 * @copyright 2013 The University of Southern Queensland {@link http://www.usq.edu.au}
 * @author    Jonathon Fowler <fowlerj@usq.edu.au>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();


/**
 * Library class for file types submission plugin extending submission plugin base class
 *
 * @package   assignsubmission_usqfiletypes
 * @copyright 2013 The University of Southern Queensland {@link http://www.usq.edu.au}
 * @author    Jonathon Fowler <fowlerj@usq.edu.au>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_usqfiletypes extends assign_submission_plugin {

    /**
     * Get the name of the file submission plugin
     * @return string
     */
    public function get_name() {
        return get_string('usqfiletypes', 'assignsubmission_usqfiletypes');
    }

    /**
     * This plugin does not accept submissions from a student
     * @return boolean
     */
    public function allow_submissions() {
        // This needs to return true because otherwise it won't be called to
        // change the submission filemanager to limit the file types, or add
        // static text to the submission form.
        return true;
    }

    /**
     * If this plugin should not include a column in the grading table or a row on the summary page
     * then return false
     *
     * @return bool
     */
    public function has_user_summary() {
        return false;
    }

    /**
     * Get the default setting for file types submission plugin
     *
     * @param MoodleQuickForm $mform The form to add elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        global $CFG, $COURSE;

        $groupels = array();
        foreach ($this->get_all_typesets() as $k => $v) {
            $elem = $mform->createElement('checkbox', $k, '', $v);
            $groupels[] = $elem;
        }
        $name = get_string('acceptedfiletypes', 'assignsubmission_usqfiletypes');
        $mform->addGroup($groupels, 'assignsubmission_usqfiletypes_filetypes', $name, '<br/>');
        $mform->disabledIf('assignsubmission_usqfiletypes_filetypes', 'assignsubmission_usqfiletypes_enabled', 'notchecked');

        $name = get_string('filetypesother', 'assignsubmission_usqfiletypes');
        $mform->addElement('text', 'assignsubmission_usqfiletypes_filetypesother', $name, 'size="30"');
        $mform->setType('assignsubmission_usqfiletypes_filetypesother', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('assignsubmission_usqfiletypes_filetypesother', 'filetypesother', 'assignsubmission_usqfiletypes');
        $mform->disabledIf('assignsubmission_usqfiletypes_filetypesother', 'assignsubmission_usqfiletypes_enabled', 'notchecked');
    }

    /**
     * Set default values that demand a little extra effort.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        $typesconfig = $this->get_configured_typesets();

        $defaultvalues['assignsubmission_usqfiletypes_filetypes'] = array();
        foreach (array_keys($typesconfig['sets']) as $extensions) {
            $defaultvalues['assignsubmission_usqfiletypes_filetypes'][$extensions] = 1;
        }
        $defaultvalues['assignsubmission_usqfiletypes_filetypesother'] = str_replace(',', ', ', $typesconfig['other']);
    }

    /**
     * Load and parse the type sets configuration.
     *
     * @return array
     */
    private function get_all_typesets() {
        $typesets = array();
        $config = get_config('assignsubmission_usqfiletypes', 'filetypes');
        if ($config) {
            foreach (explode("\n", $config) as $line) {
                list ($extensions, $description) = explode(';', $line, 2);
                $typesets[$extensions] = $description;
            }
        } else {
            $helper = new assignsubmission_usqfiletypes\filetypes_helper();
            foreach ($helper->default_types() as $type) {
                $typesets[$type->extensions] = $type->description;
            }
        }
        return $typesets;
    }

    /**
     * Get the type sets configured for this assignment
     * @return array('sets' => array(extensions => description), 'other' => extensions)
     */
    private function get_configured_typesets() {
        $typeslist = (string)$this->get_config('filetypeslist');
        $typesets = $this->get_all_typesets();

        $sets = array();
        $other = '';

        if ($typeslist !== '') {
            $other = array();
            foreach (explode(';', $typeslist) as $type) {
                if (isset($typesets[$type])) {
                    $sets[$type] = $typesets[$type];
                } else {
                    $other = array_merge($other, explode(',', $type));
                }
            }
            $other = implode(',', array_unique($other));
        }

        return compact('sets', 'other');
    }

    /**
     * Save the settings for file submission plugin
     *
     * @param stdClass $data
     * @return bool
     */
    public function save_settings(stdClass $data) {
        $filetypeslist = array();
        if (isset($data->assignsubmission_usqfiletypes_filetypes) &&
                is_array($data->assignsubmission_usqfiletypes_filetypes)) {
            $filetypeslist = array_keys($data->assignsubmission_usqfiletypes_filetypes, 1);
        }
        if ($data->assignsubmission_usqfiletypes_filetypesother !== '') {
            $filetypeslist[] = $this->normalise_filetypelist($data->assignsubmission_usqfiletypes_filetypesother);
        }
        $this->set_config('filetypeslist', implode(';', $filetypeslist));

        return true;
    }

    /**
     * Sanitise user input of file types into a consistent internal format
     *
     * @param string $typestring a list of file types
     * @return string a normalised type string
     */
    private function normalise_filetypelist($typestring) {
        $types = preg_split('/[,;\s]+/', $typestring);
        natsort($types);
        $types = array_map(function ($t) { return ltrim($t, '*.'); }, $types);
        return implode(',', $types);
    }

    /**
     * Return the accepted types list for the file manager component
     *
     * @return array
     */
    private function get_accepted_types() {
        $accepted_types = '*';
        $types = (string)$this->get_config('filetypeslist');
        if ($types !== '') {
            $types = explode(',', strtr($types, ';', ','));
            $types = array_map(function ($a) { return ".$a"; }, $types);
            $accepted_types = $types;
        }

        return $accepted_types;
    }

    /**
     * Add elements to submission form
     *
     * @param mixed $submission stdClass|null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        if (!$mform->elementExists('files_filemanager')) {
            // This relies on the 'File submissions' plugin being ordered above
            // this plugin. It won't work if it can't find the filemanager element
            // in the form.
            return true;
        }

        // tweak the 'File submissions' file manager element with our accepted types list
        $fmelem = $mform->getElement('files_filemanager');
        $fmelem->setAcceptedtypes($this->get_accepted_types());

        // add our extra text
        $text = html_writer::tag('p', get_string('filesofthesetypes', 'assignsubmission_usqfiletypes'));
        $text .= html_writer::start_tag('ul');
        $typesets = $this->get_configured_typesets();
        foreach ($typesets['sets'] as $description) {
            $text .= html_writer::tag('li', s($description));
        }
        if ($typesets['other']) {
            $text .= html_writer::tag('li', get_string('filetypesotherlist', 'assignsubmission_usqfiletypes',
                    str_replace(',', ', ', $typesets['other'])));
        }
        $text .= html_writer::end_tag('ul');
        $mform->addElement('static', '', '', $text);

        return true;
    }
}
