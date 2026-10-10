<?php
/**
 * tools/seed_medical_blood_books.php
 * Seeds 105 EXCLUSIVELY Medical, Blood, Hematology, Transfusion & Clinical Books.
 * Zero programming or computer science books!
 */

require_once __DIR__ . '/../config/database.php';
$conn = getDbConnection();

// Load the PDF writer
require_once __DIR__ . '/test_pdf_writer.php';

echo "Initializing 105 Medical & Blood-Only Books Seeder...\n";

$booksDir = __DIR__ . '/../books';
if (!is_dir($booksDir)) {
    mkdir($booksDir, 0777, true);
}

// 7 Medical Categories, 15 books each = 105 books!
$categoriesData = [
    'Blood Donation & Safety' => [
        ['The Art and Science of Voluntary Blood Donation', 'World Health Organization (WHO)', 'Beginner', 1, 20, 50, 100, 12, '15 mins', 'Comprehensive guide to voluntary non-remunerated blood donation, ethical recruitment, and public awareness.'],
        ['Donor Health Screening & Eligibility Standards', 'National Blood Transfusion Council', 'Beginner', 1, 25, 60, 120, 14, '20 mins', 'Vital assessment protocols: hemoglobin screening, blood pressure limits, medical deferral criteria, and donor safety.'],
        ['Apheresis Essentials: Platelet & Plasma Collection', 'American Association ofttt Blood Banks', 'Intermediate', 2, 30, 70, 140, 16, '25 mins', 'Mechanism of automated cell separators, plateletpheresis, plasmapheresis, and anticoagulation balance.'],
        ['Post-Donation Recovery: Physiology and Hydration', 'International Society of Blood Transfusion', 'Beginner', 1, 20, 50, 100, 10, '15 mins', 'Physiological fluid restoration, vasovagal prevention, iron repletion, and community post-care protocols.'],
        ['Overcoming Vasovagal Reactions: Clinical Prevention', 'Clinical Transfusion Society', 'Intermediate', 2, 30, 70, 140, 12, '20 mins', 'Applied muscle tension techniques, pre-donation hydration, and acute management of donor syncope.'],
        ['Community Blood Drives: Planning & Logistics', 'Red Cross Blood Services', 'Beginner', 1, 20, 50, 100, 15, '20 mins', 'Organizing college, corporate, and rural donation camps, mobile cold chain, and volunteer mobilization.'],
        ['Youth Donor Engagement & Habitual Donation', 'Global Blood Safety Initiative', 'Beginner', 1, 20, 50, 100, 11, '15 mins', 'Strategies for retaining first-time young donors into lifelong regular repeat blood donors.'],
        ['Donor Vein Care and Phlebotomy Complications', 'International Phlebotomy Association', 'Intermediate', 2, 30, 70, 140, 13, '20 mins', 'Hematoma management, nerve irritation prevention, aseptic skin preparation, and needle safety.'],
        ['Autologous Blood Donation: Principles & Indications', 'Academy of Transfusion Medicine', 'Advanced', 3, 35, 80, 160, 16, '25 mins', 'Preoperative autologous donation, acute normovolemic hemodilution, and intraoperative cell salvage.'],
        ['Informed Consent & Ethical Principles in Donation', 'Bioethics & Health Law Institute', 'Intermediate', 2, 25, 60, 120, 12, '18 mins', 'Confidential unit exclusion, privacy of screening records, and donor autonomy in modern blood banks.'],
        ['Physical Fitness, Hydration Dynamics & Blood Volume', 'Sports Medicine & Hematology Board', 'Beginner', 1, 20, 50, 100, 10, '15 mins', 'How regular exercise, hydration, and plasma volume adjustments affect athletic blood donors.'],
        ['Blood Donor Deferral Registries & Follow-up Care', 'National Health Surveillance Board', 'Intermediate', 2, 30, 70, 140, 14, '22 mins', 'Temporary vs permanent deferrals, counseling deferred donors, and pathways to reinstatement.'],
        ['Mobile Blood Collection Unit Operations', 'Emergency Disaster & Blood Response', 'Intermediate', 2, 30, 70, 140, 15, '25 mins', 'Standard operating procedures for bloodmobiles, generator power backups, and field sterile techniques.'],
        ['Donor Motivation & Social Psychology of Giving', 'Behavioral Medicine Research Center', 'Beginner', 1, 20, 50, 100, 11, '15 mins', 'Altruism, perceived risk, warm-glow effects, and behavioral nudges in humanitarian blood donation.'],
        ['Zero-Risk Blood Ecosystems: The Future of Donation', 'International Blood Safety Council', 'Advanced', 3, 40, 90, 180, 18, '30 mins', 'Artificial intelligence in donor matching, digital donor passports, and automated crossmatching.'],
    ],
    'Hematology & Blood Science' => [
        ['Erythrocytes: Structure, Function & Hemoglobin Dynamics', 'Institute of Cellular Hematology', 'Beginner', 1, 25, 60, 120, 15, '20 mins', 'The biconcave disk architecture, deformability through capillaries, gas transport, and 120-day erythrocyte lifespan.'],
        ['Hematopoiesis: The Genesis of Blood Cells', 'Stem Cell & Hematology Research Center', 'Advanced', 3, 35, 85, 170, 20, '30 mins', 'Pluripotent stem cells, myeloid vs lymphoid lineages, colony-stimulating factors, and marrow microenvironments.'],
        ['Hemoglobin: Molecular Biochemistry & Allosteric Transitions', 'Biophysical Hematology Institute', 'Intermediate', 2, 30, 70, 140, 16, '25 mins', 'Quaternary tetramer structure, Bohr effect, 2,3-DPG binding, and carbon monoxide affinity kinetics.'],
        ['Platelets & Primary Hemostasis: Vessel Wall Interactions', 'Thrombosis Research Foundation', 'Intermediate', 2, 30, 75, 150, 14, '22 mins', 'Megakaryocyte budding, platelet adhesion via von Willebrand factor, activation, and aggregation plug formation.'],
        ['The Coagulation Cascade: Intrinsic, Extrinsic & Common Pathways', 'Vascular & Coagulation Society', 'Advanced', 3, 40, 90, 180, 18, '30 mins', 'Factor activation dynamics, thrombin generation burst, fibrinogen cleavage to fibrin mesh, and fibrinolysis regulation.'],
        ['White Blood Cells: Defensive Armor of the Circulatory System', 'Immunology & Hematology Department', 'Beginner', 1, 25, 60, 120, 13, '20 mins', 'Neutrophils, lymphocytes, monocytes, eosinophils, and basophils: differential counts and diagnostic values.'],
        ['Blood Plasma: The Liquid Matrix of Life', 'Plasma Protein Therapeutics Association', 'Beginner', 1, 20, 50, 100, 12, '18 mins', 'Albumin osmotic pressure, immunoglobulins, clotting factors, transport proteins, and electrolyte equilibrium.'],
        ['Red Cell Membrane Cytoskeleton & Hereditary Spherocytosis', 'Membrane Biology Research Lab', 'Advanced', 3, 35, 80, 160, 16, '25 mins', 'Spectrin, ankyrin, band 3 complexes, osmotic fragility, and membrane stability mechanisms.'],
        ['Reticulocytes: Clinical Indicators of Marrow Activity', 'Laboratory Hematology Association', 'Intermediate', 2, 30, 70, 140, 12, '18 mins', 'Supravital staining, reticulocyte production index (RPI), and distinguishing marrow failure from hemolysis.'],
        ['Iron Metabolism: Transferrin, Ferritin & Hepcidin Axis', 'Nutritional Biochemistry Council', 'Intermediate', 2, 30, 75, 150, 15, '22 mins', 'Enterocyte absorption, cellular storage, ferroportin regulation by hepcidin, and systemic iron balance.'],
        ['Bone Marrow Microenvironment & Hematopoietic Niches', 'Stem Cell Biology Society', 'Advanced', 3, 40, 90, 180, 20, '30 mins', 'Endosteal vs perivascular niches, stromal cells, chemokines (CXCL12), and homing mechanisms.'],
        ['Eosinophils, Basophils & Mast Cells in Allergic Response', 'Allergy & Clinical Immunology Academy', 'Intermediate', 2, 25, 65, 130, 13, '20 mins', 'Degranulation biology, major basic protein, histamine release, and immune defense against parasites.'],
        ['Lymphocyte Maturation: B-Cells, T-Cells & NK Cells', 'Cellular Immunology Consortium', 'Intermediate', 2, 30, 70, 140, 16, '25 mins', 'Thymic selection, clonal diversity, humoral vs cell-mediated immunity, and memory lymphocyte formation.'],
        ['Rheology & Viscosity of Human Blood', 'Cardiovascular Biomechanics Lab', 'Advanced', 3, 35, 80, 160, 15, '22 mins', 'Non-Newtonian fluid characteristics, shear rate dependence, hematocrit impact on peripheral resistance, and microcirculation.'],
        ['Hemoglobinopathies: Genetic Variants of the Globin Chains', 'Molecular Hematology Center', 'Advanced', 3, 40, 95, 190, 18, '28 mins', 'HbS, HbC, HbE mutations, sickle cell polymers, and globin gene regulation on chromosome 11 and 16.'],
    ],
    'Blood Groups & Genetics' => [
        ['The ABO Blood Group System: Antigens, Antibodies & Inheritance', 'Karl Landsteiner Memorial Institute', 'Beginner', 1, 25, 60, 120, 14, '20 mins', 'H-antigen precursor, A and B glycosyltransferases, reciprocal antibodies, and Mendelian inheritance patterns.'],
        ['The Rh System: D-Antigen, Weak D & Partial D Variants', 'International Blood Group Reference Lab', 'Intermediate', 2, 30, 75, 150, 16, '25 mins', 'RHD and RHCE genes, immunogenicity of the D-antigen, clinical importance in transfusion and pregnancy.'],
        ['The Bombay Blood Phenotype (hh): Genetics and Compatibility', 'Rare Blood Research Syndicate', 'Advanced', 3, 40, 90, 180, 16, '25 mins', 'FUT1 mutation, lack of H substance, anti-H antibodies in serum, and universal rejection of ABO blood.'],
        ['Minor Blood Group Systems: Kell, Duffy, Kidd & MNS', 'Immunohematology Specialists Group', 'Advanced', 3, 35, 85, 170, 18, '28 mins', 'Clinically significant non-ABO antigens, delayed hemolytic transfusion reactions, and antibody identification panels.'],
        ['The Diego, Colton, Lutheran and Cartwright Systems', 'Rare Antigen Reference Laboratory', 'Advanced', 3, 40, 90, 180, 15, '22 mins', 'Membrane transport functions, ethnic distribution of Diego antigens, and rare donor registry matching.'],
        ['Hemolytic Disease of the Fetus and Newborn (HDFN)', 'Maternal-Fetal Medicine Alliance', 'Intermediate', 2, 30, 75, 150, 16, '25 mins', 'Rh incompatibility, maternal alloimmunization, anti-D immunoglobulin prophylaxis, and amniotic bilirubin monitoring.'],
        ['Crossmatching Science: Major, Minor & Electronic Matching', 'Clinical Laboratory Standards Institute', 'Intermediate', 2, 30, 70, 140, 15, '22 mins', 'Immediate-spin, indirect antiglobulin test (IAT), computerized crossmatching validation, and safety controls.'],
        ['Direct & Indirect Antiglobulin Tests (Coombs Tests)', 'Diagnostic Immunohematology Board', 'Intermediate', 2, 30, 70, 140, 13, '20 mins', 'In vivo vs in vitro sensitization, polyspecific vs monospecific anti-IgG and anti-C3d reagents.'],
        ['Antibody Titration & Identification Panels', 'Reference Blood Center Specialists', 'Advanced', 3, 35, 80, 160, 16, '25 mins', '3-cell screens, 11-cell antibody identification matrices, enzyme treatments (ficin/papain), and elution methods.'],
        ['Molecular Immunohematology: DNA Typing of Blood Groups', 'Genomic Medicine & Blood Academy', 'Advanced', 3, 40, 95, 190, 18, '28 mins', 'Polymerase chain reaction (PCR) genotyping, resolving serological discrepancies, and massively parallel sequencing.'],
        ['Blood Group Discrepancies: Resolution in the Laboratory', 'Quality Assurance in Transfusion Group', 'Advanced', 3, 35, 85, 170, 15, '22 mins', 'Forward vs reverse typing conflicts, rouleaux, cold autoantibodies, subgroup A2 with anti-A1, and elderly hypogammaglobulinemia.'],
        ['Evolutionary Biology of Human Blood Groups', 'Anthropological Genetics Department', 'Beginner', 1, 20, 50, 100, 12, '18 mins', 'Malaria resistance in Duffy-negative and sickle trait individuals, plague resistance, and global ABO distribution.'],
        ['Warm Autoantibodies & Autoimmune Hemolytic Anemia', 'Clinical Immunohematology Review', 'Advanced', 3, 35, 80, 160, 16, '24 mins', 'Pan-reactive autoantibodies, alloadsorption techniques, selecting least-incompatible blood, and steroid therapy.'],
        ['Cold Agglutinin Disease: Cryopathic Hemolysis', 'Hematology & Cryobiology Society', 'Intermediate', 2, 30, 70, 140, 14, '20 mins', 'IgM monoclonal antibodies, thermal amplitude, complement-mediated intravascular hemolysis, and blood warming protocols.'],
        ['Global Rare Blood Donor Networks & Cryo-Registries', 'International Rare Blood Registry', 'Intermediate', 2, 30, 75, 150, 15, '22 mins', 'Liquid nitrogen cryopreservation (-196C) of ultra-rare red cell units and international airlift logistics.'],
    ],
    'Transfusion Medicine' => [
        ['Component Separation: Centrifugation & Preparation Protocols', 'Blood Bank Technology Institute', 'Intermediate', 2, 30, 70, 140, 15, '22 mins', 'Heavy and light spin centrifugation, separation of PRBC, buffy coat, FFP, and cryoprecipitate from whole blood.'],
        ['Packed Red Blood Cells (PRBC): Storage, Indications & Dosing', 'Transfusion Clinical Practice Guild', 'Beginner', 1, 25, 60, 120, 14, '20 mins', 'Restricted vs liberal transfusion thresholds (7 vs 10 g/dL), shelf-life in CPDA-1/SAGM (35-42 days), and target hematocrit.'],
        ['Platelet Transfusions: Prophylaxis & Therapeutic Triggers', 'Platelet Biology Research Group', 'Intermediate', 2, 30, 75, 150, 15, '22 mins', 'Random donor platelets vs single-donor apheresis, agitation at 20-24C, corrected count increment (CCI), and refractory states.'],
        ['Fresh Frozen Plasma (FFP) & Plasma Derivatives', 'Plasma Fractionation Academy', 'Intermediate', 2, 30, 70, 140, 14, '20 mins', 'Coagulation factor replacement, INR triggers, reversal of warfarin, and differences between FFP, FP24, and solvent-detergent plasma.'],
        ['Cryoprecipitate: Fibrinogen, Factor VIII & Factor XIII', 'Hemostasis & Transfusion Board', 'Intermediate', 2, 30, 70, 140, 12, '18 mins', 'Cold-insoluble fraction extraction, dysfibrinogenemia treatment, uremic bleeding control, and calculation of replacement units.'],
        ['Leukoreduction: Prevention of Febrile Reactions & CMV', 'Quality Standards in Blood Services', 'Intermediate', 2, 25, 65, 130, 13, '20 mins', 'Pre-storage filtration, reducing HLA alloimmunization, preventing TRALI, and preventing transfusion-transmitted cytomegalovirus.'],
        ['Blood Irradiation: Preventing Transfusion-Associated GVHD', 'Radiation Medicine & Transfusion Panel', 'Advanced', 3, 35, 80, 160, 14, '22 mins', 'Gamma and X-ray irradiation (25-50 Gy), inactivation of donor T-lymphocytes, and indications for immunocompromised recipients.'],
        ['Pathogen Inactivation Technologies: Intercept & Mirasol', 'Infectious Disease & Blood Safety Unit', 'Advanced', 3, 40, 90, 180, 16, '25 mins', 'Psoralen with UVA, riboflavin with UV light, viral/bacterial/parasitic DNA disruption, and safety outcomes.'],
        ['Cold Chain Management & Temperature Monitoring in Blood Banks', 'World Health Organization Cold Chain Section', 'Beginner', 1, 25, 60, 120, 14, '20 mins', '2-6C red cell storage, -20C plasma freezers, temperature loggers, alarm calibrations, and transport cool-box standards.'],
        ['Transfusion-Transmitted Infections (TTI) Screening Protocols', 'Epidemiology & Blood Safety Center', 'Intermediate', 2, 30, 75, 150, 16, '24 mins', 'ELISA, chemiluminescence (CLIA), nucleic acid amplification testing (NAT) for HIV, HBV, HCV, syphilis, and malaria.'],
        ['Hemovigilance: Tracking & Reporting Transfusion Incidents', 'National Hemovigilance Programme', 'Intermediate', 2, 30, 70, 140, 15, '22 mins', 'Near-miss reporting, root cause analysis, international barcode verification, and continuous safety audits.'],
        ['Acute Transfusion Reactions: Diagnosis & Immediate Algorithm', 'Emergency Transfusion Medicine Board', 'Beginner', 1, 25, 65, 130, 14, '20 mins', 'Stop transfusion immediately, maintain IV access, notify blood bank, check clerical errors, and manage allergic/febrile/hemolytic events.'],
        ['TRALI vs TACO: Differential Diagnosis & Critical Care', 'Critical Care & Transfusion Taskforce', 'Advanced', 3, 40, 95, 190, 18, '28 mins', 'Transfusion-Related Acute Lung Injury vs Transfusion-Associated Circulatory Overload: BNP levels, chest radiography, and fluid balance.'],
        ['Blood Administration Sets: Filters, Warmers & Rapid Infusers', 'Critical Care Nursing Standards', 'Beginner', 1, 20, 50, 100, 12, '18 mins', '170-260 micron standard filters, blood warmers for hypothermia prevention, and rapid infuser pressure limits.'],
        ['Patient Blood Management (PBM): The Three Pillars Framework', 'International PBM Scientific Society', 'Intermediate', 2, 30, 75, 150, 16, '25 mins', 'Pillar 1: Optimizing red cell mass; Pillar 2: Minimizing blood loss; Pillar 3: Optimizing physiological reserve without allogeneic blood.'],
    ],
    'Emergency & Trauma Care' => [
        ['Massive Transfusion Protocol (MTP) in Exsanguinating Hemorrhage', 'Trauma Surgery & Resuscitation College', 'Advanced', 3, 40, 95, 190, 18, '28 mins', '1:1:1 balanced resuscitation (PRBC, FFP, Platelets), shock index triggers, tranexamic acid timing, and mortality reduction.'],
        ['Damage Control Resuscitation: Hypothermia, Acidosis & Coagulopathy', 'Critical Care Trauma Academy', 'Advanced', 3, 40, 90, 180, 17, '26 mins', 'The lethal triad in severe polytrauma, permissive hypotension, warming infused fluids, and calcium replacement.'],
        ['Thromboelastography (TEG) & ROTEM in Emergency Care', 'Point-of-Care Coagulation Society', 'Advanced', 3, 40, 95, 190, 18, '30 mins', 'Viscoelastic clot strength evaluation, reaction time (R), alpha angle, maximum amplitude (MA), and targeted factor therapy.'],
        ['Tranexamic Acid (TXA): The CRASH-2 Trial & Trauma Protocols', 'Emergency Medicine Clinical Review', 'Intermediate', 2, 30, 70, 140, 13, '20 mins', 'Antifibrinolytic mechanism, lysine binding site blockade on plasminogen, 3-hour therapeutic window, and administration dose.'],
        ['Emergency Uncrossmatched O-Negative & O-Positive Protocols', 'Emergency Department Blood Protocol Unit', 'Beginner', 1, 25, 60, 120, 12, '18 mins', 'Indications for immediate O-negative blood in women of childbearing potential, O-positive in males, and transition to crossmatched units.'],
        ['Postpartum Hemorrhage (PPH): Emergency Transfusion Guidelines', 'Obstetric Emergency Medicine Board', 'Intermediate', 2, 30, 75, 150, 16, '24 mins', 'Uterine atony management, quantitative blood loss estimation, emergency blood release, and uterine balloon tamponade.'],
        ['Pediatric Trauma Resuscitation: Weight-Based Blood Dosing', 'Pediatric Emergency Medicine Consortium', 'Intermediate', 2, 30, 75, 150, 15, '22 mins', '10-20 mL/kg dosing rules, specialized pediatric quad-packs, syringe infusion pumps, and prevention of hypocalcemia.'],
        ['Hemorrhagic Shock: Stages, Pathophysiology & Resuscitation', 'Surgical Critical Care Society', 'Intermediate', 2, 30, 70, 140, 15, '22 mins', 'Classes I through IV hemorrhage, heart rate, blood pressure, base deficit, lactate clearance, and end-organ perfusion monitoring.'],
        ['Surgical Blood Salvage: Intraoperative Cell Saver Systems', 'Surgical Technology & Transfusion Board', 'Advanced', 3, 35, 80, 160, 15, '22 mins', 'Aspiration, heparinized anticoagulation, centrifugal washing of RBCs, microaggregate filtration, and reinfusion.'],
        ['Blood Bank Disaster Response & Mass Casualty Incidents', 'Civil Defense & Disaster Health Network', 'Intermediate', 2, 30, 70, 140, 16, '24 mins', 'Triaging limited blood inventory during multi-vehicle collisions, natural disasters, and rapid replenishment pipelines.'],
        ['Pre-Hospital Blood Transfusion: Ground & Helicopter EMS', 'Air Medical & Trauma Services', 'Advanced', 3, 35, 85, 170, 16, '25 mins', 'Carrying low-titer O whole blood on rescue helicopters, portable coolers, and field transfusion verification.'],
        ['Gastrointestinal Hemorrhage: Emergency Endoscopy & Transfusion', 'Gastroenterology & Critical Care Panel', 'Intermediate', 2, 30, 70, 140, 14, '20 mins', 'Variceal vs non-variceal bleeding, octreotide, restrictive transfusion targets to prevent portal pressure spikes.'],
        ['War Surgery & Far-Forward Walking Blood Banks', 'Military Medical Corps & Defense Board', 'Advanced', 3, 40, 95, 190, 18, '28 mins', 'Fresh whole blood field donation, emergency prescreened donor registries, and combat damage control surgery.'],
        ['Coagulopathy of Trauma (ATC): Molecular Mechanisms', 'Trauma Immunology & Hemostasis Lab', 'Advanced', 3, 40, 90, 180, 17, '26 mins', 'Endothelial glycocalyx shedding, activated protein C, hyperfibrinolysis, and syndecan-1 biomarker elevation.'],
        ['Intraosseous Blood Infusion: Techniques & Pediatric Applications', 'Emergency Procedures Research Group', 'Intermediate', 2, 25, 65, 130, 12, '18 mins', 'Proximal tibia and humeral head IO access, infusion pressure dynamics, and rapid medication/blood administration.'],
    ],
    'Blood Disorders & Therapeutics' => [
        ['Iron Deficiency Anemia: Etiology, Biomarkers & Treatment', 'Clinical Hematology Educational Trust', 'Beginner', 1, 25, 60, 120, 15, '20 mins', 'Microcytic hypochromic indices, serum ferritin, transferrin saturation, oral vs intravenous iron carboxymaltose.'],
        ['Sickle Cell Disease: Vaso-Occlusion, Crises & Hydroxyurea', 'Global Sickle Cell Disease Network', 'Intermediate', 2, 30, 75, 150, 17, '25 mins', 'Polymerization of deoxygenated HbS, painful crises, acute chest syndrome, transcranial Doppler screening, and exchange transfusion.'],
        ['Thalassemia Major: Pathophysiology, Transfusion & Chelation', 'Thalassemia International Federation', 'Intermediate', 2, 30, 75, 150, 18, '26 mins', 'Ineffective erythropoiesis, regular chronic hypertransfusion protocols, iron overload toxicity, and oral chelators (deferasirox).'],
        ['Hemophilia A and B: Factor Replacement & Novel Therapies', 'World Federation of Hemophilia', 'Advanced', 3, 35, 85, 170, 16, '24 mins', 'Factor VIII and IX deficiencies, X-linked recessive genetics, prophylactic recombinant factor infusions, and emicizumab.'],
        ['Immune Thrombocytopenia (ITP): Diagnosis & Modern Management', 'Platelet Disorders Clinical Research', 'Intermediate', 2, 30, 70, 140, 15, '22 mins', 'Autoantibodies against GPIIb/IIIa, bone marrow megakaryocytes, corticosteroids, IVIG, and TPO receptor agonists (eltrombopag).'],
        ['Aplastic Anemia: Bone Marrow Failure & Immunosuppression', 'Bone Marrow Failure Disease Group', 'Advanced', 3, 40, 90, 180, 17, '26 mins', 'Pancytopenia, severe hypocellular marrow, antithymocyte globulin (ATG), cyclosporine, and allogeneic stem cell transplant.'],
        ['Megaloblastic Anemia: Vitamin B12 & Folate Deficiency', 'Nutritional Hematology Society', 'Beginner', 1, 25, 60, 120, 14, '20 mins', 'Impaired DNA synthesis, hypersegmented neutrophils, pernicious anemia, intrinsic factor antibodies, and neurological sequelae.'],
        ['Disseminated Intravascular Coagulation (DIC): Recognition & Care', 'Critical Care Coagulation Group', 'Advanced', 3, 40, 95, 190, 16, '25 mins', 'Widespread microvascular thrombosis and secondary consumption coagulopathy, D-dimer spikes, and underlying cause eradication.'],
        ['Von Willebrand Disease: Types, Laboratory Testing & DDAVP', 'Bleeding Disorders Advisory Board', 'Intermediate', 2, 30, 75, 150, 15, '22 mins', 'Type 1, 2, and 3 variants, vWF antigen, ristocetin cofactor activity, desmopressin challenge, and plasma-derived vWF/FVIII.'],
        ['Polycythemia Vera & Myeloproliferative Neoplasms', 'Oncological Hematology Consortium', 'Advanced', 3, 35, 85, 170, 16, '24 mins', 'JAK2 V617F mutation, panmyelosis, elevated hematocrit, therapeutic phlebotomy targets (<45%), and thrombotic risk reduction.'],
        ['Hemolytic Uremic Syndrome (HUS) & Thrombotic Thrombocytopenic Purpura (TTP)', 'Nephrology & Hematology Joint Board', 'Advanced', 3, 40, 95, 190, 18, '28 mins', 'ADAMTS13 deficiency, ultra-large vWF multimers, microangiopathic hemolytic anemia, schistocytes on blood smear, and plasma exchange.'],
        ['G6PD Deficiency: Favism, Oxidative Stress & Drug Triggers', 'Genetic Hematology Advisory Group', 'Beginner', 1, 20, 50, 100, 13, '18 mins', 'X-linked enzymopathy, NADPH depletion, Heinz bodies, bite cells, contraindicated medications (rasburicase, primaquine, dapsone).'],
        ['Multiple Myeloma: Plasma Cell Neoplasia & Paraproteins', 'Plasma Cell Oncology Research Group', 'Advanced', 3, 40, 90, 180, 18, '28 mins', 'CRAB criteria (hypercalcemia, renal failure, anemia, bone lesions), serum protein electrophoresis, and autologous stem cell rescue.'],
        ['Acute Myeloid Leukemia (AML): Clinical Presentation & Remission', 'Leukemia & Lymphoma Society', 'Advanced', 3, 40, 95, 190, 20, '30 mins', 'Auer rods, myeloblasts >20% in marrow, induction chemotherapy (7+3 regimen), transfusion support during aplasia, and minimal residual disease.'],
        ['Laboratory Interpretation of the Complete Blood Count (CBC)', 'Clinical Diagnostic Pathology Institute', 'Beginner', 1, 25, 65, 130, 15, '20 mins', 'Mastering red cell indices (MCV, MCH, MCHC, RDW), white cell differentials, automated scattergrams, and peripheral blood smear review.'],
    ],
    'Tamil Medical Guides' => [
        ['இரத்த தானம்: உயிர்காக்கும் முழுமையான மருத்துவ வழிகாட்டி', 'தமிழ்நாடு மாநில இரத்த பரிமாற்றக் குழு', 'Beginner', 1, 25, 60, 120, 16, '20 mins', 'தன்னார்வ இரத்த தானத்தின் அவசியம், தானத்திற்கு தகுதியானவர்கள், தானம் செய்வதற்கு முன் மற்றும் பின் கடைபிடிக்க வேண்டிய முறைகள்.'],
        ['இரத்த வகைகள் மற்றும் மரபியல் அறிவியல் அடிப்படைகள்', 'மருத்துவ மரபியல் துறை', 'Beginner', 1, 25, 60, 120, 15, '20 mins', 'ABO மற்றும் Rh இரத்தக் குழுக்கள், இரத்தக் குழுவின் மரபியல் பரிமாற்றம், மற்றும் தாய்சேய் இரத்த முரண்பாடு பற்றிய விரிவான விளக்கம்.'],
        ['இரத்த சோகை (அனீமியா) தடுப்பு முறைகள் மற்றும் சத்தான உணவு வழிகாட்டி', 'ஊட்டச்சத்து மற்றும் நலவாழ்வு இயக்கம்', 'Beginner', 1, 20, 55, 110, 14, '18 mins', 'இரும்புச்சத்து குறைபாட்டினால் ஏற்படும் இரத்த சோகை, அறிகுறிகள், கீரைகள் மற்றும் இயற்கை உணவுகளின் மூலம் ஹீமோகுளோபினை அதிகரிக்கும் வழிகள்.'],
        ['தானத்திற்கு முன் மற்றும் பின் உடலை தயார்படுத்தும் நெறிமுறைகள்', 'செஞ்சிலுவை சங்கம் தமிழ்நாடு', 'Beginner', 1, 20, 50, 100, 12, '15 mins', 'தானத்திற்கு முன் குடிநீர் பருகுதல், சத்தான உணவு உண்ணுதல், ஓய்வு எடுத்தல் மற்றும் தலைசுற்றல் வராமல் தடுக்கும் வழிகாட்டுதல்கள்.'],
        ['அவசர இரத்த மாற்று சிகிச்சை மற்றும் உயிர் பாதுகாப்பு கையேடு', 'அவசர சிகிச்சை மருத்துவ சங்கம்', 'Intermediate', 2, 30, 70, 140, 16, '22 mins', 'விபத்து மற்றும் அவசர காலங்களில் சரியான இரத்தத்தை உடனுக்குடன் நோயாளிக்கு செலுத்துவதற்கான அவசர மருத்துவ நெறிமுறைகள்.'],
        ['நவீன இரத்த வங்கிகள்: பரிசோதனை மற்றும் பாதுகாப்பான சேமிப்பு', 'மாநில நலவாழ்வு மற்றும் இரத்த வங்கி துறை', 'Intermediate', 2, 30, 75, 150, 16, '24 mins', 'நவீன பரிசோதனை முறைகள், எலிசா (ELISA), நாட் (NAT) பரிசோதனை மற்றும் 2-6 டிகிரி செல்சியஸில் பாதுகாப்பாக சேமித்து வைக்கும் நுட்பங்கள்.'],
        ['அரிய வகை இரத்தக் குழுக்கள் - பாம்பே குரூப் மரபியல் ஆய்வு', 'அரிய இரத்த வகை சர்வதேச மையம்', 'Advanced', 3, 35, 85, 170, 16, '25 mins', 'பாம்பே குரூப் (hh) இரத்த வகையின் தோற்றம், அதன் அரிய தன்மை, அவசர காலங்களில் எவ்வாறு பொருந்தும் இரத்தத்தை கண்டறிவது என்ற ஆய்வு.'],
        ['ஹீமோகுளோபின் அளவை இயற்கை முறையில் அதிகரிக்கும் மருத்துவ முறைகள்', 'சித்த மற்றும் ஊட்டச்சத்து மருத்துவ பிரிவு', 'Beginner', 1, 20, 50, 100, 13, '18 mins', 'பேரிச்சம்பழம், மாதுளை, முருங்கைக்கீரை, பீட்ரூட் ஆகியவற்றின் மூலம் ஹீமோகுளோபின் அளவை விரைவாக உயர்த்தும் இயற்கை வழிமுறைகள்.'],
        ['தன்னார்வ இரத்த தானத்தின் மகத்துவம் மற்றும் சமூக விழிப்புணர்வு', 'சமூக மருத்துவ இயக்கம்', 'Beginner', 1, 20, 50, 100, 12, '15 mins', 'ஒரு முறை இரத்தம் கொடுப்பதன் மூலம் மூன்று உயிர்களைக் காப்பாற்றும் முறை, சமூகத்தில் நிலவும் தவறான மூடநம்பிக்கைகளை உடைக்கும் விழிப்புணர்வு.'],
        ['பிளேட்லெட் தானம் மற்றும் நவீன அபெரிசிஸ் தொழில்நுட்பம்', 'ஹீமட்டாலஜி மற்றும் பிளேட்லெட் ஆய்வு மையம்', 'Intermediate', 2, 30, 75, 150, 15, '22 mins', 'டெங்கு மற்றும் புற்றுநோய் நோயாளிகளுக்கு உயிர்காக்கும் பிளேட்லெட்டுகளை மட்டும் நவீன இயந்திரங்கள் மூலம் தனியாக பிரித்து தானம் செய்யும் முறை.'],
        ['குழந்தைகள் இரத்த மாற்று சிகிச்சை மற்றும் சிறப்பு பாதுகாப்பு', 'குழந்தைகள் நல சிறப்பு மருத்துவமனை', 'Intermediate', 2, 30, 70, 140, 14, '20 mins', 'பச்சிளம் குழந்தைகள் மற்றும் சிறுவர்களுக்கு இரத்தம் ஏற்றும்போது கடைபிடிக்க வேண்டிய சிறப்பு அளவீடுகள் மற்றும் பாதுகாப்பு முறைகள்.'],
        ['தாலசீமியா மற்றும் அரிவாள் செல் இரத்த சோகை முழு விழிப்புணர்வு', 'தாலசீமியா நலச் சங்கம்', 'Intermediate', 2, 30, 75, 150, 16, '24 mins', 'மரபணு ரீதியான இரத்த நோய்கள், தொடர் இரத்த மாற்றத்தின் அவசியம், இரும்புச்சத்து தேக்கத்தை கட்டுப்படுத்தும் மருந்துகள் பற்றிய முழு வழிகாட்டி.'],
        ['இரத்த அழுத்தம் சீராக இருக்க கடைபிடிக்க வேண்டிய வாழ்வியல் முறைகள்', 'இதய மற்றும் இரத்த நாள மருத்துவ சங்கம்', 'Beginner', 1, 20, 50, 100, 13, '18 mins', 'உயர் மற்றும் குறைந்த இரத்த அழுத்தத்தை கட்டுப்படுத்தும் உணவு முறை, உடற்பயிற்சி, மன அமைதி மற்றும் உப்பு உட்கொள்ளும் அளவு.'],
        ['வைட்டமின் B12 மற்றும் ஃபோலிக் அமிலம்: நரம்பு மற்றும் இரத்த நலம்', 'மருத்துவ ஊட்டச்சத்து ஆய்வு மையம்', 'Beginner', 1, 25, 60, 120, 14, '20 mins', 'சிவப்பு ரத்த அணுக்களின் ஆரோக்கியமான வளர்ச்சிக்கு உதவும் வைட்டமின் பி12 மற்றும் ஃபோலிக் அமிலத்தின் முக்கியத்துவம் மற்றும் குறைபாடுகள்.'],
        ['மருத்துவமனை இரத்த அவசர உதவி மற்றும் ஒருங்கிணைப்பு கையேடு', 'அரசு பொது மருத்துவமனை இரத்த வங்கி', 'Intermediate', 2, 30, 70, 140, 15, '22 mins', 'மருத்துவமனையில் அனுமதிக்கப்பட்ட நோயாளிகளுக்கு தேவையான ரத்தத்தை உடனடியாக BloodConnect தளம் மூலம் ஒருங்கிணைக்கும் நெறிமுறை.'],
    ]
];

