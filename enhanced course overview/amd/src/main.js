/**
 * Direct JavaScript implementation for Enhanced Course Overview
 * This version doesn't use AMD modules for compatibility
 */
(function() {
    // Wait for the page to be fully loaded
    window.addEventListener('load', function() {
        console.log('Enhanced Course Overview: Starting initialization');
        initEnhancedOverview();
    });

    /**
     * Initialize the Enhanced Course Overview functionality
     */
    function initEnhancedOverview() {
        console.log('Enhanced Course Overview: init called');
        
        // Find all blocks with our class
        const blocks = document.querySelectorAll('.block_enhancedcourseoverview');
        console.log('Found blocks:', blocks.length);
        
        if (blocks.length === 0) {
            console.error('No Enhanced Course Overview blocks found');
            return;
        }
        
        // Process each block
        blocks.forEach(function(root) {
            initializeBlock(root);
        });
    }
    
    /**
     * Initialize a single block instance
     */
    function initializeBlock(root) {
        console.log('Initializing block:', root);
        
        // Find all filter buttons within the block
        const filterButtons = root.querySelectorAll('[data-action="filter-courses"]');
        console.log('Found filter buttons:', filterButtons.length);
        
        if (filterButtons.length === 0) {
            console.error('No filter buttons found in block');
            return;
        }
        
        // State to track visible courses and current filter
        let currentFilter = '';
        let noCoursesWarning = null;
        
        /**
         * Apply the filter to courses
         */
        function applyFilter(filterValue) {
            console.log('Applying filter:', filterValue);
            
            const courseRegion = document.querySelector('[data-region="courses-view"]');
            
            if (!courseRegion) {
                console.error('Course region not found');
                return;
            }
            
            // Remove existing no courses warning if any
            if (noCoursesWarning) {
                noCoursesWarning.remove();
                noCoursesWarning = null;
            }
            
            // Reset all buttons to inactive state
            filterButtons.forEach(function(button) {
                if (button.classList.contains('btn-outline-secondary')) {
                    button.classList.remove('active');
                } else if (button.classList.contains('btn-primary')) {
                    // For the custom styled buttons
                    button.classList.remove('active');
                }
                button.setAttribute('aria-pressed', 'false');
            });
            
            // Set the clicked button as active
            let activeButton = null;
            for (let i = 0; i < filterButtons.length; i++) {
                if (filterButtons[i].getAttribute('data-filter-value') === filterValue) {
                    activeButton = filterButtons[i];
                    break;
                }
            }
            
            if (activeButton) {
                console.log('Setting active button:', activeButton.textContent.trim());
                activeButton.classList.add('active');
                activeButton.setAttribute('aria-pressed', 'true');
            }
            
            // Store the current filter
            currentFilter = filterValue;
            
            // Get all course elements - try different selectors
            let courseItems = [];
            const selectors = [
                '[data-region="course-content"]',
                '.course-listitem',
                '.course-summaryitem',
                '.course-card',
                '.dashboard-card',
                '[data-course-id]'
            ];
            
            // Try each selector
            for (const selector of selectors) {
                const items = courseRegion.querySelectorAll(selector);
                if (items && items.length > 0) {
                    // Convert NodeList to Array and add to courseItems
                    for (let i = 0; i < items.length; i++) {
                        courseItems.push(items[i]);
                    }
                    console.log(`Found ${items.length} course items with selector: ${selector}`);
                }
            }
            
            // Remove duplicates
            const uniqueItems = [];
            const seen = {};
            for (let i = 0; i < courseItems.length; i++) {
                const item = courseItems[i];
                if (!seen[item.outerHTML]) {
                    seen[item.outerHTML] = true;
                    uniqueItems.push(item);
                }
            }
            courseItems = uniqueItems;
            
            if (courseItems.length === 0) {
                console.error('No course items found to filter');
                return;
            } else {
                console.log(`Filtering ${courseItems.length} course items`);
            }
            
            let visibleCount = 0;
            
            // Loop through each course and check if it matches the filter
            courseItems.forEach(function(item) {
                const courseContent = item.textContent.toLowerCase();
                
                // When filter is empty, show all courses
                if (!filterValue) {
                    item.style.display = '';
                    visibleCount++;
                    return;
                }
                
                // Check if course content contains the filter text
                if (courseContent.includes(filterValue.toLowerCase())) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            console.log(`Filter applied, ${visibleCount} courses visible`);
            
            // Show a message if no courses match the filter
            if (visibleCount === 0 && filterValue) {
                noCoursesWarning = document.createElement('div');
                noCoursesWarning.className = 'alert alert-info';
                noCoursesWarning.textContent = 'No courses found matching the filter';
                
                const contentContainer = courseRegion.querySelector('[data-region="course-view-content"]');
                if (contentContainer) {
                    contentContainer.appendChild(noCoursesWarning);
                } else {
                    courseRegion.appendChild(noCoursesWarning);
                }
            }
        }
        
        // Add event listeners to filter buttons
        filterButtons.forEach(function(button) {
            console.log('Adding click listener to button:', button.textContent.trim());
            
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const filterValue = button.getAttribute('data-filter-value');
                console.log('Filter button clicked:', filterValue);
                applyFilter(filterValue);
            });
        });
        
        // Create a simple debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        // Event listener for when view changes in course overview block
        const handleViewChange = debounce(function() {
            if (currentFilter) {
                console.log('View changed, reapplying filter:', currentFilter);
                applyFilter(currentFilter);
            }
        }, 300);
        
        // Monitor for changes in the course view
        const observer = new MutationObserver(handleViewChange);
        const courseView = document.querySelector('[data-region="courses-view"]');
        
        if (courseView) {
            observer.observe(courseView, {
                childList: true, 
                subtree: true
            });
            
            // Also listen for click events on pagination controls
            const paginationControls = document.querySelectorAll('[data-control="next"], [data-control="previous"]');
            paginationControls.forEach(function(control) {
                control.addEventListener('click', function() {
                    setTimeout(function() {
                        if (currentFilter) {
                            console.log('Pagination changed, reapplying filter:', currentFilter);
                            applyFilter(currentFilter);
                        }
                    }, 300);
                });
            });
        }
        
        console.log('Enhanced Course Overview block initialization complete');
    }
})();