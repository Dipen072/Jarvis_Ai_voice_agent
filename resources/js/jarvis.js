/**
 * jarvis.js
 *
 * Main JARVIS coordinator — orchestrates:
 *  - VoiceRecognition (wake word + command capture)
 *  - Larvel API calls
 *  - BrowserActionService
 *  - SpeechSynthesis (Text-to-Speech)
 *  - UI state updates
 *  - Audio waveform animation
 *  - Command history display
 */

class JarvisApp {
  constructor(config = {}) {
    this.apiBase     = config.apiBase     || '/api';
    this.apiToken    = config.apiToken    || '';
    this.userName    = config.userName    || 'Sir';

    // State
    this.state             = 'idle';
    this.isMuted           = false;
    this.isContinuous      = true;
    this.pendingAction     = null;
    this.pendingTab        = null;
    this.theme             = localStorage.getItem('jarvis-theme') || 'dark';

    // Services
    this.voiceRecognition  = null;
    this.browserActions    = null;
    this.speechSynth       = window.speechSynthesis || null;
    this.currentVoice      = null;
    this.voices            = [];

    // UI elements
    this.el = {};

    // Waveform
    this.waveformCtx       = null;
    this.waveformInterval  = null;
    this.waveformLevel     = 0;

    this._init();
  }

  // ── Initialization ──────────────────────────────────────────────────────────

  _init() {
    this._cacheElements();
    this._applyTheme();
    this._initBrowserActions();
    this._initVoiceRecognition();
    this._initSpeechSynthesis();
    this._initWaveform();
    this._bindEvents();
    this._checkBrowserCompat();
    this._setState('idle');
    this._log('Jarvis initialized.');

    // Auto-enable mic on load or first user click
    setTimeout(() => this._autoStartMic(), 400);
  }

  _autoStartMic() {
    if (!this.voiceRecognition || this.voiceRecognition.isActive) return;
    const started = this.voiceRecognition.start();
    if (started) {
      this._updateMicUI(true);
      this._showBanner('Jarvis Voice Active — Speak any command or "Hello Jarvis"', 'success');
    } else {
      const onUserInteraction = () => {
        if (this.voiceRecognition && !this.voiceRecognition.isActive) {
          const ok = this.voiceRecognition.start();
          if (ok) {
            this._updateMicUI(true);
            this._showBanner('Jarvis Voice Active — Listening…', 'success');
          }
        }
        window.removeEventListener('click', onUserInteraction);
      };
      window.addEventListener('click', onUserInteraction);
    }
  }

  _cacheElements() {
    const q = (id) => document.getElementById(id);
    this.el = {
      orb:             q('jarvis-orb'),
      orbLetter:       q('orb-letter'),
      stateBadge:      q('state-badge'),
      statusDot:       q('nav-status-dot'),
      statusText:      q('nav-status-text'),
      transcript:      q('transcript-text'),
      responseText:    q('response-text'),
      responsePanel:   q('response-panel'),
      waveform:        q('waveform-canvas'),
      historyList:     q('history-list'),
      browserBanner:   q('browser-banner'),
      popupBar:        q('popup-blocked-bar'),
      popupUrl:        q('popup-url'),
      popupOpenBtn:    q('popup-open-btn'),
      popupDismiss:    q('popup-dismiss'),
      micBtn:          q('btn-mic'),
      muteBtn:         q('btn-mute'),
      continuousBtn:   q('btn-continuous'),
      themeBtn:        q('btn-theme'),
      textInput:       q('text-command'),
      sendBtn:         q('btn-send'),
      clearHistBtn:    q('btn-clear-history'),
    };
  }

  // ── Voice Recognition ───────────────────────────────────────────────────────

