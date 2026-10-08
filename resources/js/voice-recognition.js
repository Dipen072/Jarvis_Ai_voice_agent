/**
 * voice-recognition.js
 *
 * Handles Web Speech API recognition with:
 *  - Wake-word detection ("Hey Jarvis", "Hello Jarvis")
 *  - Continuous listening with safe retry limits
 *  - State machine management
 *  - Microphone permission handling
 *  - Browser compatibility detection
 *  - Hindi/Hinglish language support
 */

class VoiceRecognition {
  static STATES = {
    IDLE:                 'idle',
    LISTENING:            'listening',
    WAKE_WORD_DETECTED:   'wake_word',
    COMMAND_CAPTURED:     'command',
    PROCESSING:           'processing',
    EXECUTING:            'executing',
    SPEAKING:             'speaking',
  };

  static WAKE_PHRASES = [
    'hello jarvis', 'hey jarvis', 'hi jarvis',
    'jarvis hello', 'jarvis hey',
    'ok jarvis', 'okay jarvis',
    'jarvis sunno', 'jarvis suno',
  ];

  constructor(options = {}) {
    this.onStateChange      = options.onStateChange      || (() => {});
    this.onTranscript       = options.onTranscript       || (() => {});
    this.onWakeWord         = options.onWakeWord         || (() => {});
    this.onCommand          = options.onCommand          || (() => {});
    this.onError            = options.onError            || (() => {});

    this.recognition        = null;
    this.currentState       = VoiceRecognition.STATES.IDLE;
    this.isActive           = false;
    this.listeningForCommand = false;
    this.retryCount         = 0;
    this.maxRetries         = 5;
    this.retryDelay         = 1000;
    this.retryTimer         = null;
    this.silenceTimer       = null;
    this.silenceTimeout     = 8000;  // 8s silence = abandon command
    this.continuousMode     = options.continuousMode !== false;
    this.language           = options.language || 'hi-IN'; // Hindi first for Hinglish support
    this.lastCommand        = '';
    this.lastCommandTime    = 0;
    this.duplicateWindow    = 2000;

    this._initRecognition();
  }

  // ── Public API ────────────────────────────────────────────────────────────────

  get isSupported() {
    return !!(window.SpeechRecognition || window.webkitSpeechRecognition);
  }

  start() {
    if (!this.isSupported) {
      this.onError('Speech recognition is not supported in this browser. Please use Google Chrome.');
      return false;
    }
    if (this.isActive) return true;

    this.isActive   = true;
    this.retryCount = 0;
    this._startRecognition();
    return true;
  }

  stop() {
    this.isActive            = false;
    this.listeningForCommand = false;
    this._clearTimers();
    if (this.recognition) {
      try { this.recognition.stop(); } catch (_) {}
    }
    this._setState(VoiceRecognition.STATES.IDLE);
  }

  setState(state) {
    this._setState(state);
  }

  // ── Private Methods ───────────────────────────────────────────────────────────

  _initRecognition() {
    if (!this.isSupported) return;

    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    this.recognition = new SR();

    // Language: hi-IN captures Hindi + Hinglish well; en-IN for English-primary
    this.recognition.lang              = this.language;
    this.recognition.continuous        = false; // We manually restart for reliability
    this.recognition.interimResults    = true;
    this.recognition.maxAlternatives   = 3;

    this.recognition.onstart = () => {
      this.retryCount = 0;
      if (!this.listeningForCommand) {
        this._setState(VoiceRecognition.STATES.LISTENING);
      }
    };

    this.recognition.onresult = (event) => {
      this._handleResult(event);
    };

    this.recognition.onerror = (event) => {
      this._handleError(event);
    };

    this.recognition.onend = () => {
      this._handleEnd();
    };
  }

  _startRecognition() {
    if (!this.isActive || !this.recognition) return;
    try {
      this.recognition.start();
    } catch (e) {
      // Already started — ignore
      if (e.name !== 'InvalidStateError') {
        this.onError(`Recognition start error: ${e.message}`);
      }
    }
  }

  _handleResult(event) {
    let interimText = '';
    let finalText   = '';

    for (let i = event.resultIndex; i < event.results.length; i++) {
      const transcript = event.results[i][0].transcript;

      // Check alternates for wake word
      if (!event.results[i].isFinal) {
        interimText += transcript;
        // Show interim transcript
        this.onTranscript(interimText, false);
        this._resetSilenceTimer();
      } else {
        // Final result — also check alternatives
        let bestTranscript = transcript;
        for (let j = 0; j < event.results[i].length; j++) {
          const alt = event.results[i][j].transcript.toLowerCase().trim();
          if (this._isWakePhrase(alt) || this._isDirectCommand(alt)) {
            bestTranscript = event.results[i][j].transcript;
            break;
          }
        }
        finalText += bestTranscript;
      }
    }

    if (!finalText) return;

    finalText = finalText.trim();
    this.onTranscript(finalText, true);

    if (this.listeningForCommand) {
      this._handleCommandCapture(finalText);
    } else {
      const isWake = this._checkWakeWord(finalText);
      if (!isWake && (this._isDirectCommand(finalText) || finalText.length > 2)) {
        // Direct command spoken without wake word (e.g. "open youtube", "युटुब खोलो")
        this._handleCommandCapture(finalText);
      }
    }
  }

