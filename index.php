<?php
/**
 * Plugin Name: Pixbet Content Scraper
 * Description: A WordPress plugin to scrape content from Pixbet website.
 * Version: 1.0
 * Author: Michael Tallada
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'pixbet_scraper_admin_menu');

function pixbet_scraper_admin_menu() {
    add_options_page(
        'Pixbet Scraper Settings',
        'Pixbet Scraper',
        'manage_options',
        'pixbet-scraper',
        'pixbet_scraper_settings_page'
    );
}

function pixbet_scraper_settings_page() {
    ?>
    <div class="wrap">
        <h1>Pixbet Content Scraper Settings</h1>
        <h2>Available Shortcodes:</h2>
        <ul>
            <li><strong>[pixbet_home]</strong> - Main page content</li>
            <li><strong>[pixbet_casino]</strong> - Casino section</li>
            <li><strong>[pixbet_sports]</strong> - Sports betting section</li>
            <li><strong>[pixbet_promotions]</strong> - Promotions page</li>
            <li><strong>[pixbet_live]</strong> - Live betting section</li>
        </ul>
        <p>Use these shortcodes in your posts or pages to display the scraped content.</p>
        <p><strong>Note:</strong> All external links will be redirected to: https://seo813.pages.dev?agentid=Bet606</p>
        
        <h3>Cached Versions (Recommended):</h3>
        <ul>
            <li><strong>[pixbet_home_cached]</strong> - Cached main page (5 min cache)</li>
            <li><strong>[pixbet_casino_cached]</strong> - Cached casino section</li>
            <li><strong>[pixbet_sports_cached]</strong> - Cached sports section</li>
            <li><strong>[pixbet_promotions_cached]</strong> - Cached promotions</li>
            <li><strong>[pixbet_live_cached]</strong> - Cached live betting</li>
        </ul>
        
        <h3>Cache Management:</h3>
        <p>
            <button type="button" onclick="clearPixbetCache()" class="button button-secondary">Clear All Cache</button>
        </p>
        
        <script>
        function clearPixbetCache() {
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=clear_pixbet_cache&_wpnonce=' + encodeURIComponent('<?php echo wp_create_nonce('clear_pixbet_cache'); ?>')
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Cache cleared successfully!');
                } else {
                    alert('Error clearing cache.');
                }
            });
        }
        </script>
    </div>
    <?php
}

// Main page scraper shortcode
function pixbet_home_shortcode() {
    $url = 'https://www.pixbet.com/';
    return pixbet_scrape_content($url, 'home');
}
add_shortcode('pixbet_home', 'pixbet_home_shortcode');

// Casino section scraper shortcode
function pixbet_casino_shortcode() {
    $url = 'https://www.pixbet.com/casino';
    return pixbet_scrape_content($url, 'casino');
}
add_shortcode('pixbet_casino', 'pixbet_casino_shortcode');

// Sports betting scraper shortcode
function pixbet_sports_shortcode() {
    $url = 'https://www.pixbet.com/sports';
    return pixbet_scrape_content($url, 'sports');
}
add_shortcode('pixbet_sports', 'pixbet_sports_shortcode');

// Promotions scraper shortcode
function pixbet_promotions_shortcode() {
    $url = 'https://www.pixbet.com/promotions';
    return pixbet_scrape_content($url, 'promotions');
}
add_shortcode('pixbet_promotions', 'pixbet_promotions_shortcode');

// Live betting scraper shortcode
function pixbet_live_shortcode() {
    $url = 'https://www.pixbet.com/live';
    return pixbet_scrape_content($url, 'live');
}
add_shortcode('pixbet_live', 'pixbet_live_shortcode');

// Main scraping function
function pixbet_scrape_content($url, $type = 'home') {
    // Set custom user agent and headers to mimic a real browser
    $args = array(
        'timeout'     => 30,
        'redirection' => 5,
        'httpversion' => '1.1',
        'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'headers'     => array(
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Cache-Control' => 'max-age=0',
        ),
    );

    $response = wp_remote_get($url, $args);
    
    if (is_wp_error($response)) {
        return '<div class="pixbet-error">Failed to fetch data from ' . esc_url($url) . '. Error: ' . $response->get_error_message() . '</div>';
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        return '<div class="pixbet-error">HTTP Error ' . $response_code . ' when fetching from ' . esc_url($url) . '</div>';
    }

    $body = wp_remote_retrieve_body($response);
    
    if (empty($body)) {
        return '<div class="pixbet-error">No content received from the website.</div>';
    }

    // Load HTML
    $dom = new DOMDocument();
    libxml_use_internal_errors(true); // Suppress HTML parsing errors
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $body);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    // Try different selectors for content - adapt these based on Pixbet's structure
    $selectors = array(
        '//main',
        '//div[@id="root"]',
        '//div[@class="main-content"]',
        '//div[@class="content"]',
        '//div[contains(@class, "container")]',
        '//div[contains(@class, "page-content")]',
        '//div[contains(@class, "content-wrapper")]',
        '//article',
        '//section[contains(@class, "main")]',
        '//body'
    );
    
    $content_div = null;
    foreach ($selectors as $selector) {
        $nodes = $xpath->query($selector);
        if ($nodes->length > 0) {
            $content_div = $nodes->item(0);
            break;
        }
    }
    
    if (!$content_div) {
        return '<div class="pixbet-error">Content container not found. The website structure may have changed.</div>';
    }

    // List of elements to remove (ads, navigation, etc.)
    $elements_to_remove = array(
        // Common ad containers
        '//div[contains(@class, "ad")]',
        '//div[contains(@class, "advertisement")]',
        '//div[contains(@class, "banner")]',
        '//div[contains(@id, "ad")]',
        '//ins[@class="adsbygoogle"]',
        
        // Navigation and menu elements
        '//nav',
        '//header[not(contains(@class, "content"))]',
        '//footer',
        '//div[contains(@class, "header")]',
        '//div[contains(@class, "footer")]',
        '//div[contains(@class, "navigation")]',
        '//div[contains(@class, "menu")]',
        
        // Social media and sharing
        '//div[contains(@class, "social")]',
        '//div[contains(@class, "share")]',
        '//div[contains(@class, "sharing")]',
        
        // Comments and forms that might interfere
        '//div[@id="comments"]',
        '//div[contains(@class, "comment")]',
        '//form[contains(@class, "search")]',
        
        // Sidebar elements
        '//aside',
        '//div[contains(@class, "sidebar")]',
        '//div[contains(@class, "widget")]',
        
        // Scripts and styles that might interfere
        '//script',
        '//style[not(@type) or @type="text/css"]',
        '//noscript',
        
        // Login/Register forms and overlays
        '//div[contains(@class, "login")]',
        '//div[contains(@class, "register")]',
        '//div[contains(@class, "modal")]',
        '//div[contains(@class, "overlay")]',
        '//div[contains(@class, "popup")]',
        
        // Cookie notices and similar
        '//div[contains(@class, "cookie")]',
        '//div[contains(@class, "privacy")]',
        '//div[contains(@class, "gdpr")]',
    );

    // Remove unwanted elements
    foreach ($elements_to_remove as $query) {
        $nodes = $xpath->query($query);
        foreach ($nodes as $node) {
            if ($node && $node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    // Update image URLs to absolute paths
    $images = $content_div->getElementsByTagName('img');
    foreach ($images as $img) {
        $src = $img->getAttribute('src');
        if (!empty($src) && strpos($src, 'http') !== 0) {
            // Convert relative URLs to absolute
            if (strpos($src, '//') === 0) {
                $img->setAttribute('src', 'https:' . $src);
            } elseif (strpos($src, '/') === 0) {
                $img->setAttribute('src', 'https://www.pixbet.com' . $src);
            } else {
                $img->setAttribute('src', 'https://www.pixbet.com/' . ltrim($src, '/'));
            }
        }
        
        // Add loading="lazy" for better performance
        $img->setAttribute('loading', 'lazy');
    }

    // Update link URLs - ALL EXTERNAL LINKS NOW REDIRECT TO YOUR SPECIFIED URL
    $redirect_url = 'https://seo813.pages.dev?agentid=Bet606';
    $links = $content_div->getElementsByTagName('a');
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        
        // Skip empty hrefs and anchor links
        if (empty($href) || strpos($href, '#') === 0 || strpos($href, 'javascript:') === 0) {
            continue;
        }
        
        // Check if it's an external link or internal link from the scraped site
        $is_external = false;
        $original_url = $href;
        
        if (strpos($href, 'http') === 0) {
            // Absolute URL - check if it's external
            $parsed_url = parse_url($href);
            if ($parsed_url && isset($parsed_url['host']) && $parsed_url['host'] !== parse_url(get_site_url(), PHP_URL_HOST)) {
                $is_external = true;
            }
        } else {
            // Relative URL - convert to absolute first, then treat as external
            if (strpos($href, '/') === 0) {
                $original_url = 'https://www.pixbet.com' . $href;
            } else {
                $original_url = 'https://www.pixbet.com/' . ltrim($href, '/');
            }
            $is_external = true; // All scraped links are considered external
        }
        
        if ($is_external) {
            // Redirect all external links to your specified URL
            $link->setAttribute('href', $redirect_url);
            $link->setAttribute('target', '_blank');
            $link->setAttribute('rel', 'noopener noreferrer');
            
            // Add a data attribute to track the original URL if needed
            $link->setAttribute('data-original-url', $original_url);
        }
    }

    // Add custom CSS classes for styling
    if ($content_div->hasAttribute('class')) {
        $content_div->setAttribute('class', $content_div->getAttribute('class') . ' pixbet-content');
    } else {
        $content_div->setAttribute('class', 'pixbet-content');
    }

    // Get the HTML content
    $html_content = $dom->saveHTML($content_div);
    
    // Clean up and add wrapper
    $html_content = '<div class="pixbet-wrapper pixbet-' . esc_attr($type) . '">' . $html_content . '</div>';
    
    // Add comprehensive styling
    $html_content .= '<style>
        .pixbet-wrapper {
            max-width: 100%;
            overflow-x: auto;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .pixbet-wrapper img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
        }
        .pixbet-wrapper table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .pixbet-wrapper th,
        .pixbet-wrapper td {
            padding: 12px;
            border: 1px solid #e0e0e0;
            text-align: center;
        }
        .pixbet-wrapper th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
        }
        .pixbet-wrapper tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .pixbet-wrapper tr:hover {
            background-color: #e3f2fd;
            transition: background-color 0.3s ease;
        }
        .pixbet-error {
            color: #d32f2f;
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            padding: 20px;
            border: 1px solid #e57373;
            border-radius: 8px;
            margin: 15px 0;
            box-shadow: 0 2px 8px rgba(211, 47, 47, 0.1);
        }
        .pixbet-wrapper a {
            color: #1976d2;
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: all 0.3s ease;
        }
        .pixbet-wrapper a:hover {
            color: #0d47a1;
            border-bottom-color: #0d47a1;
        }
        /* Style for redirected links */
        .pixbet-wrapper a[data-original-url] {
            position: relative;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white !important;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
            margin: 4px 2px;
            border: none;
            box-shadow: 0 2px 4px rgba(76, 175, 80, 0.3);
        }
        .pixbet-wrapper a[data-original-url]:hover {
            background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(76, 175, 80, 0.4);
            border-bottom: none;
        }
        .pixbet-wrapper a[data-original-url]:hover::after {
            content: "Click to visit Pixbet";
            position: absolute;
            background: rgba(0,0,0,0.9);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            top: -40px;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .pixbet-wrapper h1, .pixbet-wrapper h2, .pixbet-wrapper h3 {
            color: #2c3e50;
            margin: 20px 0 10px 0;
        }
        .pixbet-wrapper p {
            margin: 10px 0;
        }
        /* Responsive design */
        @media (max-width: 768px) {
            .pixbet-wrapper {
                padding: 10px;
            }
            .pixbet-wrapper table {
                font-size: 14px;
            }
            .pixbet-wrapper th,
            .pixbet-wrapper td {
                padding: 8px;
            }
        }
    </style>';
    
    return $html_content;
}

