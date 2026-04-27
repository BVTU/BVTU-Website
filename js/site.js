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

// Update "Member Login" button based on login status
const loginBtn = document.querySelector('a[href*="login.php"]');
if (loginBtn) {
  fetch('/members/status.php')
    .then(r => r.json())
    .then(data => {
      if (data.loggedIn) {
        loginBtn.textContent = 'My Dashboard';
        loginBtn.href = '/members/dashboard.php';
        loginBtn.style.background = '#1a6b35';
        loginBtn.style.borderColor = '#1a6b35';
      }
    })
    .catch(() => {}); // fails silently on local — works on server
}
