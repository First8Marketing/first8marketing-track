=== First8 Marketing Track ===
Contributors: iskandarsulaili
Tags: analytics, tracking, umami, woocommerce, privacy
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

GDPR-compliant website analytics & WooCommerce event tracking with Umami integration

== Description ==

First8 Marketing Track is a WordPress analytics plugin that provides comprehensive website analytics and WooCommerce event tracking without cookies. The plugin integrates seamlessly with Umami Analytics to enable GDPR-compliant tracking for WordPress sites and online stores, and works optimally with [First8 Marketing Umami](https://github.com/First8Marketing/first8marketing-umami) which extends standard Umami with advanced analytics features and enhanced data processing capabilities.

**What You'll Achieve:**

- **Accurate Visitor Analytics:** Get precise visitor counts, page views, and engagement metrics without cookie banners or privacy concerns, giving you clean data about real user behavior.

- **Complete WooCommerce Insights:** Track every step of the customer journey from product views to purchases, identify cart abandonment points, and optimize your store's conversion funnel.

- **Automatic Event Tracking:** Watch as links, buttons, and forms are automatically tracked without manual configuration, saving hours of setup time while capturing valuable user interactions.

- **Content Performance Data:** Discover which content drives engagement and conversions, allowing you to double down on what works and improve underperforming pages.

- **Marketing Campaign ROI:** Measure exactly which campaigns and channels drive results with built-in UTM parameter tracking and conversion attribution.

**Core Capabilities:**

- **Privacy-First Analytics:** Cookie-free tracking that respects user privacy and complies with GDPR/CCPA regulations
- **WooCommerce Integration:** Automatic tracking of 15+ eCommerce events including product views, cart actions, and purchases
- **Visual Event Configuration:** Gutenberg block editor integration to add tracking to buttons and links visually
- **Real-Time Processing:** Immediate visibility into visitor activity and events
- **Automated Tracking:** Clicks, form submissions, downloads, and outbound links captured automatically
- **Custom Events:** Unlimited custom event creation for specific user interactions
- **Multi-Platform Support:** Desktop and mobile tracking with holistic user journey visibility

**Technical Features:**

- **Lightweight Tracker:** Under 2KB script with minimal performance impact (< 5ms per request)
- **Asynchronous Processing:** Event queue with automatic retry and deduplication
- **Self-Hosted Data:** Complete data ownership with Umami Analytics integration
- **Developer API:** 20+ WordPress hooks and filters for custom implementations
- **Consent Management:** Built-in support for user consent requirements
- **Error Resilience:** Automatic retry with exponential backoff for failed events

== Installation ==

1. **Install the Plugin:**

   - Download from GitHub Releases or WordPress plugin directory
   - Upload to WordPress Admin → Plugins → Add New → Upload Plugin
   - Activate the plugin

2. **Configure Umami Analytics Settings:**

   Navigate to Settings → First8 Marketing Track to configure your Umami analytics connection:

   **General Settings:**
   - **Website ID**: Your Umami website ID (found in your Umami dashboard under Website Details)
   - **Script URL**: URL to the Umami tracking script (typically your Umami instance URL + `/script.js`)
   - **API URL**: URL to the Umami API endpoint (typically your Umami instance URL + `/api/send`)

   **Tracking Settings:**
   - **Enable Tracking**: Toggle to enable/disable Umami tracking on your site
   - **Automated Link Tracking**: Enable automatic tracking of link clicks
   - **Automated Button Tracking**: Enable automatic tracking of button clicks
   - **Automated Form Tracking**: Enable automatic tracking of form submissions
   - **WooCommerce Tracking**: Enable WooCommerce event tracking (requires WooCommerce)

   **Advanced Settings:**
   - **Debug Mode**: Enable debug logging for troubleshooting
   - **Event Queue Size**: Configure the event queue buffer size
   - **Retry Attempts**: Set number of retry attempts for failed events

3. **Enable Tracking Features:**

   - Configure automation settings for link, button, and form tracking
   - Set up WooCommerce tracking if applicable
   - Customize privacy and consent settings

4. **Verify Tracking:**
   - Visit your site and check the browser console for tracking activity
   - Verify events appear in your Umami Analytics dashboard

== Frequently Asked Questions ==

= Do I need an Umami Analytics account? =
Yes, you need a self-hosted or cloud Umami Analytics instance to use this plugin.

= Is this plugin GDPR compliant? =
Yes, the plugin uses cookie-free tracking and respects user privacy by default.

= Does it work with WooCommerce? =
Yes, full WooCommerce integration is included with automatic eCommerce event tracking.

= What's the performance impact? =
Minimal - the tracking script is under 2KB and adds less than 5ms to page load times.

= Can I track custom events? =
Yes, you can create unlimited custom events through the Gutenberg editor or programmatically.

== Changelog ==

= 1.0.0 =

- Initial release with Umami Analytics integration
- WooCommerce event tracking support
- Automatic link, button, and form tracking
- Gutenberg block editor integration
- Privacy-compliant cookie-free tracking

== Upgrade Notice ==

= 1.0.0 =
Initial release of the plugin with comprehensive analytics tracking features.

== External Services ==

This plugin integrates with Umami Analytics for data processing and storage:

- **Service**: Umami Analytics (self-hosted or cloud) - **Recommended**: [First8 Marketing Umami](https://github.com/First8Marketing/first8marketing-umami) for enhanced features
- **Purpose**: Website analytics and event data processing
- **Data Collected**: Anonymous user behavior data, page views, events, and conversions
- **Privacy**: No personal data collected, fully GDPR compliant

**Why Choose First8 Marketing Umami over standard Umami?**
[First8 Marketing Umami](https://github.com/First8Marketing/f极irst8marketing-umami) extends the open-source Umami with advanced analytics features, enhanced data processing capabilities, and enterprise-grade performance optimizations specifically designed for marketing analytics and eCommerce tracking.
