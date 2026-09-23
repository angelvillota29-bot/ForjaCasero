const NICHES = {
    restaurante: 'Restaurante',
    tienda: 'Tienda / E-commerce',
    servicios: 'Servicios',
    inmobiliaria: 'Inmobiliaria',
    salud: 'Salud / Clínica',
    generico: 'Genérico',
    otro: 'Otro',
};

const BOT_TABS = [
    { key: 'resumen', label: 'Resumen' },
    { key: 'conversations', label: 'Conversaciones' },
    { key: 'vault', label: 'Bóveda' },
    { key: 'leads', label: 'Leads' },
    { key: 'payments', label: 'Cobros' },
    { key: 'tickets', label: 'Tickets' },
    { key: 'reviews', label: 'Reseñas' },
    { key: 'campaigns', label: 'Campañas' },
    { key: 'templates', label: 'Plantillas' },
    { key: 'flujo', label: 'Flujo' },
    { key: 'knowledge', label: 'Conocimiento' },
    { key: 'improvements', label: 'Mejoras' },
];

const state = {
    user: null,
    view: 'resumen',
    bots: [],
    settings: null,
    schemas: null,
    currentBotId: null,
    botTab: 'resumen',
};

async function getSchemas() {
    if (!state.schemas) {
        const res = await api('api/collections/schema.php');
        state.schemas = res.schemas;
    }
    return state.schemas;
}

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
        el.addEventListener('click', () => openBotWorkspace(el.dataset.id));
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
        el.addEventListener('click', () => openBotWorkspace(el.dataset.id));
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
      <div class="field">
        <label>Modelo de IA (OpenAI)</label>
        <input type="text" id="fAiModel" value="${escapeAttr(bot?.aiModel || 'gpt-4o-mini')}" placeholder="gpt-4o-mini">
      </div>
      <div class="field">
        <label>Info del negocio (horarios, precios, ubicación, políticas…)</label>
        <textarea id="fBusinessInfo" placeholder="Ej. Horario: L-D 6pm-12am. Ubicación: Cra 26 #10-93. Métodos de pago: efectivo, Nequi.">${escapeHtml(bot?.businessInfo || '')}</textarea>
        <div class="field-hint">Esta es la única fuente de verdad que la IA usa para no inventar datos — entre más completa, mejor contesta.</div>
      </div>
      <div class="field">
        <label>Instrucciones para la IA (opcional)</label>
        <textarea id="fAiInstructions" placeholder="Ej. Sé breve, ofrece agendar una llamada si preguntan por precios.">${escapeHtml(bot?.aiInstructions || '')}</textarea>
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
            aiModel: backdrop.querySelector('#fAiModel').value.trim(),
            businessInfo: backdrop.querySelector('#fBusinessInfo').value.trim(),
            aiInstructions: backdrop.querySelector('#fAiInstructions').value.trim(),
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

/* ---------------- Bot workspace (panel completo por bot) ---------------- */

async function openBotWorkspace(botId) {
    state.currentBotId = botId;
    state.botTab = 'resumen';
    state.view = 'bots';
    setActiveNav('bots');
    const main = document.getElementById('mainContent');
    main.innerHTML = '<div class="empty-state">Cargando…</div>';
    try {
        const [botsRes] = await Promise.all([api('api/bots/list.php'), getSchemas()]);
        state.bots = botsRes.bots;
        renderBotWorkspaceShell(main);
    } catch (err) {
        main.innerHTML = `<div class="empty-state">${escapeHtml(err.message)}</div>`;
    }
}

