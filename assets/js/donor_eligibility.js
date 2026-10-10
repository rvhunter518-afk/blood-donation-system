document.addEventListener('DOMContentLoaded', function() {
    // 1. DOB and Age Calculation
    const dobInput = document.getElementById('dateOfBirth');
    const ageDisplay = document.getElementById('calculatedAgeDisplay');
    const ageHidden = document.getElementById('calculatedAge');

    if (dobInput) {
        dobInput.addEventListener('change', function() {
            if (!this.value) {
                if (ageDisplay) ageDisplay.textContent = '—';
                if (ageHidden) ageHidden.value = '';
                return;
            }
            const birthDate = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            if (ageDisplay) {
                ageDisplay.textContent = age > 0 ? `${age} years old` : 'Invalid date';
                if (age < 18 || age > 65) {
                    ageDisplay.className = 'badge bg-warning text-dark ms-2';
                } else {
                    ageDisplay.className = 'badge bg-success ms-2';
                }
            }
            if (ageHidden) ageHidden.value = age > 0 ? age : '';
        });
    }

    // 2. Emergency Availability & Selected Days
    const emergSelect = document.getElementById('emergencyAvailability');
    const daysWrap = document.getElementById('selectedDaysWrap');
    const selectedDaysHidden = document.getElementById('selectedDaysHidden');

    if (emergSelect && daysWrap) {
        emergSelect.addEventListener('change', function() {
            if (this.value === 'Available only on selected days') {
                daysWrap.style.display = 'flex';
            } else {
                daysWrap.style.display = 'none';
            }
        });
    }

    // Day chips toggle
    const dayChips = document.querySelectorAll('.day-chip');
    dayChips.forEach(chip => {
        chip.addEventListener('click', function() {
            this.classList.toggle('active');
            updateSelectedDays();
        });
    });

    function updateSelectedDays() {
        const activeDays = Array.from(document.querySelectorAll('.day-chip.active'))
            .map(c => c.getAttribute('data-day'));
        if (selectedDaysHidden) {
            selectedDaysHidden.value = activeDays.join(',');
        }
    }

    // 3. Question Buttons YES / NO
    const questionRows = document.querySelectorAll('.question-row');
    questionRows.forEach(row => {
        const qName = row.getAttribute('data-question');
        const hiddenInput = document.getElementById('input_' + qName);
        const btnYes = row.querySelector('.btn-yes');
        const btnNo = row.querySelector('.btn-no');

        if (btnYes && hiddenInput) {
            btnYes.addEventListener('click', function(e) {
                e.preventDefault();
                hiddenInput.value = 'yes';
                btnYes.classList.add('active-yes');
                btnNo.classList.remove('active-no');
                row.classList.remove('unanswered');
            });
        }

        if (btnNo && hiddenInput) {
            btnNo.addEventListener('click', function(e) {
                e.preventDefault();
                hiddenInput.value = 'no';
                btnNo.classList.add('active-no');
                btnYes.classList.remove('active-yes');
                row.classList.remove('unanswered');
            });
        }
    });

    // 4. Stepper Navigation: Phase 1 -> Phase 2
    const btnGoToPhase2 = document.getElementById('btnGoToPhase2');
    const btnBackToPhase1 = document.getElementById('btnBackToPhase1');
    const phaseCard1 = document.getElementById('phaseCard1');
    const phaseCard2 = document.getElementById('phaseCard2');
    const stepNode1 = document.getElementById('stepNode1');
    const stepNode2 = document.getElementById('stepNode2');

    if (btnGoToPhase2 && phaseCard1 && phaseCard2) {
        btnGoToPhase2.addEventListener('click', function() {
            // Validate Phase 1 required fields
            const requiredFields = ['dateOfBirth', 'gender', 'city', 'area', 'bloodGroup', 'preferredLanguage'];
            for (let id of requiredFields) {
                const el = document.getElementById(id);
                if (el && !el.value.trim()) {
                    el.focus();
                    alert('Please complete all required fields in Phase 1 before proceeding to the health questions.');
                    return;
                }
            }

            // Check age
            const ageVal = parseInt(document.getElementById('calculatedAge').value) || 0;
            if (ageVal > 0 && (ageVal < 18 || ageVal > 65)) {
                if (!confirm(`Calculated age is ${ageVal} years. Permissible donation age in India is typically 18 to 65 years. Do you still wish to continue with preliminary screening?`)) {
                    return;
                }
            }

            // Transition to Phase 2
            phaseCard1.style.display = 'none';
            phaseCard2.style.display = 'block';
            if (stepNode1) {
                stepNode1.classList.remove('active');
                stepNode1.classList.add('completed');
            }
            if (stepNode2) {
                stepNode2.classList.add('active');
            }
            phaseCard2.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    if (btnBackToPhase1 && phaseCard1 && phaseCard2) {
        btnBackToPhase1.addEventListener('click', function() {
            phaseCard2.style.display = 'none';
            phaseCard1.style.display = 'block';
            if (stepNode2) stepNode2.classList.remove('active');
            if (stepNode1) {
                stepNode1.classList.remove('completed');
                stepNode1.classList.add('active');
            }
            phaseCard1.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    // 5. Phase 2 Submission Validation
    const form = document.getElementById('eligibilityScreeningForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            let firstUnanswered = null;
            let hasError = false;

            const questionInputs = form.querySelectorAll('input[type="hidden"][name^="q_"]');
            questionInputs.forEach(input => {
                if (!input.value) {
                    hasError = true;
                    const row = document.querySelector(`.question-row[data-question="${input.name.replace('q_', '')}"]`);
                    if (row) {
                        row.classList.add('unanswered');
                        if (!firstUnanswered) firstUnanswered = row;
                    }
                }
            });

            if (hasError && firstUnanswered) {
                e.preventDefault();
                firstUnanswered.scrollIntoView({ behavior: 'smooth', block: 'center' });
                alert('Please answer all 14 preliminary eligibility questions with YES or NO before submitting.');
            }
        });
    }
});