// Helper to create medical quiz questions
function generateMedicalQuiz($bookTitle, $category) {
    if (strpos($category, 'Tamil') !== false) {
        return [
            ['q' => 'ஒரு முறை இரத்தம் கொடுப்பதன் மூலம் எத்தனை உயிர்களைக் காப்பாற்ற முடியும்?', 'a' => '1 உயிர்', 'b' => '3 உயிர்கள் (சிவப்பணு, பிளேட்லெட், பிளாஸ்மா)', 'c' => '5 உயிர்கள்', 'd' => 'உயிரைக் காப்பாற்ற முடியாது', 'ans' => 'B', 'exp' => 'ஒரு யூனிட் இரத்தத்தை பிரித்து சிவப்பணு, பிளேட்லெட், பிளாஸ்மா என மூன்று வெவ்வேறு நோயாளிகளுக்கு பயன்படுத்தலாம்.'],
            ['q' => 'ஆரோக்கியமான ஒருவர் எத்தனை மாதங்களுக்கு ஒருமுறை இரத்த தானம் செய்யலாம்?', 'a' => 'ஒவ்வொரு வாரமும்', 'b' => '3 மாதங்களுக்கு ஒருமுறை', 'c' => '10 வருடங்களுக்கு ஒருமுறை', 'd' => 'வாழ்நாளில் ஒருமுறை மட்டுமே', 'ans' => 'B', 'exp' => 'ஆண்கள் 3 மாதங்களுக்கு ஒருமுறையும், பெண்கள் 4 மாதங்களுக்கு ஒருமுறையும் பாதுகாப்பாக இரத்த தானம் செய்யலாம்.'],
            ['q' => 'இரத்தத்தில் ஹீமோகுளோபின் அளவை அதிகரிக்க உதவும் மிக முக்கியமான சத்து எது?', 'a' => 'இரும்புச்சத்து (Iron)', 'b' => 'சுண்ணாம்புச்சத்து (Calcium)', 'c' => 'கொழுப்புச்சத்து (Fat)', 'd' => 'சோடியம் (Sodium)', 'ans' => 'A', 'exp' => 'இரும்புச்சத்து உடலின் ஆக்ஸிஜனை எடுத்துச்செல்லும் ஹீமோகுளோபினை உருவாக்க மிக இன்றியமையாதது.']
        ];
    }
    
    if ($category === 'Blood Donation & Safety') {
        return [
            ['q' => 'What is the standard recommended minimum hemoglobin level for a donor before donating whole blood?', 'a' => '12.5 g/dL (Females) and 13.0 g/dL (Males)', 'b' => '9.0 g/dL for everyone', 'c' => '18.0 g/dL minimum', 'd' => 'Hemoglobin is not checked', 'ans' => 'A', 'exp' => 'Standard international donor safety regulations require at least 12.5 g/dL for female donors and 13.0 g/dL for male donors.'],
            ['q' => 'How long does the human body take to restore the plasma fluid volume lost during a whole blood donation?', 'a' => 'Approximately 24 to 48 hours with adequate hydration', 'b' => 'Six months', 'c' => 'Instantaneous within 10 seconds', 'd' => 'Over three years', 'ans' => 'A', 'exp' => 'Plasma fluid is replenished within 24-48 hours when the donor consumes fluids, while red cells regenerate over 4-8 weeks.'],
            ['q' => 'What is the most effective immediate countermeasure for a donor experiencing a mild vasovagal reaction?', 'a' => 'Lie flat with feet elevated (Trendelenburg position) and apply muscle tension', 'b' => 'Run for 15 minutes', 'c' => 'Stand up immediately in bright sun', 'd' => 'Fast for 24 hours', 'ans' => 'A', 'exp' => 'Elevating the legs promotes venous return to the heart and brain, restoring cerebral perfusion and preventing syncope.']
        ];
    }

    if ($category === 'Hematology & Blood Science') {
        return [
            ['q' => 'What is the normal average biological lifespan of a mature human erythrocyte (red blood cell)?', 'a' => 'Approximately 120 days', 'b' => '7 days', 'c' => '5 years', 'd' => '24 hours', 'ans' => 'A', 'exp' => 'Erythrocytes circulate for roughly 120 days before being sequestered and recycled by macrophages in the spleen and liver.'],
            ['q' => 'Which cell type in the bone marrow produces blood platelets via cytoplasmic fragmentation?', 'a' => 'Megakaryocyte', 'b' => 'Lymphocyte', 'c' => 'Osteoblast', 'd' => 'Fibroblast', 'ans' => 'A', 'exp' => 'Megakaryocytes undergo endomitosis and extend cytoplasmic proplatelets that fragment into circulating platelets.'],
            ['q' => 'Which hormone produced predominantly by the kidneys stimulates red blood cell production in the marrow?', 'a' => 'Erythropoietin (EPO)', 'b' => 'Insulin', 'c' => 'Thyroxine', 'd' => 'Adrenaline', 'ans' => 'A', 'exp' => 'Erythropoietin is released in response to cellular hypoxia and signals bone marrow erythroid progenitor cells.']
        ];
    }

    if ($category === 'Blood Groups & Genetics') {
        return [
            ['q' => 'What specific precursor carbohydrate substance is absent in individuals with the rare Bombay blood phenotype (hh)?', 'a' => 'H antigen precursor', 'b' => 'A antigen only', 'c' => 'Albumin', 'd' => 'Dextrose', 'ans' => 'A', 'exp' => 'Bombay phenotype individuals lack the FUT1 enzyme, cannot synthesize H antigen, and produce potent anti-H antibodies in serum.'],
            ['q' => 'Which blood group is commonly designated as the universal red blood cell donor in emergency medicine?', 'a' => 'O Negative (O RhD negative)', 'b' => 'AB Positive', 'c' => 'B Positive', 'd' => 'A Negative', 'ans' => 'A', 'exp' => 'O negative red cells lack A, B, and RhD antigens, minimizing the risk of acute hemolytic reaction in uncrossmatched emergencies.'],
            ['q' => 'What type of immunoglobulins are typical naturally occurring ABO isohemagglutinins (Anti-A and Anti-B)?', 'a' => 'Predominantly IgM antibodies', 'b' => 'IgE antibodies', 'c' => 'IgD antibodies only', 'd' => 'Lipoproteins', 'ans' => 'A', 'exp' => 'Naturally occurring anti-A and anti-B antibodies are predominantly IgM, capable of activating complement and causing intravascular hemolysis.']
        ];
    }

    if ($category === 'Transfusion Medicine') {
        return [
            ['q' => 'What is the approved international temperature range for storing Packed Red Blood Cells (PRBC)?', 'a' => 'Between +2°C and +6°C in monitored refrigerators', 'b' => 'At room temperature (+22°C)', 'c' => '-80°C standard freezer', 'd' => '+37°C body temperature incubator', 'ans' => 'A', 'exp' => 'PRBC units must be maintained strictly between +2°C and +6°C to preserve red cell viability and prevent bacterial proliferation.'],
            ['q' => 'Why must donor platelet concentrates be constantly agitated during storage at +20°C to +24°C?', 'a' => 'To facilitate oxygen exchange and prevent platelet aggregation and activation', 'b' => 'To warm them up', 'c' => 'To crush the platelets', 'd' => 'To colorize the plasma', 'ans' => 'A', 'exp' => 'Continuous gentle agitation maintains aerobic metabolism, prevents pH drop below 6.2, and prevents platelet clumping.'],
            ['q' => 'What is the primary clinical indication for leukoreduction (leukocyte filtration) of blood components?', 'a' => 'To prevent febrile non-hemolytic transfusion reactions, HLA alloimmunization, and CMV transmission', 'b' => 'To increase blood volume', 'c' => 'To change blood type', 'd' => 'To lower blood price', 'ans' => 'A', 'exp' => 'Removing donor white blood cells prevents recipient cytokine activation, HLA antibody generation, and cytomegalovirus infection.']
        ];
    }

    if ($category === 'Emergency & Trauma Care') {
        return [
            ['q' => 'In a modern Massive Transfusion Protocol (MTP), what balanced component ratio is standardly targeted?', 'a' => '1:1:1 ratio of PRBC to FFP to Platelets', 'b' => '10 units of saline only', 'c' => '5:1 PRBC to glucose only', 'd' => 'Platelets only with no red cells', 'ans' => 'A', 'exp' => 'Damage control resuscitation uses a balanced 1:1:1 ratio of PRBC, plasma, and platelets to treat traumatic coagulopathy.'],
            ['q' => 'What is the crucial therapeutic window for administering Tranexamic Acid (TXA) following severe trauma hemorrhage?', 'a' => 'Within 3 hours of the injury', 'b' => 'After 2 weeks', 'c' => 'Only after 24 hours', 'd' => 'Only during discharge', 'ans' => 'A', 'exp' => 'The landmark CRASH-2 trial demonstrated that TXA reduces mortality when administered within 3 hours of traumatic bleeding.'],
            ['q' => 'What constitutes the lethal triad in severe polytrauma and exsanguinating hemorrhage?', 'a' => 'Hypothermia, Acidosis, and Coagulopathy', 'b' => 'Fever, hypertension, and tachycardia', 'c' => 'High calcium, low sodium, and glucose', 'd' => 'Anemia, jaundice, and arthritis', 'ans' => 'A', 'exp' => 'Hypothermia, metabolic acidosis, and traumatic coagulopathy synergistically destroy clotting ability and cause rapid mortality.']
        ];
    }

    // Default: Blood Disorders & Therapeutics
    return [
        ['q' => 'Which iron biomarker is considered the most reliable indicator of total body storage iron?', 'a' => 'Serum Ferritin', 'b' => 'Blood glucose', 'c' => 'Serum creatinine', 'd' => 'Uric acid', 'ans' => 'A', 'exp' => 'Serum ferritin directly reflects intracellular iron stores in the liver and reticuloendothelial system.'],
        ['q' => 'What is the primary genetic defect that causes Sickle Cell Disease?', 'a' => 'A point mutation substituting valine for glutamic acid at position 6 of the beta-globin chain', 'b' => 'Loss of chromosome 21', 'c' => 'Lack of vitamin C', 'd' => 'Excessive iron absorption', 'ans' => 'A', 'exp' => 'The HbS mutation encodes hydrophobic valine, which causes hemoglobin molecules to polymerize upon deoxygenation.'],
        ['q' => 'In a Complete Blood Count (CBC), what does the Mean Corpuscular Volume (MCV) measure?', 'a' => 'The average physical volume and size of individual red blood cells in femtoliters (fL)', 'b' => 'Total blood volume in the body', 'c' => 'Number of platelets', 'd' => 'Blood pressure', 'ans' => 'A', 'exp' => 'MCV reflects erythrocyte size (normal 80-100 fL), differentiating microcytic (e.g. iron deficiency) from macrocytic anemias.']
    ];
}

