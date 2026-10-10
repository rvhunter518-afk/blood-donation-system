// game.js
// Main entry point: sets up Three.js scene, builds the hospital,
// spawns characters, wires up the player controller, interaction
// system, mission logic, and UI into a full game loop.

import * as THREE from "three";
import { LEVELS, getLevel } from "./levels.js";
import { Mission } from "./mission.js";
import { Player } from "./player.js";
import { InteractionSystem } from "./interaction.js";
import { UI, showScreen } from "./ui.js";

// ---------------------------------------------------------------
// GLOBAL STATE
// ---------------------------------------------------------------
const progress = {
  unlockedUpTo: 1,
  totalScore: 0,
  totalXP: 0,
};

let renderer, scene, camera, clock;
let player, interaction, mission;
let currentLevelId = 1;
let walls = []; // THREE.Box3 list for collision
let animatedCharacters = []; // { mesh, baseY } for gentle bob animation
let roomMarkers = {}; // named positions for minimap + navigation
let sceneRoot = null; // group holding current hospital, cleared on rebuild

// ---------------------------------------------------------------
// INIT THREE.JS CORE
// ---------------------------------------------------------------
function initRenderer() {
  const canvas = document.getElementById("game-canvas");
  renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(window.innerWidth, window.innerHeight);
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;

  scene = new THREE.Scene();
  scene.background = new THREE.Color(0xdfe9f5);
  scene.fog = new THREE.Fog(0xdfe9f5, 12, 40);

  camera = new THREE.PerspectiveCamera(
    70,
    window.innerWidth / window.innerHeight,
    0.1,
    200
  );
  camera.position.set(0, 1.6, 0);

  clock = new THREE.Clock();

  window.addEventListener("resize", () => {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
  });
}

// ---------------------------------------------------------------
// MATERIALS (shared, simple, low-poly friendly)
// ---------------------------------------------------------------
const MAT = {
  floor: new THREE.MeshStandardMaterial({ color: 0xeaf1fb, roughness: 0.9 }),
  floorAccent: new THREE.MeshStandardMaterial({ color: 0xd7e6fb, roughness: 0.9 }),
  wall: new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.95 }),
  wallBlue: new THREE.MeshStandardMaterial({ color: 0xbcd6f5, roughness: 0.9 }),
  door: new THREE.MeshStandardMaterial({ color: 0x1565c0, roughness: 0.6 }),
  bed: new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.8 }),
  bedSheet: new THREE.MeshStandardMaterial({ color: 0xe63946, roughness: 0.8 }),
  chair: new THREE.MeshStandardMaterial({ color: 0x3f8efc, roughness: 0.7 }),
  desk: new THREE.MeshStandardMaterial({ color: 0xcfd8e3, roughness: 0.7 }),
  patient: new THREE.MeshStandardMaterial({ color: 0xffe0b2, roughness: 0.8 }),
  patientGown: new THREE.MeshStandardMaterial({ color: 0xbde0fe, roughness: 0.8 }),
  doctorCoat: new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.7 }),
  nurseOutfit: new THREE.MeshStandardMaterial({ color: 0xa8dadc, roughness: 0.7 }),
  donorShirt: new THREE.MeshStandardMaterial({ color: 0x4caf50, roughness: 0.7 }),
  donorShirtSelected: new THREE.MeshStandardMaterial({ color: 0xffb703, roughness: 0.7 }),
  skin: new THREE.MeshStandardMaterial({ color: 0xf0c9a0, roughness: 0.8 }),
  sign: new THREE.MeshStandardMaterial({ color: 0xe63946, roughness: 0.5 }),
  equipment: new THREE.MeshStandardMaterial({ color: 0x8fa3bf, roughness: 0.6 }),
  bloodBag: new THREE.MeshStandardMaterial({ color: 0xd00000, roughness: 0.4 }),
};

