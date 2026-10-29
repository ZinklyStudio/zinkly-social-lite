/* Zinkly Social Lite - all page behaviour. No libraries, no inline scripts (works with a strict Content-Security-Policy). */
(function () {
    'use strict';
    var body = document.body;
    var T = {};
    try { T = JSON.parse(body.getAttribute('data-texts') || '{}'); } catch (e) {}
    var tx = function (k, n) {
        var s = T[k] || k;
        if (n !== undefined) {
            var parts = s.split('|');
            if (parts.length > 1) {
                var lang = (document.documentElement.lang || 'en').toLowerCase(), i = n === 1 ? 0 : 1;
                if (lang === 'ru') { var m10 = n % 10, m100 = n % 100; i = (m10 === 1 && m100 !== 11) ? 0 : ((m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) ? 1 : 2); }
                else if (lang === 'fr' || lang === 'pt-br') { i = n <= 1 ? 0 : 1; }
                s = parts[Math.min(i, parts.length - 1)];
            }
            s = s.replace(':n', n);
        }
        return s;
    };
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    function req(url, opts) {
        opts = opts || {};
        var init = { method: opts.method || 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf } };
        if (opts.body) {
            if (opts.body instanceof FormData) { init.body = opts.body; }
            else { var fd = new FormData(); Object.keys(opts.body).forEach(function (k) { fd.append(k, opts.body[k]); }); init.body = fd; }
        }
        if (init.method === 'GET') { delete init.body; }
        return fetch(url, init).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (d) {
                if (!r.ok) { var err = new Error(d.error || tx('error')); err.data = d; throw err; }
                return d;
            });
        });
    }
    var get = function (url) { return req(url, { method: 'GET' }); };

    var toastTimer;
    function toast(msg) {
        if (!msg) return;
        var el = $('.zl-toast');
        if (!el) { el = document.createElement('div'); el.className = 'zl-toast'; el.setAttribute('role', 'status'); body.appendChild(el); }
        el.textContent = msg; el.hidden = true; void el.offsetWidth; el.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { el.hidden = true; }, 3200);
    }
    var fail = function (e) { toast((e && e.message) || tx('error')); };

    /* ---------- auto-submit selects ---------- */
    $$('[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form && s.form.submit(); }); });

    /* ---------- menus ---------- */
    // the card (post, row ...) with an open menu is lifted above its neighbours, so the menu is never painted under the next post
    var lifted = function (m) { return m.closest('.zl-card, tr, .zl-person, .zl-comment') || m.parentNode; };
    function closeMenus(except) {
        $$('.zl-menu-list').forEach(function (m) {
            if (m !== except && !m.hidden) { m.hidden = true; var b = m.parentNode.querySelector('[data-menu]'); b && b.setAttribute('aria-expanded', 'false'); lifted(m).classList.remove('has-open-menu'); }
        });
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-menu]');
        if (b) {
            var list = b.parentNode.querySelector('.zl-menu-list');
            closeMenus(list);
            list.hidden = !list.hidden;
            lifted(list).classList.toggle('has-open-menu', !list.hidden);
            if (!list.hidden) { // open upwards when there is no room below
                list.classList.remove('is-up');
                var lr = list.getBoundingClientRect();
                if (lr.bottom > window.innerHeight - 8 && lr.top - lr.height - b.offsetHeight - 8 > 0) list.classList.add('is-up');
            }
            b.setAttribute('aria-expanded', String(!list.hidden));
            if (!list.hidden) { var f = list.querySelector('a,button'); f && f.focus(); }
            return;
        }
        if (!e.target.closest('.zl-menu-list')) closeMenus();
    });

    /* ---------- modals ---------- */
    var lastFocus = null;
    function openModal(id) {
        var m = document.getElementById(id);
        if (!m) return null;
        lastFocus = document.activeElement;
        closeMenus();
        m.hidden = false;
        body.classList.add('zl-modal-open');
        var f = m.querySelector('textarea,input:not([type=hidden]):not(.sr-only),select,button');
        setTimeout(function () { f && f.focus(); }, 30);
        return m;
    }
    function closeModal(m) {
        if (!m) return;
        m.hidden = true;
        if (!$('.zl-modal:not([hidden]), .zl-story-viewer:not([hidden])')) body.classList.remove('zl-modal-open');
        lastFocus && lastFocus.focus && lastFocus.focus();
    }
    document.addEventListener('click', function (e) {
        var o = e.target.closest('[data-open]');
        if (o) { e.preventDefault(); openModal(o.getAttribute('data-open')); return; }
        if (e.target.closest('[data-close]')) { closeModal(e.target.closest('.zl-modal')); return; }
        if (e.target.classList && e.target.classList.contains('zl-modal')) closeModal(e.target);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        closeMenus();
        var sv = $('.zl-story-viewer:not([hidden])');
        if (sv) { sv.id === 'story-viewer' ? stopStories() : closeLightbox(); return; }
        var m = $('.zl-modal:not([hidden])');
        m && closeModal(m);
    });

    /* ---------- confirm ---------- */
    var confirmFn = null;
    function ask(text, fn) {
        var m = openModal('confirm-modal');
        if (!m) { if (window.confirm(text)) fn(); return; }
        $('[data-confirm-text]', m).textContent = text;
        confirmFn = fn;
    }
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-confirm-yes]')) {
            var m = e.target.closest('.zl-modal'); closeModal(m);
            var fn = confirmFn; confirmFn = null; fn && fn();
        }
    });
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (f.hasAttribute('data-confirm') && !f.__ok) {
            e.preventDefault();
            ask(f.getAttribute('data-confirm'), function () { f.__ok = true; longTask(f); f.submit(); });
        }
    });

    /* ---------- long tasks (updates, backups): a full-page "please wait" so nobody clicks twice or leaves ---------- */
    function longTask(f) {
        var text = f.getAttribute('data-long-task');
        if (!text) return;
        var o = document.createElement('div');
        o.className = 'zl-busy'; o.setAttribute('role', 'alert');
        o.innerHTML = '<div><span class="zl-spin"></span><b></b></div>';
        o.querySelector('b').textContent = text;
        document.body.appendChild(o);
        $$('button', f).forEach(function (b) { b.disabled = true; });
    }
    document.addEventListener('submit', function (e) { if (!e.defaultPrevented && e.target.hasAttribute && e.target.hasAttribute('data-long-task')) longTask(e.target); });

    /* ---------- copy link ---------- */
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-copy]');
        if (!b) return;
        closeMenus();
        var v = b.getAttribute('data-copy');
        (navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).then(function () { toast(tx('copied')); }, function () { window.prompt(tx('copy_this'), v); });
    });

    /* ---------- likes, saves ---------- */
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-like]');
        if (b) {
            if (b.disabled) return;
            b.disabled = true;
            req(b.getAttribute('data-like')).then(function (d) {
                b.classList.toggle('is-liked', d.liked); b.setAttribute('aria-pressed', String(d.liked));
                if (d.liked) burst(b);
                var post = b.closest('[data-post]');
                var c = post && $('[data-like-count]', post);
                if (c) c.textContent = d.count ? tx('n_likes', d.count) : '';
            }).catch(fail).then(function () { b.disabled = false; });
            return;
        }
        var s = e.target.closest('[data-save]');
        if (s) {
            if (s.disabled) return;
            s.disabled = true;
            req(s.getAttribute('data-save')).then(function (d) {
                s.classList.toggle('is-saved', d.saved); s.setAttribute('aria-pressed', String(d.saved)); toast(d.message);
                var lab = $('[data-save-label]', s); if (lab) lab.textContent = tx(d.saved ? 'saved' : 'save');
                s.title = tx(d.saved ? 'saved' : 'save');
                if (d.saved) burst(s);
            }).catch(fail).then(function () { s.disabled = false; });
            return;
        }
        var cl = e.target.closest('[data-clike]');
        if (cl) {
            req(cl.getAttribute('data-clike')).then(function (d) {
                cl.classList.toggle('is-liked', d.liked); cl.setAttribute('aria-pressed', String(d.liked));
                var n = $('[data-n]', cl); if (n) n.textContent = d.count ? ' · ' + d.count : '';
            }).catch(fail);
        }
    });

    /* ---------- share & report ---------- */
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-share]');
        if (b) {
            var m = openModal('share-modal');
            if (m) { var f = $('form', m) || m; f.setAttribute('action', b.getAttribute('data-share')); var ta = $('textarea', m); if (ta) ta.value = ''; }
            return;
        }
        var r = e.target.closest('[data-report]');
        if (r) {
            closeMenus();
            var parts = r.getAttribute('data-report').split(':');
            var rm = openModal('report-modal');
            if (rm) { $('[name=type]', rm).value = parts[0]; $('[name=id]', rm).value = parts[1]; }
        }
    });
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (f.matches('[data-share-form], [data-report-form]')) {
            e.preventDefault();
            var btn = $('button:not([data-close])', f); btn && (btn.disabled = true);
            req(f.getAttribute('action'), { body: new FormData(f) }).then(function (d) {
                closeModal(f.closest('.zl-modal')); toast(d.message); f.reset();
            }).catch(fail).then(function () { btn && (btn.disabled = false); });
        }
    });

    /* ---------- edit & delete posts ---------- */
    document.addEventListener('click', function (e) {
        var ed = e.target.closest('[data-edit-post]');
        if (ed) {
            closeMenus();
            var post = ed.closest('[data-post]');
            var form = $('[data-edit-form]', post), bodyEl = $('[data-body]', post);
            form.hidden = false; bodyEl && (bodyEl.hidden = true);
            $$('.zl-editor', form).forEach(richify);
            var ew = $('.zl-editor', form);
            (ew && ew.__area) ? focusEnd(ew.__area) : $('textarea', form).focus();
            return;
        }
        var cancel = e.target.closest('[data-edit-cancel]');
        if (cancel) {
            var p2 = cancel.closest('[data-post]');
            $('[data-edit-form]', p2).hidden = true; var b2 = $('[data-body]', p2); b2 && (b2.hidden = false);
            return;
        }
        var del = e.target.closest('[data-delete-post]');
        if (del) {
            closeMenus();
            ask(tx('delete_post'), function () {
                req(del.getAttribute('data-url')).then(function (d) {
                    if (del.hasAttribute('data-reload')) { location.reload(); return; }
                    var art = del.closest('[data-post]');
                    if (document.querySelectorAll('[data-post]').length === 1 && /\/p\/\d+/.test(location.pathname)) { location.href = (body.getAttribute('data-base') || '') + '/'; return; }
                    art && art.remove(); toast(d.message);
                }).catch(fail);
            });
        }
    });
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f.matches('[data-edit-form]')) return;
        e.preventDefault();
        syncEditors(f);
        req(f.getAttribute('action'), { body: new FormData(f) }).then(function (d) {
            var post = f.closest('[data-post]');
            var b = $('[data-body]', post);
            if (!b) { b = document.createElement('div'); b.className = 'zl-post-body'; b.setAttribute('data-body', ''); f.parentNode.insertBefore(b, f); }
            b.innerHTML = d.html; b.hidden = false; f.hidden = true; toast(d.message);
            b.classList.toggle('zl-rich', d.format === 'html'); b.classList.remove('is-short');
        }).catch(fail);
    });

    /* ---------- comments ---------- */
    function loadComments(box) {
        var list = $('[data-list]', box);
        if (!list || list.__loaded) return;
        list.__loaded = true;
        get(list.getAttribute('data-src')).then(function (d) { list.innerHTML = d.html || ''; }).catch(fail);
    }
    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-toggle-comments]');
        if (t) {
            var post = t.closest('[data-post]');
            var box = post && $('[data-comments]', post);
            if (!box) return;
            e.preventDefault();
            box.hidden = !box.hidden;
            if (!box.hidden) { loadComments(box); var ta = $('textarea', box); ta && ta.focus(); }
            return;
        }
        var r = e.target.closest('[data-reply]');
        if (r) {
            var form = r.closest('[data-post]') ? $('[data-comment-form]', r.closest('[data-post]')) : null;
            if (!form || (r.closest('[data-static-comments]'))) form = $('#comments [data-comment-form]') || form;
            if (!form) return;
            var box2 = form.closest('[data-comments]'); if (box2) box2.hidden = false;
            form.querySelector('[name=parent_id]').value = r.getAttribute('data-reply');
            var area = form.querySelector('textarea');
            area.value = '@' + r.getAttribute('data-reply-name') + ' ';
            area.focus();
            return;
        }
        var dc = e.target.closest('[data-delete-comment]');
        if (dc) {
            ask(tx('delete_comment'), function () {
                req(dc.getAttribute('data-delete-comment')).then(function () { var c = dc.closest('[data-comment]'); c && c.remove(); }).catch(fail);
            });
        }
    });
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f.matches('[data-comment-form]')) return;
        e.preventDefault();
        var area = f.querySelector('textarea');
        if (!area.value.trim()) return;
        var btn = f.querySelector('button'); btn.disabled = true;
        req(f.getAttribute('action'), { body: new FormData(f) }).then(function (d) {
            var scope = f.closest('[data-post]') || document;
            var list = f.closest('[data-comments]') ? $('[data-list]', f.closest('[data-comments]')) : $('[data-static-comments]');
            var target = d.parent ? $('[data-replies="' + d.parent + '"]', document) : list;
            (target || list).insertAdjacentHTML('beforeend', d.html);
            var none = $('[data-no-comments]'); none && none.remove();
            area.value = ''; f.querySelector('[name=parent_id]').value = '';
            var post = document.querySelector('[data-post="' + f.getAttribute('data-comment-form') + '"]');
            var cc = post && $('[data-comment-count]', post); if (cc) cc.textContent = tx('n_comments', d.count);
        }).catch(fail).then(function () { btn.disabled = false; });
    });
    document.addEventListener('keydown', function (e) {
        var ta = e.target;
        if (e.key === 'Enter' && !e.shiftKey && ta.matches && ta.matches('[data-comment-form] textarea, [data-msg-form] textarea')) {
            e.preventDefault();
            ta.form.requestSubmit ? ta.form.requestSubmit() : ta.form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        }
    });
    // open comments when the page was opened with #comments
    if (location.hash === '#comments') { var cs = $('#comments textarea'); cs && cs.focus(); }

    /* ---------- follow buttons ---------- */
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-follow]');
        if (!b) return;
        var st = b.getAttribute('data-state');
        var url = st === 'none' ? b.getAttribute('data-follow') : b.getAttribute('data-unfollow');
        b.disabled = true;
        req(url).then(function (d) {
            var ns = (d.status === 'accepted' || d.status === 'pending') ? d.status : 'none';
            b.setAttribute('data-state', ns);
            b.textContent = b.getAttribute('data-l-' + ns);
            b.classList.toggle('zl-btn-main', ns === 'none'); b.classList.toggle('zl-btn-soft', ns !== 'none');
            var f = $('[data-followers]'); if (f && d.followers !== undefined && b.closest('.zl-profile-head')) f.textContent = d.followers;
        }).catch(fail).then(function () { b.disabled = false; });
    });

    /* ---------- feed: load more (and automatically near the end) ---------- */
    function loadMore(btn) {
        if (btn.__busy) return;
        btn.__busy = true;
        var feed = btn.closest('[data-feed]');
        var url = feed.getAttribute('data-more-url') + '?scope=' + encodeURIComponent(feed.getAttribute('data-feed')) + '&before=' + btn.getAttribute('data-before');
        btn.disabled = true;
        get(url).then(function (d) {
            var wrap = btn.closest('[data-more-wrap]');
            wrap.insertAdjacentHTML('beforebegin', d.html || '');
            if (!d.count || d.count < 15) { wrap.remove(); return; }
            btn.setAttribute('data-before', d.next);
        }).catch(fail).then(function () { btn.disabled = false; btn.__busy = false; });
    }
    document.addEventListener('click', function (e) { var b = e.target.closest('[data-more]'); if (b) loadMore(b); });
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (es) { es.forEach(function (en) { if (en.isIntersecting) loadMore(en.target); }); }, { rootMargin: '600px' });
        var watch = function () { $$('[data-more]').forEach(function (b) { if (!b.__io) { b.__io = 1; io.observe(b); } }); };
        watch();
        new MutationObserver(watch).observe(body, { childList: true, subtree: true });
    }

    /* ---------- composer: photo previews, auto-grow ---------- */
    document.addEventListener('change', function (e) {
        var inp = e.target;
        if (!inp.matches('[data-composer] input[type=file]')) return;
        var form = inp.closest('[data-composer]');
        var box = $('[data-previews]', form);
        var max = parseInt(inp.getAttribute('data-max') || '4', 10);
        box.innerHTML = '';
        var files = Array.prototype.slice.call(inp.files || []);
        if (files.length > max) { toast(tx('too_many_photos', max)); inp.value = ''; box.hidden = true; return; }
        files.forEach(function (f) { var img = document.createElement('img'); img.alt = ''; img.src = URL.createObjectURL(f); box.appendChild(img); });
        box.hidden = !files.length;
    });
    document.addEventListener('input', function (e) {
        var ta = e.target;
        if (ta.matches && ta.matches('[data-autogrow], [data-comment-form] textarea, [data-msg-form] textarea')) {
            ta.style.height = 'auto';
            ta.style.height = Math.min(ta.scrollHeight + 2, 320) + 'px';
        }
    });
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f.matches('[data-composer]')) return;
        e.preventDefault();
        syncEditors(f);
        var ta = $('textarea[name=body]', f), files = $('input[type=file]', f);
        if (!ta.value.trim() && !(files && files.files.length)) { toast(tx('empty_post')); return; }
        var b = $('.zl-push .zl-btn', f) || $('button:last-of-type', f);
        if (b) { b.disabled = true; b.__label = b.innerHTML; b.innerHTML = '<span class="zl-spin"></span>'; }
        req(f.getAttribute('action'), { body: new FormData(f) }).then(function (d) {
            var feed = $('[data-feed]');
            if (d.html && feed) {
                var empty = $('.zl-empty', feed); empty && empty.remove();
                feed.insertAdjacentHTML('afterbegin', d.html);
                reveal(feed);
            }
            resetComposer(f);
            var m = f.closest('.zl-modal'); m && closeModal(m);
            toast(d.message || tx('posted'));
            if (d.html && !feed && d.id) setTimeout(function () { location.href = (body.getAttribute('data-base') || '') + '/p/' + d.id; }, 500);
        }).catch(fail).then(function () { if (b) { b.disabled = false; b.innerHTML = b.__label; } });
    });

    /* ---------- badges ---------- */
    var badgeUrl = body.getAttribute('data-badges');
    function setBadge(name, n) {
        $$('[data-badge="' + name + '"]').forEach(function (a) {
            var c = $('.zl-count', a);
            if (n > 0) { if (!c) { c = document.createElement('span'); c.className = 'zl-count'; a.appendChild(c); } c.textContent = n > 99 ? '99+' : n; }
            else if (c) c.remove();
        });
        $$('[data-badge-dot="' + name + '"]').forEach(function (a) {
            var d = $('.zl-nav-dot', a);
            if (n > 0 && !d) { d = document.createElement('span'); d.className = 'zl-nav-dot'; a.appendChild(d); } else if (!n && d) d.remove();
        });
    }
    if (badgeUrl) {
        setInterval(function () {
            if (document.hidden) return;
            get(badgeUrl).then(function (d) { setBadge('notifications', d.notifications || 0); setBadge('messages', d.messages || 0); }).catch(function () {});
        }, 30000);
    }

    /* ---------- messages ---------- */
    var thread = $('[data-thread]');
    if (thread) {
        var lastId = function () { var b = $$('[data-mid]', thread); return b.length ? b[b.length - 1].getAttribute('data-mid') : 0; };
        var scrollDown = function () { thread.scrollTop = thread.scrollHeight; };
        var bubble = function (m) {
            if ($('[data-mid="' + m.id + '"]', thread)) return;
            var e = $('[data-thread-empty]', thread); e && e.remove();
            var d = document.createElement('div');
            d.className = 'zl-bubble' + (m.mine ? ' is-mine' : '');
            d.setAttribute('data-mid', m.id);
            d.innerHTML = (m.image ? '<img src="' + m.image.replace(/"/g, '&quot;') + '" alt="" loading="lazy">' : '') + m.html + '<time>' + m.time + '</time>';
            thread.appendChild(d);
        };
        scrollDown();
        var seen = $('[data-seen]');
        var poll = function () {
            if (document.hidden) return;
            get(thread.getAttribute('data-poll') + '?after=' + lastId()).then(function (d) {
                var near = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 140;
                (d.messages || []).forEach(bubble);
                if (d.messages && d.messages.length && near) scrollDown();
                if (seen) seen.hidden = !d.seen;
            }).catch(function () {});
        };
        setInterval(poll, 4000);
        var mf = $('[data-msg-form]');
        mf && mf.addEventListener('submit', function (e) {
            e.preventDefault();
            var ta = $('textarea', mf), file = $('input[type=file]', mf);
            if (!ta.value.trim() && !(file && file.files.length)) return;
            var btn = $('button', mf); btn.disabled = true;
            req(mf.getAttribute('action'), { body: new FormData(mf) }).then(function (d) {
                bubble(d.message); scrollDown(); ta.value = ''; ta.style.height = ''; if (file) file.value = ''; if (seen) seen.hidden = true;
            }).catch(fail).then(function () { btn.disabled = false; ta.focus(); });
        });
        var fileInp = mf && $('input[type=file]', mf);
        fileInp && fileInp.addEventListener('change', function () { if (fileInp.files.length) toast(tx('photo_ready')); });
    }

    /* ---------- stories ---------- */
    var sv = document.getElementById('story-viewer');
    var sTimer = null, sList = [], sIdx = 0, sUser = null, sTray = [], sTrayIdx = 0;
    function stopStories() { clearInterval(sTimer); sTimer = null; if (sv) { sv.hidden = true; $('[data-stage]', sv).innerHTML = ''; } if (!$('.zl-modal:not([hidden])')) body.classList.remove('zl-modal-open'); }
    function showStory(i) {
        if (i < 0) { if (sTrayIdx > 0) openStories(sTrayIdx - 1); return; }
        if (i >= sList.length) { if (sTrayIdx < sTray.length - 1) openStories(sTrayIdx + 1); else stopStories(); return; }
        sIdx = i;
        var s = sList[i];
        var stage = $('[data-stage]', sv);
        stage.innerHTML = '';
        var cap = function (cls) { if (!s.text) return; var c = document.createElement('div'); c.className = cls + ' zl-tc-' + (s.color || 'white') + ' zl-font-' + (s.font || 'modern'); c.textContent = s.text; stage.appendChild(c); };
        var vid = null;
        if (s.type === 'image') {
            var img = document.createElement('img'); img.src = s.image; img.alt = s.text || ''; stage.appendChild(img);
            cap('zl-story-cap zl-pos-' + (s.pos || 'bottom'));
        } else if (s.type === 'video') {
            vid = document.createElement('video'); vid.src = s.image; vid.playsInline = true; vid.setAttribute('playsinline', ''); stage.appendChild(vid);
            cap('zl-story-cap zl-pos-' + (s.pos || 'bottom'));
        } else {
            var t = document.createElement('div'); t.className = 'zl-story-text zl-bg-' + s.bg + ' zl-tc-' + (s.color || 'white') + ' zl-font-' + (s.font || 'modern'); t.textContent = s.text; stage.appendChild(t);
        }
        $('[data-ago]', sv).textContent = s.ago;
        var bars = $$('[data-bars] b', sv);
        bars.forEach(function (b, k) { b.style.width = k < i ? '100%' : '0%'; });
        var views = $('[data-views]', sv);
        if (s.views !== null && s.views !== undefined) { views.hidden = false; $('span', views).textContent = tx('n_views', s.views); } else views.hidden = true;
        if (!s.seen && !sUser.mine) { req((body.getAttribute('data-base') || '') + '/stories/' + s.id + '/view').catch(function () {}); s.seen = true; }
        clearInterval(sTimer);
        if (vid) {
            // a video story lasts as long as the video; it plays with sound when the browser allows it, otherwise muted
            vid.addEventListener('ended', function () { if (sIdx === i) showStory(i + 1); });
            vid.play().catch(function () { vid.muted = true; vid.play().catch(function () {}); });
            sTimer = setInterval(function () { if (isFinite(vid.duration) && vid.duration && bars[i]) bars[i].style.width = Math.min(100, vid.currentTime / vid.duration * 100) + '%'; }, 100);
            return;
        }
        var start = Date.now(), dur = 5000;
        sTimer = setInterval(function () {
            var p = Math.min(1, (Date.now() - start) / dur);
            if (bars[i]) bars[i].style.width = (p * 100) + '%';
            if (p >= 1) { clearInterval(sTimer); showStory(sIdx + 1); }
        }, 50);
    }
    function openStories(trayIdx) {
        var btn = sTray[trayIdx];
        if (!btn) return;
        sTrayIdx = trayIdx;
        get(btn.getAttribute('data-src')).then(function (d) {
            if (!d.stories || !d.stories.length) { toast(tx('no_stories')); return; }
            sList = d.stories; sUser = d;
            var ring = $('.zl-ring', btn); ring && !d.mine && ring.classList.add('is-seen');
            sv.hidden = false; body.classList.add('zl-modal-open');
            var av = $('[data-avatar]', sv);
            av.innerHTML = d.user.avatar ? '<span class="zl-av zl-av-sm"><img src="' + d.user.avatar.replace(/"/g, '&quot;') + '" alt=""></span>' : '<span class="zl-av zl-av-sm"></span>';
            if (!d.user.avatar) av.firstChild.textContent = d.user.initials;
            var name = $('[data-name]', sv); name.textContent = d.user.name; name.href = d.user.url;
            $('[data-bars]', sv).innerHTML = sList.map(function () { return '<i><b></b></i>'; }).join('');
            $('[data-story-delete]', sv).hidden = !d.mine;
            var first = 0; for (var k = 0; k < sList.length; k++) { if (!sList[k].seen) { first = k; break; } }
            showStory(d.mine ? 0 : first);
            $('[data-story-close]', sv).focus();
        }).catch(fail);
    }
    if (sv) {
        sTray = $$('[data-story-user]');
        document.addEventListener('click', function (e) {
            var b = e.target.closest('[data-story-user]');
            if (b) { openStories(sTray.indexOf(b)); return; }
            if (e.target.closest('[data-story-close]')) { stopStories(); return; }
            if (e.target.closest('[data-story-next]')) { showStory(sIdx + 1); return; }
            if (e.target.closest('[data-story-prev]')) { showStory(sIdx - 1); return; }
            if (e.target.closest('[data-story-delete]')) {
                clearInterval(sTimer);
                var s = sList[sIdx];
                req((body.getAttribute('data-base') || '') + '/stories/' + s.id + '/delete').then(function () { stopStories(); location.reload(); }).catch(fail);
            }
        });
        document.addEventListener('keydown', function (e) {
            if (sv.hidden) return;
            if (e.key === 'ArrowRight') showStory(sIdx + 1);
            if (e.key === 'ArrowLeft') showStory(sIdx - 1);
        });
    }

    /* ---------- story composer: photo, camera, video or a photo from your posts, with a live preview ---------- */
    var stf = $('[data-story-form]');
    if (stf) {
        var stPrev = $('[data-st-preview]', stf), stImg = $('[data-st-img]', stf), stVid = $('[data-st-video]', stf), stEmpty = $('[data-st-empty]', stf);
        var stCap = $('[data-st-cap]', stf), stFrom = $('[data-st-from]', stf), stGrid = $('[data-st-grid]', stf), stText = $('[data-st-text]', stf), stUrl = null;
        var stVal = function (n) { return ($('input[name="' + n + '"]:checked', stf) || {}).value || ''; };
        var stHasMedia = function () { return !stImg.hidden || !stVid.hidden; };
        var stRefresh = function () {
            var type = stVal('type') || 'image';
            stPrev.className = 'zl-st-preview is-' + type + ' zl-tc-' + stVal('color') + ' zl-font-' + stVal('font')
                + (type === 'text' ? ' zl-bg-' + stVal('bg') : ' zl-pos-' + stVal('pos')) + (stHasMedia() ? ' has-media' : '');
            $$('[data-st-panel]', stf).forEach(function (p) { p.hidden = p.getAttribute('data-st-panel') !== type; });
            var lab = $('[data-st-label]', stf); lab.textContent = lab.getAttribute('data-l-' + type) || lab.textContent;
            stCap.textContent = stText.value;
            if (type === 'text') stVid.pause(); else if (!stVid.hidden) stVid.play().catch(function () {});
        };
        var stClear = function (keep) {
            if (stUrl) { URL.revokeObjectURL(stUrl); stUrl = null; }
            stImg.hidden = true; stImg.removeAttribute('src');
            stVid.pause(); stVid.hidden = true; stVid.removeAttribute('src');
            stEmpty.hidden = false; stFrom.value = '';
            $$('[data-st-file]', stf).forEach(function (inp) { if (inp !== keep) inp.value = ''; });
            $$('[data-st-pick].is-on', stGrid).forEach(function (b) { b.classList.remove('is-on'); });
        };
        stf.addEventListener('change', function (e) {
            var inp = e.target;
            if (inp.hasAttribute('data-st-file')) {
                var f = inp.files && inp.files[0];
                stClear(inp);
                if (f) {
                    stUrl = URL.createObjectURL(f);
                    if (/^video\//.test(f.type)) {
                        var mb = +stf.getAttribute('data-video-mb'), sec = +stf.getAttribute('data-video-sec');
                        if (f.size > mb * 1048576) { toast(tx('story_video_big').replace(':n', mb)); stClear(); stRefresh(); return; }
                        stVid.muted = true; stVid.src = stUrl; stVid.hidden = false; stEmpty.hidden = true;
                        stVid.onloadedmetadata = function () {
                            if (isFinite(stVid.duration) && stVid.duration > sec + 0.5) { toast(tx('story_video_long').replace(':n', sec)); stClear(); stRefresh(); return; }
                            stVid.play().catch(function () {});
                        };
                        stVid.onerror = function () { toast(tx('story_video_bad')); stClear(); stRefresh(); };
                    } else {
                        stImg.src = stUrl; stImg.hidden = false; stEmpty.hidden = true;
                    }
                    $('#st-image').checked = true;
                }
            }
            stRefresh();
        });
        stText.addEventListener('input', function () { stCap.textContent = stText.value; });
        $('[data-st-posts]', stf).addEventListener('click', function () {
            stGrid.hidden = !stGrid.hidden;
            if (stGrid.hidden || stGrid.__loaded) return;
            stGrid.__loaded = true;
            stGrid.innerHTML = '<span class="zl-spin"></span>';
            get(stf.getAttribute('data-photos-url')).then(function (d) {
                stGrid.innerHTML = '';
                if (!d.photos || !d.photos.length) { var p = document.createElement('p'); p.className = 'zl-faint'; p.textContent = tx('story_no_photos'); stGrid.appendChild(p); return; }
                d.photos.forEach(function (ph) {
                    var b = document.createElement('button'); b.type = 'button'; b.setAttribute('data-st-pick', ph.id); b.setAttribute('aria-label', tx('story_use_photo'));
                    var im = document.createElement('img'); im.src = ph.thumb; im.alt = ''; im.loading = 'lazy'; b.appendChild(im); stGrid.appendChild(b);
                });
            }).catch(function (e) { stGrid.__loaded = false; stGrid.innerHTML = ''; fail(e); });
        });
        stGrid.addEventListener('click', function (e) {
            var b = e.target.closest('[data-st-pick]');
            if (!b) return;
            stClear();
            stFrom.value = b.getAttribute('data-st-pick'); b.classList.add('is-on');
            stImg.src = $('img', b).src; stImg.hidden = false; stEmpty.hidden = true;
            stRefresh();
        });
        stf.addEventListener('submit', function (e) {
            var type = stVal('type');
            if (type === 'image' && !stHasMedia()) { e.preventDefault(); toast(tx('story_need_media')); return; }
            if (type === 'text' && !stText.value.trim()) { e.preventDefault(); stText.focus(); toast(tx('story_need_text')); return; }
            if (type === 'text') stClear(); // a text story sends no file
            stf.setAttribute('data-long-task', stf.getAttribute('data-busy-text') || '');
            longTask(stf);
        });
        stRefresh();
    }

    /* ---------- photo viewer ---------- */
    var lb = document.getElementById('lightbox'), lbList = [], lbIdx = 0;
    function lbShow(i) { lbIdx = (i + lbList.length) % lbList.length; $('[data-lb-img]', lb).src = lbList[lbIdx]; $('[data-lb-count]', lb).textContent = lbList.length > 1 ? (lbIdx + 1) + ' / ' + lbList.length : ''; }
    function closeLightbox() { if (lb) { lb.hidden = true; if (!$('.zl-modal:not([hidden])')) body.classList.remove('zl-modal-open'); } }
    document.addEventListener('click', function (e) {
        var a = e.target.closest('[data-lightbox]');
        if (a && lb) {
            e.preventDefault();
            var g = a.getAttribute('data-lightbox');
            var all = $$('[data-lightbox="' + g + '"]');
            lbList = all.map(function (x) { return x.getAttribute('href'); });
            lb.hidden = false; body.classList.add('zl-modal-open');
            lbShow(all.indexOf(a));
            $('[data-lb-close]', lb).focus();
            return;
        }
        if (!lb || lb.hidden) return;
        if (e.target.closest('[data-lb-close]')) closeLightbox();
        else if (e.target.closest('[data-lb-next]')) lbShow(lbIdx + 1);
        else if (e.target.closest('[data-lb-prev]')) lbShow(lbIdx - 1);
    });
    document.addEventListener('keydown', function (e) {
        if (!lb || lb.hidden) return;
        if (e.key === 'ArrowRight') lbShow(lbIdx + 1);
        if (e.key === 'ArrowLeft') lbShow(lbIdx - 1);
    });

    /* ---------- live search ---------- */
    $$('[data-live-search]').forEach(function (form) {
        var input = $('input[name=q]', form), dd = $('.zl-dd', form), t = null, url = form.getAttribute('data-live-search');
        var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
        input.addEventListener('input', function () {
            clearTimeout(t);
            var q = input.value.trim();
            if (q.length < 2) { dd.hidden = true; return; }
            t = setTimeout(function () {
                get(url + '?q=' + encodeURIComponent(q)).then(function (d) {
                    var h = '';
                    (d.users || []).forEach(function (u) {
                        h += '<a href="' + esc(u.url) + '">' + (u.avatar ? '<span class="zl-av zl-av-sm"><img src="' + esc(u.avatar) + '" alt=""></span>' : '<span class="zl-av zl-av-sm">' + esc(u.initials) + '</span>')
                            + '<span><b>' + esc(u.name) + '</b><br><small>@' + esc(u.username) + '</small></span></a>';
                    });
                    (d.tags || []).forEach(function (g) { h += '<a href="' + esc(g.url) + '"><span class="zl-tag">#' + esc(g.tag) + '</span><small>' + esc(tx('n_posts', g.n)) + '</small></a>'; });
                    if (!h) h = '<p class="zl-faint" style="padding:10px">' + esc(tx('no_results')) + '</p>';
                    dd.innerHTML = h; dd.hidden = false;
                }).catch(function () {});
            }, 220);
        });
        document.addEventListener('click', function (e) { if (!form.contains(e.target)) dd.hidden = true; });
    });

    /* ---------- sideways rows: arrows on devices with a mouse ---------- */
    if (window.matchMedia && window.matchMedia('(any-hover: hover)').matches) {
        $$('.zl-chips, .zl-tabs, .zl-tray, .zl-settings-nav').forEach(function (row) {
            if (getComputedStyle(row).flexDirection === 'column') return;
            var wrap = document.createElement('div'); wrap.className = 'zl-hs-wrap';
            row.parentNode.insertBefore(wrap, row); wrap.appendChild(row);
            ['l', 'r'].forEach(function (side) {
                var b = document.createElement('button'); b.type = 'button'; b.className = 'zl-hs-btn is-' + side;
                b.setAttribute('aria-label', tx(side === 'l' ? 'scroll_left' : 'scroll_right'));
                b.innerHTML = '<svg class="zl-i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + (side === 'l' ? 'M15 18l-6-6 6-6' : 'M9 6l6 6-6 6') + '"/></svg>';
                b.addEventListener('click', function () { row.scrollBy({ left: (side === 'l' ? -1 : 1) * Math.max(160, row.clientWidth * .7), behavior: 'smooth' }); });
                wrap.appendChild(b);
            });
            var upd = function () { var max = row.scrollWidth - row.clientWidth; wrap.classList.toggle('can-l', row.scrollLeft > 4); wrap.classList.toggle('can-r', row.scrollLeft < max - 4); };
            row.addEventListener('scroll', upd, { passive: true });
            row.addEventListener('wheel', function (e) {
                if (Math.abs(e.deltaY) <= Math.abs(e.deltaX)) return;
                var max = row.scrollWidth - row.clientWidth;
                if (max <= 4 || (e.deltaY < 0 && row.scrollLeft <= 0) || (e.deltaY > 0 && row.scrollLeft >= max - 1)) return;
                e.preventDefault(); row.scrollLeft += e.deltaY;
            }, { passive: false });
            window.addEventListener('resize', upd);
            upd();
        });
    }

    /* =====================================================================
       Editor, photos, menus, animations
       ===================================================================== */
    var esc = function (str) { return String(str).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    var anim = body.classList.contains('zl-anim') && !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    function store(k, v) { try { if (v === undefined) return localStorage.getItem(k); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, v); } catch (e) { return null; } }

    /* ---------- rich text editor (contenteditable; the server cleans the HTML) ---------- */
    var SVG = function (d) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + d + '"/></svg>'; };
    var SVG_EYE_OFF = SVG('M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2');
    var SVG_LINK = SVG('M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1');
    var SVG_UL = SVG('M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01');
    function focusEnd(el) {
        el.focus();
        try { var r = document.createRange(); r.selectNodeContents(el); r.collapse(false); var s2 = window.getSelection(); s2.removeAllRanges(); s2.addRange(r); } catch (e) {}
    }
    function richify(wrap) {
        if (!wrap || wrap.__rich || !wrap.hasAttribute('data-rich') || !document.queryCommandSupported) return;
        var ta = $('textarea', wrap), fmt = $('[data-format-input]', wrap);
        if (!ta || !fmt) return;
        wrap.__rich = true;
        var area = document.createElement('div');
        area.className = 'zl-editor-area' + (ta.classList.contains('zl-input') ? ' zl-input' : '') + (ta.classList.contains('zl-editor-tall') ? ' is-tall' : '');
        area.contentEditable = 'true'; area.setAttribute('role', 'textbox'); area.setAttribute('aria-multiline', 'true');
        area.setAttribute('aria-label', ta.getAttribute('placeholder') || (wrap.querySelector('label') || {}).textContent || '');
        area.setAttribute('data-placeholder', ta.getAttribute('placeholder') || '');
        area.innerHTML = wrap.getAttribute('data-format') === 'html' ? ta.value : esc(ta.value).replace(/\n/g, '<br>');
        ta.hidden = true; fmt.value = 'html';
        wrap.insertBefore(area, ta);
        wrap.__area = area;
        try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}
        var sync = function () { ta.value = area.textContent.trim() === '' && !area.querySelector('hr') ? '' : area.innerHTML; ta.dispatchEvent(new Event('input', { bubbles: true })); };
        area.addEventListener('paste', function (e) {
            var t = (e.clipboardData || window.clipboardData); if (!t) return;
            e.preventDefault();
            document.execCommand('insertText', false, t.getData('text/plain'));
        });
        area.addEventListener('input', sync);
        // Markdown-style shortcuts at the start of a line: "## " heading, "### " subheading, "> " quote, "- " list, "1. " numbered list, "```" code
        area.addEventListener('keydown', function (e) {
            if (e.key !== ' ' && e.key !== '`') return;
            var sl = window.getSelection(); if (!sl.rangeCount || !sl.isCollapsed) return;
            var node = sl.anchorNode, off = sl.anchorOffset;
            if (!node || node.nodeType !== 3 || !area.contains(node)) return;
            var before = node.nodeValue.slice(0, off), block = node.parentNode;
            while (block !== area && !/^(P|DIV|LI|H2|H3|BLOCKQUOTE)$/.test(block.nodeName)) block = block.parentNode;
            if (block !== area && block.firstChild !== node && !(block.firstChild && block.firstChild.contains && block.firstChild.contains(node))) return;
            if (block === area && node.previousSibling && node.previousSibling.nodeName !== 'BR') return;
            var map = e.key === ' ' ? { '##': ['formatBlock', '<h2>'], '###': ['formatBlock', '<h3>'], '>': ['formatBlock', '<blockquote>'], '-': ['insertUnorderedList'], '*': ['insertUnorderedList'], '1.': ['insertOrderedList'] }
                : { '``': ['code'] };
            var cmd = map[before]; if (!cmd) return;
            e.preventDefault();
            var r = document.createRange(); r.setStart(node, 0); r.setEnd(node, off); sl.removeAllRanges(); sl.addRange(r);
            document.execCommand('delete');
            if (cmd[0] === 'code') document.execCommand('insertHTML', false, '<code>&#8203;</code>');
            else document.execCommand(cmd[0], false, cmd[1]);
            sync();
        });
        area.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { var f = wrap.closest('form'); if (f) { e.preventDefault(); f.requestSubmit ? f.requestSubmit() : f.submit(); } }
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k' && selIn()) { e.preventDefault(); linkMode(true); }
            if (e.key === 'Escape' && !bub.hidden) { e.stopPropagation(); hide(); }
            // Enter on an empty quote or heading line leaves it (like lists do)
            if (e.key === 'Enter' && !e.shiftKey) {
                var blk = inside('blockquote, h2, h3');
                if (blk && blk.textContent.replace(/[\u200b\u00a0\s]/g, '') === '') {
                    e.preventDefault();
                    var np = document.createElement('p'); np.appendChild(document.createElement('br'));
                    blk.parentNode.replaceChild(np, blk);
                    var r0 = document.createRange(); r0.setStart(np, 0); r0.collapse(true); var s0 = window.getSelection(); s0.removeAllRanges(); s0.addRange(r0);
                    sync();
                }
            }
        });

        /* ---- floating toolbar over the selected text ---- */
        var BUB = [['bold', '<b>B</b>', 'b_bold'], ['italic', '<i style="font-family:serif">I</i>', 'b_italic'], ['underline', '<u>U</u>', 'b_underline'],
            ['strikeThrough', '<s>S</s>', 'b_strike'], ['insertUnorderedList', SVG_UL, 'b_ul'], '|', ['spoiler', SVG_EYE_OFF, 'b_spoiler'], ['link', SVG_LINK, 'b_link'], '|',
            ['close', SVG('M6 6l12 12M18 6L6 18'), 'b_close']];
        var bub = document.createElement('div');
        bub.className = 'zl-bubble-bar'; bub.hidden = true; bub.setAttribute('role', 'toolbar'); bub.setAttribute('aria-label', tx('b_toolbar'));
        var tools = document.createElement('div'); tools.className = 'zl-bubble-tools';
        BUB.forEach(function (t) {
            if (t === '|') { var sp = document.createElement('span'); sp.className = 'sep'; tools.appendChild(sp); return; }
            var b = document.createElement('button'); b.type = 'button'; b.innerHTML = t[1]; b.setAttribute('data-bcmd', t[0]); b.setAttribute('aria-label', tx(t[2])); b.title = tx(t[2]);
            tools.appendChild(b);
        });
        var lrow = document.createElement('div'); lrow.className = 'zl-bubble-link'; lrow.hidden = true;
        lrow.innerHTML = '<input type="url" inputmode="url" spellcheck="false" autocomplete="off" placeholder="https://" aria-label="' + esc(tx('link_prompt')) + '">'
            + '<button type="button" data-link-apply aria-label="' + esc(tx('b_apply')) + '" title="' + esc(tx('b_apply')) + '">' + SVG('M5 12.5l4.5 4.5L19 7.5') + '</button>'
            + '<button type="button" data-link-cancel aria-label="' + esc(tx('b_close')) + '" title="' + esc(tx('b_close')) + '">' + SVG('M6 6l12 12M18 6L6 18') + '</button>';
        bub.appendChild(tools); bub.appendChild(lrow);
        document.body.appendChild(bub);
        var linkInput = $('input', lrow), saved = null, timer = null;

        function selIn() {
            var sl = window.getSelection();
            if (!sl.rangeCount || sl.isCollapsed) return null;
            var r = sl.getRangeAt(0);
            return area.contains(r.commonAncestorContainer) && String(sl).trim() !== '' ? r : null;
        }
        function inside(tag) {
            var sl = window.getSelection(); if (!sl.rangeCount) return null;
            var n = sl.anchorNode;
            while (n && n !== area) { if (n.nodeType === 1 && n.matches(tag)) return n; n = n.parentNode; }
            return null;
        }
        function place(r) {
            var rect = r.getBoundingClientRect();
            var rects = r.getClientRects(); if (rects.length) rect = rects[0]; // first line of a multi-line selection
            if (!rect.width && !rect.height) { hide(); return; }
            bub.hidden = false;
            var bw = bub.offsetWidth, bh = bub.offsetHeight, x = rect.left + rect.width / 2 - bw / 2, y = rect.top - bh - 10, below = false;
            x = Math.max(8, Math.min(x, window.innerWidth - bw - 8));
            if (y < 8) { y = r.getBoundingClientRect().bottom + 10; below = true; }
            bub.style.left = Math.round(x) + 'px'; bub.style.top = Math.round(y) + 'px';
            bub.classList.toggle('is-below', below);
        }
        function hide() { bub.hidden = true; linkMode(false, true); }
        function bstates() {
            $$('[data-bcmd]', bub).forEach(function (b) {
                var c = b.getAttribute('data-bcmd'), on = false;
                try { on = c === 'spoiler' ? !!inside('.zl-spoiler') : c === 'link' ? !!inside('a') : c === 'close' ? false : document.queryCommandState(c); } catch (e) {}
                b.classList.toggle('is-on', !!on); b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }
        function update() {
            if (bub.__linking) return;
            var r = selIn();
            if (!r) { bub.hidden = true; return; }
            bstates(); place(r);
        }
        function cleanUrl(u) {
            u = String(u).trim();
            if (!u || u === 'https://') return '';
            if (!/^(https?:\/\/|mailto:|\/)/i.test(u)) u = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(u) ? 'mailto:' + u : 'https://' + u.replace(/^\/+/, '');
            return /^(https?:\/\/[^\s<>"']+\.[^\s<>"']+|mailto:[^\s<>"']+|\/[^\s<>"']*)$/i.test(u) ? u : '';
        }
        function linkMode(on, quiet) {
            if (on) {
                var r = selIn(); if (!r) return;
                saved = r.cloneRange(); bub.__linking = true;
                var a = inside('a'); linkInput.value = a ? a.getAttribute('href') : '';
                // keep the chosen words highlighted while the link is typed (browsers with the CSS Highlight API)
                try { if (window.Highlight && CSS.highlights) CSS.highlights.set('zl-link-target', new Highlight(saved)); } catch (e) {}
                tools.hidden = true; lrow.hidden = false; linkInput.classList.remove('is-bad');
                linkInput.focus(); linkInput.select(); // right away, so fast typing never lands in the post
                place(saved);
            } else {
                if (!bub.__linking) return;
                bub.__linking = false; tools.hidden = false; lrow.hidden = true; unmark();
                if (!quiet && saved) restore();
            }
        }
        function unmark() { try { if (window.CSS && CSS.highlights) CSS.highlights.delete('zl-link-target'); } catch (e) {} }
        function restore() { unmark(); area.focus(); var sl = window.getSelection(); sl.removeAllRanges(); if (saved) sl.addRange(saved); }
        function applyLink() {
            var u = cleanUrl(linkInput.value);
            if (!u && linkInput.value.trim() !== '') { linkInput.classList.add('is-bad'); linkInput.focus(); return; }
            linkInput.classList.remove('is-bad');
            restore();
            bub.__linking = false; tools.hidden = false; lrow.hidden = true;
            if (u) document.execCommand('createLink', false, u); else document.execCommand('unlink');
            sync(); update();
        }
        function spoiler() {
            var sp = inside('.zl-spoiler');
            if (sp) { // take the spoiler off: keep the text
                var par = sp.parentNode; while (sp.firstChild) par.insertBefore(sp.firstChild, sp); par.removeChild(sp);
            } else {
                var r = selIn(); if (!r) return;
                var span = document.createElement('span'); span.className = 'zl-spoiler';
                span.appendChild(r.extractContents());
                $$('.zl-spoiler', span).forEach(function (inner) { while (inner.firstChild) inner.parentNode.insertBefore(inner.firstChild, inner); inner.remove(); });
                r.insertNode(span);
                var sl = window.getSelection(), nr = document.createRange(); nr.selectNodeContents(span); sl.removeAllRanges(); sl.addRange(nr);
            }
            sync();
        }
        bub.addEventListener('mousedown', function (e) { if (!e.target.closest('input')) e.preventDefault(); });
        bub.addEventListener('click', function (e) {
            if (e.target.closest('[data-link-apply]')) { applyLink(); return; }
            if (e.target.closest('[data-link-cancel]')) { linkMode(false); update(); return; }
            var b = e.target.closest('[data-bcmd]'); if (!b) return;
            var c = b.getAttribute('data-bcmd');
            if (c === 'close') { var sl = window.getSelection(); if (sl.rangeCount) sl.collapseToEnd(); hide(); return; }
            if (c === 'link') { if (inside('a')) { document.execCommand('unlink'); sync(); update(); } else linkMode(true); return; }
            if (c === 'spoiler') spoiler(); else document.execCommand(c);
            sync(); update();
        });
        linkInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); applyLink(); }
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); linkMode(false); update(); }
        });
        document.addEventListener('selectionchange', function () { clearTimeout(timer); timer = setTimeout(update, 50); });
        window.addEventListener('scroll', function () { if (!bub.hidden) { var r = bub.__linking ? saved : selIn(); r ? place(r) : hide(); } }, true);
        window.addEventListener('resize', function () { if (!bub.hidden) update(); });
        area.addEventListener('blur', function () { setTimeout(function () { if (!bub.contains(document.activeElement) && !selIn()) hide(); }, 120); });
        sync();
    }
    function syncEditors(root) { $$('.zl-editor', root).forEach(function (w) { if (w.__area) { var ta = $('textarea', w); ta.value = w.__area.textContent.trim() === '' && !w.__area.querySelector('hr') ? '' : w.__area.innerHTML; } }); }
    $$('.zl-editor[data-rich]').forEach(function (w) { if (!w.closest('[data-edit-form]')) richify(w); });
    // forms that are submitted normally (site pages) also need the editor content
    document.addEventListener('submit', function (e) { if (e.target.querySelector && e.target.querySelector('.zl-editor')) syncEditors(e.target); }, true);

    /* ---------- spoilers in posts: tap to show ---------- */
    function markSpoilers() {
        $$('.zl-rich .zl-spoiler:not([tabindex])').forEach(function (sp) { sp.setAttribute('tabindex', '0'); sp.setAttribute('role', 'button'); sp.setAttribute('aria-label', tx('spoiler_reveal')); });
    }
    markSpoilers();
    new MutationObserver(markSpoilers).observe(body, { childList: true, subtree: true });
    document.addEventListener('click', function (e) {
        var sp = e.target.closest('.zl-rich .zl-spoiler');
        if (sp && !sp.classList.contains('is-shown')) { e.preventDefault(); sp.classList.add('is-shown'); sp.removeAttribute('role'); sp.removeAttribute('aria-label'); }
    });
    document.addEventListener('keydown', function (e) {
        var sp = e.target.closest && e.target.closest('.zl-rich .zl-spoiler');
        if (sp && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); sp.click(); }
    });

    /* ---------- composer extras: counter, draft, emoji, anonymous, post as page ---------- */
    function resetComposer(f) {
        f.reset();
        $$('.zl-editor', f).forEach(function (w) { if (w.__area) w.__area.innerHTML = ''; $('textarea', w).value = ''; });
        var pv = $('[data-previews]', f); if (pv) { pv.innerHTML = ''; pv.hidden = true; }
        var ta = $('textarea[name=body]', f); ta && ta.dispatchEvent(new Event('input', { bubbles: true }));
        store('zl-draft:' + f.getAttribute('data-draft'), null);
        composerState(f);
    }
    function composerState(f) {
        var anon = $('[data-anon-toggle]', f), asPage = $('[data-post-as]', f), priv = $('[data-privacy]', f);
        var pageOn = asPage && asPage.value !== '';
        if (anon) { anon.disabled = pageOn; if (pageOn) anon.checked = false; anon.closest('label').style.opacity = pageOn ? '.4' : ''; }
        if (priv) priv.disabled = pageOn || (anon && anon.checked);
    }
    var EMOJI = '😀 😂 😍 🥰 😎 🤩 😉 🤔 😢 😡 🥳 😇 👍 👏 🙏 🙌 💪 🤝 👀 🔥 🎉 ❤️ 💜 💙 ✨ 🌟 💯 ✅ 🚀 🌈 ☀️ 🌙 ⚡ 🍕 ☕ 🎵 📸 🎮 ⚽ 🏆 💡 📌'.split(' ');
    $$('[data-composer]').forEach(function (f) {
        var ta = $('textarea[name=body]', f), counter = $('[data-counter]', f), key = 'zl-draft:' + f.getAttribute('data-draft');
        var max = parseInt((ta && ta.getAttribute('data-max')) || '0', 10), timer = null;
        var w = ta && ta.closest('.zl-editor');
        var plainLen = function () { return w && w.__area ? w.__area.textContent.length : ta.value.length; };
        var upd = function () {
            if (!counter || !max) return;
            var left = max - plainLen();
            counter.hidden = left > Math.min(300, max * .2);
            counter.textContent = tx('chars_left', left);
            counter.classList.toggle('is-low', left <= 100 && left >= 0); counter.classList.toggle('is-over', left < 0);
        };
        ta && ta.addEventListener('input', function () {
            upd();
            clearTimeout(timer);
            timer = setTimeout(function () { var v = (w && w.__area) ? w.__area.innerHTML : ta.value; (w && w.__area ? w.__area.textContent.trim() : ta.value.trim()) ? store(key, v) : store(key, null); }, 400);
        });
        var draft = store(key);
        if (draft && ta && !ta.value) {
            if (w && w.__area) { w.__area.innerHTML = draft; syncEditors(f); } else { ta.value = draft.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, ''); }
            if (f.getAttribute('data-draft').indexOf('modal') !== 0) toast(tx('draft_restored'));
        }
        var list = $('[data-emoji-list]', f);
        if (list) {
            list.innerHTML = EMOJI.map(function (em) { return '<button type="button" data-emoji>' + em + '</button>'; }).join('');
            list.addEventListener('click', function (e) {
                var b = e.target.closest('[data-emoji]'); if (!b) return;
                if (w && w.__area) { w.__area.focus(); document.execCommand('insertText', false, b.textContent); }
                else if (ta) { var p0 = ta.selectionStart || ta.value.length; ta.value = ta.value.slice(0, p0) + b.textContent + ta.value.slice(ta.selectionEnd || p0); ta.focus(); ta.dispatchEvent(new Event('input', { bubbles: true })); }
                closeMenus();
            });
        }
        f.addEventListener('change', function (e) { if (e.target.matches('[data-anon-toggle], [data-post-as]')) { composerState(f); if (e.target.matches('[data-anon-toggle]') && e.target.checked) toast(tx('anon_on')); } });
        composerState(f); upd();
    });

    /* ---------- generic post actions (pin, hide, show) ---------- */
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-post-action]');
        if (!b) return;
        closeMenus();
        req(b.getAttribute('data-post-action')).then(function (d) { toast(d.message); setTimeout(function () { location.reload(); }, 450); }).catch(fail);
    });

    /* ---------- change photo / cover right away ---------- */
    document.addEventListener('change', function (e) {
        var inp = e.target;
        if (!inp.matches('[data-photo-input]') || !inp.files.length) return;
        photoSend(inp.closest('[data-photo-form]'));
    });
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f.matches('[data-photo-form]')) return;
        e.preventDefault(); photoSend(f);
    });
    function photoSend(f) {
        var box = document.querySelector(f.getAttribute('data-target')), type = f.getAttribute('data-type'), removing = f.hasAttribute('data-remove');
        box && box.classList.add('is-uploading');
        req(f.getAttribute('action'), { body: new FormData(f) }).then(function (d) {
            if (!box) return;
            if (type === 'cover') {
                var img = box.querySelector(':scope > img');
                if (removing) { img && img.remove(); }
                else { if (!img) { img = document.createElement('img'); img.alt = ''; box.insertBefore(img, box.firstChild); } img.src = d.url; }
                var rm = box.querySelector('[data-remove]'); if (rm) rm.hidden = removing;
            } else {
                var av = box.querySelector('.zl-av');
                if (av && d.url) av.innerHTML = '<img src="' + esc(d.url) + '" alt="">';
            }
            toast(d.message);
        }).catch(fail).then(function () { box && box.classList.remove('is-uploading'); var i = f.querySelector('input[type=file]'); if (i) i.value = ''; });
    }

    /* ---------- follow pages ---------- */
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f.matches('[data-page-follow]')) return;
        e.preventDefault();
        var b = $('button', f); b.disabled = true;
        req(f.getAttribute('action'), { body: new FormData(f) }).then(function (d) {
            b.textContent = b.getAttribute(d.following ? 'data-l-on' : 'data-l-off');
            b.classList.toggle('zl-btn-main', !d.following); b.classList.toggle('zl-btn-soft', d.following);
            var card = f.closest('.zl-card'), n = card && $('[data-page-followers]', card);
            if (n) n.textContent = tx('n_followers', d.followers);
            if (d.following) burst(b);
        }).catch(fail).then(function () { b.disabled = false; });
    });

    /* ---------- admin: select posts for bulk actions ---------- */
    $$('[data-bulk-form]').forEach(function (form) {
        var all = $('[data-check-all]', form), label = $('[data-bulk-count]', form), bar = $('[data-bulk-bar]', form);
        var upd = function () {
            var boxes = $$('[data-check]', form), n = boxes.filter(function (c) { return c.checked; }).length;
            boxes.forEach(function (c) { var tr = c.closest('tr'); tr && tr.classList.toggle('is-checked', c.checked); });
            $$('[data-bulk-btn]', form).forEach(function (b) { b.disabled = !n; });
            label.textContent = n ? tx('selected', n) : tx('select_all');
            bar.classList.toggle('has-sel', n > 0);
            if (all) { all.checked = n > 0 && n === boxes.length; all.indeterminate = n > 0 && n < boxes.length; }
        };
        form.addEventListener('change', function (e) {
            if (e.target === all) $$('[data-check]', form).forEach(function (c) { c.checked = all.checked; });
            upd();
        });
        form.addEventListener('click', function (e) {
            var b = e.target.closest('[data-bulk-confirm]'); if (!b) return;
            e.preventDefault();
            ask(b.getAttribute('data-bulk-confirm'), function () { var h = document.createElement('input'); h.type = 'hidden'; h.name = 'action'; h.value = b.value; form.appendChild(h); form.submit(); });
        });
        upd();
    });

    /* ---------- admin: drop an extension .zip ---------- */
    $$('[data-drop]').forEach(function (zone) {
        var inp = $('[data-drop-input]', zone), lab = $('[data-drop-label]', zone), def = lab.textContent;
        var show = function () { lab.textContent = inp.files.length ? inp.files[0].name : def; };
        ['dragenter', 'dragover'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-over'); }); });
        ['dragleave', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function () { zone.classList.remove('is-over'); }); });
        zone.addEventListener('drop', function (e) { e.preventDefault(); if (e.dataTransfer && e.dataTransfer.files.length) { inp.files = e.dataTransfer.files; show(); } });
        inp.addEventListener('change', show);
    });

    /* ---------- admin settings: remember the section, live accent preview ---------- */
    var sf = $('[data-settings-form]');
    if (sf) {
        sf.addEventListener('submit', function () { var si = $('[data-section-input]', sf); if (si) si.value = (location.hash || '').slice(1); });
        $$('[data-accent-pick]', sf).forEach(function (r) {
            r.addEventListener('change', function () { var h = document.documentElement; h.className = h.className.replace(/\baccent-\w+/, '') + ' accent-' + r.value; });
        });
    }

    /* ---------- animations ---------- */
    function burst(el) {
        if (!anim) return;
        el.classList.remove('is-pop'); void el.offsetWidth; el.classList.add('is-pop');
        if (getComputedStyle(el).position === 'static') el.style.position = 'relative';
        var colors = ['#f43f5e', '#a78bfa', '#22d3ee', '#f59e0b', '#ec4899', '#22c55e'];
        for (var i = 0; i < 8; i++) {
            var d = document.createElement('i'), a = (Math.PI * 2 * i) / 8, r = 22 + Math.random() * 10;
            d.className = 'zl-burst'; d.style.left = '50%'; d.style.top = '50%'; d.style.marginLeft = '-3px'; d.style.marginTop = '-3px';
            d.style.background = colors[i % colors.length];
            d.style.setProperty('--dx', Math.round(Math.cos(a) * r) + 'px'); d.style.setProperty('--dy', Math.round(Math.sin(a) * r) + 'px');
            el.appendChild(d);
            setTimeout(function (x) { return function () { x.remove(); }; }(d), 650);
        }
    }
    var rio = ('IntersectionObserver' in window && anim) ? new IntersectionObserver(function (es) {
        es.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-in'); rio.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -40px 0px' }) : null;
    function reveal(root) {
        if (!rio) return;
        var k = 0;
        $$('[data-feed] > .zl-post, .zl-group-card, .zl-feat, .zl-stat, .zl-reveal, .zl-ext, .zl-hero-card', root || document).forEach(function (el) {
            if (el.__rv || el.classList.contains('is-fresh')) return;
            el.__rv = 1; el.classList.add('zl-rv');
            var rect = el.getBoundingClientRect();
            if (rect.top < window.innerHeight) el.style.transitionDelay = Math.min(k++ * 60, 360) + 'ms';
            rio.observe(el);
        });
    }
    reveal();
    if (rio) new MutationObserver(function () { reveal(); }).observe(body, { childList: true, subtree: true });

    // numbers count up when they come into view
    var counters = $$('[data-count]');
    if (anim && counters.length && 'IntersectionObserver' in window) {
        var cio = new IntersectionObserver(function (es) {
            es.forEach(function (en) {
                if (!en.isIntersecting) return;
                cio.unobserve(en.target);
                var el = en.target, end = parseInt(el.getAttribute('data-count'), 10) || 0, final = el.textContent, t0 = performance.now(), dur = 900;
                if (end < 2) return;
                (function step(t) {
                    var p = Math.min(1, (t - t0) / dur), v = Math.round(end * (1 - Math.pow(1 - p, 3)));
                    el.textContent = p < 1 ? v.toLocaleString() : final;
                    if (p < 1) requestAnimationFrame(step);
                })(t0);
            });
        });
        counters.forEach(function (c) { cio.observe(c); });
    }
    $$('.zl-bars').forEach(function (bars) { $$('i', bars).forEach(function (i, n) { i.style.setProperty('--i', n); }); });

    // thin bar at the top while the next page loads
    var bar = $('.zl-progress');
    var go = function () { if (!bar) return; bar.classList.add('is-on'); bar.style.width = '30%'; setTimeout(function () { bar.style.width = '75%'; }, 250); };
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[href]');
        if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || a.target || a.hasAttribute('download') || a.hasAttribute('data-open') || a.hasAttribute('data-lightbox')) return;
        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || a.origin !== location.origin || (a.pathname === location.pathname && a.hash)) return;
        go();
    });
    document.addEventListener('submit', function (e) { if (!e.defaultPrevented) go(); });
    window.addEventListener('pageshow', function () { if (bar) { bar.classList.remove('is-on'); bar.style.width = '0'; } });

    // back to top
    var top = $('[data-to-top]');
    if (top) {
        var onScroll = function () { top.hidden = window.scrollY < 900; };
        window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
        top.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: anim ? 'smooth' : 'auto' }); });
    }

    /* ---------- a step-by-step check result (database test, email test) ---------- */
    var CHECK_PATH = { ok: 'M5 12.5l4.5 4.5L19 7.5', warn: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2', fail: 'M6 6l12 12M18 6L6 18' };
    function showChecks(res, d) {
        res.hidden = false; res.className = 'zl-db-result ' + (d.ok ? 'is-ok' : 'is-bad');
        var h = '<b>' + esc(d.message || '') + '</b>';
        if (d.checks && d.checks.length) {
            h += '<ul class="zl-reqs">' + d.checks.map(function (c) {
                return '<li class="is-' + (c[1] === 'fail' ? 'bad' : c[1] === 'warn' ? 'warn' : 'ok') + '"><svg class="zl-i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + CHECK_PATH[c[1]] + '"/></svg>'
                    + '<span>' + esc(c[0]) + '</span><small>' + esc(c[2] || '') + '</small></li>';
            }).join('') + '</ul>';
        }
        res.innerHTML = h;
    }

    /* ---------- admin email settings: SMTP fields, presets, test connection, send a test ---------- */
    var mailBox = $('[data-mail-box]');
    if (mailBox) {
        var smtpFields = $('[data-smtp-fields]', mailBox), mailRes = $('[data-mail-result]', mailBox);
        var method = function () { var r = $('[name=mail_method]:checked', mailBox); return r ? r.value : 'php'; };
        mailBox.addEventListener('change', function (e) {
            if (e.target.name === 'mail_method') { smtpFields.hidden = method() !== 'smtp'; mailRes.hidden = true; }
            if (e.target.name === 'smtp_encryption') {
                var port = $('[name=smtp_port]', mailBox);
                if (e.target.value === 'ssl' && port.value === '587') port.value = '465';
                if (e.target.value === 'tls' && port.value === '465') port.value = '587';
            }
        });
        $$('[data-smtp-preset]', mailBox).forEach(function (b) {
            b.addEventListener('click', function () {
                var v = b.getAttribute('data-smtp-preset').split('|');
                $('[name=smtp_host]', mailBox).value = v[0]; $('[name=smtp_port]', mailBox).value = v[1]; $('[name=smtp_encryption]', mailBox).value = v[2];
                $('[name=smtp_user]', mailBox).focus();
            });
        });
        var runMail = function (btn, send) {
            var fd = new FormData();
            ['mail_from', 'mail_from_name', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'smtp_pass', 'test_to'].forEach(function (n) { var el = $('[name=' + n + ']', mailBox); fd.append(n, el ? el.value : ''); });
            fd.append('mail_method', method());
            if (send) fd.append('send', '1');
            var label = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="zl-spin"></span>' + esc(tx(send ? 'sending' : 'testing'));
            req(mailBox.getAttribute('data-test-url'), { body: fd })
                .then(function (d) { showChecks(mailRes, d); }, function (err) { showChecks(mailRes, err.data && err.data.message ? err.data : { ok: false, message: tx('db_unreachable'), checks: [] }); })
                .then(function () { btn.disabled = false; btn.innerHTML = label; });
        };
        $('[data-mail-check]', mailBox).addEventListener('click', function () { runMail(this, false); });
        $('[data-mail-send]', mailBox).addEventListener('click', function () { runMail(this, true); });
        $('[name=test_to]', mailBox).addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); runMail($('[data-mail-send]', mailBox), true); } });
    }

    /* ---------- installer: test the database, check the fields, never install twice ---------- */
    var inst = $('[data-install-form]');
    if (inst) {
        var res = $('[data-db-result]', inst), testBtn = $('[data-db-test]', inst), installBtn = $('[data-install-btn]', inst), tested = null;
        var DBF = ['db_host', 'db_port', 'db_name', 'db_user', 'db_pass', 'prefix'];
        var sig = function () { var d = $('[name=driver]:checked', inst); return [d ? d.value : ''].concat(DBF.map(function (n) { var el = $('[name=' + n + ']', inst); return el ? el.value : ''; })).join('\u0001'); };
        var show = function (d) { showChecks(res, d); };
        var runTest = function () {
            var s0 = sig(), label = testBtn.innerHTML;
            testBtn.disabled = true; testBtn.innerHTML = '<span class="zl-spin"></span>' + esc(tx('testing'));
            return fetch(inst.getAttribute('data-test-url'), { method: 'POST', body: new FormData(inst), credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) { show(d); tested = d.ok ? s0 : null; return !!d.ok; })
                .catch(function () { show({ ok: false, message: tx('db_unreachable'), checks: [] }); tested = null; return false; })
                .then(function (ok) { testBtn.disabled = false; testBtn.innerHTML = label; return ok; });
        };
        testBtn.addEventListener('click', runTest);
        inst.addEventListener('change', function (e) { if (e.target.name === 'driver') { tested = null; res.hidden = true; } });
        inst.addEventListener('input', function (e) { if (DBF.indexOf(e.target.name) >= 0 && tested !== sig()) { tested = null; res.classList.add('is-stale'); } });

        // field checks, the same rules as the server
        var rules = {
            username: function (v) { return /^[A-Za-z0-9_]{3,32}$/.test(v) ? '' : tx('username_rules'); },
            email: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? '' : tx('email_invalid'); },
            password: function (v) { return v.length >= 8 && /\p{L}/u.test(v) && /\d/.test(v) ? '' : tx('password_rules'); },
            password2: function (v) { return v === $('[name=password]', inst).value ? '' : tx('pw_mismatch'); }
        };
        var check = function (inp, quiet) {
            var msg = rules[inp.getAttribute('data-check')](inp.value);
            var box = inp.parentNode.querySelector('.zl-field-error');
            if (msg && !quiet) { if (!box) { box = document.createElement('small'); box.className = 'zl-field-error'; box.setAttribute('role', 'alert'); inp.insertAdjacentElement('afterend', box); } box.textContent = msg; }
            else if (!msg && box) box.remove();
            inp.classList.toggle('is-invalid', !!msg && !quiet);
            inp.classList.toggle('is-valid', !msg && inp.value !== '');
            return !msg;
        };
        $$('[data-check]', inst).forEach(function (inp) {
            inp.addEventListener('blur', function () { if (inp.value !== '') check(inp); });
            inp.addEventListener('input', function () { if (inp.classList.contains('is-invalid') || inp.classList.contains('is-valid')) check(inp); });
        });
        var meter = $('[data-meter]', inst), pw = $('[name=password]', inst);
        pw.addEventListener('input', function () {
            var v = pw.value, n = 0;
            if (v.length >= 8) n++;
            if (/\p{L}/u.test(v) && /\d/.test(v)) n++;
            if (/[a-z]/.test(v) && /[A-Z]/.test(v) || /[^A-Za-z0-9]/.test(v)) n++;
            if (v.length >= 12) n++;
            meter.setAttribute('data-score', v ? n : 0); meter.title = v ? tx('pw_' + Math.max(1, n)) : '';
            var p2 = $('[name=password2]', inst); if (p2.value) check(p2);
        });

        inst.addEventListener('submit', function (e) {
            if (inst.__go) return; // second pass: really submit
            e.preventDefault();
            var bad = $$('[data-check]', inst).filter(function (inp) { return !check(inp); });
            if (bad.length) { bad[0].focus(); return; }
            var go2 = function () {
                inst.__go = true;
                installBtn.disabled = true;
                $('[data-install-label]', installBtn).innerHTML = '<span class="zl-spin"></span> ' + esc(tx('installing'));
                inst.submit();
            };
            if (tested === sig()) { go2(); return; }
            runTest().then(function (ok) { if (ok) go2(); else res.scrollIntoView({ behavior: 'smooth', block: 'center' }); });
        });
    }
})();
