<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>BASE LOG</title>
    <style>
        :root {
            --baselog-navy: #001f3f; 
            --bg-default: #1a1a1a;    
            --bg-request: #007bff;    
            --bg-cancel: #fd7e14;     
            --btn-slate: #333333;     
            --btn-blue: #0069d9;      
            --btn-undo-red: #e63946;  
            --btn-disabled: #222;     
            --ball-color: #32cd32;
            --strike-color: #ffd700;
            --out-color: #ff4500;
        }

        body { 
            font-family: -apple-system, BlinkMacSystemFont, sans-serif;
            background: #000; color: #fff; margin: 0; text-align: center; overflow: hidden; touch-action: manipulation; 
        }

        .header { 
            height: 55px; display: flex; align-items: center; justify-content: center; 
            background: var(--baselog-navy); box-shadow: 0 2px 8px rgba(0,0,0,0.6);
            z-index: 100; position: relative; border-bottom: 1px solid #003366;
        }
        .header h1 { margin: 0; font-size: 1.2rem; font-weight: 900; color: #fff; letter-spacing: 1px; }

        .bso-container { padding: 15px 0; background: #000; border-bottom: 2px solid #222; }
        .row { display: flex; align-items: center; justify-content: center; margin: 10px 0; }
        .label { font-size: 2.8rem; font-weight: bold; width: 50px; margin-right: 15px; color: #fff; }
        .dots { display: flex; gap: 15px; width: 160px; }
        .dot { width: 42px; height: 42px; border-radius: 50%; border: 3px solid #222; background: #080808; transition: background 0.1s; }
        .active-b { background: var(--ball-color); box-shadow: 0 0 18px var(--ball-color); border-color: transparent; }
        .active-s { background: var(--strike-color); box-shadow: 0 0 18px var(--strike-color); border-color: transparent; }
        .active-o { background: var(--out-color); box-shadow: 0 0 18px var(--out-color); border-color: transparent; }

        .input-btn { width: 65px; height: 55px; background: var(--btn-slate); color: #fff; border: 1px solid #444; border-radius: 10px; font-size: 2.2rem; font-weight: bold; margin-left: 15px; box-shadow: 0 4px 0 #111; }
        .input-btn:active { transform: translateY(3px); box-shadow: 0 1px 0 #111; }

        /* フラッシュさせるパネル */
        #ui-panel { 
            padding: 15px 20px; display: flex; flex-direction: column; height: calc(100vh - 280px); 
            background-color: var(--bg-default); 
            transition: background-color 0.05s ease-in; /* 反応を極限まで速く */
            position: relative;
        }

        #judge-text { font-size: 2.8rem; font-weight: 900; height: 100px; display: flex; align-items: center; justify-content: center; text-shadow: 2px 2px 5px rgba(0,0,0,0.8); }

        .action-row { display: flex; justify-content: center; gap: 15px; min-height: 110px; }
        .btn-large { flex: 1; padding: 12px 5px; font-size: 1.1rem; font-weight: bold; border-radius: 15px; border: none; color: white; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1.4; box-shadow: 0 6px 0 rgba(0,0,0,0.5); }
        .btn-large span { font-size: 1.6rem; margin-bottom: 2px; }
        .btn-undo { background: var(--btn-undo-red) !important; box-shadow: 0 6px 0 #900 !important; } 
        .btn-next { background: var(--btn-blue) !important; box-shadow: 0 6px 0 #004080 !important; } 
        .btn-large:active:not(:disabled) { transform: translateY(4px); box-shadow: 0 2px 0 rgba(0,0,0,0.5) !important; }
        .btn-large:disabled { background: #333 !important; color: #666 !important; box-shadow: none !important; opacity: 0.5; }

        #role-indicator { position: fixed; bottom: 8px; right: 12px; font-size: 0.75rem; color: #555; }
    </style>
</head>
<body>

<header class="header"><h1 id="header-title">BASE LOG</h1></header>

<div class="bso-container">
    <div class="row"><div class="label" style="color:var(--ball-color)">B</div><div class="dots" id="b-dots"></div><button class="input-btn chief-only" onclick="sendAction('input&type=Ball')">+</button></div>
    <div class="row"><div class="label" style="color:var(--strike-color)">S</div><div class="dots" id="s-dots"></div><button class="input-btn chief-only" onclick="sendAction('input&type=Strike')">+</button></div>
    <div class="row"><div class="label" style="color:var(--out-color)">O</div><div class="dots" id="o-dots"></div><button class="input-btn chief-only" onclick="sendAction('input&type=Out')">+</button></div>
</div>

<div id="ui-panel">
    <div id="judge-text">待機中</div>
    <div class="action-row" id="main-controls"></div>
</div>

<div id="role-indicator">---</div>

<script>
    const params = new URLSearchParams(window.location.search);
    const role = params.get('role') || 'base'; 
    let lastId = 0, lastCancel = 0, lastRequest = 0, uiTimer = null;

    const titleElement = document.getElementById('header-title');
    if (role === 'chief') {
        titleElement.innerText = "主審用";
        document.getElementById('role-indicator').innerText = "MODE: CHIEF";
    } else {
        titleElement.innerText = "塁審用";
        document.getElementById('role-indicator').innerText = "MODE: BASE";
        document.querySelectorAll('.chief-only').forEach(el => el.remove());
    }

    // フラッシュ関数：一旦真っ黒を挟むことで連続入力を視覚化
    function triggerFlash(targetColor, msg) {
        const panel = document.getElementById('ui-panel');
        const txt = document.getElementById('judge-text');
        
        if (uiTimer) clearTimeout(uiTimer);

        // 手順1: 背景を真っ黒にして文字を変える
        panel.style.transition = 'none';
        panel.style.backgroundColor = '#000000';
        txt.innerText = msg;

        // 手順2: 20ミリ秒後に目的の色に変える
        setTimeout(() => {
            panel.style.transition = 'background-color 0.05s ease-in';
            panel.style.backgroundColor = targetColor;

            // 手順3: 2秒後に元のグレーに戻す
            uiTimer = setTimeout(() => {
                panel.style.transition = 'background-color 0.5s ease-out';
                panel.style.backgroundColor = 'var(--bg-default)';
                txt.innerText = "待機中";
            }, 2000);
        }, 20);

        if (navigator.vibrate) navigator.vibrate(100);
    }

    async function sendAction(query) {
        // 取り消し時は即座にオレンジフラッシュ
        if (query === 'cancel') triggerFlash('var(--bg-cancel)', "取り消しました");
        try {
            await fetch(`api.php?action=${query}&t=${Date.now()}`);
            syncStatus();
        } catch (e) { console.error(e); }
    }

    async function syncStatus() {
        try {
            const res = await fetch(`status.php?t=${Date.now()}`);
            const data = await res.json();
            
            // カウント更新
            renderDots('b-dots', data.counts.ball, 3, 'active-b');
            renderDots('s-dots', data.counts.strike, 2, 'active-s');
            renderDots('o-dots', data.counts.outs, 2, 'active-o');

            const L = data.latest;
            const reqBtn = document.getElementById('req-btn');
            if (reqBtn) reqBtn.disabled = !(L && L.id && L.is_cancelled == 0);

            if (!L) return;

            const isNewId = L.id !== lastId;
            const isCancelChanged = L.is_cancelled !== lastCancel;
            const isRequestChanged = L.requested_change !== lastRequest;

            if (isNewId || isCancelChanged || isRequestChanged) {
                if (L.is_cancelled == 1) {
                    triggerFlash('var(--bg-cancel)', "取り消されました");
                } else if (L.requested_change == 1) {
                    triggerFlash('var(--bg-request)', "判定リクエスト");
                } else if (isNewId) {
                    const names = {"Ball":"ボール", "Strike":"ストライク", "Out":"アウト", "Foul":"ファウル", "ResetBS":"次打者へ"};
                    const jName = names[L.judge_type] || L.judge_type;
                    // ボール・ストライク時も明るいグレー(#444)でフラッシュ
                    triggerFlash("#444444", jName);
                }
                lastId = L.id; lastCancel = L.is_cancelled; lastRequest = L.requested_change;
            }
        } catch (e) { console.error(e); }
    }

    function renderDots(id, count, max, activeClass) {
        let html = '';
        for (let i = 0; i < max; i++) {
            html += `<div class="dot ${i < count ? activeClass : ''}"></div>`;
        }
        document.getElementById(id).innerHTML = html;
    }

    const container = document.getElementById('main-controls');
    if (role === 'chief') {
        container.innerHTML = `
            <button class="btn-large btn-undo" onclick="sendAction('cancel')"><span>↺</span>直前の操作を<br>取り消す</button>
            <button class="btn-large btn-next" onclick="sendAction('next_batter')"><span>≫</span>打者出塁<br>(カウント更新)</button>
        `;
    } else {
        container.innerHTML = `<button id="req-btn" class="btn-large btn-next" onclick="sendAction('request')" disabled><span>⚠</span>判定リクエスト</button>`;
    }

    if (role === 'chief') {
        window.addEventListener('keydown', (e) => {
            const keys = {'8':'Ball','9':'Strike','5':'Out','3':'Foul'};
            if (keys[e.key]) sendAction(`input&type=${keys[e.key]}`);
            if (e.key === '-' || e.key === 'Subtract') sendAction('cancel');
        });
    }

    setInterval(syncStatus, 1000);
    syncStatus();
</script>
</body>
</html>