// ---------------------------------------------------------------
// SIMPLE LOW-POLY CHARACTER BUILDER
// ---------------------------------------------------------------
function buildCharacter({ bodyColor, headColor = 0xf0c9a0, height = 1.6 }) {
  const group = new THREE.Group();

  const bodyMat = new THREE.MeshStandardMaterial({ color: bodyColor, roughness: 0.75 });
  const headMat = new THREE.MeshStandardMaterial({ color: headColor, roughness: 0.8 });

  const torso = new THREE.Mesh(new THREE.CapsuleGeometry(0.28, height * 0.45, 4, 8), bodyMat);
  torso.position.y = height * 0.5;
  torso.castShadow = true;
  group.add(torso);

  const head = new THREE.Mesh(new THREE.SphereGeometry(0.2, 12, 12), headMat);
  head.position.y = height * 0.5 + height * 0.35;
  head.castShadow = true;
  group.add(head);

  // simple arms
  const armGeo = new THREE.CapsuleGeometry(0.07, height * 0.32, 4, 6);
  const armL = new THREE.Mesh(armGeo, bodyMat);
  armL.position.set(-0.35, height * 0.48, 0);
  armL.castShadow = true;
  group.add(armL);
  const armR = armL.clone();
  armR.position.x = 0.35;
  group.add(armR);

  // legs
  const legGeo = new THREE.CapsuleGeometry(0.09, height * 0.35, 4, 6);
  const legMat = new THREE.MeshStandardMaterial({ color: 0x2b2d42, roughness: 0.8 });
  const legL = new THREE.Mesh(legGeo, legMat);
  legL.position.set(-0.12, height * 0.18, 0);
  legL.castShadow = true;
  group.add(legL);
  const legR = legL.clone();
  legR.position.x = 0.12;
  group.add(legR);

  return group;
}

function makeLabelSprite(text, color = "#0b2447") {
  const canvasEl = document.createElement("canvas");
  canvasEl.width = 256;
  canvasEl.height = 64;
  const ctx = canvasEl.getContext("2d");
  ctx.fillStyle = "rgba(255,255,255,0.92)";
  roundRect(ctx, 4, 4, 248, 56, 12);
  ctx.fill();
  ctx.strokeStyle = color;
  ctx.lineWidth = 3;
  roundRect(ctx, 4, 4, 248, 56, 12);
  ctx.stroke();
  ctx.fillStyle = color;
  ctx.font = "bold 26px Arial";
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  ctx.fillText(text, 128, 34);

  const texture = new THREE.CanvasTexture(canvasEl);
  const spriteMat = new THREE.SpriteMaterial({ map: texture, depthTest: false });
  const sprite = new THREE.Sprite(spriteMat);
  sprite.scale.set(1.1, 0.28, 1);
  return sprite;
}

function roundRect(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}

// ---------------------------------------------------------------
// HOSPITAL BUILDER
// Builds a simple rectangular hospital with rooms based on layout size.
// Layout is procedurally generated from a room list so it's easy to
// extend for future levels.
// ---------------------------------------------------------------
function buildWall(x, z, w, d, h = 2.6, mat = MAT.wall) {
  const geo = new THREE.BoxGeometry(w, h, d);
  const mesh = new THREE.Mesh(geo, mat);
  mesh.position.set(x, h / 2, z);
  mesh.castShadow = true;
  mesh.receiveShadow = true;
  sceneRoot.add(mesh);

  const box = new THREE.Box3().setFromObject(mesh);
  walls.push(box);
  return mesh;
}

function buildFloorPanel(x, z, w, d, mat = MAT.floor) {
  const geo = new THREE.PlaneGeometry(w, d);
  const mesh = new THREE.Mesh(geo, mat);
  mesh.rotation.x = -Math.PI / 2;
  mesh.position.set(x, 0, z);
  mesh.receiveShadow = true;
  sceneRoot.add(mesh);
}

