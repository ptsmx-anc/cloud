<?php
error_reporting(0);
set_time_limit(0);

// Mesin Core Multi-Layer Obfuscation V3 (System Config Camouflage)
function generate_obfuscation_layer($raw_code, $is_final_layer = false) {
    // Kunci enkripsi XOR acak dinamis
    $key = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, rand(16, 24));
    
    // 1. Kompresi tingkat tinggi
    $compressed = gzdeflate($raw_code, 9);
    
    // 2. Enkripsi Bitwise XOR
    $xored = '';
    for ($i = 0; $i < strlen($compressed); $i++) {
        $xored .= chr(ord($compressed[$i]) ^ ord($key[$i % strlen($key)]));
    }
    
    // 3. Konversi ke Hexadecimal murni
    $hex_string = bin2hex($xored);
    
    // 4. Chunking (Membagi per 64 karakter agar menyerupai token lisensi/hash config)
    $chunks = str_split($hex_string, 64);
    $formatted_payload = "'" . implode("',\n        '", $chunks) . "'";
    
    // 5. Kamuflase Nama Variabel (Menjadi istilah umum System/Config)
    $v_config = '$sys_config';
    $v_kernel = '$kernel_opt';
    $v_driver = '$driver_data';
    $v_buffer = '$io_buffer';
    $v_output = '$core_cache';
    $v_index  = '$idx';
    $v_call   = '$init_handler';
    $v_parse  = '$hex_resolver';
    
    // 6. Menyembunyikan kata kunci sensitif ke dalam representasi byte dinamis
    $func_gz = [];
    foreach(str_split("gzinflate") as $c) $func_gz[] = "chr(".ord($c).")";
    $str_gz = implode(".", $func_gz);
    
    $func_hd = [];
    foreach(str_split("hexdec") as $c) $func_hd[] = "chr(".ord($c).")";
    $str_hd = implode(".", $func_hd);

    // 7. Merakit Struktur Kode dengan Samaran File System / Konfigurasi
    $layer_stub = "";
    if ($is_final_layer) {
        $layer_stub .= "<?php\n";
        $layer_stub .= "/**\n";
        $layer_stub .= " *--------------------------------------------------------------------------\n";
        $layer_stub .= " * APPLICATION CORE CONFIGURATION & KERNEL BOOTSTRAP\n";
        $layer_stub .= " *--------------------------------------------------------------------------\n";
        $layer_stub .= " * WARNING: DO NOT MODIFY OR DELETE THIS FILE.\n";
        $layer_stub .= " * Any unauthorized changes to this core configuration may cause critical\n";
        $layer_stub .= " * system failures, database desynchronization, and routing errors.\n";
        $layer_stub .= " *\n";
        $layer_stub .= " * @package    Core\\System\n";
        $layer_stub .= " * @version    4.2.1-stable\n";
        $layer_stub .= " */\n\n";
        $layer_stub .= "define('SYS_CORE_INITIALIZED', true);\n\n";
    }
    
    $layer_stub .= "/* RECOVERY & ROUTING MATRIX OPTIMIZATION */\n";
    $layer_stub .= "{$v_call} = {$str_gz};\n";
    $layer_stub .= "{$v_parse} = {$str_hd};\n";
    $layer_stub .= "{$v_kernel} = '{$key}';\n";
    $layer_stub .= "{$v_config} = [\n    'meta_hash' => '8f9a2c1e4d6b3f5a',\n    'secure_token' => [\n        {$formatted_payload}\n    ]\n];\n\n";
    
    $layer_stub .= "/* PROCESSING DRIVER CACHE AND STREAM STACK */\n";
    $layer_stub .= "{$v_driver} = implode('', {$v_config}['secure_token']);\n";
    $layer_stub .= "{$v_buffer} = '';\n";
    $layer_stub .= "for ({$v_index} = 0; {$v_index} < strlen( {$v_driver} ); {$v_index} += 2) {\n";
    $layer_stub .= "    {$v_buffer} .= chr( {$v_parse}( {$v_driver}[{$v_index}] . {$v_driver}[{$v_index} + 1] ) );\n";
    $layer_stub .= "}\n\n";
    
    $layer_stub .= "/* SYNCING INTEGRITY VERIFICATION KEYS */\n";
    $layer_stub .= "{$v_output} = '';\n";
    $layer_stub .= "for ({$v_index} = 0; {$v_index} < strlen( {$v_buffer} ); {$v_index}++) {\n";
    $layer_stub .= "    {$v_output} .= chr( ord( {$v_buffer}[{$v_index}] ) ^ ord( {$v_kernel}[{$v_index} % strlen( {$v_kernel} )] ) );\n";
    $layer_stub .= "}\n\n";
    
    $layer_stub .= "/* EXECUTE SUBSYSTEM MATRIX */\n";
    $layer_stub .= "eval( {$v_call}( {$v_output} ) );\n";
    
    if ($is_final_layer) {
        $layer_stub .= "?>";
    }
    
    return $layer_stub;
}