// Add caching functionality
function pixbet_get_cached_content($url, $type, $cache_duration = 300) { // 5 minutes cache
    $cache_key = 'pixbet_scraper_' . md5($url . $type);
    $cached_content = get_transient($cache_key);
    
    if ($cached_content !== false) {
        return $cached_content . '<p style="font-size: 12px; color: #666; text-align: right; margin-top: 10px;"><em>Cached content</em></p>';
    }
    
    $content = pixbet_scrape_content($url, $type);
    set_transient($cache_key, $content, $cache_duration);
    
    return $content;
}

// Cached shortcode versions
function pixbet_home_cached_shortcode() {
    return pixbet_get_cached_content('https://www.pixbet.com/', 'home');
}
add_shortcode('pixbet_home_cached', 'pixbet_home_cached_shortcode');

function pixbet_casino_cached_shortcode() {
    return pixbet_get_cached_content('https://www.pixbet.com/casino', 'casino');
}
add_shortcode('pixbet_casino_cached', 'pixbet_casino_cached_shortcode');

function pixbet_sports_cached_shortcode() {
    return pixbet_get_cached_content('https://www.pixbet.com/sports', 'sports');
}
add_shortcode('pixbet_sports_cached', 'pixbet_sports_cached_shortcode');

function pixbet_promotions_cached_shortcode() {
    return pixbet_get_cached_content('https://www.pixbet.com/promotions', 'promotions');
}
add_shortcode('pixbet_promotions_cached', 'pixbet_promotions_cached_shortcode');