function buildDoorMarker(x, z, rotationY = 0) {
  const geo = new THREE.BoxGeometry(1.1, 2.0, 0.08);
  const mesh = new THREE.Mesh(geo, MAT.door);
  mesh.position.set(x, 1.0, z);
  mesh.rotation.y = rotationY;
  mesh.castShadow = true;
  sceneRoot.add(mesh);
}

function buildSign(x, z, text, rotationY = 0) {
  const sprite = makeLabelSprite(text, "#e63946");
  sprite.position.set(x, 2.3, z);
  sceneRoot.add(sprite);
}

function buildChair(x, z, rotationY = 0) {
  const group = new THREE.Group();
  const seat = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.08, 0.5), MAT.chair);
  seat.position.y = 0.45;
  seat.castShadow = true;
  group.add(seat);
  const back = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.5, 0.08), MAT.chair);
  back.position.set(0, 0.7, -0.21);
  back.castShadow = true;
  group.add(back);
  const legGeo = new THREE.BoxGeometry(0.06, 0.45, 0.06);
  [[-0.2, -0.2], [0.2, -0.2], [-0.2, 0.2], [0.2, 0.2]].forEach(([lx, lz]) => {
    const leg = new THREE.Mesh(legGeo, MAT.equipment);
    leg.position.set(lx, 0.22, lz);
    group.add(leg);
  });
  group.position.set(x, 0, z);
  group.rotation.y = rotationY;
  sceneRoot.add(group);
}

function buildBed(x, z, rotationY = 0) {
  const group = new THREE.Group();
  const frame = new THREE.Mesh(new THREE.BoxGeometry(1.0, 0.5, 2.0), MAT.bed);
  frame.position.y = 0.25;
  frame.castShadow = true;
  frame.receiveShadow = true;
  group.add(frame);
  const sheet = new THREE.Mesh(new THREE.BoxGeometry(0.9, 0.08, 1.8), MAT.bedSheet);
  sheet.position.y = 0.54;
  group.add(sheet);
  const pillow = new THREE.Mesh(new THREE.BoxGeometry(0.7, 0.1, 0.35), MAT.bed);
  pillow.position.set(0, 0.6, -0.75);
  group.add(pillow);
  group.position.set(x, 0, z);
  group.rotation.y = rotationY;
  sceneRoot.add(group);
}

function buildDesk(x, z, rotationY = 0) {
  const group = new THREE.Group();
  const top = new THREE.Mesh(new THREE.BoxGeometry(1.4, 0.08, 0.7), MAT.desk);
  top.position.y = 0.75;
  top.castShadow = true;
  group.add(top);
  const legGeo = new THREE.BoxGeometry(0.06, 0.75, 0.06);
  [[-0.6, -0.3], [0.6, -0.3], [-0.6, 0.3], [0.6, 0.3]].forEach(([lx, lz]) => {
    const leg = new THREE.Mesh(legGeo, MAT.equipment);
    leg.position.set(lx, 0.375, lz);
    group.add(leg);
  });
  group.position.set(x, 0, z);
  group.rotation.y = rotationY;
  sceneRoot.add(group);
}

function buildEquipmentProp(x, z) {
  const group = new THREE.Group();
  const base = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.22, 0.9, 10), MAT.equipment);
  base.position.y = 0.45;
  base.castShadow = true;
  group.add(base);
  const screen = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.25, 0.06), new THREE.MeshStandardMaterial({ color: 0x111111 }));
  screen.position.y = 1.0;
  group.add(screen);
  group.position.set(x, 0, z);
  sceneRoot.add(group);
}

function buildLighting() {
  const hemi = new THREE.HemisphereLight(0xffffff, 0xb0c4de, 0.65);
  sceneRoot.add(hemi);

  const dir = new THREE.DirectionalLight(0xffffff, 0.9);
  dir.position.set(8, 12, 6);
  dir.castShadow = true;
  dir.shadow.mapSize.set(1024, 1024);
  dir.shadow.camera.left = -20;
  dir.shadow.camera.right = 20;
  dir.shadow.camera.top = 20;
  dir.shadow.camera.bottom = -20;
  sceneRoot.add(dir);

  // soft interior point lights per room feel
  [[-6, -6], [6, -6], [-6, 6], [6, 6], [0, 0]].forEach(([x, z]) => {
    const p = new THREE.PointLight(0xfff4e0, 0.35, 10);
    p.position.set(x, 2.4, z);
    sceneRoot.add(p);
  });
}

