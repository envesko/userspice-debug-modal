<?php
$dmCfg = function_exists('debugModalConfig') ? debugModalConfig() : null;
$dmRequireAdmin = $dmCfg ? ((int) $dmCfg->RequireAdmin === 1) : false;

// Always localhost-only. Optionally also require an admin (perm 2) when RequireAdmin is set.
if (isLocalhost() && (!$dmRequireAdmin || ($user->isLoggedIn() && hasPerm(2)))) {
    /**
     * Dev extension point - add your own sections to the dump:
     *   1) anywhere before the footer:  $GLOBALS['DebugModal_extra']['My Label'] = $anything;
     *   2) or drop a file at usersc/includes/debug_modal_custom.php that populates that same array.
     */
    $custom = $abs_us_root . $us_url_root . 'usersc/includes/debug_modal_custom.php';
    if (file_exists($custom)) {
        include $custom;
    }
    $DebugModal_extra = (isset($GLOBALS['DebugModal_extra']) && is_array($GLOBALS['DebugModal_extra'])) ? $GLOBALS['DebugModal_extra'] : [];

    // Collapsible section: clickable header (caret + label + copy) over a collapsible dump body.
    if (!function_exists('DebugModal_section')) {
        function DebugModal_section($label, $data, $open = true)
        {
            static $n = 0;
            $n++;
            $bodyId = 'dbgbody_' . $n;
            $jsonId = 'dbgjson_' . $n;
            echo '<div class="card dbg-card mb-2">';
            echo '<div class="dbg-head' . ($open ? '' : ' collapsed') . '" role="button" tabindex="0" data-bs-toggle="collapse" data-bs-target="#' . $bodyId . '" aria-expanded="' . ($open ? 'true' : 'false') . '">';
            echo '<i class="fa fa-chevron-down caret"></i><span class="dbg-label">' . htmlspecialchars($label) . '</span>';
            echo '<button type="button" class="btn btn-sm btn-light dbg-copy" title="Copy as JSON" onclick="event.stopPropagation();DebugModal_copy(\'' . $jsonId . '\',this)"><i class="fa fa-copy"></i></button>';
            echo '</div>';
            echo '<div id="' . $bodyId . '" class="collapse' . ($open ? ' show' : '') . '"><div class="dbg-dump">';
            dump($data);
            $json = @json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
            echo '<script type="application/json" id="' . $jsonId . '">' . ($json === false ? '"(not serialisable)"' : $json) . '</script>';
            echo '</div></div></div>';
        }
    }

    $dmShow = function ($key, $default = 1) {
        return function_exists('debugModalShow') ? debugModalShow($key, $default) : (bool) $default;
    };

    $dmActivePlugins = 0;
    if (isset($GLOBALS['usplugins']) && is_array($GLOBALS['usplugins'])) {
        foreach ($GLOBALS['usplugins'] as $dmV) {
            if ($dmV == 1) {
                $dmActivePlugins++;
            }
        }
    }
    $dmSystem = [
        'php_version'       => PHP_VERSION,
        'userspice_version' => $GLOBALS['user_spice_ver'] ?? 'unknown',
        'template'          => isset($settings->template) ? $settings->template : 'unknown',
        'current_page'      => currentPage(),
        'memory_usage'      => round(memory_get_usage() / 1048576, 2) . ' MB',
        'memory_peak'       => round(memory_get_peak_usage() / 1048576, 2) . ' MB',
        'active_plugins'    => $dmActivePlugins,
    ];

    // Server vars are shown intentionally for debugging; strip the sensitive ones.
    $dmServer = $_SERVER;
    foreach (['HTTP_COOKIE', 'HTTP_AUTHORIZATION', 'PHP_AUTH_PW', 'PHP_AUTH_USER', 'PHP_AUTH_DIGEST'] as $dmK) {
        unset($dmServer[$dmK]);
    }

    $dmAutoOpenDefault = ($dmCfg && (int) $dmCfg->AutoOpen === 1) ? 'enabled' : 'disabled';
    $dmCfgUrl = $us_url_root . 'users/admin.php?view=plugins_config&plugin=debugmodal';
?>
<style>
  #debugmodal .modal-header { background: linear-gradient(135deg, #2ea5cb 0%, #2edcb7 100%); color: #fff; }
  #debugmodal .modal-title { font-weight: 600; }
  #debugmodal .modal-header .btn-close { filter: brightness(0) invert(1); opacity: .9; }
  #debugmodal .modal-body { background: #eef2f7; }
  #debugmodal .dbg-toolbar { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; margin-bottom: .75rem; }
  #debugmodal .dbg-toolbar .spacer { flex: 1; }
  #debugmodal .dbg-card { border: 1px solid #dde3ec; border-radius: .55rem; overflow: hidden; background: #fff; }
  #debugmodal .dbg-head { display: flex; align-items: center; gap: .55rem; cursor: pointer; padding: .5rem .75rem; font-weight: 600; color: #1f2733; user-select: none; }
  #debugmodal .dbg-head:hover { background: #f4f7fb; }
  #debugmodal .dbg-head .caret { color: #2ea5cb; transition: transform .15s ease; font-size: .8rem; }
  #debugmodal .dbg-head.collapsed .caret { transform: rotate(-90deg); }
  #debugmodal .dbg-label { flex: 1; }
  #debugmodal .dbg-copy { padding: .05rem .45rem; line-height: 1.2; }
  #debugmodal .dbg-dump { max-height: 340px; overflow: auto; padding: .5rem .75rem; border-top: 1px solid #eef0f4; background: #fbfcfe; }
  #debugmodal .dbg-dump pre { margin: 0; white-space: pre-wrap; word-break: break-word; font-size: .8rem; }
  #debugmodal .dbg-hint { font-size: .82rem; color: #5a6473; margin-top: .25rem; }
  #debugmodal .dbg-hint code { background: #e7edf5; padding: .05rem .3rem; border-radius: .25rem; }
</style>
<div class="modal fade" id="debugmodal" tabindex="-1" role="dialog" aria-labelledby="debugLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="debugLabel"><i class="fa fa-bug me-2"></i>Debug Data</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <div class="dbg-toolbar">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="DebugModal_toggle_all" onclick="DebugModal_toggleAll(this)"><i class="fa fa-angles-up me-1"></i>Collapse all</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="DebugModal_auto_open_debug" onclick="DebugModal_autoOpenToggle()">Auto-open on load</button>
          <span class="spacer"></span>
          <?php if (!$user->isLoggedIn() && pluginActive('localhostlogin', true)) { ?>
            <a href="<?php echo $us_url_root; ?>usersc/plugins/localhostlogin/files/index.php" class="btn btn-sm btn-outline-primary" id="DebugModal_LocalhostLogin"><i class="fa fa-right-to-bracket me-1"></i>Localhost login</a>
          <?php } ?>
          <?php if ($user->isLoggedIn() && hasPerm(2) && currentPage() != 'admin.php') { ?>
            <a href="<?php echo $us_url_root; ?>users/admin.php" class="btn btn-sm btn-outline-primary" id="DebugModal_ACPLink"><i class="fa fa-gauge me-1"></i>Admin</a>
          <?php } ?>
          <span id="DebugModal_session_destroy_parent"><button class="btn btn-sm btn-outline-danger" id="DebugModal_session_destroy" onclick="DebugModal_destroySession()"><i class="fa fa-trash me-1"></i>Destroy session</button></span>
        </div>

        <?php if ($dmShow('ShowSession')) { DebugModal_section('Session', $_SESSION); } ?>
        <?php if ($dmShow('ShowPost')) { DebugModal_section('POST', $_POST); } ?>
        <?php if ($dmShow('ShowGet', 0)) { DebugModal_section('GET', $_GET); } ?>
        <?php if ($dmShow('ShowCookies', 0)) { DebugModal_section('Cookies', $_COOKIE); } ?>
        <?php if ($dmShow('ShowUser')) { DebugModal_section('User Data', $user->isLoggedIn() ? $user->data() : 'Not Logged In'); } ?>
        <?php if ($dmShow('ShowServer', 0)) { DebugModal_section('Server', $dmServer, false); } ?>
        <?php if ($dmShow('ShowSystem')) { DebugModal_section('System', $dmSystem); } ?>
        <?php DebugModal_section('Misc', ['abs_us_root' => $abs_us_root, 'us_url_root' => $us_url_root], false); ?>
        <?php foreach ($DebugModal_extra as $label => $data) {
            DebugModal_section((string) $label, $data);
        } ?>

        <p class="dbg-hint mb-0">
          <i class="fa fa-circle-info me-1"></i>Developers: add your own section with
          <code>$GLOBALS['DebugModal_extra']['Label']&nbsp;=&nbsp;$data;</code>
          (or a <code>usersc/includes/debug_modal_custom.php</code> file).
          <a href="<?php echo $dmCfgUrl; ?>">Plugin settings &amp; docs</a>.
        </p>
      </div>
    </div>
  </div>
</div>
<script>
var DebugModal_autoOpenDefault = '<?php echo $dmAutoOpenDefault; ?>';
window.addEventListener('load', function () {
  var footer = document.querySelector('footer');
  if (footer) {
    var host = footer.querySelector('p') || footer;
    var link = document.createElement('a');
    link.href = '#';
    link.id = 'DebugModal_open';
    link.setAttribute('data-bs-toggle', 'modal');
    link.setAttribute('data-bs-target', '#debugmodal');
    link.textContent = 'Debug';
    host.appendChild(document.createTextNode(' | '));
    host.appendChild(link);
  }
  var autoOpen = localStorage.getItem('DebugModal_auto_open_debug');
  if (autoOpen === null) {
    autoOpen = DebugModal_autoOpenDefault;
  }
  DebugModal_updateOpenToggleButton(autoOpen);
  if (autoOpen == 'enabled' && window.bootstrap && bootstrap.Modal) {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('debugmodal')).show();
  }
  // Keep the Expand/Collapse-all label correct, including when sections are toggled individually.
  var dbgCollapses = document.querySelectorAll('#debugmodal .collapse');
  for (var k = 0; k < dbgCollapses.length; k++) {
    dbgCollapses[k].addEventListener('shown.bs.collapse', DebugModal_syncToggleLabel);
    dbgCollapses[k].addEventListener('hidden.bs.collapse', DebugModal_syncToggleLabel);
  }
  DebugModal_syncToggleLabel();
});

