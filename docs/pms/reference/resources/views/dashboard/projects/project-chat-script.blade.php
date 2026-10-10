<script>
    $(document).ready(function() {
        const $chatModal = $('#projectChatModal');
        const $chatBox = $('#chat-messages');
        const $projectIdInput = $('#project_id');
        const $parentIdInput = $('#parent_id');
        const $messageInput = $('input[name="message"]');
        const $sendForm = $('#send-message-form');

        $(document).on('click', '.open-chat', function() {
            const projectId = $(this).data('project-id');
            $('#project_id').val(projectId);
            $('#parent_id').val('');
            // Prevent multiple listeners
            if (!window.chatListeners) window.chatListeners = {};
            if (!window.chatListeners[projectId]) {
                window.chatListeners[projectId] = true;
                window.Echo.private(`project.chat.${projectId}`)
                    .listen('.ProjectChatMessageSent', (e) => {
                        const $chatBox = $('#chat-messages');
                        if ($chatBox.find(`[data-message-id="${e.chat.id}"]`).length > 0) return;
                        if (e.chat.parent_id) {
                            // It's a reply - find the parent message
                            const $parentMsg = $chatBox.find(
                                `[data-message-id="${e.chat.parent_id}"]`);
                            if ($parentMsg.length) {
                                const $repliesUl = $parentMsg.find('ul').first();
                                $repliesUl.append(`
                    <li class="ms-3 mt-1 small">
                        <strong><span class="text-warning">${e.chat.sender.name}</span>:</strong> ${e.chat.message}
                    </li>
                `);
                            }
                        } else {
                            // It's a top-level message
                            let repliesHtml = '';
                            e.chat.replies.forEach(function(reply) {
                                repliesHtml += `
                    <li class="ms-3 mt-1 small">
                        <strong><span class="text-warning">${reply.sender.name}</span>:</strong> ${reply.message}
                    </li>`;
                            });

                            const messageHtml = `
                <li class="p-2" data-message-id="${e.chat.id}">
                    <div class="card">
                        <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <strong class="text-primary">${e.chat.sender.name}</strong>
                    <div>
                        <a href="javascript:void(0)" class="text-primary reply-btn me-2" data-id="${e.chat.id}">
                            <i class="fa fa-reply"></i> Reply
                        </a>
                        <small class="text-muted">${e.chat.created_at_human}</small>
                    </div>
                </div>
                <p class="mb-2">${e.chat.message}</p>

                <ul>${repliesHtml}</ul>

                <div class="row">
                    <div class="col-12 col-md-6">
                        <div class="reply-input-container"></div>
                    </div>
                </div>
            </div>
                    </div>
                </li>
            `;

                            $chatBox.append(messageHtml);
                            $chatBox.animate({
                                scrollTop: $chatBox.prop('scrollHeight')
                            }, 300);
                        }
                    });
            }

            // Optional: mark seen when opened
            $.post(`/projects/${projectId}/chat/mark-seen`, {
                _token: $('meta[name="csrf-token"]').attr('content')
            });

            $.get(`/projects/${projectId}/chat`, function(messages) {
                const $chatBox = $('#chat-messages');
                $chatBox.empty();
                messages.forEach(function(msg) {
                    let repliesHtml = '';
                    msg.replies.forEach(function(reply) {
                        repliesHtml += `
                    <li class="ms-3 mt-1 small">
                        <strong><span class="text-warning">${reply.sender.name}</span>:</strong> ${reply.message}
                        (${reply.created_at_human})
                    </li>`;
                    });

                    $chatBox.append(`
                <li class="p-2" data-message-id="${msg.id}">
                    <div class="card">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                    <strong class="text-primary">${msg.sender.name}</strong>
                                <div>
                                <a href="javascript:void(0)" class="text-primary reply-btn me-2" data-id="${msg.id}">
                                    <i class="fa fa-reply"></i> Reply
                                </a>
                                <small class="text-muted">${msg.created_at_human}</small>
                                </div>
                                </div>
                                <p class="mb-2">${msg.message}</p>

                                <ul>${repliesHtml}</ul>

                                <div class="row">
                                <div class="col-12 col-md-6">
                                <div class="reply-input-container"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
            `);
                });
                $('#projectChatModal').modal('show');
                setTimeout(() => {
                    $chatBox.animate({
                        scrollTop: $chatBox.prop('scrollHeight')
                    }, 300);
                }, 300);
            });
        });


        $sendForm.on('submit', function(e) {
            e.preventDefault();
            const projectId = $projectIdInput.val();
            const formData = new FormData(this);
            $.ajax({
                url: `/projects/${projectId}/chat/send`,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': formData.get('_token')
                },
                processData: false,
                contentType: false,
                data: formData,
                success: function() {
                    $messageInput.val('');
                    $parentIdInput.val('');
                    $(`.open-chat[data-project-id="${projectId}"]`).trigger(
                        'click');
                    setTimeout(function() {
                        const $commentSection = $(
                            '#chat-messages');
                        $commentSection.animate({
                            scrollTop: $commentSection.prop("scrollHeight")
                        }, 500);
                    }, 500);

                }
            });
        });


        $('#chat-messages').on('click', '.reply-btn', function() {
            const messageId = $(this).data('id');
            const messageItem = $(this).closest('li[data-message-id]');
            $('.reply-input-wrapper').remove();
            const replyInputHtml = `
        <div class="reply-input-wrapper mt-2">
        <div class="input-group input-group-sm">
            <input type="text" class="form-control reply-input" placeholder="Write a reply..." />
            <button class="btn btn-primary send-reply-btn" type="button"> <i class="fa fa-send"></i></button>
            <button class="btn  cancel-reply-btn" type="button"><i class="fa fa-times"></i></button>

         </div>
        </div>
    `;
            messageItem.find('.reply-input-container').append(replyInputHtml);
            messageItem.find('.reply-input').focus();
            messageItem.find('.reply-input').data('parent-id', messageId);
        });


        $('#chat-messages').on('click', '.send-reply-btn', function() {
            const replyWrapper = $(this).closest('.reply-input-wrapper');
            const replyInput = replyWrapper.find('.reply-input');
            const messageText = replyInput.val().trim();

            if (!messageText) {
                alert('Please enter a reply message.');
                return;
            }

            const parentId = replyInput.data('parent-id');
            const projectId = $('#project_id').val();
            const data = {
                message: messageText,
                parent_id: parentId,
                _token: $('meta[name="csrf-token"]').attr('content')
            };

            $.post(`/projects/${projectId}/chat/send`, data)
                .done(() => {
                    replyWrapper.remove();
                    $(`.open-chat[data-project-id="${projectId}"]`).click();
                })
                .fail(() => alert('Failed to send reply. Please try again.'));
        });

        $('#chat-messages').on('click', '.cancel-reply-btn', function() {
            $(this).closest('.reply-input-wrapper').remove();
        });

        $('#projectChatModal').on('shown.bs.modal', function() {
            const projectId = $('#project_id').val();
            $.ajax({
                url: `/projects/${projectId}/chat/mark-seen`,
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                }
            });
        });

        $('#projectChatModal').on('hidden.bs.modal', function() {
            if (datatable) {
                datatable.ajax.reload(null, false);
            }
        });
    });


    window.listenedProjects = window.listenedProjects || {};

    function listenForProjectChatMessages(projectId) {
        if (window.listenedProjects[projectId]) return;
        window.listenedProjects[projectId] = true;
        window.Echo.private(`project.chat.${projectId}`)
            .listen('.ProjectChatMessageSent', (e) => {
                const openProjectId = parseInt($('#project_id').val());
                if (!$('#projectChatModal').hasClass('show') || openProjectId !== e.chat.project_id) return;
                const $chatBox = $('#chat-messages');
                if ($chatBox.find(`[data-message-id="${e.chat.id}"]`).length) return;
                let repliesHtml = '';
                e.chat.replies.forEach(reply => {
                    repliesHtml += `
                    <li class="ms-3 mt-1 small">
                        <strong><span class="text-warning">${reply.sender.name}</span>:</strong> ${reply.message}
                    </li>`;
                });
                const newMessageHtml = `
                <li class="p-2" data-message-id="${e.chat.id}">
                    <div class="card">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                    <strong class="text-primary">${e.chat.sender.name}</strong>
                                <div>
                                <a href="javascript:void(0)" class="text-primary reply-btn me-2" data-id="${e.chat.id}">
                                    <i class="fa fa-reply"></i> Reply
                                </a>
                                <small class="text-muted">${e.chat.created_at_human}</small>
                                </div>
                                </div>
                                <p class="mb-2">${e.chat.message}</p>
                                <ul>${repliesHtml}</ul>
                                <div class="row">
                                <div class="col-12 col-md-6">
                                <div class="reply-input-container"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>`;
                $chatBox.append(newMessageHtml);
                setTimeout(() => {
                    $chatBox.animate({
                        scrollTop: $chatBox.prop("scrollHeight")
                    }, 300);
                }, 200);
            });
    }

    // Start listener when chat modal opens
    $(document).on('click', '.open-chat', function() {
        const projectId = $(this).data('project-id');
        listenForProjectChatMessages(projectId);
    });

    $(document).on('click', '.delete-msg-btn', function() {
        const messageId = $(this).data('id');
        if (!confirm('Are you sure you want to delete this message?')) return;
        $.ajax({
            url: `/projects/chat/delete/${messageId}`,
            method: 'DELETE',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function() {
                // Remove the message from the DOM
                $(`[data-message-id="${messageId}"]`).remove();
            },
            error: function() {
                alert('Failed to delete message.');
            }
        });
    });
</script>
