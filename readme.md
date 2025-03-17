# Enhanced Course Overview Block for Moodle

This plugin extends Moodle's Course Overview block to provide advanced filtering capabilities for courses based on academic years, terms, and user roles.

## Features

- **Academic Year and Term Filtering**: Filter courses by academic year (e.g., 2023-24) and term (e.g., Term 1, Term 2)
- **Role-Based Filtering**: Filter courses based on your role in each course (Student, Teacher, Course Admin)
- **Visual Term and Role Indicators**: Course cards include visual badges indicating the term and your role
- **Pattern-Based Course Assignment**: Automatically categorize courses by academic year and term using pattern matching
- **Configurable by Administrators**: Easy configuration of academic years, terms, and patterns through the Moodle admin interface

## Installation

1. Download the plugin
2. Install it to your Moodle site (Site administration > Plugins > Install plugins)
3. Navigate to Site administration > Plugins > Blocks > Enhanced Course Overview to configure the plugin

## Configuration

### Academic Years and Terms

Configure academic years and terms using the following format:

```
# Format: Academic years and terms
# Each line represents either a year group or specific term
# Use [current] to mark the current year/term

[current] 2024-25
Term1_2425|_A1_2425
[current] Term2_2425|_A2_2425
Term3_2425|_A3_2425

2023-24
Term1_2324|_A1_2324
Term2_2324|_A2_2324
Term3_2324|_A3_2324
```

Each term line contains two parts separated by the | character:
- The term name (e.g., "Term1_2425")
- The pattern to match in course names/codes (e.g., "_A1_2425")

### Role Configuration

The plugin automatically categorizes courses based on the user's capabilities:
- Course Admin: Users with the ability to update course settings
- Teacher: Users who can manage activities but not update the course
- Student: All other enrolled users

## Usage

1. Add the "Enhanced Course Overview" block to your Moodle Dashboard
2. Use the filter buttons to filter courses by academic year, term, and role
3. Your filter preferences will be remembered across sessions

## Requirements

- Moodle 4.0 or higher
- The standard Course Overview block must be installed (included in core Moodle)

## License

This plugin is licensed under the GNU GPL v3 or later. See the LICENSE file for details.

## Author

Created by [Your Name]

## Acknowledgements

This plugin builds upon the standard Moodle Course Overview block, extending its functionality with academic year and term filtering capabilities.
