(function () {
	'use strict';

	var localized = typeof classicpackAuthorAvatarImage === 'object' && classicpackAuthorAvatarImage !== null
		? classicpackAuthorAvatarImage
		: {};

	function byId( id ) {
		return document.getElementById( id );
	}

	var uploader;

	document.addEventListener( 'click', function ( ev ) {
		var uploadTrigger = ev.target.closest( '.easy-author-avatar-image-upload' );
		if ( uploadTrigger ) {
			ev.preventDefault();
			var img = byId( 'easy-author-avatar-image-custom' );
			var inputId = byId( 'easy-author-avatar-image-id' );
			var deleteBtn = byId( 'easy-author-avatar-image-delete-btn' );
			if ( ! img || ! inputId || typeof wp === 'undefined' || ! wp.media ) {
				return;
			}
			if ( uploader ) {
				uploader.open();
				return;
			}
			uploader = wp.media( {
				title: localized.mediaTitle || '',
				library: { type: 'image' },
				button: { text: localized.mediaButtonTitle || '' },
				multiple: false
			} );
			uploader.on( 'select', function () {
				var attachment = uploader.state().get( 'selection' ).first().toJSON();
				img.classList.remove( 'easy-author-avatar-image-hide' );
				if ( deleteBtn ) {
					deleteBtn.classList.remove( 'easy-author-avatar-image-hide' );
				}
				uploadTrigger.textContent = localized.changeButtonText || '';
				img.setAttribute( 'src', attachment.url );
				inputId.setAttribute( 'value', String( attachment.id ) );
			} );
			uploader.open();
			return;
		}

		var removeTrigger = ev.target.closest( '.easy-author-avatar-image-remove' );
		if ( removeTrigger ) {
			ev.preventDefault();
			var msg = localized.deleteConfirm || '';
			if ( ! window.confirm( msg ) ) {
				return;
			}
			var imgR = byId( 'easy-author-avatar-image-custom' );
			var inputR = byId( 'easy-author-avatar-image-id' );
			var deleteBtnR = byId( 'easy-author-avatar-image-delete-btn' );
			var uploadBtnR = byId( 'easy-author-avatar-image-upload' );
			if ( uploadBtnR ) {
				uploadBtnR.textContent = localized.uploadButtonText || '';
			}
			if ( imgR ) {
				imgR.setAttribute( 'src', '' );
				imgR.classList.add( 'easy-author-avatar-image-hide' );
			}
			if ( inputR ) {
				inputR.setAttribute( 'value', '' );
			}
			if ( deleteBtnR ) {
				deleteBtnR.classList.add( 'easy-author-avatar-image-hide' );
			}
		}
	} );
}());