// ---------------------------------------------------------------
// LAYOUT DEFINITIONS PER HOSPITAL SIZE
// Rooms arranged around a central corridor. Coordinates in meters.
// ---------------------------------------------------------------
function getLayout(sizeKey) {
  // Base small layout — used for small/medium/large with scaling additions
  const layouts = {
    small: {
      bounds: 16,
      rooms: {
        entrance: { x: 0, z: 7 },
        reception: { x: 0, z: 4 },
        waiting: { x: -5, z: 4 },
        emergency: { x: 5, z: -4 },
        collection: { x: -5, z: -4 },
        corridor: { x: 0, z: 0 },
      },
    },
    medium: {
      bounds: 20,
      rooms: {
        entrance: { x: 0, z: 9 },
        reception: { x: 0, z: 6 },
        waiting: { x: -6, z: 5 },
        emergency: { x: 7, z: -5 },
        collection: { x: -7, z: -5 },
        corridor: { x: 0, z: 0 },
      },
    },
    large: {
      bounds: 24,
      rooms: {
        entrance: { x: 0, z: 11 },
        reception: { x: 0, z: 7 },
        waiting: { x: -8, z: 6 },
        emergency: { x: 9, z: -6 },
        collection: { x: -9, z: -6 },
        corridor: { x: 0, z: 0 },
      },
    },
  };
  return layouts[sizeKey] || layouts.small;
}

function buildHospital(levelData) {
  // clear old scene content
  if (sceneRoot) scene.remove(sceneRoot);
  sceneRoot = new THREE.Group();
  scene.add(sceneRoot);
  walls = [];
  animatedCharacters = [];
  roomMarkers = {};

  const layout = getLayout(levelData.hospitalSize);
  const b = layout.bounds;

  buildLighting();

  // Overall floor
  buildFloorPanel(0, 0, b, b, MAT.floor);

  // Perimeter walls with a gap for the entrance
  const half = b / 2;
  buildWall(-half, 0, 0.3, b, 3, MAT.wallBlue); // left
  buildWall(half, 0, 0.3, b, 3, MAT.wallBlue); // right
  buildWall(0, -half, b, 0.3, 3, MAT.wallBlue); // back
  // front wall split for entrance gap
  buildWall(-b * 0.28, half, b * 0.44, 0.3, 3, MAT.wallBlue);
  buildWall(b * 0.28, half, b * 0.44, 0.3, 3, MAT.wallBlue);

  // Internal dividing walls forming rooms around a central corridor
  const midZ = 1.5;
  buildWall(-half * 0.5, midZ, half, 0.2, 2.6, MAT.wall); // divides waiting from collection area (left)
  buildWall(half * 0.5, midZ, half, 0.2, 2.6, MAT.wall); // divides reception from emergency (right)

  // Room labels / signs
  buildSign(layout.rooms.entrance.x, layout.rooms.entrance.z - 1, "ENTRANCE");
  buildSign(layout.rooms.reception.x, layout.rooms.reception.z + 1.2, "RECEPTION");
  buildSign(layout.rooms.waiting.x, layout.rooms.waiting.z + 1.2, "WAITING AREA");
  buildSign(layout.rooms.emergency.x, layout.rooms.emergency.z + 1.6, "EMERGENCY ROOM");
  buildSign(layout.rooms.collection.x, layout.rooms.collection.z + 1.6, "BLOOD COLLECTION");

  // Reception desk
  buildDesk(layout.rooms.reception.x, layout.rooms.reception.z, 0);

  // Waiting area chairs
  const wa = layout.rooms.waiting;
  buildChair(wa.x - 1, wa.z, 0);
  buildChair(wa.x, wa.z, 0);
  buildChair(wa.x + 1, wa.z, 0);

  // Emergency room: bed + equipment + patient spawn point
  const er = layout.rooms.emergency;
  buildBed(er.x, er.z, Math.PI / 2);
  buildEquipmentProp(er.x + 1.3, er.z - 1);
  buildFloorPanel(er.x, er.z, 6, 6, MAT.floorAccent);

  // Blood collection room: bed/chair + equipment
  const cr = layout.rooms.collection;
  buildChair(cr.x - 1, cr.z, Math.PI / 2);
  buildEquipmentProp(cr.x + 1.2, cr.z);
  buildFloorPanel(cr.x, cr.z, 6, 6, MAT.floorAccent);

  // Corridor direction signs
  buildSign(0, 1.5, "→ EMERGENCY   ← COLLECTION");

  // Obstacles for level 5
  if (levelData.obstacles) {
    buildEquipmentProp(2, 2);
    buildEquipmentProp(-2, -1.5);
    buildChair(3, -1, Math.PI / 4);
  }

  roomMarkers = layout.rooms;

  return layout;
}

