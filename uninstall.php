<?php

if( ! defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit();

delete_option('woocommerce_striper_settings');
