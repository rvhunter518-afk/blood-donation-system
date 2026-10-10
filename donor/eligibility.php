<?php
session_start();

// Strict Authentication: Real donor info only
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'donor') {
    header('Location: ../login.html?redirect=donor/eligibility.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Donor';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Connect DB safely
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli('localhost', 'root', '', 'blood_connect');

if ($conn->connect_error) {
    die("Database connection failed. Please ensure MySQL is running.");
}
$conn->set_charset('utf8mb4');

// 1. Retrieve REAL logged-in donor user details from database
$uStmt = $conn->prepare("SELECT full_name, email, phone, blood_type, city, area FROM users WHERE id = ?");
$uStmt->bind_param("i", $userId);
$uStmt->execute();
$userData = $uStmt->get_result()->fetch_assoc() ?? [];
$uStmt->close();

// 2. Retrieve existing donor record if exists
$dStmt = $conn->prepare("SELECT * FROM donors WHERE user_id = ?");
$dStmt->bind_param("i", $userId);
$dStmt->execute();
$donorData = $dStmt->get_result()->fetch_assoc() ?? [];
$dStmt->close();

$submittedResult = null;
$errorMessage = '';

// Handle Form Submission (Phase 2 -> Phase 3)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $dob = trim($_POST['date_of_birth'] ?? '');
    $age = (int)($_POST['calculated_age'] ?? 0);
    $gender = trim($_POST['gender'] ?? 'prefer_not_to_say');
    $city = trim($_POST['city'] ?? ($userData['city'] ?? ''));
    $area = trim($_POST['area'] ?? ($userData['area'] ?? ''));
    $preferredLang = trim($_POST['preferred_language'] ?? 'English');
    $bloodGroup = trim($_POST['blood_group'] ?? ($userData['blood_type'] ?? 'O+'));
    $emergencyAvail = trim($_POST['emergency_availability'] ?? 'Available');
    $selectedDays = trim($_POST['selected_available_days'] ?? '');
    $consentNotifications = isset($_POST['notification_consent']) && $_POST['notification_consent'] === 'yes' ? 1 : 0;
    $additionalInfo = trim($_POST['additional_information'] ?? '');

    // 14 Questions
    $qHealthy = $_POST['q_healthy_today'] ?? '';
    $qAge18 = $_POST['q_age_18_plus'] ?? '';
    $qWeight45 = $_POST['q_weight_45_plus'] ?? '';
    $qRecentDonation = $_POST['q_recent_donation'] ?? '';
    $qRecentIllness = $_POST['q_recent_illness'] ?? '';
    $qTakingMeds = $_POST['q_taking_medicines'] ?? '';
    $qProcedure = $_POST['q_recent_procedure'] ?? '';
    $qVaccine = $_POST['q_recent_vaccination'] ?? '';
    $qInfection = $_POST['q_blood_related_infection'] ?? '';
    $qPregnancy = $_POST['q_pregnancy_status'] ?? 'no';
    $qAlcoholDrugs = $_POST['q_recent_alcohol_drug_use'] ?? '';
    $qFeelingUnwell = $_POST['q_feeling_unwell'] ?? '';
    $qReaction = $_POST['q_previous_donation_reaction'] ?? '';
    $qCondition = $_POST['q_medical_condition'] ?? '';

    // Validate presence of required answers
    $allAnswered = ($qHealthy && $qAge18 && $qWeight45 && $qRecentDonation && $qRecentIllness && 
                    $qTakingMeds && $qProcedure && $qVaccine && $qInfection && $qAlcoholDrugs && 
                    $qFeelingUnwell && $qReaction && $qCondition && $dob && $bloodGroup);

    if (!$allAnswered) {
        $errorMessage = "Please complete all personal details and answer every question in Phase 2 before proceeding.";
    } else {
        // Evaluate Preliminary Result
        $resultStatus = 'green';
        $resultMessage = 'Based on your answers, you may be eligible to donate. The blood-bank team must complete the final screening.';

        // Critical deferrals (RED)
        if ($qHealthy === 'no' || $qAge18 === 'no' || $qWeight45 === 'no' || 
            $qInfection === 'yes' || $qAlcoholDrugs === 'yes' || $qFeelingUnwell === 'yes' || 
            $age < 18 || $age > 65) {
            $resultStatus = 'red';
            $resultMessage = 'You may currently be deferred from donating blood based on standard clinical guidelines. A final determination will always be made in person by qualified blood-bank staff.';
        } 
        // Secondary deferrals/reviews (YELLOW)
        elseif ($qTakingMeds === 'yes' || $qProcedure === 'yes' || $qVaccine === 'yes' || 
                $qRecentIllness === 'yes' || $qRecentDonation === 'yes' || $qPregnancy === 'yes' || 
                $qCondition === 'yes') {
            $resultStatus = 'yellow';
            $resultMessage = 'Please discuss your answers with blood-bank staff before donating. Certain medications, recent procedures, or vaccinations require a temporary waiting period.';
        }

        $weightKg = ($qWeight45 === 'yes') ? 55.0 : 42.0;

        // Upsert into donors table
        if (!empty($donorData['id'])) {
            $upStmt = $conn->prepare("UPDATE donors SET 
                city = ?, area = ?, preferred_language = ?, emergency_availability = ?, 
                selected_available_days = ?, notification_consent = ?, preliminary_result = ?, 
                date_of_birth = ?, age_years = ?, gender = ?, weight_kg = ?, screening_notes = ?, 
                blood_type = ? WHERE user_id = ?");
            if ($upStmt) {
                $upStmt->bind_param("sssssississssi", $city, $area, $preferredLang, $emergencyAvail, 
                    $selectedDays, $consentNotifications, $resultStatus, $dob, $age, $gender, 
                    $weightKg, $additionalInfo, $bloodGroup, $userId);
                $upStmt->execute();
                $donorId = (int)$donorData['id'];
                $upStmt->close();
            }
        } else {
            $donorCode = 'DON-' . strtoupper(bin2hex(random_bytes(3)));
            $inStmt = $conn->prepare("INSERT INTO donors (user_id, donor_code, blood_type, city, area, 
                phone, preferred_language, emergency_availability, selected_available_days, 
                notification_consent, preliminary_result, date_of_birth, age_years, gender, 
                weight_kg, screening_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($inStmt) {
                $userPhone = $userData['phone'] ?? '';
                $inStmt->bind_param("issssssssissisds", $userId, $donorCode, $bloodGroup, $city, $area, 
                    $userPhone, $preferredLang, $emergencyAvail, $selectedDays, $consentNotifications, 
                    $resultStatus, $dob, $age, $gender, $weightKg, $additionalInfo);
                $inStmt->execute();
                $donorId = (int)$conn->insert_id;
                $inStmt->close();
            }
        }

        // Save into donor_screenings
        $answersJson = json_encode($_POST);
        $scrStmt = $conn->prepare("INSERT INTO donor_screenings (
            user_id, donor_id, full_name, mobile, date_of_birth, age, gender, city, area, 
            preferred_language, blood_group, emergency_availability, selected_available_days, 
            notification_consent, weight_kg, preliminary_result, result_status, result_message, 
            healthy_today, age_18_plus, weight_45_plus, recent_donation, recent_illness, 
            taking_medicines, recent_procedure, recent_vaccination, blood_related_infection, 
            pregnancy_status, recent_alcohol_drug_use, feeling_unwell, previous_donation_reaction, 
            medical_condition, additional_information, answers_json
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )");
        if ($scrStmt) {
            $uMobile = $userData['phone'] ?? '';
            $uFullName = $userData['full_name'] ?? $userName;
            $scrStmt->bind_param("iisssissssssssdsssssssssssssssssss", 
                $userId, $donorId, $uFullName, $uMobile, $dob, $age, $gender, $city, $area, 
                $preferredLang, $bloodGroup, $emergencyAvail, $selectedDays, $consentNotifications, 
                $weightKg, $resultStatus, $resultStatus, $resultMessage, 
                $qHealthy, $qAge18, $qWeight45, $qRecentDonation, $qRecentIllness, 
                $qTakingMeds, $qProcedure, $qVaccine, $qInfection, $qPregnancy, 
                $qAlcoholDrugs, $qFeelingUnwell, $qReaction, $qCondition, $additionalInfo, $answersJson
            );
            $scrStmt->execute();
            $scrStmt->close();
        }

        // Save into donor_eligibility_answers
        $eaStmt = $conn->prepare("INSERT INTO donor_eligibility_answers (
            user_id, donor_id, healthy_today, age_18_plus, weight_45_plus, recent_donation, 
            recent_illness, taking_medicines, recent_procedure, recent_vaccination, 
            blood_related_infection, pregnancy_status, recent_alcohol_drug_use, 
            feeling_unwell, previous_donation_reaction, medical_condition, 
            additional_information, preliminary_result, screening_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        if ($eaStmt) {
            $eaStmt->bind_param("iissssssssssssssss", 
                $userId, $donorId, $qHealthy, $qAge18, $qWeight45, $qRecentDonation, 
                $qRecentIllness, $qTakingMeds, $qProcedure, $qVaccine, 
                $qInfection, $qPregnancy, $qAlcoholDrugs, 
                $qFeelingUnwell, $qReaction, $qCondition, 
                $additionalInfo, $resultStatus
            );
            $eaStmt->execute();
            $eaStmt->close();
        }

        $submittedResult = [
            'status' => $resultStatus,
            'message' => $resultMessage,
            'dob' => $dob,
            'age' => $age,
            'blood_group' => $bloodGroup
        ];
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Registration & Eligibility Screening | LifeFlow</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Light Red Theme CSS -->
    <link rel="stylesheet" href="../assets/css/donor_eligibility.css">
</head>
<body class="screening-page">

    <div class="screening-wrapper">
        <!-- Navigation bar -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <a href="index.php" style="display:inline-flex;align-items:center;gap:8px;padding:8px 18px;border-radius:50px;background:#ffffff;border:1px solid #ecdfda;color:#171b22;font-weight:700;text-decoration:none;font-size:0.88rem;">
                <i class="fas fa-arrow-left"></i> Donor Dashboard
            </a>
            <div style="display:flex;align-items:center;gap:12px;">
                <a href="../medical_news.php" style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:50px;background:#fff8f3;border:1px solid #ecdfda;color:#d62839;font-weight:700;text-decoration:none;font-size:0.85rem;">
                    <i class="fas fa-newspaper"></i> Live Medical News
                </a>
                <a href="../logout.php" style="color:#66707c;font-size:0.88rem;text-decoration:none;font-weight:600;"><i class="fas fa-sign-out-alt"></i> Sign out</a>
            </div>
        </div>

        <!-- PHASE BY PHASE STEPPER HEADER: 1 > 2 > 3 > Dashboard -->
        <div class="phase-stepper-wrap">
            <div class="stepper-step <?php echo !$submittedResult ? 'active' : 'completed'; ?>" id="stepNode1">
                <div class="step-num"><i class="fas fa-user-check"></i></div>
                <div class="step-label">Phase 1: Basic Details</div>
            </div>
            <div class="stepper-connector <?php echo !$submittedResult ? '' : 'completed'; ?>"></div>
            <div class="stepper-step <?php echo $submittedResult ? 'completed' : ''; ?>" id="stepNode2">
                <div class="step-num"><i class="fas fa-clipboard-question"></i></div>
                <div class="step-label">Phase 2: Health Questions</div>
            </div>
            <div class="stepper-connector <?php echo $submittedResult ? 'active' : ''; ?>"></div>
            <div class="stepper-step <?php echo $submittedResult ? 'active' : ''; ?>" id="stepNode3">
                <div class="step-num"><i class="fas fa-square-poll-vertical"></i></div>
                <div class="step-label">Phase 3: Eligibility Result</div>
            </div>
            <div class="stepper-connector"></div>
            <div class="stepper-step" id="stepNode4">
                <div class="step-num"><i class="fas fa-gauge-high"></i></div>
                <div class="step-label">Dashboard</div>
            </div>
        </div>

        <!-- Header Title Banner -->
        <header class="screening-header">
            <div class="screening-badge">
                <i class="fas fa-shield-heart"></i> Verified Donor Screening System
            </div>
            <h1>Donor Registration & Eligibility Screening</h1>
            <p class="subtitle">Please answer the following questions honestly. Final eligibility will be confirmed by qualified blood-bank staff.</p>
        </header>

        <!-- Informational Disclaimer -->
        <div class="screening-disclaimer">
            <i class="fas fa-shield-halved"></i>
            <div>
                <strong>Clinical Safety Guidelines (NBTC & Red Cross):</strong>
                This preliminary screening ensures transfusion safety for both donor and patient. Your information is encrypted and accessible only to authorized medical personnel.
            </div>
        </div>

        <?php if (!empty($errorMessage)): ?>
        <div style="background:#fee2e2;border:1px solid #ef4444;color:#b91c1c;padding:14px 20px;border-radius:12px;margin-bottom:24px;font-weight:700;">
            <i class="fas fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <?php endif; ?>

        <?php if ($submittedResult): ?>
        <!-- ==========================================
             PHASE 3: PRELIMINARY ELIGIBILITY RESULT
             ========================================== -->
        <div class="screening-card phase-container" id="phaseCard3">
            <div class="phase-header-badge">
                <i class="fas fa-square-poll-vertical"></i> Phase 3: Preliminary Eligibility Result
            </div>

            <?php if ($submittedResult['status'] === 'green'): ?>
            <div class="result-card-wrap result-green">
                <div class="result-icon-badge"><i class="fas fa-circle-check"></i></div>
                <h2>LIKELY ELIGIBLE TO DONATE</h2>
                <p class="result-text"><?php echo htmlspecialchars($submittedResult['message']); ?></p>
                <div class="result-checklist">
                    <div class="result-check-item"><i class="fas fa-check-circle text-success"></i> Preliminary screening passed</div>
                    <div class="result-check-item"><i class="fas fa-circle-info text-primary"></i> Final hemoglobin & physical screening required by blood-bank staff</div>
                </div>
                <div class="result-actions">
                    <a href="index.php" class="btn-result-action btn-result-primary">
                        <i class="fas fa-gauge-high"></i> Proceed to Phase 4: Donor Dashboard
                    </a>
                    <a href="profile.php" class="btn-result-action btn-result-outline">
                        <i class="fas fa-id-card"></i> View / Download Donor Card
                    </a>
                </div>
            </div>
            <?php elseif ($submittedResult['status'] === 'yellow'): ?>
            <div class="result-card-wrap result-yellow">
                <div class="result-icon-badge"><i class="fas fa-triangle-exclamation"></i></div>
                <h2>NEEDS MEDICAL REVIEW</h2>
                <p class="result-text"><?php echo htmlspecialchars($submittedResult['message']); ?></p>
                <div class="result-checklist">
                    <div class="result-check-item"><i class="fas fa-circle-exclamation text-warning"></i> Medical review required with blood-bank staff</div>
                    <div class="result-check-item"><i class="fas fa-user-doctor text-primary"></i> Final decision will be made by blood-bank staff before donation</div>
                </div>
                <div class="result-actions">
                    <a href="index.php" class="btn-result-action btn-result-primary">
                        <i class="fas fa-gauge-high"></i> Proceed to Phase 4: Donor Dashboard
                    </a>
                    <a href="profile.php" class="btn-result-action btn-result-outline">
                        <i class="fas fa-id-card"></i> View Donor Card
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="result-card-wrap result-red">
                <div class="result-icon-badge"><i class="fas fa-circle-xmark"></i></div>
                <h2>CURRENTLY INELIGIBLE / DEFERRED</h2>
                <p class="result-text"><?php echo htmlspecialchars($submittedResult['message']); ?></p>
                <div class="result-checklist">
                    <div class="result-check-item"><i class="fas fa-clock-rotate-left text-danger"></i> Temporary deferral for donor recovery and patient safety</div>
                    <div class="result-check-item"><i class="fas fa-hand-holding-heart text-primary"></i> You can still support blood requests in your community</div>
                </div>
                <div class="result-actions">
                    <a href="index.php" class="btn-result-action btn-result-primary">
                        <i class="fas fa-gauge-high"></i> Go to Donor Dashboard
                    </a>
                    <a href="eligibility.php" class="btn-result-action btn-result-outline">
                        <i class="fas fa-redo"></i> Retake Screening
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>

        <!-- MULTI-PHASE SCREENING FORM -->
        <form id="eligibilityScreeningForm" method="POST" action="eligibility.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

            <!-- ==========================================
                 PHASE 1: BASIC DETAILS (Real donor info)
                 ========================================== -->
            <div class="screening-card phase-container" id="phaseCard1">
                <div class="phase-header-badge">
                    <i class="fas fa-user-check"></i> Phase 1 of 3: Verified Donor Basic Details
                </div>
                <p style="color:var(--muted);font-size:0.92rem;margin-bottom:22px;">
                    Account information is retrieved directly from your registered donor profile. Please verify your details and emergency availability.
                </p>

                <div class="form-grid">
                    <!-- 1. Full Name (Auto-retrieved, read-only) -->
                    <div class="form-group">
                        <label class="form-label" for="fullName">
                            <i class="fas fa-user text-primary"></i> Full Name <span class="required-star">*</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle ms-2"><i class="fas fa-lock"></i> Verified Account</span>
                        </label>
                        <input type="text" id="fullName" class="form-control" value="<?php echo htmlspecialchars($userData['full_name'] ?? $userName); ?>" readonly title="Auto-retrieved from registered donor account">
                    </div>

                    <!-- 2. Mobile Number (Auto-retrieved, read-only) -->
                    <div class="form-group">
                        <label class="form-label" for="mobileNumber">
                            <i class="fas fa-phone text-primary"></i> Mobile Number <span class="required-star">*</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle ms-2"><i class="fas fa-lock"></i> Verified Mobile</span>
                        </label>
                        <input type="tel" id="mobileNumber" class="form-control" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>" placeholder="Auto-retrieved from donor account" <?php echo !empty($userData['phone']) ? 'readonly' : ''; ?>>
                    </div>

                    <!-- 3. Date of Birth & Age Calculation -->
                    <div class="form-group">
                        <label class="form-label" for="dateOfBirth">
                            <i class="fas fa-calendar text-danger"></i> Date of Birth <span class="required-star">*</span>
                            <span id="calculatedAgeDisplay" class="badge bg-secondary ms-2"><?php echo !empty($donorData['age_years']) ? $donorData['age_years'] . ' years old' : '—'; ?></span>
                        </label>
                        <input type="date" id="dateOfBirth" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($donorData['date_of_birth'] ?? ''); ?>" required max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>">
                        <input type="hidden" id="calculatedAge" name="calculated_age" value="<?php echo htmlspecialchars($donorData['age_years'] ?? ''); ?>">
                    </div>

                    <!-- 4. Gender -->
                    <div class="form-group">
                        <label class="form-label" for="gender">
                            <i class="fas fa-venus-mars text-primary"></i> Gender <span class="required-star">*</span>
                        </label>
                        <select id="gender" name="gender" class="form-select" required>
                            <?php $currGen = $donorData['gender'] ?? ''; ?>
                            <option value="male" <?php echo $currGen === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $currGen === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo $currGen === 'other' ? 'selected' : ''; ?>>Other</option>
                            <option value="prefer_not_to_say" <?php echo $currGen === 'prefer_not_to_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                        </select>
                    </div>

                    <!-- 5. City -->
                    <div class="form-group">
                        <label class="form-label" for="city">
                            <i class="fas fa-city text-primary"></i> City <span class="required-star">*</span>
                        </label>
                        <input type="text" id="city" name="city" class="form-control" value="<?php echo htmlspecialchars($donorData['city'] ?? ($userData['city'] ?? 'Chennai')); ?>" required placeholder="e.g. Chennai">
                    </div>

                    <!-- 6. Area -->
                    <div class="form-group">
                        <label class="form-label" for="area">
                            <i class="fas fa-location-dot text-primary"></i> Area / Locality <span class="required-star">*</span>
                        </label>
                        <input type="text" id="area" name="area" class="form-control" value="<?php echo htmlspecialchars($donorData['area'] ?? ($userData['area'] ?? '')); ?>" required placeholder="e.g. Adyar, T. Nagar">
                    </div>

                    <!-- 7. Preferred Language -->
                    <div class="form-group">
                        <label class="form-label" for="preferredLanguage">
                            <i class="fas fa-language text-primary"></i> Preferred Language <span class="required-star">*</span>
                        </label>
                        <select id="preferredLanguage" name="preferred_language" class="form-select" required>
                            <?php $currLang = $donorData['preferred_language'] ?? 'English'; ?>
                            <option value="English" <?php echo $currLang === 'English' ? 'selected' : ''; ?>>English</option>
                            <option value="Tamil" <?php echo $currLang === 'Tamil' ? 'selected' : ''; ?>>Tamil</option>
                            <option value="Hindi" <?php echo $currLang === 'Hindi' ? 'selected' : ''; ?>>Hindi</option>
                            <option value="Other" <?php echo $currLang === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <!-- 8. Blood Group -->
                    <div class="form-group">
                        <label class="form-label" for="bloodGroup">
                            <i class="fas fa-droplet text-danger"></i> Blood Group <span class="required-star">*</span>
                        </label>
                        <select id="bloodGroup" name="blood_group" class="form-select" required>
                            <?php 
                            $bGroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-', 'Unknown'];
                            $currBg = $donorData['blood_type'] ?? ($userData['blood_type'] ?? 'O+');
                            foreach ($bGroups as $bg): ?>
                            <option value="<?php echo $bg; ?>" <?php echo $currBg === $bg ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 9. Emergency Availability -->
                    <div class="form-group form-col-full">
                        <label class="form-label" for="emergencyAvailability">
                            <i class="fas fa-bell text-danger"></i> Emergency Availability <span class="required-star">*</span>
                        </label>
                        <select id="emergencyAvailability" name="emergency_availability" class="form-select" required>
                            <?php $currAvail = $donorData['emergency_availability'] ?? 'Available'; ?>
                            <option value="Available" <?php echo $currAvail === 'Available' ? 'selected' : ''; ?>>Available anytime for emergency donations</option>
                            <option value="Unavailable" <?php echo $currAvail === 'Unavailable' ? 'selected' : ''; ?>>Unavailable currently</option>
                            <option value="Available only on selected days" <?php echo $currAvail === 'Available only on selected days' ? 'selected' : ''; ?>>Available only on selected days</option>
                        </select>
                        <div id="selectedDaysWrap" class="days-picker" style="display:<?php echo $currAvail === 'Available only on selected days' ? 'flex' : 'none'; ?>;">
                            <?php 
                            $daysArr = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                            $activeDays = explode(',', $donorData['selected_available_days'] ?? '');
                            foreach ($daysArr as $d): ?>
                            <span class="day-chip <?php echo in_array($d, $activeDays) ? 'active' : ''; ?>" data-day="<?php echo $d; ?>"><?php echo $d; ?></span>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="selectedDaysHidden" name="selected_available_days" value="<?php echo htmlspecialchars($donorData['selected_available_days'] ?? ''); ?>">
                    </div>

                    <!-- 10. Consent Notifications -->
                    <div class="form-group form-col-full">
                        <label class="form-label">
                            <i class="fas fa-envelope-circle-check text-primary"></i> Consent to receive blood-donation notifications
                        </label>
                        <div style="display:flex;gap:24px;margin-top:6px;">
                            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;">
                                <input type="radio" name="notification_consent" value="yes" checked> Yes, notify me during urgent local shortages
                            </label>
                            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;">
                                <input type="radio" name="notification_consent" value="no"> No
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Phase 1 Navigation Action -->
                <div class="form-actions">
                    <button type="button" class="btn-submit-screening" id="btnGoToPhase2">
                        Next: Phase 2 Health Questions <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- ==========================================
                 PHASE 2: HEALTH SCREENING QUESTIONS (1-14)
                 ========================================== -->
            <div class="screening-card phase-container" id="phaseCard2" style="display:none;">
                <div class="phase-header-badge">
                    <i class="fas fa-clipboard-question"></i> Phase 2 of 3: Preliminary Health Screening Questions
                </div>
                <p style="color:var(--muted);font-size:0.92rem;margin-bottom:20px;">
                    Please answer all 14 questions honestly by clicking <strong>YES</strong> or <strong>NO</strong>.
                </p>

                <div class="question-list">
                    <!-- Q1 -->
                    <div class="question-row" data-question="healthy_today">
                        <div class="question-info">
                            <div class="question-text">1. Are you feeling healthy today?</div>
                            <div class="question-subtext">Free of fever, acute pain, or feeling unwell.</div>
                        </div>
                        <div class="btn-toggle-group">
                            <button type="button" class="btn-choice btn-yes"><i class="fas fa-check"></i> YES</button>
                            <button type="button" class="btn-choice btn-no"><i class="fas fa-xmark"></i> NO</button>
                        </div>
                        <input type="hidden" name="q_healthy_today" id="input_healthy_today" value="">
                    </div>

                    <!-- Q2 -->
                    <div class="question-row" data-question="age_18_plus">
                        <div class="question-info">
                            <div class="question-text">2. Are you at least 18 years old?</div>
                            <div class="question-subtext">Standard permissible donation age in India is 18 to 65 years.</div>
                        </div>
                        <div class="btn-toggle-group">
                            <button type="button" class="btn-choice btn-yes"><i class="fas fa-check"></i> YES</button>
                            <button type="button" class="btn-choice btn-no"><i class="fas fa-xmark"></i> NO</button>
                        </div>
                        <input type="hidden" name="q_age_18_plus" id="input_age_18_plus" value="">
                    </div>

                    <!-- Q3 -->
                    <div class="question-row" data-question="weight_45_plus">
                        <div class="question-info">
                            <div class="question-text">3. Is your weight at least 45 kg?</div>
                            <div class="question-subtext">Minimum threshold required to donate whole blood safely.</div>
                        </div>
                        <div class="btn-toggle-group">
                            <button type="button" class="btn-choice btn-yes"><i class="fas fa-check"></i> YES</button>
                            <button type="button" class="btn-choice btn-no"><i class="fas fa-xmark"></i> NO</button>
                        </div>
                        <input type="hidden" name="q_weight_45_plus" id="input_weight_45_plus" value="">
                    </div>

                    <!-- Q4 -->
                    <div class="question-row" data-question="recent_donation">
                        <div class="question-info">
                            <div class="question-text">4. Have you donated blood recently?</div>
                            <div class="question-subtext">A 90-day cooldown interval protects your body's red blood cell recovery.</div>
                        </div>
                        <div class="btn-toggle-group">
                            <button type="button" class="btn-choice btn-yes"><i class="fas fa-check"></i> YES</button>
                            <button type="button" class="btn-choice btn-no"><i class="fas fa-xmark"></i> NO</button>
                        </div>
                        <input type="hidden" name="q_recent_donation" id="input_recent_donation" value="">
                    </div>

                    <!-- Q5 -->
                    <div class="question-row" data-question="recent_illness">
                        <div class="question-info">
                            <div class="question-text">5. Have you had fever, cold, infection, or serious illness recently?</div>
                            <div class="question-subtext">Within the past 2 to 4 weeks.</div>
                        </div>
                        <div class="btn-toggle-group">
                            <button type="button" class="btn-choice btn-yes"><i class="fas fa-check"></i> YES</button>
                            <button type="button" class="btn-choice btn-no"><i class="fas fa-xmark"></i> NO</button>
                        </div>
                        <input type="hidden" name="q_recent_illness" id="input_recent_illness" value="">
                    </div>

                    <!-- Q6 -->
                    <div class="question-row" data-question="taking_medicines">
                        <div class="question-info">
                            <div class="question-text">6. Are you currently taking any medicines?</div>
                            <div class="question-subtext">Including blood thinners, steroids, or prescription antibiotics.</div>
                        </div>
                        <div class="btn-toggle-group">
                            <button type="button" class="btn-choice btn-yes"><i class="fas fa-check"></i> YES</button>
                            <button type="button" class="btn-choice btn-no"><i class="fas fa-xmark"></i> NO</button>
                        </div>
                        <input type="hidden" name="q_taking_medicines" id="input_taking_medicines" value="">
                    </div>

                    <!-- Q7 -->
                    <div class="question-row" data-question="recent_procedure">
                        <div class="question-info">
                            <div class="question-text">7. Have you had surgery, dental treatment, tattoo, piercing, or an invasive medical procedure recently?</div>
                            <div class="question-subtext">Within the last 6 to 12 months.</div>
                        </div>
                        <div class="btn-toggle-group">
                            <button type="button" class="btn-choice btn-yes"><i class="fas fa-check"></i> YES</button>
                            <button type="button" class="btn-choice btn-no"><i class="fas fa-xmark"></i> NO</button>
                        </div>
                        <input type="hidden" name="q_recent_procedure" id="input_recent_procedure" value="">
                    </div>

                    <!-- Q8 -->
                    <div class="question-row" data-question="recent_vaccination">
                        <div class="question-info">
                            <div class="question-text">8. Have you received a vaccination recently?</div>
                            <div class="question-subtext">Within the last 14 to 28 days.</div>
                        </div>
                        <div class="btn-toggle-group">
                            <button type="button" class="btn-choice btn-yes"><i class="fas fa-check"></i> YES</button>
                            <button type="button" class="btn-choice btn-no"><i class="fas fa-xmark"></i> NO</button>
                        </div>
                        <input type="hidden" name="q_recent_vaccination" id="input_recent_vaccination" value="">
                    </div>

                    <!-- Q9 -->
                    <div class="question-row" data-question="blood_related_infection">
                        <div class="question-info">
                            <div class="question-text">9. Have you ever had hepatitis, HIV, malaria, tuberculosis, or another blood-related infection?</div>
                            <div class="question-subtext">Critical transfusion safety marker.</div>
                        </div>
                        <div class="btn-toggle-group">
                            <button type="button" class="btn-choice btn-yes"><i class="fas fa-check"></i> YES</button>
                            <button type="button" class="btn-choice btn-no"><i class="fas fa-xmark"></i> NO</button>
                        </div>
                        <input type="hidden" name="q_blood_related_infection" id="input_blood_related_infection" value="">
                    </div>

                    <!-- Q10 (Sensitive) -->
                    <div class="question-row" data-question="pregnancy_status">
                        <div class="question-info">
                            <div class="question-text">
                                10. Are you pregnant, recently pregnant, or breastfeeding?
                                <span class="badge bg-light text-secondary border ms-2"><i class="fas fa-lock text-muted"></i> Confidential</span>
                            </div>
                            <div class="question-subtext">Handled privately and sensitively. Never exposed publicly on your donor profile or card.</div>
                        </div>
                        <div class="btn-toggle-group">
                            <button type="button" class="btn-choice btn-yes"><i class="fas fa-check"></i> YES</button>
                            <button type="button" class="btn-choice btn-no"><i class="fas fa-xmark"></i> NO</button>
                        </div>
                        <input type="hidden" name="q_pregnancy_status" id="input_pregnancy_status" value="">
                    </div>

                    <!-- Q11 -->
                    <div class="question-row" data-question="recent_alcohol_drug_use">
                        <div class="question-info">
                            <div class="question-text">11. Have you consumed alcohol or recreational drugs recently?</div>
                            <div class="question-subtext">Within the last 24–48 hours.</div>
                        </div>
                        <div class="btn-toggle-group">
                            <button type="button" class="btn-choice btn-yes"><i class="fas fa-check"></i> YES</button>
                            <button type="button" class="btn-choice btn-no"><i class="fas fa-xmark"></i> NO</button>
                        </div>
                        <input type="hidden" name="q_recent_alcohol_drug_use" id="input_recent_alcohol_drug_use" value="">
                    </div>

                    <!-- Q12 -->
                    <div class="question-row" data-question="feeling_unwell">
                        <div class="question-info">
                            <div class="question-text">12. Do you feel faint, weak, or unwell today?</div>
                            <div class="question-subtext">You should be well-rested, hydrated, and had a light meal.</div>
                        </div>
                        <div class="btn-toggle-group">
                            <button type="button" class="btn-choice btn-yes"><i class="fas fa-check"></i> YES</button>
                            <button type="button" class="btn-choice btn-no"><i class="fas fa-xmark"></i> NO</button>
                        </div>
                        <input type="hidden" name="q_feeling_unwell" id="input_feeling_unwell" value="">
                    </div>

                    <!-- Q13 -->
                    <div class="question-row" data-question="previous_donation_reaction">
                        <div class="question-info">
                            <div class="question-text">13. Have you donated blood before without a serious reaction?</div>
                            <div class="question-subtext">First-time donors can select YES if in good general health.</div>
                        </div>
                        <div class="btn-toggle-group">
                            <button type="button" class="btn-choice btn-yes"><i class="fas fa-check"></i> YES</button>
                            <button type="button" class="btn-choice btn-no"><i class="fas fa-xmark"></i> NO</button>
                        </div>
                        <input type="hidden" name="q_previous_donation_reaction" id="input_previous_donation_reaction" value="">
                    </div>

                    <!-- Q14 -->
                    <div class="question-row" data-question="medical_condition">
                        <div class="question-info">
                            <div class="question-text">14. Do you have any medical condition that a doctor should know about?</div>
                            <div class="question-subtext">Such as cardiac issues, bleeding disorders, asthma, or kidney conditions.</div>
                        </div>
                        <div class="btn-toggle-group">
                            <button type="button" class="btn-choice btn-yes"><i class="fas fa-check"></i> YES</button>
                            <button type="button" class="btn-choice btn-no"><i class="fas fa-xmark"></i> NO</button>
                        </div>
                        <input type="hidden" name="q_medical_condition" id="input_medical_condition" value="">
                    </div>

                    <!-- Optional Staff Notes -->
                    <div class="form-group form-col-full" style="margin-top:14px;">
                        <label class="form-label" for="additionalInfo">
                            <i class="fas fa-comment-medical text-primary"></i> Additional information for blood-bank staff (Optional)
                        </label>
                        <textarea id="additionalInfo" name="additional_information" class="form-control" rows="3" placeholder="Provide any details about current health, recent travels, or questions for the doctor..."></textarea>
                    </div>
                </div>

                <!-- Phase 2 Navigation Action -->
                <div class="form-actions" style="display:flex;justify-content:space-between;align-items:center;">
                    <button type="button" class="btn-result-action btn-result-outline" id="btnBackToPhase1">
                        <i class="fas fa-arrow-left"></i> Back to Phase 1 Details
                    </button>
                    <button type="submit" class="btn-submit-screening">
                        Submit Answers & View Phase 3 Result <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- Script -->
    <script src="../assets/js/donor_eligibility.js"></script>
</body>
</html>