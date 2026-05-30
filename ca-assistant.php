<?php
require_once __DIR__ . '/members/auth.php';
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>Contract Assistant — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="Ask questions about the BVTU collective agreement and signed letters of understanding. Get plain-language answers instantly.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    /* ── CA Assistant page styles ─────────────────────────────────────────── */

    /* Chat window */
    .chat-window {
      background: #fff;
      border: 1px solid var(--gray-200);
      border-radius: 14px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      box-shadow: 0 2px 16px rgba(0,0,0,.07);
      max-width: 800px;
      margin: 0 auto;
    }
    .chat-messages {
      flex: 1;
      padding: 1.75rem 1.75rem 1rem;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
      min-height: 380px;
      max-height: 520px;
    }

    /* Friendly empty / welcome state */
    .chat-welcome {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      flex: 1;
      padding: 2.5rem 1.5rem 1.5rem;
    }
    .chat-welcome-icon {
      width: 60px; height: 60px;
      background: #f0f7f2;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 1.1rem;
      border: 2px solid #c9e6d2;
    }
    .chat-welcome-icon svg {
      width: 28px; height: 28px;
      stroke: var(--primary);
    }
    .chat-welcome h3 {
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--gray-800);
      margin: 0 0 .5rem;
    }
    .chat-welcome p {
      font-size: .92rem;
      color: var(--gray-500);
      margin: 0;
      max-width: 380px;
      line-height: 1.65;
    }

    /* Message bubbles */
    .msg {
      display: flex;
      gap: .75rem;
      max-width: 100%;
    }
    .msg.msg-user { flex-direction: row-reverse; }
    .msg-avatar {
      width: 34px; height: 34px;
      border-radius: 50%;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .7rem;
      font-weight: 700;
    }
    .msg-user .msg-avatar {
      background: var(--primary);
      color: #fff;
    }
    .msg-ai .msg-avatar {
      background: #f0f7f2;
      color: var(--primary);
      border: 1px solid #c9e6d2;
    }
    .msg-body { flex: 1; min-width: 0; }
    .msg-bubble {
      padding: .85rem 1.1rem;
      border-radius: 12px;
      font-size: .93rem;
      line-height: 1.65;
      white-space: pre-wrap;
      word-wrap: break-word;
    }
    .msg-user .msg-bubble {
      background: var(--primary);
      color: #fff;
      border-bottom-right-radius: 3px;
    }
    .msg-ai .msg-bubble {
      background: #f8faf9;
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
      background: #f8faf9;
      border: 1px solid var(--gray-200);
      color: var(--gray-500);
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
      border-top: 1px solid var(--gray-100);
      padding: 1rem 1.25rem .85rem;
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
      border: 1.5px solid var(--gray-200);
      border-radius: 10px;
      padding: .7rem 1rem;
      font-size: .93rem;
      font-family: inherit;
      resize: none;
      line-height: 1.5;
      min-height: 46px;
      max-height: 120px;
      overflow-y: auto;
      transition: border-color .2s, box-shadow .2s;
      background: #fff;
      color: var(--gray-800);
    }
    #ca-question::placeholder { color: var(--gray-400); }
    #ca-question:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(26,107,53,.1);
    }
    .chat-char-count {
      font-size: .71rem;
      color: var(--gray-300);
      text-align: right;
      margin-top: .2rem;
    }
    .chat-char-count.warn { color: #d97706; }
    #ca-send {
      flex-shrink: 0;
      padding: .7rem 1.2rem;
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: .9rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: .4rem;
      transition: background .2s, transform .1s;
      height: 46px;
    }
    #ca-send:hover:not(:disabled) { background: #155a2a; }
    #ca-send:active:not(:disabled) { transform: scale(.97); }
    #ca-send:disabled { background: var(--gray-200); color: var(--gray-400); cursor: not-allowed; }
    #ca-send svg { width: 15px; height: 15px; }

    /* Example question chips — shown below the input */
    .suggestion-area {
      padding: .6rem 1.25rem 1rem;
      background: #fafafa;
      border-top: 1px solid var(--gray-100);
    }
    .suggestion-label {
      font-size: .74rem;
      color: var(--gray-400);
      margin-bottom: .5rem;
      font-weight: 500;
    }
    .suggestion-chips {
      display: flex;
      flex-wrap: wrap;
      gap: .45rem;
    }
    .suggestion-chip {
      background: #fff;
      border: 1px solid var(--gray-200);
      border-radius: 20px;
      padding: .35rem .85rem;
      font-size: .8rem;
      color: var(--gray-600);
      cursor: pointer;
      line-height: 1.4;
      transition: border-color .15s, background .15s, color .15s;
      white-space: nowrap;
    }
    .suggestion-chip:hover {
      border-color: var(--primary);
      background: #f0f7f2;
      color: var(--primary);
    }
    @media (max-width: 600px) {
      .suggestion-chip { white-space: normal; }
    }

    /* Disclaimer — soft footer note */
    .chat-disclaimer {
      text-align: center;
      font-size: .75rem;
      color: var(--gray-400);
      margin-top: .85rem;
      line-height: 1.5;
    }

    /* Toolbar */
    .chat-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: .85rem 1.25rem .7rem;
      border-bottom: 1px solid var(--gray-100);
    }
    .chat-toolbar-left {
      display: flex;
      align-items: center;
      gap: .6rem;
    }
    .chat-toolbar-dot {
      width: 8px; height: 8px;
      background: #22c55e;
      border-radius: 50%;
      flex-shrink: 0;
    }
    .chat-toolbar-title {
      font-size: .85rem;
      font-weight: 600;
      color: var(--gray-700);
    }
    #clear-chat {
      background: none;
      border: 1px solid var(--gray-200);
      border-radius: 6px;
      padding: .3rem .75rem;
      font-size: .78rem;
      color: var(--gray-400);
      cursor: pointer;
      transition: all .15s;
    }
    #clear-chat:hover { border-color: #ef4444; color: #ef4444; }
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

          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php" class="active">Contract Assistant</a></li></ul></li>
