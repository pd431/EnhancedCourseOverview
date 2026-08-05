# Enhanced Course Overview Block for Moodle

This plugin extends Moodle's Course Overview block to add simple text-based filters that allow users to quickly filter their courses.

## Features

- Simple text-based filters configured through the plugin settings, matched against course titles/codes as substrings, a digit wildcard, or full regex - not tied to any particular course code convention, since a pattern only needs to match the part of the code it cares about (typically just term+year), not the whole thing
- Filter buttons organized in groups; click a group's header to toggle every filter in that group at once
- On load, every course is fetched (by driving Moodle's own dashboard pagination - see "How course loading works" below) before deciding which filter groups have matches, so a group is never wrongly hidden just because its only matching course hadn't loaded yet
- Groups with no matching courses are hidden automatically, so the filter bar doesn't clutter the dashboard with irrelevant years
- One or more filters can be configured to be active by default when a user opens their dashboard
- JavaScript ships as a proper AMD module (`block_enhancedcourseoverview/filter`), scoped per block instance
- Buttons use plain Bootstrap classes (`.btn-outline-primary`/`.btn-outline-secondary`, `.btn-group-sm`) with no custom colours, border-radius, or shadow - they pick up whatever the active theme actually set, including a customised Boost/Boost Union theme
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
Term 1|_*1*_202324
Term 2|_*2*_202324
Term 3|_*3*_202324

2024-25
Term 1|_*1*_202425
Term 2|_*2*_202425
Term 3|_*3*_202425
```

To add a new year, add a new block of lines following the same pattern (there's no auto-generation — this is entirely manual by design, so it's easy to read and predict).

#### Pattern matching

A pattern matches if it appears **anywhere** in the course title or code — it doesn't need to describe the whole code, and usually shouldn't. Match only the part that identifies the term; leave your department/module/campus code out of the pattern entirely, whatever shape that happens to take. Two real institutions' course codes can look completely different (`MTH2030_A_1_202324` vs. `BEF3104DA_1F6O25_1_202627`) and still both work with the exact same term pattern, because neither pattern ever tries to describe the department/module/campus part — only the term+year suffix, which is what actually determines the filter:

```
Term 1|_*1*_202324
```

matches both `MTH2030_A_1_202324` and (for the 2024-25 pattern) `BEF3104DA_1F6O25_1_202425`, regardless of what precedes the term.

The `*` is a digit wildcard — see below.

#### Digit wildcard

Use `*` in a pattern to match a run of one or more digits. This is needed for course codes that combine multiple terms into one digit group, which a plain pattern can't match at all: a code like `CHE3005_A_23_202425` (spanning Term 2 and Term 3) contains neither `_2_202425` nor `_3_202425` as a substring. Instead, `_*2*_202425` matches Term 2 and `_*3*_202425` matches Term 3 — both match `_23_202425` wherever it appears, and each still matches a plain single-term code like `_2_202425` too. `*` only ever matches digits, never letters or other text, so it can't accidentally spill past the surrounding underscores. This is what the default filter definitions use.

#### Regex patterns

For anything the digit wildcard can't express, wrap a pattern in forward slashes to match it as a full regular expression instead, optionally followed by flags, e.g. `/pattern/i`. For example `/_[AB]_[0-9]*2[0-9]*_202425/` matches Term 2 whether the code has `_A_` or `_B_` immediately before the term.

### Default active filters

A comma or newline separated list of exact **patterns** (not titles) that should already be selected when a user opens their dashboard, e.g. the current term:

```
_*2*_202526
```

This applies every time the block renders — it is not a per-user preference the user can change permanently, just an initial state they can still toggle off.

## Usage

1. Add the "Enhanced Course Overview" block to your dashboard
2. Use the filter buttons to show only courses matching specific patterns
3. Click a button to activate the filter, click again to deactivate; click a group's header to toggle the whole group
4. Multiple filters can be active simultaneously (OR logic)
5. When the block loads, it fetches every course up front (see below) before deciding which filter groups have at least one match. Groups with no matches are hidden. This means there can be a brief "Loading all courses..." moment right after the dashboard loads on sites with many courses, since every course is being loaded up front rather than only when a filter is clicked.

## How course loading works

The standard Course Overview block (`block_myoverview`) doesn't have a "load more" button. It has a "Show 12 / 24 / 48 / 96 / All" dropdown and a Next/Previous pager, and it renders courses entirely client-side via AJAX (the server only ever sends a loading placeholder). To see every course - needed both for accurate group-hiding and for filtering to actually reach courses beyond the first page - this plugin's JavaScript:

1. Waits for `block_myoverview`'s own JavaScript to finish its first AJAX render (nothing exists in the DOM to query before that).
2. Prefers clicking "All" in the items-per-page dropdown, if it's offered - one request instead of many. Moodle only offers "All" for up to 100 total courses; above that it isn't shown at all.
3. If "All" isn't available, clicks "Next" repeatedly until it's exhausted. `block_myoverview` keeps every page it fetches in the DOM (hiding inactive ones rather than discarding them), so this doesn't repeat work.
4. While any filter is active, every loaded page is temporarily unhidden (so filtering can show matches from any page at once) and `block_myoverview`'s own pagination controls are hidden, since everything is already loaded and paging through it no longer applies. Clearing every filter restores `block_myoverview`'s pagination exactly as it was.

This was reverse-engineered by reading Moodle's actual `blocks/myoverview` and `lib/templates/paged_content_*`/`lib/amd/src/paged_content_*` source (branch `MOODLE_405_STABLE`), not guessed - an earlier version of this plugin assumed a "load more" button and a single wrapping course-list container that don't actually exist in Moodle, which meant that version's course-loading and empty-state logic were silently no-ops. It's still only been verified by reading source, not against a running Moodle site — test in staging.

## Requirements

- Moodle 4.2 or later
- The standard Course Overview block must be installed

## License

This plugin is licensed under the GNU GPL v3 or later. See the LICENSE file for details.
