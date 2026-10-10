<?php
/**
 * api/get_schemes.php — Fast & Resilient Government Health Schemes API
 * Provides live search, state/category filtering, sorting, and smart matching.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

// Comprehensive verified schemes dataset (Official Central & State Schemes 2026)
$masterSchemes = [
    [
        'id' => 'pmjay',
        'name' => 'Ayushman Bharat Pradhan Mantri Jan Arogya Yojana (AB PM-JAY)',
        'department' => 'Ministry of Health & Family Welfare / National Health Authority',
        'description' => 'Flagship health insurance scheme providing free secondary and tertiary care hospitalization coverage up to ₹5 lakh per family per year at empanelled public and private hospitals across India.',
        'category' => 'insurance',
        'categoryLabel' => 'Health Insurance',
        'state' => 'central',
        'stateLabel' => 'Central Government',
        'labels' => ['Central Government', 'Insurance', 'Free Treatment'],
        'eligibility' => 'Families listed under SECC 2011 deprivation categories; urban occupational categories (domestic workers, street vendors, construction workers, etc.); all senior citizens aged 70+ irrespective of socio-economic status; ASHA/Anganwadi workers.',
        'benefits' => '₹5 lakh per family per year for secondary and tertiary care; cashless and paperless treatment at empanelled hospitals; all pre-existing diseases covered from day one; 1,961 procedures across 27 specialties covered under HBP-2022.',
        'documents' => 'Aadhaar card (primary ID); Ration card; SECC 2011 verification; PM letter (if applicable); Voter ID / PAN / Driving License as supplementary ID.',
        'applyMethod' => 'Check eligibility at beneficiary.nha.gov.in; enroll via Ayushman App (Android), Common Service Centre, or at nearby empanelled hospital; contact PM-JAY helpdesk.',
        'website' => 'https://pmjay.gov.in',
        'updated' => '2026-08-15'
    ],
    [
        'id' => 'vay-vandana',
        'name' => 'Ayushman Bharat Vay Vandana Card (Senior Citizens 70+)',
        'department' => 'Ministry of Health & Family Welfare / National Health Authority',
        'description' => 'Expanded coverage under AB PM-JAY providing free treatment up to ₹5 lakh per year to all senior citizens aged 70 years and above, irrespective of socio-economic status.',
        'category' => 'senior',
        'categoryLabel' => 'Senior Citizen Healthcare',
        'state' => 'central',
        'stateLabel' => 'Central Government',
        'labels' => ['Central Government', 'Free Treatment', 'Senior Citizen'],
        'eligibility' => 'All senior citizens aged 70 years and above; irrespective of economic status; those under private health insurance or ESI also eligible to avail benefits.',
        'benefits' => '₹5 lakh per year free medical treatment; cashless and paperless treatment at empanelled hospitals; all pre-existing diseases covered; 1,961 procedures covered; special Ayushman Vay Vandana Card issued.',
        'documents' => 'Aadhaar card (primary ID); Age proof; Address proof; Bank account details; Mobile number for OTP verification.',
        'applyMethod' => 'Apply through beneficiary.nha.gov.in; use Ayushman App (Android); give missed call on 1800110770 for Ayushman Vay Vandana Card; visit nearest empanelled hospital or Common Service Centre.',
        'website' => 'https://beneficiary.nha.gov.in',
        'updated' => '2026-08-01'
    ],
    [
        'id' => 'cghs',
        'name' => 'Central Government Health Scheme (CGHS)',
        'department' => 'Ministry of Health & Family Welfare, Department of Health and Family Welfare',
        'description' => 'Comprehensive healthcare scheme for serving and retired Central Government employees, pensioners, and their dependents through a network of wellness centers and empanelled hospitals.',
        'category' => 'insurance',
        'categoryLabel' => 'Health Insurance',
        'state' => 'central',
        'stateLabel' => 'Central Government',
        'labels' => ['Central Government', 'Insurance', 'Free Treatment'],
        'eligibility' => 'Serving and retired Central Government employees; pensioners; dependents; autonomous body employees; Ex-Governors and their families.',
        'benefits' => 'OPD treatment at CGHS wellness centers including issue of prescribed medicines; specialist consultation at government and private empanelled hospitals; diagnostic investigations; hospitalization; cashless treatment at empanelled hospitals.',
        'documents' => 'CGHS card; Aadhaar card; Proof of employment / pension; Passport-size photographs; Service book / pension papers.',
        'applyMethod' => 'Apply online through CGHS portal for plastic card; visit nearest CGHS wellness center for enrollment; register at cghs.mohfw.gov.in.',
        'website' => 'https://cghs.mohfw.gov.in',
        'updated' => '2026-07-20'
    ],
    [
        'id' => 'esic',
        'name' => "Employees' State Insurance Corporation (ESIC)",
        'department' => 'Ministry of Labour & Employment, Government of India',
        'description' => "Health insurance and social security scheme for organized sector workers, providing medical care, cash benefits during sickness, maternity, disability, and dependents' benefits.",
        'category' => 'insurance',
        'categoryLabel' => 'Health Insurance',
        'state' => 'central',
        'stateLabel' => 'Central Government',
        'labels' => ['Central Government', 'Insurance', 'Financial Assistance'],
        'eligibility' => 'Employees working in factories and establishments with 10 or more employees; wages up to ₹21,000/month (₹25,000 for persons with disability); employees covered under ESI Act 1948.',
        'benefits' => 'Free medical treatment for insured person and dependents; cash benefit during sickness (70% of wages); maternity benefit (full wages); disablement benefit; dependents\' benefit; funeral expenses; confinement expenses.',
        'documents' => 'ESIC insurance number; Aadhaar card; Employer certificate; Bank account details; Passport-size photograph.',
        'applyMethod' => 'Employer registers employees on ESIC portal; visit nearest ESIC dispensary/hospital; apply online at esic.gov.in; use ESIC mobile app for services.',
        'website' => 'https://www.esic.gov.in',
        'updated' => '2026-06-12'
    ],
    [
        'id' => 'ran',
        'name' => 'Rashtriya Arogya Nidhi (RAN) & Health Minister\'s Discretionary Grant',
        'department' => 'Ministry of Health & Family Welfare',
        'description' => 'Central sector scheme providing one-time financial assistance to poor patients living below the poverty line suffering from life-threatening diseases related to heart, kidney, liver, cancer, and rare diseases.',
        'category' => 'financial',
        'categoryLabel' => 'Financial Assistance',
        'state' => 'central',
        'stateLabel' => 'Central Government',
        'labels' => ['Central Government', 'Financial Assistance', 'Critical Illness'],
        'eligibility' => 'Poor patients living below State/UT-wise threshold poverty line; suffering from life-threatening diseases; treatment at government hospitals/institutes with super-speciality facilities.',
        'benefits' => 'One-time financial assistance up to ₹15 lakh for treatment of life-threatening diseases; up to ₹20 lakh for rare diseases; Health Minister\'s Discretionary Grant up to ₹1.25 lakh for hospitalization/treatment.',
        'documents' => 'BPL certificate; Income certificate; Aadhaar card; Medical reports and diagnosis; Hospital estimate; Application in prescribed format.',
        'applyMethod' => 'Contact Nodal Officer of RAN at concerned Government hospital/institute; applications processed online through National Health Authority IT portal; offline submission available.',
        'website' => 'https://mohfw.gov.in',
        'updated' => '2026-07-28'
    ],
    [
        'id' => 'pmsma',
        'name' => 'Pradhan Mantri Surakshit Matritva Abhiyan (PMSMA)',
        'department' => 'Ministry of Health & Family Welfare / National Health Mission',
        'description' => 'Provides free, comprehensive, and quality antenatal care to pregnant women on the 9th of every month at government health facilities, aiming to reduce maternal and infant mortality.',
        'category' => 'maternal',
        'categoryLabel' => 'Maternal & Child Healthcare',
        'state' => 'central',
        'stateLabel' => 'Central Government',
        'labels' => ['Central Government', 'Free Treatment', 'Maternal & Child'],
        'eligibility' => 'All pregnant women in the second or third trimester; free check-up on 9th of every month; available at government health facilities and medical colleges.',
        'benefits' => 'Free antenatal check-ups; free diagnostics and investigations; free medicines and supplements; identification and management of high-risk pregnancies; referral support for complicated cases.',
        'documents' => 'Aadhaar card; MCP card (Mother and Child Protection card); Previous medical records; Address proof.',
        'applyMethod' => 'Visit nearest government health facility on 9th of every month; contact ASHA/ANM for registration; no separate application required.',
        'website' => 'https://pmsma.nhp.gov.in',
        'updated' => '2026-05-18'
    ],
    [
        'id' => 'jsy',
        'name' => 'Janani Suraksha Yojana (JSY)',
        'department' => 'National Health Mission, Ministry of Health & Family Welfare',
        'description' => 'Safe motherhood intervention promoting institutional delivery among poor pregnant women through conditional cash transfer, integrating cash assistance with delivery and post-delivery care.',
        'category' => 'maternal',
        'categoryLabel' => 'Maternal & Child Healthcare',
        'state' => 'central',
        'stateLabel' => 'Central Government',
        'labels' => ['Central Government', 'Financial Assistance', 'Maternal & Child'],
        'eligibility' => 'LPS states: all pregnant women delivering in government health centres. HPS states: BPL/SC/ST women delivering in government health centres. BPL/SC/ST women in accredited private institutions.',
        'benefits' => 'Rural LPS: ₹1,400 (mother) + ₹600 (ASHA). Rural HPS: ₹700 + ₹600. Urban LPS: ₹1,000 + ₹400. Urban HPS: ₹600 + ₹400. Cash assistance for institutional delivery.',
        'documents' => 'BPL card / SC/ST certificate; Aadhaar card; MCP card (Mother and Child Protection card); Bank account details; ASHA referral.',
        'applyMethod' => 'Contact ASHA worker or ANM at nearest Sub Centre/PHC/CHC; register for antenatal care; delivery at government or accredited private hospital; cash transferred via bank account.',
        'website' => 'https://nhm.gov.in',
        'updated' => '2026-04-23'
    ],
    [
        'id' => 'npy',
        'name' => 'Ni-kshay Poshan Yojana (NPY)',
        'department' => 'National Tuberculosis Elimination Programme, Ministry of Health & Family Welfare',
        'description' => 'Direct Benefit Transfer scheme providing nutritional support to tuberculosis patients notified on the Nikshay portal, at the time of notification and during treatment.',
        'category' => 'financial',
        'categoryLabel' => 'Financial Assistance',
        'state' => 'central',
        'stateLabel' => 'Central Government',
        'labels' => ['Central Government', 'Financial Assistance', 'Free Treatment'],
        'eligibility' => 'All TB patients notified on the Nikshay portal; registered under National Tuberculosis Elimination Programme (NTEP); treatment at public health facilities.',
        'benefits' => '₹500 per treatment month paid in installments of up to ₹1,000 via Direct Benefit Transfer (DBT); nutritional support throughout treatment duration; free TB diagnosis and treatment at public health facilities.',
        'documents' => 'Aadhaar card; Bank account details; Nikshay portal registration; TB diagnosis confirmation from government health facility.',
        'applyMethod' => 'Visit nearest government health facility for TB diagnosis; ensure notification on Nikshay portal; provide bank account details for DBT; automatic enrollment upon notification.',
        'website' => 'https://tbcindia.mohfw.gov.in',
        'updated' => '2026-07-14'
    ],
    [
        'id' => 'nhm',
        'name' => 'National Health Mission (NHM)',
        'department' => 'Ministry of Health & Family Welfare, Government of India',
        'description' => 'Umbrella mission providing accessible, affordable, and quality healthcare to rural and vulnerable populations through Ayushman Arogya Mandirs, free drugs, diagnostics, and maternal-child health services.',
        'category' => 'free-treatment',
        'categoryLabel' => 'Free / Subsidized Treatment',
        'state' => 'central',
        'stateLabel' => 'Central Government',
        'labels' => ['Central Government', 'Free Treatment', 'Maternal & Child'],
        'eligibility' => 'All citizens, with focus on rural population, poor, women, children, and vulnerable sections; services available at government health facilities.',
        'benefits' => 'Free drugs and diagnostics at public health facilities; comprehensive primary healthcare through Ayushman Arogya Mandirs; maternal and child health services; immunization; free ambulance services (108/102).',
        'documents' => 'Aadhaar card (optional); Ration card (for verification where applicable); Address proof; Any government-issued ID.',
        'applyMethod' => 'Visit nearest Ayushman Arogya Mandir / PHC / CHC / District Hospital; no formal application needed for most services; contact ASHA/ANM for guidance.',
        'website' => 'https://nhm.gov.in',
        'updated' => '2026-06-30'
    ],
    [
        'id' => 'pmssy',
        'name' => 'Pradhan Mantri Swasthya Suraksha Yojana (PMSSY)',
        'department' => 'Ministry of Health & Family Welfare, Government of India',
        'description' => 'Aims to correct regional imbalances in availability of affordable and reliable tertiary healthcare by setting up AIIMS-like institutions and upgrading government medical colleges.',
        'category' => 'infrastructure',
        'categoryLabel' => 'Healthcare Infrastructure',
        'state' => 'central',
        'stateLabel' => 'Central Government',
        'labels' => ['Central Government', 'Free Treatment', 'Infrastructure'],
        'eligibility' => 'All citizens seeking treatment at AIIMS and upgraded government medical colleges; no income ceiling; services available at PMSSY-funded institutions across states.',
        'benefits' => 'Access to super-speciality healthcare at AIIMS-like institutions; upgraded medical college facilities; affordable tertiary care; improved medical education and research infrastructure.',
        'documents' => 'Aadhaar card; Previous medical records; Referral letter (if applicable); Address proof; Any government-issued ID.',
        'applyMethod' => 'Visit nearest AIIMS or PMSSY-upgraded medical college; register at OPD; online appointment through respective institution websites; referral from district hospital preferred.',
        'website' => 'https://pmssy.mohfw.gov.in',
        'updated' => '2026-05-25'
    ],
    [
        'id' => 'pmsby',
        'name' => 'Pradhan Mantri Suraksha Bima Yojana (PMSBY)',
        'department' => 'Department of Financial Services, Ministry of Finance',
        'description' => 'One-year personal accident insurance scheme offering coverage for accidental death and disability, renewable annually with a nominal premium of ₹20 per year.',
        'category' => 'insurance',
        'categoryLabel' => 'Health Insurance',
        'state' => 'central',
        'stateLabel' => 'Central Government',
        'labels' => ['Central Government', 'Insurance', 'Financial Assistance'],
        'eligibility' => 'Individuals aged 18–70 years having a bank account; consent to join and enable auto-debit; available through participating banks and post offices.',
        'benefits' => '₹2 lakh payable on accidental death or permanent total disability; ₹1 lakh on partial permanent disability; simple claim settlement with minimum documentation; annual premium auto-debited.',
        'documents' => 'Bank account details (for auto-debit); Aadhaar card; Consent form; Nominee details.',
        'applyMethod' => 'Contact your bank or post office; fill PMSBY enrollment form; enable auto-debit for annual premium; online enrollment available through net banking.',
        'website' => 'https://financialservices.gov.in',
        'updated' => '2026-06-01'
    ],
    [
        'id' => 'aarogyasri',
        'name' => 'Dr. YSR Aarogyasri Health Scheme',
        'department' => 'Government of Andhra Pradesh, Health Medical & Family Welfare Department',
        'description' => 'Flagship health scheme of Andhra Pradesh providing quality healthcare and financial aid to low-income families through cashless treatment at network hospitals for serious illnesses.',
        'category' => 'free-treatment',
        'categoryLabel' => 'Free / Subsidized Treatment',
        'state' => 'andhra-pradesh',
        'stateLabel' => 'Andhra Pradesh',
        'labels' => ['State Government', 'Free Treatment', 'Critical Illness'],
        'eligibility' => 'BPL families in Andhra Pradesh; annual income below prescribed threshold; registered under YSR Navasakam / Grama Ward Sachivalayam; SECC-listed families.',
        'benefits' => 'Annual coverage of ₹5 lakh per family; cashless inpatient and outpatient care; coverage for pre-existing diseases; post-hospitalization follow-up; free dialysis for chronic kidney disease; coverage of serious illnesses.',
        'documents' => 'Aadhaar card; Income certificate; BPL certificate; Address proof; YSR Aarogyasri card / registration number; Ration card.',
        'applyMethod' => 'Visit YSR Navasakam or Grama Ward Sachivalayam portal (navasakam.apcfss.in); login and complete Aarogyasri Health Card application; upload documents; submit and print acknowledgement.',
        'website' => 'https://www.ysraarogyasri.ap.gov.in',
        'updated' => '2026-08-10'
    ],
    [
        'id' => 'cmchis',
        'name' => "Chief Minister's Comprehensive Health Insurance Scheme (CMCHIS)",
        'department' => 'Government of Tamil Nadu, Tamil Nadu Health Systems Project',
        'description' => 'State health insurance scheme providing quality healthcare to eligible families through empanelled government and private hospitals, reducing financial hardship and moving toward universal health coverage.',
        'category' => 'insurance',
        'categoryLabel' => 'Health Insurance',
        'state' => 'tamil-nadu',
        'stateLabel' => 'Tamil Nadu',
        'labels' => ['State Government', 'Insurance', 'Free Treatment'],
        'eligibility' => 'Families with annual income below prescribed threshold; members of registered welfare boards; Sri Lankan Tamil refugees; families identified by the state government.',
        'benefits' => 'Cashless treatment for listed procedures; coverage of hospitalization expenses; coverage for serious illnesses; empanelled government and private hospitals; annual coverage enhanced to ₹25 lakh per family.',
        'documents' => 'Income certificate; Aadhaar card; Ration card; CMCHIS enrollment card; Address proof; Passport-size photograph.',
        'applyMethod' => 'Visit CMCHIS project office or empanelled hospital; apply through Tamil Nadu e-Sevai centers; online application at cmchistn.com; toll-free helpline 1800 425 3993.',
        'website' => 'https://www.cmchistn.com',
        'updated' => '2026-08-21'
    ],
    [
        'id' => 'mjpjay',
        'name' => 'Mahatma Jyotirao Phule Jan Arogya Yojana (MJPJAY)',
        'department' => 'Government of Maharashtra, State Health Assurance Society',
        'description' => "Maharashtra's flagship health insurance scheme providing cashless treatment for serious illnesses to eligible families across the state.",
        'category' => 'free-treatment',
        'categoryLabel' => 'Free / Subsidized Treatment',
        'state' => 'maharashtra',
        'stateLabel' => 'Maharashtra',
        'labels' => ['State Government', 'Free Treatment', 'Insurance'],
        'eligibility' => 'Yellow, Orange, Antyodaya, and Annapurna ration card holder families; families from 865 border villages; road accident victims; expanded coverage for all families in Maharashtra.',
        'benefits' => 'Free treatment up to ₹5 lakh per family per year; cashless treatment at empanelled government and private hospitals; coverage for serious illnesses; dialysis, cancer treatment, cardiac procedures covered.',
        'documents' => 'Ration card (Yellow/Orange/Antyodaya/Annapurna); Aadhaar card; Domicile certificate; Income certificate; MJPJAY health card; Referral letter from government doctor.',
        'applyMethod' => 'Visit nearest government hospital or empanelled network hospital; contact Arogya Mitra at hospital; apply through jeevandayee.gov.in; toll-free helpline 155388 / 1800 233 2200.',
        'website' => 'https://www.jeevandayee.gov.in',
        'updated' => '2026-07-05'
    ],
    [
        'id' => 'ma-yojana',
        'name' => 'Mukhyamantri Amrutum Yojana (MA Yojana) — PMJAY-MA',
        'department' => 'Government of Gujarat, Health & Family Welfare Department',
        'description' => "Gujarat's health insurance scheme for Below Poverty Line families, integrated with PM-JAY as PMJAY-MA, providing cashless hospitalization and surgical procedures at empanelled hospitals.",
        'category' => 'free-treatment',
        'categoryLabel' => 'Free / Subsidized Treatment',
        'state' => 'gujarat',
        'stateLabel' => 'Gujarat',
        'labels' => ['State Government', 'Free Treatment', 'Insurance'],
        'eligibility' => 'Below Poverty Line (BPL) families in Gujarat; low-income families; SECC-listed families; PMJAY-MA integrated beneficiaries; 2.73 crore active card holders across the state.',
        'benefits' => 'Coverage for hospitalization and surgical procedures; ₹5 lakh per family per year under integrated PMJAY-MA; cashless treatment at 2,090 empanelled hospitals; 2,299 medical procedures + 50 specialized referral services.',
        'documents' => 'Aadhaar card; Ration card; Income certificate; BPL certificate; PMJAY-MA card; Address proof.',
        'applyMethod' => 'Visit nearest Common Service Centre or empanelled hospital; check eligibility at beneficiary.nha.gov.in; apply through Gujarat health department portal; contact district health office.',
        'website' => 'https://www.gujaratindia.gov.in',
        'updated' => '2026-02-23'
    ],
    [
        'id' => 'swasthya-sathi',
        'name' => 'Swasthya Sathi',
        'department' => 'Government of West Bengal, Health & Family Welfare Department',
        'description' => "West Bengal's flagship health insurance scheme providing cashless treatment to all families in the state, with coverage for secondary and tertiary care hospitalization.",
        'category' => 'insurance',
        'categoryLabel' => 'Health Insurance',
        'state' => 'west-bengal',
        'stateLabel' => 'West Bengal',
        'labels' => ['State Government', 'Insurance', 'Free Treatment'],
        'eligibility' => 'All families in West Bengal; universal coverage; no income ceiling; families with Swasthya Sathi card; Aadhaar-linked enrollment.',
        'benefits' => 'Coverage up to ₹5 lakh per family per year; cashless treatment at empanelled government and private hospitals; coverage for serious illnesses and surgeries; pre-existing conditions covered; free diagnostics.',
        'documents' => 'Aadhaar card; Swasthya Sathi card; Ration card; Address proof; Passport-size photograph.',
        'applyMethod' => 'Visit nearest government hospital or empanelled hospital; apply through Swasthya Sathi portal; contact Block Medical Officer of Health; enroll via Duare Sarkar camps.',
        'website' => 'https://swasthyasathi.gov.in',
        'updated' => '2026-06-10'
    ],
    [
        'id' => 'rajasthan-ayushman',
        'name' => 'Mukhyamantri Ayushman Arogya Yojana (MAAY)',
        'department' => 'Government of Rajasthan, State Health Assurance Agency',
        'description' => "Rajasthan's integrated health scheme combining AB PM-JAY with state coverage, providing free treatment to eligible families at government and empanelled private hospitals.",
        'category' => 'free-treatment',
        'categoryLabel' => 'Free / Subsidized Treatment',
        'state' => 'rajasthan',
        'stateLabel' => 'Rajasthan',
        'labels' => ['State Government', 'Free Treatment', 'Insurance'],
        'eligibility' => 'NFSA families; SECC-listed families; BPL families; PM-JAY eligible beneficiaries; approximately 1.4 crore families covered across Rajasthan.',
        'benefits' => '₹25 lakh per family per year (enhanced); free treatment at government and empanelled private hospitals; cashless treatment; coverage for serious illnesses; free diagnostics and medicines.',
        'documents' => 'Aadhaar card; Ration card; Income certificate; SECC verification; MAAY/PM-JAY card; Address proof.',
        'applyMethod' => 'Check eligibility at beneficiary.nha.gov.in; visit nearest government hospital or empanelled hospital; apply through Rajasthan health portal; contact Arogya Mitra.',
        'website' => 'https://rajswasthya.nic.in',
        'updated' => '2026-07-19'
    ],
    [
        'id' => 'karnataka-arogya',
        'name' => 'Ayushman Bharat Arogya Karnataka (AB-ArK)',
        'department' => 'Government of Karnataka, Health & Family Welfare Department',
        'description' => 'Integrated health assurance scheme combining Ayushman Bharat PM-JAY and state Arogya Karnataka, providing free and subsidized treatment to eligible families in Karnataka.',
        'category' => 'free-treatment',
        'categoryLabel' => 'Free / Subsidized Treatment',
        'state' => 'karnataka',
        'stateLabel' => 'Karnataka',
        'labels' => ['State Government', 'Free Treatment', 'Insurance'],
        'eligibility' => 'BPL families; APL families with annual income below prescribed limit; SECC-listed families; PM-JAY eligible families.',
        'benefits' => 'Free treatment for BPL families; subsidized treatment for APL families; coverage up to ₹5 lakh per family per year; cashless treatment at empanelled hospitals.',
        'documents' => 'Aadhaar card; Ration card (BPL/APL); Income certificate; SECC verification; AB-ArK card; Address proof.',
        'applyMethod' => 'Visit nearest government hospital or empanelled private hospital; apply through Arogya Karnataka portal; contact Arogya Mitra at hospital; helpline 104.',
        'website' => 'https://arogya.karnataka.gov.in',
        'updated' => '2026-06-18'
    ],
    [
        'id' => 'kasp',
        'name' => 'Karunya Arogya Suraksha Padhathi (KASP)',
        'department' => 'Government of Kerala, State Health Agency',
        'description' => "Kerala's health assurance scheme integrated with PM-JAY, providing financial protection for secondary and tertiary care hospitalization to poor and vulnerable families.",
        'category' => 'insurance',
        'categoryLabel' => 'Health Insurance',
        'state' => 'kerala',
        'stateLabel' => 'Kerala',
        'labels' => ['State Government', 'Insurance', 'Free Treatment'],
        'eligibility' => 'Poor and vulnerable families in Kerala; SECC-listed families; PM-JAY eligible beneficiaries; families with annual income below prescribed threshold.',
        'benefits' => '₹5 lakh per family per year for secondary and tertiary care; cashless treatment at empanelled hospitals; coverage for serious illnesses; pre-existing diseases covered.',
        'documents' => 'Aadhaar card; Ration card; Income certificate; SECC verification; KASP/PM-JAY card; Address proof.',
        'applyMethod' => 'Check eligibility at beneficiary.nha.gov.in; visit nearest government hospital or empanelled hospital; apply through State Health Agency Kerala; contact Arogya Mitra.',
        'website' => 'https://sha.kerala.gov.in',
        'updated' => '2026-05-30'
    ],
    [
        'id' => 'punjab-sehat',
        'name' => 'Mukh Mantri Sehat Yojana (Punjab)',
        'department' => 'Government of Punjab, Department of Health & Family Welfare',
        'description' => "Punjab's health insurance scheme integrated with AB PM-JAY, providing cashless treatment to eligible families at government and empanelled private hospitals across the state.",
        'category' => 'insurance',
        'categoryLabel' => 'Health Insurance',
        'state' => 'punjab',
        'stateLabel' => 'Punjab',
        'labels' => ['State Government', 'Insurance', 'Free Treatment'],
        'eligibility' => 'SECC-listed families; BPL families; PM-JAY eligible beneficiaries; state-specific beneficiary databases.',
        'benefits' => '₹5 lakh per family per year; cashless treatment at empanelled hospitals; coverage for secondary and tertiary care; pre-existing diseases covered; free diagnostics and medicines.',
        'documents' => 'Aadhaar card; Ration card; Income certificate; SECC verification; PM-JAY/Sehat card; Address proof.',
        'applyMethod' => 'Check eligibility at beneficiary.nha.gov.in; visit nearest government hospital or empanelled hospital; apply through Punjab health portal; contact Arogya Mitra.',
        'website' => 'https://punjab.gov.in',
        'updated' => '2026-04-08'
    ],
    [
        'id' => 'atal-amrit',
        'name' => 'Atal Amrit Abhiyan',
        'department' => 'Government of Assam, National Health Mission Assam',
        'description' => "Assam's flagship health assurance scheme providing free treatment for critical diseases to eligible families, covering both BPL and APL families below a specified income threshold.",
        'category' => 'critical',
        'categoryLabel' => 'Critical Illness Assistance',
        'state' => 'assam',
        'stateLabel' => 'Assam',
        'labels' => ['State Government', 'Critical Illness', 'Free Treatment'],
        'eligibility' => 'BPL families in Assam; APL families with annual income below ₹5 lakh; all NFSA beneficiaries; government employees excluded.',
        'benefits' => '₹2 lakh per family per year for critical illnesses; coverage for cancer, cardiac, kidney, liver, neurological, and burn injuries; cashless treatment at empanelled hospitals; free dialysis.',
        'documents' => 'Aadhaar card; Ration card; Income certificate; Atal Amrit card; Address proof; Medical diagnosis report.',
        'applyMethod' => 'Visit nearest government hospital or empanelled hospital; apply through Atal Amrit Abhiyan portal; contact District Programme Management Unit; toll-free helpline 1800 345 3611.',
        'website' => 'https://atalamritabhiyan.assam.gov.in',
        'updated' => '2026-03-15'
    ],
    [
        'id' => 'chirayu-haryana',
        'name' => 'Chirayu Ayushman Haryana',
        'department' => 'Government of Haryana, State Health Agency',
        'description' => "Haryana's integrated health scheme combining AB PM-JAY with state coverage, providing free and cashless treatment to eligible families at empanelled hospitals across the state.",
        'category' => 'insurance',
        'categoryLabel' => 'Health Insurance',
        'state' => 'haryana',
        'stateLabel' => 'Haryana',
        'labels' => ['State Government', 'Insurance', 'Free Treatment'],
        'eligibility' => 'SECC-listed families; BPL families; PM-JAY eligible beneficiaries; state-specific beneficiary databases.',
        'benefits' => '₹5 lakh per family per year; cashless treatment at empanelled hospitals; coverage for secondary and tertiary care; pre-existing diseases covered; free diagnostics and medicines.',
        'documents' => 'Aadhaar card; Ration card; Income certificate; SECC verification; Chirayu/PM-JAY card; Address proof.',
        'applyMethod' => 'Check eligibility at beneficiary.nha.gov.in; visit nearest government hospital or empanelled hospital; apply through Haryana health portal; contact Arogya Mitra.',
        'website' => 'https://ayushmanbharat.haryana.gov.in',
        'updated' => '2026-05-22'
    ],
    [
        'id' => 'himcare',
        'name' => 'Himcare',
        'department' => 'Government of Himachal Pradesh, State Health Agency',
        'description' => "Himachal Pradesh's health insurance scheme integrated with AB PM-JAY, providing cashless treatment to eligible families not covered under PM-JAY, covering both BPL and APL families.",
        'category' => 'insurance',
        'categoryLabel' => 'Health Insurance',
        'state' => 'himachal-pradesh',
        'stateLabel' => 'Himachal Pradesh',
        'labels' => ['State Government', 'Insurance', 'Free Treatment'],
        'eligibility' => 'Families not covered under AB PM-JAY; BPL families; APL families with annual income below prescribed limit; SECC-listed families.',
        'benefits' => '₹5 lakh per family per year; cashless treatment at empanelled government and private hospitals; coverage for secondary and tertiary care; pre-existing diseases covered.',
        'documents' => 'Aadhaar card; Ration card; Income certificate; Himcare card; Address proof; SECC verification (if applicable).',
        'applyMethod' => 'Visit nearest government hospital or empanelled hospital; apply through Himcare portal; contact District Health Officer; enroll via Lok Mitra Kendra.',
        'website' => 'https://himcare.hp.gov.in',
        'updated' => '2026-06-05'
    ]
];

// Query Parameters
$search = trim($_GET['q'] ?? $_GET['search'] ?? '');
$state = trim($_GET['state'] ?? '');
$category = trim($_GET['category'] ?? '');
$sort = trim($_GET['sort'] ?? 'relevance');

// Smart Matcher parameters
$support = trim($_GET['support'] ?? '');
$age = trim($_GET['age'] ?? '');
$type = trim($_GET['type'] ?? '');

$filtered = $masterSchemes;

// Smart Matching Algorithm
if ($support || $age || $type) {
    $scored = [];
    foreach ($filtered as $s) {
        $score = 0;
        if ($support && $s['category'] === $support) $score += 5;
        if ($type && $s['category'] === $type) $score += 3;
        if ($state && ($s['state'] === $state || ($state === 'central' && $s['state'] === 'central'))) $score += 3;
        if ($age === 'senior' && $s['category'] === 'senior') $score += 5;
        if ($age === 'child' && $s['category'] === 'maternal') $score += 4;
        if ($age === 'adult' && ($s['category'] === 'insurance' || $s['category'] === 'financial')) $score += 1;
        if ($score > 0) {
            $s['_match_score'] = $score;
            $scored[] = $s;
        }
    }
    usort($scored, fn($a, $b) => $b['_match_score'] <=> $a['_match_score']);
    $filtered = $scored;
} else {
    // Normal Search & Filter
    if ($search !== '') {
        $term = mb_strtolower($search, 'UTF-8');
        $filtered = array_filter($filtered, function ($s) use ($term) {
            return str_contains(mb_strtolower($s['name'], 'UTF-8'), $term)
                || str_contains(mb_strtolower($s['description'], 'UTF-8'), $term)
                || str_contains(mb_strtolower($s['department'], 'UTF-8'), $term)
                || str_contains(mb_strtolower($s['categoryLabel'], 'UTF-8'), $term)
                || str_contains(mb_strtolower($s['stateLabel'], 'UTF-8'), $term)
                || str_contains(mb_strtolower($s['eligibility'], 'UTF-8'), $term)
                || str_contains(mb_strtolower($s['benefits'], 'UTF-8'), $term);
        });
    }

    if ($state !== '') {
        $filtered = array_filter($filtered, function ($s) use ($state) {
            if ($state === 'central') return $s['state'] === 'central';
            return $s['state'] === $state || $s['state'] === 'central';
        });
    }

    if ($category !== '') {
        $filtered = array_filter($filtered, function ($s) use ($category) {
            return $s['category'] === $category;
        });
    }

    $filtered = array_values($filtered);

    // Sorting
    if ($sort === 'name-asc') {
        usort($filtered, fn($a, $b) => strcmp($a['name'], $b['name']));
    } elseif ($sort === 'name-desc') {
        usort($filtered, fn($a, $b) => strcmp($b['name'], $a['name']));
    } elseif ($sort === 'updated') {
        usort($filtered, fn($a, $b) => strtotime($b['updated']) <=> strtotime($a['updated']));
    } else {
        // Relevance: Central schemes first, then alphabetical
        usort($filtered, function ($a, $b) {
            if ($a['state'] === 'central' && $b['state'] !== 'central') return -1;
            if ($a['state'] !== 'central' && $b['state'] === 'central') return 1;
            return strcmp($a['name'], $b['name']);
        });
    }
}

echo json_encode([
    'success' => true,
    'count' => count($filtered),
    'total' => count($masterSchemes),
    'schemes' => array_values($filtered)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

