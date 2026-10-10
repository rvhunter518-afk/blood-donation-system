// donor.js
// Fictional blood-group matching logic. Kept modular/isolated so the
// matching rules can be replaced later without touching game logic.
//
// Current rule (simplified for gameplay): exact blood-group match only.
// This is intentionally simple — NOT real medical compatibility rules.

export function isDonorSuitable(donor, patient) {
  return donor.bloodGroup === patient.bloodGroup;
}

export function findSuitableDonors(donors, patient) {
  return donors.filter((d) => isDonorSuitable(d, patient));
}

// Runtime donor state wrapper used during a mission
export class DonorState {
  constructor(donorData) {
    this.id = donorData.id;
    this.name = donorData.name;
    this.bloodGroup = donorData.bloodGroup;
    this.status = "Available"; // Available | Selected | Collected
    this.mesh = null; // set by game.js when spawned
    this.position = null; // THREE.Vector3, set by game.js
  }

  select() {
    this.status = "Selected";
  }

  markCollected() {
    this.status = "Collected";
  }
}
