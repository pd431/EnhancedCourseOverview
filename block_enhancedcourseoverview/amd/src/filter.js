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
 * active filter patterns.
 *
 * @module     block_enhancedcourseoverview/filter
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Str from 'core/str';

const SELECTORS = {
    FILTER_BUTTON: '.filter-term-btn',
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
     * @param {Object} strings Localised strings, keyed by 'loading' and 'nomatches'.
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
     * Handle a filter button being toggled.
     */
    async onFilterToggled() {
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
            filter.onFilterToggled();
        });
    });
};
