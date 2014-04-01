<?php

defined('MOODLE_INTERNAL') || die;

$plugin->version   = 2014030600; // The current module version (Date: YYYYMMDDXX).
$plugin->requires  = 2014022800; // Requires this Moodle version.
$plugin->component = 'theme_protsample'; // Full name of the plugin (used for diagnostics).
$plugin->dependencies = array(
    'theme_clean'  => 2013110500,
);
