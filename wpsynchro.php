<?php
/*
  Plugin Name: WP Synchro
  Plugin URI: https://daev.tech/wpsynchro
  Description: Professional migration plugin for WordPress - Migration of database and files made easy
  Version: 1.11.3
  Author: WPSynchro
  Author URI: https://daev.tech
  Domain Path: /languages
  Text Domain: wpsynchro
  License: GPLv3
  License URI: http://www.gnu.org/licenses/gpl-3.0
 */

/**
 * 	Copyright (C) 2018 DAEV (email: support@daev.tech)
 *
 * 	This program is free software; you can redistribute it and/or
 * 	modify it under the terms of the GNU General Public License
 * 	as published by the Free Software Foundation; either version 2
 * 	of the License, or (at your option) any later version.
 *
 * 	This program is distributed in the hope that it will be useful,
 * 	but WITHOUT ANY WARRANTY; without even the implied warranty of
 * 	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * 	GNU General Public License for more details.
 *
 * 	You should have received a copy of the GNU General Public License
 * 	along with this program; if not, write to the Free Software
 * 	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

define('WPSYNCHRO_VERSION', '1.11.3');
define('WPSYNCHRO_DB_VERSION', '9');
define('WPSYNCHRO_NEWEST_MU_COMPATIBILITY_VERSION', '1.0.5');

// Load composer autoloader
require_once dirname(__FILE__) . '/vendor/autoload.php';

/**
 *  On plugin activation
 *  @since 1.0.0
 */
function wpsynchroActivation($networkwide)
{
    \WPSynchro\Utilities\Activation::activate($networkwide);
}
register_activation_hook(__FILE__, 'wpsynchroActivation');

/**
 *  On plugin deactivation
 *  @since 1.0.0
 */
function wpsynchroDeactivation()
{
    \WPSynchro\Utilities\Activation::deactivate();
}
register_deactivation_hook(__FILE__, 'wpsynchroDeactivation');

/**
 *  On plugin uninstall
 *  @since 1.6.0
 */
function wpsynchroUninstall()
{
    \WPSynchro\Utilities\Activation::uninstall();
}
register_uninstall_hook(__FILE__, 'wpsynchroUninstall');

/**
 *  Run WP Synchro
 *  @since 1.0.0
 */

$wpsynchro = new WPSynchro\WPSynchroBootstrap();
$wpsynchro->run();
// Hook into the save_post action to track post updates and new posts
add_action('save_post', 'track_post_changes', 10, 3);

// Hook into the trash_post action to track when posts are moved to trash
add_action('trash_post', 'track_post_changes', 10, 3);

// Hook into the untrash_post action to track when posts are restored from trash
add_action('untrash_post', 'track_post_changes', 10, 3);

// Hook into the before_delete_post action to track when posts are permanently deleted
add_action('before_delete_post', 'track_post_changes', 10, 3);




function track_post_changes($post_id, $post, $update = null) {
    // error_log('Post Details: ' . print_r($post->post_status, true));
    // error_log('update value'.$update);
    // Check if the post type is not a revision and the post status is not 'auto-draft'
  

    if ($post->post_type !== 'revision' && $post->post_status !== 'auto-draft') {
        if ($post->post_status === 'trash') {
            // Post moved to trash
            insert_post_change($post_id, $post->post_title, $post->post_type, 'deleted');
        } elseif ($post->post_status === 'publish') {
            // Post updated
            // Since this is an update, we shouldn't mark it as 'added'
            // We should check if the post existed before and update its status to 'updated'
            insert_post_change($post_id, $post->post_title, $post->post_type, 'added');
        }
    }
}

function insert_post_change($post_id, $post_title, $post_type, $action) {
    global $wpdb;
    $tablename = $wpdb->prefix . 'post_changes';

    // Check if a similar entry already exists
    $existing_entry = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$tablename} WHERE post_id = %d",
            $post_id
        )
    );
    
    // error_log('Database entry details: ' . print_r($existing_entry, true));
    if ($existing_entry) {
        // error_log('action: ' . print_r($action, true));
        // Update the existing entry
        // if($action == 'added'){
        //     $action = 'updated';

        // }
            $wpdb->update(
                $tablename,
                ['action' => $action, 'timestamp' => current_time('mysql')],
                ['post_id' => $post_id],
                ['%s', '%s'], // Format of the updated values
                ['%d'] // Format of the WHERE clause values
            );
    } else {
        // Insert a new entry
        $wpdb->insert(
            $tablename,
            [
                'post_id' => $post_id,
                'post_title' => $post_title,
                'post_type' => $post_type,
                'action' => $action,
                'timestamp' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
    }
}
