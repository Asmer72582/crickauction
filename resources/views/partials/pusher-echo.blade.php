<script src="/vendor/pusher.min.js"></script>
<script src="/vendor/echo.iife.js"></script>
<script>
  window.CRICKET_PUSHER = @json(\App\Support\WebSocketConfig::pusherClientConfig(request()));
</script>
