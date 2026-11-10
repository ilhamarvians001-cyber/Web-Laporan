</main>
<footer class="app-footer">
  &copy; <?= date('Y') ?> <?= htmlspecialchars(NAMA_TOKO) ?> · Sistem Laporan Laser Engrave
</footer>
</div><!-- /.app-shell -->

<script>
(function(){
  var B = document.body;

  /* ===== Drawer (tombol Back Android menutup drawer) ===== */
  window.openDrawer = function(){
    if (B.classList.contains('drawer-open') || window.innerWidth >= 900) return;
    B.classList.add('drawer-open'); B.classList.add('lock');
    history.pushState({drawer:1}, '');
  };
  function closeDrawer(fromPop){
    if (!B.classList.contains('drawer-open')) return;
    B.classList.remove('drawer-open'); B.classList.remove('lock');
    if (!fromPop && history.state && history.state.drawer) history.back();
  }
  var mb = document.getElementById('menuBtn'); if (mb) mb.addEventListener('click', openDrawer);
  document.getElementById('scrim').addEventListener('click', function(){ closeDrawer(false); });
  window.addEventListener('popstate', function(){ closeDrawer(true); });
  document.querySelectorAll('#drawer a').forEach(function(a){
    a.addEventListener('click', function(){ B.classList.remove('drawer-open','lock'); });
  });

  /* ===== App bar: bayangan saat scroll ===== */
  var bar = document.getElementById('appbar');
  window.addEventListener('scroll', function(){ bar.classList.toggle('scrolled', window.scrollY > 4); }, {passive:true});

  /* ===== Snackbar ===== */
  window.hideSnack = function(){
    var s = document.getElementById('snack'); if (!s) return;
    s.classList.add('hide'); setTimeout(function(){ s.remove(); }, 260);
  };
  if (document.getElementById('snack')) setTimeout(hideSnack, 4500);

  /* ===== Sembunyikan bottom nav/FAB saat keyboard muncul ===== */
  document.addEventListener('focusin', function(e){
    if (/^(INPUT|SELECT|TEXTAREA)$/.test(e.target.tagName)) B.classList.add('kb');
  });
  document.addEventListener('focusout', function(){ setTimeout(function(){
    var a = document.activeElement;
    if (!a || !/^(INPUT|SELECT|TEXTAREA)$/.test(a.tagName)) B.classList.remove('kb');
  }, 120); });

  /* ===== Tabel -> kartu (hanya tampil sebagai kartu di layar HP) ===== */
  document.querySelectorAll('main table').forEach(function(t){
    if (t.id === 'itemsTable' || t.classList.contains('no-cards')) return;
    if (t.querySelector('tbody input, tbody select, tbody textarea')) return;
    var heads = Array.prototype.map.call(t.querySelectorAll('thead th'), function(th){ return th.textContent.trim(); });
    if (!heads.length) return;
    t.querySelectorAll('tbody tr, tfoot tr').forEach(function(tr){
      var cells = tr.children;
      if (cells.length === 1 && cells[0].hasAttribute('colspan')) return;
      for (var i = 0; i < cells.length; i++){
        if (i === 0) cells[i].classList.add('m-title');
        else if (heads[i]) cells[i].setAttribute('data-label', heads[i]);
      }
    });
    t.classList.add('m-cards');
  });

  /* ===== Efek ripple ala Material ===== */
  var RIP = '.btn,.bn-item .bn-ind,.drawer a,.fab,.icon-btn,.btn-wa-sm,.btn-logout';
  document.addEventListener('pointerdown', function(e){
    var el = e.target.closest(RIP); if (!el) return;
    var r = el.getBoundingClientRect(), d = Math.max(r.width, r.height) * 2;
    var s = document.createElement('span'); s.className = 'rip';
    s.style.cssText = 'width:'+d+'px;height:'+d+'px;left:'+(e.clientX - r.left - d/2)+'px;top:'+(e.clientY - r.top - d/2)+'px';
    el.appendChild(s); setTimeout(function(){ s.remove(); }, 600);
  });
})();
</script>
</body>
</html>