function renderBotWorkspaceShell(main) {
    const bot = state.bots.find((b) => b.id === state.currentBotId);
    if (!bot) {
        main.innerHTML = '<div class="empty-state">Ese bot ya no existe.</div>';
        return;
    }
    const statusBadge = bot.status === 'activo'
        ? '<span class="badge badge-green">● activo</span>'
        : '<span class="badge badge-muted">○ pausado</span>';

    main.innerHTML = `
    <div class="topbar">
      <div>
        <div class="breadcrumb"><a href="#" id="backToBots" style="color:inherit;">Bots</a> / ${escapeHtml(bot.name)}</div>
        <h1 class="page-title">${escapeHtml(bot.name)} ${statusBadge}</h1>
      </div>
      <button class="btn btn-secondary" id="editBotBtn">Editar bot</button>
    </div>
    <div class="bot-tabs" id="botTabs"></div>
    <div id="botTabContent"></div>
  `;
    document.getElementById('backToBots').addEventListener('click', (e) => { e.preventDefault(); switchView('bots'); });
    document.getElementById('editBotBtn').addEventListener('click', () => openBotModal(bot));

    const tabsHost = document.getElementById('botTabs');
    tabsHost.innerHTML = BOT_TABS.map((t) => `<button class="nav-item bot-tab-btn" data-tab="${t.key}" style="display:inline-flex;width:auto;margin-right:6px;">${t.label}</button>`).join('');
    tabsHost.querySelectorAll('.bot-tab-btn').forEach((btn) => {
        btn.addEventListener('click', () => switchBotTab(btn.dataset.tab));
    });
    switchBotTab(state.botTab);
}

