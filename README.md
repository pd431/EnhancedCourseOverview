# Enhanced Course Overview
## A Proof of concept moodle plugin to add custom filters to the course overview dashboard

*NOT FOR PRODUCTION USE*

This plugin creates a new block that extends the core Course Overview Dashboard.

In Site Admin, you can configure the plugin's settings (not the block's instance settings) with user defineable groups of filters.

This is only a proof of concept, tested on Moodle 4.5.3, with Boost-Union, and coded under my supervision by Claude.

### Known Issues
- Filters only apply to current pagination. Elements on subsequent pages aren't loaded
- Changing pagination doesn't reload the filters
- Filter Style is inconsistent with the rest of moodle
- Filters can clutter the dashboard's interface.
- Filters can't be set as default values
- No AMD modules, javascript is part of the main class
- Way more. This is only an AI written concept plugin.

See [The Plugin's readme](https://github.com/pd431/EnhancedCourseOverview/blob/main/block_enhancedcourseoverview/README.md) for configuration and use

![image](https://github.com/user-attachments/assets/3b444705-4f67-40cd-8bf4-9726ecd5a6c5)
