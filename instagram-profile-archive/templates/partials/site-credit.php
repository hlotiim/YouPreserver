<?php
/**
 * Public archive site credit footer.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;

$author_name = defined( 'IPA_AUTHOR_NAME' ) ? IPA_AUTHOR_NAME : 'Roktim Saha';
$author_url  = defined( 'IPA_AUTHOR_URL' ) ? IPA_AUTHOR_URL : 'https://roktimsaha.com';
?>
<footer class="ipa-site-credit">
	<?php
	printf(
		/* translators: %s: author name linked to website */
		esc_html__( 'Built by %s', 'instagram-profile-archive' ),
		'<a href="' . esc_url( $author_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $author_name ) . '</a>'
	);
	?>
</footer>
