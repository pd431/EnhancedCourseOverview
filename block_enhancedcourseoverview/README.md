# Enhanced Course Overview Block for Moodle

This plugin extends Moodle's Course Overview block to add simple text-based filters that allow users to quickly filter their courses.

## Features

- Simple text-based filters configured through the plugin settings, plus an optional generator that builds a group of term filters for every academic year in a range automatically
- Filter buttons organized in groups; click a group's header to toggle every filter in that group at once
- Groups with no matching courses among those currently loaded are hidden automatically, so the filter bar doesn't clutter the dashboard with irrelevant years
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

Go to Site Administration > Plugins > Blocks > Enhanced Course Overview. There are three settings:

### Filter Definitions (manual groups)

For one-off, non-year-based groups. Format:

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
Custom group
Online only|_ONLINE_
Evening|_EVE_
```

### Year generator

Generates a group per academic year automatically, so a new year doesn't require editing settings by hand. One line per range:

```
startyear|endyear|termcount|title template|pattern template
```

Placeholders available in the title/pattern templates:
- `{n}` - the term number (1-based)
- `{ay}` - the full academic year, e.g. `202324`
- `{y1}` - the start year, e.g. `2023`
- `{y2}` - the two-digit end year, e.g. `24`

Example:
```
2023|2026|3|Term {n}|_A_{n}_{ay}
```
generates groups "2023-24" through "2026-27", each with Term 1, Term 2 and Term 3 buttons matching patterns like `_A_1_202324`. To add a new year, just bump the end year (e.g. `2023|2027|3|...` to include 2027-28) instead of writing a new block of lines.

Manually-defined groups and generated groups are combined; both are shown together.

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
5. Groups with no matches among the currently loaded courses are hidden; they reappear if more courses are loaded (e.g. via "load more") that do match

## Requirements

- Moodle 4.2 or later
- The standard Course Overview block must be installed

## License

This plugin is licensed under the GNU GPL v3 or later. See the LICENSE file for details.
