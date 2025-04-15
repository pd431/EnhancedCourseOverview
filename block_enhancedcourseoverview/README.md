# Enhanced Course Overview Block for Moodle

This plugin extends Moodle's Course Overview block to add simple text-based filters that allow users to quickly filter their courses.

## Features

- Simple text-based filters configured through the plugin settings
- Filter buttons organized in groups
- No AMD modules required - works with simple JavaScript
- Maintains all original Course Overview block functionality

## Installation

1. Download the plugin files
2. Create a folder called `enhancedcourseoverview` in your Moodle `blocks` directory
3. Extract the plugin files into this directory
4. Visit your Moodle site as an administrator to complete the installation

## Configuration

1. Go to Site Administration > Plugins > Blocks > Enhanced Course Overview
2. Configure the filter definitions using the following format:

```
Group Name
Filter Title|Pattern to Match

Group Name 2
Filter Title|Pattern to Match
Filter Title 2|Pattern to Match
```

Each line without a pipe (|) character starts a new group. Lines with pipes define a filter button, where the text before the pipe is the button label and the text after is the pattern to match in course titles.

Example:
```
2023-24
Term 1|_A1_202324
Term 2|_A2_202324

2024-25
Term 1|_A1_202425
Term 2|_A2_202425
```

This will create two button groups, one for 2023-24 and one for 2024-25, each with Term 1 and Term 2 buttons.

## Usage

1. Add the "Enhanced Course Overview" block to your dashboard
2. Use the filter buttons to show only courses matching specific patterns
3. Click a button to activate the filter, click again to deactivate
4. Multiple filters can be active simultaneously (OR logic)

## Requirements

- Moodle 4.2 or later
- The standard Course Overview block must be installed

## License

This plugin is licensed under the GNU GPL v3 or later. See the LICENSE file for details.
