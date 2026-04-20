(function () {
	var el = wp.element.createElement;
	var PluginPostStatusInfo = wp.editPost.PluginPostStatusInfo;
	var ToggleControl = wp.components.ToggleControl;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;

	function ClassicPressSkipImagesToggle() {
		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		} );
		var editPost = useDispatch( 'core/editor' ).editPost;

		return el(
			PluginPostStatusInfo,
			null,
			el( ToggleControl, {
				label: 'Skip remote image download for this post',
				checked: meta._classicpress_skip_remote_images === 'yes',
				onChange: function ( value ) {
					editPost( { meta: { _classicpress_skip_remote_images: value ? 'yes' : '' } } );
				}
			} )
		);
	}

	wp.plugins.registerPlugin( 'classicpress-auto-save-images-toggle', { render: ClassicPressSkipImagesToggle } );
} )();
