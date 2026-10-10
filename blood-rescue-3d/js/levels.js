// levels.js
// Modular level definitions. Each level defines patient requirement,
// available donors, time limit, and hospital layout size.
// Matching logic lives in donor.js so it can be swapped independently.

export const LEVELS = [
  {
    id: 1,
    name: "LEVEL 1 — FIRST RESPONSE",
    purpose: "Teach the basic gameplay.",
    patient: { name: "Arjun", bloodGroup: "O+", units: 1, emergency: "High" },
    time: 120,
    donors: [
      { id: "d1", name: "Kavin", bloodGroup: "O+" },
    ],
    hospitalSize: "small",
  },
  {
    id: 2,
    name: "LEVEL 2 — FIND THE DONOR",
    purpose: "Teach donor identification.",
    patient: { name: "Meena", bloodGroup: "A+", units: 1, emergency: "Medium" },
    time: 120,
    donors: [
      { id: "d1", name: "Kavin", bloodGroup: "O+" },
      { id: "d2", name: "Rahul", bloodGroup: "A+" },
      { id: "d3", name: "Sanjay", bloodGroup: "B+" },
    ],
    hospitalSize: "small",
  },
  {
    id: 3,
    name: "LEVEL 3 — EMERGENCY",
    purpose: "Introduce time pressure.",
    patient: { name: "Divya", bloodGroup: "B+", units: 1, emergency: "High" },
    time: 90,
    donors: [
      { id: "d1", name: "Kavin", bloodGroup: "O+" },
      { id: "d2", name: "Rahul", bloodGroup: "A+" },
      { id: "d3", name: "Sanjay", bloodGroup: "B+" },
      { id: "d4", name: "Vijay", bloodGroup: "O-" },
    ],
    hospitalSize: "small",
  },
  {
    id: 4,
    name: "LEVEL 4 — MULTIPLE UNITS",
    purpose: "Introduce multi-donor coordination.",
    patient: { name: "Karthik", bloodGroup: "O+", units: 2, emergency: "High" },
    time: 150,
    donors: [
      { id: "d1", name: "Kavin", bloodGroup: "O+" },
      { id: "d2", name: "Rahul", bloodGroup: "A+" },
      { id: "d3", name: "Sanjay", bloodGroup: "B+" },
      { id: "d4", name: "Vijay", bloodGroup: "O-" },
      { id: "d5", name: "Priya", bloodGroup: "O+" },
    ],
    hospitalSize: "medium",
  },
  {
    id: 5,
    name: "LEVEL 5 — FINAL RESCUE",
    purpose: "Final mini-game challenge.",
    patient: { name: "Anitha", bloodGroup: "A+", units: 1, emergency: "High" },
    time: 120,
    donors: [
      { id: "d1", name: "Kavin", bloodGroup: "O+" },
      { id: "d2", name: "Rahul", bloodGroup: "A+" },
      { id: "d3", name: "Sanjay", bloodGroup: "B+" },
      { id: "d4", name: "Vijay", bloodGroup: "O-" },
      { id: "d5", name: "Priya", bloodGroup: "O+" },
      { id: "d6", name: "Suresh", bloodGroup: "AB+" },
    ],
    hospitalSize: "large",
    obstacles: true,
  },
];

export function getLevel(levelId) {
  return LEVELS.find((l) => l.id === levelId) || LEVELS[0];
}
