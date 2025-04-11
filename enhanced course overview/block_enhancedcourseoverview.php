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
 * Enhanced Course Overview block class
 *
 * @package    block_enhancedcourseoverview
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/myoverview/block_myoverview.php');

/**
 * Enhanced Course Overview block class
 *
 * @package    block_enhancedcourseoverview
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_enhancedcourseoverview extends block_myoverview {

    /**
     * Initialize block
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_enhancedcourseoverview');
    }

    /**
     * Returns the contents.
     *
     * @return stdClass contents of block
     */
    public function get_content() {
        global $CFG, $PAGE, $OUTPUT, $DB;
        
        if (isset($this->content)) {
            return $this->content;
        }
        
        // Check if there's a stored configuration
        if (empty($this->config)) {
            // If configuration doesn't exist, use default values
            $this->config = new stdClass();
            $this->config->filters = "2023-24\nTerm 1|_A1_202324\nTerm 2|_A2_202324\n\n2024-25\nTerm 1|_A1_202425\nTerm 2|_A2_202425";
            
            if ($CFG->debugdeveloper) {
                debugging('No configuration found, using default filter values', DEBUG_DEVELOPER);
            }
        }
        
        // Get content from parent block (Course Overview)
        $this->content = parent::get_content();
        
        // If no content, return
        if (empty($this->content)) {
            return $this->content;
        }

        // Parse and add the filter buttons
        $filtersetting = isset($this->config) && isset($this->config->filters) ? $this->config->filters : '';
        
        if ($CFG->debugdeveloper) {
            debugging('Block ID: ' . $this->instance->id, DEBUG_DEVELOPER);
            debugging('Filter settings from config: ' . $filtersetting, DEBUG_DEVELOPER);
            
            // Try to load the configuration directly from the database
            $blockconfig = $DB->get_field('block_instances', 'configdata', ['id' => $this->instance->id]);
            if ($blockconfig) {
                $config = unserialize(base64_decode($blockconfig));
                debugging('Config from DB: ' . print_r($config, true), DEBUG_DEVELOPER);
            } else {
                debugging('No config found in DB', DEBUG_DEVELOPER);
            }
        }
        
        if (!empty($filtersetting)) {
            // For troubleshooting, add a visible debug message in the block for admins
            if ($CFG->debugdeveloper && is_siteadmin()) {
                $this->content->text = '<div class="alert alert-info">Filter setting length: ' . 
                                       strlen($filtersetting) . ' chars</div>' . $this->content->text;
            }
            
            $renderer = $PAGE->get_renderer('block_enhancedcourseoverview');
            $filterbuttons = new \block_enhancedcourseoverview\output\filter_buttons($filtersetting);
            $filterhtml = $renderer->render($filterbuttons);
            
            // For troubleshooting, show the rendered HTML for admins
            if ($CFG->debugdeveloper && is_siteadmin()) {
                $this->content->text = '<div class="alert alert-info">Rendered filter HTML length: ' . 
                                      strlen($filterhtml) . ' chars</div>' . $this->content->text;
            }
            
            // Add the filter buttons before the courses view
            $pattern = '/<div role="search" data-region="filter"/';
            if (preg_match($pattern, $this->content->text)) {
                $this->content->text = preg_replace(
                    $pattern, 
                    $filterhtml . "\n" . '<div role="search" data-region="filter"', 
                    $this->content->text
                );
            } else {
                // Fallback: add at the beginning
                $this->content->text = $filterhtml . $this->content->text;
            }
            
            // Add the required JavaScript
            $PAGE->requires->js(new moodle_url('/blocks/enhancedcourseoverview/amd/src/main.js'), true);
            
            // Also include our CSS
            $PAGE->requires->css('/blocks/enhancedcourseoverview/styles.css');
        } else {
            if ($CFG->debugdeveloper) {
                debugging('Enhanced Course Overview: No filter settings configured', DEBUG_DEVELOPER);
                
                // Show debug message for admins
                if (is_siteadmin()) {
                    $this->content->text = '<div class="alert alert-warning">No filter settings configured</div>' . 
                                          $this->content->text;
                }
            }
        }
        
        return $this->content;
    }

    /**
     * Allow the block to have a configuration page
     *
     * @return boolean
     */
    public function has_config() {
        return true;
    }

    /**
     * Locations where block can be displayed
     *
     * @return array
     */
    public function applicable_formats() {
        return array('my' => true);
    }

    /**
     * Gets the block instance settings that are being overridden.
     *
     * @return array Settings
     */
    public function get_instance_config_settings() {
        $settings = parent::get_instance_config_settings();
        $settings[] = 'filters';
        return $settings;
    }
    
    /**
     * Default return is false - header will be shown
     * 
     * @return boolean
     */
    public function hide_header() {
        return false;
    }
}