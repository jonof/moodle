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
 * Administration setting type for file type sets
 *
 * @package   assignsubmission_usqfiletypes
 * @copyright 2013 The University of Southern Queensland {@link http://www.usq.edu.au}
 * @author    Jonathon Fowler <fowlerj@usq.edu.au>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_usqfiletypes;

use admin_setting, stdClass, html_writer;

/**
 * Administration interface for submission file type set settings.
 */
class admin_setting_filetypes extends admin_setting {
    /**
     * Calls parent::__construct with specific args
     */
    public function __construct() {
        global $CFG;

        $helper = new filetypes_helper();
        $defaults = $this->prepare_form_data($helper->default_types());
        parent::__construct('assignsubmission_usqfiletypes/filetypes',
                get_string('filetypes', 'assignsubmission_usqfiletypes'),
                get_string('filetypes_desc', 'assignsubmission_usqfiletypes'), $defaults);
    }

    private function decode_stored_config($configstring) {
        $helper = new filetypes_helper();
        $lines = explode("\n", $configstring);
        $config = array_map(function ($s) use ($helper) {
                list ($extensions, $description) = explode(';', $s, 2);
                return $helper->prepare_file_type($extensions, $description);
            }, $lines);
        return $config;
    }

    private function encode_stored_config($config) {
        $lines = array_map(function ($a) {
                return $a->extensions . ';' . $a->description;
            }, $config);
        $configstring = implode("\n", $lines);
        return $configstring;
    }

    /**
     * Return the current setting(s)
     *
     * @return array Current settings array
     */
    public function get_setting() {
        global $CFG;

        $helper = new filetypes_helper();

        $config = $this->config_read($this->name);
        if (is_null($config)) {
            return $this->prepare_form_data($helper->default_types());
        }

        $config = $this->decode_stored_config($config);
        if (is_null($config)) {
            return null;
        }

        return $this->prepare_form_data($config);
    }

    /**
     * Save selected settings
     *
     * @param array $data Array of settings to save
     * @return bool
     */
    public function write_setting($data) {

        $filetypes = $this->process_form_data($data);
        if ($filetypes === false) {
            return false;
        }

        if ($this->config_write($this->name, $this->encode_stored_config($filetypes))) {
            return ''; // success
        } else {
            return get_string('errorsetting', 'admin') . $this->visiblename . html_writer::empty_tag('br');
        }
    }

    /**
     * Return XHTML field(s) for options
     *
     * @param array $data Array of options to set in HTML
     * @return string XHTML string for the fields and wrapping div(s)
     */
    public function output_html($data, $query='') {
        global $OUTPUT;

        $out  = html_writer::start_tag('table', array('id' => 'filetypessetting', 'class' => 'admintable generaltable'));
        $out .= html_writer::start_tag('thead');
        $out .= html_writer::start_tag('tr');
        $out .= html_writer::tag('th', get_string('displaydescription', 'assignsubmission_usqfiletypes'));
        $out .= html_writer::tag('th', get_string('fileextensions', 'assignsubmission_usqfiletypes'));
        $out .= html_writer::end_tag('tr');
        $out .= html_writer::end_tag('thead');
        $out .= html_writer::start_tag('tbody');
        for ($i = 0; isset($data['extensions'.$i]); $i++) {
                $out .= html_writer::start_tag('tr');

                $out .= html_writer::tag('td',
                    html_writer::empty_tag('input',
                        array(
                            'type'  => 'text',
                            'class' => 'form-text',
                            'name'  => $this->get_full_name().'[description'.$i.']',
                            'value' => $data['description'.$i],
                            'size'  => 30,
                        )
                    ), array('class' => 'c'.$i)
                );

                $out .= html_writer::tag('td',
                    html_writer::empty_tag('input',
                        array(
                            'type'  => 'text',
                            'class' => 'form-text',
                            'name'  => $this->get_full_name().'[extensions'.$i.']',
                            'value' => str_replace(',', ', ', $data['extensions'.$i]),
                            'size'  => 30,
                        )
                    ), array('class' => 'c'.$i)
                );

                $out .= html_writer::end_tag('tr');
        }
        $out .= html_writer::end_tag('tbody');
        $out .= html_writer::end_tag('table');
        $out  = html_writer::tag('div', $out, array('class' => 'form-group'));

        return format_admin_setting($this, $this->visiblename, $out, $this->description, false, '', NULL, $query);
    }

    /**
     * Converts the array of file type objects into admin settings form data
     *
     * @see self::process_form_data()
     * @param array $filetypes array of file type objects
     * @return array of form fields and their values
     */
    protected function prepare_form_data(array $filetypes) {

        $form = array();
        $i = 0;
        foreach ($filetypes as $filetype) {
            $form['extensions'.$i]  = $filetype->extensions;
            $form['description'.$i] = $filetype->description;
            $i++;
        }
        // add one more blank field set for new object
        $form['extensions'.$i]  = '';
        $form['description'.$i] = '';

        return $form;
    }

    /**
     * Sanitise user input of file types into a consistent internal format
     *
     * @param string $typestring a list of file types
     * @return string a normalised type string
     */
    private function normalise_filetypelist($typestring) {
        $types = preg_split('/[,;\s]+/', $typestring);
        $types = array_map(function ($t) { return ltrim($t, '*.'); }, $types);
        return implode(',', $types);
    }

    /**
     * Converts the data from admin settings form into an array of file types
     *
     * @see self::prepare_form_data()
     * @param array $data array of admin form fields and values
     * @return false|array of file types
     */
    protected function process_form_data(array $form) {

        $count = count($form); // number of form field values

        if ($count % 2) {
            // we must get two fields per file type
            return false;
        }

        $filetypes = array();
        for ($i = 0; $i < $count / 2; $i++) {
            $filetype = new stdClass();
            $filetype->extensions = $this->normalise_filetypelist(clean_param($form['extensions'.$i], PARAM_RAW_TRIMMED));
            $filetype->description = clean_param($form['description'.$i], PARAM_NOTAGS);

            if ($filetype->extensions !== '' && $filetype->description !== '') {
                $filetypes[] = $filetype;
            }
        }
        return $filetypes;
    }
}
