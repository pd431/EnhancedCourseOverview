# Enhanced Course Overview Block for Moodle

This plugin extends Moodle's Course Overview block and adds simple text-based filters to help users quickly find relevant courses.

## Features

- Extends the standard Course Overview block functionality
- Adds customizable filter buttons organized in groups
- Filters courses based on text matches in course names or descriptions
- Simple configuration through block settings
- Fully responsive design

## Installation

1. Download the plugin from the GitHub repository or Moodle plugins directory
2. Extract the folder and copy it to your Moodle blocks directory: `/blocks/enhancedcourseoverview`
3. Visit your site as an administrator and follow the prompts to install the plugin
4. Add the "Enhanced Course Overview" block to your Dashboard

## Configuration

The plugin is configured through the block's settings. After adding the block to your dashboard:

1. Click the gear icon and select "Configure Enhanced Course Overview block"
2. Configure your filter groups and items using the following syntax:

```
Group Name 1
Filter 1|text_to_match_1
Filter 2|text_to_match_2

Group Name 2
Filter 3|text_to_match_3
Filter 4|text_to_match_4
```

### Example Configuration:

```
2023-24
Term 1|_A1_202324
Term 2|_A2_202324

2024-25
Term 1|_A1_202425
Term 2|_A2_202425
```

This will create two button groups with two filters each:
- Group "2023-24" with buttons "Term 1" and "Term 2" 
- Group "2024-25" with buttons "Term 1" and "Term 2"

When a filter button is clicked, only courses containing the specified text will be shown.

## Usage

1. Add the Enhanced Course Overview block to your dashboard
2. Configure your desired filters
3. Click on filter buttons to show only relevant courses
4. Click the "All courses" button to reset filters and show all courses

## Requirements

- Moodle 4.1 or higher
- The core My Overview block must be enabled

## License

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

## Credits

This plugin was developed as an extension to Moodle's Course Overview block.
