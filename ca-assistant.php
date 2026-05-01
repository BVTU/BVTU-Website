<?php
require_once __DIR__ . '/members/auth.php';
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="site-root" content="">
  <title>Contract Assistant — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="Ask questions about the BVTU collective agreement and signed letters of understanding. Get plain-language answers instantly.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    /* ── CA Assistant page styles ─────────────────────────────────────────── */
    .ca-layout {
      display: grid;
      grid-template-columns: 1fr 320px;
      gap: 2rem;
      align-items: start;
    }
    @media (max-width: 900px) {
      .ca-layout { grid-template-columns: 1fr; }
      .ca-sidebar { order: -1; }
    }

    /* Chat window */
    .chat-window {
      background: #fff;
      border: 1px solid var(--gray-200);
      border-radius: 12px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      min-height: 520px;
    }
    .chat-messages {
      flex: 1;
      padding: 1.5rem;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
      max-height: 520px;
    }
    .chat-empty {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      flex: 1;
      padding: 2rem;
      color: var(--gray-400);
    }
    .chat-empty svg {
      width: 48px; height: 48px;
      margin-bottom: 1rem;
      color: var(--gray-300);
    }
    .chat-empty p { font-size: .95rem; margin: 0; }

    /* Message bubbles */
    .msg {
      display: flex;
      gap: .75rem;
      max-width: 100%;
    }
    .msg.msg-user {
      flex-direction: row-reverse;
    }
    .msg-avatar {
      width: 36px; height: 36px;
      border-radius: 50%;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .75rem;
      font-weight: 700;
    }
    .msg-user .msg-avatar {
      background: var(--primary);
      color: #fff;
    }
    .msg-ai .msg-avatar {
      background: #f0f7f2;
      color: var(--primary);
      border: 1px solid var(--gray-200);
    }
    .msg-body { flex: 1; min-width: 0; }
    .msg-bubble {
      padding: .85rem 1.1rem;
      border-radius: 12px;
      font-size: .93rem;
      line-height: 1.6;
      white-space: pre-wrap;
      word-wrap: break-word;
    }
    .msg-user .msg-bubble {
      background: var(--primary);
      color: #fff;
      border-bottom-right-radius: 3px;
    }
    .msg-ai .msg-bubble {
      background: #f8f9fa;
      color: var(--gray-800);
      border: 1px solid var(--gray-200);
      border-bottom-left-radius: 3px;
    }
    .msg-sources {
      margin-top: .5rem;
      display: flex;
      flex-wrap: wrap;
      gap: .4rem;
    }
    .source-badge {
      display: inline-flex;
      align-items: center;
      gap: .3rem;
      font-size: .75rem;
      padding: .2rem .6rem;
      border-radius: 20px;
      text-decoration: none;
      font-weight: 500;
      transition: opacity .15s;
    }
    .source-badge:hover { opacity: .8; }
    .source-badge.ca {
      background: #e8f4ed;
      color: #1a5c2e;
      border: 1px solid #b8ddc5;
    }
    .source-badge.lou {
      background: #e8f0fb;
      color: #1a3a7a;
      border: 1px solid #b8cdf5;
    }
    .source-badge svg { width: 12px; height: 12px; flex-shrink: 0; }

    /* Thinking indicator */
    .msg-thinking .msg-bubble {
      background: #f8f9fa;
      border: 1px solid var(--gray-200);
      color: var(--gray-500);
      font-style: italic;
    }
    .dot-flashing {
      display: inline-flex;
      gap: 4px;
      align-items: center;
    }
    .dot-flashing span {
      width: 6px; height: 6px;
      border-radius: 50%;
      background: var(--primary);
      animation: dotFlash 1.2s infinite linear;
    }
    .dot-flashing span:nth-child(2) { animation-delay: .2s; }
    .dot-flashing span:nth-child(3) { animation-delay: .4s; }
    @keyframes dotFlash {
      0%, 60%, 100% { opacity: .2; }
      30% { opacity: 1; }
    }

    /* Chat input area */
    .chat-input-area {
      border-top: 1px solid var(--gray-200);
      padding: 1rem 1.25rem;
      background: #fafafa;
    }
    .chat-form {
      display: flex;
      gap: .75rem;
      align-items: flex-end;
    }
    .chat-input-wrap { flex: 1; }
    #ca-question {
      width: 100%;
      border: 1px solid var(--gray-300);
      border-radius: 8px;
      padding: .65rem .9rem;
      font-size: .93rem;
      font-family: inherit;
      resize: none;
      line-height: 1.5;
      min-height: 44px;
      max-height: 120px;
      overflow-y: auto;
      transition: border-color .2s;
      background: #fff;
    }
    #ca-question:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(26,107,53,.12);
    }
    .chat-char-count {
      font-size: .72rem;
      color: var(--gray-400);
      text-align: right;
      margin-top: .25rem;
    }
    .chat-char-count.warn { color: #d97706; }
    #ca-send {
      flex-shrink: 0;
      padding: .65rem 1.1rem;
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: .9rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: .4rem;
      transition: background .2s;
      height: 44px;
    }
    #ca-send:hover:not(:disabled) { background: #155a2a; }
    #ca-send:disabled { background: var(--gray-300); cursor: not-allowed; }
    #ca-send svg { width: 16px; height: 16px; }

    /* Suggested questions */
    .suggestion-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: .6rem;
      margin-bottom: 1.5rem;
    }
    @media (max-width: 600px) { .suggestion-grid { grid-template-columns: 1fr; } }
    .suggestion-btn {
      background: #fff;
      border: 1px solid var(--gray-200);
      border-radius: 8px;
      padding: .65rem .85rem;
      font-size: .83rem;
      color: var(--gray-700);
      cursor: pointer;
      text-align: left;
      line-height: 1.4;
      transition: border-color .15s, background .15s;
    }
    .suggestion-btn:hover {
      border-color: var(--primary);
      background: #f0f7f2;
      color: var(--primary);
    }

    /* Sidebar */
    .ca-sidebar .info-box { margin-bottom: 1.25rem; }
    .ca-sidebar .info-box:last-child { margin-bottom: 0; }
    .sidebar-label {
      font-size: .75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--gray-500);
      margin-bottom: .6rem;
    }
    .lou-quick-links { list-style: none; padding: 0; margin: 0; }
    .lou-quick-links li {
      padding: .4rem 0;
      border-bottom: 1px solid var(--gray-100);
      font-size: .84rem;
    }
    .lou-quick-links li:last-child { border-bottom: none; }
    .lou-quick-links a { color: var(--primary); text-decoration: none; }
    .lou-quick-links a:hover { text-decoration: underline; }

    /* Disclaimer */
    .disclaimer-box {
      background: #fffbeb;
      border: 1px solid #fde68a;
      border-radius: 8px;
      padding: .85rem 1rem;
      font-size: .82rem;
      color: #78350f;
      line-height: 1.5;
      margin-bottom: 1rem;
    }
    .disclaimer-box strong { color: #92400e; }

    /* Clear conversation */
    #clear-chat {
      background: none;
      border: 1px solid var(--gray-300);
      border-radius: 6px;
      padding: .35rem .8rem;
      font-size: .8rem;
      color: var(--gray-500);
      cursor: pointer;
      transition: all .15s;
    }
    #clear-chat:hover { border-color: #ef4444; color: #ef4444; }

    .chat-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: .75rem 1.25rem .5rem;
      border-bottom: 1px solid var(--gray-100);
    }
    .chat-toolbar-title {
      font-size: .82rem;
      font-weight: 600;
      color: var(--gray-600);
    }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="header-inner container">
      <a href="index.php" class="logo">
        <img src="bvtu-logo.png" alt="BVTU Logo">
        <div class="logo-text">
          <span class="logo-name">Bulkley Valley Teachers' Union</span>
          <span class="logo-sub">Local of the BC Teachers' Federation</span>
        </div>
      </a>
      <button class="search-btn" data-search-open aria-label="Search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </button>
      <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <nav class="main-nav" id="main-nav">
        <ul>
          <li><a href="about.php">About</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php" class="active">Contract Assistant</a></li></ul></li>
