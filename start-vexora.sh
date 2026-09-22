#!/usr/bin/env bash

set -euo pipefail

PROJECT_DIR="/media/ahmad/D/Laravel/Dz-bot"
LARAVEL_URL="http://127.0.0.1:8000"
SUBDOMAIN="vexora-dz-dev"
BACKEND_URL="https://${SUBDOMAIN}.loca.lt"
WORKER_URL="https://vexora-wheel.eng-ahmad-shehade.workers.dev"

LARAVEL_PID=""
TUNNEL_PID=""

cleanup() {
    echo
    echo "🛑 إيقاف العمليات..."

    if [[ -n "$TUNNEL_PID" ]]; then
        kill -9 "$TUNNEL_PID" 2>/dev/null || true
    fi

    if [[ -n "$LARAVEL_PID" ]]; then
        kill -9 "$LARAVEL_PID" 2>/dev/null || true
    fi

    echo "✅ تم التنظيف بنجاح."
}

trap cleanup EXIT INT TERM

echo "========================================"
echo "        VEXORA LOCAL DEVELOPMENT"
echo "========================================"
echo

cd "$PROJECT_DIR"

echo "🧹 تنظيف المنافذ والعمليات القديمة..."
sudo killall -9 php lt node 2>/dev/null || true
sleep 1

echo "🚀 تشغيل Laravel..."
php artisan serve --host=0.0.0.0 --port=8000 > /tmp/vexora-laravel.log 2>&1 &
LARAVEL_PID=$!

echo "⏳ انتظار استجابة Laravel..."
until curl -s --max-time 2 "$LARAVEL_URL" >/dev/null 2>&1; do
    sleep 1
done
echo "✅ Laravel أصبح جاهزًا."

echo
echo "🌐 تشغيل LocalTunnel على ($SUBDOMAIN)..."
lt --port 8000 --subdomain "$SUBDOMAIN" --local-host 127.0.0.1 > /tmp/vexora-localtunnel.log 2>&1 &
TUNNEL_PID=$!

echo "⏳ انتظار استقرار LocalTunnel (5 ثوانٍ)..."
sleep 5

echo "--- مخرجات LocalTunnel ---"
cat /tmp/vexora-localtunnel.log
echo "---------------------------"

echo
echo "========================================"
echo "        ✅ VEXORA IS READY"
echo "========================================"
echo "🎡 WebApp:  $WORKER_URL"
echo "🔗 Backend: $BACKEND_URL"
echo "📡 Laravel: $LARAVEL_URL"
echo "----------------------------------------"
echo "اضغط Ctrl+C لإيقاف السيرفر والـ Tunnel"
echo "----------------------------------------"
echo

wait "$TUNNEL_PID"
