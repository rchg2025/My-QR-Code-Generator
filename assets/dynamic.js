document.addEventListener('DOMContentLoaded', function () {
  function initDynamic(wrapper) {
    const postId = parseInt(wrapper.getAttribute('data-post-id'), 10);
    if (!postId) return;

    const qrContainer = wrapper.querySelector('.mqrg-qrcode-container');
    const remainingEl = wrapper.querySelector('.mqrg-remaining');
    const mode = (wrapper.getAttribute('data-mode') || 'effective').toLowerCase();
    const shortUrl = wrapper.getAttribute('data-short-url') || '';
    const refreshInterval = parseInt(wrapper.getAttribute('data-refresh') || '0', 10);

    let qrcode = null;
    let currentUrl = '';
    let countdown = 0;
    let timerId = null;
    let refreshTimerId = null;

    function clearTimer() {
      if (timerId) {
        clearInterval(timerId);
        timerId = null;
      }
    }

    function clearRefreshTimer() {
      if (refreshTimerId) {
        clearInterval(refreshTimerId);
        refreshTimerId = null;
      }
    }

    function renderQRCode(url) {
      if (!url) return;
      qrContainer.innerHTML = '';
      qrcode = new QRCode(qrContainer, {
        text: url,
        width: 256,
        height: 256,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H,
      });
    }

    function fmt(sec) {
      sec = Math.max(0, parseInt(sec || 0, 10));
      const h = Math.floor(sec / 3600);
      const m = Math.floor((sec % 3600) / 60);
      const s = sec % 60;
      const pad = (n) => String(n).padStart(2, '0');
      return h > 0 ? `${pad(h)}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
    }

    function tick() {
      countdown -= 1;
      if (countdown < 0) countdown = 0;
      if (remainingEl) {
        remainingEl.textContent = fmt(countdown);
      }
      if (countdown <= 0) {
        clearTimer();
        // Re-fetch when expired
        fetchEffective();
      }
    }

    function scheduleCountdown(sec, hasRotation) {
      clearTimer();
      countdown = Math.max(0, parseInt(sec || 0, 10));
      
      // Ẩn/hiện countdown dựa vào có rotation hay không
      const countdownContainer = wrapper.querySelector('.mqrg-countdown');
      if (countdownContainer) {
        if (hasRotation) {
          countdownContainer.style.display = 'block';
          if (remainingEl) {
            remainingEl.textContent = fmt(countdown);
          }
          if (countdown > 0) {
            timerId = setInterval(tick, 1000);
          }
        } else {
          countdownContainer.style.display = 'none';
        }
      }
    }

    function fetchEffective() {
      const form = new FormData();
      form.append('action', 'mqrg_get_effective');
      form.append('nonce', mqrg_data.nonce);
      form.append('post_id', String(postId));
      fetch(mqrg_data.ajax_url, { method: 'POST', body: form })
        .then((r) => r.json())
        .then((data) => {
          if (!data || !data.success || !data.data) throw new Error('Bad response');
          const eff = data.data.effective_url || '';
          const remain = data.data.remaining_seconds || 0;
          const tokenized = data.data.tokenized_short_url || '';
          const hasRotation = data.data.has_rotation || false;
          
          // Quyết định URL hiển thị trong QR
          if (hasRotation && tokenized) {
            // Có rotation: dùng tokenized URL
            currentUrl = tokenized;
          } else if (mode === 'short' && shortUrl) {
            // Mode short: dùng short URL thường
            currentUrl = shortUrl;
          } else {
            // Mặc định: dùng effective URL (base_url)
            currentUrl = eff;
          }
          
          renderQRCode(currentUrl);
          scheduleCountdown(remain, hasRotation);
        })
        .catch((e) => {
          console.error('mqrg_get_effective error:', e);
        });
    }

    // Initial fetch
    fetchEffective();

    // Setup auto-refresh timer if refreshInterval > 0
    if (refreshInterval > 0) {
      clearRefreshTimer();
      refreshTimerId = setInterval(function() {
        console.log('Auto-refreshing QR code...');
        fetchEffective();
      }, refreshInterval * 1000);
    }
  }

  document.querySelectorAll('.mqrg-dynamic-wrap').forEach(initDynamic);
});
