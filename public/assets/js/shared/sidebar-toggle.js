// Sidebar toggle injector - works across pages that include admin.css and have body
(function(){
  if (!document.body) return;
  var btn = document.getElementById('sidebarToggle');
  if (!btn) {
    btn = document.createElement('button');
    btn.id = 'sidebarToggle';
    btn.className = 'sidebar-toggle';
    btn.type = 'button';
    btn.textContent = '☰';
    document.body.appendChild(btn);
  }
  btn.onclick = function(){
    var sidebar = document.querySelector('.sidebar');
    var main = document.querySelector('.main-content');
    if (!sidebar || !main) return;
    var isHidden = sidebar.style.display === 'none';
    if (isHidden) {
      sidebar.style.display = '';
      main.style.marginLeft = getComputedStyle(document.documentElement).getPropertyValue('--sidebar-width').trim() || '240px';
    } else {
      sidebar.style.display = 'none';
      main.style.marginLeft = '0';
    }
  };
})();


