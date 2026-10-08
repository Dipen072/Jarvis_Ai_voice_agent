<!-- SweetAlert2 CDN & JARVIS Theme Integration -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  /* JARVIS Futuristic Dark Theme for SweetAlert2 */
  .swal2-popup.jarvis-swal {
    background: #0d1322 !important;
    border: 1px solid rgba(0, 212, 255, 0.35) !important;
    box-shadow: 0 0 30px rgba(0, 212, 255, 0.25), inset 0 0 15px rgba(0, 212, 255, 0.1) !important;
    border-radius: 14px !important;
    color: #e2e8f0 !important;
    font-family: 'Inter', sans-serif !important;
  }

  .swal2-popup.jarvis-swal .swal2-title {
    color: #00d4ff !important;
    font-family: 'Orbitron', sans-serif !important;
    font-size: 1.35rem !important;
    letter-spacing: 1px !important;
    text-transform: uppercase !important;
    text-shadow: 0 0 10px rgba(0, 212, 255, 0.5) !important;
  }

  .swal2-popup.jarvis-swal .swal2-html-container {
    color: #94a3b8 !important;
    font-size: 0.95rem !important;
  }

  .swal2-popup.jarvis-swal .swal2-confirm {
    background: linear-gradient(135deg, #00d4ff, #0072ff) !important;
    color: #030712 !important;
    font-family: 'Orbitron', sans-serif !important;
    font-weight: 700 !important;
    letter-spacing: 1px !important;
    border-radius: 8px !important;
    border: none !important;
    box-shadow: 0 0 15px rgba(0, 212, 255, 0.4) !important;
    padding: 10px 24px !important;
  }

  .swal2-popup.jarvis-swal .swal2-confirm:hover {
    box-shadow: 0 0 25px rgba(0, 212, 255, 0.7) !important;
    transform: translateY(-1px) !important;
  }

  .swal2-popup.jarvis-swal .swal2-cancel {
    background: rgba(255, 255, 255, 0.08) !important;
    color: #94a3b8 !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 8px !important;
    padding: 10px 20px !important;
  }

  /* JARVIS Custom Toast Theme */
  .swal2-toast.jarvis-toast {
    background: #0b1120 !important;
    border: 1px solid rgba(0, 212, 255, 0.3) !important;
    box-shadow: 0 0 20px rgba(0, 212, 255, 0.2) !important;
    color: #e2e8f0 !important;
    border-radius: 10px !important;
    font-family: 'Inter', sans-serif !important;
  }

  .swal2-toast.jarvis-toast .swal2-title {
    color: #38bdf8 !important;
    font-family: 'Share Tech Mono', monospace !important;
    font-size: 0.9rem !important;
  }
</style>

<script>
  // Global JARVIS SweetAlert Helper Functions
  window.jarvisSwal = Swal.mixin({
    customClass: {
      popup: 'jarvis-swal',
      confirmButton: 'swal2-confirm',
      cancelButton: 'swal2-cancel'
    },
    buttonsStyling: false
  });

  window.jarvisToast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
    customClass: {
      popup: 'jarvis-toast'
    },
    didOpen: (toast) => {
      toast.addEventListener('mouseenter', Swal.stopTimer);
      toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
  });

  window.showJarvisAlert = function(title, text = '', icon = 'info') {
    return window.jarvisSwal.fire({
      title: title,
      text: text,
      icon: icon
    });
  };

  window.showJarvisToast = function(text, icon = 'info') {
    return window.jarvisToast.fire({
      title: text,
      icon: icon
    });
  };
</script>


{{-- Handle standard Laravel Session Flash Messages automatically --}}
@if (session('success'))
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      window.showJarvisToast(@json(session('success')), 'success');
    });
  </script>
@endif

@if (session('error'))
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      window.showJarvisAlert('Error', @json(session('error')), 'error');
    });
  </script>
@endif

@if (session('warning'))
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      window.showJarvisToast(@json(session('warning')), 'warning');
    });
  </script>
@endif

@if (session('info') || session('status'))
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      window.showJarvisToast(@json(session('info') ?? session('status')), 'info');
    });
  </script>
@endif
