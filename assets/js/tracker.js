/**
 * Umami WordPress Tracker
 * Enhanced event tracking with batching and retry logic
 *
 * @package UmamiWPConnect
 */

(function() {
    'use strict';

    // Configuration
    const config = {
        batchSize: window.umamiConfig?.batchSize || 10,
        batchInterval: window.umamiConfig?.batchInterval || 5000, // 5 seconds
        maxRetries: 3,
        retryDelay: 1000, // 1 second
        storageKey: 'umami_event_queue',
        debug: window.umamiConfig?.debug || false
    };

    // Event queue
    let eventQueue = [];
    let batchTimer = null;

    /**
     * Log debug message
     */
    function debug(...args) {
        if (config.debug) {
            console.log('[Umami Tracker]', ...args);
        }
    }

    /**
     * Load queue from localStorage
     */
    function loadQueue() {
        try {
            const stored = localStorage.getItem(config.storageKey);
            if (stored) {
                eventQueue = JSON.parse(stored);
                debug('Loaded queue from storage:', eventQueue.length, 'events');
            }
        } catch (e) {
            debug('Error loading queue:', e);
        }
    }

    /**
     * Save queue to localStorage
     */
    function saveQueue() {
        try {
            localStorage.setItem(config.storageKey, JSON.stringify(eventQueue));
            debug('Saved queue to storage:', eventQueue.length, 'events');
        } catch (e) {
            debug('Error saving queue:', e);
        }
    }

    /**
     * Add event to queue
     */
    function queueEvent(eventName, eventData = {}) {
        const event = {
            name: eventName,
            data: eventData,
            timestamp: Date.now(),
            retries: 0
        };

        eventQueue.push(event);
        saveQueue();
        debug('Event queued:', eventName, eventData);

        // Start batch timer if not already running
        if (!batchTimer) {
            batchTimer = setTimeout(processBatch, config.batchInterval);
        }

        // Process immediately if queue is full
        if (eventQueue.length >= config.batchSize) {
            clearTimeout(batchTimer);
            batchTimer = null;
            processBatch();
        }
    }

    /**
     * Process batch of events
     */
    function processBatch() {
        if (eventQueue.length === 0) {
            return;
        }

        const batch = eventQueue.splice(0, config.batchSize);
        debug('Processing batch:', batch.length, 'events');

        // Send each event (Umami doesn't support batch API yet)
        batch.forEach(event => {
            sendEvent(event);
        });

        saveQueue();

        // Schedule next batch if queue is not empty
        if (eventQueue.length > 0) {
            batchTimer = setTimeout(processBatch, config.batchInterval);
        } else {
            batchTimer = null;
        }
    }

    /**
     * Send event to Umami
     */
    function sendEvent(event) {
        if (typeof umami === 'undefined') {
            debug('Umami not loaded, requeueing event');
            eventQueue.push(event);
            saveQueue();
            return;
        }

        try {
            umami.track(event.name, event.data);
            debug('Event sent:', event.name, event.data);
        } catch (e) {
            debug('Error sending event:', e);
            
            // Retry logic
            if (event.retries < config.maxRetries) {
                event.retries++;
                setTimeout(() => {
                    eventQueue.push(event);
                    saveQueue();
                    processBatch();
                }, config.retryDelay * event.retries);
            } else {
                debug('Max retries reached for event:', event.name);
            }
        }
    }

    /**
     * Track scroll depth
     */
    let maxScrollDepth = 0;
    let scrollTimer = null;

    function trackScrollDepth() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const scrollPercent = Math.round((scrollTop / docHeight) * 100);

        if (scrollPercent > maxScrollDepth) {
            maxScrollDepth = scrollPercent;
        }

        clearTimeout(scrollTimer);
        scrollTimer = setTimeout(() => {
            queueEvent('scroll', {
                scroll_depth: maxScrollDepth,
                page_height: document.documentElement.scrollHeight
            });
        }, 1000);
    }

    /**
     * Track time on page
     */
    let pageLoadTime = Date.now();
    let lastActivityTime = Date.now();

    function trackTimeOnPage() {
        const timeOnPage = Math.round((Date.now() - pageLoadTime) / 1000);
        const activeTime = Math.round((lastActivityTime - pageLoadTime) / 1000);

        queueEvent('time_on_page', {
            time_on_page: timeOnPage,
            active_time: activeTime
        });
    }

    /**
     * Track user activity
     */
    function updateActivity() {
        lastActivityTime = Date.now();
    }

    /**
     * Initialize tracker
     */
    function init() {
        debug('Initializing tracker');

        // Load existing queue
        loadQueue();

        // Process any pending events
        if (eventQueue.length > 0) {
            processBatch();
        }

        // Track scroll depth
        window.addEventListener('scroll', trackScrollDepth, { passive: true });

        // Track user activity
        ['mousedown', 'keydown', 'touchstart', 'scroll'].forEach(event => {
            document.addEventListener(event, updateActivity, { passive: true });
        });

        // Track time on page before unload
        window.addEventListener('beforeunload', () => {
            trackTimeOnPage();
            // Force immediate processing
            if (eventQueue.length > 0) {
                processBatch();
            }
        });

        // Track visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                trackTimeOnPage();
            } else {
                pageLoadTime = Date.now();
                lastActivityTime = Date.now();
                maxScrollDepth = 0;
            }
        });

        debug('Tracker initialized');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose API
    window.umamiWPTracker = {
        track: queueEvent,
        processBatch: processBatch
    };

})();

