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
 * Version details for the Enhanced Course Overview block.
 *
 * @package    block_enhancedcourseoverview
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2025041006;        // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2022112800;        // Requires this Moodle version (Moodle 4.1+).
$plugin->component = 'block_enhancedcourseoverview'; // Full name of the plugin.
$plugin->dependencies = [
    'block_myoverview' => 2022112800    // The plugin depends on the My Overview block.
];
$plugin->maturity  = MATURITY_ALPHA;    // This is an alpha version.
$plugin->release   = '0.1.7';           // Human-readable version name.
