<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="JARVIS AI Voice Assistant — Control your browser with English, Hindi, and Hinglish voice commands.">
  <title>JARVIS AI — Voice Assistant</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&family=Inter:wght@300;400;500;600&family=Share+Tech+Mono&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/jarvis.css') }}">
</head>
<body>

<div class="jarvis-layout">

  <!-- ── Navbar ─────────────────────────────────────────────────────────────── -->
  <nav class="jarvis-nav">
    <div class="jarvis-container">
      <div class="nav-inner">
        <a href="/jarvis" class="nav-brand">
          <div class="brand-icon">J</div>
          <div>
            <div class="brand-name">JARVIS</div>
            <div class="brand-version">AI VOICE ASSISTANT v1.0</div>
          </div>
        </a>

        <div class="nav-controls">
          <div class="nav-status">
            <div class="status-dot" id="nav-status-dot"></div>
            <span id="nav-status-text" style="font-family:'Share Tech Mono',monospace;font-size:0.72rem;">IDLE</span>
          </div>

          <button class="btn-icon" id="btn-theme" title="Toggle theme">
            <span class="btn-icon-svg">☀️</span>
          </button>

          <div class="user-chip">
            <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            <span>{{ auth()->user()->name }}</span>
          </div>

          <form action="/logout" method="POST" style="margin:0;">
            @csrf
            <button class="btn-icon" type="submit" title="Logout">🚪</button>
          </form>
        </div>
      </div>
    </div>
  </nav>

  <!-- ── Main Content ───────────────────────────────────────────────────────── -->
  <main class="jarvis-container dashboard">

    <!-- Browser Compatibility Banner -->
    <div id="browser-banner" class="browser-banner warning d-none">
      <span>⚠️</span>
      <span>Checking browser compatibility…</span>
    </div>

    <!-- Main Dashboard Grid -->
    <div class="dashboard-grid">

      <!-- ── Left Column: Orb + Controls ─────────────────────────────────── -->
      <div class="glass-card" style="display:flex;flex-direction:column;">

        <!-- Orb Section -->
        <div class="orb-section">

          <!-- Central Orb -->
          <div class="orb-wrapper">
            <!-- Orbit rings -->
            <div class="orbit-ring"></div>
            <div class="orbit-ring"></div>
            <div class="orbit-ring"></div>

            <!-- Pulse rings -->
            <div class="orb-pulse orb-pulse-1"></div>
            <div class="orb-pulse orb-pulse-2"></div>
            <div class="orb-pulse orb-pulse-3"></div>

            <!-- Main orb -->
            <div class="jarvis-orb idle" id="jarvis-orb" title="Click to toggle microphone">
              <div class="orb-core">
                <div class="orb-shine"></div>
                <div class="orb-letter" id="orb-letter">J</div>
              </div>
            </div>
          </div>

          <!-- State Badge -->
          <div id="state-badge" class="state-badge idle">IDLE</div>

          <!-- Waveform -->
          <div style="width:100%;max-width:360px;">
            <div class="transcript-label">AUDIO WAVEFORM</div>
            <canvas id="waveform-canvas" class="waveform-canvas" width="360" height="60"></canvas>
          </div>

          <!-- Transcript -->
          <div style="width:100%;max-width:360px;">
            <div class="transcript-label">VOICE TRANSCRIPT</div>
            <div class="transcript-panel" id="transcript-text">
              Say "Hello Jarvis" to begin…<span class="cursor-blink"></span>
            </div>
          </div>

          <!-- Response Panel -->
          <div style="width:100%;max-width:360px;">
            <div class="response-panel" id="response-panel">
              <div class="response-text" id="response-text">Awaiting your command, {{ auth()->user()->name }}.</div>
            </div>
          </div>

        </div>

        <!-- ── Controls Row ───────────────────────────────────────────────── -->
        <div class="card-body" style="padding-top:0;">
          <div class="controls-row" style="margin-bottom:1rem;">

            <button class="btn-control" id="btn-mic" title="Enable microphone">
              <span class="btn-icon-svg" style="font-size:1.4rem;">🎤</span>
              <span>Mic</span>
            </button>

            <button class="btn-control" id="btn-mute" title="Toggle voice responses">
              <span class="btn-icon-svg" style="font-size:1.4rem;">🔊</span>
              <span>Sound</span>
            </button>

            <button class="btn-control active" id="btn-continuous" title="Continuous listening mode">
              <span class="btn-icon-svg" style="font-size:1.4rem;">🔄</span>
              <span>Auto ON</span>
            </button>

          </div>

          <!-- Text Command Input -->
          <div class="mb-1">
            <div class="transcript-label">TEXT COMMAND</div>
            <div class="text-input-wrapper">
              <input
                type="text"
                id="text-command"
                class="jarvis-input"
                placeholder="Type a command (e.g. 'open instagram')…"
                autocomplete="off"
                spellcheck="false"
              >
              <button class="btn-send" id="btn-send" title="Send">▶</button>
            </div>
          </div>

          <!-- Popup Info -->
          <div style="font-family:'Share Tech Mono',monospace;font-size:0.65rem;color:var(--text-dim);text-align:center;padding-top:0.5rem;">
            💡 Tip: Allow pop-ups in browser settings for "new tab" commands
          </div>
        </div>

      </div>

      <!-- ── Right Column: Quick Launch + History ─────────────────────────── -->
      <div style="display:flex;flex-direction:column;gap:1.5rem;">

        <!-- Quick Launch Panel -->
        <div class="glass-card">
          <div class="card-header">
            <div class="card-title">⚡ QUICK LAUNCH</div>
            <span style="font-size:0.7rem;color:var(--text-dim);">Click to open</span>
          </div>
          <div class="card-body">
            <div class="quick-launch">

              <button class="ql-btn"
                id="ql-instagram"
                data-url="https://www.instagram.com/"
                title="Open Instagram">
                <span>📸</span>
                <span class="ql-label">Instagram</span>
              </button>

              <button class="ql-btn"
                id="ql-youtube"
                data-url="https://www.youtube.com/"
                title="Open YouTube">
                <span>▶️</span>
                <span class="ql-label">YouTube</span>
              </button>

              <button class="ql-btn"
                id="ql-whatsapp"
                data-url="https://web.whatsapp.com/"
                title="Open WhatsApp Web">
                <span>💬</span>
                <span class="ql-label">WhatsApp</span>
              </button>

              <button class="ql-btn"
                id="ql-chatgpt"
                data-url="https://chatgpt.com/"
                title="Open ChatGPT">
                <span>🤖</span>
                <span class="ql-label">ChatGPT</span>
              </button>

              <button class="ql-btn"
                id="ql-google"
                data-url="https://www.google.com/"
                title="Open Google">
                <span>🔍</span>
                <span class="ql-label">Google</span>
              </button>

              <button class="ql-btn"
                id="ql-gemini"
                data-url="https://gemini.google.com/"
                title="Open Gemini">
                <span>✨</span>
                <span class="ql-label">Gemini</span>
              </button>

              <button class="ql-btn"
                id="ql-github"
                data-url="https://github.com/"
                title="Open GitHub">
                <span>🐙</span>
                <span class="ql-label">GitHub</span>
              </button>

              <button class="ql-btn"
                id="ql-gmail"
                data-url="https://mail.google.com/"
                title="Open Gmail">
                <span>📧</span>
                <span class="ql-label">Gmail</span>
              </button>

              <button class="ql-btn"
                id="ql-netflix"
                data-url="https://www.netflix.com/"
                title="Open Netflix">
                <span>🎬</span>
                <span class="ql-label">Netflix</span>
              </button>

            </div>
          </div>
        </div>

        <!-- Command History Panel -->
        <div class="glass-card" style="flex:1;">
          <div class="card-header">
            <div class="card-title">📋 COMMAND HISTORY</div>
            <button class="btn-icon" id="btn-clear-history" title="Clear history" style="width:28px;height:28px;font-size:0.75rem;">🗑️</button>
          </div>
          <div class="card-body" style="padding:1rem;">
            <div class="history-list" id="history-list">
              @if($recentHistory->count() > 0)
                @foreach($recentHistory as $cmd)
                  <div class="history-item">
                    <div class="history-cmd">{{ Str::limit($cmd->raw_command, 60) }}</div>
                    <div class="history-meta">
                      <span class="intent-badge {{ $cmd->detected_intent }}">{{ str_replace('_', ' ', $cmd->detected_intent) }}</span>
                      <span class="history-time">{{ $cmd->created_at->diffForHumans() }}</span>
                    </div>
                  </div>
                @endforeach
              @else
                <div class="history-empty" style="color:var(--text-dim);font-size:0.8rem;text-align:center;padding:1rem;">
                  No commands yet. Say "Hello Jarvis" to start!
                </div>
              @endif
            </div>
          </div>
        </div>

        <!-- Voice Commands Reference -->
        <div class="glass-card">
          <div class="card-header">
            <div class="card-title">🎙️ VOICE COMMANDS</div>
          </div>
          <div class="card-body" style="padding:1rem;">
            <div style="display:flex;flex-direction:column;gap:0.4rem;font-size:0.75rem;font-family:'Share Tech Mono',monospace;">
              <div style="color:var(--jarvis-blue);">▸ "Hello Jarvis" / "Hey Jarvis"</div>
              <div style="color:var(--text-secondary);">▸ "Instagram open karo new tab mein"</div>
              <div style="color:var(--text-secondary);">▸ "YouTube kholo" / "Open YouTube"</div>
              <div style="color:var(--text-secondary);">▸ "Google par Laravel search karo"</div>
              <div style="color:var(--text-secondary);">▸ "YouTube par PHP tutorials dhundo"</div>
              <div style="color:var(--text-secondary);">▸ "Back jao" / "Go back"</div>
              <div style="color:var(--text-secondary);">▸ "Page refresh karo" / "Reload"</div>
              <div style="color:var(--text-secondary);">▸ "Tab band karo" / "Close tab"</div>
              <div style="color:var(--jarvis-gold);">▸ "Help me" / "Madad karo"</div>
              <div style="color:var(--jarvis-red);">▸ "Stop listening" / "Sunna band karo"</div>
            </div>
          </div>
        </div>

      </div>
    </div>

  </main>

</div>

<!-- Popup Blocked Fallback Bar -->
<div id="popup-blocked-bar" class="popup-blocked-bar">
  <span>🔗</span>
  <div class="pb-text">
    Pop-up blocked. Click to open <strong id="popup-url"></strong>
  </div>
  <button class="pb-btn" id="popup-open-btn" data-url="">Open</button>
  <span class="pb-dismiss" id="popup-dismiss" title="Dismiss">✕</span>
</div>

<!-- ── JavaScript Configuration (Server-injected, secure) ──────────────── -->
<script>
  const JARVIS_CONFIG = {
    apiBase:  '/api',
    apiToken: @json($webToken),
    userName: @json(auth()->user()->name),
  };
</script>

<!-- ── Scripts ─────────────────────────────────────────────────────────────── -->
<script src="{{ asset('js/voice-recognition.js') }}"></script>
<script src="{{ asset('js/browser-actions.js') }}"></script>
<script src="{{ asset('js/jarvis.js') }}"></script>

@include('partials.sweetalert')
</body>
</html>
