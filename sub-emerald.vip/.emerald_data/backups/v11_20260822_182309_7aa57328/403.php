<?php
/**
 * Emerald Central Hub — 403 Secure Access Gateway
 * Premium WebGL containment-field visualization (Three.js + IBL + ACES + bloom).
 */
$detected_ip = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? 'UNKNOWN';
$detected_ip = trim(explode(',', $detected_ip)[0]);
$ip_safe = htmlspecialchars($detected_ip, ENT_QUOTES, 'UTF-8'); // header bisa dipalsukan -> escape
$checked = gmdate('Y-m-d H:i:s') . ' UTC';
$ref_id  = strtoupper(substr(hash('sha256', $detected_ip . date('Ymd')), 0, 12));
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Akses Ditolak · Emerald Central Hub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#04070a; --surface:#0a120f; --surface-2:#0d1714;
    --line:rgba(120,180,160,.11); --line-strong:rgba(120,180,160,.22);
    --emerald:#34d399; --emerald-deep:#10b981; --emerald-soft:#9bf6d4; --deny:#f0596b;
    --text:#eaf3ef; --muted:#8a9b94; --faint:#566;
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0;background:var(--bg);color:var(--text);
    font-family:"Space Grotesk",system-ui,sans-serif;-webkit-font-smoothing:antialiased;
    display:flex;align-items:center;justify-content:center;padding:24px;
    position:relative;overflow:hidden;
  }
  body::before,body::after{content:"";position:fixed;border-radius:50%;filter:blur(150px);z-index:0;pointer-events:none}
  body::before{width:760px;height:760px;top:-300px;left:-240px;background:radial-gradient(circle,rgba(16,185,129,.14),transparent 66%)}
  body::after{width:620px;height:620px;bottom:-320px;right:-220px;background:radial-gradient(circle,rgba(240,89,107,.07),transparent 66%)}

  .gate{
    position:relative;z-index:1;width:min(1080px,96vw);
    background:linear-gradient(160deg,var(--surface),#070d0b);
    border:1px solid var(--line);border-radius:26px;
    box-shadow:0 1px 0 rgba(255,255,255,.04) inset,0 70px 130px -50px rgba(0,0,0,.94);
    overflow:hidden;display:flex;flex-direction:column;
    animation:rise .75s cubic-bezier(.2,.7,.2,1) both;
  }
  @keyframes rise{from{opacity:0;transform:translateY(20px) scale(.99)}to{opacity:1;transform:none}}

  .bar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 28px;border-bottom:1px solid var(--line);background:rgba(255,255,255,.012);flex:none}
  .brand{display:flex;align-items:center;gap:12px;min-width:0}
  .mark{width:34px;height:34px;flex:none;display:grid;place-items:center;border-radius:10px;background:linear-gradient(145deg,var(--emerald-deep),#0c5a44);box-shadow:0 0 0 1px rgba(52,211,153,.35),0 6px 18px -6px rgba(16,185,129,.6)}
  .mark svg{width:18px;height:18px}
  .brand-name{font-weight:600;font-size:14px;letter-spacing:.14em;text-transform:uppercase}
  .brand-sub{font-size:11px;color:var(--faint);letter-spacing:.16em;text-transform:uppercase;margin-top:1px}
  .pill{display:inline-flex;align-items:center;gap:8px;flex:none;font-family:"JetBrains Mono",monospace;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--emerald);padding:6px 11px;border-radius:999px;border:1px solid rgba(52,211,153,.25);background:rgba(52,211,153,.06)}
  .pill .dot{width:7px;height:7px;border-radius:50%;background:var(--emerald);animation:beat 2.6s ease-out infinite}
  @keyframes beat{0%{box-shadow:0 0 0 0 rgba(52,211,153,.45)}70%{box-shadow:0 0 0 9px rgba(52,211,153,0)}100%{box-shadow:0 0 0 0 rgba(52,211,153,0)}}

  .core-grid{display:grid;grid-template-columns:54% 46%;flex:1;min-height:0}

  /* ---- Panggung 3D ---- */
  .stage{position:relative;border-right:1px solid var(--line);min-height:580px;overflow:hidden}
  #scene{position:absolute;inset:0;width:100%;height:100%;display:block;cursor:grab;touch-action:none}
  #scene:active{cursor:grabbing}
  /* vignette agar scene terasa "terkurung" dan premium */
  .stage::after{content:"";position:absolute;inset:0;pointer-events:none;z-index:2;
    background:radial-gradient(110% 110% at 50% 45%,transparent 55%,rgba(4,7,10,.65) 100%)}

  .hud{position:absolute;inset:0;pointer-events:none;z-index:3;font-family:"JetBrains Mono",monospace}
  .hud .tl{position:absolute;top:18px;left:22px;display:flex;align-items:center;gap:9px;font-size:10px;letter-spacing:.2em;text-transform:uppercase;color:var(--emerald)}
  .hud .tl .live{width:6px;height:6px;border-radius:50%;background:var(--emerald);box-shadow:0 0 10px 2px rgba(52,211,153,.7);animation:beat 2.2s ease-out infinite}
  .hud .br{position:absolute;bottom:18px;right:22px;text-align:right}
  .hud .br .n{font-size:13px;font-weight:700;color:var(--deny);letter-spacing:.02em}
  .hud .br .l{font-size:8.5px;letter-spacing:.16em;text-transform:uppercase;color:var(--faint);margin-top:3px}
  .hud .bl{position:absolute;left:22px;bottom:18px;display:flex;gap:7px}
  .hud .key{display:inline-flex;align-items:center;gap:6px;padding:4px 9px;border:1px solid var(--line-strong);border-radius:7px;background:rgba(0,0,0,.32);font-size:9.5px;letter-spacing:.04em;color:var(--faint)}
  .hud .key b{color:var(--muted);font-weight:500}

  .loader{position:absolute;inset:0;display:grid;place-items:center;z-index:4;background:#070d0b;transition:opacity .7s ease}
  .loader.hide{opacity:0;pointer-events:none}
  .spinner{width:42px;height:42px;border-radius:50%;border:2px solid rgba(52,211,153,.16);border-top-color:var(--emerald);animation:spin .9s linear infinite}
  @keyframes spin{to{transform:rotate(360deg)}}
  .loader p{position:absolute;bottom:30px;font-size:9.5px;letter-spacing:.22em;text-transform:uppercase;color:var(--faint);font-family:"JetBrains Mono",monospace}

  .panel{display:flex;flex-direction:column;justify-content:center;gap:18px;padding:42px 44px}
  .chip{display:inline-flex;align-items:center;gap:9px;align-self:flex-start;font-family:"JetBrains Mono",monospace;font-size:12px;font-weight:500;letter-spacing:.16em;text-transform:uppercase;color:var(--deny);padding:7px 13px;border-radius:999px;border:1px solid rgba(240,89,107,.30);background:rgba(240,89,107,.07)}
  .chip svg{width:13px;height:13px}
  h1{margin:0;font-size:clamp(27px,2.9vw,39px);line-height:1.1;font-weight:700;letter-spacing:-.022em}
  h1 em{font-style:normal;color:var(--emerald)}
  .lede{margin:0;max-width:46ch;color:var(--muted);font-size:14.5px;line-height:1.62}

  .diag{border:1px solid var(--line);border-radius:14px;background:var(--surface-2);overflow:hidden}
  .row{display:grid;grid-template-columns:118px 1fr auto;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--line);transition:background .18s ease}
  .row:last-child{border-bottom:0}
  .row:hover{background:rgba(255,255,255,.02)}
  .row .k{font-family:"JetBrains Mono",monospace;font-size:11.5px;color:var(--faint)}
  .row .v{font-family:"JetBrains Mono",monospace;font-size:13.5px;color:var(--text);word-break:break-all}
  .row .v.ip{color:#fff;font-weight:500;font-size:14.5px}
  .tag{font-family:"JetBrains Mono",monospace;font-size:10px;letter-spacing:.08em;text-transform:uppercase;padding:4px 9px;border-radius:6px;white-space:nowrap}
  .tag.deny{color:var(--deny);background:rgba(240,89,107,.10);border:1px solid rgba(240,89,107,.28)}
  .tag.ok{color:var(--emerald);background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.25)}

  .actions{display:flex;flex-wrap:wrap;gap:12px}
  .btn{display:inline-flex;align-items:center;gap:9px;font-family:inherit;font-size:14px;font-weight:600;padding:12px 20px;border-radius:11px;text-decoration:none;cursor:pointer;border:1px solid transparent;transition:transform .15s ease,background .2s ease,border-color .2s ease,box-shadow .2s ease}
  .btn svg{width:16px;height:16px}
  .btn.primary{color:#04140e;background:linear-gradient(180deg,var(--emerald),var(--emerald-deep));box-shadow:0 10px 26px -12px rgba(16,185,129,.8)}
  .btn.primary:hover{transform:translateY(-1px);box-shadow:0 14px 30px -12px rgba(16,185,129,.9)}
  .btn.ghost{color:var(--text);border-color:var(--line-strong);background:rgba(255,255,255,.02)}
  .btn.ghost:hover{border-color:rgba(120,180,160,.4);background:rgba(255,255,255,.05)}
  .btn:focus-visible{outline:2px solid var(--emerald);outline-offset:3px}

  .foot{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;padding:14px 28px;border-top:1px solid var(--line);background:rgba(0,0,0,.25);flex:none;font-family:"JetBrains Mono",monospace;font-size:11px;color:var(--faint);letter-spacing:.04em}
  .foot .ref{color:var(--muted)}

  @media (max-width:820px){
    .core-grid{grid-template-columns:1fr}
    .stage{border-right:0;border-bottom:1px solid var(--line);min-height:330px}
    .panel{padding:30px 26px}
    .brand-sub{display:none}
    .hud .bl{display:none}
    .actions .btn{flex:1 1 100%;justify-content:center}
    .row{grid-template-columns:1fr;gap:5px}.row .tag{justify-self:start}
  }
  @media (prefers-reduced-motion:reduce){.spinner{animation:none}}
</style>
</head>
<body>
  <main class="gate" role="alertdialog" aria-labelledby="title" aria-describedby="lede">

    <header class="bar">
      <div class="brand">
        <span class="mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#04140e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 3 6v6c0 5 3.8 8.4 9 10 5.2-1.6 9-5 9-10V6l-9-4Z"/></svg></span>
        <div><div class="brand-name">Emerald Central Hub</div><div class="brand-sub">Secure Access Gateway</div></div>
      </div>
      <span class="pill"><span class="dot"></span> Gateway Online</span>
    </header>

    <div class="core-grid">
      <div class="stage">
        <canvas id="scene" aria-label="Visualisasi medan firewall 3D interaktif"></canvas>
        <div class="hud" aria-hidden="true">
          <div class="tl"><span class="live"></span> Containment Field · Active</div>
          <div class="bl">
            <span class="key"><b>Tarik</b> putar</span>
            <span class="key"><b>Klik</b> uji firewall</span>
          </div>
          <div class="br"><div class="n" id="blocked">0</div><div class="l">Upaya Diblokir</div></div>
        </div>
        <div class="loader" id="loader"><div class="spinner"></div><p>Menyiapkan gateway…</p></div>
      </div>

      <div class="panel">
        <span class="chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg> 403 · Akses Ditolak</span>
        <h1 id="title">Koneksimu <em>belum terdaftar</em> di daftar izin.</h1>
        <p class="lede" id="lede">Medan firewall Emerald hanya meneruskan koneksi yang disetujui. Coba klik kubah di kiri — paketmu akan dipantulkan. Kirim ID referensi di bawah ke administrator untuk meminta akses.</p>

        <div class="diag" aria-label="Diagnostik koneksi">
          <div class="row"><span class="k">Alamat IP</span><span class="v ip"><?= $ip_safe ?></span><span class="tag deny">Tidak diizinkan</span></div>
          <div class="row"><span class="k">Status</span><span class="v">403 — firewall reject</span><span class="tag deny">Diblokir</span></div>
          <div class="row"><span class="k">Diperiksa</span><span class="v"><?= $checked ?></span><span class="tag ok">Gateway aktif</span></div>
        </div>

        <div class="actions">
          <a class="btn primary" href="https://t.me/liejunxi" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M21.9 4.3 18.5 20c-.3 1.1-.9 1.4-1.9.9l-5.1-3.8-2.5 2.4c-.3.3-.5.5-1 .5l.4-5.2 9.4-8.5c.4-.4-.1-.6-.6-.2L5.1 13.2 0 11.6c-1.1-.3-1.1-1 .2-1.5L20.6 2.3c.9-.3 1.7.2 1.3 2Z"/></svg> Minta akses via Telegram</a>
          <a class="btn ghost" href="javascript:history.back()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Kembali</a>
        </div>
      </div>
    </div>

    <footer class="foot">
      <span>Administrator · <span class="ref">@liejunxi</span></span>
      <span>REF&nbsp;<span class="ref"><?= $ref_id ?></span></span>
    </footer>
  </main>

  <script type="importmap">
  {"imports":{
    "three":"https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
    "three/addons/":"https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"
  }}
  </script>

  <script type="module">
  import * as THREE from 'three';
  import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
  import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
  import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
  import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
  import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';
  import { OutputPass } from 'three/addons/postprocessing/OutputPass.js';

  const REDUCED = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const canvas = document.getElementById('scene');
  const stage  = canvas.parentElement;
  const loader = document.getElementById('loader');
  const blockedEl = document.getElementById('blocked');
  const SHIELD_R = 2.2;

  // ---------- Renderer: tone-mapping sinematik ----------
  const renderer = new THREE.WebGLRenderer({ canvas, antialias:true, alpha:true });
  renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.05;
  renderer.setClearColor(0x000000, 0);

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(42, 1, 0.1, 100);
  camera.position.set(0.4, 0.9, 6.4);

  // ---------- IBL studio (kunci tampilan premium) ----------
  const pmrem = new THREE.PMREMGenerator(renderer);
  scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;

  const controls = new OrbitControls(camera, canvas);
  Object.assign(controls, { enableDamping:true, dampingFactor:0.05, enablePan:false,
    minDistance:4.2, maxDistance:9.5, autoRotate:!REDUCED, autoRotateSpeed:0.55 });
  controls.target.set(0,0,0);

  // ---------- Cahaya aksen ----------
  const key = new THREE.PointLight(0x46e6ad, 22, 30); key.position.set(4,4,5); scene.add(key);
  const rim = new THREE.PointLight(0x0c6b50, 14, 30); rim.position.set(-5,-2,-4); scene.add(rim);

  // ---------- Inti permata emerald (faceted, reflektif) ----------
  const coreGroup = new THREE.Group(); scene.add(coreGroup);
  const coreMat = new THREE.MeshPhysicalMaterial({
    color:0x0f5c46, metalness:0.9, roughness:0.14,
    emissive:0x2fe3a6, emissiveIntensity:0.55,
    clearcoat:1.0, clearcoatRoughness:0.18, envMapIntensity:1.25, flatShading:true
  });
  const core = new THREE.Mesh(new THREE.IcosahedronGeometry(1, 0), coreMat); coreGroup.add(core);
  // sangkar wireframe halus
  const cage = new THREE.LineSegments(
    new THREE.WireframeGeometry(new THREE.IcosahedronGeometry(1.18, 1)),
    new THREE.LineBasicMaterial({ color:0x6ee7b7, transparent:true, opacity:0.18 }));
  coreGroup.add(cage);
  // glow inti
  const coreGlow = new THREE.Mesh(new THREE.SphereGeometry(1.35,32,32),
    new THREE.MeshBasicMaterial({ color:0x10b981, transparent:true, opacity:0.07, blending:THREE.AdditiveBlending, depthWrite:false }));
  coreGroup.add(coreGlow);

  // ---------- Medan firewall: geodesik bersih (bukan noise) ----------
  const shieldGeo = new THREE.IcosahedronGeometry(SHIELD_R, 2);
  const edgeMat = new THREE.LineBasicMaterial({ color:0x2fe3a6, transparent:true, opacity:0.16 });
  const shieldEdges = new THREE.LineSegments(new THREE.WireframeGeometry(shieldGeo), edgeMat);
  scene.add(shieldEdges);
  const shellMat = new THREE.MeshBasicMaterial({ color:0x10b981, transparent:true, opacity:0.022, side:THREE.BackSide, blending:THREE.AdditiveBlending, depthWrite:false });
  const shieldShell = new THREE.Mesh(shieldGeo, shellMat); scene.add(shieldShell);
  // simpul bercahaya di titik medan
  const vGeo = new THREE.BufferGeometry();
  vGeo.setAttribute('position', new THREE.BufferAttribute(shieldGeo.attributes.position.array.slice(), 3));
  const shieldNodes = new THREE.Points(vGeo, new THREE.PointsMaterial({ color:0x9bf6d4, size:0.05, transparent:true, opacity:0.85, depthWrite:false }));
  scene.add(shieldNodes);

  // ---------- Cincin armillary (instrumen) ----------
  const ringDefs = [
    { r:2.55, tube:0.008, tilt:[0.5,0.2,0], spin:0.18, beadSpeed:0.7 },
    { r:2.78, tube:0.007, tilt:[-0.9,0.6,0.3], spin:-0.13, beadSpeed:-0.55 },
    { r:2.95, tube:0.006, tilt:[0.3,-0.8,0.5], spin:0.1, beadSpeed:0.4 },
  ];
  const rings = ringDefs.map(d=>{
    const g = new THREE.Group(); g.rotation.set(d.tilt[0], d.tilt[1], d.tilt[2]); scene.add(g);
    const ring = new THREE.Mesh(new THREE.TorusGeometry(d.r, d.tube, 12, 220),
      new THREE.MeshStandardMaterial({ color:0x063b2c, emissive:0x34d399, emissiveIntensity:1.5, metalness:0.6, roughness:0.4 }));
    g.add(ring);
    const bead = new THREE.Mesh(new THREE.SphereGeometry(0.035,16,16), new THREE.MeshBasicMaterial({ color:0xcafff0 }));
    g.add(bead);
    return { group:g, bead, def:d, a:Math.random()*Math.PI*2 };
  });

  // ---------- Partikel ambient ----------
  (function(){
    const N=420, g=new THREE.BufferGeometry(), pos=new Float32Array(N*3);
    for(let i=0;i<N;i++){ const r=5+Math.random()*16, t=Math.random()*6.28, p=Math.acos(2*Math.random()-1);
      pos[i*3]=r*Math.sin(p)*Math.cos(t); pos[i*3+1]=r*Math.cos(p); pos[i*3+2]=r*Math.sin(p)*Math.sin(t); }
    g.setAttribute('position', new THREE.BufferAttribute(pos,3));
    const pts = new THREE.Points(g, new THREE.PointsMaterial({ color:0x2f6f59, size:0.04, transparent:true, opacity:0.6, depthWrite:false }));
    scene.add(pts); scene.userData.dust = pts;
  })();

  // ---------- Probe penyusup (merah) ----------
  const probes = [];
  const probeMat = new THREE.MeshBasicMaterial({ color:0xff5d6e });
  const haloMat  = new THREE.MeshBasicMaterial({ color:0xff5d6e, transparent:true, opacity:0.35, blending:THREE.AdditiveBlending, depthWrite:false });
  function spawnProbe(dir){
    dir = (dir || new THREE.Vector3(Math.random()*2-1, Math.random()*2-1, Math.random()*2-1)).normalize();
    const m = new THREE.Mesh(new THREE.SphereGeometry(0.08,14,14), probeMat.clone());
    m.position.copy(dir.clone().multiplyScalar(7.5));
    m.add(new THREE.Mesh(new THREE.SphereGeometry(0.2,14,14), haloMat.clone()));
    scene.add(m);
    probes.push({ m, vel: dir.clone().multiplyScalar(-(2.4+Math.random()*1.0)), state:'in', life:0 });
  }

  // ---------- Riak benturan + kilatan medan ----------
  const ripples = [];
  function spawnRipple(at){
    const ring = new THREE.Mesh(new THREE.RingGeometry(0.02,0.06,48),
      new THREE.MeshBasicMaterial({ color:0xff8d9a, transparent:true, opacity:0.95, side:THREE.DoubleSide, blending:THREE.AdditiveBlending, depthWrite:false }));
    ring.position.copy(at); ring.lookAt(at.clone().multiplyScalar(2));
    scene.add(ring); ripples.push({ m:ring, life:0 });
  }
  let flash=0, blocked=0;
  function onBlocked(hit){
    blocked++; blockedEl.textContent = blocked;
    flash = 1; spawnRipple(hit.clone().normalize().multiplyScalar(SHIELD_R));
  }

  // ---------- Post-processing: bloom halus ----------
  const composer = new EffectComposer(renderer);
  composer.addPass(new RenderPass(scene, camera));
  const bloom = new UnrealBloomPass(new THREE.Vector2(1,1), 0.55, 0.5, 0.22);
  composer.addPass(bloom);
  composer.addPass(new OutputPass());

  // ---------- Interaksi klik ----------
  const ray = new THREE.Raycaster(); const ptr = new THREE.Vector2(); let down=null;
  canvas.addEventListener('pointerdown', e=> down=[e.clientX,e.clientY]);
  canvas.addEventListener('pointerup', e=>{
    if(!down) return; const moved=Math.hypot(e.clientX-down[0], e.clientY-down[1]); down=null;
    if(moved>6) return;
    const r=canvas.getBoundingClientRect();
    ptr.set(((e.clientX-r.left)/r.width)*2-1, -((e.clientY-r.top)/r.height)*2+1);
    ray.setFromCamera(ptr, camera);
    spawnProbe(ray.ray.direction.clone().multiplyScalar(-1));
  });

  // ---------- Resize ----------
  function resize(){ const w=stage.clientWidth,h=stage.clientHeight;
    renderer.setSize(w,h,false); composer.setSize(w,h); bloom.setSize(w,h);
    camera.aspect=w/h; camera.updateProjectionMatrix(); }
  new ResizeObserver(resize).observe(stage); resize();

  // ---------- Loop ----------
  const clock=new THREE.Clock(); let spawnT=0;
  function tick(){
    const dt=Math.min(clock.getDelta(),0.05), t=clock.elapsedTime;

    if(!REDUCED){
      coreGroup.rotation.y += dt*0.22; coreGroup.rotation.x += dt*0.06;
      cage.rotation.y -= dt*0.35;
      core.scale.setScalar(1+Math.sin(t*1.8)*0.03);
      coreMat.emissiveIntensity = 0.5+Math.sin(t*1.8)*0.12;
      shieldEdges.rotation.y += dt*0.04; shieldShell.rotation.y += dt*0.04; shieldNodes.rotation.y += dt*0.04;
      if(scene.userData.dust) scene.userData.dust.rotation.y += dt*0.01;
      rings.forEach(r=>{ r.group.rotation.z += dt*r.def.spin*0.4; });
      spawnT+=dt; if(spawnT>3.0){ spawnT=0; spawnProbe(); }
    }

    // bead mengorbit di tiap cincin
    rings.forEach(r=>{ r.a += dt*r.def.beadSpeed; r.bead.position.set(Math.cos(r.a)*r.def.r, Math.sin(r.a)*r.def.r, 0); });

    // kilatan medan meredup
    flash = Math.max(0, flash - dt*3.2);
    edgeMat.opacity = 0.16 + flash*0.5;
    edgeMat.color.setHex(0x2fe3a6).lerp(new THREE.Color(0xff8d9a), flash*0.7);
    shellMat.opacity = 0.022 + flash*0.06;

    // probe
    for(let i=probes.length-1;i>=0;i--){
      const p=probes[i]; p.life+=dt; p.m.position.addScaledVector(p.vel, dt);
      const d=p.m.position.length();
      if(p.state==='in' && d<=SHIELD_R+0.05){
        const hit=p.m.position.clone(); onBlocked(hit);
        p.vel.reflect(hit.clone().normalize()).multiplyScalar(0.5); p.state='out';
      }
      if(p.state==='out'){
        p.m.material.opacity = 1; const sc=Math.max(0.01, 1-(d-SHIELD_R)*0.45); p.m.scale.setScalar(sc);
        if(d>8.5){ scene.remove(p.m); probes.splice(i,1); continue; }
      }
      if(p.life>7){ scene.remove(p.m); probes.splice(i,1); }
    }

    // riak
    for(let i=ripples.length-1;i>=0;i--){
      const r=ripples[i]; r.life+=dt; r.m.scale.setScalar(1+r.life*7.5);
      r.m.material.opacity=Math.max(0,0.95-r.life*1.7);
      if(r.life>0.6){ scene.remove(r.m); ripples.splice(i,1); }
    }

    controls.update(); composer.render(); requestAnimationFrame(tick);
  }

  function start(){ resize(); composer.render(); loader.classList.add('hide'); tick(); }
  requestAnimationFrame(()=>requestAnimationFrame(start));
  canvas.addEventListener('webglcontextlost', e=>e.preventDefault());
  </script>

  <noscript><div style="position:fixed;inset:0;display:grid;place-items:center;color:#8a9b94;font-family:monospace;background:#04070a">403 — Akses ditolak. Aktifkan JavaScript untuk tampilan penuh. Hubungi @liejunxi.</div></noscript>
</body>
</html>