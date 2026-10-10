// mission.js
// Tracks the state of a single mission: timer, objectives, collected
// units, score/XP awarded. Independent of rendering.

import { getLevel } from "./levels.js";
import { DonorState, isDonorSuitable } from "./donor.js";

export const OBJECTIVE_STEPS = [
  { key: "read", label: "Read emergency request" },
  { key: "findDonor", label: "Find suitable donor" },
  { key: "collect", label: "Collect blood" },
  { key: "deliver", label: "Deliver blood" },
];

export class Mission {
  constructor(levelId, onEvent) {
    this.levelData = getLevel(levelId);
    this.onEvent = onEvent || (() => {}); // callback(eventName, payload)

    this.patient = { ...this.levelData.patient };
    this.donors = this.levelData.donors.map((d) => new DonorState(d));
    this.timeRemaining = this.levelData.time;
    this.unitsCollected = 0;
    this.unitsRequired = this.patient.units;

    this.objectives = OBJECTIVE_STEPS.map((o) => ({ ...o, done: false }));
    this.objectives[0].done = true; // "read emergency request" done at briefing

    this.state = "briefing"; // briefing | active | success | failed
    this.selectedDonor = null;
    this._timerHandle = null;

    this.score = 0;
    this.xp = 0;
    this.startTimestamp = null;
  }

  begin() {
    this.state = "active";
    this.startTimestamp = performance.now();
    this._tick();
  }

  _tick() {
    if (this.state !== "active") return;
    this._timerHandle = setInterval(() => {
      if (this.state !== "active") return;
      this.timeRemaining -= 1;
      this.onEvent("timerUpdate", this.timeRemaining);
      if (this.timeRemaining <= 0) {
        this.timeRemaining = 0;
        this.fail("Time ran out.");
      }
    }, 1000);
  }

  stopTimer() {
    if (this._timerHandle) {
      clearInterval(this._timerHandle);
      this._timerHandle = null;
    }
  }

  // Called when player interacts with a donor
  interactDonor(donor) {
    const suitable = isDonorSuitable(donor, this.patient);
    if (suitable) {
      donor.select();
      this.selectedDonor = donor;
      this._markObjective("findDonor");
      this.onEvent("donorSuitable", donor);
      this.addScore(25, "Correct donor selection");
    } else {
      this.onEvent("donorUnsuitable", donor);
      this.addScore(-10, "Wrong donor");
    }
    return suitable;
  }

  // Called when player collects blood at the collection room,
  // provided a suitable donor has been selected and escorted.
  collectBlood() {
    if (!this.selectedDonor) return false;
    this.selectedDonor.markCollected();
    this.unitsCollected += 1;
    this.onEvent("bloodCollected", this.unitsCollected);

    if (this.unitsCollected >= this.unitsRequired) {
      this._markObjective("collect");
    }
    // Reset selection so player can find another donor if more units needed
    this.selectedDonor = null;
    return true;
  }

  // Called when player delivers blood at the emergency room
  deliverBlood() {
    if (this.unitsCollected < this.unitsRequired) {
      this.onEvent("deliverBlocked", {
        have: this.unitsCollected,
        need: this.unitsRequired,
      });
      return false;
    }
    this._markObjective("deliver");
    this.succeed();
    return true;
  }

  _markObjective(key) {
    const obj = this.objectives.find((o) => o.key === key);
    if (obj) obj.done = true;
    this.onEvent("objectivesUpdate", this.objectives);
  }

  addScore(points, reason) {
    this.score += points;
    this.onEvent("scoreUpdate", { score: this.score, delta: points, reason });
  }

  addXP(points) {
    this.xp += points;
    this.onEvent("xpUpdate", { xp: this.xp, delta: points });
  }

  succeed() {
    if (this.state !== "active") return;
    this.state = "success";
    this.stopTimer();

    this.addScore(100, "Mission completion");
    const elapsed = (performance.now() - this.startTimestamp) / 1000;
    let fastBonus = 0;
    if (elapsed < this.levelData.time * 0.6) {
      fastBonus = 50;
      this.addScore(50, "Fast completion");
    }
    this.addXP(100);

    this.onEvent("missionComplete", {
      score: this.score,
      xp: this.xp,
      fastBonus,
    });
  }

  fail(reason) {
    if (this.state !== "active") return;
    this.state = "failed";
    this.stopTimer();
    this.onEvent("missionFailed", { reason });
  }

  getCurrentObjectiveText() {
    const next = this.objectives.find((o) => !o.done);
    if (!next) return "Mission complete";
    switch (next.key) {
      case "findDonor":
        return `Find a ${this.patient.bloodGroup} donor.`;
      case "collect":
        return "Take donor to Blood Collection Room.";
      case "deliver":
        return "Deliver blood to Emergency Room.";
      default:
        return next.label;
    }
  }
}