async function switchBotTab(tab) {
    state.botTab = tab;
    document.querySelectorAll('.bot-tab-btn').forEach((btn) => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    const host = document.getElementById('botTabContent');
    host.innerHTML = '<div class="empty-state">Cargando…</div>';
    try {
        if (tab === 'resumen') await renderBotResumenTab(host);
        else if (tab === 'flujo') renderBotFlujoTab(host);
        else await renderCollectionTab(host, tab);
    } catch (err) {
        host.innerHTML = `<div class="empty-state">${escapeHtml(err.message)}</div>`;
    }
}

async function renderBotResumenTab(host) {
    const bot = state.bots.find((b) => b.id === state.currentBotId);
    const collectionsToCount = ['leads', 'tickets', 'payments', 'conversations'];
    const counts = {};
    await Promise.all(collectionsToCount.map(async (key) => {
        const res = await api(`api/collections/list.php?collection=${key}&botId=${encodeURIComponent(bot.id)}`);
        counts[key] = res.items.length;
    }));

    host.innerHTML = `
    <div class="intro-banner">
      <b>${escapeHtml(bot.name)}</b> — ${escapeHtml(NICHES[bot.niche] || bot.niche)}.
      ${bot.description ? escapeHtml(bot.description) : 'Sin descripción todavía.'}
      ${bot.url ? `<br>URL: <a href="${escapeAttr(bot.url)}" target="_blank" rel="noopener">${escapeHtml(bot.url)}</a>` : ''}
    </div>
    <div class="stat-row">
      <div class="stat-card"><div class="stat-label">Leads</div><div class="stat-value">${counts.leads}</div></div>
      <div class="stat-card"><div class="stat-label">Tickets</div><div class="stat-value">${counts.tickets}</div></div>
      <div class="stat-card"><div class="stat-label">Cobros</div><div class="stat-value">${counts.payments}</div></div>
      <div class="stat-card"><div class="stat-label">Conversaciones</div><div class="stat-value">${counts.conversations}</div></div>
    </div>
    <div class="card">
      <h3>Llave API</h3>
      <p class="card-desc">
        ${bot.keyMode === 'own'
            ? `Este bot usa su propia llave${bot.ownApiKeyHint ? ` (${bot.ownApiKeyHint})` : ''}.`
            : 'Este bot usa la llave API compartida del panel.'}
      </p>
    </div>
    <div class="card" id="telegramCard"></div>
  `;
    renderTelegramCard(host.querySelector('#telegramCard'), bot);
}

function renderTelegramCard(container, bot) {
    if (bot.telegramConnected) {
        container.innerHTML = `
      <h3>Telegram</h3>
      <p class="card-desc">
        Conectado como <b style="color:var(--text)">@${escapeHtml(bot.telegramUsername || '')}</b>.
        Este bot ya contesta solo por Telegram usando su llave de IA.
      </p>
      <button class="btn btn-danger" id="disconnectTgBtn">Desconectar Telegram</button>
    `;
        container.querySelector('#disconnectTgBtn').addEventListener('click', async () => {
            if (!confirm('¿Desconectar Telegram de este bot?')) return;
            try {
                await api('api/bots/disconnect-telegram.php', { method: 'POST', body: { botId: bot.id } });
                toast('Telegram desconectado');
                switchBotTab('resumen');
            } catch (err) {
                toast(err.message, 'error');
            }
        });
        return;
    }

    container.innerHTML = `
    <h3>Telegram</h3>
    <p class="card-desc">Conecta un bot de Telegram (creado con @BotFather) para que este bot conteste solo, usando su llave de IA.</p>
    <div class="field">
      <label>Token de Telegram</label>
      <input type="password" id="tgTokenInput" placeholder="Pégalo aquí">
    </div>
    <button class="btn" id="connectTgBtn">Conectar</button>
  `;
    container.querySelector('#connectTgBtn').addEventListener('click', async () => {
        const token = container.querySelector('#tgTokenInput').value.trim();
        if (!token) { toast('Pega el token de Telegram', 'error'); return; }
        try {
            const res = await api('api/bots/connect-telegram.php', { method: 'POST', body: { botId: bot.id, telegramToken: token } });
            toast(`Conectado como @${res.username}`);
            switchBotTab('resumen');
        } catch (err) {
            toast(err.message, 'error');
        }
    });
}

function renderBotFlujoTab(host) {
    const bot = state.bots.find((b) => b.id === state.currentBotId);
    const keySource = bot.keyMode === 'own' ? 'Llave propia de este bot' : 'Llave API compartida del panel';
    host.innerHTML = `
    <div class="intro-banner">
      Radiografía simple de cómo procesa mensajes este bot. No es en vivo (eso vive en la
      Cloudflare del propio bot) — es la referencia de su configuración en Forja Casero.
    </div>
    <div class="stat-row">
      <div class="stat-card"><div class="stat-label">Canales</div><div class="stat-value" style="font-size:15px;">WhatsApp · Telegram · Web</div></div>
      <div class="stat-card"><div class="stat-label">Tipo de negocio</div><div class="stat-value" style="font-size:15px;">${escapeHtml(NICHES[bot.niche] || bot.niche)}</div></div>
      <div class="stat-card"><div class="stat-label">Autenticación IA</div><div class="stat-value" style="font-size:15px;">${escapeHtml(keySource)}</div></div>
      <div class="stat-card"><div class="stat-label">Estado</div><div class="stat-value" style="font-size:15px;">${bot.status === 'activo' ? 'Activo' : 'Pausado'}</div></div>
    </div>
    <div class="card">
      <h3>Pipeline</h3>
      <p class="card-desc">Cliente (WhatsApp / Telegram / Web) → Bot (Cloudflare Worker) → ${escapeHtml(keySource)} → Respuesta al cliente. Los módulos de Leads, Tickets, Cobros, etc. de este panel son registros propios de Forja Casero, independientes del código del bot.</p>
    </div>
  `;
}

/* ---- CRUD genérico para cada módulo (Leads, Bóveda, Tickets, Reseñas...) ---- */

function fieldInputHtml(key, def, value) {
    const id = `rf_${key}`;
    if (def.type === 'select') {
        const options = Object.entries(def.options || {}).map(([val, label]) =>
            `<option value="${escapeAttr(val)}" ${value === val ? 'selected' : ''}>${escapeHtml(label)}</option>`
        ).join('');
        return `<select id="${id}">${options}</select>`;
    }
    if (def.type === 'textarea') {
        return `<textarea id="${id}">${escapeHtml(value || '')}</textarea>`;
    }
    if (def.secret) {
        return `<input type="password" id="${id}" placeholder="${value ? 'Guardado — deja vacío para no cambiarlo' : 'Escribe el valor'}">`;
    }
    const type = def.type === 'date' ? 'date' : (def.type === 'number' ? 'number' : 'text');
    return `<input type="${type}" id="${id}" value="${escapeAttr(value ?? '')}">`;
}

function openRecordModal(collectionKey, item) {
    const schema = state.schemas[collectionKey];
    const fields = schema.fields;
    const isEdit = !!item;

    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    backdrop.innerHTML = `
    <div class="modal">
      <div class="modal-header">
        <h3>${isEdit ? 'Editar' : 'Agregar'} — ${escapeHtml(schema.label)}</h3>
        <button class="modal-close" id="closeModal">✕</button>
      </div>
      ${Object.entries(fields).map(([key, def]) => {
          const rawValue = def.secret
              ? (item && item[key + 'Set'] ? item[key + 'Hint'] : '')
              : (item ? item[key] : (def.default ?? ''));
          return `
        <div class="field">
          <label>${escapeHtml(def.label)}${def.required ? ' *' : ''}</label>
          ${fieldInputHtml(key, def, rawValue)}
          ${def.secret ? '<div class="field-hint">Se guarda en el servidor, nunca se vuelve a mostrar completo.</div>' : ''}
        </div>`;
      }).join('')}
      <div class="modal-actions">
        ${isEdit ? '<button class="btn btn-danger" id="deleteRecordBtn">Eliminar</button>' : '<span></span>'}
        <div class="right">
          <button class="btn btn-secondary" id="cancelModal">Cancelar</button>
          <button class="btn" id="saveRecordBtn">Guardar</button>
        </div>
      </div>
    </div>
  `;
    document.body.appendChild(backdrop);
    const close = () => backdrop.remove();
    backdrop.querySelector('#closeModal').addEventListener('click', close);
    backdrop.querySelector('#cancelModal').addEventListener('click', close);
    backdrop.addEventListener('click', (e) => { if (e.target === backdrop) close(); });

    backdrop.querySelector('#saveRecordBtn').addEventListener('click', async () => {
        const payload = { collection: collectionKey, botId: state.currentBotId, id: item?.id };
        for (const key of Object.keys(fields)) {
            const el = backdrop.querySelector(`#rf_${key}`);
            payload[key] = el.value;
        }
        try {
            await api('api/collections/save.php', { method: 'POST', body: payload });
            toast(isEdit ? 'Guardado' : 'Agregado');
            close();
            switchBotTab(collectionKey);
        } catch (err) {
            toast(err.message, 'error');
        }
    });

    if (isEdit) {
        backdrop.querySelector('#deleteRecordBtn').addEventListener('click', async () => {
            if (!confirm('¿Eliminar este registro? No se puede deshacer.')) return;
            try {
                await api('api/collections/delete.php', { method: 'POST', body: { collection: collectionKey, id: item.id } });
                toast('Eliminado');
                close();
                switchBotTab(collectionKey);
            } catch (err) {
                toast(err.message, 'error');
            }
        });
    }
}

function recordSummaryHtml(collectionKey, item) {
    const schema = state.schemas[collectionKey];
    const entries = Object.entries(schema.fields).filter(([, def]) => !def.secret && def.type !== 'textarea');
    const parts = entries.slice(0, 3).map(([key, def]) => {
        const val = item[key];
        if (!val) return '';
        const label = def.type === 'select' ? (def.options[val] || val) : val;
        return `<span class="badge">${escapeHtml(String(label))}</span>`;
    }).filter(Boolean).join(' ');
    const titleKey = Object.keys(schema.fields)[0];
    const title = item[titleKey] || '(sin título)';
    return `
    <div class="bot-card record-card" data-id="${escapeAttr(item.id)}">
      <div class="bot-card-top">
        <div class="bot-name">${escapeHtml(title)}</div>
      </div>
      <div class="bot-card-foot">${parts}</div>
    </div>
  `;
}

async function renderCollectionTab(host, collectionKey) {
    const schema = state.schemas[collectionKey];
    const res = await api(`api/collections/list.php?collection=${collectionKey}&botId=${encodeURIComponent(state.currentBotId)}`);
    const items = res.items;

    host.innerHTML = `
    <div class="intro-banner">${escapeHtml(schema.description || '')}</div>
    <div class="topbar" style="margin-bottom:14px;">
      <div></div>
      <button class="btn" id="addRecordBtn">+ Agregar</button>
    </div>
    ${items.length === 0
        ? `<div class="empty-state">Todavía no hay nada en ${escapeHtml(schema.label)}.</div>`
        : `<div class="bots-grid">${items.map((it) => recordSummaryHtml(collectionKey, it)).join('')}</div>`
    }
  `;
    document.getElementById('addRecordBtn').addEventListener('click', () => openRecordModal(collectionKey, null));
    host.querySelectorAll('.record-card').forEach((el) => {
        el.addEventListener('click', () => {
            const item = items.find((it) => it.id === el.dataset.id);
            openRecordModal(collectionKey, item);
        });
    });
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
