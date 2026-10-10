# Blood Rescue 3D — Python Support Folder

This folder contains optional development/support scripts. **The game itself
does not depend on Python or this folder at all** — it is a fully
client-side browser game built with HTML5, CSS3, JavaScript, and Three.js.

## generate_data.py

A small helper script that generates fictional patient/donor JSON data,
useful when prototyping new levels before writing them by hand into
`../js/levels.js`.

Run it with:

```bash
python generate_data.py
```

It writes `fictional_data.json` in this folder. This output is for
reference/prototyping only — the live game always reads level data from
`js/levels.js`, so copy values over by hand if you want to use generated
data in-game.

## Why Python is only used here

The project brief requires a pure client-side game (no backend, no
database, no server). Python is included solely as a development/data-prep
convenience tool, not as part of the running game.
