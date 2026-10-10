<div class="modal fade text-start" id="projectChatModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Comments</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <ul id="chat-messages" class="list-unstyled" style="max-height: 400px; overflow-y: auto;">
          <!-- Messages load here -->
        </ul>

        <form id="send-message-form">
          @csrf
          <input type="hidden" id="project_id">
          <input type="hidden" name="parent_id" id="parent_id">
          <div class="input-group mt-1">
            <input type="text" name="message" class="form-control" placeholder="Write a message..." required>
            <button class="btn btn-primary" type="submit"><i class="fa fa-send"></i></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>