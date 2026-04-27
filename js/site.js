// Mobile nav toggle
const toggle = document.querySelector('.nav-toggle');
const nav    = document.getElementById('main-nav');

if (toggle && nav) {
  toggle.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    toggle.setAttribute('aria-expanded', open);
  });

  nav.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      nav.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
    });
  });

  document.addEventListener('click', e => {
    if (!nav.contains(e.target) && !toggle.contains(e.target)) {
      nav.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });
}

// Update nav button based on login cookie — instant, no network request
const loginBtn = document.querySelector('a[href*="login.php"]');
if (loginBtn) {
  const isLoggedIn = document.cookie.split(';').some(c => c.trim() === 'bvtu_logged_in=1');
  if (isLoggedIn) {
    loginBtn.textContent = 'My Dashboard';
    loginBtn.href = '/members/dashboard.php';
    loginBtn.style.background = '#1a6b35';
    loginBtn.style.borderColor = '#1a6b35';
  }
}