  _initVoiceRecognition() {
    this.voiceRecognition = new VoiceRecognition({
      continuousMode: this.isContinuous,
      language: 'hi-IN',

      onStateChange: (state) => {
        if (state === 'idle') this._setState('idle');
        else if (state === 'listening') this._setState('listening');
      },

      onTranscript: (text, isFinal) => {
        this._updateTranscript(text, isFinal);
      },

      onWakeWord: (text) => {
        this._log(`Wake word: "${text}"`);
        this._playBeep(880, 80);
        const greeting = this._randomFrom([
          `Hello ${this.userName}, how may I assist you?`,
          `At your service, ${this.userName}. What can I do for you?`,
          `Good day ${this.userName}. I'm listening.`,
        ]);

        if (this.voiceRecognition && this.voiceRecognition.isActive) {
          this.voiceRecognition.listeningForCommand = true;
          this.voiceRecognition.resumeListening();
        }

        this._speak(greeting, () => {
          if (this.voiceRecognition && this.voiceRecognition.isActive) {
            this.voiceRecognition.listeningForCommand = true;
            this.voiceRecognition.resumeListening();
          }
        });
      },

      onCommand: (text) => {
        this._processCommand(text);
      },

      onError: (msg) => {
        this._showBanner(msg, 'error');
        this._setState('idle');
        this._updateMicUI(false);
      },
    });
  }

  // ── Browser Actions ─────────────────────────────────────────────────────────

  _initBrowserActions() {
    this.browserActions = new BrowserActionService({
      onPopupBlocked: (url) => {
        this._showPopupBlockedBar(url);
      },
      onActionComplete: (type, url) => {
        this._log(`Action: ${type} → ${url || ''}`);
      },
      onError: (msg) => {
        this._showBanner(msg, 'error');
      },
    });
  }

  // ── Speech Synthesis ────────────────────────────────────────────────────────

  _initSpeechSynthesis() {
    if (!this.speechSynth) {
      this._log('SpeechSynthesis not supported.');
      return;
    }
    const loadVoices = () => {
      this.voices = this.speechSynth.getVoices();
      // Prefer a natural English voice
      this.currentVoice =
        this.voices.find(v => v.name.toLowerCase().includes('google') && v.lang === 'en-US') ||
        this.voices.find(v => v.lang === 'en-US') ||
        this.voices.find(v => v.lang.startsWith('en')) ||
        this.voices[0] || null;
    };
    loadVoices();
    this.speechSynth.onvoiceschanged = loadVoices;
  }

  _speak(text, onEnd = null) {
    if (!this.speechSynth || this.isMuted) {
      if (onEnd) onEnd();
      return;
    }

    try {
      this.speechSynth.cancel();
    } catch (_) {}

    if (this._ttsSafetyTimer) {
      clearTimeout(this._ttsSafetyTimer);
      this._ttsSafetyTimer = null;
    }

    const utt = new SpeechSynthesisUtterance(text);
    utt.voice  = this.currentVoice;
    utt.rate   = 1.0;
    utt.pitch  = 1.0;
    utt.volume = 1.0;

    let hasEnded = false;
    const finish = () => {
      if (hasEnded) return;
      hasEnded = true;
      if (this._ttsSafetyTimer) {
        clearTimeout(this._ttsSafetyTimer);
        this._ttsSafetyTimer = null;
      }
      this._setState('idle');
      if (onEnd) {
        onEnd();
      } else if (this.voiceRecognition?.isActive && this.isContinuous) {
        setTimeout(() => this.voiceRecognition.resumeListening(), 200);
      }
    };

    utt.onstart = () => {
      this._setState('speaking');
    };

    utt.onend = () => {
      finish();
    };

    utt.onerror = (e) => {
      console.warn('[JARVIS] SpeechSynthesis error/cancel:', e);
      finish();
    };

    // Chrome SpeechSynthesis safety timeout: force state reset if onend never fires
    const maxDuration = Math.max(3000, (text.length * 100) + 3000);
    this._ttsSafetyTimer = setTimeout(() => {
      if (!hasEnded) {
        console.warn('[JARVIS] TTS safety timeout triggered, forcing state reset.');
        try { this.speechSynth.cancel(); } catch (_) {}
        finish();
      }
    }, maxDuration);

    try {
      this.speechSynth.speak(utt);
    } catch (err) {
      finish();
    }
  }

