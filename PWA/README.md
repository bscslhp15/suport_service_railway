# Progressive Web App (PWA) Setup

This folder contains all the necessary files to convert the Support Services System into a fully functional Progressive Web App.

## Files in this folder:

### 1. **manifest.json**
The Web App Manifest file that defines your app's metadata:
- App name, short name, and description
- Start URL and scope
- Display mode (standalone)
- Theme colors
- App icons (192x192 and 512x512)
- Shortcuts for quick access
- Screenshots for app stores

**Note:** You need to create icon files:
- `IMG ASSETS/passlogo-192x192.png` (192x192 pixels)
- `IMG ASSETS/passlogo-512x512.png` (512x512 pixels)

You can generate these from `IMG ASSETS/passlogo.png` using image editing tools or online converters.

### 2. **sw.js** (Service Worker)
The service worker handles offline functionality and caching:
- **Installation:** Caches static assets when first installed
- **Activation:** Cleans up old cache versions
- **Fetch:** Uses "Network First" strategy:
  - Tries to fetch from the network first
  - Falls back to cached content if offline
  - Shows offline page when absolutely necessary

### 3. **pwa-registration.js**
Registration script that:
- Registers the service worker on page load
- Detects the `beforeinstallprompt` event
- Provides utility functions for PWA operations
- Handles service worker updates
- Detects standalone mode (when running as installed app)

### 4. **install-button.js**
Custom install button controller:
- Manages the "Install App" button UI
- Triggers the installation prompt
- Handles user acceptance/decline
- Shows installation notifications
- Auto-hides button if app is already installed

### 5. **offline.html**
Fallback page displayed when:
- User is offline and no cached page is available
- Network request fails
- Server is unreachable

### 6. **pwa-styles.css**
Pre-built CSS styles for:
- Install button
- Notifications
- Update banners
- Status indicators
- Responsive design

### 7. **README.md** (this file)
Documentation and setup instructions

---

## How to Integrate into Your PHP Application

### Step 1: Add Files to HTML/PHP Head

Add these lines to your main layout file (before closing `</head>`):

```html
<!-- PWA Manifest -->
<link rel="manifest" href="/THESIS/SUPPORTSERVICESYSTEM/PWA/manifest.json">

<!-- PWA Styles -->
<link rel="stylesheet" href="/THESIS/SUPPORTSERVICESYSTEM/PWA/pwa-styles.css">

<!-- Theme Color for Mobile -->
<meta name="theme-color" content="#1f77b4">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="SSS">

<!-- Apple Splash Screens (optional) -->
<link rel="apple-touch-icon" href="/THESIS/SUPPORTSERVICESYSTEM/IMG%20ASSETS/passlogo-192x192.png">
```

### Step 2: Register Service Worker

Add this script before closing `</body>`:

```html
<!-- PWA Registration -->
<script src="/THESIS/SUPPORTSERVICESYSTEM/PWA/pwa-registration.js"></script>
```

### Step 3: Add Install Button (Optional)

Add a button element in your navigation or header:

```html
<button class="pwa-install-button" id="installButton">
  📲 Install App
</button>
```

Then include the install button controller:

```html
<!-- PWA Install Button Handler -->
<script src="/THESIS/SUPPORTSERVICESYSTEM/PWA/install-button.js"></script>
```

### Step 4: Generate App Icons

You need to create icon files from `IMG ASSETS/passlogo.png`:

1. **192x192 pixels** → Save as `IMG ASSETS/passlogo-192x192.png`
2. **512x512 pixels** → Save as `IMG ASSETS/passlogo-512x512.png`

For square, maskable icons (best practice):
- Use a tool like [Imagemin](https://github.com/imagemin/imagemin) or online converters
- Ensure icons have padding around the main content
- Use solid colors to avoid transparency issues on some devices

---

## Complete HTML Example

Here's a minimal example for your layout:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- PWA Configuration -->
    <link rel="manifest" href="/THESIS/SUPPORTSERVICESYSTEM/PWA/manifest.json">
    <meta name="theme-color" content="#1f77b4">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="/THESIS/SUPPORTSERVICESYSTEM/IMG%20ASSETS/passlogo-192x192.png">
    
    <!-- PWA Styles -->
    <link rel="stylesheet" href="/THESIS/SUPPORTSERVICESYSTEM/PWA/pwa-styles.css">
    
    <title>Support Services System</title>
</head>
<body>
    <!-- Navigation with Install Button -->
    <nav>
        <button class="pwa-install-button">📲 Install App</button>
    </nav>

    <!-- Your app content here -->
    <main>
        <!-- ... -->
    </main>

    <!-- PWA Scripts -->
    <script src="/THESIS/SUPPORTSERVICESYSTEM/PWA/pwa-registration.js"></script>
    <script src="/THESIS/SUPPORTSERVICESYSTEM/PWA/install-button.js"></script>
</body>
</html>
```

---

## Testing Your PWA

### Desktop (Chrome):
1. Open your app in Chrome
2. Look for the install icon in the address bar (or press F12, then ⋮ menu → "Install app")
3. Click to install

### Mobile (Android):
1. Open your app in Chrome
2. Wait 30 seconds
3. Tap the "Install app" prompt that appears at the bottom
4. Or use the custom button in your UI

### Check Service Worker:
- Open DevTools (F12)
- Go to **Application** tab
- Check **Service Workers** section
- Verify cache under **Cache Storage**

### Offline Mode:
1. Install the app
2. Go to **Application** tab → **Network**
3. Toggle **Offline** checkbox
4. Navigate to your app - it should still work!

---

## Configuration & Customization

### Modify manifest.json:
- Change colors, icons, start_url
- Add more shortcuts
- Adjust display mode

### Modify sw.js:
- Add more static assets to STATIC_ASSETS array
- Adjust cache strategy
- Add custom fetch handlers

### Modify pwa-registration.js:
- Change service worker path
- Adjust update check interval
- Add custom event handlers

### Modify install-button.js:
- Change button selector
- Customize notification messages
- Adjust animation timing

---

## Best Practices

1. **HTTPS Only:** PWAs require HTTPS in production (localhost is OK for development)
2. **Icons:** Always include maskable icons for better compatibility
3. **Scope:** Keep the `scope` narrow to your app folder
4. **Cache Strategy:** Network First is good for user experience but consider your use case
5. **Updates:** Service workers will check for updates automatically every 60 seconds
6. **Testing:** Test on real devices for accurate installation behavior

---

## Troubleshooting

### App won't install:
- Check HTTPS certificate (if not localhost)
- Verify manifest.json is valid JSON
- Check browser console for errors
- Clear cache and try again

### Offline page shows instead of cached page:
- Check that pages are being cached (DevTools → Application → Cache Storage)
- Verify fetch event handlers aren't blocking requests
- Check network tab to see what's failing

### Icons not showing:
- Verify icon paths in manifest.json are correct
- Check that icon files exist and are accessible
- Use DevTools to inspect actual requests to icons

### Service worker not updating:
- Force refresh with Ctrl+Shift+R or Cmd+Shift+R
- Clear cache in DevTools
- Check Application → Service Workers for updates

---

## Additional Resources

- [MDN - Web App Manifest](https://developer.mozilla.org/en-US/docs/Web/Manifest)
- [MDN - Service Workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)
- [Web.dev - PWA Guide](https://web.dev/progressive-web-apps/)
- [PWA Checklist](https://web.dev/pwa-checklist/)

---

## Support

For issues or improvements, refer to the console logs in DevTools (F12 → Console).
All PWA-related logs are prefixed with `[PWA]`, `[Service Worker]`, or `[PWA Install Button]`.
