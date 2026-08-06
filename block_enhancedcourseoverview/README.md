# Enhanced Course Overview Block for Moodle

This plugin extends Moodle's Course Overview block to add simple text-based filters that allow users to quickly filter their courses.

## Features

- Simple text-based filters configured through the plugin settings, matched against course titles/codes as substrings, a digit wildcard, or full regex - not tied to any particular course code convention, since a pattern only needs to match the part of the code it cares about (typically just term+year), not the whole thing
- Filter buttons organized in groups; click a group's header to toggle every filter in that group at once
- Which filter groups have matches is decided via a lightweight background request for course data - not by forcing every course to render - so opening the dashboard never changes what's visible or how far you need to scroll, whether or not a filter is active by default
- Groups with no matching courses are hidden automatically, so the filter bar doesn't clutter the dashboard with irrelevant years
- Filters stay in sync when you switch Moodle's own course-list controls (the All/Past/Future/etc. grouping, sort order, custom field), instead of silently going stale
- An automatic "Roles" filter appears whenever a user holds more than one role across their courses (e.g. Editor in some, Student in others), with no configuration needed. Filters within a group OR together (any Term 1 or Term 2 course); a Term filter and the Roles filter AND together (Term 2 courses where you're also an Editor), not OR
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

Go to Site Administration > Plugins > Blocks > Enhanced Course Overview. There is one setting:

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

Use `*` in a pattern to match a run of zero or more digits. This is needed for course codes that combine multiple terms into one digit group, which a plain pattern can't match at all: a code like `CHE3005_A_23_202425` (spanning Term 2 and Term 3) contains neither `_2_202425` nor `_3_202425` as a substring. Instead, `_*2*_202425` matches Term 2 and `_*3*_202425` matches Term 3 — both match `_23_202425` wherever it appears, and each still matches a plain single-term code like `_2_202425` too (matching zero extra digits either side of the required "2"). `*` only ever matches digits, never letters or other text, so it can't accidentally spill past the surrounding underscores. This is what the default filter definitions use.

#### Regex patterns

For anything the digit wildcard can't express, wrap a pattern in forward slashes to match it as a full regular expression instead, optionally followed by flags, e.g. `/pattern/i`. For example `/_[AB]_[0-9]*2[0-9]*_202425/` matches Term 2 whether the code has `_A_` or `_B_` immediately before the term.

### Default active filters

Mark a filter as active by default (already selected when a user opens their dashboard) directly on its filter definition line by adding `|default` to the end:

```
2025-26
Term 1|_*1*_202526
Term 2|_*2*_202526|default
Term 3|_*3*_202526|default
```

A pipe inside the pattern itself (e.g. regex alternation like `/_(A|B)_2_202425/`) is left alone - only a trailing `|default` is treated specially.

This applies every time the block renders — it is not a per-user preference the user can change permanently, just an initial state they can still toggle off.

## Usage

1. Add the "Enhanced Course Overview" block to your dashboard
2. Use the filter buttons to show only courses matching specific patterns
3. Click a button to activate the filter, click again to deactivate; click a group's header to toggle the whole group
4. Filters within the same group (or the same auto-generated Roles group) OR together - activating "Term 1" and "Term 2" shows courses matching either. A Term filter and a Role filter AND together - activating "Term 2" and "Editor" shows only Term 2 courses where you're also an Editor, not the union of the two
5. Opening the dashboard doesn't change what's shown or how far you have to scroll - group visibility is decided in the background (see below), and no course is force-loaded unless you (or a default) actually activate a filter, in which case there's a brief "Loading all courses..." moment while every matching course loads.

## How this works

### Deciding which filter groups to show, and role filtering

This plugin defines its own webservice, `block_enhancedcourseoverview_get_courses_with_roles` (see `classes/external/get_courses_with_roles.php`), to fetch the user's courses for the current view in the background - without touching or forcing anything to render. Rather than reimplementing course enrolment/classification logic, this webservice calls Moodle's own `core_course_external::get_enrolled_courses_by_timeline_classification()` directly (the exact function `block_myoverview` itself uses) and only adds each course's role(s) for the current user to its output, so this plugin stays riding on core's own logic instead of forking it - the same approach Moodle core itself uses in `core_course\external\get_enrolled_courses_with_action_events_by_timeline_classification` to add calendar event data. It only ever returns the logged-in user's own courses and own roles - there's no way to query another user's data through it. It deliberately requests core's default (full) course field set rather than restricting `$requiredfields` to just `id`/`fullname`: on at least one tested Moodle version, restricting it makes core's own return-value validation throw a coding exception, because core still needs a field like `enddate` internally to compute the classification but no longer declares it in the narrowed structure it validates its own output against.

This single background request drives two things:
- **Group visibility**: term/year groups are checked against course names as before; a group is hidden if none of its patterns match any course in the current view.
- **The Roles filter**: if the user holds more than one distinct role across their courses in the current view (e.g. Editor in some, Student in others), a "Roles" group is generated automatically - one button per distinct role, labelled with its normal Moodle display name. With only one role (the common case for most students), there's nothing meaningful to filter by, so no Roles group appears at all. It's rebuilt on every view change, preserving whichever role buttons were already active.

A `MutationObserver` watches the courses-view region for `block_myoverview` rebuilding its course list (the user switched the All/Past/Future/etc. grouping, sort order, or custom field selector). When that happens, this plugin re-runs the same background check (including rebuilding the Roles group), and - if a filter is currently active - re-applies it against the freshly rendered list. Without this, switching Moodle's own controls would silently leave a filter showing as "active" while doing nothing, since the course list it was filtering had just been replaced. One known gap: while the user is actively using `block_myoverview`'s own search box, group visibility is still checked against the last-selected grouping rather than the search results, since the search term isn't reflected anywhere group visibility can read it. It corrects itself once the search is cleared.

### Avoiding the flash of unfiltered filters, and caching the result client-side

The filter bar starts hidden (`enhancedcourseoverview-pending`, plain CSS `visibility: hidden` so its layout space is still reserved and nothing below it jumps) rather than rendering every group and then narrowing them down a moment later once the background fetch above resolves - the two looked identical in intent but the first flashes every group visible before hiding the ones with no matches, which is what this replaces.

To avoid paying that wait on every single page load, the last-known decision - which groups were hidden, the built Roles group, and whichever filters were actually active - is cached in the browser's `localStorage`, keyed by the current user's id and this block instance's id together (`block_enhancedcourseoverview:filters:<userid>:<uniqid>`), so it can never leak between different users sharing the same browser, and two people can't collide on the same key. On the next page load, if a cache entry exists for the current user, this block instance, and the current view (grouping/sort/custom field - a different one is simply treated as a miss), it's applied and the filter bar is revealed immediately, with no wait on the network at all; a background fetch still runs afterwards to keep that cache accurate for next time, updating quietly since the bar is already visible and usable throughout. Cache entries expire after 24 hours as a safety net against acting on very stale data (e.g. a role or enrolment that's since changed), and a cached selection takes precedence over a server-configured `|default` on subsequent visits, since it reflects the user's own last choice rather than the plugin's static configuration.

