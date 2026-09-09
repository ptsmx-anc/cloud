<?php $active_menu = defined('ACTIVE_MENU') ? ACTIVE_MENU : 'dashboard'; ?>
<!DOCTYPE html>
<html lang="en" class="antialiased dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Central Hub</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Fira+Code:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { 
                        sans: ['Plus Jakarta Sans', 'sans-serif'], 
                        mono: ['Fira Code', 'monospace'] 
                    },
                    colors: {
                        glass: {
                            50: 'rgba(255, 255, 255, 0.05)',
                            100: 'rgba(255, 255, 255, 0.1)',
                            200: 'rgba(255, 255, 255, 0.2)',
                            dark: 'rgba(15, 23, 42, 0.6)',
                            border: 'rgba(255, 255, 255, 0.08)'
                        },
                        brand: { 400: '#22d3ee', 500: '#06b6d4', 600: '#0891b2' },
                        accent: { 400: '#a78bfa', 500: '#8b5cf6', 600: '#7c3aed' }
                    },
                    animation: {
                        'blob': 'blob 10s infinite',
                        'fade-in': 'fadeIn 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards',
                        'slide-up': 'slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards'
                    },
                    keyframes: {
                        blob: {
                            '0%': { transform: 'translate(0px, 0px) scale(1)' },
                            '33%': { transform: 'translate(30px, -50px) scale(1.1)' },
                            '66%': { transform: 'translate(-20px, 20px) scale(0.9)' },
                            '100%': { transform: 'translate(0px, 0px) scale(1)' }
                        },
                        fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                        slideUp: { '0%': { transform: 'translateY(20px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } }
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/monokai.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/addon/dialog/dialog.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/addon/search/search.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/addon/search/searchcursor.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/addon/dialog/dialog.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/php/php.min.js"></script>

    <style>
        :root {
            --bg-base: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --glass-bg: rgba(255, 255, 255, 0.4);
            --glass-border: rgba(255, 255, 255, 0.5);
            --glass-card: rgba(255, 255, 255, 0.6);
            --glass-hover: rgba(255, 255, 255, 0.8);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
            --input-bg: rgba(255, 255, 255, 0.5);
            --border-color: rgba(148, 163, 184, 0.3);
        }

        html.dark {
            --bg-base: #020617;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --glass-bg: rgba(15, 23, 42, 0.4);
            --glass-border: rgba(255, 255, 255, 0.05);
            --glass-card: rgba(30, 41, 59, 0.5);
            --glass-hover: rgba(30, 41, 59, 0.8);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.4);
            --input-bg: rgba(15, 23, 42, 0.6);
            --border-color: rgba(51, 65, 85, 0.5);
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            transition: all 0.5s ease;
            overflow: hidden;
        }

        .glass-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
        }

        .card-premium {
            background: var(--glass-card);
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 1.5rem;
            box-shadow: var(--glass-shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .card-premium:hover {
            transform: translateY(-4px);
            background: var(--glass-hover);
            border-color: rgba(6, 182, 212, 0.3);
            box-shadow: 0 20px 40px -10px rgba(6, 182, 212, 0.15);
        }

        .input-premium {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            backdrop-filter: blur(8px);
            transition: all 0.3s ease;
            outline: none;
        }

        .input-premium:focus {
            border-color: #06b6d4;
            box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.1);
            background: var(--glass-hover);
        }

        .btn-animated {
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1;
        }
        
        .btn-animated::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: all 0.5s ease;
            z-index: -1;
        }
        
        .btn-animated:hover::before {
            left: 100%;
        }

        .btn-animated:hover {
            transform: translateY(-2px);
        }

        .btn-animated:active {
            transform: translateY(1px);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

        .view-section { display: none; opacity: 0; transform: translateY(15px); transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1); }
        .view-section.active { display: block; opacity: 1; transform: translateY(0); }

        /* Modal Glassmorphism */
        .modal { opacity: 0; pointer-events: none; visibility: hidden; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); z-index: 9999; backdrop-filter: blur(16px); }
        .modal.active { opacity: 1; pointer-events: auto; visibility: visible; }
        .modal-content { 
            transform: scale(0.95) translateY(20px); 
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            background: var(--glass-card);
            border: 1px solid var(--glass-border);
            box-shadow: 0 30px 60px -12px rgba(0,0,0,0.5);
            backdrop-filter: blur(24px);
        }
        .modal.active .modal-content { transform: scale(1) translateY(0); }

        table th {
            font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
            color: var(--text-muted); border-bottom: 1px solid var(--glass-border); background: var(--glass-bg);
        }
        table td { padding: 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-main); font-size: 0.875rem; font-weight: 500; }
        tr:hover td { background: var(--glass-hover); }

        .auth-secret { color: transparent; text-shadow: 0 0 10px var(--text-muted); cursor: pointer; transition: all 0.2s; user-select: all; }
        .auth-secret:active, .auth-secret:focus, .auth-secret.revealed { color: var(--text-main); text-shadow: none; background: rgba(6, 182, 212, 0.1); border-radius: 6px; padding: 2px 6px; }

        .file-checkbox { width: 1.25rem; height: 1.25rem; border-radius: 0.375rem; border: 1px solid var(--border-color); appearance: none; cursor: pointer; background: var(--input-bg); transition: all 0.2s; position: relative; }
        .file-checkbox:checked { background: #06b6d4; border-color: #06b6d4; }
        .file-checkbox:checked::after { content: ''; position: absolute; left: 6px; top: 2px; width: 5px; height: 10px; border: solid white; border-width: 0 2px 2px 0; transform: rotate(45deg); }

        .role-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; box-shadow: inset 0 0 0 1px currentColor; backdrop-filter: blur(4px); }
        .role-owner { color: #f43f5e; background: rgba(244, 63, 94, 0.1); }
        .role-admin { color: #10b981; background: rgba(16, 185, 129, 0.1); }
        .role-guest { color: #8b5cf6; background: rgba(139, 92, 246, 0.1); }

        .CodeMirror { height: 100% !important; font-family: 'Fira Code', monospace; font-size: 14px; background: transparent !important; color: var(--text-main) !important; border-radius: 0 0 1.5rem 1.5rem; }
    </style>
</head>
<body id="bodyTheme" class="selection:bg-brand-500/30 selection:text-brand-400">
    <script>if (localStorage.getItem('emerald_theme') !== 'light') document.documentElement.classList.add('dark');</script>

    <div class="fixed inset-0 z-[-1] overflow-hidden bg-[var(--bg-base)] transition-colors duration-700 pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[50vw] h-[50vw] rounded-full bg-brand-500/20 blur-[120px] animate-blob mix-blend-screen"></div>
        <div class="absolute top-[20%] right-[-10%] w-[40vw] h-[40vw] rounded-full bg-accent-500/20 blur-[120px] animate-blob mix-blend-screen" style="animation-delay: 2s;"></div>
        <div class="absolute bottom-[-20%] left-[20%] w-[60vw] h-[60vw] rounded-full bg-emerald-500/15 blur-[150px] animate-blob mix-blend-screen" style="animation-delay: 4s;"></div>
    </div>

    <div id="dropOverlay" class="fixed inset-0 bg-brand-900/40 backdrop-blur-md z-[1000] hidden items-center justify-center border-2 border-dashed border-brand-500 m-4 rounded-[2rem] pointer-events-none transition-all">
        <div class="text-center p-12 glass-panel rounded-3xl shadow-2xl">
            <div class="w-24 h-24 bg-brand-500/20 rounded-full flex items-center justify-center mx-auto mb-6 animate-bounce shadow-[0_0_30px_rgba(6,182,212,0.5)]">
                <i class="fa-solid fa-cloud-arrow-up text-5xl text-brand-400"></i>
            </div>
            <h2 class="text-3xl font-extrabold text-white">Deploy Payload</h2>
            <p class="text-brand-200 font-medium mt-2">Release to initiate secure transfer</p>
        </div>
    </div>

    <div class="h-screen w-screen p-4 md:p-6 flex gap-6 relative z-10 box-border">
        
        <aside class="w-[280px] glass-panel rounded-[2rem] flex flex-col shadow-2xl transition-all duration-300 z-30 overflow-hidden">
            <div class="h-24 flex items-center px-8 border-b border-[var(--glass-border)] relative">
                <div class="flex items-center gap-4 relative z-10">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-400 to-accent-500 p-[1px] shadow-lg">
                        <div class="w-full h-full bg-[var(--glass-card)] rounded-2xl flex items-center justify-center backdrop-blur-md">
                            <i class="fa-solid fa-terminal text-brand-400 text-xl"></i>
                        </div>
                    </div>
                    <div>
                        <h1 class="font-extrabold text-xl tracking-tight text-[var(--text-main)] flex items-center gap-2">SUB CLOUD <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse shadow-[0_0_10px_#34d399]"></div></h1>
                        <p class="text-[10px] text-[var(--text-muted)] font-mono tracking-widest uppercase font-bold">POWERED BY PTSMC TEAM</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 px-5 py-8 space-y-2 overflow-y-auto custom-scrollbar" id="mainNav">
                <a href="/dashboard/" class="nav-item w-full flex items-center gap-4 px-4 py-3.5 <?= $active_menu === 'dashboard' ? 'bg-brand-500/15 text-brand-400 border border-brand-500/30 font-bold shadow-inner' : 'text-[var(--text-muted)] hover:bg-[var(--glass-hover)] hover:text-[var(--text-main)] border border-transparent font-medium' ?> rounded-2xl transition-all">
                    <i class="fa-solid fa-chart-pie w-5 text-center <?= $active_menu === 'dashboard' ? 'text-brand-400' : '' ?>"></i> Overview
                </a>
                <a href="/assets/" class="nav-item w-full flex items-center gap-4 px-4 py-3.5 <?= $active_menu === 'files' ? 'bg-brand-500/15 text-brand-400 border border-brand-500/30 font-bold shadow-inner' : 'text-[var(--text-muted)] hover:bg-[var(--glass-hover)] hover:text-[var(--text-main)] border border-transparent font-medium' ?> rounded-2xl transition-all">
                    <i class="fa-solid fa-folder-open w-5 text-center <?= $active_menu === 'files' ? 'text-brand-400' : '' ?>"></i> Storage
                </a>
                <a href="/containers/" class="nav-item w-full flex items-center gap-4 px-4 py-3.5 <?= $active_menu === 'notes' ? 'bg-brand-500/15 text-brand-400 border border-brand-500/30 font-bold shadow-inner' : 'text-[var(--text-muted)] hover:bg-[var(--glass-hover)] hover:text-[var(--text-main)] border border-transparent font-medium' ?> rounded-2xl transition-all">
                    <i class="fa-solid fa-box w-5 text-center <?= $active_menu === 'notes' ? 'text-brand-400' : '' ?>"></i> Domains
                </a>
                <a href="/cloaking/" class="nav-item w-full flex items-center gap-4 px-4 py-3.5 <?= $active_menu === 'cloaking' ? 'bg-brand-500/15 text-brand-400 border border-brand-500/30 font-bold shadow-inner' : 'text-[var(--text-muted)] hover:bg-[var(--glass-hover)] hover:text-[var(--text-main)] border border-transparent font-medium' ?> rounded-2xl transition-all">
                    <i class="fa-solid fa-masks-theater w-5 text-center <?= $active_menu === 'cloaking' ? 'text-brand-400' : '' ?>"></i> Data Cloaking
                </a>
                <a href="/users/" class="nav-item w-full flex items-center gap-4 px-4 py-3.5 <?= $active_menu === 'users' ? 'bg-brand-500/15 text-brand-400 border border-brand-500/30 font-bold shadow-inner' : 'text-[var(--text-muted)] hover:bg-[var(--glass-hover)] hover:text-[var(--text-main)] border border-transparent font-medium' ?> rounded-2xl transition-all">
                    <i class="fa-solid fa-fingerprint w-5 text-center <?= $active_menu === 'users' ? 'text-brand-400' : '' ?>"></i> Access
                </a>
                <a href="/firewall/" class="nav-item w-full flex items-center gap-4 px-4 py-3.5 <?= $active_menu === 'firewall' ? 'bg-brand-500/15 text-brand-400 border border-brand-500/30 font-bold shadow-inner' : 'text-[var(--text-muted)] hover:bg-[var(--glass-hover)] hover:text-[var(--text-main)] border border-transparent font-medium' ?> rounded-2xl transition-all">
                    <i class="fa-solid fa-shield-halved w-5 text-center <?= $active_menu === 'firewall' ? 'text-brand-400' : '' ?>"></i> Firewall
                </a>
                
                <div class="pt-6 pb-1">
                    <p class="text-[9px] font-bold text-[var(--text-muted)] uppercase tracking-widest px-4">Public Apps</p>
                </div>
                <a href="/notepad/" class="w-full flex items-center gap-4 px-4 py-3 text-emerald-400 hover:bg-emerald-500/10 border border-transparent rounded-2xl font-bold transition-all btn-animated">
                    <i class="fa-solid fa-book-open w-5 text-center"></i> Public Notepad
                </a>
            </nav>

            <div class="p-5 border-t border-[var(--glass-border)] bg-[var(--glass-bg)]">
                <div class="flex items-center justify-between mb-4 cursor-pointer hover:bg-[var(--glass-hover)] p-3 rounded-2xl transition-all border border-transparent hover:border-[var(--glass-border)] shadow-sm" onclick="openProfileModal()">
                    <div class="flex items-center gap-3 overflow-hidden">
                        <div class="relative">
                            <img id="sidebarAvatar" src="" class="w-10 h-10 rounded-full border border-[var(--glass-border)] shadow-md object-cover">
                            <div id="sidebarDot" class="w-3 h-3 rounded-full absolute -bottom-0.5 -right-0.5 border-2 border-[var(--bg-base)] bg-slate-500"></div>
                        </div>
                        <div class="overflow-hidden">
                            <div class="font-bold text-[var(--text-main)] text-sm truncate" id="sidebarUsername"><?= htmlspecialchars($_SESSION['emerald_user']) ?></div>
                            <div class="text-[9px] font-bold mt-0.5 role-display uppercase tracking-widest text-[var(--text-muted)]" id="sidebarRole">Fetching...</div>
                        </div>
                    </div>
                    <i class="fa-solid fa-sliders text-[var(--text-muted)] hover:text-brand-400 transition-colors"></i>
                </div>
                <a href="/index.php?action=logout" class="block w-full text-center px-4 py-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 hover:bg-rose-500 hover:text-white rounded-2xl transition-all text-xs font-bold shadow-sm btn-animated">
                    <i class="fa-solid fa-power-off mr-2"></i> Terminate Session
                </a>
            </div>
        </aside>

        <main class="flex-1 flex flex-col gap-6 relative z-20 min-w-0">
            <header class="glass-panel rounded-[2rem] h-24 flex items-center justify-between px-8 shadow-sm shrink-0">
                <div class="flex flex-col">
                    <h1 class="text-3xl font-extrabold text-[var(--text-main)] tracking-tight drop-shadow-md" id="pageTitle">System Overview</h1>
                    <div id="breadcrumb" class="text-xs font-mono text-[var(--text-muted)] mt-1.5 flex items-center gap-2 opacity-0 transition-opacity font-medium">
                        <i class="fa-solid fa-layer-group"></i> / root
                    </div>
                </div>
                <div class="flex items-center gap-6">
                    <div class="hidden lg:flex items-center gap-3 bg-[var(--input-bg)] px-5 py-2.5 rounded-2xl border border-[var(--glass-border)] shadow-inner backdrop-blur-md">
                        <span class="text-[10px] text-[var(--text-muted)] font-bold tracking-widest uppercase">Node Auth:</span>
                        <span class="font-extrabold text-[var(--text-main)] text-sm"><?= htmlspecialchars($_SESSION['emerald_user']) ?></span>
                        <span id="headerRoleBadge" class="role-badge role-guest ml-2">User</span>
                    </div>
                    <div class="relative group">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--text-muted)] group-focus-within:text-brand-400 transition-colors"></i>
                        <input type="text" id="globalSearch" autocomplete="off" readonly onfocus="this.removeAttribute('readonly');" oninput="performSearch()" placeholder="Ctrl+F to Find..." class="text-sm pl-11 pr-4 py-3 input-premium rounded-2xl w-80 shadow-sm font-medium">
                    </div>
                </div>
            </header>

            <div class="flex-1 glass-panel rounded-[2rem] p-8 overflow-y-auto custom-scrollbar relative shadow-lg" id="mainAreaWrapper">
                
                <div id="view_dashboard" class="view-section <?= $active_menu === 'dashboard' ? 'active' : '' ?>">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div class="card-premium p-6 group">
                            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-brand-500/20 rounded-full blur-[40px] group-hover:bg-brand-500/40 transition-colors duration-500"></div>
                            <div class="flex items-center justify-between mb-6 relative z-10">
                                <h4 class="text-[var(--text-muted)] text-[10px] font-extrabold uppercase tracking-widest">Cipher Engine</h4>
                                <div class="w-10 h-10 rounded-2xl bg-[var(--glass-bg)] flex items-center justify-center border border-[var(--glass-border)] shadow-sm"><i class="fa-solid fa-shield-halved text-brand-400"></i></div>
                            </div>
                            <ul class="space-y-3 font-mono text-sm relative z-10 font-bold">
                                <li class="flex flex-col"><span class="text-[9px] text-[var(--text-muted)] tracking-widest mb-1">STANDARD</span><span class="text-[var(--text-main)] flex items-center gap-2"><div class="w-1.5 h-1.5 rounded-full bg-emerald-400"></div> AES-256</span></li>
                                <li class="flex flex-col"><span class="text-[9px] text-[var(--text-muted)] tracking-widest mb-1">INTEGRITY</span><span class="text-emerald-400 drop-shadow-[0_0_8px_rgba(52,211,153,0.5)]">OPTIMAL</span></li>
                            </ul>
                        </div>
                        <div class="card-premium p-6 group">
                            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-emerald-500/20 rounded-full blur-[40px] group-hover:bg-emerald-500/40 transition-colors duration-500"></div>
                            <div class="flex items-center justify-between mb-6 relative z-10">
                                <h4 class="text-[var(--text-muted)] text-[10px] font-extrabold uppercase tracking-widest">Network</h4>
                                <div class="w-10 h-10 rounded-2xl bg-[var(--glass-bg)] flex items-center justify-center border border-[var(--glass-border)] shadow-sm"><i class="fa-solid fa-network-wired text-emerald-400"></i></div>
                            </div>
                            <ul class="space-y-3 font-mono text-sm relative z-10 font-bold">
                                <li class="flex flex-col"><span class="text-[9px] text-[var(--text-muted)] tracking-widest mb-1">FIREWALL</span><span class="text-[var(--text-main)] flex items-center gap-2"><div class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></div> ACTIVE</span></li>
                                <li class="flex flex-col"><span class="text-[9px] text-[var(--text-muted)] tracking-widest mb-1">IP NODE</span><span class="text-[var(--text-main)]"><?= $_SERVER['REMOTE_ADDR'] ?></span></li>
                            </ul>
                        </div>
                        <div class="card-premium p-6 group">
                            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-accent-500/20 rounded-full blur-[40px] group-hover:bg-accent-500/40 transition-colors duration-500"></div>
                            <div class="flex items-center justify-between mb-6 relative z-10">
                                <h4 class="text-[var(--text-muted)] text-[10px] font-extrabold uppercase tracking-widest">Environment</h4>
                                <div class="w-10 h-10 rounded-2xl bg-[var(--glass-bg)] flex items-center justify-center border border-[var(--glass-border)] shadow-sm"><i class="fa-solid fa-server text-accent-400"></i></div>
                            </div>
                            <ul class="space-y-3 font-mono text-sm relative z-10 font-bold" id="sysEnvList">
                                <li class="flex flex-col"><span class="text-[9px] text-[var(--text-muted)] tracking-widest mb-1">STORAGE</span><span class="text-accent-400" id="dashDisk">...</span></li>
                                <li class="flex flex-col"><span class="text-[9px] text-[var(--text-muted)] tracking-widest mb-1">INTERFACE</span><span class="text-[var(--text-main)] uppercase" id="dashSapi">...</span></li>
                            </ul>
                        </div>
                        <div class="card-premium p-6 group flex flex-col justify-between">
                            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-amber-500/20 rounded-full blur-[40px] group-hover:bg-amber-500/40 transition-colors duration-500"></div>
                            <div class="flex items-center justify-between mb-2 relative z-10">
                                <h4 class="text-[var(--text-muted)] text-[10px] font-extrabold uppercase tracking-widest">Interface</h4>
                                <div class="w-10 h-10 rounded-2xl bg-[var(--glass-bg)] flex items-center justify-center border border-[var(--glass-border)] shadow-sm"><i class="fa-solid fa-palette text-amber-400"></i></div>
                            </div>
                            <div class="relative z-10 mt-auto">
                                <p class="text-[9px] font-mono text-[var(--text-muted)] mb-3 tracking-widest font-bold">WORKSPACE THEME</p>
                                <div class="flex items-center justify-between bg-[var(--input-bg)] border border-[var(--border-color)] p-3 rounded-2xl cursor-pointer btn-animated shadow-inner w-full" onclick="toggleTheme()">
                                    <span class="text-sm font-extrabold text-[var(--text-main)]" id="themeText">Dark Mode</span>
                                    <div class="w-12 h-6 bg-slate-700/50 rounded-full flex items-center px-1 theme-toggle-btn border border-[var(--glass-border)]"><div class="w-4 h-4 bg-white rounded-full transition-all shadow-md" id="themeCircle"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="card-premium flex flex-col h-[400px]">
                            <div class="px-6 py-5 border-b border-[var(--glass-border)] bg-[var(--glass-bg)] flex items-center gap-3 shrink-0 backdrop-blur-md">
                                <div class="w-8 h-8 rounded-xl bg-brand-500/20 flex items-center justify-center border border-brand-500/30"><i class="fa-solid fa-shield-halved text-brand-400 text-sm"></i></div>
                                <h3 class="font-extrabold text-[var(--text-main)] text-base">Access Logs</h3>
                            </div>
                            <div class="overflow-y-auto flex-1 custom-scrollbar bg-[var(--glass-bg)]">
                                <table class="w-full text-left border-collapse">
                                    <thead class="sticky top-0 z-10 shadow-sm backdrop-blur-md">
                                        <tr><th class="px-6 py-4">Time</th><th class="px-4 py-4">User</th><th class="px-4 py-4">IP</th><th class="px-4 py-4 text-right">Status</th></tr>
                                    </thead>
                                    <tbody id="logsList" class="divide-y divide-[var(--border-color)]"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-premium flex flex-col h-[400px]">
                            <div class="px-6 py-5 border-b border-[var(--glass-border)] bg-[var(--glass-bg)] flex items-center gap-3 shrink-0 backdrop-blur-md">
                                <div class="w-8 h-8 rounded-xl bg-accent-500/20 flex items-center justify-center border border-accent-500/30"><i class="fa-solid fa-clipboard-list text-accent-400 text-sm"></i></div>
                                <h3 class="font-extrabold text-[var(--text-main)] text-base">Activity Journal</h3>
                            </div>
                            <div class="overflow-y-auto flex-1 custom-scrollbar bg-[var(--glass-bg)]">
                                <table class="w-full text-left border-collapse">
                                    <thead class="sticky top-0 z-10 shadow-sm backdrop-blur-md">
                                        <tr><th class="px-6 py-4">Time</th><th class="px-4 py-4">User</th><th class="px-4 py-4">Action Details</th></tr>
                                    </thead>
                                    <tbody id="activityList" class="divide-y divide-[var(--border-color)]"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="view_files" class="view-section <?= $active_menu === 'files' ? 'active h-full flex flex-col' : '' ?>">
                    <div class="flex items-center gap-3 mb-6 bg-[var(--glass-card)] p-4 rounded-2xl border border-[var(--glass-border)] hidden shadow-lg backdrop-blur-xl animate-fade-in z-20" id="bulkToolbar">
                        <span class="text-[var(--text-main)] font-bold text-sm mr-2 border-r border-[var(--border-color)] pr-4 flex items-center gap-2"><span id="selCount" class="bg-brand-500 text-white px-2 py-0.5 rounded-md text-xs">0</span> selected</span>
                        <button class="bg-rose-500/10 text-rose-400 px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-rose-500 hover:text-white border border-rose-500/20 transition-all btn-animated" onclick="bulkAction('delete')"><i class="fa-solid fa-trash mr-1.5"></i> Purge</button>
                        <button class="bg-brand-500/10 text-brand-400 px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-brand-500 hover:text-white border border-brand-500/20 transition-all btn-animated" onclick="bulkAction('copy')"><i class="fa-solid fa-copy mr-1.5"></i> Copy</button>
                        <button class="bg-accent-500/10 text-accent-400 px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-accent-500 hover:text-white border border-accent-500/20 transition-all btn-animated" onclick="bulkAction('cut')"><i class="fa-solid fa-scissors mr-1.5"></i> Cut</button>
                    </div>
                    
                    <div class="flex items-center gap-3 mb-6 bg-brand-500/20 p-4 rounded-2xl border border-brand-400/40 hidden shadow-[0_0_30px_rgba(6,182,212,0.2)] backdrop-blur-xl animate-fade-in z-20" id="pasteToolbar">
                        <span class="text-brand-300 font-bold text-sm mr-2 border-r border-brand-400/40 pr-4" id="pasteInfo"></span>
                        <button class="bg-brand-500 text-white px-6 py-2.5 rounded-xl text-sm font-bold hover:bg-brand-400 transition-all btn-animated shadow-lg" onclick="executePaste()"><i class="fa-solid fa-paste mr-1.5"></i> Paste Here</button>
                        <button class="bg-transparent border border-brand-400/40 text-brand-300 px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-brand-500/30 transition-all btn-animated" onclick="cancelPaste()">Cancel</button>
                    </div>

                    <div class="flex justify-between items-center mb-6 bg-[var(--glass-card)] border border-[var(--glass-border)] p-4 rounded-[2rem] shadow-sm backdrop-blur-lg">
                        <div class="flex gap-3">
                            <button class="bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-main)] px-5 py-3 text-sm font-bold rounded-2xl hover:border-brand-400/50 transition-all btn-animated flex items-center gap-2 shadow-sm" onclick="navigateUp()" id="btnNavUp" style="display:none;">
                                <i class="fa-solid fa-arrow-left"></i> Back
                            </button>
                            <div class="relative group">
                                <i class="fa-solid fa-filter absolute left-4 top-1/2 -translate-y-1/2 text-[var(--text-muted)] text-sm"></i>
                                <input type="text" id="assetSearchFilter" autocomplete="off" readonly onfocus="this.removeAttribute('readonly');" placeholder="Filter current view..." onkeyup="filterAssets()" class="text-sm pl-11 pr-4 py-3 input-premium rounded-2xl w-64 font-medium shadow-sm">
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button class="bg-[var(--input-bg)] border border-[var(--glass-border)] text-brand-400 px-5 py-3 text-sm font-bold rounded-2xl hover:border-brand-400/50 transition-all btn-animated flex items-center gap-2 shadow-sm" onclick="promptCreateFolder()">
                                <i class="fa-solid fa-folder-plus text-lg"></i>
                            </button>
                            <button class="bg-[var(--input-bg)] border border-[var(--glass-border)] text-emerald-400 px-5 py-3 text-sm font-bold rounded-2xl hover:border-emerald-400/50 transition-all btn-animated flex items-center gap-2 shadow-sm" onclick="promptCreateFile()">
                                <i class="fa-solid fa-file-circle-plus text-lg"></i>
                            </button>
                            <div class="w-px h-8 bg-[var(--border-color)] self-center mx-2"></div>
                            <button class="bg-brand-600 text-white px-6 py-3 text-sm font-bold rounded-2xl btn-animated flex items-center gap-2 shadow-lg hover:bg-brand-500 border border-brand-400/50" onclick="document.getElementById('fileInput').click()">
                                <i class="fa-solid fa-cloud-arrow-up"></i> Upload
                            </button>
                            <button class="bg-glass-card border border-brand-500/40 text-brand-400 px-5 py-3 text-sm font-bold rounded-2xl btn-animated flex items-center gap-2 shadow-sm hover:bg-brand-500/20" onclick="document.getElementById('folderInput').click()">
                                <i class="fa-solid fa-folder-tree"></i>
                            </button>
                            <input type="file" id="fileInput" class="hidden" multiple onchange="handleStandardUpload(this.files, false)">
                            <input type="file" id="folderInput" class="hidden" webkitdirectory directory multiple onchange="handleStandardUpload(this.files, true)">
                            <button class="bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] px-5 py-3 rounded-2xl hover:text-[var(--text-main)] transition-all btn-animated shadow-sm" onclick="loadFiles(currentPath)">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                        </div>
                    </div>

                    <div class="card-premium flex flex-col flex-1 min-h-[500px]">
                        <div class="overflow-y-auto flex-1 custom-scrollbar">
                            <table class="w-full text-left border-collapse relative">
                                <thead class="sticky top-0 z-10 shadow-sm backdrop-blur-xl bg-[var(--glass-bg)]/80">
                                    <tr>
                                        <th class="px-6 py-5 w-12 text-center border-b border-[var(--glass-border)]">
                                            <input type="checkbox" id="selectAllCheckbox" class="file-checkbox" onclick="event.stopPropagation(); toggleSelectAll(this)">
                                        </th>
                                        <th class="px-4 py-5 border-b border-[var(--glass-border)]">Asset Name</th>
                                        <th class="px-6 py-5 border-b border-[var(--glass-border)]">Format</th>
                                        <th class="px-6 py-5 border-b border-[var(--glass-border)]">Size</th>
                                        <th class="px-6 py-5 border-b border-[var(--glass-border)]">Last Mod</th>
                                        <th class="px-6 py-5 border-b border-[var(--glass-border)]">Owner</th>
                                        <th class="px-6 py-5 text-right border-b border-[var(--glass-border)]">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="filesList" class="divide-y divide-[var(--border-color)]"></tbody>
                            </table>
                        </div>
                        <div id="assetsPagination" class="bg-[var(--glass-bg)] border-t border-[var(--glass-border)] shrink-0 p-2 backdrop-blur-md"></div>
                    </div>
                </div>

                <div id="view_notes" class="view-section <?= $active_menu === 'notes' ? 'active' : '' ?>">
                    <div class="flex justify-between items-center mb-8 bg-[var(--glass-card)] p-6 border border-[var(--glass-border)] rounded-[2rem] shadow-lg backdrop-blur-xl">
                        <div>
                            <h2 class="text-2xl font-extrabold text-[var(--text-main)]">Unified Domains</h2>
                            <p class="text-[var(--text-muted)] text-sm mt-1 font-medium">Manage deployment configurations and encrypted payloads.</p>
                        </div>
                        <button class="bg-brand-600 border border-brand-400/50 text-white px-8 py-3.5 text-sm font-bold rounded-2xl btn-animated flex items-center gap-2 shadow-[0_0_20px_rgba(6,182,212,0.4)] hover:bg-brand-500" onclick="openContainerModal()">
                            <i class="fa-solid fa-layer-group"></i> Build Container
                        </button>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" id="notesListArea"></div>
                </div>

                <div id="view_cloaking" class="view-section <?= $active_menu === 'cloaking' ? 'active' : '' ?>">
                    <div class="flex justify-between items-center mb-8 bg-[var(--glass-card)] p-6 border border-[var(--glass-border)] rounded-[2rem] shadow-lg backdrop-blur-xl">
                        <div>
                            <h2 class="text-2xl font-extrabold text-[var(--text-main)]">SEO Cloaking</h2>
                            <p class="text-[var(--text-muted)] text-sm mt-1 font-medium">Dynamic payload injections for targeted routing.</p>
                        </div>
                        <button class="bg-accent-600 border border-accent-400/50 text-white px-8 py-3.5 text-sm font-bold rounded-2xl hover:bg-accent-500 transition-all btn-animated shadow-[0_0_20px_rgba(139,92,246,0.4)] flex items-center gap-2" onclick="openCloakingModal()">
                            <i class="fa-solid fa-masks-theater"></i> Inject Cloaking
                        </button>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6" id="cloakingGrid"></div>
                </div>

                <div id="view_users" class="view-section <?= $active_menu === 'users' ? 'active' : '' ?>">
                    <div class="flex justify-between items-center mb-8 bg-[var(--glass-card)] p-6 border border-[var(--glass-border)] rounded-[2rem] shadow-lg backdrop-blur-xl">
                        <div>
                            <h2 class="text-2xl font-extrabold text-[var(--text-main)]">Identity Registry</h2>
                            <p class="text-[var(--text-muted)] text-sm mt-1 font-medium">Manage node privileges and secure access.</p>
                        </div>
                        <button class="bg-emerald-600 border border-emerald-400/50 text-white px-8 py-3.5 text-sm font-bold rounded-2xl hover:bg-emerald-500 transition-all btn-animated shadow-[0_0_20px_rgba(16,185,129,0.4)] flex items-center gap-2" onclick="openUserModal()">
                            <i class="fa-solid fa-fingerprint"></i> Register Node
                        </button>
                    </div>
                    <div class="card-premium max-w-5xl">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-[var(--glass-bg)] border-b border-[var(--glass-border)] backdrop-blur-md">
                                <tr><th class="px-8 py-5 text-sm">Identity</th><th class="px-6 py-5 text-sm">Privilege</th><th class="px-6 py-5 text-right text-sm">Actions</th></tr>
                            </thead>
                            <tbody id="usersList" class="divide-y divide-[var(--border-color)]"></tbody>
                        </table>
                    </div>
                </div>

                <div id="view_firewall" class="view-section <?= $active_menu === 'firewall' ? 'active' : '' ?>">
                    <div class="flex justify-between items-center mb-8 bg-[var(--glass-card)] p-6 border border-[var(--glass-border)] rounded-[2rem] shadow-lg backdrop-blur-xl">
                        <div>
                            <h2 class="text-2xl font-extrabold text-[var(--text-main)]">Network Firewall</h2>
                            <p class="text-[var(--text-muted)] text-sm mt-1 font-medium">Strict IP whitelist control for terminal access.</p>
                        </div>
                        <button class="bg-rose-600 border border-rose-400/50 text-white px-8 py-3.5 text-sm font-bold rounded-2xl hover:bg-rose-500 transition-all btn-animated shadow-[0_0_20px_rgba(244,63,94,0.4)] flex items-center gap-2" onclick="openFirewallModal()">
                            <i class="fa-solid fa-shield-virus"></i> Authorize IP
                        </button>
                    </div>
                    <div class="card-premium max-w-5xl">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-[var(--glass-bg)] border-b border-[var(--glass-border)] backdrop-blur-md">
                                <tr><th class="px-8 py-5 text-sm">IP Address</th><th class="px-6 py-5 text-sm">Notes</th><th class="px-6 py-5 text-sm">Added</th><th class="px-6 py-5 text-right text-sm">Actions</th></tr>
                            </thead>
                            <tbody id="firewallList" class="divide-y divide-[var(--border-color)] font-mono text-sm"></tbody>
                        </table>
                    </div>
                </div>

                <div id="view_monitor" class="view-section <?= $active_menu === 'monitor' ? 'active' : '' ?>">
                    <div class="flex justify-between items-center mb-8 bg-[var(--glass-card)] p-6 border border-[var(--glass-border)] rounded-[2rem] shadow-lg backdrop-blur-xl">
                        <div>
                            <h2 class="text-2xl font-extrabold text-[var(--text-main)]">Resource Monitor</h2>
                            <p class="text-[var(--text-muted)] text-sm mt-1 font-medium">Real-time Server Resource & Process execution.</p>
                        </div>
                        <button class="bg-brand-600 border border-brand-400/50 text-white px-8 py-3.5 text-sm font-bold rounded-2xl hover:bg-brand-500 transition-all btn-animated flex items-center gap-2 shadow-[0_0_20px_rgba(6,182,212,0.4)]" onclick="createSnapshot()">
                            <i class="fa-solid fa-camera"></i> System Snapshot
                        </button>
                    </div>
                    <div class="card-premium max-w-6xl flex flex-col h-[600px]">
                        <div class="overflow-y-auto flex-1 custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead class="sticky top-0 z-10 bg-[var(--glass-bg)] backdrop-blur-md">
                                    <tr class="border-b border-[var(--glass-border)] text-xs font-bold text-[var(--text-muted)] uppercase tracking-widest">
                                        <th class="px-6 py-5">PID</th>
                                        <th class="px-6 py-5">User</th>
                                        <th class="px-6 py-5">CPU %</th>
                                        <th class="px-6 py-5">RAM %</th>
                                        <th class="px-6 py-5">Command Executed</th>
                                        <th class="px-6 py-5 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="processList" class="divide-y divide-[var(--border-color)] text-sm font-mono"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            </div>
        </main>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalAuthPrompt">
        <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('modalAuthPrompt')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-sm relative z-10 overflow-hidden p-10 text-center border-t-4 border-t-rose-500">
            <div class="w-24 h-24 bg-rose-500/20 rounded-[2rem] border border-rose-500/30 flex items-center justify-center mx-auto mb-8 shadow-[0_0_30px_rgba(244,63,94,0.3)]">
                <i class="fa-solid fa-lock text-5xl text-rose-400"></i>
            </div>
            <h3 class="font-extrabold text-2xl text-[var(--text-main)] mb-2">Auth Required</h3>
            <p class="text-[var(--text-muted)] text-sm mb-8 font-medium">Verify identity for destructive operation.</p>
            <input type="password" id="authPassword" placeholder="Passphrase" class="w-full text-center input-premium rounded-2xl p-4 font-bold text-lg mb-8 tracking-widest shadow-inner">
            <div class="flex gap-4">
                <button class="flex-1 py-3.5 bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-2xl font-bold text-[var(--text-muted)] hover:text-[var(--text-main)] hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal('modalAuthPrompt')">Cancel</button>
                <button class="flex-1 py-3.5 bg-rose-600 text-white rounded-2xl font-bold hover:bg-rose-500 transition-all btn-animated shadow-[0_0_20px_rgba(244,63,94,0.4)]" onclick="executeAuthorizedAction()">Confirm</button>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalUploadProgress">
        <div class="fixed inset-0 bg-black/40 backdrop-blur-sm"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-md relative z-10 overflow-hidden p-10 text-center border border-[var(--glass-border)]">
            <div class="w-24 h-24 bg-brand-500/20 rounded-[2rem] border border-brand-500/30 flex items-center justify-center mx-auto mb-8 animate-pulse shadow-[0_0_30px_rgba(6,182,212,0.3)]">
                <i class="fa-solid fa-cloud-arrow-up text-5xl text-brand-400"></i>
            </div>
            <h3 class="font-extrabold text-2xl text-[var(--text-main)] mb-3">Processing Payload</h3>
            <p class="text-brand-300 text-sm mb-8 font-mono tracking-wide" id="uploadProgressText">Initializing secure tunnel...</p>
            <div class="w-full bg-[var(--input-bg)] rounded-full h-3 mb-2 overflow-hidden border border-[var(--glass-border)] p-0.5">
                <div class="bg-gradient-to-r from-brand-500 to-accent-500 h-full rounded-full transition-all duration-300 shadow-[0_0_10px_rgba(6,182,212,0.8)]" id="uploadProgressBar" style="width: 0%"></div>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalProfile">
        <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('modalProfile')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-lg relative z-10 overflow-hidden border border-[var(--glass-border)]">
            <div class="px-10 py-6 border-b border-[var(--glass-border)] flex justify-between items-center bg-[var(--glass-bg)] backdrop-blur-md">
                <h3 class="font-extrabold text-xl text-[var(--text-main)]">Profile Identity</h3>
                <button onclick="closeModal('modalProfile')" class="w-8 h-8 rounded-full bg-[var(--input-bg)] text-[var(--text-muted)] hover:text-[var(--text-main)] flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-10">
                <div class="text-center mb-8 relative">
                    <img id="profileAvatarPreview" src="" class="w-32 h-32 rounded-full border-[3px] border-brand-500/50 shadow-[0_0_30px_rgba(6,182,212,0.3)] object-cover mx-auto cursor-pointer hover:opacity-80 transition-opacity" onclick="openAvatarZoom(this.src)">
                    <button onclick="document.getElementById('avatarUpload').click()" class="absolute bottom-[-10px] right-1/2 translate-x-12 w-10 h-10 bg-brand-500 rounded-xl border border-[var(--glass-border)] text-white flex items-center justify-center hover:bg-brand-400 shadow-lg cursor-pointer transition-all"><i class="fa-solid fa-camera"></i></button>
                    <input type="file" id="avatarUpload" class="hidden" accept="image/*" onchange="handleAvatarUpload(event)">
                    <input type="hidden" id="profAvatarBase64">
                </div>
                <div class="grid grid-cols-2 gap-5 mb-5">
                    <div>
                        <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-2">Username</label>
                        <input type="text" id="profUsername" class="w-full input-premium rounded-xl p-3.5 font-bold text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-2">New Password</label>
                        <input type="password" id="profPassword" placeholder="Leave blank to keep" class="w-full input-premium rounded-xl p-3.5 font-bold text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-5">
                    <div>
                        <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-2">Security Question</label>
                        <input type="text" id="profSecQ" class="w-full input-premium rounded-xl p-3.5 font-bold text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-2">Security Answer</label>
                        <input type="text" id="profSecA" class="w-full input-premium rounded-xl p-3.5 font-bold text-sm">
                    </div>
                </div>
                <button class="w-full mt-8 py-4 bg-brand-600 border border-brand-400/50 text-white rounded-2xl font-bold btn-animated text-sm shadow-[0_0_20px_rgba(6,182,212,0.3)] hover:bg-brand-500" onclick="updateProfile()">Update Identity</button>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 md:p-8 z-[10000]" id="modalAvatarZoom">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-xl" onclick="closeModal('modalAvatarZoom')"></div>
        <img id="avatarZoomImg" src="" class="max-w-full max-h-full rounded-3xl relative z-10 shadow-[0_0_50px_rgba(0,0,0,0.8)] object-contain border border-[var(--glass-border)]">
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 md:p-8 z-[9999]" id="modalEditor">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('modalEditor')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-[90vw] flex flex-col h-[90vh] relative z-10 overflow-hidden border border-[var(--glass-border)]">
            <div class="px-8 py-5 border-b border-[var(--glass-border)] flex justify-between items-center bg-[var(--glass-bg)] backdrop-blur-xl shrink-0 z-50">
                <div class="flex items-center gap-5">
                    <div class="w-12 h-12 rounded-[1rem] bg-brand-500/20 flex items-center justify-center border border-brand-500/30 shadow-inner">
                        <i id="editorIcon" class="fa-solid fa-code text-brand-400 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-xl text-[var(--text-main)] font-mono tracking-tight flex items-center gap-4">
                            <span id="editorTitle">filename.ext</span>
                            <span id="editorSize" class="text-[10px] bg-[var(--input-bg)] text-brand-300 border border-[var(--glass-border)] px-3 py-1 rounded-md font-sans uppercase font-extrabold shadow-sm">0 KB</span>
                        </h3>
                        <div class="text-[10px] text-[var(--text-muted)] mt-1 flex items-center gap-4 font-medium uppercase tracking-widest">
                            <span><i class="fa-regular fa-clock mr-1.5"></i> <span id="editorModified">...</span></span>
                            <span><kbd class="bg-[var(--input-bg)] border border-[var(--glass-border)] px-2 py-0.5 rounded font-mono shadow-sm">Ctrl+F</kbd> Find | <kbd class="bg-[var(--input-bg)] border border-[var(--glass-border)] px-2 py-0.5 rounded font-mono shadow-sm">Ctrl+S</kbd> Save</span>
                        </div>
                    </div>
                </div>
                <div class="flex gap-4">
                    <button class="bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-main)] px-6 py-3 text-sm font-bold rounded-2xl hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal('modalEditor')">Close</button>
                    <button class="bg-brand-600 border border-brand-400/50 text-white px-8 py-3 text-sm font-bold rounded-2xl btn-animated flex items-center gap-2 hover:bg-brand-500 shadow-[0_0_20px_rgba(6,182,212,0.4)]" onclick="saveFileEditor()">
                        <i class="fa-solid fa-floppy-disk"></i> Commit
                    </button>
                </div>
            </div>
            <div class="flex-1 relative w-full flex flex-col bg-[#0b0f19]/90 backdrop-blur-md" id="editorContainer">
                <textarea id="codeEditor"></textarea>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 md:p-8 z-[9999]" id="modalPreviewAsset">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-md" onclick="closeModal('modalPreviewAsset')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-5xl flex flex-col h-[85vh] relative z-10 overflow-hidden border border-[var(--glass-border)]">
            <div class="px-8 py-5 border-b border-[var(--glass-border)] flex justify-between items-center bg-[var(--glass-bg)] backdrop-blur-xl shrink-0">
                <h3 class="font-bold text-xl text-[var(--text-main)] font-mono tracking-tight flex items-center gap-3">
                    <i class="fa-solid fa-eye text-brand-400"></i> <span id="previewTitle">Preview</span>
                </h3>
                <div class="flex gap-3">
                    <button class="bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-main)] px-6 py-2.5 text-sm font-bold rounded-xl hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal('modalPreviewAsset')">Close</button>
                    <button id="btnEditPreview" class="bg-emerald-600 border border-emerald-400/50 text-white px-6 py-2.5 text-sm font-bold rounded-xl btn-animated hover:bg-emerald-500 shadow-[0_0_15px_rgba(16,185,129,0.3)] flex items-center gap-2"><i class="fa-solid fa-pen"></i> Edit Mode</button>
                </div>
            </div>
            <div class="flex-1 overflow-hidden relative flex bg-black/50" id="previewContentArea"></div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalContainer">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('modalContainer')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-6xl relative z-10 max-h-[95vh] flex flex-col overflow-hidden border border-[var(--glass-border)]">
            <div class="px-10 py-6 border-b border-[var(--glass-border)] flex justify-between items-center bg-[var(--glass-bg)] backdrop-blur-xl">
                <h3 class="font-extrabold text-2xl text-[var(--text-main)] flex items-center gap-4">
                    <div class="w-12 h-12 bg-brand-500/20 rounded-[1rem] flex items-center justify-center border border-brand-500/30">
                        <i class="fa-solid fa-layer-group text-brand-400 text-xl"></i>
                    </div>
                    Configure Container
                </h3>
                <button onclick="closeModal('modalContainer')" class="w-10 h-10 rounded-full bg-[var(--input-bg)] text-[var(--text-muted)] hover:text-rose-400 flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="p-10 overflow-y-auto custom-scrollbar flex-1">
                <input type="hidden" id="containerId">
                <div class="space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Identifier</label>
                            <input type="text" id="containerTitle" placeholder="e.g., Project Alpha" class="w-full input-premium rounded-2xl p-4 font-bold text-base shadow-inner">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Status</label>
                            <select id="containerStatus" class="w-full input-premium rounded-2xl p-4 font-bold text-base shadow-inner appearance-none">
                                <option value="active">Active (Green)</option>
                                <option value="new">New (Grey)</option>
                                <option value="deactive">Deactive (Red)</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                        <div class="bg-[var(--glass-card)] border border-[var(--glass-border)] rounded-[2rem] p-6 flex flex-col shadow-sm">
                            <div class="flex items-center justify-between mb-5 border-b border-[var(--glass-border)] pb-4">
                                <h4 class="font-extrabold text-[var(--text-main)] text-sm flex items-center gap-3"><i class="fa-solid fa-list text-emerald-400"></i> Terminal & Resource List</h4>
                            </div>
                            <textarea id="containerTextList" class="w-full flex-1 input-premium rounded-xl p-5 font-mono text-xs h-72 custom-scrollbar whitespace-nowrap overflow-x-auto" placeholder="Enter payloads, commands..."></textarea>
                        </div>
                        <div class="bg-[var(--glass-card)] border border-[var(--glass-border)] rounded-[2rem] p-6 shadow-sm">
                            <div class="flex items-center gap-3 mb-5 border-b border-[var(--glass-border)] pb-4">
                                <h4 class="font-extrabold text-[var(--text-main)] text-sm"><i class="fa-solid fa-server text-accent-400"></i> Server Node Auth</h4>
                            </div>
                            <div class="space-y-4 mt-4">
                                <div class="flex items-center bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-xl p-1 shadow-inner">
                                    <span class="text-[10px] font-extrabold text-[var(--text-muted)] w-20 text-center tracking-widest">HOST</span>
                                    <input type="text" id="containerHost" class="flex-1 bg-transparent text-[var(--text-main)] py-3.5 px-4 font-mono text-sm outline-none border-l border-[var(--glass-border)]">
                                </div>
                                <div class="flex items-center bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-xl p-1 shadow-inner">
                                    <span class="text-[10px] font-extrabold text-[var(--text-muted)] w-20 text-center tracking-widest">USER</span>
                                    <input type="text" id="containerUser" class="flex-1 bg-transparent text-brand-400 py-3.5 px-4 font-mono text-sm outline-none border-l border-[var(--glass-border)]">
                                </div>
                                <div class="flex items-center bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-xl p-1 shadow-inner">
                                    <span class="text-[10px] font-extrabold text-[var(--text-muted)] w-20 text-center tracking-widest">PASS</span>
                                    <input type="text" id="containerPass" class="flex-1 bg-transparent text-rose-400 py-3.5 px-4 font-mono text-sm outline-none border-l border-[var(--glass-border)]">
                                </div>
                                <div class="flex items-center bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-xl p-1 shadow-inner">
                                    <span class="text-[10px] font-extrabold text-[var(--text-muted)] w-20 text-center tracking-widest">DIR</span>
                                    <input type="text" id="containerDir" class="flex-1 bg-transparent text-emerald-400 py-3.5 px-4 font-mono text-sm outline-none border-l border-[var(--glass-border)]">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-10 py-6 border-t border-[var(--glass-border)] bg-[var(--glass-bg)] flex justify-end gap-4 backdrop-blur-xl">
                <button class="px-8 py-3.5 bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-2xl text-sm font-bold text-[var(--text-main)] hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal('modalContainer')">Cancel</button>
                <button class="px-10 py-3.5 bg-brand-600 border border-brand-400/50 text-white rounded-2xl text-sm font-bold btn-animated flex items-center gap-2 hover:bg-brand-500 shadow-[0_0_20px_rgba(6,182,212,0.4)]" onclick="saveContainer()">
                    Store Container
                </button>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 md:p-8 z-[9999]" id="modalViewContainer">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-xl" onclick="closeModal('modalViewContainer')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-5xl relative z-10 overflow-hidden flex flex-col max-h-[90vh] border border-[var(--glass-border)]">
            <div class="px-10 py-8 border-b border-[var(--glass-border)] flex justify-between items-center bg-[var(--glass-bg)] backdrop-blur-xl">
                <div class="flex items-center gap-5">
                    <img id="viewContainerAvatar" src="" class="w-14 h-14 rounded-full border-2 border-[var(--glass-border)] shadow-[0_0_15px_rgba(255,255,255,0.1)] object-cover cursor-pointer hover:opacity-80">
                    <div>
                        <h3 class="font-extrabold text-2xl text-[var(--text-main)] tracking-tight drop-shadow-md" id="viewContainerTitle">Container</h3>
                        <p class="text-[10px] text-brand-400 font-mono uppercase tracking-widest font-bold mt-1" id="viewContainerOwner">Owner</p>
                    </div>
                </div>
                <div class="flex gap-3" id="viewContainerActions"></div>
            </div>
            <div class="p-10 overflow-y-auto custom-scrollbar flex-1 space-y-8" id="viewContainerContent"></div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalCloaking">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('modalCloaking')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-4xl relative z-10 overflow-hidden flex flex-col border border-[var(--glass-border)]">
            <div class="px-10 py-6 border-b border-[var(--glass-border)] flex justify-between items-center bg-[var(--glass-bg)] backdrop-blur-xl">
                <h3 class="font-extrabold text-2xl text-[var(--text-main)] flex items-center gap-4">
                    <div class="w-12 h-12 bg-accent-500/20 rounded-[1rem] flex items-center justify-center border border-accent-500/30 shadow-inner">
                        <i class="fa-solid fa-masks-theater text-accent-400"></i>
                    </div>
                    Cloaking
                </h3>
                <button onclick="closeModal('modalCloaking')" class="w-10 h-10 rounded-full bg-[var(--input-bg)] text-[var(--text-muted)] hover:text-rose-400 flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="p-10 space-y-8 flex-1 overflow-y-auto custom-scrollbar">
                <input type="hidden" id="cloakId">
                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Domain Target</label>
                        <input type="text" id="cloakDomain" placeholder="target.com" class="w-full input-premium rounded-2xl p-4 font-bold text-sm shadow-inner">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Cloak Path</label>
                        <input type="text" id="cloakPath" placeholder="/seo-landing" class="w-full input-premium rounded-2xl p-4 font-bold text-sm shadow-inner">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Visibility Scope</label>
                    <select id="cloakType" class="w-full input-premium rounded-2xl p-4 font-bold text-sm shadow-inner appearance-none">
                        <option value="personal">Personal Scope</option>
                        <option value="global">Global Scope</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 ml-2">Payload Schema (HTML/JS)</label>
                    <textarea id="cloakContent" class="w-full input-premium rounded-2xl p-5 font-mono text-xs h-56 shadow-inner text-accent-300 custom-scrollbar"></textarea>
                </div>
            </div>
            <div class="px-10 py-6 border-t border-[var(--glass-border)] bg-[var(--glass-bg)] flex justify-end gap-4 backdrop-blur-xl">
                <button class="px-8 py-3.5 bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-2xl font-bold text-[var(--text-main)] text-sm hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal('modalCloaking')">Cancel</button>
                <button class="px-8 py-3.5 bg-accent-600 border border-accent-400/50 text-white rounded-2xl font-bold text-sm hover:bg-accent-500 transition-all btn-animated shadow-[0_0_20px_rgba(139,92,246,0.4)]" onclick="saveCloaking()">Deploy Target</button>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalUser">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('modalUser')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-sm relative z-10 overflow-hidden p-10 border-t-4 border-t-emerald-500 text-center">
            <div class="w-24 h-24 bg-emerald-500/20 rounded-[2rem] border border-emerald-500/30 flex items-center justify-center mx-auto mb-8 shadow-inner">
                <i class="fa-solid fa-user-shield text-5xl text-emerald-400"></i>
            </div>
            <h3 class="font-extrabold text-2xl text-[var(--text-main)] mb-6">Register Identity</h3>
            <div class="space-y-5">
                <input type="text" id="newUserName" autocomplete="off" placeholder="Node Username" class="w-full input-premium rounded-2xl p-4 font-bold text-sm text-center shadow-inner">
                <input type="password" id="newUserPass" autocomplete="new-password" placeholder="Passphrase" class="w-full input-premium rounded-2xl p-4 font-bold text-sm text-center shadow-inner">
                <select id="newUserRole" class="w-full input-premium rounded-2xl p-4 font-bold text-sm text-center shadow-inner appearance-none">
                    <option value="guest">Guest Level</option>
                    <option value="admin">Admin Level</option>
                    <option value="owner">Owner Level</option>
                </select>
            </div>
            <div class="mt-10 flex gap-4">
                <button class="flex-1 py-3.5 bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-main)] rounded-2xl text-sm font-bold hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal('modalUser')">Cancel</button>
                <button class="flex-1 py-3.5 bg-emerald-600 border border-emerald-400/50 text-white rounded-2xl text-sm font-bold hover:bg-emerald-500 transition-all btn-animated shadow-[0_0_20px_rgba(16,185,129,0.4)]" onclick="saveUser()">Register</button>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalDeleteUser">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('modalDeleteUser')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-sm relative z-10 overflow-hidden p-10 border-t-4 border-t-rose-500 text-center">
            <div class="w-24 h-24 bg-rose-500/20 rounded-[2rem] border border-rose-500/30 flex items-center justify-center mx-auto mb-8 shadow-inner">
                <i class="fa-solid fa-user-xmark text-5xl text-rose-400"></i>
            </div>
            <h3 class="font-extrabold text-2xl text-[var(--text-main)] mb-6">Purge Identity</h3>
            <div class="space-y-5">
                <input type="text" id="delTargetUser" class="w-full input-premium text-rose-400 bg-rose-500/5 rounded-2xl p-4 font-bold text-sm text-center shadow-inner border-rose-500/20" readonly>
                <select id="delMigrateTo" class="w-full input-premium rounded-2xl p-4 font-bold text-sm text-center shadow-inner appearance-none">
                    <option value="">-- Destroy All Data --</option>
                </select>
            </div>
            <div class="mt-10 flex gap-4">
                <button class="flex-1 py-3.5 bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-main)] rounded-2xl text-sm font-bold hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal('modalDeleteUser')">Cancel</button>
                <button class="flex-1 py-3.5 bg-rose-600 border border-rose-400/50 text-white rounded-2xl text-sm font-bold hover:bg-rose-500 transition-all btn-animated shadow-[0_0_20px_rgba(244,63,94,0.4)]" onclick="executeDeleteUser()">Proceed</button>
            </div>
        </div>
    </div>

    <div class="modal fixed inset-0 flex items-center justify-center p-4 z-[9999]" id="modalFirewall">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('modalFirewall')"></div>
        <div class="modal-content rounded-[2.5rem] w-full max-w-sm relative z-10 overflow-hidden p-10 border-t-4 border-t-emerald-500 text-center">
            <div class="w-24 h-24 bg-emerald-500/20 rounded-[2rem] border border-emerald-500/30 flex items-center justify-center mx-auto mb-8 shadow-inner">
                <i class="fa-solid fa-shield-virus text-5xl text-emerald-400"></i>
            </div>
            <h3 class="font-extrabold text-2xl text-[var(--text-main)] mb-6">Whitelist IPv4/v6</h3>
            <div class="space-y-5">
                <input type="text" id="fwIP" autocomplete="off" placeholder="IP Address" class="w-full input-premium rounded-2xl p-4 font-bold text-sm text-center shadow-inner">
                <input type="text" id="fwNote" autocomplete="off" placeholder="Node Reference" class="w-full input-premium rounded-2xl p-4 font-bold text-sm text-center shadow-inner">
            </div>
            <div class="mt-10 flex gap-4">
                <button class="flex-1 py-3.5 bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-main)] rounded-2xl text-sm font-bold hover:bg-[var(--glass-hover)] transition-all btn-animated" onclick="closeModal('modalFirewall')">Cancel</button>
                <button class="flex-1 py-3.5 bg-emerald-600 border border-emerald-400/50 text-white rounded-2xl text-sm font-bold hover:bg-emerald-500 transition-all btn-animated shadow-[0_0_20px_rgba(16,185,129,0.4)]" onclick="saveFirewallIP()">Authorize</button>
            </div>
        </div>
    </div>

<script>
    let currentEditorFile = '';
    let editorInstance = null;
    let globalFilesData = []; let globalNotesData = []; let globalCloakData = []; let globalUsers = [];
    let currentPath = ''; 
    let currentAssetsPage = 1; const ASSETS_PER_PAGE = 100;
    const currentUser = '<?= $_SESSION['emerald_user'] ?>';
    let clipboard = { action: '', files: [], sourcePath: '' };
    
    // Updated SweetAlert configurations for Glassmorphism
    const Toast = Swal.mixin({
        toast: true, position: 'bottom-end', showConfirmButton: false, timer: 3000,
        background: 'var(--glass-card)', color: 'var(--text-main)', 
        customClass: { popup: 'border border-[var(--glass-border)] shadow-2xl rounded-2xl backdrop-blur-xl' }
    });
    const swalDark = Swal.mixin({
        background: 'var(--glass-card)', color: 'var(--text-main)',
        customClass: {
            popup: 'border border-[var(--glass-border)] shadow-[0_0_50px_rgba(0,0,0,0.5)] rounded-[2.5rem] backdrop-blur-xl',
            title: 'text-2xl font-extrabold text-[var(--text-main)] mt-4',
            input: 'bg-[var(--input-bg)] border border-[var(--border-color)] text-[var(--text-main)] rounded-2xl p-4 text-center font-mono focus:border-brand-500 outline-none w-[85%] mx-auto',
            confirmButton: 'bg-brand-600 text-white px-8 py-3 rounded-2xl text-sm font-bold hover:bg-brand-500 transition-all shadow-[0_0_20px_rgba(6,182,212,0.4)] mx-2',
            cancelButton: 'bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-main)] px-8 py-3 rounded-2xl text-sm font-bold hover:bg-[var(--glass-hover)] transition-all mx-2',
            actions: 'mt-8 mb-4'
        },
        buttonsStyling: false
    });

    function getMyRole() {
        const roleEl = document.getElementById('headerRoleBadge');
        return roleEl ? roleEl.innerText.trim().toLowerCase() : 'guest';
    }

    document.addEventListener('DOMContentLoaded', () => {
        const theme = localStorage.getItem('emerald_theme');
        if (theme === 'light') { document.documentElement.classList.remove('dark'); document.getElementById('themeText').innerText = 'Light Mode'; document.getElementById('themeCircle').style.transform = 'translateX(24px)'; document.querySelector('.theme-toggle-btn').classList.add('bg-emerald-400/50'); document.querySelector('.theme-toggle-btn').classList.remove('bg-slate-700/50'); }

        const dropOverlay = document.getElementById('dropOverlay');
        document.body.addEventListener('dragover', function(e) {
            e.preventDefault();
            if(document.getElementById('view_files').classList.contains('active')){
                dropOverlay.classList.remove('hidden'); dropOverlay.classList.add('flex');
            }
        });
        document.body.addEventListener('dragleave', function(e) {
            if (e.relatedTarget === null) {
                dropOverlay.classList.add('hidden');
                dropOverlay.classList.remove('flex');
            }
        });
        document.body.addEventListener('drop', async (e) => { 
            e.preventDefault();
            dropOverlay.classList.add('hidden'); dropOverlay.classList.remove('flex');
            if(document.getElementById('view_files').classList.contains('active')){
                const items = e.dataTransfer.items;
                if(items && items.length > 0 && items[0].webkitGetAsEntry) {
                    processDropUpload(items);
                } else { handleStandardUpload(e.dataTransfer.files, false); }
            }
        });
        loadUsers(); 
        
        editorInstance = CodeMirror.fromTextArea(document.getElementById("codeEditor"), {
            lineNumbers: true, theme: "monokai", mode: "htmlmixed", 
            matchBrackets: true, autoCloseBrackets: true, lineWrapping: true,
            extraKeys: { "Ctrl-F": "findPersistent", "Ctrl-S": function(cm) { saveFileEditor(); } }
        });
        if(theme === 'light') {
            editorInstance.setOption('theme', 'default');
        }

        document.addEventListener('keydown', e => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {
                if (!document.getElementById('modalEditor').classList.contains('active') && !document.getElementById('modalPreviewAsset').classList.contains('active')) {
                    e.preventDefault(); document.getElementById('globalSearch').focus();
                }
            }
        });

        const activeTab = '<?= $active_menu ?>';
        const titles = {'dashboard': 'System Overview', 'files': 'Storage Assets', 'notes': 'Unified Domains', 'cloaking': 'SEO Cloaking', 'users': 'Identity Registry', 'firewall': 'Firewall & IPs', 'monitor': 'Resource Monitor'};
        document.getElementById('pageTitle').innerText = titles[activeTab];
        document.getElementById('breadcrumb').style.opacity = (activeTab === 'files') ? '1' : '0';

        if (activeTab === 'dashboard') loadSysInfo();
        if (activeTab === 'files') loadFiles(currentPath);
        if (activeTab === 'notes') loadNotes();
        if (activeTab === 'cloaking') loadCloaking();
        if (activeTab === 'users') loadUsers();
        if (activeTab === 'firewall') loadFirewall();
        if (activeTab === 'monitor') loadMonitor();
    });

    async function processDropUpload(items) {
        document.getElementById('modalUploadProgress').classList.add('active');
        document.getElementById('uploadProgressText').innerText = 'Scanning directories...';
        let currentUpload = 0;
        document.getElementById('uploadProgressText').innerText = 'Uploading payloads...';
        for (let i=0; i<items.length; i++) {
            const item = items[i].webkitGetAsEntry();
            if(item) await traverseFileTree(item, '', function(){
                currentUpload++;
                const pct = Math.min(100, Math.round((currentUpload / (currentUpload+2)) * 100));
                document.getElementById('uploadProgressBar').style.width = pct + '%';
            });
        }
        document.getElementById('modalUploadProgress').classList.remove('active');
        document.getElementById('uploadProgressBar').style.width = '0%';
        Toast.fire({ icon: 'success', title: 'Upload Completed' });
        loadFiles(currentPath);
    }

    async function traverseFileTree(item, path, callback = null) {
        if (item.isFile) {
            return new Promise((resolve) => {
                item.file(async (file) => {
                    const fd = new FormData(); fd.append('file', file); fd.append('path', currentPath); fd.append('relative_path', path + file.name);
                    await fetch('/index.php?api=upload', { method: 'POST', body: fd });
                    if(callback) callback();
                    resolve();
                });
            });
        } else if (item.isDirectory) {
            let dirReader = item.createReader();
            return new Promise((resolve) => {
                dirReader.readEntries(async (entries) => {
                    for (let i=0; i<entries.length; i++) { await traverseFileTree(entries[i], path + item.name + "/", callback); }
                    resolve();
                });
             });
        }
    }

    async function handleStandardUpload(files, isFolderInput) {
        if (!files || files.length === 0) return;
        document.getElementById('modalUploadProgress').classList.add('active');
        for (let i = 0; i < files.length; i++) {
            document.getElementById('uploadProgressText').innerText = `Uploading ${isFolderInput ? (files[i].webkitRelativePath || files[i].name) : files[i].name}...`;
            const fd = new FormData(); fd.append('file', files[i]); fd.append('path', currentPath);
            if(isFolderInput) fd.append('relative_path', files[i].webkitRelativePath || files[i].name);
            await fetch('/index.php?api=upload', { method: 'POST', body: fd });
            document.getElementById('uploadProgressBar').style.width = Math.round(((i+1)/files.length)*100) + '%';
        }
        document.getElementById('modalUploadProgress').classList.remove('active');
        document.getElementById('uploadProgressBar').style.width = '0%';
        Toast.fire({ icon: 'success', title: 'Upload completed' }); loadFiles(currentPath);
    }

    function toggleTheme() {
        const body = document.documentElement;
        const text = document.getElementById('themeText'); const circle = document.getElementById('themeCircle'); const btn = document.querySelector('.theme-toggle-btn');
        body.classList.toggle('dark');
        if (!body.classList.contains('dark')) {
            localStorage.setItem('emerald_theme', 'light'); text.innerText = 'Light Mode';
            circle.style.transform = 'translateX(24px)'; btn.classList.add('bg-emerald-400/50'); btn.classList.remove('bg-slate-700/50');
            if(editorInstance) editorInstance.setOption('theme', 'default');
        } else {
            localStorage.setItem('emerald_theme', 'dark');
            text.innerText = 'Dark Mode'; circle.style.transform = 'translateX(0)'; btn.classList.remove('bg-emerald-400/50'); btn.classList.add('bg-slate-700/50');
            if(editorInstance) editorInstance.setOption('theme', 'monokai');
        }
    }

    function stringToColor(str) { let hash = 0;
        for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
        return `hsl(${Math.abs(hash) % 360}, 70%, 65%)`; 
    }

    function closeModal(id) { document.getElementById(id).classList.remove('active');
    }

    async function loadSysInfo() {
        const res = await fetch('/index.php?api=sys_info').then(r=>r.json()).catch(e => { return {stats:{}, extended:{}, logs:[], activity:[]}; });
        if(!res.stats) return;
        
        if (res.extended) {
            document.getElementById('dashDisk').innerText = `${res.extended.disk_free} / ${res.extended.disk_total}`;
            document.getElementById('dashSapi').innerText = res.extended.php_sapi;
        }

        const logsList = document.getElementById('logsList'); logsList.innerHTML = '';
        const logsData = Array.isArray(res.logs) ? res.logs : Object.values(res.logs || {});
        if(logsData.length > 0) {
            logsData.forEach(l => {
                if(!l || !l.time) return;
                const date = new Date(l.time * 1000).toLocaleString();
                const status = l.status === 'Success' ? '<span class="text-emerald-400 bg-emerald-500/20 px-2 py-1 rounded-md text-[9px] font-bold uppercase tracking-widest border border-emerald-500/30">SUCCESS</span>' : '<span class="text-rose-400 bg-rose-500/20 px-2 py-1 rounded-md text-[9px] font-bold uppercase tracking-widest border border-rose-500/30">FAILED</span>';
                logsList.innerHTML += `<tr><td class="px-6 py-4 text-[var(--text-muted)] text-xs font-mono">${date}</td><td class="px-4 py-4 text-[var(--text-main)] font-bold text-sm"><i class="fa-solid fa-user-shield text-[var(--text-muted)] mr-2 text-xs"></i>${l.user}</td><td class="px-4 py-4 text-brand-400 font-mono text-xs">${l.ip}</td><td class="px-4 py-4 text-right">${status}</td></tr>`;
            });
        } else {
            logsList.innerHTML = `<tr><td colspan="4" class="px-6 py-8 text-center text-[var(--text-muted)] text-sm">No access logs recorded.</td></tr>`;
        }
        
        const activityList = document.getElementById('activityList');
        if(activityList) {
            activityList.innerHTML = '';
            const activityData = Array.isArray(res.activity) ? res.activity : Object.values(res.activity || {});
            if (activityData.length > 0) {
                activityData.forEach(a => {
                    if(!a || !a.time) return;
                    const date = new Date(a.time * 1000).toLocaleString();
                    activityList.innerHTML += `<tr><td class="px-6 py-4 text-[var(--text-muted)] text-xs font-mono">${date}</td><td class="px-4 py-4 text-[var(--text-main)] font-bold text-sm"><i class="fa-solid fa-user-shield text-[var(--text-muted)] mr-2 text-xs"></i>${a.user}</td><td class="px-4 py-4 text-accent-400 font-mono text-xs">${a.detail}</td></tr>`;
                });
            } else {
                activityList.innerHTML = `<tr><td colspan="3" class="px-6 py-8 text-center text-[var(--text-muted)] text-sm">No activity recorded.</td></tr>`;
            }
        }
    }

    let authTarget = { action: '', id: '', path: '', extra: '' };
    function promptAuth(action, id, path = '', extra = '') {
        authTarget = { action, id, path, extra };
        swalDark.fire({
            title: 'Konfirmasi Eksekusi',
            html: '<p class="text-sm text-[var(--text-muted)] font-medium mb-4">Lanjutkan tindakan penghapusan/modifikasi pada data ini?</p>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Eksekusi',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if(result.isConfirmed) {
                const passField = document.getElementById('authPassword');
                if(passField) passField.value = 'bypass'; // Bypass visual legacy
                executeAuthorizedAction();
            }
        });
    }

    window.attemptAction = async function(action, filename, owner) {
        if(action === 'delete_file') {
            promptAuth('delete_file', filename, currentPath);
        } else if (action === 'zip_file' || action === 'unzip_file') {
            const fd = new FormData();
            fd.append('file', filename); fd.append('path', currentPath);
            const res = await fetch('/index.php?api=' + action, { method: 'POST', body: fd }).then(r=>r.json());
            if(res.status === 'success') { Toast.fire({icon:'success', title: 'Action Executed'}); loadFiles(currentPath); }
            else Toast.fire({icon:'error', title: res.message});
        }
    };

    window.downloadFolderAsZip = async function(folderName) {
        Toast.fire({icon:'info', title:'Compressing directory...'});
        const fd = new FormData();
        fd.append('file', folderName); fd.append('path', currentPath);
        const res = await fetch('/index.php?api=zip_file', { method: 'POST', body: fd }).then(r=>r.json());
        
        if(res.status === 'success') {
            Toast.fire({icon:'success', title: 'Download starting...'});
            loadFiles(currentPath);
            const zipName = folderName + '.zip';
            const fileRoute = currentPath ? `${currentPath}/${zipName}` : zipName;
            const link = document.createElement('a');
            link.href = `/emerald_assets/${fileRoute}`;
            link.download = zipName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } else {
            Toast.fire({icon:'error', title: res.message});
        }
    };

    async function executeAuthorizedAction() {
        const pass = document.getElementById('authPassword').value;
        if(!pass) return Toast.fire({icon:'error', title:'Passphrase empty'});
        
        const fd = new FormData(); fd.append('auth_pass', pass);
        if (authTarget.action === 'delete_file') {
            fd.append('file', authTarget.id); fd.append('path', authTarget.path);
            const res = await fetch('/index.php?api=delete_file', { method: 'POST', body: fd }).then(r=>r.json());
            handleAuthResponse(res, () => loadFiles(currentPath));
        } else if (authTarget.action === 'multi_delete') {
            fd.append('files', JSON.stringify(authTarget.id));
            fd.append('path', currentPath);
            const res = await fetch('/index.php?api=multi_delete', { method: 'POST', body: fd }).then(r=>r.json());
            handleAuthResponse(res, () => { loadFiles(currentPath); resetSelection(); });
        } else if (authTarget.action === 'delete_note') {
            fd.append('id', authTarget.id);
            const res = await fetch('/index.php?api=delete_note', { method: 'POST', body: fd }).then(r=>r.json());
            handleAuthResponse(res, () => loadNotes());
        } else if (authTarget.action === 'delete_cloaking') {
            fd.append('id', authTarget.id);
            const res = await fetch('/index.php?api=delete_cloaking', { method: 'POST', body: fd }).then(r=>r.json());
            handleAuthResponse(res, () => loadCloaking());
        } else if (authTarget.action === 'delete_user') {
            fd.append('target_user', authTarget.id);
            fd.append('migrate_to', authTarget.extra);
            const res = await fetch('/index.php?api=delete_user', { method: 'POST', body: fd }).then(r=>r.json());
            handleAuthResponse(res, () => { closeModal('modalDeleteUser'); loadUsers(); });
        } else if (authTarget.action === 'delete_firewall') {
            fd.append('id', authTarget.id);
            const res = await fetch('/index.php?api=delete_firewall', { method: 'POST', body: fd }).then(r=>r.json());
            handleAuthResponse(res, () => loadFirewall());
        } else if (authTarget.action === 'kill_process') {
            fd.append('pid', authTarget.id);
            const res = await fetch('index.php?api=kill_process', { method: 'POST', body: fd }).then(r=>r.json());
            handleAuthResponse(res, () => loadMonitor());
        }
    }

    function handleAuthResponse(res, successCallback) {
        if(res.status === 'success') { closeModal('modalAuthPrompt');
            Toast.fire({icon:'success', title:'Action Executed'}); successCallback(); } 
        else { swalDark.fire({icon:'error', title:'Denied', html:`<p class="text-[var(--text-muted)] text-sm">${res.message}</p>`});
        }
    }

    function openProfileModal() {
        document.getElementById('profUsername').value = currentUser;
        document.getElementById('profPassword').value = '';
        document.getElementById('profSecQ').value = ''; document.getElementById('profSecA').value = '';
        document.getElementById('profileAvatarPreview').src = document.getElementById('sidebarAvatar').src;
        document.getElementById('modalProfile').classList.add('active');
    }

    function openAvatarZoom(src) {
        document.getElementById('avatarZoomImg').src = src; document.getElementById('modalAvatarZoom').classList.add('active');
    }

    function handleAvatarUpload(event) {
        const file = event.target.files[0]; if(!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement('canvas');
                const MAX_WIDTH = 256; const MAX_HEIGHT = 256;
                let width = img.width; let height = img.height;
                if (width > height) { if (width > MAX_WIDTH) { height *= MAX_WIDTH / width; width = MAX_WIDTH;
                } } else { if (height > MAX_HEIGHT) { width *= MAX_HEIGHT / height; height = MAX_HEIGHT;
                } }
                canvas.width = width;
                canvas.height = height; const ctx = canvas.getContext('2d'); ctx.drawImage(img, 0, 0, width, height);
                const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
                document.getElementById('profileAvatarPreview').src = dataUrl;
                document.getElementById('profAvatarBase64').value = dataUrl;
            }
            img.src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
    
    async function updateProfile() {
        const fd = new FormData();
        fd.append('username', document.getElementById('profUsername').value); fd.append('password', document.getElementById('profPassword').value);
        fd.append('avatar', document.getElementById('profAvatarBase64').value); fd.append('sec_q', document.getElementById('profSecQ').value); fd.append('sec_a', document.getElementById('profSecA').value);
        const res = await fetch('/index.php?api=update_profile', { method: 'POST', body: fd }).then(r=>r.json());
        if(res.status === 'success') {
            Toast.fire({icon:'success', title:'Identity Updated'});
            if(res.new_user !== currentUser) window.location.reload(); 
            closeModal('modalProfile'); loadUsers(); 
        } else { Toast.fire({icon:'error', title:res.message});
        }
    }

    // --- BULK FILE ACTIONS ---
    function toggleSelectAll(source) {
        const checkboxes = document.querySelectorAll('.file-sel');
        checkboxes.forEach(cb => {
            if (cb.closest('tr').style.display !== 'none') { cb.checked = source.checked; }
        });
        updateBulkToolbar();
    }

    function updateBulkToolbar() {
        const checked = document.querySelectorAll('.file-sel:checked');
        const toolbar = document.getElementById('bulkToolbar');
        const pasteToolbar = document.getElementById('pasteToolbar');
        
        if (clipboard.files.length > 0) {
            pasteToolbar.classList.remove('hidden');
            pasteToolbar.classList.add('flex');
            document.getElementById('pasteInfo').innerText = `${clipboard.files.length} items to ${clipboard.action}`;
        } else {
            pasteToolbar.classList.add('hidden');
            pasteToolbar.classList.remove('flex');
        }

        if (checked.length > 0) {
            toolbar.classList.remove('hidden');
            toolbar.classList.add('flex');
            document.getElementById('selCount').innerText = checked.length;
        } else {
            toolbar.classList.add('hidden'); toolbar.classList.remove('flex');
        }
    }

    function bulkAction(action) {
        const checked = Array.from(document.querySelectorAll('.file-sel:checked')).map(cb => cb.value);
        if(checked.length === 0) return;
        
        if (action === 'delete') {
            swalDark.fire({
                title: 'Purge Selected?', html: `<p class="text-[var(--text-muted)] text-sm">Delete ${checked.length} selected items permanently?</p>`,
                showCancelButton: true, confirmButtonText: 'Yes, Purge'
            }).then((result) => {
                if(result.isConfirmed) { promptAuth('multi_delete', checked, currentPath); }
            });
        } else {
            clipboard = { action: action, files: checked, sourcePath: currentPath };
            resetSelection();
            Toast.fire({ icon: 'info', title: `${checked.length} items ready to ${action}` });
        }
    }

    async function executePaste() {
        if(clipboard.files.length === 0) return;
        Toast.fire({ icon: 'info', title: `Processing ${clipboard.action}...` });
        const fd = new FormData();
        fd.append('files', JSON.stringify(clipboard.files)); fd.append('source_path', clipboard.sourcePath); fd.append('target_path', currentPath); fd.append('mode', clipboard.action);
        const res = await fetch('/index.php?api=paste_files', { method: 'POST', body: fd }).then(r=>r.json());
        if(res.status === 'success') {
            if(clipboard.action === 'cut') clipboard = { action: '', files: [], sourcePath: '' };
            Toast.fire({ icon: 'success', title: 'Action Successful' });
            loadFiles(currentPath); resetSelection();
        } else { Toast.fire({ icon: 'error', title: res.message });
        }
    }

    function cancelPaste() { clipboard = { action: '', files: [], sourcePath: '' };
        updateBulkToolbar(); }

    function resetSelection() {
        document.querySelectorAll('.file-sel').forEach(cb => cb.checked = false);
        const sa = document.getElementById('selectAllCheckbox'); if(sa) sa.checked = false;
        updateBulkToolbar();
    }

    async function loadFiles(path = '') {
        currentPath = path;
        document.getElementById('assetSearchFilter').value = '';
        const res = await fetch(`/index.php?api=list_files&path=${path}`).then(r => r.json()).catch(e => { return {files:[]}; });
        globalFilesData = res.files;
        const breadcrumb = document.getElementById('breadcrumb'); const btnUp = document.getElementById('btnNavUp');
        if(path) { breadcrumb.innerHTML = `<i class="fa-solid fa-layer-group"></i> / root / <span class="text-brand-400 font-bold">${path}</span>`;
            btnUp.style.display = 'flex'; } 
        else { breadcrumb.innerHTML = `<i class="fa-solid fa-layer-group"></i> / root`;
            btnUp.style.display = 'none'; }
        renderFiles(res.files, 1); resetSelection();
    }

    function navigateUp() { if(!currentPath) return; let parts = currentPath.split('/'); parts.pop(); loadFiles(parts.join('/'));
    }
    function navigateDown(folder) { let newPath = currentPath ? currentPath + '/' + folder : folder; loadFiles(newPath);
    }

    function getExtIcon(ext) {
        ext = ext.toLowerCase();
        if(ext === 'zip') return '<div class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center border border-amber-500/30 shadow-inner"><i class="fa-solid fa-file-zipper text-amber-400 text-lg"></i></div>';
        if(['php','html','css','js','json'].includes(ext)) return '<div class="w-10 h-10 rounded-xl bg-brand-500/20 flex items-center justify-center border border-brand-500/30 shadow-inner"><i class="fa-solid fa-file-code text-brand-400 text-lg"></i></div>';
        if(['png','jpg','jpeg','gif'].includes(ext)) return '<div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center border border-emerald-500/30 shadow-inner"><i class="fa-solid fa-image text-emerald-400 text-lg"></i></div>';
        if(ext === 'txt') return '<div class="w-10 h-10 rounded-xl bg-[var(--input-bg)] flex items-center justify-center border border-[var(--glass-border)] shadow-inner"><i class="fa-solid fa-file-lines text-[var(--text-muted)] text-lg"></i></div>';
        return '<div class="w-10 h-10 rounded-xl bg-[var(--input-bg)] flex items-center justify-center border border-[var(--glass-border)] shadow-inner"><i class="fa-solid fa-file text-[var(--text-muted)] text-lg"></i></div>';
    }

    function filterAssets() {
        const q = document.getElementById('assetSearchFilter').value.toLowerCase();
        const trs = document.getElementById('filesList').getElementsByTagName('tr');
        Array.from(trs).forEach(tr => {
            const name = tr.getAttribute('data-filename') || '';
            tr.style.display = name.toLowerCase().includes(q) ? '' : 'none';
        });
    }

    async function promptRename(oldName) {
        const { value: newName } = await swalDark.fire({
            title: 'Rename Asset',
            html: `<p class="text-sm text-[var(--text-muted)] mb-4">Enter new name for <b class="text-[var(--text-main)]">${oldName}</b>.</p>`,
            input: 'text',
            inputValue: oldName,
            showCancelButton: true
        });
        
        if (newName && newName !== oldName) {
            const fd = new FormData();
            fd.append('old_name', oldName); fd.append('new_name', newName); fd.append('path', currentPath);
            const res = await fetch('/index.php?api=rename_file', { method: 'POST', body: fd }).then(r => r.json());
            if (res.status === 'success') { Toast.fire({icon: 'success', title: 'Asset Renamed'}); loadFiles(currentPath);
            } 
            else { Toast.fire({icon: 'error', title: res.message});
            }
        }
    }

    function renderFiles(files, page = 1) {
        currentAssetsPage = page;
        const tbody = document.getElementById('filesList'); tbody.innerHTML = '';
        
        const sortedFiles = files.sort((a,b) => b.is_dir - a.is_dir || a.name.localeCompare(b.name));
        const totalItems = sortedFiles.length;
        const totalPages = Math.ceil(totalItems / ASSETS_PER_PAGE);
        const startIdx = (page - 1) * ASSETS_PER_PAGE;
        const endIdx = startIdx + ASSETS_PER_PAGE;
        const paginatedFiles = sortedFiles.slice(startIdx, endIdx);
        paginatedFiles.forEach(f => {
            const icon = f.is_dir ? '<div class="w-10 h-10 rounded-xl bg-brand-500/20 flex items-center justify-center border border-brand-500/30 shadow-inner"><i class="fa-solid fa-folder text-brand-400 text-lg"></i></div>' : getExtIcon(f.ext);
            const baseUrl = window.location.origin; const fileRoute = currentPath ? `${currentPath}/${f.link_name}` : f.link_name;
            const cleanUrl = `${baseUrl}/view/${fileRoute}`;
            
            const color = stringToColor(f.owner);
 
            const trClick = `onclick="if(${f.is_dir}) { navigateDown('${f.name}') } else { previewFile('${f.name}') }"`;
            const wgetCmd = `wget ${cleanUrl} -O ${f.name}`;

            tbody.innerHTML += `
                <tr class="group cursor-pointer hover:bg-[var(--glass-hover)] transition-all" data-filename="${f.name}" ${trClick}>
                    <td class="px-6 py-4 text-center" onclick="event.stopPropagation()">
                        <input type="checkbox" class="file-sel file-checkbox" value="${f.name}" onchange="updateBulkToolbar()">
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex items-center gap-4">
                            ${icon}
                            <div>
                                <div class="font-bold text-[var(--text-main)] tracking-tight text-sm group-hover:text-brand-400 transition-colors">${f.name}</div>
                                ${!f.is_dir ? `<div class="text-[10px] text-[var(--text-muted)] font-mono mt-1 truncate max-w-[250px]" title="${cleanUrl}">${cleanUrl}</div>` : `<div class="text-[10px] text-[var(--text-muted)] font-mono mt-1 uppercase tracking-widest">Directory</div>`}
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 font-mono text-[var(--text-muted)] font-bold uppercase tracking-widest text-[10px]">${f.ext}</td>
                    <td class="px-6 py-4 text-[var(--text-muted)] text-sm">${f.size}</td>
                    <td class="px-6 py-4 text-[var(--text-muted)] font-mono text-xs">${f.modified}</td>
                    <td class="px-6 py-4" onclick="event.stopPropagation()">
                        <span class="px-3 py-1.5 rounded-md text-[9px] font-extrabold uppercase tracking-widest border bg-[var(--input-bg)] shadow-sm" style="color: ${color}; border-color: ${color}40;">${f.owner}</span>
                    </td>
                    <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity" onclick="event.stopPropagation()">
                        ${(!f.is_dir && f.ext === 'zip') ? `<button onclick="attemptAction('unzip_file', '${f.name}', '${f.owner}')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-amber-400 hover:text-white hover:bg-amber-500 hover:border-amber-400 transition-all shadow-sm" title="Extract"><i class="fa-solid fa-box-open"></i></button>` : ''}
                        ${(!f.is_dir && f.ext !== 'zip') ? `<button onclick="attemptAction('zip_file', '${f.name}', '${f.owner}')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-amber-400 hover:border-amber-400/50 hover:bg-amber-500/10 transition-all shadow-sm" title="Compress"><i class="fa-solid fa-file-zipper"></i></button>` : ''}
                        ${f.is_dir ? `<button onclick="attemptAction('zip_file', '${f.name}', '${f.owner}')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-amber-400 hover:border-amber-400/50 hover:bg-amber-500/10 transition-all shadow-sm" title="Compress"><i class="fa-solid fa-file-zipper"></i></button>` : ''}

                        ${!f.is_dir ? `<button onclick="copyToClipboard('${wgetCmd.replace(/'/g,"\\'").replace(/"/g,"&quot;")}')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-brand-400 hover:text-white hover:bg-brand-500 hover:border-brand-400 transition-all shadow-sm" title="Copy Wget"><i class="fa-solid fa-terminal"></i></button>` : ''}
                        ${!f.is_dir ? `<a href="${cleanUrl}" target="_blank" class="w-9 h-9 inline-flex items-center justify-center rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-emerald-400 hover:text-white hover:bg-emerald-500 hover:border-emerald-400 transition-all shadow-sm" title="Open Link"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>` : ''}
                        ${!f.is_dir ? `<a href="/emerald_assets/${currentPath ? currentPath+'/' : ''}${f.name}" download class="w-9 h-9 inline-flex items-center justify-center rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-[var(--text-main)] hover:bg-[var(--glass-hover)] transition-all shadow-sm" title="Download File"><i class="fa-solid fa-download"></i></a>` : ''}
                        ${f.is_dir ? `<button onclick="downloadFolderAsZip('${f.name}')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-emerald-400 hover:text-white hover:bg-emerald-500 hover:border-emerald-400 transition-all shadow-sm" title="Download Folder as ZIP"><i class="fa-solid fa-cloud-arrow-down"></i></button>` : ''}
                        <button onclick="promptRename('${f.name}')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-blue-400 hover:border-blue-400/50 hover:bg-blue-500/10 transition-all shadow-sm" title="Rename"><i class="fa-solid fa-pen"></i></button>
                        <button onclick="attemptAction('delete_file', '${f.name}', '${f.owner}')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-rose-400 hover:border-rose-400/50 hover:bg-rose-500/10 transition-all shadow-sm" title="Erase"><i class="fa-solid fa-trash"></i></button>
                        <button onclick="attemptAction('delete_file', '${f.name}', '${f.owner}')" class="w-9 h-9 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-rose-400 hover:border-rose-400/50 hover:bg-rose-500/10 transition-all shadow-sm" title="Erase"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>
            `;
        });

        let pagHtml = `<div class="flex items-center justify-between"><span class="text-xs text-[var(--text-muted)] font-bold tracking-wide">Showing ${startIdx + 1} to ${Math.min(endIdx, totalItems)} of ${totalItems} Assets</span><div class="flex gap-2">`;
        if (page > 1) pagHtml += `<button class="px-5 py-2.5 bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-xl text-xs font-bold hover:bg-[var(--glass-hover)] transition-all btn-animated shadow-sm" onclick="renderFiles(globalFilesData, ${page - 1})">Prev</button>`;
        if (page < totalPages) pagHtml += `<button class="px-5 py-2.5 bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-xl text-xs font-bold hover:bg-[var(--glass-hover)] transition-all btn-animated shadow-sm" onclick="renderFiles(globalFilesData, ${page + 1})">Next</button>`;
        pagHtml += `</div></div>`;
        document.getElementById('assetsPagination').innerHTML = pagHtml;
    }

    async function promptCreateFolder() {
        const { value: folderName } = await swalDark.fire({ 
            title: 'New Directory', html: '<p class="text-sm text-[var(--text-muted)] mb-4">Enter a name for the new directory.</p>',
            input: 'text', inputPlaceholder: 'e.g., project_assets', showCancelButton: true 
        });
        if (folderName) {
            const fd = new FormData();
            fd.append('folder', folderName); fd.append('path', currentPath);
            const res = await fetch('/index.php?api=create_folder', { method: 'POST', body: fd }).then(r => r.json());
            if(res.status === 'success') loadFiles(currentPath); else Toast.fire({icon:'error', title:res.message});
        }
    }

    async function promptCreateFile() {
        const { value: fileName } = await swalDark.fire({ 
            title: 'New File', html: '<p class="text-sm text-[var(--text-muted)] mb-4">Enter filename with extension.</p>',
            input: 'text', inputPlaceholder: 'script.php', showCancelButton: true 
        });
        if (fileName) {
            const fd = new FormData();
            fd.append('file', fileName); fd.append('path', currentPath);
            const res = await fetch('/index.php?api=create_file', { method: 'POST', body: fd }).then(r => r.json());
            if(res.status === 'success') { loadFiles(currentPath); editFile(fileName); } else Toast.fire({icon:'error', title:res.message});
        }
    }

    window.previewFile = async function(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const fileRoute = currentPath ? `${currentPath}/${filename}` : filename;
        const cleanUrl = `${window.location.origin}/emerald_assets/${fileRoute}`;

        document.getElementById('previewTitle').innerText = filename;
        const contentArea = document.getElementById('previewContentArea');
        contentArea.innerHTML = '<div class="absolute inset-0 flex items-center justify-center text-white"><i class="fa-solid fa-circle-notch fa-spin text-5xl opacity-50"></i></div>';
        document.getElementById('modalPreviewAsset').classList.add('active');

        const btnEdit = document.getElementById('btnEditPreview');
        btnEdit.onclick = function() { closeModal('modalPreviewAsset'); editFile(filename); };

        if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico'].includes(ext)) {
            contentArea.innerHTML = `<img src="${cleanUrl}" class="max-w-full max-h-full m-auto object-contain shadow-2xl rounded-lg border border-[var(--glass-border)]">`;
        } else if (['mp4', 'webm', 'ogg'].includes(ext)) {
            contentArea.innerHTML = `<video controls src="${cleanUrl}" class="max-w-full max-h-full m-auto shadow-2xl rounded-lg border border-[var(--glass-border)]"></video>`;
        } else {
            const fd = new FormData();
            fd.append('file', filename); fd.append('path', currentPath);
            const res = await fetch('/index.php?api=read_file', { method: 'POST', body: fd }).then(r => r.json());
            if (res.status === 'success') {
                const escaped = res.content.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
                contentArea.innerHTML = `<pre class="text-gray-300 font-mono text-sm w-full h-full text-left bg-transparent p-8 whitespace-pre-wrap overflow-auto custom-scrollbar select-all cursor-text">${escaped}</pre>`;
            } else {
                contentArea.innerHTML = `<div class="absolute inset-0 flex items-center justify-center text-rose-400 font-bold text-lg"><i class="fa-solid fa-triangle-exclamation mr-3 text-2xl"></i> Error loading preview</div>`;
            }
        }
    };
    
    async function editFile(filename) {
        const fd = new FormData(); fd.append('file', filename); fd.append('path', currentPath);
        const res = await fetch('/index.php?api=read_file', { method: 'POST', body: fd }).then(r => r.json());
        if (res.status === 'success') {
            currentEditorFile = filename;
            const ext = filename.split('.').pop().toLowerCase();
            document.getElementById('editorTitle').innerText = filename;
            document.getElementById('editorModified').innerText = res.modified;
            document.getElementById('editorSize').innerText = res.size;
            
            let mode = "htmlmixed";
            if (ext === 'php') mode = "php"; else if (ext === 'js') mode = "javascript";
            else if (ext === 'css') mode = "css";
            editorInstance.setOption("mode", mode);
            editorInstance.setValue(res.content);
            document.getElementById('modalEditor').classList.add('active');
            setTimeout(() => { editorInstance.refresh(); }, 150);
        }
    }

    async function saveFileEditor() {
        const fd = new FormData();
        fd.append('file', currentEditorFile); fd.append('path', currentPath); fd.append('content', editorInstance.getValue());
        await fetch('/index.php?api=save_file', { method: 'POST', body: fd });
        Toast.fire({ icon: 'success', title: 'Source synchronized' }); closeModal('modalEditor'); loadFiles(currentPath);
    }

    // --- CONTAINERS ---
    async function loadNotes() {
        const res = await fetch('/index.php?api=list_notes').then(r => r.json()).catch(e => { return []; });
        globalNotesData = res; renderNotes(res);
    }

    function renderNotes(notes) {
        const listArea = document.getElementById('notesListArea');
        listArea.innerHTML = '';
        notes.sort((a,b) => b.timestamp - a.timestamp).forEach(note => {
            const avatarUrl = note.avatar || `https://ui-avatars.com/api/?name=${note.owner}&background=0ea5e9&color=fff&rounded=true&bold=true`;
            const date = new Date(note.timestamp * 1000).toLocaleString();
            
            let data = {}; try { data = JSON.parse(note.data); } catch(e){}
            let statusColor = 'bg-emerald-400 shadow-[0_0_10px_rgba(52,211,153,0.5)]'; let statusText = 'ACTIVE';
   
            if(data.status === 'new') { statusColor = 'bg-slate-400 shadow-[0_0_10px_rgba(148,163,184,0.5)]'; statusText = 'NEW'; }
            else if(data.status === 'deactive') { statusColor = 'bg-rose-500 shadow-[0_0_10px_rgba(244,63,94,0.5)]'; statusText = 'DEACTIVE'; }

            listArea.innerHTML += `
                <div class="card-premium p-6 flex items-center cursor-pointer group" onclick="viewContainer('${note.id}')">
                    <div class="absolute top-5 right-5 flex items-center gap-2 bg-[var(--input-bg)] px-3 py-1 rounded-lg border border-[var(--glass-border)] backdrop-blur-md">
                        <div class="w-2.5 h-2.5 rounded-full ${statusColor}"></div>
                        <span class="text-[9px] font-extrabold text-[var(--text-muted)] tracking-widest">${statusText}</span>
                    </div>
            
                    <img src="${avatarUrl}" class="w-14 h-14 rounded-full border-2 border-[var(--glass-border)] object-cover shadow-[0_0_15px_rgba(255,255,255,0.05)] mr-5">
                    <div class="flex-1 overflow-hidden pr-20">
                        <h3 class="font-extrabold text-[var(--text-main)] text-lg tracking-tight truncate group-hover:text-brand-400 transition-colors">${note.title}</h3>
                        <p class="text-[10px] text-[var(--text-muted)] font-mono mt-1 uppercase tracking-widest"><i class="fa-regular fa-clock mr-1.5"></i> ${date}</p>
                    </div>
                </div>`;
        });
    }

    window.viewContainer = function(id) {
        const note = globalNotesData.find(n => n.id === id);
        if(!note) return;
        
        let data = { auth: {host:'', user:'', pass:'', dir:''}, list: '' };
        try { data = JSON.parse(note.data);
        } catch(e){}

        document.getElementById('viewContainerTitle').innerText = note.title;
        document.getElementById('viewContainerOwner').innerHTML = `<i class="fa-solid fa-user-shield mr-1.5"></i> ${note.owner}`;
        document.getElementById('viewContainerAvatar').src = note.avatar || `https://ui-avatars.com/api/?name=${note.owner}&background=0ea5e9&color=fff&rounded=true&bold=true`;
        
        document.getElementById('viewContainerActions').innerHTML = `
            <button onclick="closeModal('modalViewContainer'); editContainer('${note.id}')" class="bg-[var(--input-bg)] border border-brand-500/40 text-brand-400 px-5 py-2.5 rounded-2xl text-sm font-bold hover:bg-brand-500 hover:text-white transition-all btn-animated shadow-sm"><i class="fa-solid fa-pen mr-2"></i>Edit</button>
            <button onclick="closeModal('modalViewContainer'); promptAuth('delete_note', '${note.id}')" class="bg-[var(--input-bg)] border border-rose-500/40 text-rose-400 px-5 py-2.5 rounded-2xl text-sm font-bold hover:bg-rose-500 hover:text-white transition-all btn-animated shadow-sm"><i class="fa-solid fa-trash mr-2"></i>Delete</button>
        `;
        let authHTML = '';
        if(data.auth.host || data.auth.user || data.auth.dir) {
            authHTML = `
                <div class="bg-[var(--glass-card)] border border-[var(--glass-border)] rounded-[2rem] p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4 border-b border-[var(--glass-border)] pb-3">
                        <i class="fa-solid fa-server text-accent-400 text-lg"></i><span class="text-sm font-bold text-[var(--text-main)] tracking-widest uppercase">Auth Config</span>
                    </div>
                    <ul class="text-sm font-mono text-[var(--text-muted)] space-y-3">
                        <li class="flex items-center bg-[var(--input-bg)] p-3 rounded-2xl border border-[var(--glass-border)] shadow-inner"><span class="text-[var(--text-muted)] font-bold w-20 text-xs">HOST</span><span class="cursor-pointer truncate text-accent-300 font-bold hover:underline hover:opacity-80 transition-all" onclick="copyToClipboard(this.innerText)" title="Click to copy">${data.auth.host || '-'}</span></li>
                        <li class="flex items-center bg-[var(--input-bg)] p-3 rounded-2xl border border-[var(--glass-border)] shadow-inner"><span class="text-[var(--text-muted)] font-bold w-20 text-xs">USER</span><span class="cursor-pointer truncate text-brand-300 font-bold hover:underline hover:opacity-80 transition-all" onclick="copyToClipboard(this.innerText)" title="Click to copy">${data.auth.user || '-'}</span></li>
                        <li class="flex items-center bg-[var(--input-bg)] p-3 rounded-2xl border border-[var(--glass-border)] shadow-inner"><span class="text-[var(--text-muted)] font-bold w-20 text-xs">PASS</span><span class="cursor-pointer truncate text-rose-400 font-bold hover:underline hover:opacity-80 transition-all" onclick="copyToClipboard(this.innerText)" title="Click to copy">${data.auth.pass || '-'}</span></li>
                        <li class="flex items-center bg-[var(--input-bg)] p-3 rounded-2xl border border-[var(--glass-border)] shadow-inner"><span class="text-[var(--text-muted)] font-bold w-20 text-xs">DIR</span><span class="cursor-pointer truncate text-emerald-400 font-bold hover:underline hover:opacity-80 transition-all" onclick="copyToClipboard(this.innerText)" title="Click to copy">${data.auth.dir || '-'}</span></li>
                    </ul>
                </div>
            `;
        }

        let listHTML = ''; let gsocketHTML = '';
        if(data.list && data.list.trim() !== '') {
            const lines = data.list.split('\n');
            lines.forEach(line => {
                let cl = line.trim(); if(!cl) return;
                if(cl.startsWith('http://') || cl.startsWith('https://')) {
                    listHTML += `<div class="flex items-center gap-4 py-3 border-b border-[var(--glass-border)] last:border-0 hover:bg-[var(--glass-hover)] transition-colors px-4 rounded-xl group"><a href="${cl}" target="_blank" class="text-sm truncate text-emerald-400 hover:text-emerald-300 font-mono flex-1 transition-colors hover:underline">${cl}</a><i class="fa-solid fa-arrow-up-right-from-square text-emerald-400 text-xs opacity-0 group-hover:opacity-100 transition-opacity"></i></div>`;
       
                } else if(cl.includes('gs-netcat') || cl.startsWith('S=')) {
                    gsocketHTML += `<div class="flex items-center gap-4 py-3 border-b border-[var(--glass-border)] last:border-0 hover:bg-[var(--glass-hover)] transition-colors px-4 rounded-xl"><i class="fa-solid fa-terminal text-brand-400 text-xs"></i><span class="text-sm truncate text-brand-300 font-mono flex-1 cursor-pointer" onclick="copyToClipboard('${cl.replace(/'/g,"\\'").replace(/"/g,"&quot;")}')">${cl}</span></div>`;
                } else { 
                    listHTML += `<div class="flex items-center gap-4 py-3 border-b border-[var(--glass-border)] last:border-0 hover:bg-[var(--glass-hover)] transition-colors px-4 rounded-xl"><i class="fa-solid fa-align-left text-[var(--text-muted)] text-xs"></i><span class="text-sm truncate text-[var(--text-main)] font-mono flex-1 select-all">${cl}</span></div>`;
                }
            });
        }

        document.getElementById('viewContainerContent').innerHTML = `
            ${authHTML}
            ${listHTML ? `<div class="bg-[var(--glass-card)] border border-[var(--glass-border)] rounded-[2rem] p-6 shadow-sm"><div class="text-sm font-bold text-[var(--text-main)] tracking-widest mb-4 flex items-center gap-3 border-b border-[var(--glass-border)] pb-3 uppercase"><i class="fa-solid fa-list text-emerald-400"></i> Assets & Links</div>${listHTML}</div>` : ''}
            ${gsocketHTML ? `<div class="bg-[var(--glass-card)] border border-brand-500/30 rounded-[2rem] p-6 shadow-[0_0_20px_rgba(6,182,212,0.1)]"><div class="text-sm font-bold text-[var(--text-main)] tracking-widest mb-4 flex items-center gap-3 border-b border-[var(--glass-border)] pb-3 uppercase"><i class="fa-solid fa-terminal text-brand-400"></i> Terminal Commands</div>${gsocketHTML}</div>` : ''}
        `;
        document.getElementById('modalViewContainer').classList.add('active');
    };

    window.editContainer = function(id) { 
        const note = globalNotesData.find(n => n.id === id);
        if(!note) return;
        document.getElementById('containerId').value = note.id; document.getElementById('containerTitle').value = note.title;
        let data = { auth: {host:'', user:'', pass:'', dir:''}, list: '', status: 'active' };
        try { data = JSON.parse(note.data); } catch(e){}
        document.getElementById('containerHost').value = data.auth.host || '';
        document.getElementById('containerUser').value = data.auth.user || ''; 
        document.getElementById('containerPass').value = data.auth.pass || ''; document.getElementById('containerDir').value = data.auth.dir || '';
        document.getElementById('containerTextList').value = data.list || '';
        document.getElementById('containerStatus').value = data.status || 'active';
        document.getElementById('modalContainer').classList.add('active');
    };

    function openContainerModal() {
        document.getElementById('containerId').value = '';
        document.getElementById('containerTitle').value = '';
        document.getElementById('containerHost').value = ''; document.getElementById('containerUser').value = ''; 
        document.getElementById('containerPass').value = ''; document.getElementById('containerDir').value = '';
        document.getElementById('containerTextList').value = '';
        document.getElementById('containerStatus').value = 'active';
        document.getElementById('modalContainer').classList.add('active');
    }

    async function saveContainer() {
        const fd = new FormData();
        fd.append('id', document.getElementById('containerId').value); fd.append('title', document.getElementById('containerTitle').value || 'Untitled'); 
        fd.append('host', document.getElementById('containerHost').value); fd.append('user', document.getElementById('containerUser').value);
        fd.append('pass', document.getElementById('containerPass').value); fd.append('dir', document.getElementById('containerDir').value);
        fd.append('status', document.getElementById('containerStatus').value);
        fd.append('text_list', document.getElementById('containerTextList').value);
        await fetch('/index.php?api=save_note', { method: 'POST', body: fd }); 
        closeModal('modalContainer'); loadNotes(); Toast.fire({icon:'success', title:'Container Built'});
    }

    // --- CLOAKING ---
    async function loadCloaking() {
        const res = await fetch('/index.php?api=list_cloaking').then(r => r.json()).catch(e=>{return [];});
        globalCloakData = res; renderCloaking(res);
    }

    function renderCloaking(cloaks) {
        const grid = document.getElementById('cloakingGrid');
        grid.innerHTML = '';
        cloaks.sort((a,b) => b.timestamp - a.timestamp).forEach(c => {
            const isGlobal = c.type === 'global';
            const badgeClass = isGlobal ? 'bg-accent-500/20 text-accent-300 border-accent-500/40 shadow-[0_0_10px_rgba(139,92,246,0.3)]' : 'bg-brand-500/20 text-brand-300 border-brand-500/40 shadow-[0_0_10px_rgba(6,182,212,0.3)]';
            const avatarUrl = c.avatar || `https://ui-avatars.com/api/?name=${c.owner}&background=0ea5e9&color=fff&rounded=true&bold=true`;
            
            grid.innerHTML += `
                <div class="card-premium p-7 relative group cursor-pointer" onclick="viewCloaking('${c.id}')">
                    <div class="absolute top-5 right-5 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity" onclick="event.stopPropagation()">
                        <button onclick="editCloaking('${c.id}')" class="w-10 h-10 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-brand-400 flex items-center justify-center shadow-sm transition-colors"><i class="fa-solid fa-pen"></i></button>
                        <button onclick="promptAuth('delete_cloaking', '${c.id}')" class="w-10 h-10 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-rose-400 flex items-center justify-center shadow-sm transition-colors"><i class="fa-solid fa-trash"></i></button>
                    </div>
                    <div class="flex items-center gap-5 mb-5">
                        <img src="${avatarUrl}" class="w-12 h-12 rounded-full object-cover border-2 border-[var(--glass-border)] shadow-sm">
                        <div>
                            <h3 class="font-extrabold text-[var(--text-main)] text-lg tracking-tight truncate w-40">${c.domain}</h3>
                            <span class="px-3 py-1 rounded-md text-[9px] font-extrabold uppercase tracking-widest border mt-1 inline-block ${badgeClass}">${c.type}</span>
                        </div>
                    </div>
                    <div class="bg-[var(--input-bg)] border border-[var(--glass-border)] rounded-2xl p-4 font-mono text-xs text-brand-300 truncate mb-5 shadow-inner">
                        <span class="text-[var(--text-muted)]">PATH:</span> ${c.path}
                    </div>
                    <div class="text-[10px] text-[var(--text-muted)] uppercase tracking-widest font-bold">Node: <span class="text-[var(--text-main)]">${c.owner}</span></div>
                </div>
            `;
        });
    }

    window.viewCloaking = function(id) {
        const c = globalCloakData.find(x => x.id === id);
        if(!c) return;
        document.getElementById('viewContainerTitle').innerText = c.domain;
        document.getElementById('viewContainerOwner').innerHTML = `<i class="fa-solid fa-user-shield mr-1.5"></i> ${c.owner}`;
        document.getElementById('viewContainerAvatar').src = c.avatar || `https://ui-avatars.com/api/?name=${c.owner}&background=0ea5e9&color=fff&rounded=true&bold=true`;
        document.getElementById('viewContainerActions').innerHTML = `
            <button onclick="closeModal('modalViewContainer'); editCloaking('${c.id}')" class="bg-[var(--input-bg)] border border-brand-500/40 text-brand-400 px-5 py-2.5 rounded-2xl text-sm font-bold hover:bg-brand-500 hover:text-white transition-all btn-animated shadow-sm"><i class="fa-solid fa-pen mr-2"></i>Edit</button>
            <button onclick="closeModal('modalViewContainer'); promptAuth('delete_cloaking', '${c.id}')" class="bg-[var(--input-bg)] border border-rose-500/40 text-rose-400 px-5 py-2.5 rounded-2xl text-sm font-bold hover:bg-rose-500 hover:text-white transition-all btn-animated shadow-sm"><i class="fa-solid fa-trash mr-2"></i>Delete</button>
        `;
        document.getElementById('viewContainerContent').innerHTML = `
            <div class="bg-[var(--glass-card)] border border-[var(--glass-border)] rounded-[2rem] p-6 shadow-sm mb-8">
                <div class="flex items-center gap-3 mb-4 border-b border-[var(--glass-border)] pb-3">
                    <i class="fa-solid fa-globe text-accent-400 text-lg"></i><span class="text-sm font-bold text-[var(--text-main)] tracking-widest uppercase">Target Details</span>
                </div>
                <ul class="text-sm font-mono text-[var(--text-muted)] space-y-3">
                    <li class="flex items-center bg-[var(--input-bg)] p-3 rounded-2xl border border-[var(--glass-border)] shadow-inner"><span class="text-[var(--text-muted)] font-bold w-24 text-xs">DOMAIN</span><span class="cursor-pointer truncate text-[var(--text-main)] font-bold" onclick="copyToClipboard('${c.domain}')">${c.domain}</span></li>
                    <li class="flex items-center bg-[var(--input-bg)] p-3 rounded-2xl border border-[var(--glass-border)] shadow-inner"><span class="text-[var(--text-muted)] font-bold w-24 text-xs">PATH</span><span class="cursor-pointer truncate text-[var(--text-main)] font-bold" onclick="copyToClipboard('${c.path}')">${c.path}</span></li>
                    <li class="flex items-center bg-[var(--input-bg)] p-3 rounded-2xl border border-[var(--glass-border)] shadow-inner"><span class="text-[var(--text-muted)] font-bold w-24 text-xs">SCOPE</span><span class="truncate text-[var(--text-main)] font-bold uppercase">${c.type}</span></li>
                </ul>
            </div>
            <div class="bg-[var(--glass-card)] border border-[var(--glass-border)] rounded-[2rem] p-6 shadow-sm">
                <div class="flex items-center gap-3 mb-4 border-b border-[var(--glass-border)] pb-3">
                    <i class="fa-solid fa-code text-brand-400 text-lg"></i><span class="text-sm font-bold text-[var(--text-main)] tracking-widest uppercase">Payload Body</span>
                </div>
                <pre class="bg-black/40 border border-[var(--glass-border)] text-brand-100 p-5 rounded-2xl font-mono text-sm overflow-x-auto whitespace-pre-wrap select-all custom-scrollbar">${c.content}</pre>
            </div>
        `;
        document.getElementById('modalViewContainer').classList.add('active');
    };

    function openCloakingModal(cloak = null) {
        document.getElementById('cloakId').value = cloak ? cloak.id : ''; 
        document.getElementById('cloakDomain').value = cloak ? cloak.domain : '';
        document.getElementById('cloakPath').value = cloak ? cloak.path : '';
        document.getElementById('cloakType').value = cloak ? cloak.type : 'personal';
        document.getElementById('cloakContent').value = cloak ? cloak.content : ''; 
        document.getElementById('modalCloaking').classList.add('active');
    }

    function editCloaking(id) { const c = globalCloakData.find(x => x.id === id); if(c) openCloakingModal(c);
    }

    async function saveCloaking() {
        const fd = new FormData();
        fd.append('id', document.getElementById('cloakId').value); fd.append('domain', document.getElementById('cloakDomain').value);
        fd.append('path', document.getElementById('cloakPath').value); fd.append('type', document.getElementById('cloakType').value); fd.append('content', document.getElementById('cloakContent').value);
        await fetch('/index.php?api=save_cloaking', { method: 'POST', body: fd }); closeModal('modalCloaking'); loadCloaking();
        Toast.fire({icon:'success', title:'Rule Deployed'});
    }

    // --- USERS ---
    async function loadUsers() {
        const res = await fetch('/index.php?api=list_users').then(r => r.json()).catch(e=>{return [];});
        globalUsers = res;
        const tbody = document.getElementById('usersList'); tbody.innerHTML = '';
        res.forEach(u => { 
            let roleClass = 'role-guest';
            if(u.role === 'owner') roleClass = 'role-owner';
            if(u.role === 'admin') roleClass = 'role-admin';

            const avatarUrl = u.avatar ? u.avatar : `https://ui-avatars.com/api/?name=${u.username}&background=0ea5e9&color=fff&rounded=true&bold=true`;
            const isOnline = (Math.floor(Date.now()/1000) - (u.last_active || 0)) < 30; 
            
            if(u.username === currentUser) {
                document.getElementById('sidebarAvatar').src = avatarUrl;
                document.getElementById('sidebarRole').innerText = u.role;
                document.getElementById('headerRoleBadge').innerText = u.role;
                document.getElementById('headerRoleBadge').className = `role-badge ${roleClass} ml-2`;
                
                const sbDot = document.getElementById('sidebarDot');
                if(sbDot) sbDot.className = `w-3 h-3 rounded-full absolute -bottom-0.5 -right-0.5 border-2 border-[var(--bg-base)] ${isOnline ? 'bg-emerald-400 shadow-[0_0_8px_#34d399]' : 'bg-slate-500'}`;
            }

            const statusDot = isOnline ? '<div class="w-3.5 h-3.5 rounded-full bg-emerald-400 shadow-[0_0_8px_#34d399] absolute -bottom-0.5 -right-0.5 border-[3px] border-[var(--bg-base)]"></div>' : '<div class="w-3.5 h-3.5 rounded-full bg-slate-500 absolute -bottom-0.5 -right-0.5 border-[3px] border-[var(--bg-base)]"></div>';
            
            tbody.innerHTML += `
            <tr class="group hover:bg-[var(--glass-hover)] transition-colors">
                <td class="px-8 py-5 font-bold text-[var(--text-main)] flex items-center gap-5">
                    <div class="relative cursor-pointer hover:opacity-80 transition-opacity" onclick="openAvatarZoom('${avatarUrl}')">
                        <img src="${avatarUrl}" class="w-12 h-12 rounded-full border-2 border-[var(--glass-border)] shadow-sm object-cover">
                        <div id="user_dot_${u.username}">${statusDot}</div>
                    </div>
                    <div>
                        <span class="text-base block tracking-tight font-extrabold">${u.username}</span>
                        <span id="user_status_${u.username}" class="text-[10px] font-mono uppercase tracking-widest ${isOnline ? 'text-emerald-400 font-bold' : 'text-[var(--text-muted)]'}"><i class="fa-solid fa-circle text-[7px] mr-1.5"></i>${isOnline ? 'Online' : 'Offline'}</span>
                    </div>
                </td>
                <td class="px-6 py-5">
                    <span class="role-badge ${roleClass}"><i class="fa-solid fa-shield-halved mr-2"></i>${u.role}</span>
                </td>
                <td class="px-6 py-5 text-right opacity-0 group-hover:opacity-100 transition-opacity">
                    ${u.username === currentUser ? `<button onclick="promptDeleteUser('${u.username}')" class="w-10 h-10 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-rose-400 hover:border-rose-400/50 hover:bg-rose-500/10 transition-all shadow-sm" title="Delete Identity"><i class="fa-solid fa-user-minus text-sm"></i></button>` : ''}
                </td>
            </tr>`;
        });
    }

    function promptDeleteUser(username) {
        document.getElementById('delTargetUser').value = username;
        const select = document.getElementById('delMigrateTo');
        select.innerHTML = '<option value="">-- Destroy All Data --</option>';
        globalUsers.forEach(u => {
            if(u.username !== username) select.innerHTML += `<option value="${u.username}">Migrate Data To: ${u.username}</option>`;
        });
        document.getElementById('modalDeleteUser').classList.add('active');
    }

    function executeDeleteUser() {
        const target = document.getElementById('delTargetUser').value;
        const migrate = document.getElementById('delMigrateTo').value;
        promptAuth('delete_user', target, '', migrate);
    }

    function openUserModal() {
        if(getMyRole() !== 'owner') return swalDark.fire({icon:'error', title:'Denied', html:'<p class="text-[var(--text-muted)] text-sm">Only Owners can register users.</p>'});
        document.getElementById('newUserName').value = ''; document.getElementById('newUserPass').value = '';
        document.getElementById('modalUser').classList.add('active');
    }

    async function saveUser() {
        const user = document.getElementById('newUserName').value;
        const pass = document.getElementById('newUserPass').value; const role = document.getElementById('newUserRole').value;
        if(!user || !pass) return; const fd = new FormData(); fd.append('username', user);
        fd.append('password', pass); fd.append('role', role);
        const res = await fetch('/index.php?api=add_user', { method: 'POST', body: fd }).then(r => r.json());
        if(res.status === 'success') { closeModal('modalUser'); loadUsers(); Toast.fire({icon:'success',title:'Identity Registered'}); } else Toast.fire({icon:'error', title:res.message});
    }

    // --- FIREWALL ---
    async function loadFirewall() {
        const res = await fetch('/index.php?api=list_firewall').then(r => r.json()).catch(e=>{return [];});
        const tbody = document.getElementById('firewallList'); tbody.innerHTML = '';
        res.forEach(fw => { 
            const date = new Date(fw.added * 1000).toLocaleString();
            tbody.innerHTML += `
            <tr class="group hover:bg-[var(--glass-hover)] transition-colors">
                <td class="px-8 py-5 text-emerald-400 font-extrabold text-base tracking-widest">${fw.ip}</td>
                <td class="px-6 py-5 text-[var(--text-main)] font-sans text-sm font-medium">${fw.note}</td>
                <td class="px-6 py-5 text-[var(--text-muted)] text-xs font-mono">${date}</td>
                <td class="px-6 py-5 text-right opacity-0 group-hover:opacity-100 transition-opacity">
                    <button onclick="promptAuth('delete_firewall', '${fw.id}')" class="w-10 h-10 rounded-xl bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-rose-400 hover:border-rose-400/50 hover:bg-rose-500/10 transition-all shadow-sm" title="Remove IP"><i class="fa-solid fa-trash text-sm"></i></button>
                </td>
            </tr>`; 
        });
    }

    function openFirewallModal() {
        if(getMyRole() !== 'owner') return swalDark.fire({icon:'error', title:'Denied', html:'<p class="text-[var(--text-muted)] text-sm">Only Owners can manage Firewall.</p>'});
        document.getElementById('fwIP').value = ''; document.getElementById('fwNote').value = '';
        document.getElementById('modalFirewall').classList.add('active');
    }

    async function saveFirewallIP() {
        const ip = document.getElementById('fwIP').value;
        const note = document.getElementById('fwNote').value;
        if(!ip) return; const fd = new FormData(); fd.append('ip', ip); fd.append('note', note);
        const res = await fetch('/index.php?api=add_firewall', { method: 'POST', body: fd }).then(r => r.json());
        if(res.status === 'success') { closeModal('modalFirewall'); loadFirewall();
            Toast.fire({icon:'success',title:'IP Whitelisted'}); } else Toast.fire({icon:'error', title:res.message});
    }

    function copyToClipboard(text) { if(!text || text === '-') return; navigator.clipboard.writeText(text);
        Toast.fire({ icon: 'success', title: 'Copied' }); }
    
    function performSearch() {
        const q = document.getElementById('globalSearch').value.toLowerCase();
        if (document.getElementById('view_files').classList.contains('active')) renderFiles(globalFilesData.filter(f => f.name.toLowerCase().includes(q)));
        if (document.getElementById('view_notes').classList.contains('active')) renderNotes(globalNotesData.filter(n => n.title.toLowerCase().includes(q) || n.data.toLowerCase().includes(q)));
        if (document.getElementById('view_cloaking').classList.contains('active')) renderCloaking(globalCloakData.filter(c => c.domain.toLowerCase().includes(q) || c.path.toLowerCase().includes(q)));
    }

    async function loadMonitor() {
        const res = await fetch('index.php?api=list_processes').then(r => r.json()).catch(e=>{return [];});
        const tbody = document.getElementById('processList'); tbody.innerHTML = '';
        if(res.status === 'success' && res.data) {
            if(res.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-[var(--text-muted)] font-medium">Shell exec disabled or inaccessible by OS.</td></tr>';
            } else {
                res.data.forEach(p => {
                    const cpuColor = parseFloat(p.cpu) > 50 ? 'text-rose-400 font-extrabold' : 'text-emerald-400 font-bold';
                    tbody.innerHTML += `
                    <tr class="border-b border-[var(--glass-border)] hover:bg-[var(--glass-hover)] transition-colors group">
                        <td class="px-6 py-5 text-brand-400 font-bold tracking-widest">${p.pid}</td>
                        <td class="px-6 py-5 text-[var(--text-main)] font-semibold">${p.user}</td>
                        <td class="px-6 py-5 ${cpuColor}">${p.cpu}%</td>
                        <td class="px-6 py-5 text-[var(--text-muted)]">${p.mem}%</td>
                        <td class="px-6 py-5 text-[var(--text-muted)] truncate max-w-md font-mono text-xs" title="${p.cmd}">${p.cmd}</td>
                        <td class="px-6 py-5 text-right opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="promptAuth('kill_process', '${p.pid}')" class="w-10 h-10 rounded-[1rem] bg-[var(--input-bg)] border border-[var(--glass-border)] text-[var(--text-muted)] hover:text-rose-400 hover:bg-rose-500/10 hover:border-rose-500/40 transition-all shadow-sm" title="Kill Process"><i class="fa-solid fa-skull"></i></button>
                        </td>
                    </tr>`;
                });
            }
        }
    }

    async function createSnapshot() {
        Toast.fire({ icon: 'info', title: 'Building snapshot...' });
        const res = await fetch('index.php?api=create_snapshot').then(r=>r.json());
        if(res.status === 'success') { Toast.fire({icon:'success', title:'Snapshot saved to Assets'});
        }
        else { Toast.fire({icon:'error', title:res.message}); }
    }

    // Interval Heartbeat Ringan
    setInterval(async () => { 
        const res = await fetch('/index.php?api=heartbeat').then(r=>r.json());
        if(res.status === 'success' && res.online_data) {
            const now = Math.floor(Date.now() / 1000);
            
            const myOnline = res.online_data[currentUser] ? (now - res.online_data[currentUser] < 30) : false;
            const sbDot = document.getElementById('sidebarDot');
            if(sbDot) sbDot.className = `w-3 h-3 rounded-full absolute -bottom-0.5 -right-0.5 border-2 border-[var(--bg-base)] ${myOnline ? 'bg-emerald-400 shadow-[0_0_8px_#34d399]' : 'bg-slate-500'}`;
            
            if(document.getElementById('view_users').classList.contains('active')) {
                globalUsers.forEach(u => {
                    if(res.online_data[u.username]) u.last_active = res.online_data[u.username];
                    const isOnline = (now - (u.last_active || 0)) < 30; 

                    const uDot = document.getElementById(`user_dot_${u.username}`);
                    if(uDot) uDot.innerHTML = isOnline ?
                        '<div class="w-3.5 h-3.5 rounded-full bg-emerald-400 shadow-[0_0_8px_#34d399] absolute -bottom-0.5 -right-0.5 border-[3px] border-[var(--bg-base)]"></div>' : '<div class="w-3.5 h-3.5 rounded-full bg-slate-500 absolute -bottom-0.5 -right-0.5 border-[3px] border-[var(--bg-base)]"></div>';
                    
                    const uText = document.getElementById(`user_status_${u.username}`);
                    if(uText) {
                        uText.innerHTML = isOnline ?
                            '<i class="fa-solid fa-circle text-[7px] mr-1.5"></i>Online' : '<i class="fa-solid fa-circle text-[7px] mr-1.5"></i>Offline';
                        uText.className = `text-[10px] font-mono uppercase tracking-widest ${isOnline ? 'text-emerald-400 font-bold' : 'text-[var(--text-muted)]'}`;
                    }
                });
            }
        }
    }, 15000);
</script>
</body>
</html>