(function () {
    'use strict';

    const root = document.querySelector('[data-calsys-chat]');
    if (!root || typeof calsysChat === 'undefined') {
        return;
    }

    const channels = root.querySelector('[data-chat-channels]');
    const heading = root.querySelector('[data-chat-heading]');
    const status = root.querySelector('[data-chat-status]');
    const messages = root.querySelector('[data-chat-messages]');
    const older = root.querySelector('[data-chat-older]');
    const compose = root.querySelector('[data-chat-send]');
    const fieldset = compose.querySelector('fieldset');
    const panel = document.getElementById('calsys-chat-panel');
    const toggles = document.querySelectorAll('[data-chat-toggle]');
    const reads = new Set();
    let opener = null;
    let active = false;
    let initializing = false;
    let timer = null;
    let channel = 0;
    let first = 0;
    let history = false;
    let loading = false;
    let sending = false;
    let expired = false;
    let revision = 0;
    let snapshot = '';

    async function request(operation, fields) {
        const data = fields instanceof FormData ? fields : new FormData();
        if (!(fields instanceof FormData)) {
            Object.entries(fields || {}).forEach(([key, value]) => data.set(key, value));
        }
        data.set('action', 'calsys_communication');
        data.set('operation', operation);
        data.set('nonce', calsysChat.nonce);
        const controller = new AbortController();
        const read = operation === 'channels' || operation === 'list';
        if (read) {
            reads.add(controller);
        }
        let response;
        let result;
        try {
            response = await fetch(calsysChat.url, {
                method: 'POST',
                credentials: 'same-origin',
                body: data,
                signal: read ? controller.signal : undefined,
            });
            result = await response.json();
        } finally {
            reads.delete(controller);
        }
        if (response.status === 403 || response.status === 401) {
            expired = true;
            fieldset.disabled = true;
            messages.replaceChildren();
            window.clearInterval(timer);
            throw new Error(calsysChat.expired);
        }
        if (!response.ok || !result.success) {
            throw new Error(result.data && result.data.message ? result.data.message : calsysChat.failed);
        }
        return result.data;
    }

    function showError(error) {
        status.textContent = error.message || calsysChat.failed;
        status.setAttribute('role', 'alert');
    }

    function cancelReads() {
        reads.forEach(controller => controller.abort());
        revision += 1;
        loading = false;
        initializing = false;
    }

    function messageNode(item) {
        const article = document.createElement('article');
        article.className = 'calsys-chat-message';
        article.dataset.message = item.id;
        const header = document.createElement('header');
        const author = document.createElement('strong');
        author.textContent = item.author;
        const time = document.createElement('span');
        time.textContent = item.time;
        header.append(author, time);
        const body = document.createElement('p');
        body.textContent = item.body;
        article.append(header, body);
        if (item.download) {
            const file = document.createElement('a');
            file.href = item.download;
            file.textContent = item.file;
            article.append(file);
        }
        if (item.can_delete) {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.delete = item.id;
            button.textContent = calsysChat.delete;
            article.append(button);
        }
        return article;
    }

    async function load(before = 0) {
        if (!active || !channel || expired || loading || document.hidden) {
            return;
        }
        loading = true;
        const current = revision;
        const atBottom = messages.scrollHeight - messages.scrollTop - messages.clientHeight < 80;
        const oldHeight = messages.scrollHeight;
        try {
            const data = await request('list', { channel_id: channel, before });
            if (current !== revision) {
                return;
            }
            const next = JSON.stringify(data.messages);
            if (before) {
                const fragment = document.createDocumentFragment();
                data.messages.forEach(item => fragment.append(messageNode(item)));
                messages.prepend(fragment);
                messages.scrollTop += messages.scrollHeight - oldHeight;
                history = true;
            } else if (next !== snapshot) {
                const scroll = messages.scrollTop;
                messages.replaceChildren(...data.messages.map(messageNode));
                messages.scrollTop = atBottom ? messages.scrollHeight : scroll;
                snapshot = next;
            }
            if (data.messages.length) {
                first = data.messages[0].id;
            }
            older.hidden = !data.more;
            status.setAttribute('role', 'status');
            status.textContent = history ? calsysChat.history : (data.messages.length ? '' : calsysChat.empty);
        } catch (error) {
            if (current === revision && active && error.name !== 'AbortError') {
                showError(error);
            }
        } finally {
            if (current === revision) {
                loading = false;
            }
        }
    }

    function choose(button) {
        if (!active || sending || expired) {
            return;
        }
        channel = Number(button.dataset.channel);
        cancelReads();
        history = false;
        first = 0;
        snapshot = '';
        messages.replaceChildren();
        older.hidden = true;
        channels.querySelectorAll('button').forEach(item => {
            item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
        });
        heading.textContent = button.textContent;
        fieldset.disabled = false;
        status.textContent = calsysChat.loading;
        load();
    }

    channels.addEventListener('click', event => {
        const button = event.target.closest('[data-channel]');
        if (button) {
            choose(button);
        }
    });
    older.addEventListener('click', () => load(first));
    root.querySelector('[data-chat-retry]').addEventListener('click', () => {
        if (!loading && !initializing) {
            history = false;
            snapshot = '';
            messages.scrollTop = messages.scrollHeight;
            loadChannels();
        }
    });

    compose.addEventListener('submit', async event => {
        event.preventDefault();
        if (!active || !channel || sending || expired) {
            return;
        }
        const data = new FormData(compose);
        data.set('channel_id', channel);
        sending = true;
        fieldset.disabled = true;
        try {
            await request('send', data);
            compose.reset();
            cancelReads();
            history = false;
            snapshot = '';
            messages.scrollTop = messages.scrollHeight;
            status.textContent = calsysChat.sent;
            await load();
        } catch (error) {
            showError(error);
        } finally {
            sending = false;
            fieldset.disabled = expired;
        }
    });

    messages.addEventListener('click', async event => {
        const button = event.target.closest('[data-delete]');
        if (!active || !button || expired || !window.confirm(calsysChat.confirm)) {
            return;
        }
        button.disabled = true;
        try {
            await request('delete', { message_id: button.dataset.delete });
            button.closest('article').remove();
            cancelReads();
            snapshot = '';
        } catch (error) {
            button.disabled = false;
            showError(error);
        }
    });

    const create = root.querySelector('[data-chat-create]');
    if (create) {
        create.addEventListener('submit', async event => {
            event.preventDefault();
            if (!active || expired) {
                return;
            }
            const button = create.querySelector('button');
            button.disabled = true;
            try {
                const data = await request('create', new FormData(create));
                const tab = document.createElement('button');
                tab.type = 'button';
                tab.dataset.channel = data.id;
                tab.textContent = '# ' + data.name;
                channels.append(tab);
                create.reset();
                choose(tab);
            } catch (error) {
                showError(error);
            } finally {
                button.disabled = expired;
            }
        });
    }

    async function loadChannels() {
        if (!active || expired || initializing) {
            return;
        }
        initializing = true;
        const current = revision;
        status.textContent = calsysChat.loading;
        try {
            const data = await request('channels');
            if (!active || current !== revision) {
                return;
            }
            const buttons = data.map(item => {
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.channel = item.id;
                button.textContent = '# ' + item.name;
                button.setAttribute('aria-pressed', Number(item.id) === channel ? 'true' : 'false');
                return button;
            });
            channels.replaceChildren(...buttons);
            initializing = false;
            const selected = buttons.find(button => Number(button.dataset.channel) === channel);
            if (selected) {
                if (!history) {
                    await load();
                } else {
                    status.textContent = calsysChat.history;
                }
            } else if (buttons.length) {
                choose(buttons[0]);
            } else {
                status.textContent = calsysChat.noChannels;
            }
        } catch (error) {
            if (active && current === revision && error.name !== 'AbortError') {
                showError(error);
            }
        } finally {
            if (current === revision) {
                initializing = false;
            }
        }
    }

    function close() {
        active = false;
        panel.hidden = true;
        toggles.forEach(button => button.setAttribute('aria-expanded', 'false'));
        window.clearInterval(timer);
        cancelReads();
        if (opener) {
            opener.focus();
        }
    }

    toggles.forEach(button => {
        button.addEventListener('click', () => {
            if (active) {
                close();
                return;
            }
            opener = button;
            active = true;
            panel.hidden = false;
            toggles.forEach(toggle => toggle.setAttribute('aria-expanded', 'true'));
            panel.querySelector('[data-chat-close]').focus();
            loadChannels();
            if (!expired) {
                timer = window.setInterval(() => {
                    if (!document.hidden && !history && !sending && !initializing) {
                        load();
                    }
                }, 10000);
            }
        });
    });
    panel.querySelector('[data-chat-close]').addEventListener('click', close);
    document.addEventListener('keydown', event => {
        if (active && event.key === 'Escape') {
            event.preventDefault();
            close();
        }
    });
}());
