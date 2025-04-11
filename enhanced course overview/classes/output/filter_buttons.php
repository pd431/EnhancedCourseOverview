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
 * Class containing data for filter buttons
 *
 * @package    block_enhancedcourseoverview
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_enhancedcourseoverview\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use stdClass;

/**
 * Class containing data for filter buttons
 *
 * @package    block_enhancedcourseoverview
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_buttons implements renderable, templatable {
    
    /** @var string The filter configuration text */
    private $filterconfig;
    
    /** @var array Parsed filter groups */
    private $filtergroups;
    
    /**
     * Constructor.
     *
     * @param string $filterconfig The filter configuration from block settings
     */
    public function __construct($filterconfig) {
        global $CFG;

        // Echo raw filter config for debugging
        if ($CFG->debugdeveloper) {
            debugging('Raw filter config: ' . htmlspecialchars($filterconfig), DEBUG_DEVELOPER);
        }
        
        $this->filterconfig = $filterconfig;
        $this->filtergroups = $this->parse_filters($filterconfig);
    }
    
    /**
     * Parse the filter configuration text into structured groups and filters
     *
     * @param string $filterconfig The filter configuration text
     * @return array The parsed filter groups
     */
    private function parse_filters($filterconfig) {
        global $CFG;
        
        if (empty($filterconfig)) {
            debugging('Filter config is empty', DEBUG_DEVELOPER);
            return [];
        }
        
        // Example hard-coded filters for debugging
        if ($CFG->debugdeveloper) {
            $hardcoded = [
                [
                    'name' => '2023-24',
                    'filters' => [
                        [
                            'title' => 'Term 1',
                            'match' => '_A1_202324',
                            'id' => 'filter-' . md5('_A1_202324')
                        ],
                        [
                            'title' => 'Term 2',
                            'match' => '_A2_202324',
                            'id' => 'filter-' . md5('_A2_202324')
                        ]
                    ]
                ],
                [
                    'name' => '2024-25',
                    'filters' => [
                        [
                            'title' => 'Term 1',
                            'match' => '_A1_202425',
                            'id' => 'filter-' . md5('_A1_202425')
                        ],
                        [
                            'title' => 'Term 2',
                            'match' => '_A2_202425',
                            'id' => 'filter-' . md5('_A2_202425')
                        ]
                    ]
                ]
            ];
            
            debugging('Using hardcoded filters for debugging', DEBUG_DEVELOPER);
            return $hardcoded;
        }
        
        $lines = explode("\n", $filterconfig);
        if ($CFG->debugdeveloper) {
            debugging('Number of lines in config: ' . count($lines), DEBUG_DEVELOPER);
        }
        
        $groups = [];
        $currentGroup = null;
        
        foreach ($lines as $linenum => $line) {
            $line = trim($line);
            if (empty($line)) {
                if ($CFG->debugdeveloper) {
                    debugging("Skipping empty line " . ($linenum + 1), DEBUG_DEVELOPER);
                }
                continue; // Skip empty lines
            }
            
            if ($CFG->debugdeveloper) {
                debugging("Processing line " . ($linenum + 1) . ": " . $line, DEBUG_DEVELOPER);
            }
            
            if (strpos($line, '|') === false) {
                // This is a group header
                $currentGroup = [
                    'name' => $line,
                    'filters' => []
                ];
                $groups[] = $currentGroup;
                
                if ($CFG->debugdeveloper) {
                    debugging("Created group: {$line}", DEBUG_DEVELOPER);
                }
            } else if ($currentGroup !== null) {
                // This is a filter item
                $parts = explode('|', $line, 2);
                if (count($parts) < 2) {
                    if ($CFG->debugdeveloper) {
                        debugging("Invalid filter line format at line " . ($linenum + 1) . ": {$line}", DEBUG_DEVELOPER);
                    }
                    continue;
                }
                
                list($title, $match) = array_map('trim', $parts);
                $filterid = 'filter-' . md5($match . $linenum);
                
                $currentGroup['filters'][] = [
                    'title' => $title,
                    'match' => $match,
                    'id' => $filterid
                ];
                
                if ($CFG->debugdeveloper) {
                    debugging("Added filter to group '{$currentGroup['name']}': {$title} | {$match}", DEBUG_DEVELOPER);
                }
            } else {
                if ($CFG->debugdeveloper) {
                    debugging("Found filter line without a preceding group header: {$line}", DEBUG_DEVELOPER);
                }
            }
        }
        
        if ($CFG->debugdeveloper) {
            debugging("Parse complete. Found " . count($groups) . " groups", DEBUG_DEVELOPER);
            
            foreach ($groups as $index => $group) {
                debugging("Group " . ($index + 1) . ": {$group['name']} with " . count($group['filters']) . " filters", DEBUG_DEVELOPER);
                
                foreach ($group['filters'] as $fidx => $filter) {
                    debugging("  Filter " . ($fidx + 1) . ": {$filter['title']} | {$filter['match']}", DEBUG_DEVELOPER);
                }
            }
        }
        
        return $groups;
    }
    
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output The renderer
     * @return stdClass Data for the template
     */
    public function export_for_template(renderer_base $output) {
        global $CFG;
        
        $data = new stdClass();
        $data->groups = $this->filtergroups;
        $data->hasgroups = !empty($this->filtergroups);
        $data->uniqid = uniqid();
        
        if ($CFG->debugdeveloper) {
            debugging("Exporting template data with " . count($this->filtergroups) . " groups", DEBUG_DEVELOPER);
            
            // Debug the actual data being passed to the template
            $debugData = json_encode($data, JSON_PRETTY_PRINT);
            debugging("Template data: " . $debugData, DEBUG_DEVELOPER);
        }
        
        return $data;
    }
}