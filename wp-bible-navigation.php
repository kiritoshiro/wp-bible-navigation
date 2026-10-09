<?php
/**
 * Plugin Name: Bible navigation
 * Plugin URI: https://github.com/kiritoshiro/wp-bible-navigation
 * Description: Bible study videos, audio and articles listed by Bible book, with counts.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Adventistai
 * License: GPL-3.0-or-later
 * Update URI: https://github.com/kiritoshiro/wp-bible-navigation
 * Text Domain: wp-bible-navigation
 */

defined( 'ABSPATH' ) || exit;

define( 'BNAV_VERSION', '0.1.0' );
define( 'BNAV_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-books.php';
require_once __DIR__ . '/includes/class-bible-navigation.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-updater.php';

Bible_Navigation::register();
Bible_Navigation_Admin::register();
Bible_Navigation_Updater::register();
