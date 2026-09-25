/**
 * PWA Install Button Logic
 * Handles custom install button and installation prompts
 */

class PWAInstallButton {
  constructor(buttonSelector = '.pwa-install-button') {
    this.buttonSelector = buttonSelector;
    this.button = null;
    this.isInstalled = false;
    this.isInstalling = false;

    this.init();
  }

  /**
   * Initialize the install button
   */
  init() {
    console.log('[PWA Install Button] Initializing...');

    // Find the button element
    this.button = document.querySelector(this.buttonSelector);

    if (!this.button) {
      console.warn('[PWA Install Button] Button element not found:', this.buttonSelector);
      return;
    }

    // Always show button initially
    this.showInstallButton();

    // Check if app is already installed
    this.checkInstallationStatus();

    // Listen for install availability
    window.addEventListener('pwa-install-available', () => {
      this.showInstallButton();
    });

    // Listen for app installed
    window.addEventListener('pwa-app-installed', () => {
      this.handleAppInstalled();
    });

    // Button click handler
    this.button.addEventListener('click', (e) => {
      e.preventDefault();
      this.promptInstall();
    });

    // Listen for beforeinstallprompt (in case it fires after initialization)
    window.addEventListener('beforeinstallprompt', (event) => {
      console.log('[PWA Install Button] beforeinstallprompt event received');
      this.showInstallButton();
    });
  }

  /**
   * Check if app is already installed
   */
  async checkInstallationStatus() {
    try {
      this.isInstalled = await window.checkPWAInstallation();
      if (this.isInstalled) {
        this.hideInstallButton();
      }
    } catch (error) {
      console.warn('[PWA Install Button] Error checking installation:', error);
    }
  }

  /**
   * Show the install button
   */
  showInstallButton() {
    if (this.button) {
      this.button.style.display = 'inline-flex';
      this.button.classList.remove('hidden', 'disabled');
      this.button.disabled = false;
      this.button.title = 'Download and install this app';
      console.log('[PWA Install Button] Button shown');
    }
  }

  /**
   * Hide the install button
   */
  hideInstallButton() {
    if (this.button) {
      // Don't hide - keep button visible always
      // Just disable it when app is already installed
      this.button.disabled = true;
      this.button.title = 'App already installed';
      console.log('[PWA Install Button] Button disabled (app already installed)');
    }
  }

  /**
   * Disable the install button
   */
  disableInstallButton(reason = '') {
    if (this.button) {
      this.button.disabled = true;
      this.button.classList.add('disabled');
      if (reason) {
        this.button.title = reason;
        this.button.setAttribute('data-reason', reason);
      }
    }
  }

  /**
   * Prompt the user to install the app
   */
  async promptInstall() {
    console.log('[PWA Install Button] Attempting to install...');

    if (this.isInstalling) {
      console.warn('[PWA Install Button] Installation already in progress');
      return;
    }

    if (!window.deferredPrompt) {
      console.warn('[PWA Install Button] Install prompt not available');
      this.disableInstallButton('Install prompt not available');
      return;
    }

    try {
      this.isInstalling = true;
      this.button.disabled = true;
      this.button.title = 'Installing...';

      // Trigger the install prompt
      window.deferredPrompt.prompt();

      // Wait for the user to respond
      const { outcome } = await window.deferredPrompt.userChoice;

      console.log('[PWA Install Button] User choice:', outcome);

      if (outcome === 'accepted') {
        this.handleInstallAccepted();
      } else {
        this.handleInstallDeclined();
      }

      // Clear the deferred prompt
      window.deferredPrompt = null;
    } catch (error) {
      console.error('[PWA Install Button] Error during installation:', error);
      this.button.title = 'Installation failed - try again';
      this.button.disabled = false;
    } finally {
      this.isInstalling = false;
    }
  }

  /**
   * Handle successful installation
   */
  handleInstallAccepted() {
    console.log('[PWA Install Button] Installation accepted');
    this.button.title = 'App installed successfully!';
    this.button.disabled = true;
    this.isInstalled = true;

    // Show success message
    this.showNotification('App installed successfully!', 'success');

    // Dispatch custom event
    window.dispatchEvent(new CustomEvent('pwa-installation-successful'));
  }

  /**
   * Handle declined installation
   */
  handleInstallDeclined() {
    console.log('[PWA Install Button] Installation declined');
    this.button.title = 'Download and install this app';
    this.button.disabled = false;

    // Show info message
    this.showNotification('Installation cancelled', 'info');
  }

  /**
   * Handle app installed via other means
   */
  handleAppInstalled() {
    console.log('[PWA Install Button] App installed');
    this.button.disabled = true;
    this.button.title = 'App installed successfully!';
    this.isInstalled = true;
    this.showNotification('App is now installed!', 'success');
  }

  /**
   * Update button text
   */
  updateButtonText(text) {
    if (this.button) {
      this.button.textContent = text;
      this.button.innerText = text;
    }
  }

  /**
   * Show notification
   */
  showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `pwa-notification pwa-notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
      position: fixed;
      bottom: 20px;
      right: 20px;
      padding: 15px 20px;
      background-color: ${type === 'success' ? '#4caf50' : '#2196f3'};
      color: white;
      border-radius: 4px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.2);
      z-index: 9999;
      animation: slideIn 0.3s ease-in-out;
    `;

    document.body.appendChild(notification);

    // Auto-remove after 3 seconds
    setTimeout(() => {
      notification.style.animation = 'slideOut 0.3s ease-in-out';
      setTimeout(() => notification.remove(), 300);
    }, 3000);
  }

  /**
   * Destroy the install button manager
   */
  destroy() {
    if (this.button) {
      this.button.removeEventListener('click', () => this.promptInstall());
    }
    console.log('[PWA Install Button] Destroyed');
  }
}

// Auto-initialize if DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    window.pwaInstallButton = new PWAInstallButton();
  });
} else {
  window.pwaInstallButton = new PWAInstallButton();
}

// Export for manual initialization
window.PWAInstallButton = PWAInstallButton;
