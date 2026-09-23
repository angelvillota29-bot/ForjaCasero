const NICHES = {
    restaurante: 'Restaurante',
    tienda: 'Tienda / E-commerce',
    servicios: 'Servicios',
    inmobiliaria: 'Inmobiliaria',
    salud: 'Salud / Clínica',
    generico: 'Genérico',
    otro: 'Otro',
};

const state = {
    user: null,
    view: 'resumen',
    bots: [],
    settings: null,
};

async function api(path, options = {}) {
    const res = await fetch(path, {
        method: options.method || 'GET',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: options.body ? JSON.stringify(options.body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(data.error || `Error ${res.status}`);
    }
    return data;
}

function toast(message, kind = 'success') {
    const el = document.createElement('div');
    el.className = `toast ${kind}`;
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3200);
}

/* ---------------- Login ---------------- */

function waitForGoogleIdentity(attemptsLeft = 50) {
    return new Promise((resolve, reject) => {
        (function poll(n) {
            if (window.google && window.google.accounts && window.google.accounts.id) {
                resolve();
            } else if (n <= 0) {
                reject(new Error('No se pudo cargar el script de Google'));
            } else {
                setTimeout(() => poll(n - 1), 100);
            }
        })(attemptsLeft);
    });
}

async function initLogin() {
    document.getElementById('loginScreen').classList.remove('hidden');
    document.getElementById('app').classList.add('hidden');

    try {
        const cfg = await api('api/config.php');
        if (!cfg.googleClientId) {
            document.getElementById('loginConfigNote').textContent =
                'Falta configurar GOOGLE_CLIENT_ID en las variables de entorno del servidor.';
            return;
        }
        await waitForGoogleIdentity();
        window.google.accounts.id.initialize({
            client_id: cfg.googleClientId,
            callback: onGoogleCredential,
        });
        window.google.accounts.id.renderButton(document.getElementById('googleBtnHost'), {
            theme: 'filled_black',
            size: 'large',
            shape: 'pill',
            text: 'signin_with',
        });
    } catch (err) {
        document.getElementById('loginConfigNote').textContent = 'No se pudo cargar la configuración de login.';
    }
}

async function onGoogleCredential(response) {
    const errBox = document.getElementById('loginError');
    errBox.classList.add('hidden');
    try {
        const result = await api('api/auth/google-login.php', {
            method: 'POST',
            body: { credential: response.credential },
        });
        state.user = result;
        boot();
    } catch (err) {
        errBox.textContent = err.message;
        errBox.classList.remove('hidden');
    }
}

async function logout() {
    await api('api/auth/logout.php', { method: 'POST' }).catch(() => {});
    state.user = null;
    window.location.reload();
}

/* ---------------- App shell ---------------- */

function renderShell() {
    document.getElementById('loginScreen').classList.add('hidden');
    const app = document.getElementById('app');
    app.classList.remove('hidden');
    app.innerHTML = `
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-mark">FC</div>
        <div>
          <div class="brand-name">Forja Casero</div>
          <div class="brand-sub">Panel · Bots</div>
        </div>
      </div>
      <div class="nav-group-label">Panel</div>
      <button class="nav-item" data-view="resumen">Resumen</button>
      <button class="nav-item" data-view="bots">Bots</button>
      <div class="nav-group-label">Configuración</div>
      <button class="nav-item" data-view="conexiones">Conexiones</button>
      <button class="nav-item" data-view="accesos">Accesos</button>
      <div class="sidebar-footer">
        <div class="user-chip">
          ${state.user.picture ? `<img class="user-avatar" src="${escapeAttr(state.user.picture)}">` : '<div class="user-avatar"></div>'}
          <div class="user-email">${escapeHtml(state.user.email)}</div>
        </div>
        <button class="logout-btn" id="logoutBtn">Cerrar sesión</button>
      </div>
    </aside>
    <main class="main" id="mainContent"></main>
  `;
    document.getElementById('logoutBtn').addEventListener('click', logout);
    app.querySelectorAll('.nav-item').forEach((btn) => {
        btn.addEventListener('click', () => switchView(btn.dataset.view));
    });
    switchView(state.view);
}

function setActiveNav(view) {
    document.querySelectorAll('.nav-item').forEach((btn) => {
        btn.classList.toggle('active', btn.dataset.view === view);
    });
}

async function switchView(view) {
    state.view = view;
    setActiveNav(view);
    const main = document.getElementById('mainContent');
    main.innerHTML = '<div class="empty-state">Cargando…</div>';
    try {
        if (view === 'resumen') await renderResumen(main);
        else if (view === 'bots') await renderBots(main);
        else if (view === 'conexiones') await renderConexiones(main);
        else if (view === 'accesos') await renderAccesos(main);
    } catch (err) {
        main.innerHTML = `<div class="empty-state">${escapeHtml(err.message)}</div>`;
    }
}

/* ---------------- Resumen ---------------- */

async function renderResumen(main) {
    const [botsRes, settingsRes] = await Promise.all([
        api('api/bots/list.php'),
        api('api/settings/get.php'),
    ]);
    state.bots = botsRes.bots;
    state.settings = settingsRes.settings;

    const total = state.bots.length;
    const activos = state.bots.filter((b) => b.status === 'activo').length;
    const propios = state.bots.filter((b) => b.keyMode === 'own').length;

    main.innerHTML = `
    <div class="topbar">
      <div>
        <div class="breadcrumb">Panel / Resumen</div>
        <h1 class="page-title">Resumen</h1>
      </div>
    </div>
    <div class="intro-banner">
      Aquí puedes crear <b>cantidad ilimitada de bots</b>, cada uno con su propio nombre,
      tipo de negocio y descripción de qué hace y cómo. Cada bot decide si usa
      <b>la llave API compartida</b> de este panel o <b>su propia llave</b> — a tu gusto.
    </div>
    <div class="stat-row">
      <div class="stat-card">
        <div class="stat-label">Bots creados</div>
        <div class="stat-value">${total}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Activos</div>
        <div class="stat-value">${activos}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Con llave propia</div>
        <div class="stat-value">${propios}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Llave compartida</div>
        <div class="stat-value">${state.settings.sharedApiKeySet ? 'Configurada' : 'Sin configurar'}</div>
      </div>
    </div>
    ${total === 0 ? `
      <div class="empty-state">
        Todavía no has creado ningún bot.
        <div style="margin-top:14px;"><button class="btn" id="goToBots">+ Agregar tu primer bot</button></div>
      </div>` : `
      <h3 style="font-size:13px;color:var(--text-muted);font-weight:600;margin-bottom:10px;">Últimos bots</h3>
      <div class="bots-grid">${state.bots.slice(0, 6).map(botCardHtml).join('')}</div>
    `}
  `;
    document.getElementById('goToBots')?.addEventListener('click', () => switchView('bots'));
    main.querySelectorAll('.bot-card').forEach((el) => {
        el.addEventListener('click', () => switchView('bots'));
    });
}

/* ---------------- Bots ---------------- */

function botCardHtml(bot) {
    const statusBadge = bot.status === 'activo'
        ? '<span class="badge badge-green">● activo</span>'
        : '<span class="badge badge-muted">○ pausado</span>';
    const keyBadge = bot.keyMode === 'own'
        ? '<span class="badge badge-orange">llave propia</span>'
        : '<span class="badge">llave compartida</span>';
    return `
    <div class="bot-card" data-id="${escapeAttr(bot.id)}">
      <div class="bot-card-top">
        <div class="bot-name">${escapeHtml(bot.name)}</div>
        ${statusBadge}
      </div>
      <div class="bot-niche">${escapeHtml(NICHES[bot.niche] || bot.niche)}</div>
      <div class="bot-desc">${escapeHtml(bot.description || 'Sin descripción todavía.')}</div>
      <div class="bot-card-foot">${keyBadge}</div>
    </div>
  `;
}

async function renderBots(main) {
    const res = await api('api/bots/list.php');
    state.bots = res.bots;

    main.innerHTML = `
    <div class="topbar">
      <div>
        <div class="breadcrumb">Panel / Bots</div>
        <h1 class="page-title">Tus bots</h1>
      </div>
      <button class="btn" id="addBotBtn">+ Agregar bot</button>
    </div>
    ${state.bots.length === 0
        ? '<div class="empty-state">Aún no tienes bots. Crea el primero — puede ser de cualquier tipo de negocio.</div>'
        : `<div class="bots-grid">${state.bots.map(botCardHtml).join('')}</div>`
    }
  `;
    document.getElementById('addBotBtn').addEventListener('click', () => openBotModal(null));
    main.querySelectorAll('.bot-card').forEach((el) => {
        el.addEventListener('click', () => {
            const bot = state.bots.find((b) => b.id === el.dataset.id);
            openBotModal(bot);
        });
    });
}

function openBotModal(bot) {
    const isEdit = !!bot;
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    backdrop.innerHTML = `
    <div class="modal">
      <div class="modal-header">
        <h3>${isEdit ? 'Editar bot' : 'Agregar bot'}</h3>
        <button class="modal-close" id="closeModal">✕</button>
      </div>
      <div class="field">
        <label>Nombre del bot</label>
        <input type="text" id="fName" value="${escapeAttr(bot?.name || '')}" placeholder="Ej. Bot Restaurante La Delicia">
      </div>
      <div class="field">
        <label>Tipo de negocio</label>
        <select id="fNiche">
          ${Object.entries(NICHES).map(([k, v]) => `<option value="${k}" ${bot?.niche === k ? 'selected' : ''}>${v}</option>`).join('')}
        </select>
      </div>
      <div class="field">
        <label>Qué hace y cómo (descripción)</label>
        <textarea id="fDesc" placeholder="Ej. Atiende WhatsApp y Telegram, muestra el menú, cotiza y confirma pedidos automáticamente.">${escapeHtml(bot?.description || '')}</textarea>
      </div>
      <div class="field">
        <label>URL del bot (opcional)</label>
        <input type="url" id="fUrl" value="${escapeAttr(bot?.url || '')}" placeholder="https://tu-bot.workers.dev">
      </div>
      <div class="field">
        <label>Estado</label>
        <div class="radio-row">
          <label class="radio-option"><input type="radio" name="fStatus" value="activo" ${(!bot || bot.status === 'activo') ? 'checked' : ''}> Activo</label>
          <label class="radio-option"><input type="radio" name="fStatus" value="pausado" ${bot?.status === 'pausado' ? 'checked' : ''}> Pausado</label>
        </div>
      </div>
      <div class="field">
        <label>Llave API</label>
        <div class="radio-row">
          <label class="radio-option"><input type="radio" name="fKeyMode" value="shared" ${(!bot || bot.keyMode === 'shared') ? 'checked' : ''}> Usar la llave compartida</label>
          <label class="radio-option"><input type="radio" name="fKeyMode" value="own" ${bot?.keyMode === 'own' ? 'checked' : ''}> Llave propia</label>
        </div>
      </div>
      <div class="field" id="ownKeyField" style="${bot?.keyMode === 'own' ? '' : 'display:none;'}">
        <label>Llave propia de este bot</label>
        <input type="password" id="fOwnKey" placeholder="${bot?.ownApiKeyHint ? 'Guardada: ' + bot.ownApiKeyHint + ' — deja vacío para no cambiarla' : 'Pega la API key de este bot'}">
        <div class="field-hint">Se guarda en el servidor, nunca se vuelve a mostrar completa.</div>
      </div>
      <div class="modal-actions">
        ${isEdit ? '<button class="btn btn-danger" id="deleteBotBtn">Eliminar</button>' : '<span></span>'}
        <div class="right">
          <button class="btn btn-secondary" id="cancelModal">Cancelar</button>
          <button class="btn" id="saveBotBtn">Guardar</button>
        </div>
      </div>
    </div>
  `;
    document.body.appendChild(backdrop);

    const close = () => backdrop.remove();
    backdrop.querySelector('#closeModal').addEventListener('click', close);
    backdrop.querySelector('#cancelModal').addEventListener('click', close);
    backdrop.addEventListener('click', (e) => { if (e.target === backdrop) close(); });

    backdrop.querySelectorAll('input[name="fKeyMode"]').forEach((r) => {
        r.addEventListener('change', () => {
            const own = backdrop.querySelector('input[name="fKeyMode"]:checked').value === 'own';
            backdrop.querySelector('#ownKeyField').style.display = own ? '' : 'none';
        });
    });

    backdrop.querySelector('#saveBotBtn').addEventListener('click', async () => {
        const payload = {
            id: bot?.id,
            name: backdrop.querySelector('#fName').value.trim(),
            niche: backdrop.querySelector('#fNiche').value,
            description: backdrop.querySelector('#fDesc').value.trim(),
            url: backdrop.querySelector('#fUrl').value.trim(),
            status: backdrop.querySelector('input[name="fStatus"]:checked').value,
            keyMode: backdrop.querySelector('input[name="fKeyMode"]:checked').value,
        };
        const ownKeyInput = backdrop.querySelector('#fOwnKey').value;
        if (payload.keyMode === 'own' && ownKeyInput !== '') {
            payload.ownApiKey = ownKeyInput;
        }
        if (!payload.name) {
            toast('Ponle un nombre al bot', 'error');
            return;
        }
        try {
            await api('api/bots/save.php', { method: 'POST', body: payload });
            toast(isEdit ? 'Bot actualizado' : 'Bot creado');
            close();
            switchView('bots');
        } catch (err) {
            toast(err.message, 'error');
        }
    });

    if (isEdit) {
        backdrop.querySelector('#deleteBotBtn').addEventListener('click', async () => {
            if (!confirm(`¿Eliminar "${bot.name}"? Esta acción no se puede deshacer.`)) return;
            try {
                await api('api/bots/delete.php', { method: 'POST', body: { id: bot.id } });
                toast('Bot eliminado');
                close();
                switchView('bots');
            } catch (err) {
                toast(err.message, 'error');
            }
        });
    }
}

/* ---------------- Conexiones ---------------- */

async function renderConexiones(main) {
    const res = await api('api/settings/get.php');
    state.settings = res.settings;

    main.innerHTML = `
    <div class="topbar">
      <div>
        <div class="breadcrumb">Configuración / Conexiones</div>
        <h1 class="page-title">Conexiones</h1>
      </div>
    </div>
    <div class="card">
      <h3>Llave API compartida</h3>
      <p class="card-desc">
        La usan por defecto todos los bots que elijas "llave compartida" en vez de una propia.
        ${state.settings.sharedApiKeyHint ? `Actual: <b style="color:var(--text)">${state.settings.sharedApiKeyHint}</b>` : 'Todavía no está configurada.'}
      </p>
      <div class="field">
        <label>Nueva llave (deja vacío para no cambiarla)</label>
        <input type="password" id="sharedKeyInput" placeholder="sk-...">
      </div>
      <button class="btn" id="saveSharedKeyBtn">Guardar</button>
    </div>
  `;
    document.getElementById('saveSharedKeyBtn').addEventListener('click', async () => {
        const value = document.getElementById('sharedKeyInput').value;
        if (value === '') { toast('No escribiste ninguna llave nueva', 'error'); return; }
        try {
            await api('api/settings/update.php', { method: 'POST', body: { sharedApiKey: value } });
            toast('Llave compartida actualizada');
            renderConexiones(main);
        } catch (err) {
            toast(err.message, 'error');
        }
    });
}

/* ---------------- Accesos ---------------- */

async function renderAccesos(main) {
    const res = await api('api/settings/get.php');
    state.settings = res.settings;

    main.innerHTML = `
    <div class="topbar">
      <div>
        <div class="breadcrumb">Configuración / Accesos</div>
        <h1 class="page-title">Accesos</h1>
      </div>
    </div>
    <div class="card">
      <h3>Correos de Google con acceso a este panel</h3>
      <p class="card-desc">Solo estas cuentas de Google pueden iniciar sesión. No se usan contraseñas.</p>
      <div class="email-list" id="emailList">
        ${state.settings.allowedEmails.map((e) => `
          <div class="email-row">
            <span>${escapeHtml(e)}</span>
            <button data-email="${escapeAttr(e)}" class="removeEmailBtn">Quitar</button>
          </div>
        `).join('') || '<div class="field-hint">Sin correos autorizados todavía.</div>'}
      </div>
      <div class="add-email-row">
        <input type="email" id="newEmailInput" placeholder="correo@gmail.com">
        <button class="btn" id="addEmailBtn">Agregar</button>
      </div>
    </div>
  `;
    main.querySelectorAll('.removeEmailBtn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            try {
                await api('api/settings/update.php', { method: 'POST', body: { removeEmail: btn.dataset.email } });
                toast('Acceso removido');
                renderAccesos(main);
            } catch (err) {
                toast(err.message, 'error');
            }
        });
    });
    document.getElementById('addEmailBtn').addEventListener('click', async () => {
        const value = document.getElementById('newEmailInput').value.trim();
        if (!value) return;
        try {
            await api('api/settings/update.php', { method: 'POST', body: { addEmail: value } });
            toast('Acceso agregado');
            renderAccesos(main);
        } catch (err) {
            toast(err.message, 'error');
        }
    });
}

/* ---------------- Utils & boot ---------------- */

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function escapeAttr(str) { return escapeHtml(str); }

async function boot() {
    try {
        const me = await api('api/auth/me.php');
        if (me.authenticated) {
            state.user = me;
            renderShell();
        } else {
            initLogin();
        }
    } catch (err) {
        initLogin();
    }
}

document.addEventListener('DOMContentLoaded', boot);