// ---------------------------------------------------------------
// CHARACTER SPAWNING
// ---------------------------------------------------------------
let patientCharacter = null;
let donorCharacters = []; // { donorState, mesh, position }
let doctorCharacter, nurseCharacter;

function spawnCharacters(levelData, layout) {
  patientCharacter = null;
  donorCharacters = [];

  // Patient in emergency room
  const er = layout.rooms.emergency;
  const patientMesh = buildCharacter({ bodyColor: 0xbde0fe });
  patientMesh.position.set(er.x - 1.2, 0, er.z + 0.5);
  patientMesh.rotation.y = Math.PI;
  sceneRoot.add(patientMesh);
  const patientLabel = makeLabelSprite(levelData.patient.name);
  patientLabel.position.set(er.x - 1.2, 2.05, er.z + 0.5);
  sceneRoot.add(patientLabel);
  patientCharacter = { position: new THREE.Vector3(er.x - 1.2, 0, er.z + 0.5), mesh: patientMesh };
  animatedCharacters.push({ mesh: patientMesh, baseY: 0, phase: Math.random() * 10 });

  // Doctor near patient
  doctorCharacter = buildCharacter({ bodyColor: 0xffffff });
  doctorCharacter.position.set(er.x + 0.8, 0, er.z + 0.8);
  sceneRoot.add(doctorCharacter);
  animatedCharacters.push({ mesh: doctorCharacter, baseY: 0, phase: Math.random() * 10 });

  // Nurse near collection room
  const cr = layout.rooms.collection;
  nurseCharacter = buildCharacter({ bodyColor: 0xa8dadc });
  nurseCharacter.position.set(cr.x + 0.9, 0, cr.z + 0.9);
  sceneRoot.add(nurseCharacter);
  animatedCharacters.push({ mesh: nurseCharacter, baseY: 0, phase: Math.random() * 10 });

  // Donors spread across waiting area + reception + entrance
  const spots = [
    { x: layout.rooms.waiting.x - 1.5, z: layout.rooms.waiting.z - 1.5 },
    { x: layout.rooms.waiting.x + 1.5, z: layout.rooms.waiting.z - 1.5 },
    { x: layout.rooms.reception.x + 1.5, z: layout.rooms.reception.z + 1.5 },
    { x: layout.rooms.entrance.x - 1.5, z: layout.rooms.entrance.z - 0.5 },
    { x: layout.rooms.entrance.x + 1.5, z: layout.rooms.entrance.z - 0.5 },
    { x: layout.rooms.reception.x - 1.8, z: layout.rooms.reception.z - 1 },
  ];

  mission.donors.forEach((donorState, i) => {
    const spot = spots[i % spots.length];
    const mesh = buildCharacter({ bodyColor: 0x4caf50 });
    mesh.position.set(spot.x, 0, spot.z);
    mesh.rotation.y = Math.random() * Math.PI * 2;
    sceneRoot.add(mesh);

    const label = makeLabelSprite(donorState.name);
    label.position.set(spot.x, 2.05, spot.z);
    sceneRoot.add(label);

    donorState.mesh = mesh;
    donorState.position = new THREE.Vector3(spot.x, 0, spot.z);
    donorCharacters.push({ donorState, mesh, label });
    animatedCharacters.push({ mesh, baseY: 0, phase: Math.random() * 10 });
  });
}

