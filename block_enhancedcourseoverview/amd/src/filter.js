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
 * block_myoverview renders only a loading skeleton server-side; the actual
 * course cards, and its pagination (a "Show N / All" items-per-page
 * dropdown plus a Next/Previous paging bar - there is no "load more"
 * button), are built entirely client-side by block_myoverview's own AMD
 * module (core/paged_content_*) after an AJAX call. This module first waits
 * for that initial render, then - to decide accurately which filter groups
 * have matches, and to filter across every course rather than just the
 * active page - drives that same pagination: it selects "Show all" when
 * available (courses up to Moodle's own cap of 100, above which the option
 * isn't offered), otherwise clicks "Next" until it's exhausted. Because
 * block_myoverview keeps every page it has fetched in the DOM (hiding
 * inactive ones with a "hidden" class rather than removing them), once
 * loaded this module can filter across all of them by temporarily lifting
 * that hidden state, and puts it back exactly as block_myoverview left it
 * once every filter is cleared.
 *
 * @module     block_enhancedcourseoverview/filter
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Str from 'core/str';

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
 * "no courses" message) before this module touches anything.
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
     * @param {Object} strings Localised strings, keyed by 'loading', 'nomatches' and 'showing'.
     */
    constructor(coursesView, filterContainer, strings) {
        this.coursesView = coursesView;
        this.filterContainer = filterContainer;
        this.strings = strings;
        this.allLoaded = false;
        this.loadPromise = null;
        this.filtering = false;
        this.originalActivePage = null;

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
     * concurrently - callers share the same in-flight load.
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
     * Get the display text used to match each currently loaded course
     * against filter patterns.
     *
     * @return {String[]}
     */
    getCourseNames() {
        const names = Array.from(
            this.coursesView.querySelectorAll(SELECTORS.COURSE_NAME),
            el => el.textContent || ''
        );
        if (names.length) {
            return names;
        }

        // No dedicated course name element found, fall back to the whole card.
        return Array.from(this.coursesView.querySelectorAll(SELECTORS.COURSE_ITEM), el => el.textContent || '');
    }

    /**
     * Hide filter groups whose patterns match none of the currently loaded
     * courses, and show groups that do have at least one match. Called
     * once, after loadAllCourses() has resolved, so the decision reflects
     * the complete course list rather than whatever page loaded first.
     */
    updateGroupVisibility() {
        const courseNames = this.getCourseNames();

        this.filterContainer.querySelectorAll(SELECTORS.GROUP).forEach(group => {
            const patterns = Array.from(
                group.querySelectorAll(SELECTORS.FILTER_BUTTON),
                button => button.getAttribute('data-pattern')
            );
            const hasMatch = patterns.some(
                pattern => courseNames.some(name => patternMatches(name, pattern))
            );
            group.classList.toggle('enhancedcourseoverview-group-hidden', !hasMatch);
        });
    }

    /**
     * Show or hide course cards depending on whether their name matches one
     * of the active filter patterns. While any filter is active, every
     * loaded page is unhidden (block_myoverview otherwise only shows the
     * page it currently considers "active") and its own pagination controls
     * are hidden, since every course is already loaded and there's nothing
     * left to page through.
     *
     * @param {String[]} patterns Active filter patterns (OR'd together).
     */
    applyFilters(patterns) {
        this.coursesView.querySelectorAll(SELECTORS.PAGE).forEach(page => page.classList.remove('hidden'));
        const pagingControls = this.coursesView.querySelector(SELECTORS.PAGING_CONTROLS);
        if (pagingControls) {
            pagingControls.style.setProperty('display', 'none');
        }

        const cards = this.coursesView.querySelectorAll(SELECTORS.COURSE_ITEM);
        let visible = 0;

        cards.forEach(card => {
            const column = card.closest(SELECTORS.COLUMN) || card;

            const nameEl = card.querySelector(SELECTORS.COURSE_NAME);
            const haystack = (nameEl ? nameEl.textContent : card.textContent) || '';
            const matches = patterns.some(pattern => patternMatches(haystack, pattern));

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
     * Re-read which filter buttons are currently active and re-apply
     * filtering accordingly. Used both after a user interaction and once at
     * startup, to pick up any filters marked active by default.
     *
     * @return {Promise}
     */
    async refresh() {
        this.syncButtonStates();

        const active = this.filterContainer.querySelectorAll(`${SELECTORS.FILTER_BUTTON}.active`);
        const patterns = Array.from(active, button => button.getAttribute('data-pattern'));

        if (patterns.length === 0) {
            this.restoreLayout();
            this.countIndicator.textContent = '';
            this.toggleEmptyMessage(false);
            return;
        }

        this.saveLayout();

        if (!this.allLoaded) {
            this.countIndicator.textContent = this.strings.loading;
            await this.loadAllCourses();
        }

        this.applyFilters(patterns);
    }
}

/**
 * Initialise the filter buttons for one block instance.
 *
 * @param {String} uniqid The DOM id of this block instance's filter container.
 */
export const init = async(uniqid) => {
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
        return;
    }

    const [loading, nomatches, showing] = await Str.get_strings([
        {key: 'filter:loading', component: 'block_enhancedcourseoverview'},
        {key: 'filter:nomatches', component: 'block_enhancedcourseoverview'},
        {key: 'filter:showing', component: 'block_enhancedcourseoverview'},
    ]);

    const filter = new CourseFilter(coursesView, filterContainer, {loading, nomatches, showing});

    filterContainer.querySelectorAll(SELECTORS.FILTER_BUTTON).forEach(button => {
        button.addEventListener('click', event => {
            event.preventDefault();
            button.classList.toggle('active');
            filter.refresh();
        });
    });

    filterContainer.querySelectorAll(SELECTORS.GROUP_TOGGLE).forEach(toggle => {
        toggle.addEventListener('click', event => {
            event.preventDefault();
            const group = toggle.closest(SELECTORS.GROUP);
            const buttons = group.querySelectorAll(SELECTORS.FILTER_BUTTON);
            const allActive = Array.from(buttons).every(button => button.classList.contains('active'));
            buttons.forEach(button => button.classList.toggle('active', !allActive));
            filter.refresh();
        });
    });

    // block_myoverview renders only a placeholder skeleton server-side; wait
    // for its own AJAX call to render real courses before touching anything.
    await waitForInitialRender(coursesView);

    // Then load every course up front so which groups have matches can be
    // determined accurately, instead of guessing from just the first page
    // and revising that guess (visibly) as more courses stream in.
    if (coursesView.querySelector(SELECTORS.PAGING_BAR)) {
        filter.countIndicator.textContent = loading;
    }
    await filter.loadAllCourses();
    filter.updateGroupVisibility();
    await filter.refresh();
};