<li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
            </ul>
          </li>
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="<?= $loggedIn ? '/members/dashboard.php' : 'members/login.php' ?>"
              class="btn btn-primary"
              style="padding:.4rem .9rem;font-size:.88rem;margin-left:.5rem;<?= $loggedIn ? 'background:#1a6b35;border-color:#1a6b35;' : '' ?>">
            <?= $loggedIn ? 'My Dashboard' : 'Member Login' ?>
          </a></li>
        </ul>
      </nav>
    </div>
  </header>

  <section class="page-hero">
    <div class="container">
      <h1>Contract Assistant</h1>
      <p>Ask questions about the collective agreement and signed letters of understanding in plain language.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <!-- Suggested questions (shown above the chat on first load) -->
      <div id="suggestions-section">
        <p style="font-size:.88rem;color:var(--gray-500);margin-bottom:.75rem;">Try one of these questions, or type your own below:</p>
        <div class="suggestion-grid">
          <button class="suggestion-btn" data-q="How much preparation time am I entitled to as an elementary teacher?">How much prep time am I entitled to as an elementary teacher?</button>
          <button class="suggestion-btn" data-q="How does sick leave work for TTOCs and temporary contract teachers?">How does sick leave work for TTOCs and temporary contract teachers?</button>
          <button class="suggestion-btn" data-q="How is seniority calculated and what are the tiebreakers?">How is seniority calculated and what are the tiebreakers?</button>
          <button class="suggestion-btn" data-q="What are the TTOC pay rules when called in for less than a full day?">What are the TTOC pay rules when called in for less than a full day?</button>
          <button class="suggestion-btn" data-q="What class size limits apply to science labs and shop classes?">What class size limits apply to science labs and shop classes?</button>
          <button class="suggestion-btn" data-q="Can I request to go part-time and what are the rules?">Can I request to go part-time and what are the rules?</button>
        </div>
      </div>

      <div class="ca-layout">

        <!-- Chat window -->
        <div>
          <div class="chat-window">
            <div class="chat-toolbar">
              <span class="chat-toolbar-title">Contract Assistant</span>
              <button id="clear-chat" title="Clear conversation">Clear chat</button>
            </div>
            <div class="chat-messages" id="chat-messages">
              <div class="chat-empty" id="chat-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 3v-3z"/></svg>
                <p>Ask a question about your collective agreement or any signed letter of understanding.</p>
              </div>
            </div>
            <div class="chat-input-area">
              <div class="disclaimer-box">
                <strong>For information only.</strong> This assistant searches the CA and signed LOUs but may not catch every nuance. For individual workplace situations, always consult the BVTU.
              </div>
              <form class="chat-form" id="ca-form" autocomplete="off">
                <div class="chat-input-wrap">
                  <textarea
                    id="ca-question"
                    name="question"
                    rows="1"
                    maxlength="600"
                    placeholder="Ask about prep time, sick leave, TTOC rights, class size…"
                    aria-label="Your question"
                  ></textarea>
                  <div class="chat-char-count"><span id="char-count">0</span>/600</div>
                </div>
                <button type="submit" id="ca-send" disabled>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                  Send
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="ca-sidebar">

          <div class="info-box">
            <div class="sidebar-label">What this covers</div>
            <p style="font-size:.85rem;margin:0;line-height:1.6;">This assistant has access to the full <a href="collective-agreement.php">Local Collective Agreement</a> and all signed <a href="lous.php">Letters of Understanding</a> between BVTU and School District 54.</p>
          </div>

          <div class="info-box">
            <div class="sidebar-label">Recent LOUs &amp; Settlements</div>
            <ul class="lou-quick-links">
              <li><a href="documents/settlements/2025-2026-lous-signed.pdf" target="_blank">2025-2026 LOUs (Thursday dismissal, FTE increases, Pro-D remedy)</a></li>
              <li><a href="documents/settlements/2024-lab-shop-class-size.pdf" target="_blank">2024 Labs &amp; Shops Class Size Settlement</a></li>
              <li><a href="documents/settlements/2024-d4-5-elem-prep.pdf" target="_blank">2024 Elementary Prep Time LOA</a></li>
              <li><a href="documents/settlements/2023-calendar-settlement.pdf" target="_blank">2023 School Calendar Settlement</a></li>
              <li><a href="lous.php">View all LOUs &amp; Settlements →</a></li>
            </ul>
          </div>

          <div class="info-box">
            <div class="sidebar-label">Still have questions?</div>
            <p style="font-size:.85rem;margin:0 0 .75rem;line-height:1.6;">Contact the BVTU president for interpretation or advice on your specific situation.</p>
            <a href="contact.php" class="btn btn-outline" style="font-size:.85rem;padding:.45rem .9rem;">Contact BVTU</a>
          </div>

        </div>

      </div><!-- /.ca-layout -->

    </div>
  </main>

  <footer class="site-footer">
    <div class="container footer-grid">
      <div>
        <h3>Bulkley Valley Teachers' Union</h3>
        <p>Local of the BC Teachers' Federation</p>
        <p>Representing educators in<br>Houston, Telkwa, and Smithers</p>
      </div>
      <div>
        <h3>Contact</h3>
        <p><strong style="color:rgba(255,255,255,.9)">President:</strong> Cody Lind</p>
        <p>3772-C 1st Ave<br>Smithers, BC V0J 2N0</p>
        <p><a href="contact.php">Contact Us</a></p>
      </div>
      <div>
        <h3>Navigate</h3>
        <ul class="footer-nav-list">
          <li><a href="about.php">About</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li></ul></li>
          <li><a href="members.php">Members</a></li>
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
        </ul>
      </div>
      <div>
        <h3>Connect</h3>
        <a href="#" target="_blank" rel="noopener" class="btn btn-outline-white">Facebook Group</a>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="container">
        <p>© 2026 Bulkley Valley Teachers' Union · Local of the BC Teachers' Federation</p>
      </div>
    </div>
  </footer>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
  <script>
  (function () {
    'use strict';

    // ── State ────────────────────────────────────────────────────────────────
    let history = []; // [{role, content}]
    let isLoading = false;

    // ── Elements ─────────────────────────────────────────────────────────────
    const form        = document.getElementById('ca-form');
    const textarea    = document.getElementById('ca-question');
    const sendBtn     = document.getElementById('ca-send');
    const messagesEl  = document.getElementById('chat-messages');
    const emptyEl     = document.getElementById('chat-empty');
    const charCount   = document.getElementById('char-count');
    const clearBtn    = document.getElementById('clear-chat');
    const suggestSec  = document.getElementById('suggestions-section');

    // ── Auto-resize textarea ─────────────────────────────────────────────────
    textarea.addEventListener('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 120) + 'px';
      const len = this.value.length;
      charCount.textContent = len;
      charCount.parentElement.className = 'chat-char-count' + (len > 500 ? ' warn' : '');
      sendBtn.disabled = len === 0 || isLoading;
    });

    // ── Enter to send (Shift+Enter for newline) ───────────────────────────────
    textarea.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (!sendBtn.disabled) form.requestSubmit();
      }
    });

    // ── Suggestion buttons ───────────────────────────────────────────────────
    document.querySelectorAll('.suggestion-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        textarea.value = this.dataset.q;
        textarea.dispatchEvent(new Event('input'));
        textarea.focus();
      });
    });

    // ── Clear chat ────────────────────────────────────────────────────────────
    clearBtn.addEventListener('click', function () {
      history = [];
      messagesEl.innerHTML = '';
      emptyEl.style.display = '';
      messagesEl.appendChild(emptyEl);
      if (suggestSec) suggestSec.style.display = '';
    });

    // ── Form submit ───────────────────────────────────────────────────────────
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      const q = textarea.value.trim();
      if (!q || isLoading) return;

      // Hide suggestions after first ask
      if (suggestSec) suggestSec.style.display = 'none';

      // Hide empty state
      if (emptyEl) emptyEl.style.display = 'none';

      // Add user message
      appendMessage('user', q);
      history.push({ role: 'user', content: q });

      // Clear input
      textarea.value = '';
      textarea.style.height = 'auto';
      charCount.textContent = '0';
      sendBtn.disabled = true;

      // Show thinking indicator
      const thinkId = appendThinking();
      isLoading = true;
      sendBtn.disabled = true;

      try {
        const resp = await fetch('ca-ask.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ question: q, history: history.slice(0, -1) }),
        });

        const data = await resp.json();
        removeThinking(thinkId);
        isLoading = false;
        sendBtn.disabled = textarea.value.trim() === '';

        if (data.error) {
          appendMessage('ai', data.error, [], true);
        } else {
          appendMessage('ai', data.answer, data.sources || []);
          // Add assistant turn to history (without the context — just the answer)
          history.push({ role: 'assistant', content: data.answer });
          // Keep history trim (last 10 turns = 20 messages)
          if (history.length > 20) history = history.slice(-20);
        }
      } catch (err) {
        removeThinking(thinkId);
        isLoading = false;
        sendBtn.disabled = textarea.value.trim() === '';
        appendMessage('ai', 'Sorry, something went wrong. Please try again in a moment.', [], true);
      }
    });

    // ── Helpers ───────────────────────────────────────────────────────────────
    function appendMessage(role, text, sources, isError) {
      const wrap = document.createElement('div');
      wrap.className = 'msg msg-' + (role === 'user' ? 'user' : 'ai');

      const avatar = document.createElement('div');
      avatar.className = 'msg-avatar';
      avatar.textContent = role === 'user' ? 'You' : 'CA';

      const body = document.createElement('div');
      body.className = 'msg-body';

      const bubble = document.createElement('div');
      bubble.className = 'msg-bubble' + (isError ? ' error-bubble' : '');
      bubble.textContent = text;
      if (isError) bubble.style.cssText = 'background:#fef2f2;border-color:#fecaca;color:#991b1b;';
      body.appendChild(bubble);

      // Source badges
      if (sources && sources.length) {
        const sourceRow = document.createElement('div');
        sourceRow.className = 'msg-sources';
        sources.forEach(src => {
          const badge = document.createElement('a');
          badge.className = 'source-badge ' + (src.type === 'ca' ? 'ca' : 'lou');
          badge.href = src.url;
          badge.target = '_blank';
          badge.rel = 'noopener';
          badge.title = src.title;
          badge.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <span>${truncate(src.title, 40)}</span>`;
          sourceRow.appendChild(badge);
        });
        body.appendChild(sourceRow);
      }

      wrap.appendChild(avatar);
      wrap.appendChild(body);
      messagesEl.appendChild(wrap);
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    let thinkingCounter = 0;
    function appendThinking() {
      const id = 'think-' + (++thinkingCounter);
      const wrap = document.createElement('div');
      wrap.className = 'msg msg-ai msg-thinking';
      wrap.id = id;
      wrap.innerHTML = `
        <div class="msg-avatar">CA</div>
        <div class="msg-body">
          <div class="msg-bubble">
            <div class="dot-flashing"><span></span><span></span><span></span></div>
          </div>
        </div>`;
      messagesEl.appendChild(wrap);
      messagesEl.scrollTop = messagesEl.scrollHeight;
      return id;
    }

    function removeThinking(id) {
      const el = document.getElementById(id);
      if (el) el.remove();
    }

    function truncate(str, max) {
      return str.length <= max ? str : str.slice(0, max - 1) + '…';
    }
  })();
  </script>
</body>
</html>
