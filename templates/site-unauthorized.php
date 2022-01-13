<?php
/**
 * Template for unsuccessful authorization
 *
 * @package Intuto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'edit_posts' ) ) {
	wp_safe_redirect( home_url() );
	exit;
}

?>
<html>
<head>
	<title>There was a problem authorizing your site</title>
	<?php require plugin_dir_path( __FILE__ ) . 'inline-css.php'; ?>
</head>
<body>
<div class="container">
	<img src="<?php echo esc_url( plugin_dir_url( __DIR__ ) . '/assets/images/intuto.webp' ); ?>" alt="Image of Intuto Logo" class="logo" />
	<p class="content">
	<?php echo esc_html( __( 'There was a problem authorizing your site to use Intuto. Please ensure your API key and client ID are valid.', 'woocommerce-intuto-products' ) ); ?>
	</p>
</div>
</body>
</html>