  _checkWakeWord(text) {
    const lower = text.toLowerCase().trim();
    const wakePhrase = this._getWakePhrase(lower);
    if (wakePhrase) {
      this._clearTimers();

      // Check if user said wake word + command together in one phrase (e.g. "hello jarvis open youtube")
      let extra = lower;
      const idx = lower.indexOf(wakePhrase);
      if (idx !== -1) {
        extra = lower.substring(idx + wakePhrase.length).trim();
        extra = extra.replace(/^(?:please|zara|sir|karo|mujhe)\s*/i, '').trim();
      }

      if (extra.length > 1) {
        this.listeningForCommand = false;
        this._setState(VoiceRecognition.STATES.COMMAND_CAPTURED);
        this.onCommand(extra);
      } else {
        this.listeningForCommand = true;
        this._setState(VoiceRecognition.STATES.WAKE_WORD_DETECTED);
        this.onWakeWord(text);
        this._startSilenceTimer(() => {
          this.listeningForCommand = false;
          if (this.isActive) this._restartRecognition();
        });
      }
      return true;
    }
    return false;
  }

  _isDirectCommand(text) {
    const lower = text.toLowerCase().trim();
    const keywords = [
      'open', 'kholo', 'kholna', 'launch', 'start', 'search', 'dhundo', 'dhundho',
      'youtube', 'युटुब', 'यूट्यूब', 'युट्युब', 'instagram', 'इंस्टाग्राम', 'इन्स्टाग्राम',
      'whatsapp', 'व्हाट्सएप', 'google', 'गूगल', 'chatgpt', 'चैटजीपीटी',
      'gemini', 'जेमिनी', 'github', 'गिटहब', 'gmail', 'जीमेल', 'facebook', 'फेसबुक',
      'twitter', 'ट्विटर', 'netflix', 'नेटफ्लिक्स', 'amazon', 'अमेज़न',
      'back', 'wapas', 'refresh', 'reload', 'close', 'band karo', 'tab', 'help', 'madad'
    ];
    return keywords.some(k => lower.includes(k));
  }

  _handleCommandCapture(text) {
    this._clearTimers();

    // Duplicate command prevention
    const now = Date.now();
    const lower = text.toLowerCase().trim();
    if (lower === this.lastCommand && (now - this.lastCommandTime) < this.duplicateWindow) {
      this._restartListening();
      return;
    }

    this.lastCommand     = lower;
    this.lastCommandTime = now;
    this.listeningForCommand = false;

    this._setState(VoiceRecognition.STATES.COMMAND_CAPTURED);
    this.onCommand(text);
  }

  _handleError(event) {
    const { error } = event;

    // Non-fatal errors — try to restart
    if (error === 'no-speech' || error === 'audio-capture') {
      if (this.isActive) this._scheduleRestart();
      return;
    }

    if (error === 'not-allowed' || error === 'service-not-allowed') {
      this.isActive = false;
      this._setState(VoiceRecognition.STATES.IDLE);
      this.onError('Microphone access denied. Please allow microphone access and refresh.');
      return;
    }

    if (error === 'network') {
      this.onError('Network error with speech recognition. Check your internet connection.');
      if (this.isActive) this._scheduleRestart();
      return;
    }

    // Other errors
    this.onError(`Recognition error: ${error}`);
    if (this.isActive) this._scheduleRestart();
  }

  _handleEnd() {
    if (!this.isActive) return;

    // If we were waiting for a command and speech ended, restart recognition immediately to keep listening
    if (this.listeningForCommand) {
      if (!this.silenceTimer) {
        this._startSilenceTimer(() => {
          this.listeningForCommand = false;
          if (this.isActive) this._restartRecognition();
        });
      }
      this._scheduleRestart(true);
    } else {
      this._scheduleRestart(false);
    }
  }

  _scheduleRestart(fast = false) {
    if (this.retryCount >= this.maxRetries) {
      this.onError('Too many recognition failures. Click the microphone to restart.');
      this._setState(VoiceRecognition.STATES.IDLE);
      this.isActive = false;
      return;
    }
    this.retryCount++;
    const delay = (fast || this.listeningForCommand) ? 150 : this.retryDelay;
    this.retryTimer = setTimeout(() => this._restartRecognition(), delay);
  }

  _restartRecognition() {
    if (!this.isActive) return;
    if (this.currentState !== VoiceRecognition.STATES.PROCESSING &&
        this.currentState !== VoiceRecognition.STATES.SPEAKING) {
      this._setState(VoiceRecognition.STATES.LISTENING);
    }
    this._startRecognition();
  }

  _restartListening() {
    this.listeningForCommand = false;
    if (this.isActive) this._restartRecognition();
  }

  _startSilenceTimer(cb) {
    this._clearTimers();
    this.silenceTimer = setTimeout(cb, this.silenceTimeout);
  }

  _resetSilenceTimer() {
    if (this.silenceTimer) {
      clearTimeout(this.silenceTimer);
      this.silenceTimer = setTimeout(() => {
        if (this.listeningForCommand) {
          this.listeningForCommand = false;
          if (this.isActive) this._restartRecognition();
        }
      }, this.silenceTimeout);
    }
  }

  _clearTimers() {
    if (this.retryTimer)  { clearTimeout(this.retryTimer);  this.retryTimer  = null; }
    if (this.silenceTimer){ clearTimeout(this.silenceTimer); this.silenceTimer= null; }
  }

  _setState(state) {
    if (this.currentState === state) return;
    this.currentState = state;
    this.onStateChange(state);
  }

  _isWakePhrase(text) {
    const lower = text.toLowerCase().trim();
    return VoiceRecognition.WAKE_PHRASES.some(phrase =>
      lower === phrase || lower.startsWith(phrase) || lower.endsWith(phrase)
    );
  }

  // Called externally when SPEAKING/PROCESSING ends to resume listening
  resumeListening() {
    if (!this.isActive) return;
    this._setState(VoiceRecognition.STATES.LISTENING);
    this._startRecognition();
  }
}

// Export for use in jarvis.js
window.VoiceRecognition = VoiceRecognition;
