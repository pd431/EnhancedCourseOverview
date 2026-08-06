// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Course filter buttons for the Enhanced Course Overview block.
 *
 * Which filter groups have any matching course is decided from a
 * lightweight background call to this plugin's own webservice
 * (block_enhancedcourseoverview_get_courses_with_roles - a thin wrapper
 * around core's own course-fetching function that additionally attaches
 * the current user's role(s) in each course), asking only for course names
 * and roles - not by forcing every page of the visible course list to
 * render. This means opening the dashboard never changes what's visible or
 * how far a user has to scroll, whether or not any filter is active by
 * default. A MutationObserver on the courses-view region reacts whenever
 * Moodle's own grouping/sort/search controls change it, so filter groups
 * stay accurate and any currently-active filter is silently reapplied
 * against the new course list, instead of going stale.
 *
 * Filters are grouped into categories (year/term groups are all "term";
 * an auto-generated "Roles" group, built only when the user holds more
 * than one distinct role, is "role"). Filters within the same category
 * OR together; different categories AND together - e.g. activating
 * "Term 2" and "Editor" shows Term 2 courses where the user is an Editor,
 * not the union of the two.
 *
 * Actually filtering (hiding non-matching course cards once the user
 * activates a filter) still works against the rendered DOM, and still
 * needs every page loaded to filter across all of them - see
 * loadAllCoursesImpl() - but this now only happens when a filter is
 * actually activated, not eagerly on page load.
 *
 * @module     block_enhancedcourseoverview/filter
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Str from 'core/str';
import Ajax from 'core/ajax';

const SELECTORS = {
    FILTER_BUTTON: '.filter-term-btn',
    GROUP_TOGGLE: '.filter-group-toggle',
    GROUP: '.btn-group',
    COURSES_VIEW: '[data-region="courses-view"]',
    PAGING_BAR: '[data-region="paging-bar"]',
    PAGING_CONTROLS: '[data-region="paging-control-container"]',
    NEXT_CONTROL: '[data-control="next"]',
    PAGE_LINK: '[data-region="page-link"]',
    LIMIT_ALL_OPTION: '[data-limit="0"]',
    PAGE: '[data-region="paged-content-page"]',
    COURSE_ITEM: '[data-region="course-content"]',
    COURSE_NAME: '.coursename',
    COLUMN: '.col.d-flex, .col',
};

const AJAX_SETTLE_TIMEOUT_MS = 8000;
const INITIAL_RENDER_TIMEOUT_MS = 15000;
const INITIAL_RENDER_POLL_MS = 150;
const MAX_NEXT_CLICKS = 500;
const VIEW_CHANGE_DEBOUNCE_MS = 300;
const ROLE_CATEGORY = 'role';
const DEFAULT_CATEGORY = 'term';

// Bump when the cached shape below changes, so old entries are ignored
// instead of misread.
const CACHE_VERSION = 1;
const CACHE_PREFIX = 'block_enhancedcourseoverview:filters:';
const CACHE_TTL_MS = 24 * 60 * 60 * 1000;
const PENDING_CLASS = 'enhancedcourseoverview-pending';
const COURSES_PENDING_CLASS = 'enhancedcourseoverview-courses-pending';

// Cache of pattern string -> compiled RegExp (or null for a plain-substring
// pattern), shared across every filter instance on the page since patterns
// are static per page load.
const patternCache = new Map();

/**
 * Escape every regex-special character in a string so it matches itself
 * literally when used inside a RegExp.
 *
 * @param {String} text
 * @return {String}
 */
const escapeRegExp = text => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/**
 * Compile a pattern into a RegExp if it uses either of two opt-in syntaxes,
 * or return null to mean "match as a plain substring" (unchanged, original
 * behaviour). Every existing plain-text pattern (e.g. "_A_2_202425") uses
 * neither syntax, so it keeps matching exactly as before.
 *
 * 1. Digit wildcard: a "*" in the pattern matches a run of zero or more
 *    digits (not arbitrary text). Handles course codes that combine
 *    multiple terms into one digit group, which a plain substring pattern
 *    can't express: a code like "_A_23_202425" (spanning Term 2 and
 *    Term 3) doesn't contain "_A_2_202425" or "_A_3_202425" as a
 *    substring at all. A pattern of "_A_*2*_202425" matches it (and still
 *    matches a plain single-term code like "_A_2_202425" too).
 *
 * 2. Full regex: a pattern wrapped in slashes, e.g. "/pattern/" or with
 *    trailing flags "/pattern/i", is compiled as a regular expression
 *    as-is, for anything the digit wildcard can't express.
 *
 * @param {String} pattern
 * @return {?RegExp} The compiled regex, or null if this is a plain-substring pattern.
 */
const compilePattern = pattern => {
    const regexLiteral = /^\/(.*)\/([a-z]*)$/.exec(pattern);
    if (regexLiteral) {
        try {
            return new RegExp(regexLiteral[1], regexLiteral[2]);
        } catch (e) {
            // Invalid regex - fall back to treating it as a literal substring.
            return null;
        }
    }

    if (pattern.indexOf('*') !== -1) {
        return new RegExp(pattern.split('*').map(escapeRegExp).join('[0-9]*'));
    }

    return null;
};

/**
 * Test whether a course's display text matches a filter pattern, as either
 * a compiled regex (see compilePattern()) or a plain substring.
 *
 * @param {String} haystack The course's display text.
 * @param {String} pattern The filter pattern.
 * @return {Boolean}
 */
const patternMatches = (haystack, pattern) => {
    if (!patternCache.has(pattern)) {
        patternCache.set(pattern, compilePattern(pattern));
    }
    const regex = patternCache.get(pattern);
    return regex ? regex.test(haystack) : haystack.indexOf(pattern) !== -1;
};

/**
 * Wait until an element's subtree stops mutating (e.g. after Moodle's own
 * pagination fetches and renders a page via AJAX), or until a timeout
 * elapses.
 *
 * @param {Element} target Element to observe for new content.
 * @param {Number} timeout Maximum time to wait, in milliseconds.
 * @return {Promise}
 */
const waitForUpdate = (target, timeout) => new Promise(resolve => {
    let settled = false;
    const finish = () => {
        if (settled) {
            return;
        }
        settled = true;
        observer.disconnect();
        resolve();
    };
    const observer = new MutationObserver(finish);
    observer.observe(target, {childList: true, subtree: true});
    setTimeout(finish, timeout);
});

/**
 * Poll for block_myoverview's first real page of content (a
 * [data-region="paged-content-page"], containing either course cards or a
 * "no courses" message) before this module touches the rendered course
 * list.
 *
 * This deliberately does NOT treat the paging bar itself as "ready": Moodle
 * builds and inserts the paging bar synchronously, then immediately clicks
 * page 1 on its own to kick off the *first* course fetch - so the paging
 * bar exists well before any page has actually rendered. Moodle's own
 * paging bar ignores further clicks while a fetch is already in flight
 * (ignoreControlWhileLoading), so acting on the paging bar's mere presence
 * would race that first fetch: our own "load everything" click gets
 * silently swallowed, and we'd wrongly settle for whatever that first
 * fetch alone returned (e.g. just the first page of 12).
 *
 * @param {Element} coursesView The block's [data-region="courses-view"] element.
 * @return {Promise}
 */
const waitForInitialRender = coursesView => new Promise(resolve => {
    const start = Date.now();
    const check = () => {
        if (coursesView.querySelector(SELECTORS.PAGE)) {
            resolve();
            return;
        }
        if (Date.now() - start > INITIAL_RENDER_TIMEOUT_MS) {
            // Gives up rather than hangs, in case this never appears for
            // some unanticipated reason.
            resolve();
            return;
        }
        setTimeout(check, INITIAL_RENDER_POLL_MS);
    };
    check();
});

/**
 * Controller for a single block instance's filter UI.
 */
class CourseFilter {
    /**
     * @param {Element} coursesView The block_myoverview [data-region="courses-view"] element.
     * @param {Element} filterContainer The container holding the filter buttons.
     * @param {Object} strings Localised strings, keyed by 'loading', 'nomatches', 'showing' and 'rolesgroup'.
     * @param {Number} userid The current user's id, used to scope the browser-side cache so it never
     *                        crosses between different users of the same browser.
     */
    constructor(coursesView, filterContainer, strings, userid) {
        this.coursesView = coursesView;
        this.filterContainer = filterContainer;
        this.strings = strings;
        this.allLoaded = false;
        this.loadPromise = null;
        this.filtering = false;
        this.originalActivePage = null;
        this.syncing = false;
        // Scoped by user id (so switching to a different user on the same
        // browser never sees another user's cached filter state) and by
        // this block instance's own uniqid (so multiple instances, or
        // multiple sites sharing an origin, don't collide).
        this.cacheKey = `${CACHE_PREFIX}${userid}:${filterContainer.id}`;
        // Map of course id (string) -> Set of role shortnames, built from
        // the last background fetch. Used to filter rendered cards by role
        // via their data-course-id, without a further request per card.
        this.courseRolesById = new Map();

        this.countIndicator = document.createElement('div');
        this.countIndicator.className = 'course-count-indicator text-muted small';
        filterContainer.insertAdjacentElement('afterend', this.countIndicator);
    }

    /**
     * Record block_myoverview's own pagination state before this module
     * starts flattening pages together for filtering, so it can be restored
     * exactly once every filter is cleared.
     */
    saveLayout() {
        if (this.filtering) {
            return;
        }
        this.filtering = true;

        const pagingBar = this.coursesView.querySelector(SELECTORS.PAGING_BAR);
        this.originalActivePage = pagingBar ? pagingBar.getAttribute('data-active-page-number') : null;
    }

    /**
     * Undo saveLayout(): put block_myoverview's pagination back to showing
     * only the page that was active before filtering started, and clear any
     * display overrides this module made on individual course columns.
     */
    restoreLayout() {
        if (!this.filtering) {
            return;
        }
        this.filtering = false;

        this.coursesView.querySelectorAll(SELECTORS.COURSE_ITEM).forEach(card => {
            const column = card.closest(SELECTORS.COLUMN) || card;
            column.style.removeProperty('display');
        });

        if (this.originalActivePage !== null) {
            this.coursesView.querySelectorAll(SELECTORS.PAGE).forEach(page => {
                page.classList.toggle('hidden', page.getAttribute('data-page') !== this.originalActivePage);
            });
        }

        const pagingControls = this.coursesView.querySelector(SELECTORS.PAGING_CONTROLS);
        if (pagingControls) {
            pagingControls.style.removeProperty('display');
        }
    }

    /**
     * Load every course into the DOM: prefer selecting "Show all" from
     * block_myoverview's items-per-page dropdown (a single request), and
     * fall back to clicking "Next" until it's exhausted when "Show all"
     * isn't offered (Moodle hides it above 100 courses). Safe to call
     * concurrently - callers share the same in-flight load. Only called
     * once a filter is actually activated (see refresh()), never eagerly.
     *
     * @return {Promise}
     */
    loadAllCourses() {
        if (this.allLoaded) {
            return Promise.resolve();
        }

        if (!this.loadPromise) {
            this.loadPromise = this.loadAllCoursesImpl().finally(() => {
                this.allLoaded = true;
                this.loadPromise = null;
            });
        }

        return this.loadPromise;
    }

    /**
     * @return {Promise}
     */
    async loadAllCoursesImpl() {
        await waitForInitialRender(this.coursesView);

        const pagingBar = this.coursesView.querySelector(SELECTORS.PAGING_BAR);
        if (!pagingBar) {
            // Everything already fits on the one page that's rendered.
            return;
        }

        const allOption = this.coursesView.querySelector(SELECTORS.LIMIT_ALL_OPTION);
        if (allOption) {
            allOption.click();
            await waitForUpdate(this.coursesView, AJAX_SETTLE_TIMEOUT_MS);
            return;
        }

        // No "Show all" option (more than 100 courses) - page through
        // manually. Previously-loaded pages stay in the DOM (just hidden),
        // so nothing here is wasted work.
        let clicks = 0;
        let next = pagingBar.querySelector(SELECTORS.NEXT_CONTROL);
        while (next && !next.classList.contains('disabled') && clicks < MAX_NEXT_CLICKS) {
            const link = next.querySelector(SELECTORS.PAGE_LINK) || next;
            link.click();
            await waitForUpdate(this.coursesView, AJAX_SETTLE_TIMEOUT_MS);
            next = pagingBar.querySelector(SELECTORS.NEXT_CONTROL);
            clicks++;
        }
    }

    /**
     * Read the view parameters block_myoverview is currently using to fetch
     * its own course list, straight off the courses-view element's data
     * attributes (the same ones its own view.js reads and writes).
     *
     * @return {Object}
     */
    getViewParams() {
        return {
            classification: this.coursesView.getAttribute('data-grouping') || 'all',
            sort: this.coursesView.getAttribute('data-sort') || null,
            customfieldname: this.coursesView.getAttribute('data-customfieldname') || null,
            customfieldvalue: this.coursesView.getAttribute('data-customfieldvalue') || null,
        };
    }

    /**
     * A string identifying the current view (grouping/sort/custom field), so
     * a cached decision is only ever reused for the view it was actually
     * computed for.
     *
     * @return {String}
     */
    getViewKey() {
        const params = this.getViewParams();
        return [params.classification, params.sort, params.customfieldname, params.customfieldvalue].join('|');
    }

    /**
     * A stable identifier for a filter group, used as a cache key: the
     * dynamically-built Roles group (there's ever at most one) uses its
     * category; PHP-rendered term/year groups use their category plus their
     * configured group name (read off their toggle button's data-group,
     * since that's set on the button rather than the wrapping .btn-group).
     *
     * @param {Element} group
     * @return {String}
     */
    getGroupKey(group) {
        const category = this.getGroupCategory(group);
        if (category === ROLE_CATEGORY) {
            return ROLE_CATEGORY;
        }
        const toggle = group.querySelector(SELECTORS.GROUP_TOGGLE);
        return `${category}:${toggle ? toggle.getAttribute('data-group') : ''}`;
    }

    /**
     * Fetch the current user's courses matching the current view (grouping/
     * sort/custom field), with their role(s) in each, via this plugin's own
     * webservice - independent of, and without forcing, anything being
     * rendered. That webservice is a thin wrapper around core's own
     * course-fetching function (see the PHP class for details), so this
     * stays in sync with however Moodle's own enrolment/classification
     * rules work, rather than this module reimplementing them.
     *
     * Note: while a user is actively using block_myoverview's own search
     * box, this still fetches by the last-selected grouping rather than the
     * search results, since the search term isn't reflected in courses-view's
     * data attributes. Group visibility can therefore be briefly inaccurate
     * during an active search; it corrects itself once the search is
     * cleared.
     *
     * @return {Promise<?Array>} Courses ({id, fullname, roles}), or null if the request failed.
     */
    async fetchCourseData() {
        const params = this.getViewParams();
        try {
            const response = await Ajax.call([{
                methodname: 'block_enhancedcourseoverview_get_courses_with_roles',
                args: {
                    classification: params.classification,
                    sort: params.sort,
                    customfieldname: params.customfieldname,
                    customfieldvalue: params.customfieldvalue,
                },
            }])[0];
            return response.courses || [];
        } catch (e) {
            // eslint-disable-next-line no-console
            console.error('block_enhancedcourseoverview: failed to fetch course/role data', e);
            return null;
        }
    }

    /**
     * Get the effective filter category for a group element: whatever its
     * data-category says, or the default "term" category if unset (every
     * PHP-rendered year/term group).
     *
     * @param {Element} group
     * @return {String}
     */
    getGroupCategory(group) {
        return group.dataset.category || DEFAULT_CATEGORY;
    }

    /**
     * Build (or rebuild) the "Roles" filter group from the distinct roles
     * found across the given course data, preserving whichever role
     * buttons were already active. Only created at all when the user holds
     * more than one distinct role - with just one, a role filter has
     * nothing meaningful to narrow down.
     *
     * @param {Array} courseData
     */
    rebuildRolesGroup(courseData) {
        const existing = this.filterContainer.querySelector(`${SELECTORS.GROUP}[data-category="${ROLE_CATEGORY}"]`);
        const previouslyActive = existing ?
            new Set(Array.from(
                existing.querySelectorAll(`${SELECTORS.FILTER_BUTTON}.active`),
                button => button.getAttribute('data-pattern')
            )) :
            new Set();

        const roleNamesByShortname = new Map();
        courseData.forEach(course => {
            (course.roles || []).forEach(role => {
                if (!roleNamesByShortname.has(role.shortname)) {
                    roleNamesByShortname.set(role.shortname, role.name);
                }
            });
        });

        const roles = Array.from(roleNamesByShortname, ([shortname, name]) => ({shortname, name}));
        this.renderRolesGroup(roles, previouslyActive);
    }

    /**
     * (Re)build the "Roles" filter group from a plain list of distinct
     * roles - shared by rebuildRolesGroup() (from a fresh background fetch)
     * and applyCachedState() (from a cached list, before any fetch has
     * happened this page load). Only created at all when there's more than
     * one distinct role - with just one, a role filter has nothing
     * meaningful to narrow down.
     *
     * @param {Array} roles [{shortname, name}, ...]
     * @param {Set} activeShortnames Shortnames that should render as already active.
     */
    renderRolesGroup(roles, activeShortnames) {
        const existing = this.filterContainer.querySelector(`${SELECTORS.GROUP}[data-category="${ROLE_CATEGORY}"]`);
        if (existing) {
            existing.remove();
        }

        if (!roles || roles.length <= 1) {
            return;
        }

        const group = document.createElement('div');
        group.className = 'btn-group btn-group-sm mb-1';
        group.dataset.category = ROLE_CATEGORY;

        const header = document.createElement('button');
        header.type = 'button';
        header.className = 'btn btn-outline-secondary filter-group-toggle';
        header.setAttribute('aria-pressed', 'false');
        header.textContent = this.strings.rolesgroup;
        group.appendChild(header);

        roles.forEach(role => {
            const isactive = activeShortnames.has(role.shortname);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-primary filter-term-btn' + (isactive ? ' active' : '');
            button.setAttribute('data-pattern', role.shortname);
            button.setAttribute('aria-pressed', isactive ? 'true' : 'false');
            button.textContent = role.name;
            group.appendChild(button);
        });

        this.filterContainer.appendChild(group);
        this.wireGroup(group);
    }

    /**
     * Reveal the filter bar (see PENDING_CLASS on the template - it starts
     * hidden so it never flashes fully-visible-then-narrowed before this
     * module has decided what should actually show).
     */
    reveal() {
        this.filterContainer.classList.remove(PENDING_CLASS);
    }

    /**
     * Hide the actual rendered course list while a filter that's about to
     * be applied (from cache, or a server-rendered "|default") is still
     * being loaded/computed - otherwise block_myoverview's own unfiltered
     * render (and this module's own further course-loading, see
     * loadAllCourses()) would flash fully-visible first, only to shrink
     * once filtering actually catches up a moment later. Only ever called
     * when a filter is already known to be active before anything has
     * loaded; if none is, the course list is left alone entirely, exactly
     * as before. Visibility (not display) keeps its layout space reserved.
     */
    hideCourses() {
        this.coursesView.classList.add(COURSES_PENDING_CLASS);
    }

    /**
     * Undo hideCourses(). Safe to call unconditionally, even if the course
     * list was never hidden.
     */
    showCourses() {
        this.coursesView.classList.remove(COURSES_PENDING_CLASS);
    }

    /**
     * Read this block instance's cached filter state for the current view
     * (see getViewKey()), if any exists, isn't expired, and matches the
     * cache format this version of the code writes. Anything else - no
     * entry, wrong version, expired, different view, malformed - is treated
     * as a cache miss rather than risking acting on bad data.
     *
     * @return {?Object}
     */
    readCache() {
        try {
            const raw = window.localStorage.getItem(this.cacheKey);
            if (!raw) {
                return null;
            }
            const data = JSON.parse(raw);
            if (
                !data ||
                data.version !== CACHE_VERSION ||
                typeof data.updatedAt !== 'number' ||
                (Date.now() - data.updatedAt) > CACHE_TTL_MS ||
                data.viewKey !== this.getViewKey() ||
                !Array.isArray(data.hiddenGroups) ||
                !data.activePatterns || typeof data.activePatterns !== 'object' ||
                !Array.isArray(data.roles)
            ) {
                return null;
            }
            return data;
        } catch (e) {
            return null;
        }
    }

    /**
     * Persist the current group-visibility, Roles group, and active-filter
     * state to the browser-side cache, read straight off the live DOM
     * (already the source of truth by the time this is called) rather than
     * needing a fresh copy of the last fetch's data threaded through.
     * Purely an optimisation for next time this view loads - failures here
     * (storage disabled, full, private browsing) are safe to ignore.
     */
    writeCache() {
        try {
            const hiddenGroups = [];
            this.filterContainer.querySelectorAll(SELECTORS.GROUP).forEach(group => {
                if (group.classList.contains('enhancedcourseoverview-group-hidden')) {
                    hiddenGroups.push(this.getGroupKey(group));
                }
            });

            const activePatterns = {};
            this.filterContainer.querySelectorAll(`${SELECTORS.FILTER_BUTTON}.active`).forEach(button => {
                const group = button.closest(SELECTORS.GROUP);
                const category = group ? this.getGroupCategory(group) : DEFAULT_CATEGORY;
                if (!activePatterns[category]) {
                    activePatterns[category] = [];
                }
                activePatterns[category].push(button.getAttribute('data-pattern'));
            });

            const roleGroup = this.filterContainer.querySelector(`${SELECTORS.GROUP}[data-category="${ROLE_CATEGORY}"]`);
            const roles = roleGroup ? Array.from(
                roleGroup.querySelectorAll(SELECTORS.FILTER_BUTTON),
                button => ({shortname: button.getAttribute('data-pattern'), name: button.textContent})
            ) : [];

            window.localStorage.setItem(this.cacheKey, JSON.stringify({
                version: CACHE_VERSION,
                viewKey: this.getViewKey(),
                hiddenGroups,
                activePatterns,
                roles,
                updatedAt: Date.now(),
            }));
        } catch (e) {
            // Storage unavailable, full, or disabled - caching is an
            // optimisation only, safe to skip.
        }
    }

    /**
     * Apply a cached decision (see readCache()) to the DOM immediately, so
     * the filter bar can be revealed without waiting for a fresh background
     * fetch: hide whichever groups were hidden last time, rebuild the Roles
     * group from the cached role list, and restore whichever filters were
     * last active - which takes precedence over the server-rendered
     * "|default" markers, since it reflects the user's own last choice.
     *
     * @param {Object} cached
     */
    applyCachedState(cached) {
        this.filterContainer.querySelectorAll(SELECTORS.GROUP).forEach(group => {
            const key = this.getGroupKey(group);
            group.classList.toggle('enhancedcourseoverview-group-hidden', cached.hiddenGroups.indexOf(key) !== -1);
        });

        this.renderRolesGroup(cached.roles, new Set(cached.activePatterns[ROLE_CATEGORY] || []));

        this.filterContainer.querySelectorAll(SELECTORS.FILTER_BUTTON).forEach(button => {
            const group = button.closest(SELECTORS.GROUP);
            const category = group ? this.getGroupCategory(group) : DEFAULT_CATEGORY;
            const patterns = cached.activePatterns[category] || [];
            button.classList.toggle('active', patterns.indexOf(button.getAttribute('data-pattern')) !== -1);
        });

        this.syncButtonStates();
    }

    /**
     * Hide filter groups whose patterns match none of the user's courses in
     * the current view, and show groups that do have at least one match.
     * Also (re)builds the Roles group and the course id -> roles lookup
     * used by applyFilters(). Based on a background fetch (see
     * fetchCourseData()), not on what happens to be rendered, so this never
     * forces the visible course list to expand and is safe to call any
     * time, including before block_myoverview has rendered anything at all.
     *
     * @return {Promise}
     */
    async updateGroupVisibility() {
        const courseData = await this.fetchCourseData();
        if (courseData === null) {
            // Request failed - leave whatever state groups already have
            // rather than guessing (and potentially hiding everything, or
            // dropping role data) from no data.
            return;
        }

        this.courseRolesById = new Map(
            courseData.map(course => [String(course.id), new Set((course.roles || []).map(role => role.shortname))])
        );

        this.rebuildRolesGroup(courseData);

        this.filterContainer.querySelectorAll(SELECTORS.GROUP).forEach(group => {
            const category = this.getGroupCategory(group);
            const patterns = Array.from(
                group.querySelectorAll(SELECTORS.FILTER_BUTTON),
                button => button.getAttribute('data-pattern')
            );
            const hasMatch = patterns.some(pattern => courseData.some(course => category === ROLE_CATEGORY ?
                (course.roles || []).some(role => role.shortname === pattern) :
                patternMatches(course.fullname || '', pattern)));
            group.classList.toggle('enhancedcourseoverview-group-hidden', !hasMatch);
        });
    }

    /**
     * Show or hide course cards depending on whether they match the active
     * filters: patterns within the same category are OR'd together (any
     * one match is enough), different categories are AND'd (a course must
     * satisfy every category that has at least one active filter). While
     * any filter is active, every loaded page is unhidden (block_myoverview
     * otherwise only shows the page it currently considers "active") and
     * its own pagination controls are hidden, since every course is
     * already loaded and there's nothing left to page through.
     *
     * @param {Object} activeByCategory e.g. {term: [...patterns], role: [...shortnames]}
     */
    applyFilters(activeByCategory) {
        this.coursesView.querySelectorAll(SELECTORS.PAGE).forEach(page => page.classList.remove('hidden'));
        const pagingControls = this.coursesView.querySelector(SELECTORS.PAGING_CONTROLS);
        if (pagingControls) {
            pagingControls.style.setProperty('display', 'none');
        }

        const categories = Object.keys(activeByCategory).filter(category => activeByCategory[category].length > 0);
        const cards = this.coursesView.querySelectorAll(SELECTORS.COURSE_ITEM);
        let visible = 0;

        cards.forEach(card => {
            const column = card.closest(SELECTORS.COLUMN) || card;
            const nameEl = card.querySelector(SELECTORS.COURSE_NAME);
            const haystack = (nameEl ? nameEl.textContent : card.textContent) || '';
            const courseRoles = this.courseRolesById.get(card.getAttribute('data-course-id')) || new Set();

            const matches = categories.every(category => {
                const patterns = activeByCategory[category];
                if (category === ROLE_CATEGORY) {
                    return patterns.some(pattern => courseRoles.has(pattern));
                }
                return patterns.some(pattern => patternMatches(haystack, pattern));
            });

            if (matches) {
                column.style.removeProperty('display');
                visible++;
            } else {
                column.style.setProperty('display', 'none', 'important');
            }
        });

        this.toggleEmptyMessage(visible === 0);
        this.countIndicator.textContent = this.strings.showing.replace('{visible}', visible).replace('{total}', cards.length);
    }

    /**
     * Show or hide the "no courses match" message.
     *
     * @param {Boolean} show Whether the message should be shown.
     */
    toggleEmptyMessage(show) {
        let message = this.coursesView.querySelector('.enhancedcourseoverview-empty');

        if (!show) {
            if (message) {
                message.remove();
            }
            return;
        }

        if (!message) {
            message = document.createElement('div');
            message.className = 'alert alert-info enhancedcourseoverview-empty';
            message.textContent = this.strings.nomatches;
            this.coursesView.appendChild(message);
        }
    }

    /**
     * Update each button's aria-pressed attribute, and each group header's
     * active styling (Bootstrap's own .btn-outline-*.active look), to
     * reflect the current active buttons.
     */
    syncButtonStates() {
        this.filterContainer.querySelectorAll(SELECTORS.FILTER_BUTTON).forEach(button => {
            button.setAttribute('aria-pressed', button.classList.contains('active') ? 'true' : 'false');
        });

        this.filterContainer.querySelectorAll(SELECTORS.GROUP).forEach(group => {
            const buttons = group.querySelectorAll(SELECTORS.FILTER_BUTTON);
            const toggle = group.querySelector(SELECTORS.GROUP_TOGGLE);
            if (!toggle || buttons.length === 0) {
                return;
            }
            const activeCount = group.querySelectorAll(`${SELECTORS.FILTER_BUTTON}.active`).length;
            toggle.classList.toggle('active', activeCount === buttons.length);
            toggle.setAttribute('aria-pressed', activeCount === buttons.length ? 'true' : 'false');
        });
    }

    /**
     * Whether any filter button is currently active.
     *
     * @return {Boolean}
     */
    hasActiveFilters() {
        return this.filterContainer.querySelector(`${SELECTORS.FILTER_BUTTON}.active`) !== null;
    }

    /**
     * Re-read which filter buttons are currently active, grouped by
     * category, and re-apply filtering accordingly. Used both after a user
     * interaction and once at startup, to pick up any filters marked active
     * by default. Only loads every course (loadAllCourses()) when there's
     * actually a filter to apply - if none are active, this does nothing to
     * the rendered course list at all.
     *
     * @return {Promise}
     */
    async refresh() {
        this.syncButtonStates();

        const activeByCategory = {};
        this.filterContainer.querySelectorAll(`${SELECTORS.FILTER_BUTTON}.active`).forEach(button => {
            const group = button.closest(SELECTORS.GROUP);
            const category = group ? this.getGroupCategory(group) : DEFAULT_CATEGORY;
            if (!activeByCategory[category]) {
                activeByCategory[category] = [];
            }
            activeByCategory[category].push(button.getAttribute('data-pattern'));
        });

        if (Object.keys(activeByCategory).length === 0) {
            this.restoreLayout();
            this.countIndicator.textContent = '';
            this.toggleEmptyMessage(false);
            this.writeCache();
            return;
        }

        this.saveLayout();

        if (!this.allLoaded) {
            this.countIndicator.textContent = this.strings.loading;
            await this.loadAllCourses();
        }

        this.applyFilters(activeByCategory);
        this.writeCache();
    }

    /**
     * Wire up click handling for every filter button and group toggle
     * within a single group element. Used both for the PHP-rendered groups
     * at init, and for the dynamically-built Roles group.
     *
     * @param {Element} group
     */
    wireGroup(group) {
        group.querySelectorAll(SELECTORS.FILTER_BUTTON).forEach(button => {
            button.addEventListener('click', event => {
                event.preventDefault();
                button.classList.toggle('active');
                this.refresh();
            });
        });

        group.querySelectorAll(SELECTORS.GROUP_TOGGLE).forEach(toggle => {
            toggle.addEventListener('click', event => {
                event.preventDefault();
                const buttons = group.querySelectorAll(SELECTORS.FILTER_BUTTON);
                const allActive = Array.from(buttons).every(button => button.classList.contains('active'));
                buttons.forEach(button => button.classList.toggle('active', !allActive));
                this.refresh();
            });
        });
    }

    /**
     * React to block_myoverview rebuilding its course list (e.g. the user
     * switched its grouping, sort, or custom field selector): re-check which
     * filter groups have matches, and if a filter is currently active,
     * re-apply it against the freshly rendered list - otherwise it would
     * keep showing its "active" styling while silently no longer filtering
     * anything, since the DOM it was filtering was just replaced.
     *
     * @return {Promise}
     */
    async handleViewChanged() {
        if (this.syncing) {
            return;
        }
        this.syncing = true;

        try {
            // The DOM was rebuilt: previously-loaded/flattened pages and any
            // saved layout no longer correspond to anything real.
            this.allLoaded = false;
            this.filtering = false;

            await this.updateGroupVisibility();

            if (this.hasActiveFilters()) {
                await this.refresh();
            }

            this.writeCache();
        } finally {
            this.syncing = false;
        }
    }

    /**
     * Start watching the courses-view region for block_myoverview rebuilding
     * its course list, so filters stay in sync with it instead of silently
     * going stale. Debounced, since a single grouping change can cause
     * several mutations in quick succession as block_myoverview re-renders.
     */
    watchForViewChanges() {
        let debounceTimer = null;
        const observer = new MutationObserver(() => {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            debounceTimer = setTimeout(() => this.handleViewChanged(), VIEW_CHANGE_DEBOUNCE_MS);
        });
        observer.observe(this.coursesView, {childList: true, subtree: true});
    }
}

/**
 * Initialise the filter buttons for one block instance.
 *
 * The filter bar starts hidden (see PENDING_CLASS on the template) so it
 * never flashes fully-visible-then-narrowed while this waits to find out
 * which groups actually have matches. If a cached decision from a previous
 * page load exists for this user, this instance, and the current view, it's
 * applied and revealed immediately - no waiting on a fetch at all - with a
 * background fetch afterwards to keep that cache accurate for next time.
 * Otherwise, it waits on the real fetch before revealing anything.
 *
 * If a filter is already known to be active before anything has loaded -
 * from a cached selection, or a server-rendered "|default" - the course
 * list itself is also hidden until that filter has actually been applied,
 * so the user never sees the full unfiltered list appear and then shrink.
 * With no such filter, the course list is left alone entirely, exactly as
 * block_myoverview would render it on its own.
 *
 * @param {String} uniqid The DOM id of this block instance's filter container.
 * @param {Number} userid The current user's id, used to scope the cache.
 */
export const init = async(uniqid, userid) => {
    const filterContainer = document.getElementById(uniqid);
    if (!filterContainer) {
        return;
    }

    // Guard against being initialised twice on the same DOM, e.g. if
    // Moodle re-renders this block via AJAX without a full page reload.
    // Without this, every click listener below would get attached a
    // second time, and a single click would fire both, e.g. toggling a
    // button's own "active" class on then back off within the same tap.
    if (filterContainer.dataset.enhancedcourseoverviewInit) {
        return;
    }
    filterContainer.dataset.enhancedcourseoverviewInit = 'true';

    const block = filterContainer.closest('.block') || document.body;
    const coursesView = block.querySelector(SELECTORS.COURSES_VIEW);
    if (!coursesView) {
        // Nothing this module can do without it - reveal whatever the
        // server rendered rather than leaving the bar hidden forever.
        filterContainer.classList.remove(PENDING_CLASS);
        return;
    }

    const [loading, nomatches, showing, rolesgroup] = await Str.get_strings([
        {key: 'filter:loading', component: 'block_enhancedcourseoverview'},
        {key: 'filter:nomatches', component: 'block_enhancedcourseoverview'},
        {key: 'filter:showing', component: 'block_enhancedcourseoverview'},
        {key: 'filter:rolesgroup', component: 'block_enhancedcourseoverview'},
    ]);

    const filter = new CourseFilter(coursesView, filterContainer, {loading, nomatches, showing, rolesgroup}, userid);

    filterContainer.querySelectorAll(SELECTORS.GROUP).forEach(group => filter.wireGroup(group));

    filter.watchForViewChanges();

    const cached = filter.readCache();
    if (cached) {
        filter.applyCachedState(cached);
        filter.reveal();

        // A cached selection may have just marked filters active - hide
        // the course list until refresh() below has actually applied them,
        // so nothing flashes unfiltered first. No-op if nothing's active.
        if (filter.hasActiveFilters()) {
            filter.hideCourses();
        }
        try {
            await filter.refresh();
        } finally {
            filter.showCourses();
        }

        // Quietly refresh in the background to keep the cache accurate for
        // next time - the bar (and course list) are already visible and
        // usable throughout this, so nothing needs to wait on it.
        filter.updateGroupVisibility().then(async() => {
            if (filter.hasActiveFilters()) {
                await filter.refresh();
            }
            filter.writeCache();
            return null;
        }).catch(() => {
            // Best-effort background refresh - a failure here just means
            // the cache stays as it was, not a user-visible error.
        });
        return;
    }

    // No cache, but the server may still have rendered one or more filters
    // active via "|default" - hide the course list up front if so, same
    // reasoning as the cached branch above.
    if (filter.hasActiveFilters()) {
        filter.hideCourses();
    }

    try {
        // Group visibility (and the Roles group) come from a background
        // webservice call, not from anything rendered, so it doesn't need
        // to wait for block_myoverview's own AJAX render at all - it can
        // run immediately.
        await filter.updateGroupVisibility();
    } finally {
        // Always reveal, even if the fetch failed - better to show an
        // unfiltered bar than hide it forever.
        filter.reveal();
    }

    // Apply any filters marked active by default. If none are, this leaves
    // the rendered course list exactly as block_myoverview produced it -
    // no forced full-course load, no extra scroll.
    try {
        await filter.refresh();
    } finally {
        filter.showCourses();
    }
    filter.writeCache();
};