<li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="salary.php">Salary Grids</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
            </ul>
          </li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://bctf.ca/member-services/efap" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://bctf.ca/member-services/benefits-and-services" target="_blank" rel="noopener">Member Benefits</a></li><li><a href="https://bctf.ca/bargaining" target="_blank" rel="noopener">Bargaining</a></li></ul></li>
          <li class="has-dropdown"><a href="library.php">Resources</a><ul class="dropdown"><li><a href="library.php">Resource Library</a></li><li><a href="curated.php">Curated Resources</a></li></ul></li><li><a href="newsletter-archive.php">Newsletters</a></li>
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
      <p>Get plain-language answers about your rights, entitlements, and working conditions — straight from the collective agreement.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <div class="chat-window">

        <!-- Toolbar -->
        <div class="chat-toolbar">
          <div class="chat-toolbar-left">
            <div class="chat-toolbar-dot"></div>
            <span class="chat-toolbar-title">Ask anything about your contract</span>
          </div>
          <button id="clear-chat" title="Start a new conversation">New conversation</button>
        </div>

        <!-- Messages -->
        <div class="chat-messages" id="chat-messages">
          <div class="chat-welcome" id="chat-welcome">
            <div class="chat-welcome-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 3v-3z"/></svg>
            </div>
            <h3>Hi there! What can I help you with?</h3>
            <p>Ask me anything about your collective agreement or letters of understanding — prep time, sick leave, TTOC rights, class size, and more. I'll give you a plain-language answer with sources.</p>
          </div>
        </div>

        <!-- Input -->
        <div class="chat-input-area">
          <form class="chat-form" id="ca-form" autocomplete="off">
            <div class="chat-input-wrap">
              <textarea
                id="ca-question"
                name="question"
                rows="1"
                maxlength="600"
                placeholder="Type your question here…"
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

        <!-- Example questions — below the input, hidden once chatting starts -->
        <div class="suggestion-area" id="suggestions-section">
          <div class="suggestion-label">Try asking…</div>
          <div class="suggestion-chips">
            <button class="suggestion-chip" data-q="How much preparation time am I entitled to as an elementary teacher?">How much prep time do I get as an elementary teacher?</button>
            <button class="suggestion-chip" data-q="How does sick leave work for TTOCs and temporary contract teachers?">How does sick leave work for TTOCs?</button>
            <button class="suggestion-chip" data-q="How is seniority calculated and what are the tiebreakers?">How is seniority calculated?</button>
            <button class="suggestion-chip" data-q="What are the TTOC pay rules when called in for less than a full day?">TTOC pay for less than a full day?</button>
            <button class="suggestion-chip" data-q="What class size limits apply to science labs and shop classes?">Class size limits for labs and shops?</button>
            <button class="suggestion-chip" data-q="Can I request to go part-time and what are the rules?">Can I request to go part-time?</button>
          </div>
        </div>

      </div><!-- /.chat-window -->

      <p class="chat-disclaimer">For information only — answers are sourced from the CA and signed LOUs but may not cover every nuance. For advice on your specific situation, <a href="contact.php">contact the BVTU</a>.</p>

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

          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li></ul></li>
          <li><a href="members.php">Members</a></li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://bctf.ca/member-services/efap" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://bctf.ca/member-services/benefits-and-services" target="_blank" rel="noopener">Member Benefits</a></li><li><a href="https://bctf.ca/bargaining" target="_blank" rel="noopener">Bargaining</a></li></ul></li>
          <li class="has-dropdown"><a href="library.php">Resources</a><ul class="dropdown"><li><a href="library.php">Resource Library</a></li><li><a href="curated.php">Curated Resources</a></li></ul></li><li><a href="newsletter-archive.php">Newsletters</a></li>
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

    let history = [];
    let isLoading = false;

    const form        = document.getElementById('ca-form');
    const textarea    = document.getElementById('ca-question');
    const sendBtn     = document.getElementById('ca-send');
    const messagesEl  = document.getElementById('chat-messages');
    const welcomeEl   = document.getElementById('chat-welcome');
    const charCount   = document.getElementById('char-count');
    const clearBtn    = document.getElementById('clear-chat');
    const suggestSec  = document.getElementById('suggestions-section');

    // Auto-resize textarea
    textarea.addEventListener('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 120) + 'px';
      const len = this.value.length;
      charCount.textContent = len;
      charCount.parentElement.className = 'chat-char-count' + (len > 500 ? ' warn' : '');
      sendBtn.disabled = len === 0 || isLoading;
    });

    // Enter to send (Shift+Enter for newline)
    textarea.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (!sendBtn.disabled) form.requestSubmit();
      }
    });

    // Suggestion chips — fill textarea and focus
    document.querySelectorAll('.suggestion-chip').forEach(btn => {
      btn.addEventListener('click', function () {
        textarea.value = this.dataset.q;
        textarea.dispatchEvent(new Event('input'));
        textarea.focus();
      });
    });

    // Clear / new conversation
    clearBtn.addEventListener('click', function () {
      history = [];
      messagesEl.innerHTML = '';
      messagesEl.appendChild(welcomeEl);
      welcomeEl.style.display = '';
      if (suggestSec) suggestSec.style.display = '';
    });

    // Form submit
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      const q = textarea.value.trim();
      if (!q || isLoading) return;

      // Hide welcome state and suggestions
      if (welcomeEl) welcomeEl.style.display = 'none';
      if (suggestSec) suggestSec.style.display = 'none';

      appendMessage('user', q);
      history.push({ role: 'user', content: q });

      textarea.value = '';
      textarea.style.height = 'auto';
      charCount.textContent = '0';
      sendBtn.disabled = true;

      const thinkId = appendThinking();
      isLoading = true;

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
          history.push({ role: 'assistant', content: data.answer });
          if (history.length > 20) history = history.slice(-20);
        }
      } catch (err) {
        removeThinking(thinkId);
        isLoading = false;
        sendBtn.disabled = textarea.value.trim() === '';
        appendMessage('ai', 'Sorry, something went wrong. Please try again in a moment.', [], true);
      }
    });

    function appendMessage(role, text, sources, isError) {
      const wrap = document.createElement('div');
      wrap.className = 'msg msg-' + (role === 'user' ? 'user' : 'ai');

      const avatar = document.createElement('div');
      avatar.className = 'msg-avatar';
      avatar.textContent = role === 'user' ? 'You' : 'CA';

      const body = document.createElement('div');
      body.className = 'msg-body';

      const bubble = document.createElement('div');
      bubble.className = 'msg-bubble';
      bubble.textContent = text;
      if (isError) bubble.style.cssText = 'background:#fef2f2;border-color:#fecaca;color:#991b1b;';
      body.appendChild(bubble);

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
