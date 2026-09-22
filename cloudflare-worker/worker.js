export default {
    async fetch(request, env) {
        const url = new URL(request.url);

        const corsHeaders = {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, X-Telegram-Init-Data',
        };

        if (request.method === 'OPTIONS') {
            return new Response(null, { headers: corsHeaders });
        }

        if (url.pathname.startsWith('/images/')) {
            return proxyStatic(url.pathname, env.BACKEND_URL, corsHeaders);
        }

        if (url.pathname === '/api/wheel/state' && request.method === 'POST') {
            return proxyToLaravel(request, '/api/wheel/state', corsHeaders, env.BACKEND_URL);
        }

        if (url.pathname === '/api/wheel/spin' && request.method === 'POST') {
            return proxyToLaravel(request, '/api/wheel/spin', corsHeaders, env.BACKEND_URL);
        }

        return new Response(HTML, {
            headers: {
                'Content-Type': 'text/html; charset=UTF-8',
                ...corsHeaders,
            },
        });
    },
};

// ============================================================
//  🖼️ Proxy للصور الثابتة
// ============================================================
async function proxyStatic(pathname, backendUrl, corsHeaders) {
    try {
        const response = await fetch(`${backendUrl}${pathname}`);
        if (!response.ok) {
            return new Response('Not Found', { status: 404 });
        }
        return new Response(response.body, {
            status: 200,
            headers: {
                'Content-Type': response.headers.get('Content-Type') || 'image/jpeg',
                'Cache-Control': 'public, max-age=86400',
                ...corsHeaders,
            },
        });
    } catch (e) {
        return new Response('Not Found', { status: 404 });
    }
}

// ============================================================
//  🔗 Proxy للـ API
// ============================================================
async function proxyToLaravel(request, path, corsHeaders, backendUrl) {
    try {
        const body = await request.text();
        const headers = new Headers();
        headers.set('Content-Type', request.headers.get('Content-Type') || 'application/json');

        const initData = request.headers.get('X-Telegram-Init-Data');
        if (initData) headers.set('X-Telegram-Init-Data', initData);

        const response = await fetch(`${backendUrl}${path}`, {
            method: 'POST',
            headers,
            body,
        });

        const responseBody = await response.text();

        return new Response(responseBody, {
            status: response.status,
            statusText: response.statusText,
            headers: {
                'Content-Type': response.headers.get('Content-Type') || 'application/json',
                ...corsHeaders,
            },
        });
    } catch (error) {
        return new Response(
            JSON.stringify({
                success: false,
                message: 'تعذر الاتصال بخادم VEXORA',
            }),
            {
                status: 502,
                headers: { 'Content-Type': 'application/json', ...corsHeaders },
            }
        );
    }
}

