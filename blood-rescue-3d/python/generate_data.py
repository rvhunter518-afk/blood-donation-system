"""
generate_data.py

Development/support script for Blood Rescue 3D.

Generates fictional patient and donor data as JSON, useful for
prototyping new levels before hand-writing them into js/levels.js.
This script is NOT required to run the game — the game is fully
client-side and reads its level data directly from js/levels.js.

Usage:
    python generate_data.py

Output:
    fictional_data.json  (in this same folder)
"""

import json
import random

FICTIONAL_NAMES = [
    "Arjun", "Kavin", "Rahul", "Sanjay", "Vijay", "Priya", "Suresh",
    "Meena", "Divya", "Karthik", "Anitha", "Deepak", "Kiran", "Latha",
]

BLOOD_GROUPS = ["O+", "O-", "A+", "A-", "B+", "B-", "AB+", "AB-"]

EMERGENCY_LEVELS = ["Low", "Medium", "High"]


def generate_patient():
    """Generate one fictional patient emergency case."""
    return {
        "name": random.choice(FICTIONAL_NAMES),
        "bloodGroup": random.choice(BLOOD_GROUPS),
        "units": random.choice([1, 1, 1, 2]),  # mostly 1 unit
        "emergency": random.choice(EMERGENCY_LEVELS),
    }


def generate_donors(count=4):
    """Generate a list of fictional donors with unique names."""
    names = random.sample(FICTIONAL_NAMES, k=min(count, len(FICTIONAL_NAMES)))
    donors = []
    for i, name in enumerate(names):
        donors.append({
            "id": f"d{i + 1}",
            "name": name,
            "bloodGroup": random.choice(BLOOD_GROUPS),
            "status": "Available",
        })
    return donors


def generate_level(level_id, time_seconds, donor_count):
    patient = generate_patient()
    donors = generate_donors(donor_count)

    # Guarantee at least one suitable donor exists for playability
    if not any(d["bloodGroup"] == patient["bloodGroup"] for d in donors):
        donors[0]["bloodGroup"] = patient["bloodGroup"]

    return {
        "id": level_id,
        "patient": patient,
        "donors": donors,
        "time": time_seconds,
    }


def main():
    random.seed()  # non-deterministic; remove/set a seed for reproducibility

    levels = [
        generate_level(1, 120, 1),
        generate_level(2, 120, 3),
        generate_level(3, 90, 4),
        generate_level(4, 150, 5),
        generate_level(5, 120, 6),
    ]

    output = {"levels": levels}

    with open("fictional_data.json", "w", encoding="utf-8") as f:
        json.dump(output, f, indent=2)

    print(f"Generated {len(levels)} fictional levels -> fictional_data.json")
    print("Note: this file is for prototyping only. The live game reads")
    print("level data from ../js/levels.js, not from this JSON output.")


if __name__ == "__main__":
    main()