### Actually filtering, once a filter is activated

The standard Course Overview block doesn't have a "load more" button. It has a "Show 12 / 24 / 48 / 96 / All" dropdown and a Next/Previous pager, and it renders courses entirely client-side via AJAX (the server only ever sends a loading placeholder). To filter across every matching course, not just whichever page happens to be showing, this plugin's JavaScript - only once a filter is actually activated, never eagerly on page load:

1. Waits for `block_myoverview`'s own JavaScript to finish its first AJAX render (nothing exists in the DOM to query before that).
2. Prefers clicking "All" in the items-per-page dropdown, if it's offered - one request instead of many. Moodle only offers "All" for up to 100 total courses; above that it isn't shown at all.
3. If "All" isn't available, clicks "Next" repeatedly until it's exhausted. `block_myoverview` keeps every page it fetches in the DOM (hiding inactive ones rather than discarding them), so this doesn't repeat work.
4. While any filter is active, every loaded page is temporarily unhidden (so filtering can show matches from any page at once) and `block_myoverview`'s own pagination controls are hidden, since everything is already loaded and paging through it no longer applies. Clearing every filter restores `block_myoverview`'s pagination exactly as it was.

This applies equally whether the active filter is a Term pattern or a Role: role matching itself doesn't need any extra network request (it looks up each rendered card's `data-course-id` against the role data already fetched for group visibility), but every matching course still needs to actually be rendered as a card before it can be shown, hence loading every page regardless of which kind of filter triggered it.

This was reverse-engineered by reading Moodle's actual `blocks/myoverview` and `lib/templates/paged_content_*`/`lib/amd/src/paged_content_*` source (branch `MOODLE_405_STABLE`), not guessed - an earlier version of this plugin assumed a "load more" button and a single wrapping course-list container that don't actually exist in Moodle, which meant that version's course-loading and empty-state logic were silently no-ops. It's still only been verified by reading source, not against a running Moodle site — test in staging.

## Requirements

- Moodle 4.2 or later
- The standard Course Overview block must be installed

## License

This plugin is licensed under the GNU GPL v3 or later. See the LICENSE file for details.
