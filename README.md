# Enhanced Course Overview
## A moodle plugin to add custom filters to the course overview dashboard

*Started as a proof of concept; hardened since, but still worth a careful review/staging test before rolling out to a live site.*

This plugin creates a new block that extends the core Course Overview Dashboard.

In Site Admin, you can configure the plugin's settings (not the block's instance settings) with user defineable groups of filters.

Originally a proof of concept tested on Moodle 4.5.3 with Boost-Union, coded under supervision by Claude. It has since been cleaned up for production readiness: debug/dev cruft (inline `error_log()` calls, an on-page debug panel, a raw settings dump on the admin page) has been removed, the JavaScript now lives in a proper AMD module instead of an inline `<script>` block, filter matching targets the course name specifically instead of the whole card's text, the "load more" pagination flow waits on actual DOM mutations instead of a fixed `setTimeout`, and a missing `addinstance` capability was added.

On top of that: filter groups now hide themselves when no course matches them, a group's header toggles every filter in it at once, one or more filters can be marked to be active by default, and the button styling was condensed to sit closer to Boost's own look. Deliberately kept manual-only for filter definitions (no auto-generation) so the config stays simple to read and predict.

### Known Issues
- To make the "hide empty groups" behaviour accurate, the block now eagerly loads every page of courses (via the dashboard's own "load more" button, if present) as soon as it renders, before deciding which groups have matches — rather than judging off just the first page. This trades a brief "Loading all courses..." moment on page load (on courses-heavy dashboards) for correctness. Sites using numbered pagination or an AJAX-only course list (rather than a load-more button) are not yet supported — this couldn't be verified without access to a live Moodle instance during this pass.
- Filters can clutter the dashboard's interface, though contextual group-hiding now helps with this.
- `amd/build/filter.min.js` was hand-written to mirror `amd/src/filter.js`, since this repo doesn't include Moodle's own Grunt build tooling. Once dropped into a full Moodle checkout, regenerate it with `grunt amd` before shipping.
- Still relies on regex-splicing filter buttons into the parent block's rendered HTML and on CSS selectors (`.course-card`, `.coursename`, `data-region="courses-view"`, etc.) that come from `block_myoverview`'s markup, so a future Moodle core update could break it. Test against your target Moodle version before upgrading.
- None of this has been exercised against a live Moodle instance during this pass (no Moodle install was available) — PHP syntax and the parsing/generation logic were unit-tested standalone, and the JS was checked for valid syntax only. Test in a staging site before rolling out.

See [The Plugin's readme](https://github.com/pd431/EnhancedCourseOverview/blob/main/block_enhancedcourseoverview/README.md) for configuration and use

![image](https://github.com/user-attachments/assets/3b444705-4f67-40cd-8bf4-9726ecd5a6c5)
