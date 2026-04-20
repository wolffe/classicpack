/**
 * Bulk Post Type Editor — CodeMirror, list filters, featured image.
 */
document.addEventListener('DOMContentLoaded', function () {
	var contentTextarea = document.getElementById('classicpack-bpte-content');

	if (contentTextarea && typeof wp !== 'undefined' && wp.codeEditor) {
		try {
			var editor = wp.codeEditor.initialize(contentTextarea);
			var form = contentTextarea.closest('form');
			if (form) {
				form.addEventListener('submit', function () {
					if (editor && typeof editor.codemirror !== 'undefined') {
						contentTextarea.value = editor.codemirror.getValue();
					}
				});
			}
			if (editor && editor.codemirror) {
				editor.codemirror.setOption('extraKeys', {
					Tab: function (cm) {
						cm.replaceSelection('    ', 'end');
					},
					'Shift-Tab': function (cm) {
						var cursor = cm.getCursor();
						var line = cm.getLine(cursor.line);
						if (line.startsWith('    ')) {
							cm.replaceRange('', { line: cursor.line, ch: 0 }, { line: cursor.line, ch: 4 });
						}
					},
				});
			}
		} catch (error) {
			console.error('BPTE: CodeMirror init failed', error);
		}
	} else if (contentTextarea) {
		contentTextarea.addEventListener('keydown', function (e) {
			if (e.key === 'Tab') {
				e.preventDefault();
				var start = this.selectionStart;
				var end = this.selectionEnd;
				this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
				this.selectionStart = this.selectionEnd = start + 4;
			}
		});
	}

	var termSelector = document.getElementById('classicpack-bpte-term-selector');
	if (termSelector && termSelector.form) {
		termSelector.addEventListener('change', function () {
			this.form.submit();
		});
	}

	initFeaturedImageUpload();
});

function initFeaturedImageUpload() {
	var uploadButton = document.getElementById('classicpack-bpte-upload-btn');
	var removeButton = document.querySelector('.classicpack-bpte-remove-featured-image');
	var hiddenInput = document.getElementById('classicpack-bpte-featured-image');

	if (uploadButton) {
		uploadButton.addEventListener('click', function (e) {
			e.preventDefault();
			if (typeof wp !== 'undefined' && wp.media) {
				var mediaUploader = wp.media({
					title: 'Select Featured Image',
					button: { text: 'Use this image' },
					multiple: false,
				});
				mediaUploader.on('select', function () {
					var attachment = mediaUploader.state().get('selection').first().toJSON();
					if (hiddenInput) {
						hiddenInput.value = attachment.id;
					}
					updateFeaturedImagePreview(attachment.url, attachment.alt);
					if (uploadButton) {
						uploadButton.textContent = 'Change Image';
					}
				});
				mediaUploader.open();
			} else {
				alert('WordPress Media Library is not available. Please ensure you are logged in and on an admin page.');
			}
		});
	}

	if (removeButton) {
		removeButton.addEventListener('click', function (e) {
			e.preventDefault();
			if (confirm('Are you sure you want to remove the featured image?')) {
				if (hiddenInput) {
					hiddenInput.value = '';
				}
				var currentImageContainer = document.querySelector('.classicpack-bpte-current-featured-image');
				if (currentImageContainer) {
					currentImageContainer.remove();
				}
				if (uploadButton) {
					uploadButton.textContent = 'Set Featured Image';
				}
			}
		});
	}
}

function updateFeaturedImagePreview(imageUrl, altText) {
	var existingPreview = document.querySelector('.classicpack-bpte-current-featured-image');
	if (existingPreview) {
		existingPreview.remove();
	}
	var thumbnailUrl = imageUrl.replace(/(\.[^.]+)$/, '-150x150$1');
	var previewContainer = document.createElement('div');
	previewContainer.className = 'classicpack-bpte-current-featured-image';
	var img = document.createElement('img');
	img.src = thumbnailUrl;
	img.alt = altText || '';
	img.className = 'classicpack-bpte-featured-image-preview';
	var removeBtn = document.createElement('button');
	removeBtn.type = 'button';
	removeBtn.className = 'button classicpack-bpte-remove-featured-image';
	removeBtn.setAttribute(
		'data-post-id',
		document.getElementById('classicpack-bpte-upload-btn')
			? document.getElementById('classicpack-bpte-upload-btn').closest('form').querySelector('input[name="post_id"]').value
			: ''
	);
	removeBtn.textContent = 'Remove';
	previewContainer.appendChild(img);
	previewContainer.appendChild(removeBtn);
	var uploadContainer = document.querySelector('.classicpack-bpte-featured-image-upload');
	if (uploadContainer) {
		uploadContainer.parentNode.insertBefore(previewContainer, uploadContainer);
	}
	removeBtn.addEventListener('click', function (e) {
		e.preventDefault();
		if (confirm('Are you sure you want to remove the featured image?')) {
			var hi = document.getElementById('classicpack-bpte-featured-image');
			if (hi) {
				hi.value = '';
			}
			previewContainer.remove();
			var ub = document.getElementById('classicpack-bpte-upload-btn');
			if (ub) {
				ub.textContent = 'Set Featured Image';
			}
		}
	});
}
