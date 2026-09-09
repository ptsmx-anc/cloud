<!DOCTYPE html>
<html lang="en" data-theme="dark" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Terminal Authentication</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], mono: ['Fira Code', 'monospace'] },
                    colors: { primary: 'var(--text-primary)', muted: 'var(--text-muted)' }
                }
            }
        }
    </script>
    <style>
        :root { --bg-base: #f8fafc; --bg-panel: rgba(255, 255, 255, 0.85); --text-primary: #0f172a; --text-muted: #64748b; --border: rgba(15, 23, 42, 0.1); --input-bg: rgba(255,255,255,0.95); }
        html.dark { --bg-base: #030712; --bg-panel: rgba(15, 23, 42, 0.6); --text-primary: #ffffff; --text-muted: #9ca3af; --border: rgba(255,255,255,0.1); --input-bg: rgba(0,0,0,0.6); }
        
        body { background-color: var(--bg-base); color: var(--text-primary); transition: background-color 0.5s ease, color 0.5s ease; overflow: hidden; margin: 0; }
        
        #particleCanvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; }
        
        .glass-panel { background: var(--bg-panel); backdrop-filter: blur(40px); -webkit-backdrop-filter: blur(40px); border: 1px solid var(--border); }
        .input-auth { background: var(--input-bg); border: 1px solid var(--border); color: var(--text-primary); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-family: 'Fira Code', monospace; }
        .input-auth:focus { border-color: #0ea5e9; box-shadow: 0 0 0 4px rgba(14,165,233,0.15); outline: none; transform: translateY(-2px); }
        
        .btn-gradient { background: linear-gradient(135deg, #0ea5e9, #38bdf8, #8b5cf6, #0ea5e9); background-size: 300% 300%; transition: all 0.5s ease; color: white; border: none; animation: gradientShift 5s ease infinite; }
        .btn-gradient:hover { box-shadow: 0 10px 30px -5px rgba(14,165,233,0.6); transform: translateY(-3px) scale(1.02); }
        .btn-gradient:active { transform: translateY(1px) scale(0.98); }
        
        @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        @keyframes floatUp { 0% { opacity: 0; transform: translateY(40px) scale(0.95); } 100% { opacity: 1; transform: translateY(0) scale(1); } }
        
        .animate-float-up { animation: floatUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .view-frame { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        
        .theme-toggle-btn { transition: all 0.3s ease; }
        html:not(.dark) .theme-toggle-btn { background-color: #0ea5e9; }
    </style>
</head>
<body class="h-screen w-full flex items-center justify-center relative selection:bg-[#0ea5e9] selection:text-white">
    <script>
        if (localStorage.getItem('emerald_theme') === 'light') { document.documentElement.classList.remove('dark'); document.documentElement.setAttribute('data-theme', 'light'); }
    </script>

    <canvas id="particleCanvas"></canvas>

    <div class="glass-panel p-10 rounded-[2.5rem] w-full max-w-[420px] shadow-[0_20px_60px_-15px_rgba(0,0,0,0.5)] relative z-10 animate-float-up overflow-hidden">
        
        <div id="loginView" class="view-frame block">
            <div class="text-center mb-10 relative">
                <div class="absolute inset-0 bg-[#0ea5e9]/20 blur-[50px] rounded-full z-0"></div>
                <div class="w-24 h-24 bg-gradient-to-br from-[#0ea5e9] to-[#0284c7] rounded-[2rem] mx-auto flex items-center justify-center shadow-[0_0_40px_rgba(14,165,233,0.5)] mb-6 transform hover:rotate-12 transition-all duration-500 relative z-10">
                    <i class="fa-solid fa-gem text-5xl text-white drop-shadow-lg"></i>
                </div>
                <h1 class="text-4xl font-extrabold tracking-tight text-primary relative z-10">EMERALD</h1>
                <p class="text-[11px] text-[#0ea5e9] font-mono mt-2 tracking-[0.4em] uppercase font-bold relative z-10">System Access</p>
            </div>
            
            <form onsubmit="handleLogin(event)" class="space-y-5">
                <div class="relative group">
                    <div class="absolute left-4 top-1/2 -translate-y-1/2 w-8 flex justify-center text-muted group-focus-within:text-[#0ea5e9] transition-colors"><i class="fa-solid fa-user-shield"></i></div>
                    <input type="text" id="login_user" class="w-full input-auth rounded-2xl py-4 pl-14 pr-4 tracking-wider text-sm font-semibold" placeholder="IDENTITY ID" required autocomplete="off">
                </div>
                <div class="relative group">
                    <div class="absolute left-4 top-1/2 -translate-y-1/2 w-8 flex justify-center text-muted group-focus-within:text-[#0ea5e9] transition-colors"><i class="fa-solid fa-lock"></i></div>
                    <input type="password" id="login_pass" class="w-full input-auth rounded-2xl py-4 pl-14 pr-4 tracking-wider text-sm font-semibold" placeholder="PASSPHRASE" required>
                </div>
                <button type="submit" class="w-full btn-gradient py-4 rounded-2xl font-bold uppercase tracking-widest mt-4 flex items-center justify-center gap-3 shadow-lg">
                    Authenticate <i class="fa-solid fa-bolt"></i>
                </button>
            </form>
            
            <div class="mt-8 flex items-center justify-between px-2 pt-6 border-t border-[var(--border)]">
                <div class="flex items-center gap-3 cursor-pointer group" onclick="toggleTheme()">
                    <div class="w-10 h-5 bg-gray-600 rounded-full flex items-center px-1 theme-toggle-btn relative">
                        <div class="w-3.5 h-3.5 bg-white rounded-full transition-all duration-300 transform" id="themeCircle"></div>
                    </div>
                    <span class="text-[10px] text-muted font-bold uppercase tracking-widest group-hover:text-[#0ea5e9] transition-colors">Theme</span>
                </div>
                <button onclick="toggleView('resetView')" class="text-[10px] text-muted hover:text-[#0ea5e9] font-bold uppercase tracking-widest transition-colors flex items-center gap-1"><i class="fa-solid fa-rotate-right"></i> Recover</button>
            </div>
        </div>

        <div id="resetView" class="view-frame hidden">
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-[1.5rem] mx-auto flex items-center justify-center shadow-[0_0_30px_rgba(168,85,247,0.4)] mb-5">
                    <i class="fa-solid fa-fingerprint text-4xl text-white"></i>
                </div>
                <h2 class="text-2xl font-extrabold tracking-tight text-primary">Identity Recovery</h2>
            </div>
            
            <div id="step1" class="space-y-6 block">
                <p class="text-[11px] text-muted text-center font-mono uppercase tracking-widest font-bold mb-2">Initialize Recovery Protocol</p>
                <input type="text" id="reset_user" class="w-full input-auth rounded-2xl p-4 text-center font-bold tracking-widest text-sm" placeholder="ENTER USERNAME" required>
                <button onclick="fetchQuestion()" class="w-full bg-purple-600 hover:bg-purple-500 text-white py-4 rounded-2xl font-bold uppercase tracking-widest transition-all shadow-[0_10px_20px_rgba(168,85,247,0.3)] hover:-translate-y-1 text-sm flex items-center justify-center gap-2"><i class="fa-solid fa-magnifying-glass"></i> Verify Node</button>
                <div class="text-center pt-4 border-t border-[var(--border)]"><button onclick="toggleView('loginView')" class="text-[10px] text-muted hover:text-purple-500 font-bold uppercase tracking-widest transition-colors">Abort Protocol</button></div>
            </div>

            <div id="step2" class="space-y-5 hidden">
                <div class="bg-[var(--input-bg)] border border-[var(--border)] rounded-2xl p-5 text-center shadow-inner">
                    <p class="text-[9px] text-[#0ea5e9] font-mono mb-2 uppercase tracking-[0.2em] font-bold"><i class="fa-solid fa-circle-question mr-1"></i> Security Question</p>
                    <p id="sec_q_display" class="font-extrabold text-base text-primary"></p>
                </div>
                <input type="text" id="reset_answer" class="w-full input-auth rounded-xl p-4 text-center font-bold text-sm tracking-wide" placeholder="Secret Answer" required>
                <input type="password" id="reset_new_pass" class="w-full input-auth rounded-xl p-4 text-center font-bold text-sm tracking-wide" placeholder="New Passphrase" required>
                <button onclick="executeReset()" class="w-full bg-emerald-500 hover:bg-emerald-400 text-white py-4 rounded-2xl font-bold uppercase tracking-widest transition-all shadow-[0_10px_20px_rgba(16,185,129,0.3)] hover:-translate-y-1 text-sm mt-4 flex items-center justify-center gap-2"><i class="fa-solid fa-check-double"></i> Overwrite</button>
                <div class="text-center pt-4 border-t border-[var(--border)]"><button onclick="toggleView('loginView')" class="text-[10px] text-muted hover:text-emerald-500 font-bold uppercase tracking-widest transition-colors">Cancel Override</button></div>
            </div>
        </div>

    </div>

<script>
    // Particle Engine for Fire Embers Effect
    const canvas = document.getElementById('particleCanvas');
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth; canvas.height = window.innerHeight;
    let particles = [];

    class Particle {
        constructor() {
            this.x = Math.random() * canvas.width;
            this.y = canvas.height + Math.random() * 200;
            this.size = Math.random() * 3 + 0.5;
            this.speedY = Math.random() * 2.5 + 1;
            this.speedX = (Math.random() - 0.5) * 1.5;
            this.opacity = Math.random() * 0.8 + 0.2;
            this.sway = Math.random() * Math.PI * 2;
        }
        update() {
            this.y -= this.speedY;
            this.x += Math.sin(this.sway) * 0.5 + this.speedX;
            this.sway += 0.04;
            if (this.opacity > 0) this.opacity -= 0.004;
            
            if (this.y < -10 || this.opacity <= 0) {
                this.y = canvas.height + 10;
                this.x = Math.random() * canvas.width;
                this.opacity = Math.random() * 0.8 + 0.2;
            }
        }
        draw() {
            const isDark = document.documentElement.classList.contains('dark');
            ctx.fillStyle = isDark ? `rgba(255, 255, 255, ${this.opacity})` : `rgba(0, 0, 0, ${this.opacity})`;
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    function initParticles() {
        particles = [];
        const particleCount = window.innerWidth < 768 ? 50 : 150;
        for (let i = 0; i < particleCount; i++) { particles.push(new Particle()); }
    }

    function animateParticles() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach(p => { p.update(); p.draw(); });
        requestAnimationFrame(animateParticles);
    }

    initParticles(); animateParticles();
    window.addEventListener('resize', () => { canvas.width = window.innerWidth; canvas.height = window.innerHeight; initParticles(); });

    // UI Logic
    function updateThemeUI() {
        const circle = document.getElementById('themeCircle');
        if (document.documentElement.classList.contains('dark')) {
            circle.style.transform = 'translateX(0px)';
        } else {
            circle.style.transform = 'translateX(20px)';
        }
    }
    
    document.addEventListener('DOMContentLoaded', updateThemeUI);

    function toggleTheme() {
        const root = document.documentElement;
        if (root.classList.contains('dark')) {
            root.classList.remove('dark'); root.setAttribute('data-theme', 'light'); localStorage.setItem('emerald_theme', 'light');
        } else {
            root.classList.add('dark'); root.setAttribute('data-theme', 'dark'); localStorage.setItem('emerald_theme', 'dark');
        }
        updateThemeUI();
    }

    function toggleView(view) {
        document.getElementById('loginView').style.display = 'none'; document.getElementById('resetView').style.display = 'none';
        
        const target = document.getElementById(view);
        target.style.display = 'block';
        target.style.opacity = '0';
        target.style.transform = 'scale(0.95)';
        
        setTimeout(() => {
            target.style.opacity = '1';
            target.style.transform = 'scale(1)';
        }, 10);

        if(view === 'resetView') { document.getElementById('step1').style.display = 'block'; document.getElementById('step2').style.display = 'none'; document.getElementById('reset_user').value = ''; }
    }

    async function handleLogin(e) {
        e.preventDefault();
        const fd = new FormData(); fd.append('username', document.getElementById('login_user').value); fd.append('password', document.getElementById('login_pass').value);
        const res = await fetch('/index.php?action=login', { method: 'POST', body: fd }).then(r => r.json());
        if (res.status === 'success') { 
            document.querySelector('.glass-panel').style.transform = 'scale(1.1)';
            document.querySelector('.glass-panel').style.opacity = '0';
            setTimeout(() => window.location.href = '/dashboard', 300);
        } else { 
            Swal.fire({ 
                icon: 'error', title: 'ACCESS DENIED', text: res.message, 
                background: document.documentElement.classList.contains('dark') ? 'rgba(15, 23, 42, 0.9)' : '#fff', 
                color: document.documentElement.classList.contains('dark') ? '#fff' : '#000',
                customClass: { popup: 'rounded-[2rem] border border-red-500/30 backdrop-blur-xl', confirmButton: 'bg-red-500 hover:bg-red-600 rounded-xl px-8 py-3 text-white font-bold transition-all shadow-lg' }
            }); 
        }
    }

    async function fetchQuestion() {
        const user = document.getElementById('reset_user').value;
        if(!user) return;
        const fd = new FormData(); fd.append('username', user);
        const res = await fetch('/index.php?action=get_sec_q', { method: 'POST', body: fd }).then(r=>r.json());
        if(res.status === 'success') {
            document.getElementById('sec_q_display').innerText = res.question;
            document.getElementById('reset_user').value = res.actual_user; 
            document.getElementById('step1').style.display = 'none'; document.getElementById('step2').style.display = 'block';
        } else { 
            Swal.fire({ icon: 'error', title: 'Target Not Found', text: res.message, background: document.documentElement.classList.contains('dark') ? 'rgba(15, 23, 42, 0.9)' : '#fff', color: document.documentElement.classList.contains('dark') ? '#fff' : '#000', customClass: { popup: 'rounded-[2rem] border border-red-500/30 backdrop-blur-xl', confirmButton: 'bg-red-500 hover:bg-red-600 rounded-xl px-8 py-3 text-white font-bold shadow-lg' } }); 
        }
    }

    async function executeReset() {
        const user = document.getElementById('reset_user').value; const answer = document.getElementById('reset_answer').value; const new_pass = document.getElementById('reset_new_pass').value;
        if(!answer || !new_pass) return;
        const fd = new FormData(); fd.append('username', user); fd.append('answer', answer); fd.append('new_pass', new_pass);
        const res = await fetch('/index.php?action=reset_pass', { method: 'POST', body: fd }).then(r=>r.json());
        if(res.status === 'success') {
            Swal.fire({ icon: 'success', title: 'Identity Restored', text: 'Passphrase overwritten. Please authenticate.', background: document.documentElement.classList.contains('dark') ? 'rgba(15, 23, 42, 0.9)' : '#fff', color: document.documentElement.classList.contains('dark') ? '#fff' : '#000', customClass: { popup: 'rounded-[2rem] border border-emerald-500/30 backdrop-blur-xl', confirmButton: 'bg-emerald-500 hover:bg-emerald-600 rounded-xl px-8 py-3 text-white font-bold shadow-lg' } }).then(() => { toggleView('loginView'); });
        } else { 
            Swal.fire({ icon: 'error', title: 'Verification Failed', text: res.message, background: document.documentElement.classList.contains('dark') ? 'rgba(15, 23, 42, 0.9)' : '#fff', color: document.documentElement.classList.contains('dark') ? '#fff' : '#000', customClass: { popup: 'rounded-[2rem] border border-red-500/30 backdrop-blur-xl', confirmButton: 'bg-red-500 hover:bg-red-600 rounded-xl px-8 py-3 text-white font-bold shadow-lg' } }); 
        }
    }
</script>
</body>
</html>