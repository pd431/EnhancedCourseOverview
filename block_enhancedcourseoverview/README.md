# Enhanced Course Overview Block for Moodle

This plugin extends Moodle's Course Overview block to add simple text-based filters that allow users to quickly filter their courses.

## Features

- Simple text-based filters configured through the plugin settings
- Filter buttons organized in groups; click a group's header to toggle every filter in that group at once
- On load, every course is fetched (via the dashboard's own "load more" button, if present) before deciding which filter groups have matches, so a group is never wrongly hidden just because its only matching course hadn't loaded yet
- Groups with no matching courses are hidden automatically, so the filter bar doesn't clutter the dashboard with irrelevant years
- One or more filters can be configured to be active by default when a user opens their dashboard
- JavaScript ships as a proper AMD module (`block_enhancedcourseoverview/filter`), scoped per block instance
- Maintains all original Course Overview block functionality

## Installation

1. Download the plugin files
2. Create a folder called `enhancedcourseoverview` in your Moodle `blocks` directory
3. Extract the plugin files into this directory
4. Visit your Moodle site as an administrator to complete the installation
5. Recommended: from your Moodle root, run `grunt amd --root=blocks/enhancedcourseoverview` to regenerate `amd/build/filter.min.js` with Moodle's own build tooling. The committed build file is a hand-written equivalent kept for out-of-the-box use, but a proper Grunt build is the canonical minified/versioned artifact.

## Configuration

Go to Site Administration > Plugins > Blocks > Enhanced Course Overview. There are two settings:

### Filter Definitions

```
Group Name
Filter Title|Pattern to Match

Group Name 2
Filter Title|Pattern to Match
Filter Title 2|Pattern to Match
```

Each line without a pipe (|) character starts a new group. Lines with pipes define a filter button, where the text before the pipe is the button label and the text after is the pattern to match in course titles. Leave an empty line between groups.

Example:
```
2023-24
Term 1|_A_1_202324
Term 2|_A_2_202324
Term 3|_A_3_202324

2024-25
Term 1|_A_1_202425
Term 2|_A_2_202425
Term 3|_A_3_202425
```

To add a new year, add a new block of lines following the same pattern (there's no auto-generation — this is entirely manual by design, so it's easy to read and predict).

### Default active filters

A comma or newline separated list of exact **patterns** (not titles) that should already be selected when a user opens their dashboard, e.g. the current term:

```
_A_2_202526
```

This applies every time the block renders — it is not a per-user preference the user can change permanently, just an initial state they can still toggle off.

## Usage

1. Add the "Enhanced Course Overview" block to your dashboard
2. Use the filter buttons to show only courses matching specific patterns
3. Click a button to activate the filter, click again to deactivate; click a group's header to toggle the whole group
4. Multiple filters can be active simultaneously (OR logic)
5. When the block loads, it fetches every page of courses (if the dashboard paginates via a "load more" button) before deciding which filter groups have at least one match. Groups with no matches are hidden. This means there can be a brief "Loading all courses..." moment right after the dashboard loads on sites with many courses, since every course is being loaded up front rather than only when a filter is clicked.

## Requirements

- Moodle 4.2 or later
- The standard Course Overview block must be installed

## License

This plugin is licensed under the GNU GPL v3 or later. See the LICENSE file for details.
