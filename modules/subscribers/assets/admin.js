document.addEventListener('DOMContentLoaded', function () {
	var button = document.getElementById('classicpack-subscribers-send');

	if (!button) {
		return;
	}

	button.addEventListener('click', function () {
		var subject = document.getElementById('classicpack-subscribers-subject');
		var form    = document.createElement('form');
		var fields  = {
			action: 'classicpack_subscribers_send',
			post_id: button.getAttribute('data-post-id'),
			subject: subject ? subject.value : '',
			send_again: button.getAttribute('data-send-again') || '0',
			_wpnonce: button.getAttribute('data-nonce')
		};

		form.method = 'POST';
		form.action = button.getAttribute('data-action');

		Object.keys(fields).forEach(function (name) {
			var input = document.createElement('input');
			input.type  = 'hidden';
			input.name  = name;
			input.value = fields[name];
			form.appendChild(input);
		});

		document.body.appendChild(form);
		form.submit();
	});
});
