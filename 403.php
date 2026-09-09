<?php
/**
 * Emerald Central Hub — 403 Forbidden page.
 * Rendered by core/config.php when the firewall blocks a client IP
 * (identical behaviour to V10).
 */
http_response_code(403);
header('Content-Type: text/html; charset=utf-8');
$__ip = function_exists('getRealIpAddr') ? getRealIpAddr() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>403 — Forbidden</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:#0b1019;color:#dbe4f3;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{max-width:460px;width:100%;background:#111a2a;border:1px solid #22304a;border-radius:16px;padding:40px 36px;text-align:center}
  .code{font-size:64px;font-weight:800;background:linear-gradient(135deg,#f87171,#fbbf24);-webkit-background-clip:text;background-clip:text;color:transparent;line-height:1.1}
  h1{font-size:20px;margin:14px 0 8px;color:#f1f5fb}
  p{color:#93a1bd;font-size:14px;line-height:1.7}
  code{display:inline-block;background:#0b111d;border:1px solid #26324a;border-radius:6px;padding:2px 8px;font-size:12px;color:#fca5a5;margin-top:14px}
  .shield{margin:0 auto 6px;width:52px;height:52px;border-radius:14px;background:#1c2740;display:flex;align-items:center;justify-content:center;font-size:26px}
</style>
</head>
<body>
  <div class="card">
    <div class="shield">🛡</div>
    <div class="code">403</div>
    <h1>Access Forbidden</h1>
    <p>Your IP address is not whitelisted in the firewall.<br>If this is a mistake, contact the owner to add it via the Emergency Access or the firewall panel.</p>
    <code><?php echo htmlspecialchars((string)$__ip, ENT_QUOTES, 'UTF-8'); ?></code>
  </div>
</body>
</html>
