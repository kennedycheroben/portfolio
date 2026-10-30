<?php
declare(strict_types=1);
?>
<script>
function toggleSidebar() {
  var sidebar = document.querySelector('.admin-sidebar');
  var overlay = document.querySelector('.sidebar-overlay');
  if (sidebar && overlay) {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
  }
}

function updateThemeToggleUi(theme) {
  var icon = document.getElementById('themeToggleIcon');
  var label = document.getElementById('themeToggleLabel');
  if (theme === 'light') {
    if (icon) {
      icon.className = 'bi bi-sun-fill';
    }
    if (label) {
      label.textContent = 'Theme: Light';
    }
  } else {
    if (icon) {
      icon.className = 'bi bi-moon-stars-fill';
    }
    if (label) {
      label.textContent = 'Theme: Dark';
    }
  }
}

function toggleAdminTheme() {
  var current = document.documentElement.getAttribute('data-admin-theme') === 'light' ? 'light' : 'dark';
  var next = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-admin-theme', next);
  try {
    localStorage.setItem('portfolio_admin_theme', next);
  } catch (e) {}
  updateThemeToggleUi(next);
}

// Sync toggle button icon with initial theme
(function() {
  var currentTheme = document.documentElement.getAttribute('data-admin-theme') || 'dark';
  updateThemeToggleUi(currentTheme);
})();
</script>
</body>
</html>
