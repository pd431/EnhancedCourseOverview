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
 * Filters the courses rendered by block_myoverview by toggling the
 * visibility of course cards/list items whose name matches one of the
 * active filter patterns. Filter groups whose patterns match none of the
 * currently loaded courses are hidden, and a group's header button toggles
 * every filter within that group at once.
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
    LOAD_MORE_BUTTON: '[data-action="more-courses"]',
    COURSE_CONTENT: '[data-region="course-content"]',
    COURSE_ITEM: '.course-card, .list-group-item.course-listitem',
    COURSE_NAME: '.coursename',
    COLUMN: '.col.d-flex, .col',
};

const LOAD_MORE_TIMEOUT_MS = 5000;

/**
 * Wait until the course content region has finished mutating after a
 * "load more" click, or until a timeout elapses.
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
 * Controller for a single block instance's filter UI.
 */
class CourseFilter {
    /**
     * @param {Element} root The block instance's root element.
     * @param {Element} filterContainer The container holding the filter buttons.
     * @param {Object} strings Localised strings, keyed by 'loading', 'nomatches' and 'showing'.
     */
    constructor(root, filterContainer, strings) {
        this.root = root;
        this.filterContainer = filterContainer;
        this.strings = strings;
        this.courseContent = root.querySelector(SELECTORS.COURSE_CONTENT);
        this.allLoaded = false;
        this.originalLayout = null;

        this.countIndicator = document.createElement('div');
        this.countIndicator.className = 'course-count-indicator';
        filterContainer.insertAdjacentElement('afterend', this.countIndicator);

        if (this.courseContent) {
            // Courses can be added later, either by a user manually paging
            // through "load more", or by our own loadAllCourses(). Either
            // way, re-check which filter groups are still relevant.
            this.visibilityObserver = new MutationObserver(() => this.updateGroupVisibility());
            this.visibilityObserver.observe(this.courseContent, {childList: true, subtree: true});
        }
    }

    /**
     * Remember the untouched course list markup so it can be restored once
     * every filter is deactivated.
     */
    saveLayout() {
        if (this.originalLayout === null && this.courseContent) {
            this.originalLayout = this.courseContent.innerHTML;
        }
    }

    /**
     * Restore the course list to its state before any filter was applied.
     */
    restoreLayout() {
        if (this.courseContent && this.originalLayout !== null) {
            this.courseContent.innerHTML = this.originalLayout;
        }
    }

    /**
     * Repeatedly click the "load more courses" button, if present, until all
     * pages of courses have been loaded into the DOM.
     *
     * @return {Promise}
     */
    async loadAllCourses() {
        if (this.allLoaded || !this.courseContent) {
            return;
        }

        let loadMoreButton = this.root.querySelector(SELECTORS.LOAD_MORE_BUTTON);
        while (loadMoreButton) {
            loadMoreButton.click();
            await waitForUpdate(this.courseContent, LOAD_MORE_TIMEOUT_MS);
            loadMoreButton = this.root.querySelector(SELECTORS.LOAD_MORE_BUTTON);
        }

        this.allLoaded = true;
    }

    /**
     * Get the display text used to match each currently loaded course
     * against filter patterns.
     *
     * @return {String[]}
     */
    getCourseNames() {
        const names = Array.from(this.root.querySelectorAll(SELECTORS.COURSE_NAME), el => el.textContent || '');
        if (names.length) {
            return names;
        }

        // No dedicated course name element found, fall back to the whole card.
        return Array.from(this.root.querySelectorAll(SELECTORS.COURSE_ITEM), el => el.textContent || '');
    }

    /**
     * Hide filter groups whose patterns match none of the currently loaded
     * courses, and show groups that do have at least one match. This only
     * considers courses already present in the DOM: on a paginated
     * dashboard, a group may reappear once more courses are loaded.
     */
    updateGroupVisibility() {
        const courseNames = this.getCourseNames();

        this.filterContainer.querySelectorAll(SELECTORS.GROUP).forEach(group => {
            const patterns = Array.from(
                group.querySelectorAll(SELECTORS.FILTER_BUTTON),
                button => button.getAttribute('data-pattern')
            );
            const hasMatch = patterns.some(
                pattern => courseNames.some(name => name.indexOf(pattern) !== -1)
            );
            group.classList.toggle('enhancedcourseoverview-group-hidden', !hasMatch);
        });
    }

    /**
     * Show or hide course cards depending on whether their name matches one
     * of the active filter patterns.
     *
     * @param {String[]} patterns Active filter patterns (OR'd together).
     */
    applyFilters(patterns) {
        const cards = this.root.querySelectorAll(SELECTORS.COURSE_ITEM);
        let visible = 0;

        cards.forEach(card => {
            const column = card.closest(SELECTORS.COLUMN) || card;

            if (patterns.length === 0) {
                column.style.removeProperty('display');
                visible++;
                return;
            }

            const nameEl = card.querySelector(SELECTORS.COURSE_NAME);
            const haystack = (nameEl ? nameEl.textContent : card.textContent) || '';
            const matches = patterns.some(pattern => haystack.indexOf(pattern) !== -1);

            if (matches) {
                column.style.removeProperty('display');
                visible++;
            } else {
                column.style.setProperty('display', 'none', 'important');
            }
        });

        this.toggleEmptyMessage(patterns.length > 0 && visible === 0);
        this.countIndicator.textContent = patterns.length === 0 ?
            '' : this.strings.showing.replace('{visible}', visible).replace('{total}', cards.length);
    }

    /**
     * Show or hide the "no courses match" message.
     *
     * @param {Boolean} show Whether the message should be shown.
     */
    toggleEmptyMessage(show) {
        let message = this.root.querySelector('.enhancedcourseoverview-empty');

        if (!show) {
            if (message) {
                message.remove();
            }
            return;
        }

        if (!message && this.courseContent) {
            message = document.createElement('div');
            message.className = 'alert alert-info enhancedcourseoverview-empty';
            message.textContent = this.strings.nomatches;
            this.courseContent.appendChild(message);
        }
    }

    /**
     * Update each button's aria-pressed attribute, and each group header's
     * active/indeterminate styling, to reflect the current active buttons.
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
            toggle.classList.toggle('filter-group-toggle-partial', activeCount > 0 && activeCount < buttons.length);
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

        const hasMoreToLoad = !this.allLoaded && this.root.querySelector(SELECTORS.LOAD_MORE_BUTTON);
        if (hasMoreToLoad) {
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

    const root = filterContainer.closest('.block') || document.body;
    const [loading, nomatches, showing] = await Str.get_strings([
        {key: 'filter:loading', component: 'block_enhancedcourseoverview'},
        {key: 'filter:nomatches', component: 'block_enhancedcourseoverview'},
        {key: 'filter:showing', component: 'block_enhancedcourseoverview'},
    ]);

    const filter = new CourseFilter(root, filterContainer, {loading, nomatches, showing});

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

    filter.updateGroupVisibility();
    await filter.refresh();
};