// ============================================================
//  🎨 HTML
// ============================================================
const HTML = `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#050b1a">
<title>🎡 عجلة الحظ | VEXORA</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
*, *::before, *::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
    -webkit-touch-callout: none;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

:root {
    --space-deep: #050b1a;
    --blue-royal: #1e40af;
    --blue-bright: #3b82f6;
    --gold-primary: #fbbf24;
    --gold-light: #fcd34d;
    --gold-dark: #f59e0b;
    --text-primary: #f1f5f9;
    --text-secondary: #94a3b8;
    --success: #10b981;

    --grad-gold: linear-gradient(135deg, #fcd34d 0%, #fbbf24 40%, #f59e0b 100%);
    --grad-glass: linear-gradient(135deg, rgba(30, 64, 175, 0.15) 0%, rgba(15, 33, 55, 0.25) 100%);

    --shadow-card: 0 4px 20px rgba(0, 0, 0, 0.35);
    --shadow-inset: inset 0 1px 0 rgba(255, 255, 255, 0.1);

    --r-sm: 12px;
    --r-md: 18px;
    --r-lg: 24px;
    --r-xl: 32px;
}

html, body {
    height: 100%;
    overflow: hidden;
}

body {
    font-family: 'Cairo', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--text-primary);
    height: 100dvh;
    width: 100vw;
    position: fixed;
    inset: 0;
    overflow: hidden;
    user-select: none;
    -webkit-user-select: none;
    overscroll-behavior: none;
}

body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image: url('/images/back.jpg');
    background-size: cover;
    background-position: center center;
    background-repeat: no-repeat;
    background-attachment: fixed;
    z-index: -3;
}

body::after {
    content: '';
    position: fixed;
    inset: 0;
    background: radial-gradient(
        ellipse at center,
        rgba(5, 11, 26, 0.15) 0%,
        rgba(5, 11, 26, 0.45) 50%,
        rgba(5, 11, 26, 0.75) 100%
    );
    z-index: -2;
    pointer-events: none;
}

.aurora {
    position: fixed;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 120%;
    height: 40%;
    background: radial-gradient(
        ellipse at center top,
        rgba(59, 130, 246, 0.25) 0%,
        rgba(34, 211, 238, 0.12) 30%,
        transparent 70%
    );
    z-index: -1;
    pointer-events: none;
    filter: blur(40px);
}

.app {
    position: relative;
    z-index: 1;
    width: 100%;
    height: 100%;
    max-width: 500px;
    margin: 0 auto;
    display: grid;
    grid-template-rows: auto auto 1fr auto auto;
    gap: 8px;
    padding:
        max(env(safe-area-inset-top), 10px)
        max(env(safe-area-inset-right), 14px)
        max(env(safe-area-inset-bottom), 10px)
        max(env(safe-area-inset-left), 14px);
}

.brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 4px 0;
    animation: brandFloat 4s ease-in-out infinite;
    position: relative;
}

@keyframes brandFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-3px); }
}

.brand__svg {
    width: clamp(80px, 22vw, 110px);
    height: auto;
    filter: drop-shadow(0 4px 16px rgba(59, 130, 246, 0.6))
            drop-shadow(0 0 24px rgba(251, 191, 36, 0.35));
}

.brand__text {
    font-size: clamp(9px, 2.6vw, 11px);
    font-weight: 800;
    letter-spacing: 0.35em;
    background: var(--grad-gold);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    text-transform: uppercase;
    margin-top: -4px;
}

.sound-toggle {
    position: absolute;
    top: 8px;
    left: 8px;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.7);
    border: 1px solid rgba(251, 191, 36, 0.4);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    cursor: pointer;
    z-index: 20;
    transition: all 200ms;
    color: #fbbf24;
}

.sound-toggle:active {
    transform: scale(0.9);
    background: rgba(30, 41, 59, 0.9);
}

.sound-toggle.is-muted {
    opacity: 0.5;
    border-color: rgba(148, 163, 184, 0.4);
    color: #94a3b8;
}

.stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.stat {
    position: relative;
    background: var(--grad-glass);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(96, 165, 250, 0.2);
    border-radius: var(--r-md);
    padding: 10px 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    overflow: hidden;
    transition: transform 200ms cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-card), var(--shadow-inset);
}

.stat::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(96, 165, 250, 0.6), transparent);
}

.stat:active {
    transform: scale(0.97);
}

.stat__icon {
    width: 36px;
    height: 36px;
    border-radius: var(--r-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
    background: linear-gradient(135deg, rgba(30, 64, 175, 0.5), rgba(15, 33, 55, 0.7));
    border: 1px solid rgba(96, 165, 250, 0.25);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.stat__body {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}

.stat__value {
    font-size: clamp(16px, 4.5vw, 20px);
    font-weight: 900;
    line-height: 1.1;
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stat__value--gold {
    background: var(--grad-gold);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.stat__label {
    font-size: clamp(9px, 2.4vw, 10px);
    font-weight: 700;
    color: var(--text-secondary);
    letter-spacing: 0.05em;
    margin-top: 1px;
}

.stage {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 0;
    padding: 6px 0;
}

.wheel {
    position: relative;
    width: clamp(240px, min(90vw, 58vh), 440px);
    aspect-ratio: 1 / 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wheel::before {
    content: '';
    position: absolute;
    inset: -12px;
    border-radius: 50%;
    background: radial-gradient(
        circle,
        rgba(251, 191, 36, 0.25) 0%,
        rgba(59, 130, 246, 0.15) 40%,
        transparent 70%
    );
    filter: blur(20px);
    z-index: -1;
    animation: auraPulse 3s ease-in-out infinite;
}

@keyframes auraPulse {
    0%, 100% { opacity: 0.7; transform: scale(1); }
    50% { opacity: 1; transform: scale(1.05); }
}

.pointer {
    position: absolute;
    top: -12px;
    left: 50%;
    transform: translateX(-50%);
    width: clamp(30px, 7.5vw, 40px);
    height: clamp(36px, 9vw, 48px);
    z-index: 10;
    filter: drop-shadow(0 4px 12px rgba(239, 68, 68, 0.6))
            drop-shadow(0 0 8px rgba(251, 191, 36, 0.5));
    animation: pointerPulse 2s ease-in-out infinite;
}

.pointer svg {
    width: 100%;
    height: 100%;
    display: block;
}

@keyframes pointerPulse {
    0%, 100% { transform: translateX(-50%) translateY(0); }
    50% { transform: translateX(-50%) translateY(2px); }
}

.pulse-canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    border-radius: 50%;
    z-index: 3;
}

#wheel {
    position: relative;
    z-index: 2;
    width: 100%;
    height: 100%;
    display: block;
    border-radius: 50%;
    box-shadow:
        0 0 0 2px #0a0e1a,
        0 0 0 4px #f59e0b,
        0 0 0 6px #0a0e1a,
        0 0 0 8px rgba(251, 191, 36, 0.3),
        0 0 60px rgba(251, 191, 36, 0.5),
        0 0 120px rgba(59, 130, 246, 0.35),
        0 30px 80px rgba(0, 0, 0, 0.7);
    will-change: transform;
    backface-visibility: hidden;
    transform: translateZ(0);
}

.center {
    position: absolute;
    width: 24%;
    height: 24%;
    min-width: 70px;
    min-height: 70px;
    max-width: 100px;
    max-height: 100px;
    border-radius: 50%;
    background: var(--grad-gold);
    border: 3px solid rgba(255, 255, 255, 0.95);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 5;
    box-shadow:
        0 6px 20px rgba(0, 0, 0, 0.5),
        inset 0 -4px 8px rgba(0, 0, 0, 0.2),
        inset 0 3px 6px rgba(255, 255, 255, 0.5),
        0 0 32px rgba(251, 191, 36, 0.7);
    transition: transform 200ms cubic-bezier(0.4, 0, 0.2, 1);
    -webkit-tap-highlight-color: transparent;
    padding: 0;
    overflow: hidden;
}

.center__logo {
    width: 68%;
    height: 68%;
    display: block;
    filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.3));
}

.center:active {
    transform: scale(0.92);
}

.center.is-spinning {
    pointer-events: none;
    animation: centerPulse 0.9s ease-in-out infinite;
}

@keyframes centerPulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5), 0 0 32px rgba(251, 191, 36, 0.7);
    }
    50% {
        transform: scale(1.08);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5), 0 0 56px rgba(251, 191, 36, 1);
    }
}

.action {
    padding: 4px 0;
}

.btn-spin {
    position: relative;
    width: 100%;
    padding: clamp(12px, 3.2vw, 16px) clamp(16px, 4vw, 22px);
    background: var(--grad-gold);
    color: #0a1628;
    border: none;
    border-radius: var(--r-lg);
    font-family: inherit;
    font-size: clamp(14px, 4vw, 17px);
    font-weight: 900;
    letter-spacing: 0.02em;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    overflow: hidden;
    box-shadow:
        0 8px 24px rgba(251, 191, 36, 0.45),
        0 0 0 1px rgba(255, 255, 255, 0.15) inset,
        inset 0 2px 4px rgba(255, 255, 255, 0.4);
    transition: transform 200ms cubic-bezier(0.4, 0, 0.2, 1);
    -webkit-tap-highlight-color: transparent;
}

.btn-spin::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.5) 50%, transparent 100%);
    transform: translateX(-150%);
    animation: shimmer 3s ease-in-out infinite;
}

@keyframes shimmer {
    0%, 60% { transform: translateX(-150%); }
    100% { transform: translateX(150%); }
}

.btn-spin:active {
    transform: scale(0.97);
}

.btn-spin:disabled {
    opacity: 0.55;
    cursor: not-allowed;
    filter: grayscale(0.5);
}

.btn-spin:disabled::before {
    animation: none;
}

.footer {
    text-align: center;
    font-size: clamp(8px, 2.2vw, 10px);
    font-weight: 700;
    letter-spacing: 0.4em;
    color: rgba(96, 165, 250, 0.6);
    padding: 2px 0;
    text-transform: uppercase;
}

.footer::before,
.footer::after {
    content: '——';
    margin: 0 6px;
    opacity: 0.5;
}

.modal {
    position: fixed;
    inset: 0;
    background: rgba(5, 11, 26, 0.85);
    backdrop-filter: blur(24px) saturate(180%);
    -webkit-backdrop-filter: blur(24px) saturate(180%);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 110;
    padding: 20px;
}

.modal.is-open {
    display: flex;
    animation: fadeIn 250ms ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal__card {
    position: relative;
    background: linear-gradient(135deg, rgba(15, 33, 55, 0.95), rgba(10, 22, 40, 0.98));
    border: 2px solid rgba(251, 191, 36, 0.4);
    border-radius: var(--r-xl);
    padding: 28px 22px;
    text-align: center;
    max-width: 340px;
    width: 100%;
    overflow: hidden;
    box-shadow:
        0 24px 64px rgba(0, 0, 0, 0.7),
        0 0 64px rgba(251, 191, 36, 0.3);
    animation: modalIn 450ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.modal__card::before {
    content: '';
    position: absolute;
    inset: -50%;
    background: conic-gradient(from 0deg, transparent, rgba(251, 191, 36, 0.15), transparent, rgba(96, 165, 250, 0.15), transparent);
    animation: rotate 8s linear infinite;
    pointer-events: none;
}

@keyframes rotate {
    to { transform: rotate(360deg); }
}

@keyframes modalIn {
    from { transform: scale(0.6) translateY(40px); opacity: 0; }
    to { transform: scale(1) translateY(0); opacity: 1; }
}

.modal__content {
    position: relative;
    z-index: 1;
}

.modal__icon {
    font-size: clamp(56px, 16vw, 80px);
    line-height: 1;
    display: inline-block;
    margin-bottom: 12px;
    animation: iconBounce 1.2s ease-in-out infinite;
    filter: drop-shadow(0 6px 24px rgba(251, 191, 36, 0.7));
}

@keyframes iconBounce {
    0%, 100% { transform: translateY(0) scale(1); }
    50% { transform: translateY(-8px) scale(1.06); }
}

.modal__title {
    font-size: clamp(20px, 5.5vw, 26px);
    font-weight: 900;
    background: var(--grad-gold);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 8px;
}

.modal__prize {
    font-size: clamp(14px, 3.8vw, 16px);
    font-weight: 700;
    color: var(--text-secondary);
    margin-bottom: 12px;
}

.modal__value {
    font-size: clamp(26px, 7vw, 36px);
    font-weight: 900;
    color: var(--success);
    margin-bottom: 20px;
    font-variant-numeric: tabular-nums;
    text-shadow: 0 2px 16px rgba(16, 185, 129, 0.6);
}

.modal__btn {
    width: 100%;
    padding: clamp(12px, 3.2vw, 14px);
    background: var(--grad-gold);
    color: #0a1628;
    border: none;
    border-radius: var(--r-md);
    font-family: inherit;
    font-size: clamp(14px, 3.6vw, 16px);
    font-weight: 900;
    cursor: pointer;
    box-shadow: 0 6px 20px rgba(251, 191, 36, 0.4);
}

.modal__btn:active {
    transform: scale(0.97);
}

#confetti {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 105;
}

@media (max-height: 640px) {
    .app { gap: 6px; }
    .brand__svg { width: 70px; }
    .stat { padding: 8px 10px; }
    .stat__icon { width: 30px; height: 30px; font-size: 15px; }
    .btn-spin { padding: 10px 16px; font-size: 13px; }
}

@media (max-height: 540px) {
    .brand__text { display: none; }
    .brand__svg { width: 55px; }
    .footer { display: none; }
    .wheel { width: clamp(200px, min(85vw, 52vh), 340px); }
}

@media (max-height: 460px) {
    .brand { display: none; }
    .stat__label { display: none; }
}

@media (orientation: landscape) and (max-height: 500px) {
    .app {
        grid-template-columns: 1fr 1fr;
        grid-template-rows: auto 1fr auto;
        max-width: 900px;
    }
    .brand { grid-column: 1 / -1; }
    .stats { grid-column: 1 / -1; grid-template-columns: repeat(4, 1fr); }
    .stage { grid-column: 1; }
    .action { grid-column: 2; align-self: center; }
    .wheel { width: min(80vw, 70vh, 340px); }
}
</style>
</head>
<body>

<div class="aurora" aria-hidden="true"></div>
<canvas id="confetti" aria-hidden="true"></canvas>

<main class="app">

    <header class="brand">
        <button class="sound-toggle" id="soundToggle" aria-label="كتم الصوت">🔊</button>
        <svg class="brand__svg" viewBox="0 0 200 110" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="goldGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stop-color="#fcd34d"/>
                    <stop offset="50%" stop-color="#fbbf24"/>
                    <stop offset="100%" stop-color="#f59e0b"/>
                </linearGradient>
                <linearGradient id="blueGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#93c5fd"/>
                    <stop offset="50%" stop-color="#3b82f6"/>
                    <stop offset="100%" stop-color="#1e40af"/>
                </linearGradient>
                <linearGradient id="vGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stop-color="#ffffff"/>
                    <stop offset="40%" stop-color="#dbeafe"/>
                    <stop offset="100%" stop-color="#60a5fa"/>
                </linearGradient>
                <filter id="glow" x="-50%" y="-50%" width="200%" height="200%">
                    <feGaussianBlur stdDeviation="2.5" result="blur"/>
                    <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
                </filter>
                <filter id="glowStrong" x="-50%" y="-50%" width="200%" height="200%">
                    <feGaussianBlur stdDeviation="3.5" result="blur"/>
                    <feMerge><feMergeNode in="blur"/><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
                </filter>
            </defs>
            <g filter="url(#glowStrong)">
                <path d="M 72 30 L 78 14 L 90 26 L 100 8 L 110 26 L 122 14 L 128 30 Z"
                      fill="url(#goldGrad)" stroke="#fbbf24" stroke-width="1" stroke-linejoin="round"/>
                <circle cx="78" cy="14" r="2.5" fill="#fef3c7"/>
                <circle cx="100" cy="8" r="3" fill="#fef3c7"/>
                <circle cx="122" cy="14" r="2.5" fill="#fef3c7"/>
                <rect x="70" y="30" width="60" height="4" rx="1.5" fill="url(#goldGrad)"/>
            </g>
            <g filter="url(#glowStrong)">
                <path d="M 62 42 L 100 92 L 138 42 L 124 42 L 100 72 L 76 42 Z" fill="url(#vGrad)"/>
            </g>
            <ellipse cx="100" cy="68" rx="58" ry="10" fill="none" stroke="url(#blueGrad)" stroke-width="1.5" opacity="0.65" filter="url(#glow)"/>
            <ellipse cx="100" cy="68" rx="58" ry="10" fill="none" stroke="#93c5fd" stroke-width="0.5" opacity="0.4"/>
        </svg>
        <div class="brand__text">VEXORA</div>
    </header>

    <section class="stats" aria-label="الإحصائيات">
        <article class="stat">
            <div class="stat__icon" aria-hidden="true">🎟</div>
            <div class="stat__body">
                <span class="stat__value stat__value--gold" id="statSpins">0</span>
                <span class="stat__label">اللفات المتاحة</span>
            </div>
        </article>
        <article class="stat">
            <div class="stat__icon" aria-hidden="true">💰</div>
            <div class="stat__body">
                <span class="stat__value" id="statWon">0</span>
                <span class="stat__label">المكاسب</span>
            </div>
        </article>
    </section>

    <section class="stage" aria-label="عجلة الحظ">
        <div class="wheel">
            <div class="pointer" aria-hidden="true">
                <svg viewBox="0 0 36 48" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="pointerGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#fcd34d"/>
                            <stop offset="50%" stop-color="#ef4444"/>
                            <stop offset="100%" stop-color="#b91c1c"/>
                        </linearGradient>
                    </defs>
                    <path d="M 18 46 L 4 16 Q 18 0 32 16 Z"
                          fill="url(#pointerGrad)"
                          stroke="#fbbf24"
                          stroke-width="1.5"
                          stroke-linejoin="round"/>
                    <circle cx="18" cy="16" r="3.5" fill="#fef3c7"/>
                </svg>
            </div>

            <canvas id="pulseCanvas" class="pulse-canvas" aria-hidden="true"></canvas>
            <canvas id="wheel" role="img" aria-label="عجلة الجوائز"></canvas>

            <button class="center" id="centerBtn" aria-label="تدوير العجلة">
                <svg class="center__logo" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="cCrown" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#fef3c7"/>
                            <stop offset="50%" stop-color="#fcd34d"/>
                            <stop offset="100%" stop-color="#d97706"/>
                        </linearGradient>
                        <linearGradient id="cV" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#ffffff"/>
                            <stop offset="50%" stop-color="#dbeafe"/>
                            <stop offset="100%" stop-color="#1e40af"/>
                        </linearGradient>
                    </defs>
                    <path d="M 30 24 L 36 6 L 47 18 L 50 2 L 53 18 L 64 6 L 70 24 Z"
                          fill="url(#cCrown)" stroke="#fbbf24" stroke-width="0.8" stroke-linejoin="round"/>
                    <circle cx="36" cy="6" r="1.8" fill="#fef3c7"/>
                    <circle cx="50" cy="2" r="2.2" fill="#fef3c7"/>
                    <circle cx="64" cy="6" r="1.8" fill="#fef3c7"/>
                    <rect x="28" y="24" width="44" height="3" rx="1" fill="url(#cCrown)"/>
                    <path d="M 26 32 L 50 68 L 74 32 L 64 32 L 50 54 L 36 32 Z" fill="url(#cV)"/>
                    <circle cx="50" cy="73" r="1.8" fill="#fcd34d"/>
                </svg>
            </button>
        </div>
    </section>

    <section class="action">
        <button class="btn-spin" id="spinBtn" onclick="spin()">
            <span aria-hidden="true">🎰</span>
            <span id="spinBtnText">لف العجلة</span>
        </button>
    </section>

    <footer class="footer">VEXORA</footer>
</main>

<div class="modal" id="result" role="dialog" aria-modal="true">
    <div class="modal__card">
        <div class="modal__content">
            <div class="modal__icon" id="resultIcon">🎊</div>
            <h2 class="modal__title" id="resultTitle">مبروك!</h2>
            <p class="modal__prize" id="resultPrize"></p>
            <p class="modal__value" id="resultValue"></p>
            <button class="modal__btn" onclick="closeResult()">حسناً</button>
        </div>
    </div>
</div>

<script>
'use strict';

const tg = window.Telegram?.WebApp;
if (tg) {
    tg.ready();
    tg.expand();
    tg.setHeaderColor('#050b1a');
    tg.setBackgroundColor('#050b1a');
    tg.disableVerticalSwipes?.();
}

// ============================================================
//  🎵 محرك الصوت
// ============================================================
var SoundEngine = (function() {
    var ctx = null;
    var isMuted = false;
    var tickTimeout = null;
    var masterGain = null;

    function ensureContext() {
        if (!ctx) {
            try {
                var AC = window.AudioContext || window.webkitAudioContext;
                if (AC) {
                    ctx = new AC();

                    masterGain = ctx.createGain();
                    masterGain.gain.value = 0.45;

                    var compressor = ctx.createDynamicsCompressor();
                    compressor.threshold.value = -22;
                    compressor.knee.value = 25;
                    compressor.ratio.value = 3;
                    compressor.attack.value = 0.005;
                    compressor.release.value = 0.25;

                    masterGain.connect(compressor);
                    compressor.connect(ctx.destination);
                }
            } catch (e) {
                ctx = null;
            }
        }
        if (ctx && ctx.state === 'suspended') {
            ctx.resume().catch(function() {});
        }
        return ctx;
    }

    function clockTick(volume) {
        if (isMuted || !ctx) return;
        volume = volume || 0.3;

        try {
            var now = ctx.currentTime;

            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            var filter = ctx.createBiquadFilter();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(1800, now);
            osc.frequency.exponentialRampToValueAtTime(1200, now + 0.012);

            filter.type = 'lowpass';
            filter.frequency.value = 4000;
            filter.Q.value = 0.5;

            gain.gain.setValueAtTime(0, now);
            gain.gain.linearRampToValueAtTime(volume * 0.6, now + 0.001);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.022);

            osc.connect(filter);
            filter.connect(gain);
            gain.connect(masterGain);

            osc.start(now);
            osc.stop(now + 0.025);

            var wood = ctx.createOscillator();
            var woodGain = ctx.createGain();

            wood.type = 'triangle';
            wood.frequency.setValueAtTime(600, now);
            wood.frequency.exponentialRampToValueAtTime(400, now + 0.02);

            woodGain.gain.setValueAtTime(0, now);
            woodGain.gain.linearRampToValueAtTime(volume * 0.15, now + 0.001);
            woodGain.gain.exponentialRampToValueAtTime(0.0001, now + 0.025);

            wood.connect(woodGain);
            woodGain.connect(masterGain);

            wood.start(now);
            wood.stop(now + 0.03);
        } catch (e) {}
    }

    function playTone(freq, duration, type, volume, when) {
        if (isMuted) return;
        var c = ensureContext();
        if (!c) return;
        type = type || 'sine';
        volume = volume || 0.12;
        when = when || 0;

        try {
            var now = c.currentTime + when;
            var osc = c.createOscillator();
            var gain = c.createGain();

            osc.type = type;
            osc.frequency.setValueAtTime(freq, now);

            gain.gain.setValueAtTime(0, now);
            gain.gain.linearRampToValueAtTime(volume, now + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + duration);

            osc.connect(gain);
            gain.connect(masterGain);

            osc.start(now);
            osc.stop(now + duration + 0.02);
        } catch (e) {}
    }

    function playClick() {
        if (isMuted) return;
        var c = ensureContext();
        if (!c) return;

        try {
            var now = c.currentTime;

            var osc = c.createOscillator();
            var gain = c.createGain();
            var filter = c.createBiquadFilter();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(520, now);
            osc.frequency.exponentialRampToValueAtTime(780, now + 0.06);

            filter.type = 'lowpass';
            filter.frequency.value = 1500;
            filter.Q.value = 0.7;

            gain.gain.setValueAtTime(0, now);
            gain.gain.linearRampToValueAtTime(0.08, now + 0.008);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.1);

            osc.connect(filter);
            filter.connect(gain);
            gain.connect(masterGain);

            osc.start(now);
            osc.stop(now + 0.12);
        } catch (e) {}
    }

    function startSpinTicks(durationMs, rotations) {
        if (isMuted) return;
        stopSpinTicks();

        var startTime = performance.now();
        var totalTicks = Math.max(24, Math.floor(rotations * 6));

        function scheduleNext() {
            var elapsed = performance.now() - startTime;
            var progress = Math.min(elapsed / durationMs, 1);

            var eased = 1 - Math.pow(1 - progress, 4);
            var nextTickProgress = (Math.floor(eased * totalTicks) + 1) / totalTicks;

            if (progress >= 1) return;

            var nextEased = nextTickProgress;
            var nextT = 1 - Math.pow(1 - nextEased, 1 / 4);
            var nextDelay = Math.max(35, (nextT * durationMs) - elapsed);

            var speedFactor = 1 - progress;
            var tickVolume = 0.18 + 0.22 * speedFactor;

            clockTick(tickVolume);

            tickTimeout = setTimeout(scheduleNext, nextDelay);
        }

        clockTick(0.4);
        tickTimeout = setTimeout(scheduleNext, 70);
    }

    function stopSpinTicks() {
        if (tickTimeout) {
            clearTimeout(tickTimeout);
            tickTimeout = null;
        }
    }

    function playWin(isBigWin) {
        if (isMuted) return;
        var c = ensureContext();
        if (!c) return;

        var notes = isBigWin
            ? [523.25, 659.25, 783.99, 1046.50, 1318.51]
            : [523.25, 659.25, 783.99, 1046.50];

        notes.forEach(function(freq, i) {
            playTone(freq, 0.45, 'sine', 0.10, i * 0.09);
            playTone(freq * 2, 0.35, 'triangle', 0.03, i * 0.09);
        });
    }

    function playLose() {
        if (isMuted) return;
        playTone(392.00, 0.4, 'sine', 0.08, 0);
        playTone(329.63, 0.45, 'sine', 0.06, 0.2);
        playTone(261.63, 0.55, 'sine', 0.05, 0.4);
    }

    function playExtra() {
        if (isMuted) return;
        playTone(880.00, 0.18, 'sine', 0.10, 0);
        playTone(1174.66, 0.18, 'sine', 0.10, 0.1);
        playTone(1567.98, 0.3, 'sine', 0.10, 0.2);
    }

    return {
        toggle: function() {
            isMuted = !isMuted;
            if (isMuted) stopSpinTicks();
            return isMuted;
        },
        playClick: playClick,
        startSpinTicks: startSpinTicks,
        stopSpinTicks: stopSpinTicks,
        playWin: playWin,
        playLose: playLose,
        playExtra: playExtra,
        ensureContext: ensureContext,
    };
})();

var soundToggleBtn = document.getElementById('soundToggle');
if (soundToggleBtn) {
    soundToggleBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        var muted = SoundEngine.toggle();
        soundToggleBtn.textContent = muted ? '🔇' : '🔊';
        soundToggleBtn.classList.toggle('is-muted', muted);
    });
}

var CONFIG = Object.freeze({
    API_URL: window.location.origin,
    SPIN_DURATION: 5000,
    MIN_ROTATIONS: 5,
    CONFETTI_COUNT: 80,
    CONFETTI_DURATION: 3000,
});

var COLORS = Object.freeze([
    '#0f172a',
    '#1e40af',
    '#050b1a',
    '#1d4ed8',
    '#0f172a',
    '#2563eb',
    '#050b1a',
    '#3b82f6',
]);

var PULSE_COLORS_RGB = [
    [255, 60, 60],
    [60, 220, 100],
    [60, 130, 255],
    [255, 220, 50],
    [50, 220, 220],
    [230, 70, 230],
    [255, 140, 50],
    [150, 90, 240],
];

var CONFETTI_COLORS = Object.freeze([
    '#fbbf24', '#fcd34d', '#f59e0b',
    '#3b82f6', '#60a5fa', '#93c5fd',
    '#22d3ee', '#67e8f9',
    '#ffffff',
]);

var state = {
    prizes: [],
    isSpinning: false,
    rotation: 0,
    availableSpins: 0,
    todaySpins: 0,
    dailyLimit: 100,
    totalWon: 0,
};

function $(id) { return document.getElementById(id); }
var canvas = $('wheel');
var ctx = canvas.getContext('2d');
var pulseCanvas = $('pulseCanvas');
var pulseCtx = pulseCanvas.getContext('2d');
var confettiCanvas = $('confetti');
var confettiCtx = confettiCanvas.getContext('2d');

async function init() {
    try {
        var res = await fetch(CONFIG.API_URL + '/api/wheel/state', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Telegram-Init-Data': tg ? (tg.initData || '') : '',
            },
            body: JSON.stringify({
                telegram_id: tg ? (tg.initDataUnsafe && tg.initDataUnsafe.user ? tg.initDataUnsafe.user.id : null) : null,
            }),
        });

        var data = await res.json();
        if (!data.success) throw new Error(data.message || 'فشل التحميل');

        Object.assign(state, {
            prizes: data.prizes,
            availableSpins: data.available_spins,
            todaySpins: data.spins_today,
            dailyLimit: data.daily_limit,
            totalWon: data.total_won_amount,
        });

        renderStats();
        renderWheel();
        startPulseAnimation();
    } catch (err) {
        console.error('[init]', err);
        toast(err.message || 'فشل الاتصال');
    }
}

function renderStats() {
    $('statSpins').textContent = state.availableSpins;
    $('statWon').textContent = Math.round(state.totalWon).toLocaleString('en-US');

    var btn = $('spinBtn');
    var btnText = $('spinBtnText');

    if (state.availableSpins <= 0) {
        btn.disabled = true;
        btnText.textContent = 'لا توجد لفات';
    } else {
        btn.disabled = false;
        btnText.textContent = 'لف العجلة (' + state.availableSpins + ')';
    }
}

function adjustBrightness(hex, percent) {
    var num = parseInt(hex.replace('#', ''), 16);
    var amt = Math.round(2.55 * percent);
    var R = Math.max(0, Math.min(255, (num >> 16) + amt));
    var G = Math.max(0, Math.min(255, ((num >> 8) & 0x00FF) + amt));
    var B = Math.max(0, Math.min(255, (num & 0x0000FF) + amt));
    return '#' + ((1 << 24) + (R << 16) + (G << 8) + B).toString(16).slice(1);
}

function renderWheel() {
    var rect = canvas.getBoundingClientRect();
    var size = Math.min(rect.width, rect.height);
    if (size <= 0) return;

    var dpr = window.devicePixelRatio || 1;
    canvas.width = size * dpr;
    canvas.height = size * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, size, size);

    if (!state.prizes.length) return;

    var cx = size / 2;
    var cy = size / 2;
    var outerR = cx - size * 0.002;
    var rimWidth = size * 0.038;
    var innerR = outerR - rimWidth;
    var hubR = size * 0.155;
    var sliceCount = state.prizes.length;
    var sliceAngle = (Math.PI * 2) / sliceCount;

    ctx.beginPath();
    ctx.arc(cx, cy, outerR, 0, Math.PI * 2);
    ctx.fillStyle = '#050b1a';
    ctx.fill();

    var rimGrad = ctx.createLinearGradient(0, 0, 0, size);
    rimGrad.addColorStop(0, '#fef3c7');
    rimGrad.addColorStop(0.2, '#fcd34d');
    rimGrad.addColorStop(0.5, '#fbbf24');
    rimGrad.addColorStop(0.8, '#f59e0b');
    rimGrad.addColorStop(1, '#b45309');

    ctx.beginPath();
    ctx.arc(cx, cy, outerR, 0, Math.PI * 2);
    ctx.arc(cx, cy, innerR, 0, Math.PI * 2, true);
    ctx.fillStyle = rimGrad;
    ctx.fill();

    ctx.beginPath();
    ctx.arc(cx, cy, outerR, 0, Math.PI * 2);
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.5)';
    ctx.lineWidth = 1;
    ctx.stroke();

    ctx.beginPath();
    ctx.arc(cx, cy, innerR, 0, Math.PI * 2);
    ctx.strokeStyle = 'rgba(180, 83, 9, 0.9)';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    var fs = {
        value: Math.max(14, Math.min(24, size * 0.038)),
        currency: Math.max(9, Math.min(14, size * 0.022)),
        icon: Math.max(18, Math.min(30, size * 0.046)),
    };

    state.prizes.forEach(function(prize, i) {
        var start = i * sliceAngle - Math.PI / 2;
        var end = start + sliceAngle;
        var color = COLORS[i % COLORS.length];

        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, innerR, start, end);
        ctx.closePath();

        var segGrad = ctx.createRadialGradient(cx, cy, hubR, cx, cy, innerR);
        segGrad.addColorStop(0, adjustBrightness(color, 15));
        segGrad.addColorStop(0.6, color);
        segGrad.addColorStop(1, adjustBrightness(color, -15));

        ctx.fillStyle = segGrad;
        ctx.fill();

        ctx.beginPath();
        ctx.moveTo(cx + Math.cos(start) * hubR, cy + Math.sin(start) * hubR);
        ctx.lineTo(cx + Math.cos(start) * innerR, cy + Math.sin(start) * innerR);
        ctx.strokeStyle = 'rgba(251, 191, 36, 0.4)';
        ctx.lineWidth = Math.max(0.8, size * 0.0015);
        ctx.stroke();
    });

    // ============================================================
    //  📝 النصوص — إيموجي (الأقرب) → قيمة → عملة (الأبعد)
    //  المسافات: 0.32 → 0.58 → 0.82 (متساوية تقريباً)
    // ============================================================
    state.prizes.forEach(function(prize, i) {
        var start = i * sliceAngle - Math.PI / 2;
        var sliceCenter = start + sliceAngle / 2;

        ctx.save();
        ctx.translate(cx, cy);
        ctx.rotate(sliceCenter);

        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        ctx.shadowColor = 'rgba(0, 0, 0, 0.9)';
        ctx.shadowBlur = 6;

        if (prize.value > 0) {
            // ✅ الإيموجي — أقرب شيء للمركز (لكن بعيداً عن الـ Hub)
            var rEmoji = hubR + (innerR - hubR) * 0.32;
            ctx.font = '700 ' + fs.icon + 'px "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", Arial';
            ctx.fillStyle = '#ffffff';
            ctx.fillText(prize.icon || '🎁', rEmoji, 0);

            // ✅ القيمة — في المنتصف
            var rValue = hubR + (innerR - hubR) * 0.58;
            ctx.font = '900 ' + fs.value + 'px Cairo, Arial, sans-serif';
            ctx.fillStyle = '#fef3c7';
            ctx.shadowColor = 'rgba(251, 191, 36, 0.9)';
            ctx.shadowBlur = 12;
            ctx.fillText(prize.value.toFixed(0), rValue, 0);

            // ✅ العملة — الأبعد عن المركز
            var rCurrency = hubR + (innerR - hubR) * 0.82;
            ctx.font = '700 ' + fs.currency + 'px Cairo, Arial, sans-serif';
            ctx.fillStyle = 'rgba(251, 191, 36, 0.95)';
            ctx.shadowColor = 'rgba(0, 0, 0, 0.6)';
            ctx.shadowBlur = 4;
            ctx.fillText(prize.currency, rCurrency, 0);
        } else {
            // ✅ نفس ترتيب: إيموجي → نص
            var rEmoji2 = hubR + (innerR - hubR) * 0.32;
            ctx.font = '700 ' + (fs.icon * 1.15) + 'px "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", Arial';
            ctx.fillStyle = '#ffffff';
            ctx.fillText(prize.icon || '🎁', rEmoji2, 0);

            var rText = hubR + (innerR - hubR) * 0.68;
            ctx.font = '800 ' + fs.currency + 'px Cairo, Arial, sans-serif';
            ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
            ctx.shadowColor = 'rgba(0, 0, 0, 0.7)';
            ctx.shadowBlur = 4;
            ctx.fillText(prize.is_recycle ? 'EXTRA' : 'EMPTY', rText, 0);
        }

        ctx.restore();
    });

    // ============================================================
    //  Hub (المركز الذهبي)
    // ============================================================
    ctx.beginPath();
    ctx.arc(cx, cy, hubR * 1.15, 0, Math.PI * 2);
    var hubRingGrad = ctx.createRadialGradient(
        cx - hubR * 0.4, cy - hubR * 0.4, hubR * 0.2,
        cx, cy, hubR * 1.15
    );
    hubRingGrad.addColorStop(0, '#fcd34d');
    hubRingGrad.addColorStop(0.6, '#fbbf24');
    hubRingGrad.addColorStop(1, '#b45309');
    ctx.fillStyle = hubRingGrad;
    ctx.shadowColor = 'rgba(0, 0, 0, 0.8)';
    ctx.shadowBlur = size * 0.04;
    ctx.shadowOffsetY = size * 0.008;
    ctx.fill();
    ctx.shadowBlur = 0;

    ctx.beginPath();
    ctx.arc(cx, cy, hubR, 0, Math.PI * 2);
    var hubBgGrad = ctx.createRadialGradient(
        cx - hubR * 0.3, cy - hubR * 0.3, 0,
        cx, cy, hubR
    );
    hubBgGrad.addColorStop(0, '#1e3a8a');
    hubBgGrad.addColorStop(0.6, '#0f172a');
    hubBgGrad.addColorStop(1, '#050b1a');
    ctx.fillStyle = hubBgGrad;
    ctx.fill();

    ctx.beginPath();
    ctx.arc(cx, cy, hubR, 0, Math.PI * 2);
    ctx.strokeStyle = '#fbbf24';
    ctx.lineWidth = size * 0.004;
    ctx.stroke();
}

var pulseAnimationId = null;

function startPulseAnimation() {
    if (pulseAnimationId) cancelAnimationFrame(pulseAnimationId);
    function animate() {
        renderPulse();
        pulseAnimationId = requestAnimationFrame(animate);
    }
    animate();
}

function stopPulseAnimation() {
    if (pulseAnimationId) {
        cancelAnimationFrame(pulseAnimationId);
        pulseAnimationId = null;
    }
}

// ============================================================
//  🌊 النبضات — موجات من بداية القطاع (بعد الـ Hub) للحافة
//  + حلقة RGB حول الـ Hub
// ============================================================
function renderPulse() {
    var rect = pulseCanvas.getBoundingClientRect();
    var size = Math.min(rect.width, rect.height);
    if (size <= 0) return;

    var dpr = window.devicePixelRatio || 1;
    if (pulseCanvas.width !== size * dpr) {
        pulseCanvas.width = size * dpr;
        pulseCanvas.height = size * dpr;
    }

    pulseCtx.setTransform(dpr, 0, 0, dpr, 0, 0);
    pulseCtx.clearRect(0, 0, size, size);

    if (!state.prizes.length) return;

    var cx = size / 2;
    var cy = size / 2;
    var outerR = cx - size * 0.002;
    var rimWidth = size * 0.038;
    var innerR = outerR - rimWidth;
    var hubR = size * 0.155;

    // ✅ بداية الخط = نهاية الـ Hub (خارج الدائرة الذهبية) — يمنع التقاطع
    var lineStartR = hubR * 1.18;

    var sliceCount = state.prizes.length;
    var sliceAngle = (Math.PI * 2) / sliceCount;
    var time = performance.now() / 1000;

    // اللون الموحّد
    var colorCycleSpeed = 0.35;
    var colorPos = (time * colorCycleSpeed) % PULSE_COLORS_RGB.length;
    var colorIdx = Math.floor(colorPos);
    var nextColorIdx = (colorIdx + 1) % PULSE_COLORS_RGB.length;
    var blend = colorPos - colorIdx;

    var c1 = PULSE_COLORS_RGB[colorIdx];
    var c2 = PULSE_COLORS_RGB[nextColorIdx];

    var r = Math.round(c1[0] * (1 - blend) + c2[0] * blend);
    var g = Math.round(c1[1] * (1 - blend) + c2[1] * blend);
    var b = Math.round(c1[2] * (1 - blend) + c2[2] * blend);

    // نبضة موحّدة
    var pulseWave = 0.5 + 0.5 * Math.sin(time * 1.5);
    var pulseVal = 0.55 + 0.45 * pulseWave;

    // ============================================================
    //  🌟 حلقة RGB حول الـ Hub (نفس اللون الموحّد)
    // ============================================================
    var hubRingRadius = hubR * 1.10;
    var hubRingPulse = 0.5 + 0.5 * Math.sin(time * 1.8);
    var hubRingAlpha = 0.35 + 0.5 * hubRingPulse;

    // هالة خارجية
    pulseCtx.beginPath();
    pulseCtx.arc(cx, cy, hubRingRadius + size * 0.012, 0, Math.PI * 2);
    pulseCtx.strokeStyle = 'rgba(' + r + ',' + g + ',' + b + ',' + (0.25 * hubRingAlpha) + ')';
    pulseCtx.lineWidth = size * 0.020;
    pulseCtx.stroke();

    // حلقة أساسية
    pulseCtx.beginPath();
    pulseCtx.arc(cx, cy, hubRingRadius, 0, Math.PI * 2);
    pulseCtx.strokeStyle = 'rgba(' + r + ',' + g + ',' + b + ',' + hubRingAlpha + ')';
    pulseCtx.lineWidth = size * 0.005;
    pulseCtx.shadowColor = 'rgba(' + r + ',' + g + ',' + b + ',1)';
    pulseCtx.shadowBlur = size * 0.02 * hubRingPulse;
    pulseCtx.stroke();
    pulseCtx.shadowBlur = 0;

    // حلقة داخلية رقيقة
    pulseCtx.beginPath();
    pulseCtx.arc(cx, cy, hubRingRadius - size * 0.008, 0, Math.PI * 2);
    pulseCtx.strokeStyle = 'rgba(255,255,255,' + (0.6 * hubRingAlpha) + ')';
    pulseCtx.lineWidth = size * 0.0015;
    pulseCtx.stroke();

    // نقاط صغيرة حول الـ Hub (تتبع نفس اللون)
    var dotsAroundHub = sliceCount * 2;
    for (var dh = 0; dh < dotsAroundHub; dh++) {
        var dotAngle = (dh / dotsAroundHub) * Math.PI * 2 - Math.PI / 2;
        var dhx = cx + Math.cos(dotAngle) * hubRingRadius;
        var dhy = cy + Math.sin(dotAngle) * hubRingRadius;
        var dotPulse = 0.5 + 0.5 * Math.sin(time * 2.5 + dh * 0.5);
        var dotAlpha = 0.5 + 0.5 * dotPulse;

        pulseCtx.beginPath();
        pulseCtx.arc(dhx, dhy, size * 0.0035 * dotAlpha, 0, Math.PI * 2);
        pulseCtx.fillStyle = 'rgba(255,255,255,' + dotAlpha + ')';
        pulseCtx.shadowColor = 'rgba(' + r + ',' + g + ',' + b + ',1)';
        pulseCtx.shadowBlur = size * 0.008 * dotPulse;
        pulseCtx.fill();
        pulseCtx.shadowBlur = 0;
    }

    // ============================================================
    //  🌊 رسم الموجات الممتدة (من بداية القطاع للحافة)
    // ============================================================
    for (var i = 0; i < sliceCount; i++) {
        var boundaryAngle = i * sliceAngle - Math.PI / 2;

        // ✅ البداية: بعد الـ Hub — لا تدخل داخله
        var startX = cx + Math.cos(boundaryAngle) * lineStartR;
        var startY = cy + Math.sin(boundaryAngle) * lineStartR;
        var endX = cx + Math.cos(boundaryAngle) * outerR;
        var endY = cy + Math.sin(boundaryAngle) * outerR;

        // 1. الشعاع الأساسي (Beam)
        var beamGrad = pulseCtx.createLinearGradient(startX, startY, endX, endY);
        beamGrad.addColorStop(0,    'rgba(' + r + ',' + g + ',' + b + ',0)');
        beamGrad.addColorStop(0.10, 'rgba(' + r + ',' + g + ',' + b + ',' + (0.25 * pulseVal) + ')');
        beamGrad.addColorStop(0.40, 'rgba(' + r + ',' + g + ',' + b + ',' + (0.55 * pulseVal) + ')');
        beamGrad.addColorStop(0.70, 'rgba(' + r + ',' + g + ',' + b + ',' + (0.85 * pulseVal) + ')');
        beamGrad.addColorStop(0.90, 'rgba(' + r + ',' + g + ',' + b + ',' + (0.60 * pulseVal) + ')');
        beamGrad.addColorStop(1,    'rgba(' + r + ',' + g + ',' + b + ',0)');

        pulseCtx.beginPath();
        pulseCtx.moveTo(startX, startY);
        pulseCtx.lineTo(endX, endY);
        pulseCtx.strokeStyle = beamGrad;
        pulseCtx.lineWidth = size * (0.016 + 0.010 * pulseVal);
        pulseCtx.lineCap = 'round';
        pulseCtx.shadowColor = 'rgba(' + r + ',' + g + ',' + b + ',' + (pulseVal * 0.85) + ')';
        pulseCtx.shadowBlur = size * 0.035 * pulseVal;
        pulseCtx.stroke();
        pulseCtx.shadowBlur = 0;

        // 2. الشعاع المركزي
        var coreGrad = pulseCtx.createLinearGradient(startX, startY, endX, endY);
        coreGrad.addColorStop(0,    'rgba(255,255,255,0)');
        coreGrad.addColorStop(0.20, 'rgba(' + r + ',' + g + ',' + b + ',' + (0.6 * pulseVal) + ')');
        coreGrad.addColorStop(0.55, 'rgba(255,255,255,' + (0.9 * pulseVal) + ')');
        coreGrad.addColorStop(0.88, 'rgba(' + r + ',' + g + ',' + b + ',' + (0.6 * pulseVal) + ')');
        coreGrad.addColorStop(1,    'rgba(255,255,255,0)');

        pulseCtx.beginPath();
        pulseCtx.moveTo(startX, startY);
        pulseCtx.lineTo(endX, endY);
        pulseCtx.strokeStyle = coreGrad;
        pulseCtx.lineWidth = size * 0.003;
        pulseCtx.lineCap = 'round';
        pulseCtx.stroke();

        // 3. الموجات المتحركة
        var waveCount = 3;

        for (var w = 0; w < waveCount; w++) {
            var waveSpeed = 0.35 + w * 0.08;
            var phaseOffset = i * 0.18 + w * 0.33;
            var wavePos = ((time * waveSpeed + phaseOffset) % 1);

            var wx = startX + (endX - startX) * wavePos;
            var wy = startY + (endY - startY) * wavePos;

            var edgeFade = Math.sin(wavePos * Math.PI);

            var waveSize = size * 0.020 * edgeFade * pulseVal;
            var waveCoreSize = size * 0.006 * edgeFade * pulseVal;

            var waveGlowGrad = pulseCtx.createRadialGradient(wx, wy, 0, wx, wy, waveSize);
            waveGlowGrad.addColorStop(0,   'rgba(' + r + ',' + g + ',' + b + ',' + (0.85 * edgeFade * pulseVal) + ')');
            waveGlowGrad.addColorStop(0.4, 'rgba(' + r + ',' + g + ',' + b + ',' + (0.35 * edgeFade * pulseVal) + ')');
            waveGlowGrad.addColorStop(1,   'rgba(' + r + ',' + g + ',' + b + ',0)');

            pulseCtx.beginPath();
            pulseCtx.arc(wx, wy, waveSize, 0, Math.PI * 2);
            pulseCtx.fillStyle = waveGlowGrad;
            pulseCtx.fill();

            pulseCtx.beginPath();
            pulseCtx.arc(wx, wy, waveCoreSize, 0, Math.PI * 2);
            pulseCtx.fillStyle = 'rgba(255,255,255,' + (0.95 * edgeFade * pulseVal) + ')';
            pulseCtx.shadowColor = 'rgba(' + r + ',' + g + ',' + b + ',1)';
            pulseCtx.shadowBlur = size * 0.018 * pulseVal;
            pulseCtx.fill();
            pulseCtx.shadowBlur = 0;
        }
    }

    // 4. حلقة خارجية ناعمة
    var ringWave = 0.5 + 0.5 * Math.sin(time * 1.2);
    var ringAlpha = 0.15 + 0.25 * ringWave;
    var ringRadius = outerR + size * 0.008 * (0.5 + ringWave * 0.5);

    pulseCtx.beginPath();
    pulseCtx.arc(cx, cy, ringRadius, 0, Math.PI * 2);
    pulseCtx.strokeStyle = 'rgba(' + r + ',' + g + ',' + b + ',' + ringAlpha + ')';
    pulseCtx.lineWidth = size * 0.0025;
    pulseCtx.shadowColor = 'rgba(' + r + ',' + g + ',' + b + ',' + (ringAlpha * 0.8) + ')';
    pulseCtx.shadowBlur = size * 0.018 * ringWave;
    pulseCtx.stroke();
    pulseCtx.shadowBlur = 0;
}

async function spin() {
    if (state.isSpinning) return;
    if (state.availableSpins <= 0) return;

    SoundEngine.ensureContext();
    SoundEngine.playClick();

    state.isSpinning = true;
    $('spinBtn').disabled = true;
    $('centerBtn').classList.add('is-spinning');
    stopPulseAnimation();

    try {
        var res = await fetch(CONFIG.API_URL + '/api/wheel/spin', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Telegram-Init-Data': tg ? (tg.initData || '') : '',
            },
            body: JSON.stringify({
                telegram_id: tg ? (tg.initDataUnsafe && tg.initDataUnsafe.user ? tg.initDataUnsafe.user.id : null) : null,
            }),
        });

        var data = await res.json();
        if (!data.success) throw new Error(data.message || 'فشل اللف');

        var idx = -1;
        for (var p = 0; p < state.prizes.length; p++) {
            if (Number(state.prizes[p].id) === Number(data.prize_id)) {
                idx = p;
                break;
            }
        }

        if (idx === -1) throw new Error('الجائزة غير موجودة');

        var count = state.prizes.length;
        var slice = 360 / count;
        var sectorCenter = idx * slice + slice / 2;
        var targetAngle = -sectorCenter;
        var currentAngle = ((state.rotation % 360) + 360) % 360;

        var delta = targetAngle - currentAngle;
        delta = ((delta % 360) + 360) % 360;

        var target = 360 * CONFIG.MIN_ROTATIONS + delta;

        SoundEngine.startSpinTicks(CONFIG.SPIN_DURATION, CONFIG.MIN_ROTATIONS);

        animate(target, function() {
            SoundEngine.stopSpinTicks();

            Object.assign(state, {
                availableSpins: data.available_spins,
                todaySpins: data.spins_today,
                totalWon: data.total_won_amount,
            });

            renderStats();
            showResult(data.prize, data.won_value, data.currency);

            if (data.prize && data.prize.is_recycle) {
                SoundEngine.playExtra();
            } else if (data.won_value > 0) {
                var isBigWin = data.won_value >= 50000;
                SoundEngine.playWin(isBigWin);
                fireConfetti();
            } else {
                SoundEngine.playLose();
            }

            resetSpin();
        });
    } catch (err) {
        console.error('[spin]', err);
        SoundEngine.stopSpinTicks();
        toast(err.message || 'فشل الاتصال');
        resetSpin();
    }
}

function resetSpin() {
    state.isSpinning = false;
    var btn = $('spinBtn');
    btn.disabled = state.availableSpins <= 0;
    $('centerBtn').classList.remove('is-spinning');

    var rotationStr = 'rotate(' + state.rotation + 'deg)';
    canvas.style.transform = rotationStr;
    pulseCanvas.style.transform = rotationStr;

    startPulseAnimation();
}

function animate(delta, done) {
    var start = performance.now();
    var from = state.rotation;
    function easeOutQuart(t) { return 1 - Math.pow(1 - t, 4); }

    (function frame(now) {
        var t = Math.min((now - start) / CONFIG.SPIN_DURATION, 1);
        var eased = easeOutQuart(t);

        state.rotation = from + delta * eased;

        var rotationStr = 'rotate(' + state.rotation + 'deg)';
        canvas.style.transform = rotationStr;
        pulseCanvas.style.transform = rotationStr;

        if (t < 1) {
            requestAnimationFrame(frame);
        } else {
            state.rotation = (((from + delta) % 360) + 360) % 360;
            var finalRotation = 'rotate(' + state.rotation + 'deg)';
            canvas.style.transform = finalRotation;
            pulseCanvas.style.transform = finalRotation;
            done();
        }
    })(start);
}

function showResult(prize, value, currency) {
    $('resultIcon').textContent = prize.icon || '🎊';
    var title = 'مبروك!';
    var text = '';

    if (prize.is_recycle) {
        title = 'لفة مجانية!';
        text = '♻️ لفة إضافية';
    } else if (prize.is_empty) {
        title = 'حظ أوفر';
        text = '💔 لا شيء هذه المرة';
    } else if (value > 0) {
        text = '+' + value.toFixed(2) + ' ' + currency;
    }

    $('resultTitle').textContent = title;
    $('resultPrize').textContent = prize.name;
    $('resultValue').textContent = text;
    $('result').classList.add('is-open');
}

function closeResult() {
    $('result').classList.remove('is-open');
}

function toast(message) {
    if (tg) tg.showAlert(message);
    else alert(message);
}

function fireConfetti() {
    confettiCanvas.width = window.innerWidth;
    confettiCanvas.height = window.innerHeight;

    var particles = [];
    for (var i = 0; i < CONFIG.CONFETTI_COUNT; i++) {
        particles.push({
            x: Math.random() * confettiCanvas.width,
            y: -20 - Math.random() * 100,
            vx: (Math.random() - 0.5) * 4,
            vy: 2 + Math.random() * 4,
            size: 6 + Math.random() * 8,
            color: CONFETTI_COLORS[Math.floor(Math.random() * CONFETTI_COLORS.length)],
            rotation: Math.random() * 360,
            rotSpeed: (Math.random() - 0.5) * 10,
            opacity: 1,
        });
    }

    var start = performance.now();

    (function frame(now) {
        var elapsed = now - start;
        confettiCtx.clearRect(0, 0, confettiCanvas.width, confettiCanvas.height);
        var alive = 0;

        for (var i = 0; i < particles.length; i++) {
            var p = particles[i];
            p.x += p.vx;
            p.y += p.vy;
            p.vy += 0.1;
            p.rotation += p.rotSpeed;
            p.opacity = Math.max(0, 1 - elapsed / CONFIG.CONFETTI_DURATION);

            if (p.opacity > 0 && p.y < confettiCanvas.height + 50) {
                alive++;
                confettiCtx.save();
                confettiCtx.translate(p.x, p.y);
                confettiCtx.rotate((p.rotation * Math.PI) / 180);
                confettiCtx.globalAlpha = p.opacity;
                confettiCtx.fillStyle = p.color;
                confettiCtx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
                confettiCtx.restore();
            }
        }

        if (alive > 0 && elapsed < CONFIG.CONFETTI_DURATION + 500) {
            requestAnimationFrame(frame);
        } else {
            confettiCtx.clearRect(0, 0, confettiCanvas.width, confettiCanvas.height);
        }
    })(start);
}

var resizeTimer;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
        confettiCanvas.width = window.innerWidth;
        confettiCanvas.height = window.innerHeight;
        renderWheel();
    }, 150);
});

document.addEventListener('DOMContentLoaded', function() {
    init();
});
</script>
</body>
</html>`;
