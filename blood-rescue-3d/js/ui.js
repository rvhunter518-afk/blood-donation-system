// ui.js
// Handles all DOM manipulation: menu screens, HUD updates, dialogue boxes,
// briefing/result overlays, minimap drawing.

export function showScreen(id) {
  document.querySelectorAll(".screen").forEach((el) => el.classList.remove("active"));
  const target = document.getElementById(id);
  if (target) target.classList.add("active");
}

export function formatTime(seconds) {
  const s = Math.max(0, Math.floor(seconds));
  const m = Math.floor(s / 60);
  const rem = s % 60;
  return `${String(m).padStart(2, "0")}:${String(rem).padStart(2, "0")}`;
}

export const UI = {
  setMissionName(name) {
    document.getElementById("hud-mission-name").textContent = name;
  },
  setTimer(seconds) {
    document.getElementById("hud-timer").textContent = formatTime(seconds);
    const el = document.getElementById("hud-timer");
    el.style.color = seconds <= 15 ? "#e63946" : "";
  },
  setScore(score) {
    document.getElementById("hud-score").textContent = `Score: ${score}`;
  },
  setXP(xp) {
    document.getElementById("hud-xp").textContent = `XP: ${xp}`;
  },
  setObjectiveText(text) {
    document.getElementById("current-objective-text").textContent = text;
  },
  setObjectives(objectives) {
    const list = document.getElementById("objective-list");
    list.innerHTML = "";
    objectives.forEach((o) => {
      const li = document.createElement("li");
      li.className = o.done ? "done" : "pending";
      li.textContent = `${o.done ? "✓" : "○"} ${o.label}`;
      list.appendChild(li);
    });
  },
  showInteractPrompt(text) {
    const el = document.getElementById("interact-prompt");
    el.textContent = text || "Press E to interact";
    el.classList.remove("hidden");
  },
  hideInteractPrompt() {
    document.getElementById("interact-prompt").classList.add("hidden");
  },
  showDialogue(html, duration = 2600) {
    const box = document.getElementById("dialogue-box");
    box.innerHTML = html;
    box.classList.remove("hidden");
    clearTimeout(this._dialogueTimeout);
    this._dialogueTimeout = setTimeout(() => {
      box.classList.add("hidden");
    }, duration);
  },
  showBriefing(patient, timeSeconds) {
    document.getElementById("brief-patient").textContent = patient.name;
    document.getElementById("brief-bloodgroup").textContent = patient.bloodGroup;
    document.getElementById("brief-units").textContent = patient.units;
    document.getElementById("brief-level").textContent = patient.emergency;
    document.getElementById("brief-time").textContent = `${timeSeconds}s`;
    document.getElementById("briefing").classList.remove("hidden");
  },
  hideBriefing() {
    document.getElementById("briefing").classList.add("hidden");
  },
  showMissionComplete({ score, xp }) {
    document.getElementById("mc-score").textContent = `+${score}`;
    document.getElementById("mc-xp").textContent = `+${xp}`;
    document.getElementById("mission-complete").classList.remove("hidden");
  },
  hideMissionComplete() {
    document.getElementById("mission-complete").classList.add("hidden");
  },
  showMissionFailed(reason) {
    document.getElementById("fail-reason").textContent = reason || "Time expired.";
    document.getElementById("mission-failed").classList.remove("hidden");
  },
  hideMissionFailed() {
    document.getElementById("mission-failed").classList.add("hidden");
  },
  showClickToPlay() {
    document.getElementById("click-to-play").classList.remove("hidden");
  },
  hideClickToPlay() {
    document.getElementById("click-to-play").classList.add("hidden");
  },
  showPause() {
    document.getElementById("pause-menu").classList.remove("hidden");
  },
  hidePause() {
    document.getElementById("pause-menu").classList.add("hidden");
  },
  drawMinimap(canvas, { playerPos, playerYaw, points, worldSize }) {
    const ctx = canvas.getContext("2d");
    const w = canvas.width;
    const h = canvas.height;
    ctx.clearRect(0, 0, w, h);
    ctx.fillStyle = "#0e2a52";
    ctx.fillRect(0, 0, w, h);

    const scale = w / worldSize;
    const toMap = (x, z) => ({
      x: w / 2 + x * scale,
      y: h / 2 + z * scale,
    });

    // draw points (rooms/donors)
    points.forEach((p) => {
      const pos = toMap(p.x, p.z);
      ctx.fillStyle = p.color || "#3f8efc";
      ctx.beginPath();
      ctx.arc(pos.x, pos.y, p.size || 4, 0, Math.PI * 2);
      ctx.fill();
    });

    // draw player
    const pp = toMap(playerPos.x, playerPos.z);
    ctx.save();
    ctx.translate(pp.x, pp.y);
    ctx.rotate(playerYaw);
    ctx.fillStyle = "#ffffff";
    ctx.beginPath();
    ctx.moveTo(0, -6);
    ctx.lineTo(4, 5);
    ctx.lineTo(-4, 5);
    ctx.closePath();
    ctx.fill();
    ctx.restore();
  },
  buildLevelList(levels, unlockedUpTo, onSelect) {
    const container = document.getElementById("level-list");
    container.innerHTML = "";
    levels.forEach((lvl) => {
      const locked = lvl.id > unlockedUpTo;
      const btn = document.createElement("div");
      btn.className = "level-btn" + (locked ? " locked" : "");
      btn.innerHTML = `
        <div>
          <div class="lv-name">${lvl.name}</div>
          <div class="lv-sub">${lvl.purpose}</div>
        </div>
        <div class="lv-status">${locked ? "🔒" : "▶"}</div>
      `;
      if (!locked) {
        btn.addEventListener("click", () => onSelect(lvl.id));
      }
      container.appendChild(btn);
    });
  },
};
