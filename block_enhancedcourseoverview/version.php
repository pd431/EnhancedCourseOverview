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
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2023121112;        // The current plugin version (Date: YYYYMMDDXX)
$plugin->requires  = 2023041800;        // Requires Moodle 4.2 or later
$plugin->component = 'block_enhancedcourseoverview'; // Full name of the plugin
$plugin->dependencies = [
    'block_myoverview' => 2023041800    // The block_myoverview plugin must be present
];
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
