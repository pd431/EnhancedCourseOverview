# Enhanced Course Overview
## A moodle plugin to add custom filters to the course overview dashboard

*Started as a proof of concept; hardened since, but still worth a careful review/staging test before rolling out to a live site.*

This plugin creates a new block that extends the core Course Overview Dashboard.

In Site Admin, you can configure the plugin's settings (not the block's instance settings) with user defineable groups of filters.

Originally a proof of concept tested on Moodle 4.5.3 with Boost-Union, coded under supervision by Claude. It has since been cleaned up for production readiness: debug/dev cruft (inline `error_log()` calls, an on-page debug panel, a raw settings dump on the admin page) has been removed, the JavaScript now lives in a proper AMD module instead of an inline `<script>` block, filter matching targets the course name specifically instead of the whole card's text, the "load more" pagination flow waits on actual DOM mutations instead of a fixed `setTimeout`, and a missing `addinstance` capability was added.

On top of that: filter groups now hide themselves when no course matches them, a group's header toggles every filter in it at once, one or more filters can be marked to be active by default, and pattern matching supports a digit wildcard and full regex for course codes that don't fit a plain substring (e.g. a code spanning multiple terms in one field). Deliberately kept manual-only for filter definitions (no auto-generation) so the config stays simple to read and predict. Button styling was pared back to plain `.btn-outline-primary`/`.btn-outline-secondary` and `.btn-group-sm` - no custom colours, border-radius, or shadow overrides - so buttons pick up whatever the active theme actually set, including on themes that customise Bootstrap's variables.

A later pass corrected two real bugs the earlier pass's guesses had introduced, found by pulling Moodle's actual `blocks/myoverview` and `core/paged_content_*` source rather than assuming: (1) `block_myoverview` has no "load more" button — it has a "Show 12/24/48/96/All" dropdown plus Next/Previous, and previously-loaded pages stay in the DOM (hidden, not removed); the course-loading logic now drives that real component instead of clicking a button that doesn't exist. (2) `data-region="course-content"` is an attribute on each *individual* course card, not a wrapping list container as assumed — so `querySelector` for it was silently grabbing the first course card, making the old save/restore-layout and empty-state logic no-ops. Both are fixed; see the block's own README for how course loading now works.

### Known Issues
- To make the "hide empty groups" behaviour accurate, the block now eagerly loads every course as soon as it renders (see the block README's "How course loading works"), before deciding which groups have matches — rather than judging off just the first page. This trades a brief "Loading all courses..." moment on page load (on courses-heavy dashboards) for correctness.
- Filters can clutter the dashboard's interface, though contextual group-hiding now helps with this.
- `amd/build/filter.min.js` was hand-written to mirror `amd/src/filter.js`, since this repo doesn't include Moodle's own Grunt build tooling. Once dropped into a full Moodle checkout, regenerate it with `grunt amd` before shipping.
- Still relies on regex-splicing filter buttons into the parent block's rendered HTML and on the specific `data-region`/`data-control`/`data-limit` markup of `block_myoverview` and Moodle core's `core/paged_content_*` components (verified by reading their source on `MOODLE_405_STABLE`, not by guessing), so a future Moodle core update could break it. Test against your target Moodle version before upgrading.
- None of this has been exercised against a running Moodle instance — verification so far is reading Moodle's actual source for the real markup/behaviour, unit-testing the PHP parsing logic standalone, and checking the JS for valid syntax. Test in a staging site before rolling out.

See [The Plugin's readme](https://github.com/pd431/EnhancedCourseOverview/blob/main/block_enhancedcourseoverview/README.md) for configuration and use

![image](https://github.com/user-attachments/assets/3b444705-4f67-40cd-8bf4-9726ecd5a6c5)
