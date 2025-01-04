<?php

namespace Pointilis\Pustaloka\WP\User;

class User {

    /**
     * Add more capabilities to user
     */
    public function extend_user_capabilities() {
        // Set new user capabilities
        $role = get_role( 'author' );

        // Post capabilities
        $role->add_cap( 'edit_post_meta' );
        $role->add_cap( 'add_post_meta' );
        $role->add_cap( 'edit_posts' );
        $role->add_cap( 'delete_posts' );
        $role->add_cap( 'publish_posts' );
        $role->add_cap( 'upload_files' );
        
        // User capabilities
        $role->add_cap( 'edit_user_meta' );
        $role->add_cap( 'add_user_meta' );
        $role->add_cap( 'edit_users' );
    }

}