$final_obfuscated_code = '';
$process_time = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['input_text'])) {
    $start_time = microtime(true);
    
    $raw_code = trim($_POST['input_text']);
    $raw_code = preg_replace('/^\s*<\?php\s*/i', '', $raw_code);
    $raw_code = preg_replace('/\?>\s*$/i', '', $raw_code);

    $total_layers = 3;
    $protected_code = $raw_code;

    for ($layer = 1; $layer <= $total_layers; $layer++) {
        $is_final = ($layer == $total_layers);
        $protected_code = generate_obfuscation_layer($protected_code, $is_final);
    }

    $final_obfuscated_code = $protected_code;
    $process_time = round((microtime(true) - $start_time) * 1000, 2);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusCrypt - Camouflage Edition</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['Fira Code', 'monospace'],
                    },
                    colors: {
                        brand: {
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                        },
                        dark: {
                            900: '#0f172a',
                            800: '#1e293b',
                            700: '#334155',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
        .code-textarea { background-color: #0b1120; white-space: pre; }
    </style>
</head>
<body class="bg-dark-900 text-slate-300 font-sans flex h-screen overflow-hidden">

    <aside class="w-64 bg-dark-800 border-r border-dark-700 hidden md:flex flex-col">
        <div class="h-16 flex items-center px-6 border-b border-dark-700">
            <i class="fa-solid fa-mask text-brand-500 text-xl mr-3"></i>
            <span class="text-white font-bold text-lg tracking-wide">Nexus<span class="text-brand-500">Crypt</span></span>
        </div>
        <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
            <a href="#" class="flex items-center px-3 py-2.5 bg-brand-600/10 text-brand-400 rounded-lg group">
                <i class="fa-solid fa-file-code w-6"></i>
                <span class="font-medium">Camouflage Panel</span>
            </a>
        </nav>
        <div class="p-4 border-t border-dark-700">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full bg-brand-500 flex items-center justify-center text-white font-bold">
                    A
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-white">Administrator</p>
                    <p class="text-xs text-slate-500">System Panel</p>
                </div>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <header class="h-16 bg-dark-800 border-b border-dark-700 flex items-center justify-between px-6 z-10">
            <div class="flex items-center md:hidden">
                <i class="fa-solid fa-mask text-brand-500 text-xl mr-2"></i>
                <span class="text-white font-bold">NexusCrypt</span>
            </div>
            <h1 class="text-lg font-semibold text-white hidden md:block">PHP Obfuscator (System Camouflage Mode)</h1>
            <div class="flex items-center space-x-4">
                <span class="flex h-3 w-3 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                </span>
                <span class="text-sm font-medium text-slate-400">Camouflage Active</span>
            </div>
        </header>

        <div class="flex-1 overflow-auto p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-dark-800 border border-dark-700 rounded-xl p-5 flex items-center shadow-sm">
                    <div class="p-3 rounded-lg bg-blue-500/10 text-blue-500">
                        <i class="fa-solid fa-user-secret text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-slate-400 font-medium">Disguise Type</p>
                        <p class="text-xl font-bold text-white">System Config</p>
                    </div>
                </div>
                <div class="bg-dark-800 border border-dark-700 rounded-xl p-5 flex items-center shadow-sm">
                    <div class="p-3 rounded-lg bg-emerald-500/10 text-emerald-500">
                        <i class="fa-solid fa-code text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-slate-400 font-medium">Structure</p>
                        <p class="text-xl font-bold text-white">Clean & Indented</p>
                    </div>
                </div>
                <div class="bg-dark-800 border border-dark-700 rounded-xl p-5 flex items-center shadow-sm">
                    <div class="p-3 rounded-lg bg-purple-500/10 text-purple-500">
                        <i class="fa-solid fa-bolt text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-slate-400 font-medium">Process</p>
                        <p class="text-xl font-bold text-white"><?php echo $process_time; ?> ms</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 h-[calc(100%-120px)] min-h-[500px]">
                <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-sm flex flex-col">
                    <div class="px-5 py-4 border-b border-dark-700 flex justify-between items-center bg-dark-800/50 rounded-t-xl">
                        <h2 class="text-sm font-semibold text-white flex items-center">
                            <i class="fa-solid fa-code mr-2 text-slate-400"></i> Source Code Input
                        </h2>
                    </div>
                    <form method="POST" action="" class="flex-1 flex flex-col p-5">
                        <textarea name="input_text" 
                                  class="w-full flex-1 code-textarea border border-dark-700 rounded-lg p-4 font-mono text-sm text-blue-300 focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 resize-none shadow-inner" 
                                  placeholder="// Paste Script PHP di sini..." 
                                  required><?php echo isset($_POST['input_text']) ? htmlspecialchars($_POST['input_text']) : ''; ?></textarea>
                        <button type="submit" class="mt-4 w-full bg-brand-600 hover:bg-brand-500 text-white font-semibold py-3 px-4 rounded-lg transition-colors flex items-center justify-center shadow-lg shadow-brand-500/20">
                            <i class="fa-solid fa-user-shield mr-2"></i> Generate Camouflaged Script
                        </button>
                    </form>
                </div>

                <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-sm flex flex-col">
                    <div class="px-5 py-4 border-b border-dark-700 flex justify-between items-center bg-dark-800/50 rounded-t-xl">
                        <h2 class="text-sm font-semibold text-white flex items-center">
                            <i class="fa-solid fa-terminal mr-2 text-slate-400"></i> Output (As Part of Core Config)
                        </h2>
                        <?php if(!empty($final_obfuscated_code)): ?>
                        <button onclick="copyCode()" class="text-xs bg-dark-700 hover:bg-dark-600 text-white py-1 px-3 rounded flex items-center transition-colors">
                            <i class="fa-regular fa-copy mr-1.5"></i> Copy Code
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 p-5 flex flex-col">
                        <textarea id="output_result" 
                                  readonly 
                                  class="w-full flex-1 code-textarea border border-dark-700 rounded-lg p-4 font-mono text-sm text-green-400 focus:outline-none resize-none shadow-inner"
                                  placeholder="Hasil kamuflase sistem akan muncul di sini..."><?php echo htmlspecialchars($final_obfuscated_code); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div id="toast" class="fixed bottom-5 right-5 transform translate-y-20 opacity-0 transition-all duration-300 bg-emerald-500 text-white px-4 py-3 rounded-lg shadow-lg flex items-center">
        <i class="fa-solid fa-circle-check mr-2"></i>
        <span class="font-medium text-sm">Code disalin ke clipboard!</span>
    </div>

    <script>
        function copyCode() {
            var copyText = document.getElementById("output_result");
            if(copyText.value.trim() === '') return;
            
            copyText.select();
            copyText.setSelectionRange(0, 9999999);
            navigator.clipboard.writeText(copyText.value);
            
            var toast = document.getElementById("toast");
            toast.classList.remove("translate-y-20", "opacity-0");
            
            setTimeout(function(){
                toast.classList.add("translate-y-20", "opacity-0");
            }, 3000);
        }
    </script>
</body>
</html>