(() => {
    'use strict';

    const form = document.querySelector('#lwc-comments-form');

    if (!form) {
        return;
    }

    const STORAGE_KEY = 'classicpackLightweightCommentsAuthor';

    const commentsList = document.querySelector('#lwc-comments-list');
    const parentInput = document.querySelector('#lwc-parent-id');
    const nameInput = form.querySelector('[name="author_name"]');
    const emailInput = form.querySelector('[name="author_email"]');
    const replyingTo = document.querySelector('#lwc-replying-to');
    const replyingToText = document.querySelector('.lwc-replying-to-text');
    const cancelReply = document.querySelector('#lwc-cancel-reply');
    const newComment = document.querySelector('#lwc-new-comment');
    const submitButton = document.querySelector('#lwc-submit-button');

    const SUBMIT_LABEL = submitButton ? submitButton.textContent.trim() : 'Post Feedback';

    // Restore saved author details.
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');

        if (saved.name && nameInput) {
            nameInput.value = saved.name;
        }

        if (saved.email && emailInput) {
            emailInput.value = saved.email;
        }
    } catch (error) {
        // Ignore malformed storage.
    }

    const clearReply = () => {
        parentInput.value = '0';

        if (replyingTo) {
            replyingTo.hidden = true;
        }

        if (replyingToText) {
            replyingToText.textContent = '';
        }

        if (submitButton) {
            submitButton.textContent = SUBMIT_LABEL;
        }
    };

    const focusForm = () => {
        form.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });

        const target = form.querySelector('[name="comment_content"]');

        if (target) {
            target.focus({ preventScroll: true });
        }
    };

    document.addEventListener('click', (event) => {

        const button = event.target.closest('.lwc-reply-button');

        if (!button) {
            return;
        }

        parentInput.value = button.dataset.commentId;

        const author = button.dataset.author || '';

        if (replyingTo && replyingToText) {
            replyingToText.textContent = author ? `Replying to ${author}...` : 'Replying...';
            replyingTo.hidden = false;
        }

        if (submitButton) {
            submitButton.textContent = 'Post Reply';
        }

        focusForm();
    });

    if (cancelReply) {
        cancelReply.addEventListener('click', clearReply);
    }

    if (newComment) {
        newComment.addEventListener('click', () => {
            clearReply();
            focusForm();
        });
    }

    form.addEventListener('submit', async (event) => {

        event.preventDefault();

        const formData = new FormData(form);

        formData.append('action', 'classicpress_lightweight_comments_submit');
        formData.append('nonce', classicpackLightweightComments.nonce);
        formData.append('post_id', classicpackLightweightComments.postId);

        const response = await fetch(classicpackLightweightComments.ajaxUrl, {
            method: 'POST',
            body: formData,
        });

        const data = await response.json();

        if (!data.success) {
            alert('Unable to submit feedback.');
            return;
        }

        // Save author details for next time.
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                name: nameInput ? nameInput.value : '',
                email: emailInput ? emailInput.value : '',
            }));
        } catch (error) {
            // Ignore storage failures.
        }

        commentsList.innerHTML = data.data.html;

        const savedName = nameInput ? nameInput.value : '';
        const savedEmail = emailInput ? emailInput.value : '';

        form.reset();

        // Keep the author details populated after reset.
        if (nameInput) {
            nameInput.value = savedName;
        }

        if (emailInput) {
            emailInput.value = savedEmail;
        }

        clearReply();
    });
})();
