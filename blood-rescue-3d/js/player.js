// player.js
// First-person player controller: WASD movement + pointer-lock mouse look.
// Simple AABB-based collision against wall boxes for basic bounding.

import * as THREE from "three";

export class Player {
  constructor(camera, domElement, walls) {
    this.camera = camera;
    this.domElement = domElement;
    this.walls = walls || []; // array of THREE.Box3

    this.velocity = new THREE.Vector3();
    this.direction = new THREE.Vector3();

    this.moveForward = false;
    this.moveBackward = false;
    this.moveLeft = false;
    this.moveRight = false;

    this.speed = 4.2; // units per second
    this.radius = 0.4; // player collision radius

    this.euler = new THREE.Euler(0, 0, 0, "YXZ");
    this.pitchMin = -Math.PI / 2 + 0.05;
    this.pitchMax = Math.PI / 2 - 0.05;

    this.enabled = false;
    this.locked = false;

    this._bindEvents();
  }

  _bindEvents() {
    document.addEventListener("keydown", (e) => this._onKey(e, true));
    document.addEventListener("keyup", (e) => this._onKey(e, false));

    document.addEventListener("pointerlockchange", () => {
      this.locked = document.pointerLockElement === this.domElement;
    });

    this.domElement.addEventListener("mousemove", (e) => {
      if (!this.locked || !this.enabled) return;
      const sensitivity = 0.0022;
      this.euler.setFromQuaternion(this.camera.quaternion);
      this.euler.y -= e.movementX * sensitivity;
      this.euler.x -= e.movementY * sensitivity;
      this.euler.x = Math.max(this.pitchMin, Math.min(this.pitchMax, this.euler.x));
      this.camera.quaternion.setFromEuler(this.euler);
    });
  }

  requestLock() {
    this.domElement.requestPointerLock();
  }

  _onKey(e, isDown) {
    if (!this.enabled) return;
    switch (e.code) {
      case "KeyW":
      case "ArrowUp":
        this.moveForward = isDown;
        break;
      case "KeyS":
      case "ArrowDown":
        this.moveBackward = isDown;
        break;
      case "KeyA":
      case "ArrowLeft":
        this.moveLeft = isDown;
        break;
      case "KeyD":
      case "ArrowRight":
        this.moveRight = isDown;
        break;
    }
  }

  setWalls(walls) {
    this.walls = walls;
  }

  _willCollide(nextPos) {
    const r = this.radius;
    const playerBox = new THREE.Box3(
      new THREE.Vector3(nextPos.x - r, 0, nextPos.z - r),
      new THREE.Vector3(nextPos.x + r, 2, nextPos.z + r)
    );
    for (const wallBox of this.walls) {
      if (playerBox.intersectsBox(wallBox)) return true;
    }
    return false;
  }

  update(delta) {
    if (!this.enabled) return;

    const damping = 10.0;
    this.velocity.x -= this.velocity.x * damping * delta;
    this.velocity.z -= this.velocity.z * damping * delta;

    this.direction.z = Number(this.moveForward) - Number(this.moveBackward);
    this.direction.x = Number(this.moveRight) - Number(this.moveLeft);
    this.direction.normalize();

    if (this.moveForward || this.moveBackward) {
      this.velocity.z -= this.direction.z * this.speed * 10 * delta;
    }
    if (this.moveLeft || this.moveRight) {
      this.velocity.x -= this.direction.x * this.speed * 10 * delta;
    }

    // Move relative to camera yaw only (flat plane)
    const yaw = this.euler.y;
    const forward = new THREE.Vector3(-Math.sin(yaw), 0, -Math.cos(yaw));
    const right = new THREE.Vector3(Math.cos(yaw), 0, -Math.sin(yaw));

    const moveX = -this.velocity.x * delta;
    const moveZ = -this.velocity.z * delta;

    const displacement = new THREE.Vector3()
      .addScaledVector(forward, moveZ)
      .addScaledVector(right, -moveX);

    const currentPos = this.camera.position.clone();

    // Try full move, then axis-separated to slide along walls
    const tryX = currentPos.clone();
    tryX.x += displacement.x;
    if (!this._willCollide(tryX)) currentPos.x = tryX.x;

    const tryZ = currentPos.clone();
    tryZ.z += displacement.z;
    if (!this._willCollide(tryZ)) currentPos.z = tryZ.z;

    this.camera.position.x = currentPos.x;
    this.camera.position.z = currentPos.z;
    this.camera.position.y = 1.6; // eye height, fixed (no jumping needed)
  }

  getFlatPosition() {
    return new THREE.Vector3(this.camera.position.x, 0, this.camera.position.z);
  }
}