// Clear out old books, progress, and quizzes to have a fresh, pristine medical catalog
echo "Clearing existing non-medical books and related tables...\n";
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("TRUNCATE TABLE quiz_questions");
$conn->query("TRUNCATE TABLE quiz_attempts");
$conn->query("TRUNCATE TABLE book_progress");
$conn->query("TRUNCATE TABLE library_books");
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

$bookIdCounter = 1;

foreach ($categoriesData as $categoryName => $booksList) {
    foreach ($booksList as $bookInfo) {
        $id = $bookIdCounter++;
        $title = $bookInfo[0];
        $author = $bookInfo[1];
        $difficulty = $bookInfo[2];
        $level = $bookInfo[3];
        $lowPoints = $bookInfo[4];
        $medPoints = $bookInfo[5];
        $highPoints = $bookInfo[6];
        $pages = $bookInfo[7];
        $time = $bookInfo[8];
        $desc = $bookInfo[9];

        $pdfRelPath = 'books/book_' . str_pad($id, 3, '0', STR_PAD_LEFT) . '.pdf';
        $pdfAbsPath = __DIR__ . '/../' . $pdfRelPath;

        // Clean non-ascii for PDF Helvetica font
        $safeTitle = preg_replace('/[^\x20-\x7E]/', '', $title);
        if (trim($safeTitle) === '') {
            $safeTitle = "BloodConnect Medical Guide Vol " . $id;
        }
        $safeAuthor = preg_replace('/[^\x20-\x7E]/', '', $author);
        if (trim($safeAuthor) === '') {
            $safeAuthor = "LifeFlow Clinical Hematology Group";
        }
        $safeCategory = preg_replace('/[^\x20-\x7E]/', '', $categoryName);
        $safeDesc = preg_replace('/[^\x20-\x7E]/', '', $desc);
        if (trim($safeDesc) === '') {
            $safeDesc = "Essential clinical guide covering hematology, blood donation, emergency transfusion protocols, and patient safety.";
        }

        // Generate genuine multi-page medical PDF with SimplePdfWriter
        $pdf = new SimplePdfWriter($safeTitle, $safeAuthor);

        // Page 1: Overview & Abstract
        $pdf->addPage(
            "Overview & Clinical Abstract",
            [
                "## Clinical Overview & Objectives",
                $safeDesc,
                "This educational volume is published by BloodConnect for healthcare workers, blood donors, and clinical coordinators.",
                "## Key Clinical Points:",
                "* Evidence-based guidelines for donor selection, blood processing, and transfusion triggers.",
                "* Rigorous cold-chain maintenance (2-6 deg C for PRBC, -20 deg C for Fresh Frozen Plasma).",
                "* Immunologic compatibility, antibody screening, and crossmatching protocols.",
                "* Recognition and immediate management of adverse transfusion reactions.",
                "## Knowledge Scoring:",
                "- Low Score (<50%): " . $lowPoints . " Points  |  +10 XP",
                "- Medium Score (50-79%): " . $medPoints . " Points  |  +30 XP",
                "- High Score (80-100%): " . $highPoints . " Points  |  +75 XP (Perfect: +100 XP)",
            ],
            1,
            3,
            $safeCategory
        );

        // Page 2: Core Physiology & Principles
        $pdf->addPage(
            "Core Hematology & Physiology",
            [
                "## 1.1 Cellular Blood Physiology",
                "Human blood comprises approximately 7% to 8% of adult body weight (5 to 6 liters in average adults).",
                "Erythrocytes are biconcave non-nucleated disks optimized for gas exchange and peripheral capillary transit.",
                "Hemoglobin tetramers bind four oxygen molecules with positive cooperativity, driven by the partial pressure of O2.",
                "## 1.2 Clinical Blood Conservation",
                "Patient Blood Management (PBM) emphasizes preserving the patient's own red cell mass whenever possible.",
                "Restricting unindicated transfusions prevents volume overload, TRALI, and alloimmunization.",
                "* Routine hemoglobin threshold for stable hospitalized patients: 7.0 g/dL.",
                "* Cardiovascular or symptomatic patients: 8.0 g/dL threshold.",
                "* Hemorrhagic shock: immediate resuscitation with balanced blood components."
            ],
            2,
            3,
            $safeCategory
        );

        // Page 3: Emergency Protocol & Quality Safety
        $pdf->addPage(
            "Emergency Action & Hemovigilance",
            [
                "## 2.1 Acute Transfusion Emergency Protocol",
                "If an acute transfusion reaction is suspected during infusion:",
                "1. STOP THE TRANSFUSION IMMEDIATELY and disconnect the blood line.",
                "2. Maintain IV access with a new infusion set running 0.9% Normal Saline.",
                "3. Perform immediate bedside checks: patient ID band, unit label, and crossmatch tag.",
                "4. Notify the blood bank laboratory and attending physician immediately.",
                "5. Send the blood bag, administration set, and fresh post-transfusion blood and urine samples.",
                "## 2.2 Hemovigilance Reporting",
                "All near-miss events and adverse transfusion incidents must be reported through the BloodConnect Hemovigilance Portal.",
                "Digital tracking guarantees tracing from donor vein to recipient bedside."
            ],
            3,
            3,
            $safeCategory
        );

        file_put_contents($pdfAbsPath, $pdf->output());

        // Insert into library_books
        $stmt = $conn->prepare("INSERT INTO library_books (id, title, author, category, description, difficulty, level, low_points, medium_points, high_points, estimated_reading_time, page_count, pdf_path, published_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("isssssiiiisis", $id, $title, $author, $categoryName, $desc, $difficulty, $level, $lowPoints, $medPoints, $highPoints, $time, $pages, $pdfRelPath);
        $stmt->execute();
        $stmt->close();

        // Insert 3 Medical Quiz Questions
        $quizQuestions = generateMedicalQuiz($title, $categoryName);
        foreach ($quizQuestions as $q) {
            $qStmt = $conn->prepare("INSERT INTO quiz_questions (book_id, question, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $qStmt->bind_param("issssssss", $id, $q['q'], $q['q'], $q['a'], $q['b'], $q['c'], $q['d'], $q['ans'], $q['exp']);
            $qStmt->execute();
            $qStmt->close();
        }

        echo "Seeded Book #$id [$categoryName]: $title\n";
    }
}

echo "\n=======================================================\n";
echo "SUCCESS! 105 EXCLUSIVELY MEDICAL & BLOOD BOOKS SEEDED!\n";
echo "All 315 medical quiz questions and physical multi-page PDFs created!\n";
echo "=======================================================\n";
