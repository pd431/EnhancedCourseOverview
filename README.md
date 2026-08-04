# Enhanced Course Overview
## A moodle plugin to add custom filters to the course overview dashboard

*Started as a proof of concept; hardened since, but still worth a careful review/staging test before rolling out to a live site.*

This plugin creates a new block that extends the core Course Overview Dashboard.

In Site Admin, you can configure the plugin's settings (not the block's instance settings) with user defineable groups of filters.

Originally a proof of concept tested on Moodle 4.5.3 with Boost-Union, coded under supervision by Claude. It has since been cleaned up for production readiness: debug/dev cruft (inline `error_log()` calls, an on-page debug panel, a raw settings dump on the admin page) has been removed, the JavaScript now lives in a proper AMD module instead of an inline `<script>` block, filter matching targets the course name specifically instead of the whole card's text, the "load more" pagination flow waits on actual DOM mutations instead of a fixed `setTimeout`, and a missing `addinstance` capability was added.

### Known Issues
- Filters only apply once all pages have been loaded via the "load more" button. Sites using numbered pagination or an AJAX-only course list (rather than a load-more button) are not yet supported — this couldn't be verified without access to a live Moodle instance during this pass.
- Filter style is inconsistent with the rest of Moodle.
- Filters can clutter the dashboard's interface.
- Filters can't be set as default values.
- `amd/build/filter.min.js` was hand-written to mirror `amd/src/filter.js`, since this repo doesn't include Moodle's own Grunt build tooling. Once dropped into a full Moodle checkout, regenerate it with `grunt amd` before shipping.
- Still relies on regex-splicing filter buttons into the parent block's rendered HTML and on CSS selectors (`.course-card`, `.coursename`, `data-region="courses-view"`, etc.) that come from `block_myoverview`'s markup, so a future Moodle core update could break it. Test against your target Moodle version before upgrading.

See [The Plugin's readme](https://github.com/pd431/EnhancedCourseOverview/blob/main/block_enhancedcourseoverview/README.md) for configuration and use

![image](https://github.com/user-attachments/assets/3b444705-4f67-40cd-8bf4-9726ecd5a6c5)
