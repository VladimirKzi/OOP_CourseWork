'use strict';
const CommentComponent = {
  build(c, bookId, depth = 0) {
    const user    = Auth.getUser();
    const isMod   = Auth.isMod();
    const isOwner = user && user.id === c.user_id;
    const E       = Utils.el;

    const avatar = E('div', { class: 'comment__avatar' }, Utils.initials(c.author.name));
    const header = E('div', { class: 'comment__header' }, avatar,
      E('span', { class: 'comment__name' }, c.author.name));
    if (c.author.role === 'admin')     header.appendChild(E('span', { class: 'badge badge--admin' }, 'адмін'));
    if (c.author.role === 'moderator') header.appendChild(E('span', { class: 'badge badge--mod' },   'мод'));
    header.appendChild(E('span', { class: 'comment__date' }, Utils.fmtDate(c.created_at)));

    const textEl  = E('p', { class: 'comment__text' + (c.is_deleted ? ' comment__text--deleted' : '') }, c.text);
    const actions = E('div', { class: 'comment__actions' });

    if (!c.is_deleted) {
      // Vote buttons
      const likeBtn = E('button', { class: 'vote-btn' + (c.user_vote === 'like' ? ' vote-btn--like' : '') },
        '👍 ' + c.likes_count);
      const disBtn  = E('button', { class: 'vote-btn' + (c.user_vote === 'dislike' ? ' vote-btn--dislike' : '') },
        '👎 ' + c.dislikes_count);

      const doVote = async (type) => {
        if (!user) { Router.go('/auth/login'); return; }
        try {
          await Api.voteComment(c.id, type);
          if (c.user_vote === type) {
            type === 'like' ? c.likes_count-- : c.dislikes_count--;
            c.user_vote = null;
          } else {
            if (c.user_vote) { c.user_vote === 'like' ? c.likes_count-- : c.dislikes_count--; }
            type === 'like' ? c.likes_count++ : c.dislikes_count++;
            c.user_vote = type;
          }
          wrap.replaceWith(CommentComponent.build(c, bookId, depth));
        } catch (err) { Toast.error(err.message); }
      };
      likeBtn.addEventListener('click', () => doVote('like'));
      disBtn.addEventListener('click',  () => doVote('dislike'));
      actions.appendChild(likeBtn);
      actions.appendChild(disBtn);

      // Reply
      if (user) {
        const replyBtn = E('button', { class: 'text-btn' }, 'Відповісти');
        replyBtn.addEventListener('click', () => {
          const existing = wrap.querySelector('.reply-form');
          if (existing) { existing.remove(); return; }
          wrap.appendChild(CommentComponent.replyForm(c.id, bookId, (newReply) => {
            repliesWrap.appendChild(CommentComponent.build(newReply, bookId, depth + 1));
            c.replies_count++;
            wrap.querySelector('.reply-form')?.remove();
          }));
        });
        actions.appendChild(replyBtn);
      }

      // Edit (owner only)
      if (isOwner) {
        const editBtn = E('button', { class: 'text-btn' }, 'Редагувати');
        editBtn.addEventListener('click', () => {
          const area   = E('textarea', { class: 'form-input', style: 'width:100%;min-height:70px;resize:vertical;margin-top:8px' }, c.text);
          const saveBtn   = E('button', { class: 'btn btn--sm btn--primary' }, 'Зберегти');
          const cancelBtn = E('button', { class: 'btn btn--sm' }, 'Скасувати');
          const row    = E('div', { style: 'display:flex;gap:8px;justify-content:flex-end;margin-top:8px' }, cancelBtn, saveBtn);
          const editBox = E('div', {}, area, row);
          textEl.replaceWith(editBox);
          cancelBtn.addEventListener('click', () => editBox.replaceWith(textEl));
          saveBtn.addEventListener('click', async () => {
            try {
              const updated = await Api.updateComment(c.id, area.value.trim());
              c.text = updated.text; textEl.textContent = updated.text;
              editBox.replaceWith(textEl); Toast.success('Збережено');
            } catch (err) { Toast.error(err.message); }
          });
        });
        actions.appendChild(editBtn);
      }

      // Delete
      if (isOwner || isMod) {
        const delBtn = E('button', { class: 'text-btn text-btn--danger' }, 'Видалити');
        delBtn.addEventListener('click', async () => {
          if (!confirm('Видалити коментар?')) return;
          try {
            await Api.deleteComment(c.id);
            c.is_deleted = true; c.text = '[видалено]';
            wrap.replaceWith(CommentComponent.build(c, bookId, depth));
            Toast.success('Видалено');
          } catch (err) { Toast.error(err.message); }
        });
        actions.appendChild(delBtn);
      }

      // Flag
      if (user && !isOwner) {
        const flagBtn = E('button', { class: 'text-btn' }, 'Поскаржитись');
        flagBtn.addEventListener('click', async () => {
          try { await Api.flagComment(c.id); Toast.success('Скаргу надіслано'); }
          catch (err) { Toast.error(err.message); }
        });
        actions.appendChild(flagBtn);
      }

      // Load replies
      if (c.replies_count > 0) {
        const label   = `↳ ${c.replies_count} відповід${c.replies_count === 1 ? 'ь' : 'і'}`;
        const loadBtn = E('button', { class: 'text-btn' }, label);
        let loaded = false;
        loadBtn.addEventListener('click', async () => {
          if (loaded) {
            repliesWrap.hidden = !repliesWrap.hidden;
            loadBtn.textContent = repliesWrap.hidden ? label : 'Сховати відповіді';
            return;
          }
          try {
            const replies = await Api.getReplies(c.id);
            replies.forEach(r => repliesWrap.appendChild(CommentComponent.build(r, bookId, depth + 1)));
            loaded = true;
            loadBtn.textContent = 'Сховати відповіді';
          } catch (err) { Toast.error(err.message); }
        });
        actions.appendChild(loadBtn);
      }
    }

    const repliesWrap = E('div', { class: 'comment__replies' });
    const wrap = E('div', {
      class: `comment comment--${depth === 0 ? 'root' : 'reply'}`,
      id: 'cmt-' + c.id,
    }, header, textEl, actions, repliesWrap);

    return wrap;
  },

  replyForm(parentId, bookId, onDone) {
    const E    = Utils.el;
    const area = E('textarea', { class: 'form-input', placeholder: 'Ваша відповідь...', style: 'min-height:70px;resize:vertical' });
    const row  = E('div', { class: 'reply-form__actions' });
    const cancel = E('button', { class: 'btn btn--sm' }, 'Скасувати');
    const submit = E('button', { class: 'btn btn--sm btn--primary' }, 'Відповісти');
    row.appendChild(cancel); row.appendChild(submit);
    const form = E('div', { class: 'reply-form' }, area, row);
    cancel.addEventListener('click', () => form.remove());
    submit.addEventListener('click', async () => {
      if (!area.value.trim()) return;
      try {
        const reply = await Api.createComment({ text: area.value.trim(), book_id: bookId, parent_id: parentId });
        onDone(reply);
      } catch (err) { Toast.error(err.message); }
    });
    return form;
  },
};
