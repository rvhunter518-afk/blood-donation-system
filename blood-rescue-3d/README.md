# Blood Rescue 3D

A small browser-based 3D mini-game — an educational extra feature for the
**Blood Connect** blood-donation website. Simulates a fictional emergency
blood-rescue scenario: enter a small 3D hospital, learn a patient's blood
requirement, find a matching fictional donor, collect blood, and deliver it
before time runs out.

## How to run

Because the game uses ES modules and loads Three.js from a CDN via an
import map, open it through a local web server rather than double-clicking
the file (browsers block ES module imports from `file://` URLs).

**Option A — Python's built-in server (recommended, no install needed):**

```bash
cd blood-rescue-3d
python -m http.server 8000
```

Then open **http://localhost:8000/game.html** in your browser.

**Option B — VS Code Live Server extension**, or any static file server.

> If you try to open `game.html` directly by double-clicking it, the 3D
> scene will fail to load due to browser module/CORS restrictions — use a
> local server as shown above.

## Controls

| Key | Action |
|---|---|
| W / A / S / D | Move |
| Mouse (after clicking screen) | Look around |
| E | Interact |
| Esc | Pause |

## Gameplay loop

Main Menu → Start Mission → Emergency Briefing → find the correct donor
(matching blood group) → escort them to the Blood Collection Room → collect
blood → deliver it to the Emergency Room before the timer hits zero →
Mission Complete → Score/XP → Next Level.

Five levels are included, increasing in donor count, time pressure, and
(Level 4) multi-unit requirements.

## Project structure

```
blood-rescue-3d/
├── game.html              # entry point — open this via a local server
├── css/style.css          # medical blue/white/red UI theme
├── js/
│   ├── game.js            # Three.js scene, hospital build, game loop
│   ├── player.js          # first-person controller (WASD + mouse look)
│   ├── mission.js         # timer, objectives, scoring state machine
│   ├── donor.js           # blood-group matching logic (modular)
│   ├── interaction.js     # proximity "Press E" interaction system
│   ├── ui.js               # HUD/menu DOM + minimap rendering
│   └── levels.js          # 5 level definitions (patient/donors/time)
├── assets/                # placeholders for models/textures/audio/icons
│                           # (game runs fine with none present)
└── python/
    ├── generate_data.py   # optional dev helper to prototype level data
    └── README.md
```

## Notes

- **No backend, no database, no login** — fully client-side.
- Sound is optional; the game works silently if no audio files are added
  to `assets/audio/`.
- All patients, donors, and scenarios are **fictional**. This is an
  educational simulation, not a medical decision-making tool.
- The donor-matching rule in `js/donor.js` is deliberately simple (exact
  blood-group match) and isolated in its own module so it can be replaced
  with a different fictional rule set later without touching other code.