function DebugModal_anyOpen() {
  var heads = document.querySelectorAll('#debugmodal .dbg-head');
  for (var i = 0; i < heads.length; i++) {
    if (!heads[i].classList.contains('collapsed')) { return true; }
  }
  return false;
}

// Keep the button label matching the action it will perform, based on the real section state.
function DebugModal_syncToggleLabel() {
  var btn = document.getElementById('DebugModal_toggle_all');
  if (!btn) { return; }
  if (DebugModal_anyOpen()) {
    btn.innerHTML = '<i class="fa fa-angles-up me-1"></i>Collapse all';
  } else {
    btn.innerHTML = '<i class="fa fa-angles-down me-1"></i>Expand all';
  }
}

function DebugModal_toggleAll() {
  var collapse = !DebugModal_anyOpen() ? false : true; // if anything is open, collapse everything
  var heads = document.querySelectorAll('#debugmodal .dbg-head');
  for (var j = 0; j < heads.length; j++) {
    var target = document.querySelector(heads[j].getAttribute('data-bs-target'));
    if (!target || !window.bootstrap) { continue; }
    var inst = bootstrap.Collapse.getOrCreateInstance(target, { toggle: false });
    if (collapse) { inst.hide(); } else { inst.show(); }
  }
  // Label resyncs from the collapse events; set it immediately too for responsiveness.
  var btn = document.getElementById('DebugModal_toggle_all');
  if (btn) {
    btn.innerHTML = collapse ? '<i class="fa fa-angles-down me-1"></i>Expand all' : '<i class="fa fa-angles-up me-1"></i>Collapse all';
  }
}