  // ── Command Processing ──────────────────────────────────────────────────────

  async _processCommand(command) {
    if (!command.trim()) return;

    this._setState('processing');
    this._updateTranscript(`▶ ${command}`, true);
    this._log(`Processing: "${command}"`);

    // Pre-open a tab NOW (user gesture context) for websites that need new tab
    // We'll navigate it after the API response
    const quickIntent = this._quickGuessNewTab(command);
    if (quickIntent) {
      this.pendingTab = this.browserActions.preOpenTab();
    }

    try {
      const response = await fetch(`${this.apiBase}/jarvis/command`, {
        method:  'POST',
        headers: {
          'Content-Type':  'application/json',
          'Accept':        'application/json',
          'Authorization': `Bearer ${this.apiToken}`,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ command }),
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.message || 'API error');
      }

      this._handleApiResponse(data);

    } catch (error) {
      // Close pre-opened tab on error
      if (this.pendingTab && !this.pendingTab.closed) {
        this.pendingTab.close();
        this.pendingTab = null;
      }
      const errMsg = "I'm sorry sir, I encountered an error. Please try again.";
      this._displayResponse(errMsg);
      this._speak(errMsg);
      this._setState('idle');
      this._log(`Error: ${error.message}`);
    }
  }

  _handleApiResponse(data) {
    const { intent, message, action } = data;

    this._setState('executing');
    this._displayResponse(message);
    this._addToHistory(this.el.transcript?.textContent || '', intent, message);

    // Execute browser action
    if (action && action.type !== 'speak_only') {
      this._executeAction(action);
    }

    // Speak the response
    if (intent === 'stop_listening') {
      this._speak(message, () => {
        if (this.voiceRecognition) this.voiceRecognition.stop();
        this._setState('idle');
        this._updateMicUI(false);
      });
    } else {
      this._speak(message);
    }

    this._log(`Intent: ${intent}, Source: ${data.source}`);
  }

  _executeAction(action) {
    // If we have a pending pre-opened tab and the action needs a new tab, use it
    if (this.pendingTab && !this.pendingTab.closed && action.type === 'open_url' && action.new_tab) {
      const success = this.browserActions.navigatePreOpenedTab(action.url, this.pendingTab);
      this.pendingTab = null;
      if (!success) {
        this._showPopupBlockedBar(action.url);
      }
      return;
    }

    // Close unused pending tab
    if (this.pendingTab && !this.pendingTab.closed) {
      this.pendingTab.close();
      this.pendingTab = null;
    }

    this.browserActions.execute(action, false);
  }

  /**
   * Quick check if the command likely needs a new tab (for pre-opening).
   */
  _quickGuessNewTab(command) {
    const lower = command.toLowerCase();
    return lower.includes('open') || lower.includes('kholo') || lower.includes('kholna') ||
           lower.includes('launch') || lower.includes('search') || lower.includes('dhundo') ||
           lower.includes('youtube') || lower.includes('google') || lower.includes('instagram') ||
           lower.includes('whatsapp') || lower.includes('facebook') || lower.includes('twitter') ||
           lower.includes('github') || lower.includes('gmail') || lower.includes('netflix') ||
           lower.includes('new tab') || lower.includes('naye tab') || lower.includes('naya tab') ||
           lower.includes('tab');
  }

  // ── UI State Management ─────────────────────────────────────────────────────

  _setState(state) {
    this.state = state;

    const labels = {
      idle:       'IDLE',
      listening:  'LISTENING',
      wake_word:  'WAKE DETECTED',
      command:    'COMMAND CAPTURED',
      processing: 'PROCESSING',
      executing:  'EXECUTING',
      speaking:   'SPEAKING',
      error:      'ERROR',
    };

    if (this.el.stateBadge) {
      this.el.stateBadge.textContent = labels[state] || state.toUpperCase();
      this.el.stateBadge.className   = `state-badge ${state}`;
    }

    if (this.el.statusDot) {
      this.el.statusDot.className = `status-dot ${state}`;
    }

    if (this.el.statusText) {
      this.el.statusText.textContent = labels[state] || state;
    }

    if (this.el.orb) {
      this.el.orb.className = `jarvis-orb ${state}`;
    }

    // Waveform animation control
    if (state === 'listening' || state === 'speaking') {
      this._startWaveform();
    } else if (state === 'idle') {
      this._stopWaveform();
    }
  }

  _updateTranscript(text, isFinal) {
    if (!this.el.transcript) return;
    if (isFinal) {
      this.el.transcript.textContent = text;
    } else {
      this.el.transcript.textContent = text + '…';
    }
  }

  _displayResponse(message) {
    if (this.el.responseText) {
      this.el.responseText.textContent = message;
    }
  }

  _addToHistory(command, intent, response) {
    if (!this.el.historyList) return;

    // Remove "empty" placeholder if present
    const empty = this.el.historyList.querySelector('.history-empty');
    if (empty) empty.remove();

    const item = document.createElement('div');
    item.className = 'history-item';

    const intentClass = intent.replace(/_/g, '-');
    const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    item.innerHTML = `
      <div class="history-cmd">${this._escHtml(command.replace('▶ ', '').substring(0, 60))}</div>
      <div class="history-meta">
        <span class="intent-badge ${intent}">${intent.replace(/_/g, ' ')}</span>
        <span class="history-time">${time}</span>
      </div>
    `;

    this.el.historyList.prepend(item);

    // Keep max 10 items
    const items = this.el.historyList.querySelectorAll('.history-item');
    if (items.length > 10) items[items.length - 1].remove();
  }

  _showBanner(message, type = 'warning') {
    if (!this.el.browserBanner) return;
    this.el.browserBanner.className = `browser-banner ${type}`;
    this.el.browserBanner.innerHTML = `<span>${type === 'error' ? '⚠️' : 'ℹ️'}</span> ${this._escHtml(message)}`;
    this.el.browserBanner.classList.remove('d-none');
    if (type !== 'error') {
      setTimeout(() => this.el.browserBanner.classList.add('d-none'), 5000);
    }
  }

  _showPopupBlockedBar(url) {
    if (!this.el.popupBar) return;
    if (this.el.popupUrl)    this.el.popupUrl.textContent    = url ? `→ ${new URL(url).hostname}` : 'New tab';
    if (this.el.popupOpenBtn) this.el.popupOpenBtn.dataset.url = url || '';
    this.el.popupBar.classList.add('visible');
  }

  _hidePopupBar() {
    if (this.el.popupBar) this.el.popupBar.classList.remove('visible');
  }

  // ── Waveform ────────────────────────────────────────────────────────────────

  _initWaveform() {
    if (!this.el.waveform) return;
    this.waveformCtx = this.el.waveform.getContext('2d');
    const { width, height } = this.el.waveform.getBoundingClientRect();
    this.el.waveform.width  = width || 400;
    this.el.waveform.height = 60;
    this._drawIdleWaveform();
  }

  _drawIdleWaveform() {
    const ctx = this.waveformCtx;
    if (!ctx) return;
    const w = this.el.waveform.width;
    const h = this.el.waveform.height;
    ctx.clearRect(0, 0, w, h);
    ctx.strokeStyle = 'rgba(0, 212, 255, 0.2)';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(0, h / 2);
    ctx.lineTo(w, h / 2);
    ctx.stroke();
  }

  _startWaveform() {
    if (this.waveformInterval) return;
    this.waveformInterval = setInterval(() => this._drawWaveform(), 50);
  }

  _stopWaveform() {
    if (this.waveformInterval) {
      clearInterval(this.waveformInterval);
      this.waveformInterval = null;
    }
    this._drawIdleWaveform();
  }

  _drawWaveform() {
    const ctx = this.waveformCtx;
    if (!ctx) return;
    const w = this.el.waveform.width;
    const h = this.el.waveform.height;

    ctx.clearRect(0, 0, w, h);

    const barCount = 48;
    const barW     = w / barCount - 1;

    for (let i = 0; i < barCount; i++) {
      const t = Date.now() / 1000;
      const amp = this.state === 'idle' ? 2 :
                  this.state === 'speaking' ? 15 : 20;
      const height = amp + Math.sin(t * 4 + i * 0.4) * amp * 0.7
                        + Math.sin(t * 7 + i * 0.2) * amp * 0.3
                        + (Math.random() - 0.5) * (this.state === 'idle' ? 0 : 6);
      const barH   = Math.max(2, Math.abs(height));
      const x      = i * (barW + 1);
      const y      = (h - barH) / 2;

      const color = this.state === 'speaking' ? '#ffd700' :
                    this.state === 'processing' ? '#7b2ff7' : '#00d4ff';

      ctx.fillStyle = color;
      ctx.globalAlpha = 0.7 + 0.3 * Math.sin(t + i);
      ctx.fillRect(x, y, barW, barH);
    }
    ctx.globalAlpha = 1;
  }

  // ── Event Binding ───────────────────────────────────────────────────────────

  _bindEvents() {
    // Mic button
    this.el.micBtn?.addEventListener('click', () => this._toggleMic());

    // Mute button
    this.el.muteBtn?.addEventListener('click', () => this._toggleMute());

    // Continuous mode
    this.el.continuousBtn?.addEventListener('click', () => this._toggleContinuous());

    // Theme toggle
    this.el.themeBtn?.addEventListener('click', () => this._toggleTheme());

    // Text input send
    this.el.sendBtn?.addEventListener('click', () => this._sendTextCommand());
    this.el.textInput?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') this._sendTextCommand();
    });

    // Quick launch buttons
    document.querySelectorAll('.ql-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const url = btn.dataset.url;
        if (url) {
          const action = { type: 'open_url', url, new_tab: true };
          this.browserActions.execute(action, true);
        }
      });
    });

    // Popup blocked bar
    this.el.popupOpenBtn?.addEventListener('click', () => {
      const url = this.el.popupOpenBtn.dataset.url;
      if (url) window.open(url, '_blank', 'noopener,noreferrer');
      this._hidePopupBar();
    });
    this.el.popupDismiss?.addEventListener('click', () => this._hidePopupBar());

    // Clear history button
    this.el.clearHistBtn?.addEventListener('click', () => this._clearHistory());

    // Orb click = toggle mic
    this.el.orb?.addEventListener('click', () => this._toggleMic());

    // Window resize — resize waveform
    window.addEventListener('resize', () => {
      if (this.el.waveform) {
        this.el.waveform.width = this.el.waveform.getBoundingClientRect().width || 400;
        this._drawIdleWaveform();
      }
    });
  }

  // ── Controls ────────────────────────────────────────────────────────────────

  _toggleMic() {
    if (!this.voiceRecognition) return;
    if (!this.voiceRecognition.isSupported) {
      this._showBanner('Speech recognition not supported. Please use Chrome.', 'error');
      return;
    }

    if (this.voiceRecognition.isActive) {
      this.voiceRecognition.stop();
      this._updateMicUI(false);
      this._setState('idle');
      this._showBanner('Microphone disabled.', 'warning');
    } else {
      const started = this.voiceRecognition.start();
      if (started) {
        this._updateMicUI(true);
        this._showBanner('Listening for "Hello Jarvis"…', 'success');
      }
    }
  }

  _updateMicUI(isOn) {
    if (!this.el.micBtn) return;
    if (isOn) {
      this.el.micBtn.classList.add('mic-active');
      this.el.micBtn.title = 'Click to stop listening';
      const icon = this.el.micBtn.querySelector('.btn-icon-svg');
      if (icon) icon.textContent = '🔴';
    } else {
      this.el.micBtn.classList.remove('mic-active');
      this.el.micBtn.title = 'Click to enable microphone';
    }
  }

  _toggleMute() {
    this.isMuted = !this.isMuted;
    if (this.el.muteBtn) {
      this.el.muteBtn.classList.toggle('active', this.isMuted);
      const icon = this.el.muteBtn.querySelector('.btn-icon-svg');
      if (icon) icon.textContent = this.isMuted ? '🔇' : '🔊';
      const label = this.el.muteBtn.querySelector('span');
      if (label) label.textContent = this.isMuted ? 'Muted' : 'Sound';
    }
    if (this.isMuted && this.speechSynth) {
      this.speechSynth.cancel();
    }
  }

  _toggleContinuous() {
    this.isContinuous = !this.isContinuous;
    if (this.el.continuousBtn) {
      this.el.continuousBtn.classList.toggle('active', this.isContinuous);
      const label = this.el.continuousBtn.querySelector('span');
      if (label) label.textContent = this.isContinuous ? 'Auto ON' : 'Auto OFF';
    }
  }

  _toggleTheme() {
    this.theme = this.theme === 'dark' ? 'light' : 'dark';
    this._applyTheme();
    localStorage.setItem('jarvis-theme', this.theme);
  }

  _applyTheme() {
    document.documentElement.setAttribute('data-theme', this.theme);
    if (this.el.themeBtn) {
      const icon = this.el.themeBtn.querySelector('.btn-icon-svg');
      if (icon) icon.textContent = this.theme === 'dark' ? '☀️' : '🌙';
    }
  }

  _sendTextCommand() {
    const text = this.el.textInput?.value.trim();
    if (!text) return;
    if (this.el.textInput) this.el.textInput.value = '';
    this._processCommand(text);
  }

  _clearHistory() {
    if (!this.el.historyList) return;
    this.el.historyList.innerHTML =
      '<div class="history-empty" style="color: var(--text-dim); font-size: 0.8rem; text-align: center; padding: 1rem;">No commands yet.</div>';

    fetch(`${this.apiBase}/jarvis/history`, {
      method: 'DELETE',
      headers: { 'Authorization': `Bearer ${this.apiToken}`, 'Accept': 'application/json' },
    }).catch(() => {});
  }

  // ── Browser Compat Check ────────────────────────────────────────────────────

  _checkBrowserCompat() {
    const isChrome  = /Chrome/.test(navigator.userAgent) && !/Edge|OPR/.test(navigator.userAgent);
    const hasSSR    = !!(window.SpeechRecognition || window.webkitSpeechRecognition);
    const hasSynth  = !!window.speechSynthesis;

    if (!hasSSR) {
      this._showBanner(
        'Voice recognition requires Chrome browser. Text commands still work.',
        'warning'
      );
    } else if (!isChrome) {
      this._showBanner(
        'Best experience in Chrome. Some features may be limited in this browser.',
        'warning'
      );
    } else {
      if (this.el.browserBanner) this.el.browserBanner.classList.add('d-none');
    }
  }

  // ── Utilities ───────────────────────────────────────────────────────────────

  _playBeep(freq = 440, duration = 100) {
    try {
      const ctx  = new (window.AudioContext || window.webkitAudioContext)();
      const osc  = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.frequency.value = freq;
      osc.type = 'sine';
      gain.gain.setValueAtTime(0.1, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration / 1000);
      osc.start();
      osc.stop(ctx.currentTime + duration / 1000);
    } catch (_) {}
  }

  _randomFrom(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
  }

  _escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  _log(msg) {
    console.log(`[JARVIS] ${msg}`);
  }
}

// Auto-init when DOM ready (config injected from Blade template)
document.addEventListener('DOMContentLoaded', () => {
  if (typeof JARVIS_CONFIG !== 'undefined') {
    window.jarvis = new JarvisApp(JARVIS_CONFIG);
  }
});
