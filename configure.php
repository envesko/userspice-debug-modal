<?php
// Debug Modal - admin config page (Envesko). admin.php?view=plugins_config&plugin=debugmodal
if (!in_array($user->data()->id, $master_account)) {
    die('You do not have permission to configure this plugin.');
}
global $db;
$selfUrl = $us_url_root . 'users/admin.php?view=plugins_config&plugin=debugmodal';

$fields = ['ShowSession', 'ShowPost', 'ShowGet', 'ShowCookies', 'ShowServer', 'ShowUser', 'ShowSystem', 'AutoOpen', 'RequireAdmin'];

if (Input::exists()) {
    if (!Token::check(Input::get('csrf'))) {
        usError('Invalid security token. Please refresh and try again.');
        Redirect::to($selfUrl);
    }
    if (Input::get('do') === 'save_settings') {
        if ($db->query('SELECT id FROM debugmodal_config WHERE id = 1')->count() == 0) {
            $db->insert('debugmodal_config', ['id' => 1]);
        }
        $update = [];
        foreach ($fields as $f) {
            $update[$f] = Input::get(strtolower($f)) ? 1 : 0;
        }
        $db->update('debugmodal_config', 1, $update);
        usSuccess('Debug Modal settings saved.');
        Redirect::to($selfUrl);
    }
}

$cfg = function_exists('debugModalConfig') ? debugModalConfig() : null;
$val = function ($key, $default) use ($cfg) {
    if (!$cfg) {
        return (bool) $default;
    }
    return isset($cfg->$key) ? ((int) $cfg->$key === 1) : (bool) $default;
};
$sections = [
    'ShowSession' => ['Session', 1],
    'ShowPost'    => ['POST', 1],
    'ShowGet'     => ['GET', 0],
    'ShowCookies' => ['Cookies', 0],
    'ShowServer'  => ['Server vars (sensitive keys stripped)', 0],
    'ShowUser'    => ['User data', 1],
    'ShowSystem'  => ['System info (PHP/UserSpice version, template, memory, plugin count)', 1],
];
?>
<style>
  .dm-hero { background:linear-gradient(135deg,#2ea5cb 0%,#2edcb7 100%); color:#fff; border-radius:.6rem; padding:1rem 1.25rem; margin-bottom:1rem; }
  .dm-hero h2 { color:#fff; margin:0; font-weight:600; }
  .dm-hero p { margin:.25rem 0 0; opacity:.95; }
  .dm-btn { background:linear-gradient(135deg,#2ea5cb 0%,#2edcb7 100%); border:0; color:#fff; }
  .dm-btn:hover { filter:brightness(1.07); color:#fff; }
  .dm-code { position:relative; }
  .dm-code .dm-copy { position:absolute; top:.3rem; right:.3rem; padding:.05rem .45rem; line-height:1.2; }
</style>

<div class="container-fluid">
  <div class="dm-hero">
    <h2><i class="fa fa-bug me-2"></i>Debug Modal <span style="font-weight:400;opacity:.85;font-size:1rem">by Envesko</span></h2>
    <p>A localhost-only debug panel in your footer. Choose what it shows below.</p>
  </div>

  <div class="row">
    <div class="col-12 col-lg-7 mb-4">
      <form method="post" action="<?= safeReturn($selfUrl) ?>">
        <?= tokenHere() ?><input type="hidden" name="do" value="save_settings">
        <div class="card mb-3">
          <div class="card-header"><i class="fa fa-list-check me-1"></i> Sections to show</div>
          <div class="card-body">
            <?php foreach ($sections as $key => $meta) { ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="<?= strtolower($key) ?>" id="<?= strtolower($key) ?>" value="1" <?= $val($key, $meta[1]) ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= strtolower($key) ?>"><?= safeReturn($meta[0]) ?></label>
              </div>
            <?php } ?>
            <p class="small text-muted mb-0 mt-2">Misc (paths) always shows. Add your own sections in code via <code>$GLOBALS['DebugModal_extra']</code>.</p>
          </div>
        </div>
        <div class="card mb-3">
          <div class="card-header"><i class="fa fa-gear me-1"></i> Behaviour</div>
          <div class="card-body">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="autoopen" id="autoopen" value="1" <?= $val('AutoOpen', 0) ? 'checked' : '' ?>>
              <label class="form-check-label" for="autoopen">Auto-open the modal on page load (default; each dev can still toggle it for themselves)</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="requireadmin" id="requireadmin" value="1" <?= $val('RequireAdmin', 0) ? 'checked' : '' ?>>
              <label class="form-check-label" for="requireadmin">Only show to admins (permission 2), in addition to the localhost requirement</label>
            </div>
          </div>
        </div>
        <button type="submit" class="btn dm-btn">Save settings</button>
      </form>
    </div>
    <div class="col-12 col-lg-5 mb-4">
      <div class="card mb-3">
        <div class="card-header"><i class="fa fa-code me-1"></i> For developers: add your own data</div>
        <div class="card-body">
          <p class="mb-2">Push anything into the modal as its own collapsible, copyable section. Two ways:</p>
          <p class="mb-1"><strong>1. Inline</strong> (anywhere before the page footer renders):</p>
          <div class="dm-code mb-3">
            <button type="button" class="btn btn-sm btn-light dm-copy" title="Copy" onclick="DM_copyCode('dmcode1', this)"><i class="fa fa-copy"></i></button>
<pre id="dmcode1" class="bg-light p-2 border rounded mb-0"><code>$GLOBALS['DebugModal_extra']['My Query'] = $rows;
$GLOBALS['DebugModal_extra']['Cart'] = $_SESSION['cart'] ?? [];</code></pre>
          </div>
          <p class="mb-1"><strong>2. Reusable file</strong> at <code>usersc/includes/debug_modal_custom.php</code>:</p>
          <div class="dm-code mb-2">
            <button type="button" class="btn btn-sm btn-light dm-copy" title="Copy" onclick="DM_copyCode('dmcode2', this)"><i class="fa fa-copy"></i></button>
<pre id="dmcode2" class="bg-light p-2 border rounded mb-0"><code>&lt;?php
$GLOBALS['DebugModal_extra']['Feature flags'] = getMyFlags();</code></pre>
          </div>
          <p class="mb-0 text-muted small">Each entry becomes a labelled section with its own Copy button. Values are dumped with <code>dump()</code> and JSON-copied. The label is the array key.</p>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><i class="fa fa-shield-halved me-1"></i> Safety</div>
        <div class="card-body">
          <p class="mb-2">The modal only ever renders on <strong>localhost</strong>. Server vars and cookies can expose sensitive data, so they are off by default and sensitive server keys are stripped.</p>
          <p class="mb-0 text-muted small">Never enable this plugin on a public host.</p>
        </div>
      </div>
    </div>
  </div>
</div>
<script nonce="<?= htmlspecialchars($userspice_nonce ?? '') ?>">
function DM_copyCode(id, btn) {
  var el = document.getElementById(id);
  if (!el) { return; }
  navigator.clipboard.writeText(el.innerText).then(function () {
    if (btn) {
      var html = btn.innerHTML;
      btn.innerHTML = '<i class="fa fa-check"></i>';
      setTimeout(function () { btn.innerHTML = html; }, 1200);
    }
  });
}
</script>