function DebugModal_autoOpenToggle() {
  var autoOpen = localStorage.getItem('DebugModal_auto_open_debug');
  if (!autoOpen) { autoOpen = DebugModal_autoOpenDefault; }
  autoOpen = (autoOpen == 'enabled') ? 'disabled' : 'enabled';
  localStorage.setItem('DebugModal_auto_open_debug', autoOpen);
  DebugModal_updateOpenToggleButton(autoOpen);
}

function DebugModal_updateOpenToggleButton(state) {
  var btn = document.getElementById('DebugModal_auto_open_debug');
  if (!btn) { return; }
  if (state == 'enabled') {
    btn.classList.remove('btn-outline-secondary');
    btn.classList.add('btn-success');
  } else {
    btn.classList.remove('btn-success');
    btn.classList.add('btn-outline-secondary');
  }
}

function DebugModal_destroySession() {
  fetch('//<?php echo Server::get('HTTP_HOST') . "{$us_url_root}usersc/plugins/debugmodal/files/destroy_session.php"; ?>')
  .then(response => response.json())
  .then(data => {
    if (data == 'success') {
      location.reload();
    } else {
      document.getElementById('DebugModal_session_destroy_parent').innerHTML += '<span class="text-danger ms-2">Error destroying session</span>';
    }
  });
}

function DebugModal_copy(id, btn) {
  var el = document.getElementById(id);
  if (!el) { return; }
  navigator.clipboard.writeText(el.textContent).then(function () {
    if (btn) {
      var html = btn.innerHTML;
      btn.innerHTML = '<i class="fa fa-check"></i>';
      setTimeout(function () { btn.innerHTML = html; }, 1200);
    }
  });
}
</script>
<?php } ?>