function pixbet_live_cached_shortcode() {
    return pixbet_get_cached_content('https://www.pixbet.com/live', 'live');
}
add_shortcode('pixbet_live_cached', 'pixbet_live_cached_shortcode');

// Clear cache function
function pixbet_clear_cache() {
    $urls = array(
        'https://www.pixbet.com/' => 'home',
        'https://www.pixbet.com/casino' => 'casino',
        'https://www.pixbet.com/sports' => 'sports',
        'https://www.pixbet.com/promotions' => 'promotions',
        'https://www.pixbet.com/live' => 'live'
    );
    
    foreach ($urls as $url => $type) {
        $cache_key = 'pixbet_scraper_' . md5($url . $type);
        delete_transient($cache_key);
    }
    
    wp_send_json_success('Cache cleared successfully');
}

// Add admin action to clear cache
add_action('wp_ajax_clear_pixbet_cache', 'pixbet_clear_cache');

// Activation hook
register_activation_hook(__FILE__, 'pixbet_scraper_activate');
function pixbet_scraper_activate() {
    // Clear any existing cache on activation
    pixbet_clear_cache();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'pixbet_scraper_deactivate');
function pixbet_scraper_deactivate() {
    // Clear cache on deactivation
    pixbet_clear_cache();
}

// Add JavaScript to handle click tracking and enhanced functionality
add_action('wp_footer', 'pixbet_add_click_tracking');
function pixbet_add_click_tracking() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Track clicks on redirected links
        const redirectedLinks = document.querySelectorAll('.pixbet-wrapper a[data-original-url]');
        redirectedLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                // Optional: Add analytics tracking here
                console.log('Pixbet redirected link clicked:', this.getAttribute('data-original-url'));
                
                // Optional: Add Google Analytics event tracking
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'click', {
                        'event_category': 'Pixbet Scraper',
                        'event_label': this.getAttribute('data-original-url'),
                        'value': 1
                    });
                }
            });
        });
        
        // Add smooth hover effects
        const pixbetWrapper = document.querySelectorAll('.pixbet-wrapper');
        pixbetWrapper.forEach(function(wrapper) {
            wrapper.style.opacity = '0';
            wrapper.style.transform = 'translateY(20px)';
            wrapper.style.transition = 'all 0.5s ease';
            
            setTimeout(function() {
                wrapper.style.opacity = '1';
                wrapper.style.transform = 'translateY(0)';
            }, 100);
        });
    });
    </script>
    <?php
}

// Add custom admin notice for successful setup
add_action('admin_notices', 'pixbet_scraper_admin_notice');
function pixbet_scraper_admin_notice() {
    if (isset($_GET['page']) && $_GET['page'] === 'pixbet-scraper') {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>Pixbet Scraper is active!</strong> Use the shortcodes to display Pixbet content on your site. All external links will redirect to your affiliate URL.</p>
        </div>
        <?php
    }
}
?>