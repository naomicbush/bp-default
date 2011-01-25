<?php

/*******************************************************************************
 * Screen Functions
 *
 * Screen functions are the controllers of BuddyPress. They will execute when
 * their specific URL is caught. They will first save or manipulate data using
 * business functions, then pass on the user to a template file.
 */

/**
 * Activity index
 *
 * @global obj $bp
 */
function bp_activity_screen_index() {
	global $bp;

	if ( !bp_displayed_user_id() && bp_is_current_component( 'activity' ) && !bp_current_action() ) {
		$bp->is_directory = true;

		do_action( 'bp_activity_screen_index' );

		bp_core_load_template( apply_filters( 'bp_activity_screen_index', 'activity/index' ) );
	}
}
add_action( 'bp_screens', 'bp_activity_screen_index' );

function bp_activity_screen_my_activity() {
	do_action( 'bp_activity_screen_my_activity' );
	bp_core_load_template( apply_filters( 'bp_activity_template_my_activity', 'members/single/home' ) );
}

function bp_activity_screen_friends() {
	global $bp;

	if ( !bp_is_active( 'friends' ) )
		return false;

	if ( !is_super_admin() )
		$bp->is_item_admin = false;

	do_action( 'bp_activity_screen_friends' );
	bp_core_load_template( apply_filters( 'bp_activity_template_friends_activity', 'members/single/home' ) );
}

function bp_activity_screen_groups() {
	global $bp;

	if ( !bp_is_active( 'groups' ) )
		return false;

	if ( !is_super_admin() )
		$bp->is_item_admin = false;

	do_action( 'bp_activity_screen_groups' );
	bp_core_load_template( apply_filters( 'bp_activity_template_groups_activity', 'members/single/home' ) );
}

function bp_activity_screen_favorites() {
	global $bp;

	if ( !is_super_admin() )
		$bp->is_item_admin = false;

	do_action( 'bp_activity_screen_favorites' );
	bp_core_load_template( apply_filters( 'bp_activity_template_favorite_activity', 'members/single/home' ) );
}

function bp_activity_screen_mentions() {
	global $bp;

	if ( !is_super_admin() )
		$bp->is_item_admin = false;

	do_action( 'bp_activity_screen_mentions' );
	bp_core_load_template( apply_filters( 'bp_activity_template_mention_activity', 'members/single/home' ) );
}

/**
 * bp_activity_remove_screen_notifications()
 *
 * Removes activity notifications from the notification menu when a user clicks on them and
 * is taken to a specific screen.
 *
 * @package BuddyPress Activity
 */
function bp_activity_remove_screen_notifications() {
	global $bp;

	bp_members_delete_notifications_by_type( $bp->loggedin_user->id, $bp->activity->id, 'new_at_mention' );
}
add_action( 'bp_activity_screen_my_activity',               'bp_activity_remove_screen_notifications' );
add_action( 'bp_activity_screen_single_activity_permalink', 'bp_activity_remove_screen_notifications' );
add_action( 'bp_activity_screen_mentions',                  'bp_activity_remove_screen_notifications' );

function bp_activity_screen_single_activity_permalink() {
	global $bp;

	// No displayed user or not viewing activity component
	if ( !bp_is_activity_component() )
		return false;

	// No current action or not a specific activity item
	if ( !bp_current_action() || !bp_is_current_action( 'p' ) || !isset( $bp->action_variables['0'] ) )
		return false;

	// RE-jig the action and action variables
	$bp->current_action = $bp->action_variables['0'];
	unset( $bp->action_variables['0'] );

	// Get the activity details
	$activity = bp_activity_get_specific( array( 'activity_ids' => bp_current_action() ) );

	if ( !$activity = $activity['activities'][0] )
		bp_core_redirect( $bp->root_domain );

	// Default access is true
	$has_access = true;

	// If activity is from a group, do an extra cap check
	if ( isset( $bp->groups->id ) && $activity->component == $bp->groups->id ) {

		// Activity is from a group, but groups is currently disabled
		if ( !bp_is_active( 'groups') )
			bp_core_redirect( $bp->root_domain );

		// Check to see if the group is not public, if so, check the
		// user has access to see this activity
		if ( $group = groups_get_group( array( 'group_id' => $activity->item_id ) ) ) {

			// Group is not public
			if ( 'public' != $group->status ) {

				// User is not a member of group
				if ( !groups_is_user_member( $bp->loggedin_user->id, $group->id ) ) {
					$has_access = false;
				}
			}
		}
	}

	// Allow access to be filtered
	$has_access = apply_filters( 'bp_activity_permalink_access', $has_access, &$activity );

	// Allow additional code execution
	do_action( 'bp_activity_screen_single_activity_permalink', $activity, $has_access );

	// Access is specifically disallowed
	if ( false === $has_access ) {

		// User feedback
		bp_core_add_message( __( 'You do not have access to this activity.', 'buddypress' ), 'error' );

		// Redirect based on logged in status
		is_user_logged_in() ?
			bp_core_redirect( $bp->loggedin_user->domain ) :
			bp_core_redirect( site_url( 'wp-login.php?redirect_to=' . esc_url( $bp->root_domain . '/' . $bp->activity->slug . '/p/' . $bp->current_action ) ) );
	}

	bp_core_load_template( apply_filters( 'bp_activity_template_profile_activity_permalink', 'members/single/activity/permalink' ) );
}
add_action( 'bp_screens', 'bp_activity_screen_single_activity_permalink' );

function bp_activity_screen_notification_settings() {
	global $bp;

	if ( !$mention = get_user_meta( $bp->displayed_user->id, 'notification_activity_new_mention', true ) )
		$mention = 'yes';

	if ( !$reply = get_user_meta( $bp->displayed_user->id, 'notification_activity_new_reply', true ) )
		$reply = 'yes'; ?>

	<table class="notification-settings zebra" id="activity-notification-settings">
		<thead>
			<tr>
				<th class="icon">&nbsp;</th>
				<th class="title"><?php _e( 'Activity', 'buddypress' ) ?></th>
				<th class="yes"><?php _e( 'Yes', 'buddypress' ) ?></th>
				<th class="no"><?php _e( 'No', 'buddypress' )?></th>
			</tr>
		</thead>

		<tbody>
			<tr id="activity-notification-settings-mentions">
				<td>&nbsp;</td>
				<td><?php printf( __( 'A member mentions you in an update using "@%s"', 'buddypress' ), bp_core_get_username( $bp->displayed_user->id, $bp->displayed_user->userdata->user_nicename, $bp->displayed_user->userdata->user_login ) ) ?></td>
				<td class="yes"><input type="radio" name="notifications[notification_activity_new_mention]" value="yes" <?php checked( $mention, 'yes', true ) ?>/></td>
				<td class="no"><input type="radio" name="notifications[notification_activity_new_mention]" value="no" <?php checked( $mention, 'no', true ) ?>/></td>
			</tr>
			<tr id="activity-notification-settings-replies">
				<td>&nbsp;</td>
				<td><?php _e( "A member replies to an update or comment you've posted", 'buddypress' ) ?></td>
				<td class="yes"><input type="radio" name="notifications[notification_activity_new_reply]" value="yes" <?php checked( $reply, 'yes', true ) ?>/></td>
				<td class="no"><input type="radio" name="notifications[notification_activity_new_reply]" value="no" <?php checked( $reply, 'no', true ) ?>/></td>
			</tr>

			<?php do_action( 'bp_activity_screen_notification_settings' ) ?>
		</tbody>
	</table>

<?php
}
add_action( 'bp_notification_settings', 'bp_activity_screen_notification_settings', 1 );

?>
