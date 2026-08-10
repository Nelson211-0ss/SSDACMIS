# SSD-ACMIS Desktop

A thin desktop wrapper around the SSD-ACMIS web app. It doesn't run any school
data locally — it just opens the live server (`config.json` → `serverUrl`) in
its own window, so staff get a dedicated icon and taskbar app instead of a
browser tab. The server still has to be reachable over the network; this adds
nothing offline.

## Point it at your server

Edit `config.json` before building (or packaging per-school):

```json
{
  "serverUrl": "http://your-server-address:4080",
  "windowTitle": "SSD-ACMIS"
}
```

`SSDACMIS_URL` as an environment variable overrides `config.json` at runtime,
useful for testing against a different server without rebuilding.

## Run it during development

```bash
npm install
npm start
```

If `npm start` throws `Cannot read properties of undefined (reading 'getPath')`,
your shell has `ELECTRON_RUN_AS_NODE=1` set — VS Code's integrated terminal
leaks this into child shells, and it makes Electron run as plain Node instead
of launching the app. Run `unset ELECTRON_RUN_AS_NODE` first, or launch from a
regular terminal instead.

## Build an installer

```bash
npm run dist:win     # Windows installer (NSIS)
npm run dist:mac     # macOS .dmg
npm run dist:linux   # Linux AppImage
```

Output lands in `release/`. Building `dist:win` from Linux/macOS requires
Wine; it's most reliable to run that command on an actual Windows machine (or
in CI) rather than cross-building.

The app icon (`build/icon-180.png`) is reused from the web app's existing
favicon set at 180×180 — swap in a higher-resolution (512×512+) icon before a
real release; installers look noticeably better with one.
