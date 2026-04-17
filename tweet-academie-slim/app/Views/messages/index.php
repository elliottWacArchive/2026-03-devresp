<?php use App\Core\Helpers; ?>

<?php
$groupNameId = 'group-name';
$participantUsernamesId = 'participant-usernames';
$newMessageToId = 'new-message-to';
$newMessageBodyId = 'new-message-body';
$newMessageImageId = 'new-message-image';
?>

<div class="row">
  <aside class="four columns panel">
    <h1>Chats</h1>

    <?php if (!empty($requests)): ?>
      <details class="reply-box" open>
        <summary>Message requests (<?= count($requests) ?>)</summary>

        <div class="connections-list">
          <?php foreach ($requests as $request): ?>
            <article class="connection-card">
              <a class="search-result-link" href="/messages?conversation=<?= (int) $request['id'] ?>">
                <?php if (!empty($request['display_image'])): ?>
                  <img class="avatar search-avatar" src="<?= Helpers::e($request['display_image']) ?>" alt="Conversation image">
                <?php else: ?>
                  <div class="avatar search-avatar avatar-fallback">
                    <?= strtoupper(substr((string) $request['display_name'], 0, 1)) ?>
                  </div>
                <?php endif; ?>

                <div class="search-user-meta">
                  <strong><?= Helpers::e($request['display_name']) ?></strong>
                  <?php if (!empty($request['display_subtitle'])): ?>
                    <span><?= Helpers::e($request['display_subtitle']) ?></span>
                  <?php endif; ?>
                </div>
              </a>

              <div class="request-actions" style="padding: 0 1rem 1rem;">
                <form action="/messages/requests/<?= (int) $request['id'] ?>/accept" method="POST" class="inline-form">
                  <button type="submit" class="button button-primary">Accept</button>
                </form>

                <form action="/messages/requests/<?= (int) $request['id'] ?>/reject" method="POST" class="inline-form" data-confirm="Reject this message request?">
                  <button type="submit" class="button button-outline danger-outline">Reject</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endif; ?>

    <details class="reply-box">
      <summary>Create group chat</summary>

      <form action="/messages/group/create" method="POST" class="reply-form">
        <label for="<?= $groupNameId ?>">Group name (optional)</label>
        <input class="u-full-width" id="<?= $groupNameId ?>" name="group_name" type="text" placeholder="Optional group name">

        <label for="<?= $participantUsernamesId ?>">Participants usernames</label>
        <textarea
          class="u-full-width"
          id="<?= $participantUsernamesId ?>"
          name="participant_usernames"
          placeholder="@username1, @username2, @username3"
          required
        ></textarea>

        <button type="submit" class="button-primary">Create group</button>
      </form>
    </details>

    <?php if (empty($conversations)): ?>
      <p>No chats yet.</p>
    <?php else: ?>
      <div class="connections-list">
        <?php foreach ($conversations as $chat): ?>
          <article class="connection-card">
            <a class="search-result-link" href="/messages?conversation=<?= (int) $chat['id'] ?>">
              <?php if (!empty($chat['display_image'])): ?>
                <img class="avatar search-avatar" src="<?= Helpers::e($chat['display_image']) ?>" alt="Conversation image">
              <?php else: ?>
                <div class="avatar search-avatar avatar-fallback">
                  <?= strtoupper(substr((string) $chat['display_name'], 0, 1)) ?>
                </div>
              <?php endif; ?>

              <div class="search-user-meta">
                <strong><?= Helpers::e($chat['display_name']) ?></strong>
                <?php if (!empty($chat['is_pending_sent_by_me'])): ?>
                  <span>Pending</span>
                <?php elseif (!empty($chat['display_subtitle'])): ?>
                  <span><?= Helpers::e($chat['display_subtitle']) ?></span>
                <?php endif; ?>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </aside>

  <section class="eight columns panel">
    <?php if (empty($activeConversation) && empty($directTarget)): ?>
      <h1>Messages</h1>
      <p>Open a chat from the list, start a direct chat from a profile, or create a group chat.</p>

      <form action="/messages" method="POST" enctype="multipart/form-data" class="reply-form">
        <label for="<?= $newMessageToId ?>">Send to</label>
        <input class="u-full-width" type="text" id="<?= $newMessageToId ?>" name="to_username" placeholder="@username" required>

        <label for="<?= $newMessageBodyId ?>">Message</label>
        <textarea class="u-full-width" id="<?= $newMessageBodyId ?>" name="message"></textarea>

        <label for="<?= $newMessageImageId ?>">Image</label>
        <input id="<?= $newMessageImageId ?>" name="image" type="file" accept="image/*" class="input-file-dark">

        <button type="submit" class="button-primary">Send</button>
      </form>
    <?php else: ?>
      <?php
        $headerName = !empty($activeConversation)
          ? $activeConversation['display_name']
          : ($directTarget['username'] ?? '');

        $headerSubtitle = !empty($activeConversation)
          ? ($activeConversation['display_subtitle'] ?? '')
          : trim(($directTarget['firstname'] ?? '') . ' ' . ($directTarget['lastname'] ?? ''));

        $headerImage = !empty($activeConversation)
          ? ($activeConversation['display_image'] ?? null)
          : ($directTarget['icon'] ?? null);

        $isOwner = !empty($activeConversation['is_owner']);
        $isGroup = !empty($activeConversation['is_group']);

        $conversationId = !empty($activeConversation['id']) ? (int) $activeConversation['id'] : 0;
        $conversationMessageId = 'conversation-message-' . $conversationId;
        $conversationImageId = 'conversation-image-' . $conversationId;
        $conversationPhotoId = 'conversation-photo-' . $conversationId;
        $addMemberId = 'add-member-' . $conversationId;
        $renameGroupId = 'rename-group-' . $conversationId;
      ?>

      <div class="dm-header">
        <div class="request-user">
          <?php if (!empty($headerImage)): ?>
            <img class="avatar request-avatar" src="<?= Helpers::e($headerImage) ?>" alt="Conversation image">
          <?php else: ?>
            <div class="avatar request-avatar avatar-fallback">
              <?= strtoupper(substr((string) $headerName, 0, 1)) ?>
            </div>
          <?php endif; ?>

          <div>
            <h1><?= Helpers::e($headerName) ?></h1>

            <?php if (!empty($activeConversation) && !$isGroup && !empty($activeConversation['direct_username'])): ?>
              <p><a href="/profile/<?= Helpers::e($activeConversation['direct_username']) ?>">@<?= Helpers::e($activeConversation['direct_username']) ?></a></p>
            <?php elseif (!empty($headerSubtitle)): ?>
              <p><?= Helpers::e($headerSubtitle) ?></p>
            <?php endif; ?>
          </div>
        </div>

        <div class="request-actions">
          <?php if (!empty($activeConversation) && $isOwner && $isGroup): ?>
            <form action="/messages/conversation/<?= (int) $activeConversation['id'] ?>/image" method="POST" enctype="multipart/form-data" class="inline-form">
              <label for="<?= $conversationPhotoId ?>" class="sr-only">Change conversation photo</label>
              <input id="<?= $conversationPhotoId ?>" type="file" name="image" accept="image/*" required class="input-file-dark">
              <button type="submit" class="button button-outline">Change photo</button>
            </form>
          <?php endif; ?>

          <?php if (!empty($activeConversation) && $isOwner && $isGroup): ?>
            <button type="button" class="button button-outline" onclick="toggleRenameBox()">Rename group</button>

            <div id="rename-chat-box" style="display:none; margin-top: .8rem;">
              <form action="/messages/conversation/<?= (int) $activeConversation['id'] ?>/rename" method="POST" class="inline-form">
                <label for="<?= $renameGroupId ?>" class="sr-only">Rename the group</label>
                <input id="<?= $renameGroupId ?>" type="text" name="name" placeholder="Group name" required>
                <button type="submit" class="button button-outline">Save</button>
              </form>
            </div>
          <?php endif; ?>

          <?php if (!empty($activeConversation)): ?>
            <form action="/messages/conversation/<?= (int) $activeConversation['id'] ?>/delete" method="POST" class="inline-form" data-confirm="Delete this chat for you?">
              <button type="submit" class="button button-outline danger-outline">Delete chat for me</button>
            </form>
          <?php endif; ?>

          <?php if (!empty($activeConversation) && $isOwner && (($activeConversation['status'] ?? 'accepted') === 'accepted')): ?>
            <form action="/messages/conversation/<?= (int) $activeConversation['id'] ?>/delete-all" method="POST" class="inline-form" data-confirm="Delete this conversation for everyone?">
              <button type="submit" class="button button-outline danger-outline">Delete for everyone</button>
            </form>
          <?php endif; ?>

          <?php if (!$isGroup && !empty($activeConversation['direct_username'])): ?>
            <?php if ($isBlocked): ?>
              <form action="/messages/unblock/<?= Helpers::e($activeConversation['direct_username']) ?>" method="POST" class="inline-form">
                <button type="submit" class="button button-outline">Unblock</button>
              </form>
            <?php else: ?>
              <form action="/messages/block/<?= Helpers::e($activeConversation['direct_username']) ?>" method="POST" class="inline-form" data-confirm="Block this user and remove follow links?">
                <button type="submit" class="button button-outline danger-outline">Block</button>
              </form>
            <?php endif; ?>
          <?php elseif (empty($activeConversation) && !empty($directTarget['username'])): ?>
            <?php if ($isBlocked): ?>
              <form action="/messages/unblock/<?= Helpers::e($directTarget['username']) ?>" method="POST" class="inline-form">
                <button type="submit" class="button button-outline">Unblock</button>
              </form>
            <?php else: ?>
              <form action="/messages/block/<?= Helpers::e($directTarget['username']) ?>" method="POST" class="inline-form" data-confirm="Block this user and remove follow links?">
                <button type="submit" class="button button-outline danger-outline">Block</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($activeConversation) && $isGroup): ?>
        <details class="reply-box members-panel">
          <summary>Members</summary>

          <div class="replies members-list">
            <?php foreach (($participants ?? []) as $participant): ?>
              <article class="reply-card member-card">
                <div class="tweet-top member-row">
                  <a href="/profile/<?= Helpers::e($participant['username']) ?>" class="member-link">
                    <strong>@<?= Helpers::e($participant['username']) ?></strong>
                    <span><?= Helpers::e(trim(($participant['firstname'] ?? '') . ' ' . ($participant['lastname'] ?? ''))) ?></span>
                  </a>

                  <?php if ($isOwner && (int) $participant['id'] !== (int) $me['id']): ?>
                    <form
                      action="/messages/conversation/<?= (int) $activeConversation['id'] ?>/remove-member/<?= (int) $participant['id'] ?>"
                      method="POST"
                      class="inline-form"
                      data-confirm="Remove this member from the group?"
                    >
                      <button type="submit" class="button button-outline danger-outline">Kick</button>
                    </form>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <form action="/messages/conversation/<?= (int) $activeConversation['id'] ?>/add-member" method="POST" class="reply-form members-add-form">
            <label for="<?= $addMemberId ?>">Add member</label>
            <input
              class="u-full-width"
              id="<?= $addMemberId ?>"
              name="username"
              type="text"
              placeholder="@username"
              required
            >
            <button type="submit" class="button-primary">Add</button>
          </form>
        </details>
      <?php endif; ?>

      <?php if (!empty($activeConversation['is_pending_sent_by_me'])): ?>
        <div class="panel">
          <p>This message request is pending until the other user accepts it.</p>
        </div>
      <?php endif; ?>

      <?php if ($isBlocked): ?>
        <div class="panel">
          <p>This chat is blocked.</p>
        </div>
      <?php else: ?>
        <div class="dm-thread">
          <?php if (empty($messagesList)): ?>
            <p>No messages yet.</p>
          <?php else: ?>
            <?php foreach ($messagesList as $message): ?>
              <?php
                $mine = (int) $message['from_user_id'] === (int) $me['id'];
                $messageEditId = 'edit-message-' . (int) $message['id'];
              ?>
              <div class="dm-message-block <?= $mine ? 'dm-message-block-mine' : 'dm-message-block-other' ?>">
  <article class="dm-bubble <?= $mine ? 'dm-bubble-mine' : 'dm-bubble-other' ?>">
    <div class="dm-meta">
      <strong><?= Helpers::e($mine ? 'You' : $message['from_username']) ?></strong>
      <small><?= Helpers::formatDate($message['created_at']) ?><?= !empty($message['edited_at']) ? ' · edited' : '' ?></small>
    </div>

    <?php if (!empty($message['message'])): ?>
      <p><?= nl2br(Helpers::e($message['message'])) ?></p>
    <?php endif; ?>

    <?php if (!empty($message['image'])): ?>
      <img class="tweet-image" src="<?= Helpers::e($message['image']) ?>" alt="Message image">
    <?php endif; ?>

    <?php if ($mine): ?>
      <details class="reply-box">
        <summary>Edit</summary>
        <form action="/messages/<?= (int) $message['id'] ?>/edit" method="POST" class="reply-form">
          <label for="<?= $messageEditId ?>" class="sr-only">Edit this message</label>
          <textarea id="<?= $messageEditId ?>" name="message" class="u-full-width" required><?= Helpers::e($message['message']) ?></textarea>
          <button type="submit" class="button-primary">Save edit</button>
        </form>
      </details>
    <?php endif; ?>
  </article>

  <?php if ($mine): ?>
    <div class="dm-message-actions">
      <form action="/messages/<?= (int) $message['id'] ?>/delete" method="POST" class="inline-form" data-confirm="Delete for you?">
        <button type="submit" class="button button-outline danger-outline">Delete</button>
      </form>

      <form action="/messages/<?= (int) $message['id'] ?>/delete-all" method="POST" class="inline-form" data-confirm="Delete for everyone?">
        <button type="submit" class="button button-outline danger-outline">Delete for all</button>
      </form>
    </div>
  <?php endif; ?>
</div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <?php if ($canMessage && empty($activeConversation['is_pending_sent_by_me'])): ?>
          <form action="/messages" method="POST" enctype="multipart/form-data" class="reply-form">
            <?php if (!empty($activeConversation)): ?>
              <input type="hidden" name="conversation_id" value="<?= (int) $activeConversation['id'] ?>">
            <?php elseif (!empty($directTarget['username'])): ?>
              <input type="hidden" name="to_username" value="<?= Helpers::e($directTarget['username']) ?>">
            <?php endif; ?>

            <label for="<?= $conversationMessageId ?>">Message</label>
            <textarea class="u-full-width" id="<?= $conversationMessageId ?>" name="message"></textarea>

            <label for="<?= $conversationImageId ?>">Image</label>
            <input id="<?= $conversationImageId ?>" name="image" type="file" accept="image/*" class="input-file-dark">

            <button type="submit" class="button-primary">Send</button>
          </form>
        <?php else: ?>
          <?php if (!empty($activeConversation['is_pending_sent_by_me'])): ?>
            <p>Waiting for the other user to accept your message request.</p>
          <?php else: ?>
            <p>You cannot message this user.</p>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>