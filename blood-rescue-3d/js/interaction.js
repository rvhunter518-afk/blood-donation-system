// interaction.js
// Handles proximity detection to interactables and the "Press E" prompt.
// Interactables are plain objects: { type, position (Vector3), data, radius }

export class InteractionSystem {
  constructor({ getPlayerPosition, onPromptChange, onInteract }) {
    this.getPlayerPosition = getPlayerPosition;
    this.onPromptChange = onPromptChange || (() => {});
    this.onInteract = onInteract || (() => {});

    this.interactables = [];
    this.nearest = null;
    this.enabled = true;

    document.addEventListener("keydown", (e) => {
      if (!this.enabled) return;
      if (e.code === "KeyE" && this.nearest) {
        this.onInteract(this.nearest);
      }
    });
  }

  register(interactable) {
    this.interactables.push(interactable);
  }

  clear() {
    this.interactables = [];
    this.nearest = null;
  }

  update() {
    const playerPos = this.getPlayerPosition();
    let closest = null;
    let closestDist = Infinity;

    for (const item of this.interactables) {
      if (item.disabled) continue;
      const dist = playerPos.distanceTo(item.position);
      const radius = item.radius || 2.2;
      if (dist <= radius && dist < closestDist) {
        closest = item;
        closestDist = dist;
      }
    }

    if (closest !== this.nearest) {
      this.nearest = closest;
      this.onPromptChange(closest);
    }
  }
}
