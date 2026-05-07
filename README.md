# Visual Migration Test Tool

Ein CLI-basiertes Tool zum automatisierten Vergleich von Webseiten zwischen zwei Umgebungen (z. B. CMS Migration).

Vergleicht:
- ✅ gerendertes HTML
- ✅ Screenshots (responsive Viewports)
- ✅ visuelle Unterschiede (Pixel + Overlay)
- ✅ mehrere URLs & Scroll-Ziele

Ergebnis:
- CLI Report (`passed X/N`)
- HTML Dashboard im Browser
- visuelle Diff-Bilder

---

## ✅ Voraussetzungen

- https://ddev.readthedocs.io/ installiert
- Git
- Composer
- WSL / Linux / macOS empfohlen
-
- Google Chrome im DDEV Container (siehe .ddev.web-build/Dockerfile)
- ChromeDriver (automatisch via BDI siehe auch composer.json)


---

## 🚀 Installation

```bash
git clone git@github.com:velletti/migration-tests.git
cd migration-tests

ddev start
ddev ssh

composer install
```

## Browser & driver Setup 

Für das Rendering und Screenshot-Diff benötigt das Tool einen Headless Browser (Google Chrome) sowie einen passenden ChromeDriver.
.ddev/web-build/Dockerfile

## chrome testen

```bash
ddev exec google-chrome --version
```

## chromedriver installieren testen

```bash
ddev ssh
vendor/bin/bdi detect drivers
chromedriver --version
```