// ---------------------------------------------------------------
// INTERACTABLES REGISTRATION
// ---------------------------------------------------------------
function registerInteractables(layout) {
  interaction.clear();

  // Patient
  interaction.register({
    type: "patient",
    position: patientCharacter.position,
    radius: 2.2,
    data: {},
  });

  // Donors
  donorCharacters.forEach((dc) => {
    interaction.register({
      type: "donor",
      position: dc.donorState.position,
      radius: 2.0,
      data: dc.donorState,
    });
  });

  // Collection room point
  const cr = layout.rooms.collection;
  interaction.register({
    type: "collection",
    position: new THREE.Vector3(cr.x, 0, cr.z),
    radius: 2.6,
    data: {},
  });

  // Emergency delivery point
  const er = layout.rooms.emergency;
  interaction.register({
    type: "delivery",
    position: new THREE.Vector3(er.x, 0, er.z),
    radius: 2.6,
    data: {},
  });
}

// ---------------------------------------------------------------
// MISSION EVENT HANDLING
// ---------------------------------------------------------------
function onMissionEvent(eventName, payload) {
  switch (eventName) {
    case "timerUpdate":
      UI.setTimer(payload);
      break;
    case "scoreUpdate":
      UI.setScore(payload.score);
      break;
    case "xpUpdate":
      UI.setXP(payload.xp);
      break;
    case "objectivesUpdate":
      UI.setObjectives(payload);
      UI.setObjectiveText(mission.getCurrentObjectiveText());
      break;
    case "donorSuitable":
      UI.showDialogue(`<strong>${payload.name}</strong><br>Blood Group: ${payload.bloodGroup}<br><em>Suitable donor found.</em>`);
      updateDonorVisual(payload, true);
      UI.setObjectiveText(mission.getCurrentObjectiveText());
      break;
    case "donorUnsuitable":
      UI.showDialogue(`<strong>${payload.name}</strong><br>Blood Group: ${payload.bloodGroup}<br><em>This donor does not match this mission.</em>`);
      break;
    case "bloodCollected":
      UI.showDialogue(`Blood Collected ✓ (${payload}/${mission.unitsRequired})`);
      UI.setObjectiveText(mission.getCurrentObjectiveText());
      break;
    case "deliverBlocked":
      UI.showDialogue(`Need ${payload.need - payload.have} more unit(s) before delivery.`);
      break;
    case "missionComplete":
      exitPointerLock();
      progress.totalScore += payload.score;
      progress.totalXP += payload.xp;
      if (currentLevelId >= progress.unlockedUpTo && currentLevelId < LEVELS.length) {
        progress.unlockedUpTo = currentLevelId + 1;
      }
      UI.showMissionComplete(payload);
      break;
    case "missionFailed":
      exitPointerLock();
      UI.showMissionFailed(payload.reason);
      break;
  }
}

function updateDonorVisual(donorState, selected) {
  const entry = donorCharacters.find((dc) => dc.donorState.id === donorState.id);
  if (!entry) return;
  entry.mesh.traverse((child) => {
    if (child.isMesh && child.material === MAT.donorShirt) {
      child.material = selected ? MAT.donorShirtSelected : MAT.donorShirt;
    }
  });
  // simplest approach: recolor torso capsule directly
  const torso = entry.mesh.children[0];
  if (torso && torso.material) {
    torso.material = torso.material.clone();
    torso.material.color.set(selected ? 0xffb703 : 0x4caf50);
  }
}

