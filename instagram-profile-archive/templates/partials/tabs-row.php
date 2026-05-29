<?php
/**
 * Profile tabs partial.
 *
 * @package Instagram_Profile_Archive
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="ipa-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Content filters', 'instagram-profile-archive' ); ?>">
	<button type="button" class="ipa-tab ipa-tab-active" data-filter="grid" role="tab" aria-selected="true">
		<svg class="ipa-tab-icon ipa-tab-icon-grid" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="3" width="5.5" height="5.5"/><rect x="9.25" y="3" width="5.5" height="5.5"/><rect x="15.5" y="3" width="5.5" height="5.5"/><rect x="3" y="9.25" width="5.5" height="5.5"/><rect x="9.25" y="9.25" width="5.5" height="5.5"/><rect x="15.5" y="9.25" width="5.5" height="5.5"/><rect x="3" y="15.5" width="5.5" height="5.5"/><rect x="9.25" y="15.5" width="5.5" height="5.5"/><rect x="15.5" y="15.5" width="5.5" height="5.5"/></svg>
	</button>
	<button type="button" class="ipa-tab" data-filter="reels" role="tab" aria-selected="false">
		<svg class="ipa-tab-icon ipa-tab-icon-reels" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M10 8.5v7l6-3.5-6-3.5z" fill="currentColor" stroke="none"/></svg>
	</button>
</section>
