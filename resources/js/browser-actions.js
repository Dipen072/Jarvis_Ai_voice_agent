/**
 * browser-actions.js
 *
 * Client-side BrowserActionService.
 * Executes validated actions received from the Jarvis API.
 *
 * IMPORTANT: All URLs are validated against a client-side allowlist
 * before execution — matching the server-side allowlist.
 *
 * POP-UP HANDLING:
 * Browsers block window.open() called from async contexts (after an API
 * call). This service uses a "deferred open" pattern:
 *   1. On API response, a pre-opened tab with "about:blank" is used
 *      (opened synchronously during the user gesture) OR
 *   2. A visible fallback button is shown so the user can manually click
 *      to open the URL (user gesture → no pop-up block).
 */

class BrowserActionService {

  // Allowlist matching the server-side WebsiteRegistryService
  static ALLOWED_ORIGINS = [
    'https://www.instagram.com',
    'https://instagram.com',
    'https://www.youtube.com',
    'https://youtube.com',
    'https://web.whatsapp.com',
    'https://whatsapp.com',
    'https://chatgpt.com',
    'https://www.chatgpt.com',
    'https://www.google.com',
    'https://google.com',
    'https://gemini.google.com',
    'https://github.com',
    'https://www.github.com',
    'https://mail.google.com',
    'https://www.facebook.com',
    'https://facebook.com',
    'https://twitter.com',
    'https://www.twitter.com',
    'https://x.com',
    'https://www.netflix.com',
    'https://www.amazon.in',
    'https://amazon.in',
    'https://www.linkedin.com',
    'https://linkedin.com',
    'https://stackoverflow.com',
    'https://maps.google.com',
  ];

  static ALLOWED_SEARCH_PATTERNS = [
    /^https:\/\/www\.google\.com\/search\?/,
    /^https:\/\/www\.youtube\.com\/results\?/,
  ];

  constructor(options = {}) {
    this.onPopupBlocked   = options.onPopupBlocked   || (() => {});
    this.onActionComplete = options.onActionComplete || (() => {});
    this.onError          = options.onError          || (() => {});
    this._pendingTab      = null; // Pre-opened blank tab for deferred navigation
  }

  /**
   * Main entry point. Called with the action object from the API response.
   * Must be called WITHIN a user gesture context (directly from a click
   * or voice-start handler) for window.open() to work reliably.
   *
   * @param {Object} action  - The action from API: { type, url, new_tab, direction }
   * @param {boolean} isUserGesture - true if called directly from user interaction
   */
  execute(action, isUserGesture = false) {
    if (!action || !action.type) return;

    switch (action.type) {
      case 'open_url':
        this._openUrl(action.url, action.new_tab ?? false, isUserGesture);
        break;
      case 'navigate':
        this._navigate(action.direction);
        break;
      case 'refresh':
        this._refresh();
        break;
      case 'close_tab':
        this._closeTab();
        break;
      case 'new_tab':
        this._openNewTab(isUserGesture);
        break;
      case 'stop_listening':
        // Handled by jarvis.js
        break;
      case 'speak_only':
        // No browser action needed
        break;
      default:
        this.onError(`Unknown action type: ${action.type}`);
    }
  }

  /**
   * Pre-open a blank tab during the user gesture phase (before the async API call).
   * Store the tab reference so we can navigate it after the API responds.
   * Returns a "tab handle" to pass to executeWithTab().
   */
  preOpenTab() {
    try {
      const tab = window.open('about:blank', '_blank');
      if (tab) {
        this._pendingTab = tab;
        return tab;
      }
    } catch (_) {}
    this._pendingTab = null;
    return null;
  }

  /**
   * Navigate a pre-opened tab to a URL after the API response.
   */
  navigatePreOpenedTab(url, tab = null) {
    const targetTab = tab || this._pendingTab;
    if (!targetTab || targetTab.closed) {
      this._pendingTab = null;
      return false;
    }

    if (!this._isUrlAllowed(url)) {
      targetTab.close();
      this._pendingTab = null;
      this.onError('URL not in allowlist.');
      return false;
    }

    try {
      targetTab.location.href = url;
      this._pendingTab = null;
      return true;
    } catch (_) {
      this._pendingTab = null;
      return false;
    }
  }

  // ── Private Methods ──────────────────────────────────────────────────────────

  _openUrl(url, newTab, isUserGesture) {
    if (!url) return;

    if (!this._isUrlAllowed(url)) {
      this.onError('URL is not in the allowed list.');
      return;
    }

    if (newTab) {
      this._openInNewTab(url, isUserGesture);
    } else {
      window.location.href = url;
      this.onActionComplete('navigated', url);
    }
  }

  _openInNewTab(url, isUserGesture) {
    if (!this._isUrlAllowed(url)) {
      this.onError('URL not allowed.');
      return;
    }

    // Check if we have a pre-opened pending tab (best approach)
    if (this._pendingTab && !this._pendingTab.closed) {
      if (this.navigatePreOpenedTab(url)) {
        this.onActionComplete('opened_new_tab', url);
        return;
      }
    }

    // Direct open attempt
    try {
      const tab = window.open(url, '_blank', 'noopener,noreferrer');
      if (tab) {
        this.onActionComplete('opened_new_tab', url);
      } else {
        // Pop-up was blocked
        this.onPopupBlocked(url);
      }
    } catch (e) {
      this.onPopupBlocked(url);
    }
  }

  _openNewTab(isUserGesture) {
    try {
      const tab = window.open('', '_blank');
      if (!tab) this.onPopupBlocked(null);
      else this.onActionComplete('new_tab', null);
    } catch (_) {
      this.onPopupBlocked(null);
    }
  }

  _navigate(direction) {
    if (direction === 'back') {
      window.history.back();
      this.onActionComplete('navigated_back', null);
    } else if (direction === 'forward') {
      window.history.forward();
      this.onActionComplete('navigated_forward', null);
    }
  }

  _refresh() {
    window.location.reload();
    this.onActionComplete('refreshed', null);
  }

  _closeTab() {
    try {
      window.close();
      // Note: browsers only allow closing tabs opened by script.
      // If the tab can't be closed, show a message.
    } catch (_) {
      this.onError('Cannot close this tab — it was not opened by Jarvis.');
    }
  }

  /**
   * Validate a URL against the client-side allowlist.
   */
  _isUrlAllowed(url) {
    if (!url || typeof url !== 'string') return false;
    if (!url.startsWith('https://') && !url.startsWith('http://')) return false;

    try {
      const parsed = new URL(url);

      // Check search patterns (Google search, YouTube search)
      for (const pattern of BrowserActionService.ALLOWED_SEARCH_PATTERNS) {
        if (pattern.test(url)) return true;
      }

      // Check allowed origins
      for (const allowed of BrowserActionService.ALLOWED_ORIGINS) {
        const allowedOrigin = new URL(allowed).origin;
        if (parsed.origin === allowedOrigin || parsed.origin.endsWith('.' + allowedOrigin)) {
          return true;
        }
      }
    } catch (_) {
      return false;
    }

    return false;
  }
}

window.BrowserActionService = BrowserActionService;