// ---------------------------------------------------------------
// INTERACTION HANDLING
// ---------------------------------------------------------------
function handleInteract(item) {
  switch (item.type) {
    case "patient":
      UI.showDialogue(`<strong>${mission.patient.name}</strong> requires <strong>${mission.patient.bloodGroup}</strong> blood.<br>Units needed: ${mission.patient.units}`);
      break;
    case "donor": {
      const donorState = item.data;
      if (donorState.status === "Collected") {
        UI.showDialogue(`${donorState.name} has already donated.`);
        return;
      }
      mission.interactDonor(donorState);
      break;
    }
    case "collection":
      if (!mission.selectedDonor) {
        UI.showDialogue("Select a suitable donor first.");
        return;
      }
      mission.collectBlood();
      break;
    case "delivery":
      mission.deliverBlood();
      break;
  }
}

function onPromptChange(item) {
  if (!item) {
    UI.hideInteractPrompt();
    return;
  }
  const prompts = {
    patient: "Press E to talk to patient",
    donor: "Press E to interact",
    collection: mission.selectedDonor ? "Press E to collect blood" : "Take a suitable donor here first",
    delivery: "Press E to deliver blood",
  };
  UI.showInteractPrompt(prompts[item.type]);
}

// ---------------------------------------------------------------
// POINTER LOCK / CLICK TO PLAY
// ---------------------------------------------------------------
function exitPointerLock() {
  if (document.pointerLockElement) document.exitPointerLock();
  player.enabled = false;
}

function setupPointerLock() {
  const canvas = document.getElementById("game-canvas");
  const overlay = document.getElementById("click-to-play");

  overlay.addEventListener("click", () => {
    if (mission.state !== "active") return;
    player.requestLock();
  });

  document.addEventListener("pointerlockchange", () => {
    if (document.pointerLockElement === canvas) {
      UI.hideClickToPlay();
      player.enabled = true;
    } else {
      if (mission.state === "active") UI.showClickToPlay();
      player.enabled = false;
    }
  });
}

// ---------------------------------------------------------------
// MISSION LIFECYCLE
// ---------------------------------------------------------------
function startLevel(levelId) {
  currentLevelId = levelId;
  const levelData = getLevel(levelId);

  mission = new Mission(levelId, onMissionEvent);

  showScreen("game-screen");
  UI.setMissionName(levelData.name);
  UI.setTimer(mission.timeRemaining);
  UI.setScore(0);
  UI.setXP(progress.totalXP);
  UI.setObjectives(mission.objectives);
  UI.setObjectiveText("Read the emergency request.");
  UI.hideMissionComplete();
  UI.hideMissionFailed();

  const layout = buildHospital(levelData);
  spawnCharacters(levelData, layout);
  registerInteractables(layout);

  player.setWalls(walls);
  // spawn player near entrance, facing into hospital
  const entrance = layout.rooms.entrance;
  camera.position.set(entrance.x, 1.6, entrance.z - 1);
  camera.rotation.set(0, Math.PI, 0);
  player.euler.set(0, Math.PI, 0);
  camera.quaternion.setFromEuler(player.euler);

  UI.showBriefing(mission.patient, mission.timeRemaining);
}

function beginMissionPlay() {
  UI.hideBriefing();
  mission.begin();
  UI.showClickToPlay();
}

