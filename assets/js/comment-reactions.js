// Adds an emoji reaction bar under every task-discussion comment, for both
// interfaces. Deliberately additive/read-only with respect to the existing
// comment rendering (view-task.php's server template and its
// createCommentHTML()/addCommentToPage() JS both already stamp every
// comment element with data-comment-id) - this file only appends a sibling
// element after each [data-comment-id]'s .comment-text, via a
// MutationObserver so newly-polled comments get a bar too without any
// change to how those functions build comments.
//
// Needs a task id on the page: window.iTaskerTaskId, or a
// #globalCsrfToken-style hidden input isn't enough on its own - set
// window.iTaskerTaskId = <?php echo (int) $taskId; ?> before including this
// script on a task-detail page.
(function () {
    const REACTIONS = ['👍', '❤️', '👏', '😂', '🎉', '👀'];

    // Hidden until the comment is hovered, EXCEPT a bar that already has at
    // least one reaction on it - that one stays visible so the reaction
    // isn't hidden from anyone who isn't currently hovering. Plain CSS
    // (:hover) rather than JS mouseenter/leave handlers, since it needs to
    // apply uniformly to comments this script hasn't rendered itself
    // (view-task.php's server template) as well as ones it has.
    if (!document.getElementById('commentReactionStyles')) {
        const style = document.createElement('style');
        style.id = 'commentReactionStyles';
        style.textContent = '.comment-reaction-bar{opacity:0!important;transition:opacity .15s ease;pointer-events:none;}'
            + '[data-comment-id]:hover .comment-reaction-bar,'
            + '.comment-reaction-bar.has-reactions,'
            + '.comment-reaction-bar:focus-within{opacity:1!important;pointer-events:auto;}';
        document.head.appendChild(style);
    }

    function csrfToken() {
        const el = document.querySelector('[name="csrf_token"]');
        return el ? el.value : '';
    }

    function buildBar(commentId, reactions) {
        const bar = document.createElement('div');
        const hasReactions = (reactions || []).some(function (r) { return r.count > 0; });
        bar.className = 'comment-reaction-bar d-flex align-items-center flex-wrap gap-1 mt-1' + (hasReactions ? ' has-reactions' : '');
        bar.dataset.reactionBarFor = commentId;

        const counts = {};
        (reactions || []).forEach(function (r) { counts[r.emoji] = r; });

        REACTIONS.forEach(function (emoji) {
            const r = counts[emoji];
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm py-0 px-2 border-0 comment-reaction-btn' + (r && r.reacted_by_me ? ' bg-primary-subtle' : '');
            btn.style.cssText = 'font-size:12px;border-radius:12px;background:var(--falcon-tertiary-bg,#f0f2f5);';
            btn.innerHTML = emoji + (r && r.count ? ' <span class="fw-semibold">' + r.count + '</span>' : '');
            btn.title = r && r.count ? r.count + ' reaction(s)' : 'React';
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                sendReaction(commentId, emoji, bar);
            });
            bar.appendChild(btn);
        });

        return bar;
    }

    function sendReaction(commentId, emoji, bar) {
        const formData = new FormData();
        formData.append('comment_id', commentId);
        formData.append('emoji', emoji);
        formData.append('csrf_token', csrfToken());

        fetch('add-comment-reaction', { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    const newBar = buildBar(commentId, data.reactions);
                    bar.replaceWith(newBar);
                }
            })
            .catch(function () {});
    }

    function attachBars() {
        if (!window.iTaskerTaskId) return;

        const commentEls = document.querySelectorAll('[data-comment-id]:not([data-reaction-bar-attached])');
        if (!commentEls.length) return;

        const ids = [];
        commentEls.forEach(function (el) { ids.push(el.dataset.commentId); el.setAttribute('data-reaction-bar-attached', '1'); });

        fetch('get-comment-reactions?task_id=' + encodeURIComponent(window.iTaskerTaskId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                const reactions = (data && data.success && data.reactions) || {};
                commentEls.forEach(function (el) {
                    const textEl = el.querySelector('.comment-text');
                    if (!textEl) return;
                    const bar = buildBar(el.dataset.commentId, reactions[el.dataset.commentId]);
                    textEl.insertAdjacentElement('afterend', bar);
                });
            })
            .catch(function () {});
    }

    document.addEventListener('DOMContentLoaded', function () {
        attachBars();

        const container = document.body;
        const observer = new MutationObserver(function () { attachBars(); });
        observer.observe(container, { childList: true, subtree: true });
    });
})();