// ---------------------------------------------------------------
// GAME LOOP
// ---------------------------------------------------------------
function animate() {
  requestAnimationFrame(animate);
  const delta = Math.min(clock.getDelta(), 0.1);

  if (player) player.update(delta);
  if (interaction) interaction.update();

  // gentle idle bob for characters
  const t = clock.elapsedTime;
  animatedCharacters.forEach((ac) => {
    ac.mesh.position.y = ac.baseY + Math.sin(t * 2 + ac.phase) * 0.02;
  });

  // minimap
  if (mission && mission.state === "active" && roomMarkers.entrance) {
    const playerPos = player.getFlatPosition();
    const points = [
      { x: roomMarkers.emergency.x, z: roomMarkers.emergency.z, color: "#e63946", size: 6 },
      { x: roomMarkers.collection.x, z: roomMarkers.collection.z, color: "#3f8efc", size: 6 },
    ];
    donorCharacters.forEach((dc) => {
      if (dc.donorState.status !== "Collected") {
        points.push({
          x: dc.donorState.position.x,
          z: dc.donorState.position.z,
          color: "#4caf50",
          size: 3,
        });
      }
    });
    UI.drawMinimap(document.getElementById("minimap"), {
      playerPos,
      playerYaw: player.euler.y,
      points,
      worldSize: getLayout(getLevel(currentLevelId).hospitalSize).bounds + 4,
    });
  }

  renderer.render(scene, camera);
}

// ---------------------------------------------------------------
// MENU WIRING
// ---------------------------------------------------------------
function wireMenus() {
  document.getElementById("btn-start").addEventListener("click", () => {
    startLevel(progress.unlockedUpTo <= LEVELS.length ? Math.min(progress.unlockedUpTo, LEVELS.length) : 1);
  });
  document.getElementById("btn-levels").addEventListener("click", () => {
    UI.buildLevelList(LEVELS, progress.unlockedUpTo, (id) => startLevel(id));
    showScreen("level-menu");
  });
  document.getElementById("btn-howto").addEventListener("click", () => showScreen("howto-menu"));
  document.getElementById("btn-about").addEventListener("click", () => showScreen("about-menu"));

  document.querySelectorAll("[data-back]").forEach((btn) => {
    btn.addEventListener("click", () => showScreen(btn.dataset.back));
  });

  document.getElementById("btn-begin-mission").addEventListener("click", beginMissionPlay);

  document.getElementById("btn-pause").addEventListener("click", () => {
    if (mission.state !== "active") return;
    exitPointerLock();
    UI.showPause();
  });
  document.addEventListener("keydown", (e) => {
    if (e.code === "Escape" && mission && mission.state === "active") {
      exitPointerLock();
      UI.showPause();
    }
  });
  document.getElementById("btn-resume").addEventListener("click", () => {
    UI.hidePause();
    UI.showClickToPlay();
  });
  document.getElementById("btn-restart-mission").addEventListener("click", () => {
    UI.hidePause();
    startLevel(currentLevelId);
  });
  document.getElementById("btn-quit-menu").addEventListener("click", () => {
    UI.hidePause();
    showScreen("main-menu");
  });

  document.getElementById("btn-next-level").addEventListener("click", () => {
    UI.hideMissionComplete();
    const next = Math.min(currentLevelId + 1, LEVELS.length);
    startLevel(next);
  });
  document.getElementById("btn-replay").addEventListener("click", () => {
    UI.hideMissionComplete();
    startLevel(currentLevelId);
  });
  document.getElementById("btn-mc-menu").addEventListener("click", () => {
    UI.hideMissionComplete();
    showScreen("main-menu");
  });

  document.getElementById("btn-retry").addEventListener("click", () => {
    UI.hideMissionFailed();
    startLevel(currentLevelId);
  });
  document.getElementById("btn-mf-menu").addEventListener("click", () => {
    UI.hideMissionFailed();
    showScreen("main-menu");
  });
}

// ---------------------------------------------------------------
// BOOT
// ---------------------------------------------------------------
function init() {
  initRenderer();
  player = new Player(camera, document.getElementById("game-canvas"), []);
  interaction = new InteractionSystem({
    getPlayerPosition: () => player.getFlatPosition(),
    onPromptChange,
    onInteract: handleInteract,
  });
  wireMenus();
  setupPointerLock();
  showScreen("main-menu");
  animate();
}

init();
