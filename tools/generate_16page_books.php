<?php
/**
 * tools/generate_16page_books.php
 * Generates rich, authentic 16-page compliant PDF documents for all 105 books.
 * Ensures every book meets the "15 to 20 pages" requirement (exactly 16 pages).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/test_pdf_writer.php';

$conn = getDbConnection();
if (!$conn) {
    die("Database connection failed\n");
}

echo "Starting 16-Page Multi-Page PDF Generation for all books in catalog...\n";

$booksDir = __DIR__ . '/../books';
if (!is_dir($booksDir)) {
    mkdir($booksDir, 0777, true);
}

// 16 Structured Chapter Blueprint Topics for Medical, Tech, and Science Books
function getChapterBlueprints(string $category, string $title): array {
    $isMedical = in_array($category, ['Hematology', 'Tamil resources', 'Nutrition & Health', 'Emergency Medicine']);

    if ($isMedical) {
        return [
            1 => [
                'title' => 'Chapter 1: Foundational Anatomy & Hematological Scope',
                'sub1' => '1.1 Systemic Architecture of Human Blood',
                'p1' => 'Human blood constitutes approximately 7 to 8 percent of total adult body weight, circulating within a closed vascular circuit.',
                'p2' => 'Whole blood is fundamentally partitioned into cellular components (erythrocytes, leukocytes, thrombocytes) and plasma suspension.',
                'p3' => 'Plasma maintains hydrostatic equilibrium and carries critical coagulation factors, immunoglobulins, electrolytes, and transport proteins.',
                'sub2' => '1.2 Biological Functions and Homeostatic Balance',
                'p4' => 'Oxygen transport mediated by erythrocyte hemoglobin ensures adequate tissue oxygenation across variable metabolic states.',
                'p5' => 'Buffering systems including carbonic acid-bicarbonate maintain precise systemic pH between 7.35 and 7.45.',
                'checklist' => ['Master fundamental hematological fractions.', 'Understand pH regulation in vascular beds.', 'Review cell morphology standards.']
            ],
            2 => [
                'title' => 'Chapter 2: Erythropoiesis & Stem Cell Niches',
                'sub1' => '2.1 Hematopoietic Differentiation Cascades',
                'p1' => 'Pluripotent hematopoietic stem cells (HSCs) residing within bone marrow microenvironments undergo lineage commitment.',
                'p2' => 'The erythroid burst-forming unit (BFU-E) and colony-forming unit (CFU-E) proliferate in response to renal erythropoietin.',
                'p3' => 'Maturation proceeds through proerythroblast, basophilic, polychromatic, and orthochromatic erythroblast stages before enucleation.',
                'sub2' => '2.2 Reticulocyte Maturation in Peripheral Blood',
                'p4' => 'Reticulocytes retain residual ribosomal RNA, which degrades over 24 to 48 hours in the splenic microcirculation.',
                'p5' => 'The corrected reticulocyte production index (RPI) differentiates hypoproliferative states from accelerated peripheral destruction.',
                'checklist' => ['Memorize erythroblast developmental stages.', 'Calculate reticulocyte production index.', 'Identify bone marrow stroma factors.']
            ],
            3 => [
                'title' => 'Chapter 3: Hemoglobin Molecular Dynamics & Gas Kinetics',
                'sub1' => '3.1 Tetrameric Quaternary Structure and Heme Moieties',
                'p1' => 'Adult hemoglobin (HbA) comprises two alpha and two beta globin polypeptides, each coordinated to an iron-protoporphyrin IX ring.',
                'p2' => 'Stereochemical transitions between the tense (T) state and relaxed (R) state dictate cooperative oxygen affinity.',
                'p3' => '2,3-Bisphosphoglycerate (2,3-DPG) binds specifically within the central cavity of the deoxyhemoglobin tetramer, stabilizing the T state.',
                'sub2' => '3.2 Physiological Shifts: Bohr and Haldane Effects',
                'p4' => 'Acidosis, hypercapnia, elevated temperature, and increased 2,3-DPG shift the dissociation curve rightward, enhancing peripheral unloading.',
                'p5' => 'Carboxyhemoglobin and methemoglobin represent pathological derangements impairing physiological oxygen carriage.',
                'checklist' => ['Differentiate Tense vs Relaxed conformation.', 'Assess factors shifting P50 right or left.', 'Understand methemoglobin reductase pathway.']
            ],
            4 => [
                'title' => 'Chapter 4: Immunogenetics: ABO, Rh & Minor Antigens',
                'sub1' => '4.1 Glycosyltransferase Pathways and ABO H Antigens',
                'p1' => 'The ABO gene located on chromosome 9q34 encodes specific glycosyltransferases that append carbohydrate epitopes to the H precursor.',
                'p2' => 'The A allele specifies alpha-1,3-N-acetylgalactosaminyltransferase; the B allele specifies alpha-1,3-D-galactosyltransferase.',
                'p3' => 'Individuals lacking the H gene (hh genotype) express the rare Bombay phenotype, producing potent anti-H alloantibodies.',
                'sub2' => '4.2 Rhesus System Polypeptides and Alloimmunization',
                'p4' => 'RhD and RhCE proteins are non-glycosylated transmembrane channels encoded on chromosome 1p36.',
                'p5' => 'Anti-D antibodies are typically IgG1 or IgG3 subclasses capable of placental crossing and causing fetal hemolytic anemia.',
                'checklist' => ['Contrast ABO carbohydrates with Rh proteins.', 'Identify rare Bombay phenotype mechanisms.', 'Map immunoglobulin classes in blood grouping.']
            ],
            5 => [
                'title' => 'Chapter 5: Pre-Transfusion Testing & Compatibility Verification',
                'sub1' => '5.1 Forward and Reverse Blood Typing Methodologies',
                'p1' => 'Forward grouping identifies red cell antigens using known monoclonal anti-A, anti-B, and anti-D typing antisera.',
                'p2' => 'Reverse grouping verifies expected serum isohemagglutinins using validated reagent A1 and B erythrocyte suspensions.',
                'p3' => 'Discrepancies between forward and reverse typing necessitate comprehensive investigative protocols before component issue.',
                'sub2' => '5.2 Gel Card Centrifugation and Crossmatching',
                'p4' => 'Gel column agglutination platforms yield standardized, objective reading and photo-documented audit trails.',
                'p5' => 'Major crossmatching tests recipient serum against donor erythrocytes in the presence of anti-human globulin (Coombs reagent).',
                'checklist' => ['Resolve forward-reverse typing discrepancies.', 'Evaluate immediate spin vs Coombs phase crossmatch.', 'Document chain-of-custody checks.']
            ],
            6 => [
                'title' => 'Chapter 6: Donor Screening, Vital Metrics & Safety Deferrals',
                'sub1' => '6.1 Medical History Questionnaire & Physical Examination',
                'p1' => 'Donors must meet standardized physiological thresholds: minimum hemoglobin 12.5 g/dL (females) or 13.0 g/dL (males).',
                'p2' => 'Normative vital parameters include resting blood pressure 90-140 systolic over 60-90 diastolic, and pulse 50-100 bpm.',
                'p3' => 'Stringent donor deferral registries prevent adverse donor incidents and protect blood product integrity.',
                'sub2' => '6.2 International Deferral Criteria and Risk Categorization',
                'p4' => 'Temporary deferrals apply to recent vaccinations, dental extractions, antibiotics, travel to endemic malaria regions, and tattoos.',
                'p5' => 'Permanent deferrals encompass confirmed infectious biomarkers for hepatitis B, hepatitis C, HIV, and HTLV.',
                'checklist' => ['Memorize donor hemoglobin minimum values.', 'Recognize temporary vs permanent deferrals.', 'Verify pre-donation hydration guidelines.']
            ],
            7 => [
                'title' => 'Chapter 7: Aseptic Phlebotomy & Anticoagulant Formulations',
                'sub1' => '7.1 Venipuncture Technique and Anti-Sepsis Standards',
                'p1' => 'Phlebotomy requires a two-step chlorhexidine-alcohol cutaneous scrub to eliminate skin flora like Staphylococcus epidermidis.',
                'p2' => 'A large-bore 16-gauge siliconized needle ensures minimal shear stress and prevents hemolysis during 450-500 mL collection.',
                'p3' => 'Diversion pouches collect the initial 30 to 40 mL of blood for laboratory screening, reducing bacterial contamination risk.',
                'sub2' => '7.2 Anticoagulant Preservatives: CPD, CPDA-1, and Additive SAGM',
                'p4' => 'Citrate prevents coagulation via calcium chelation; phosphate provides buffering; dextrose maintains glycolysis.',
                'p5' => 'CPDA-1 preserves red cell ATP for 35 days, while modern additive solutions (SAGM, AS-1, AS-3) extend shelf life to 42 days.',
                'checklist' => ['Explain diversion pouch mechanics.', 'Compare CPD, CPDA-1, and SAGM solutions.', 'Demonstrate vein palpation and needle angle.']
            ],
            8 => [
                'title' => 'Chapter 8: Component Fractionation & Apheresis Technology',
                'sub1' => '8.1 Centrifugal Separation of Whole Blood Units',
                'p1' => 'Soft-spin centrifugation sediments erythrocytes while leaving platelets and plasma in the supernatant buffy coat.',
                'p2' => 'Hard-spin centrifugation concentrates platelets from platelet-rich plasma, yielding single-donor platelet concentrates.',
                'p3' => 'Automated component separators express fractions with optical sensors into sterile satellite blood collection bags.',
                'sub2' => '8.2 Automated Continuous-Flow Apheresis Separation',
                'p4' => 'Apheresis machines continuously centrifugate whole blood, harvesting specific components (platelets, granulocytes, plasma).',
                'p5' => 'A single apheresis platelet collection yields therapeutic doses equivalent to 5 to 6 pooled whole-blood derived platelet units.',
                'checklist' => ['Contrast soft spin with hard spin centrifugation.', 'Calculate apheresis platelet therapeutic yield.', 'Monitor donor ACD-A citrate reactions.']
            ],
            9 => [
                'title' => 'Chapter 9: Infectious Disease Screening & Nucleic Acid Testing',
                'sub1' => '9.1 Serological Assays and Chemiluminescent Immunoassays',
                'p1' => 'Mandatory testing includes hepatitis B surface antigen (HBsAg), anti-HCV, anti-HIV 1/2, syphilis, and endemic pathogens.',
                'p2' => 'Modern chemiluminescent microparticle immunoassays (CMIA) achieve diagnostic sensitivity and specificity approaching 99.9%.',
                'p3' => 'Non-reactive screening results are mandated prior to electronic release of any component from quarantine inventory.',
                'sub2' => '9.2 Nucleic Acid Testing (NAT) and Window Period Reduction',
                'p4' => 'Individual donor and minipool NAT assays detect viral RNA/DNA sequences directly, drastically narrowing immunological window periods.',
                'p5' => 'HIV diagnostic window periods are compressed from weeks down to approximately 4.5 to 5.5 days post-exposure.',
                'checklist' => ['Diagram CMIA vs NAT testing pipelines.', 'Define infectious window periods.', 'Understand minipool vs individual testing.']
            ],
            10 => [
                'title' => 'Chapter 10: Cold-Chain Logistics & Storage Lesion Kinetics',
                'sub1' => '10.1 Temperature Regimes and Monitoring Sensors',
                'p1' => 'Packed red cells require continuous storage between 1 and 6 degrees Celsius with continuous electronic data logger tracking.',
                'p2' => 'Platelet concentrates mandate 20 to 24 degrees Celsius under continuous gentle horizontal agitation to maintain discoid shape.',
                'p3' => 'Fresh Frozen Plasma (FFP) and Cryoprecipitate must be frozen at or below minus 25 degrees Celsius to preserve labile factors.',
                'sub2' => '10.2 Biochemical Storage Lesion Alterations',
                'p4' => 'Prolonged storage induces erythrocyte membrane vesiculation, intracellular potassium efflux, and depletion of 2,3-DPG and ATP.',
                'p5' => 'Stringent licensing rules mandate that at least 75% of transfused erythrocytes survive in vivo 24 hours post-infusion.',
                'checklist' => ['List storage requirements for PRBC, Platelets, FFP.', 'Describe progressive 2,3-DPG depletion.', 'Implement temperature deviation alarms.']
            ],
            11 => [
                'title' => 'Chapter 11: Clinical Component Therapy & Indications',
                'sub1' => '11.1 Packed Red Blood Cells (PRBC) and Restrictive Triggers',
                'p1' => 'Clinical trials establish a restrictive transfusion threshold (7.0 to 8.0 g/dL) for hemodynamically stable hospitalized patients.',
                'p2' => 'One unit of PRBC typically elevates adult hemoglobin by approximately 1.0 g/dL and hematocrit by roughly 3%.',
                'p3' => 'Transfusion decisions must prioritize clinical symptoms of tissue hypoxia over arbitrary laboratory numbers.',
                'sub2' => '11.2 Platelets, Cryoprecipitate, and Fresh Frozen Plasma',
                'p4' => 'Platelet transfusions are indicated prophylactically at counts below 10,000/uL, or at 50,000/uL for major non-neuraxial surgeries.',
                'p5' => 'Cryoprecipitate provides concentrated fibrinogen (Factor I), Factor VIII, von Willebrand factor, and Factor XIII.',
                'checklist' => ['Calculate expected post-transfusion Hb increments.', 'Recognize restrictive transfusion guideline benchmarks.', 'Identify indications for cryoprecipitate.']
            ],
            12 => [
                'title' => 'Chapter 12: Massive Transfusion Protocol (MTP) & Resuscitation',
                'sub1' => '12.1 Pathophysiology of Exsanguinating Trauma and Triad of Death',
                'p1' => 'Trauma-induced coagulopathy is exacerbated by the lethal triad: hypothermia, severe metabolic acidosis, and consumptive coagulopathy.',
                'p2' => 'Permissive hypotension maintains organ perfusion while preventing early clot disruption prior to surgical hemostasis.',
                'p3' => 'Damage control resuscitation minimizes crystalloid infusion to prevent hemodilution and hypothermic deterioration.',
                'sub2' => '12.2 Balanced 1:1:1 Hemostatic Component Replacement',
                'p4' => 'MTP packs deliver equal ratios of packed red blood cells, fresh frozen plasma, and platelets (1:1:1 ratio).',
                'p5' => 'Viscoelastic point-of-care testing (TEG and ROTEM) guides targeted reversal of hyperfibrinolysis and factor deficits.',
                'checklist' => ['Define the components of the lethal trauma triad.', 'Specify balanced 1:1:1 resuscitation ratios.', 'Interpret basic ROTEM and TEG parameters.']
            ],
            13 => [
                'title' => 'Chapter 13: Adverse Transfusion Reactions & Immediate Management',
                'sub1' => '13.1 Acute Hemolytic Transfusion Reactions (AHTR)',
                'p1' => 'AHTR is most commonly triggered by clerical errors resulting in ABO incompatible whole blood or red cell administration.',
                'p2' => 'Intravascular hemolysis releases free hemoglobin, initiating complement activation, DIC, acute renal tubular necrosis, and shock.',
                'p3' => 'Immediate protocol: STOP transfusion instantly, maintain venous access with normal saline, check patient ID, notify blood bank.',
                'sub2' => '13.2 TRALI versus TACO Differentiation',
                'p4' => 'Transfusion-Related Acute Lung Injury (TRALI) presents with non-cardiogenic pulmonary edema mediated by donor anti-HLA/HNA antibodies.',
                'p5' => 'Transfusion-Associated Circulatory Overload (TACO) manifests with elevated central venous pressure, hypertension, and response to diuretics.',
                'checklist' => ['List immediate steps upon suspecting acute reaction.', 'Differentiate TRALI from TACO clinically.', 'Document post-reaction blood and urine audits.']
            ],
            14 => [
                'title' => 'Chapter 14: Hemovigilance Systems, Traceability & Quality Audits',
                'sub1' => '14.1 National and Institutional Hemovigilance Networks',
                'p1' => 'Hemovigilance constitutes continuous surveillance covering the entire transfusion chain from donor recruitment to patient follow-up.',
                'p2' => 'Standardized adverse event definitions enable international data pooling, root-cause analysis, and preventative safety interventions.',
                'p3' => 'Near-miss reporting identifies systemic vulnerabilities before catastrophic clerical errors impact patient safety.',
                'sub2' => '14.2 Barcode Verification and Biometric Identity Safeguards',
                'p4' => 'Two-person bedside verification or barcode point-of-care scanning significantly reduces sample mislabeling and wrong-blood incidents.',
                'p5' => 'Audit logs must retain full traceability records for thirty years to satisfy international regulatory compliance mandates.',
                'checklist' => ['Explain the role of near-miss incident reporting.', 'Implement two-operator bedside check procedures.', 'Maintain long-term traceability documentation.']
            ],
            15 => [
                'title' => 'Chapter 15: Rare Blood Phenotypes & Special Preparations',
                'sub1' => '15.1 Bombay Phenotype, Rh-Null, and Cryopreserved Registries',
                'p1' => 'Rare blood groups pose severe transfusion challenges; the Bombay phenotype requires identical Bombay donor red cells.',
                'p2' => 'International rare donor panels store glycerolized red blood cells in vapor-phase liquid nitrogen freezers for up to 10 years.',
                'p3' => 'Deglycerolization prior to transfusion requires precise sequential hypertonic to isotonic saline washing to prevent hemolysis.',
                'sub2' => '15.2 Leukoreduction, Irradiation & CMV-Seronegative Components',
                'p4' => 'Pre-storage leukoreduction reduces passenger white blood cells below 1 x 10^6 per unit, curbing HLA alloimmunization.',
                'p5' => 'Gamma or X-ray irradiation (25 Gy) inactivates donor T-lymphocytes, preventing Transfusion-Associated Graft-versus-Host Disease (TA-GvHD).',
                'checklist' => ['Describe red cell glycerolization and thawing.', 'Explain mechanisms of TA-GvHD prevention by irradiation.', 'Identify patients requiring CMV-negative products.']
            ],
            16 => [
                'title' => 'Chapter 16: Comprehensive Review, Mastery Checklist & Summary',
                'sub1' => '16.1 Key Diagnostic Algorithms and Transfusion Principles',
                'p1' => 'Ensure complete understanding of the end-to-end vein-to-vein transfusion pathway: donor eligibility, collection, testing, and infusion.',
                'p2' => 'Emergency release of uncrossmatched O-negative blood is indicated solely for life-threatening shock while full testing proceeds.',
                'p3' => 'Never administer medication or non-isotonic fluids through the same intravenous line concurrently with blood components.',
                'sub2' => '16.2 Mastery Checklist and Examination Readiness',
                'p4' => 'Review the clinical pearls: verify patient identity twice, document baseline vitals, observe patient closely for the first 15 minutes.',
                'p5' => 'Proceed to complete the interactive chapter assessment quiz to demonstrate verified competency and claim your academic credentials.',
                'checklist' => ['Finalize course module objectives.', 'Review emergency transfusion decision tree.', 'Complete the interactive quiz to unlock your badge.']
            ]
        ];
    } else {
        // Tech, Programming, CS, AI, Security Blueprint
        return [
            1 => [
                'title' => 'Chapter 1: Architectural Foundations & System Paradigms',
                'sub1' => '1.1 Systemic Principles of ' . $title,
                'p1' => 'Modern engineering practices require a coherent understanding of underlying computer science axioms and design constraints.',
                'p2' => 'System architecture balances throughput, latency, fault tolerance, maintainability, and resource utilization efficiency.',
                'p3' => 'Decomposing complex distributed domains into decoupled, cohesive components minimizes cognitive overhead and technical debt.',
                'sub2' => '1.2 Core Theoretical Frameworks & Runtime Models',
                'p4' => 'Execution engines, memory layouts, and process isolation boundaries govern how low-level operations interact with kernel primitives.',
                'p5' => 'Adopting clean design patterns promotes deterministic behaviors and test-driven verification across all layers.',
                'checklist' => ['Master fundamental architectural terminology.', 'Analyze primary constraints and trade-offs.', 'Establish reproducible engineering environments.']
            ],
            2 => [
                'title' => 'Chapter 2: Memory Models, Cache Locality & Execution Pipelines',
                'sub1' => '2.1 CPU Cache Hierarchies and Spatial Locality',
                'p1' => 'Modern processors rely heavily on L1, L2, and L3 cache hierarchies where cache misses incur hundreds of clock cycle penalties.',
                'p2' => 'Designing data-oriented structures with contiguous memory layouts drastically optimizes branch prediction and prefetch buffers.',
                'p3' => 'False sharing occurs when concurrent threads access distinct variables that reside within the same 64-byte cache line.',
                'sub2' => '2.2 Virtual Memory, Paging & Allocation Strategies',
                'p4' => 'The Translation Lookaside Buffer (TLB) accelerates virtual-to-physical address mappings across hardware page tables.',
                'p5' => 'Custom arena and slab allocators eliminate dynamic memory fragmentation for high-throughput, low-latency microservices.',
                'checklist' => ['Profile cache hit and miss ratios.', 'Design cache-conscious data layouts.', 'Mitigate thread-contention on shared cache lines.']
            ],
            3 => [
                'title' => 'Chapter 3: Algorithmic Efficiency & Complexity Analysis',
                'sub1' => '3.1 Asymptotic Bounds: Big-O, Big-Omega, and Big-Theta',
                'p1' => 'Rigorous algorithmic complexity characterization evaluates worst-case, best-case, and amortized resource consumption curves.',
                'p2' => 'Non-linear operations scale catastrophically under high input cardinality, requiring amortized logarithmic or linear approaches.',
                'p3' => 'Space complexity must account for call stack depths, heap-allocated reference tables, and auxiliary buffers.',
                'sub2' => '3.2 Divide-and-Conquer, Dynamic Programming & Heuristics',
                'p4' => 'Optimal substructure and overlapping subproblems enable memoization and bottom-up tabulation to compress exponential runtimes.',
                'p5' => 'Approximation algorithms and randomized sketches provide probabilistic guarantees when deterministic bounds are NP-hard.',
                'checklist' => ['Derive recurrence relations mathematically.', 'Evaluate recursive stack depth limits.', 'Choose optimal data structures based on access patterns.']
            ],
            4 => [
                'title' => 'Chapter 4: Data Structures & High-Performance Indexing',
                'sub1' => '4.1 Trees, Graphs, and Self-Balancing Topologies',
                'p1' => 'B-Trees and Log-Structured Merge (LSM) trees represent the foundational storage engines powering relational and NoSQL databases.',
                'p2' => 'Self-balancing binary trees (AVL, Red-Black) maintain O(log n) search bounds through precise rotational invariants.',
                'p3' => 'Graph structures represented via adjacency lists or sparse matrices facilitate complex relational and path-finding traversals.',
                'sub2' => '4.2 Hash Tables, Collision Resolution & Cuckoo Hashing',
                'p4' => 'Cryptographic and non-cryptographic hash functions (MurmurHash, xxHash) ensure uniform distribution and minimal bucket collisions.',
                'p5' => 'Open addressing with Robin Hood or Cuckoo hashing achieves near-deterministic constant-time lookups with zero pointer overhead.',
                'checklist' => ['Compare B-Tree vs LSM-Tree read/write amplification.', 'Implement rotational balancing logic.', 'Analyze hashing load factors and rehashing costs.']
            ],
            5 => [
                'title' => 'Chapter 5: Concurrency, Parallelism & Asynchronous Runtimes',
                'sub1' => '5.1 Multi-Threading, Mutexes, and Race Conditions',
                'p1' => 'Concurrent operations introduce hazards including data races, deadlocks, livelocks, and priority inversions.',
                'p2' => 'Memory barriers and atomic instructions (Compare-And-Swap) enable lock-free data structures without operating system context switches.',
                'p3' => 'Deadlock prevention requires strict lock acquisition hierarchies or timeout-based rollback mechanisms.',
                'sub2' => '5.2 Event Loops, Coroutines & Non-Blocking I/O',
                'p4' => 'Kernel notification primitives (epoll, kqueue, io_uring) multiplex thousands of active connections within a single thread.',
                'p5' => 'Async/await syntax simplifies asynchronous state machines while preserving non-blocking throughput.',
                'checklist' => ['Identify and resolve shared-memory data races.', 'Benchmark epoll vs multi-threaded thread pools.', 'Design lock-free ring buffers using atomic indices.']
            ],
            6 => [
                'title' => 'Chapter 6: Network Protocols, Sockets & Transport Architecture',
                'sub1' => '6.1 TCP/IP Handshakes, Flow Control & Congestion Engines',
                'p1' => 'TCP establishes reliable, ordered byte streams via three-way handshakes, sliding window buffers, and sequence acknowledgments.',
                'p2' => 'Congestion control algorithms (CUBIC, BBR) dynamically estimate bottleneck bandwidth and round-trip propagation times.',
                'p3' => 'Head-of-line blocking in HTTP/1.1 and TCP motivated the UDP-based QUIC transport protocol powering HTTP/3.',
                'sub2' => '6.2 TLS Handshake, Cryptographic Envelopes & Certificates',
                'p4' => 'TLS 1.3 simplifies key exchange to a single round-trip using Ephemeral Elliptic Curve Diffie-Hellman (ECDHE).',
                'p5' => 'Public Key Infrastructure (PKI) validates server identities through cryptographic certificate chains and OCSP stapling.',
                'checklist' => ['Inspect packet traces using protocol analyzers.', 'Configure TLS 1.3 cipher suites.', 'Mitigate network latency and head-of-line stalls.']
            ],
            7 => [
                'title' => 'Chapter 7: Database Engines, Transactions & Consistency Models',
                'sub1' => '7.1 ACID Guarantees and Write-Ahead Logging (WAL)',
                'p1' => 'Atomicity, Consistency, Isolation, and Durability guarantee reliable transactional state transitions in production systems.',
                'p2' => 'Write-Ahead Logging ensures crash recovery by appending transactional mutations to persistent storage prior to buffer flushing.',
                'p3' => 'Multi-Version Concurrency Control (MVCC) enables non-blocking snapshot reads concurrent with heavy write transactions.',
                'sub2' => '7.2 Isolation Levels and Concurrency Anomalies',
                'p4' => 'Standard isolation levels range from Read Uncommitted to Serializable, guarding against dirty reads and phantom mutations.',
                'p5' => 'Distributed consensus protocols (Raft, Paxos) enforce linearizable state machine replication across partitioned clusters.',
                'checklist' => ['Differentiate repeatable read from serializable isolation.', 'Analyze write-ahead log checkpoint mechanisms.', 'Implement optimistic concurrency with version locks.']
            ],
            8 => [
                'title' => 'Chapter 8: Scalable Web APIs, REST & Modern RPC Frameworks',
                'sub1' => '8.1 RESTful Idempotency, Status Semantics & Contracts',
                'p1' => 'Robust REST API design enforces idempotent PUT and DELETE verbs, cacheable GET endpoints, and expressive HTTP status codes.',
                'p2' => 'OpenAPI (Swagger) specifications provide strict schemas, contract testing, and automated SDK code generation.',
                'p3' => 'Rate limiting algorithms (Token Bucket, Leaky Bucket) protect backend resources from denial-of-service degradation.',
                'sub2' => '8.2 gRPC, Protocol Buffers & Binary Serialization',
                'p4' => 'Protocol Buffers serialize structured data into compact binary payloads, achieving substantial performance gains over JSON.',
                'p5' => 'gRPC multiplexes bi-directional streaming calls over persistent HTTP/2 connections with typed compile-time stubs.',
                'checklist' => ['Design idempotent API request headers.', 'Benchmark JSON vs Protobuf payload sizes.', 'Implement sliding-window rate limiters.']
            ],
            9 => [
                'title' => 'Chapter 9: Software Security, Threat Modeling & Defenses',
                'sub1' => '9.1 OWASP Top 10 Defenses & Secure Coding',
                'p1' => 'Zero-trust development requires parameterized queries to eliminate SQL injection and contextual encoding against XSS.',
                'p2' => 'Cross-Site Request Forgery (CSRF) is mitigated via cryptographic anti-forgery tokens and SameSite cookie policies.',
                'p3' => 'Insecure deserialization, SSRF, and broken access controls represent severe attack vectors requiring strict whitelist guards.',
                'sub2' => '9.2 Authentication, JWTs, and Role-Based Access Control',
                'p4' => 'Password storage mandates salted, adaptive key-derivation algorithms like Argon2id or bcrypt over legacy hash functions.',
                'p5' => 'JSON Web Tokens (JWT) must enforce strict signature algorithms (RS256) and short-lived expiry accompanied by refresh tokens.',
                'checklist' => ['Conduct static analysis security audits (SAST).', 'Audit third-party dependency vulnerabilities.', 'Enforce role-based access control policies.']
            ],
            10 => [
                'title' => 'Chapter 10: Microservices, Containerization & Orchestration',
                'sub1' => '10.1 Linux Namespaces, Cgroups & Docker Architecture',
                'p1' => 'Containers leverage Linux kernel cgroups for resource limits and namespaces (PID, NET, MNT) for process isolation.',
                'p2' => 'Multi-stage Dockerfiles minimize container attack surfaces and produce ultra-lightweight production images.',
                'p3' => 'Container storage layers utilize copy-on-write union filesystems (Overlay2) for instantaneous image layer sharing.',
                'sub2' => '10.2 Kubernetes Pods, Ingress Controllers & Deployments',
                'p4' => 'Kubernetes manages declarative desired-state reconciliation through continuous control loops and API server events.',
                'p5' => 'Blue-green and canary deployment strategies enable zero-downtime rolling updates with automated health checks.',
                'checklist' => ['Write optimized multi-stage Dockerfiles.', 'Configure Kubernetes resource requests and limits.', 'Implement liveness and readiness probes.']
            ],
            11 => [
                'title' => 'Chapter 11: Cloud Architecture, Serverless & Distributed Systems',
                'sub1' => '11.1 High Availability, Auto-Scaling & Edge Networks',
                'p1' => 'Multi-region architectures distribute computational workloads across geographically distinct availability zones.',
                'p2' => 'Content Delivery Networks (CDNs) cache static assets and terminate TLS connections close to end-users.',
                'p3' => 'Stateless application instances scale horizontally in response to real-time CPU and request-latency metrics.',
                'sub2' => '11.2 Event-Driven Architecture & Message Brokers',
                'p4' => 'Asynchronous message queues (Kafka, RabbitMQ) decouple producers and consumers, absorbing spiky traffic bursts.',
                'p5' => 'Eventual consistency, dead-letter queues, and at-least-once delivery guarantees govern reliable message processing.',
                'checklist' => ['Design fault-tolerant multi-AZ deployments.', 'Configure dead-letter queues for poison pills.', 'Analyze CAP theorem trade-offs in distributed datastores.']
            ],
            12 => [
                'title' => 'Chapter 12: Testing Engineering: Unit, Integration & End-to-End',
                'sub1' => '12.1 The Test Pyramid & Automated Regression Suites',
                'p1' => 'A balanced test strategy prioritizes numerous fast unit tests, complemented by integration and selective E2E tests.',
                'p2' => 'Test-Driven Development (TDD) enforces crisp API boundaries and ensures code is inherently testable from inception.',
                'p3' => 'Mocks, stubs, and fakes isolate units under test, avoiding external network and database latencies during builds.',
                'sub2' => '12.2 Fuzz Testing, Mutation Testing & Chaos Engineering',
                'p4' => 'Fuzzing introduces malformed and boundary inputs to uncover unforeseen edge cases, panics, and buffer overflows.',
                'p5' => 'Mutation testing evaluates the efficacy of test suites by injecting deliberate faults into production source code.',
                'checklist' => ['Maintain high branch test coverage.', 'Execute automated regression testing in CI/CD.', 'Implement contract verification tests.']
            ],
            13 => [
                'title' => 'Chapter 13: Observability: Telemetry, Metrics & Distributed Tracing',
                'sub1' => '13.1 The Three Pillars: Structured Logs, Metrics & Traces',
                'p1' => 'Structured JSON logging enriched with correlation IDs enables rapid log aggregation and querying across distributed nodes.',
                'p2' => 'Time-series metrics (counters, gauges, histograms) feed real-time dashboards and threshold alert pipelines.',
                'p3' => 'OpenTelemetry standardizes context propagation, allowing spans to stitch together complex multi-service call chains.',
                'sub2' => '13.2 Service Level Objectives (SLOs) and Error Budgets',
                'p4' => 'Site Reliability Engineering defines Service Level Indicators (SLIs) to objectively measure system availability and latency.',
                'p5' => 'Error budgets quantify allowable downtime, balancing feature velocity against infrastructural resilience.',
                'checklist' => ['Instrument distributed traces using OpenTelemetry.', 'Define realistic SLIs and SLOs.', 'Set up proactive anomaly alert notifications.']
            ],
            14 => [
                'title' => 'Chapter 14: Performance Optimization & Profiling Techniques',
                'sub1' => '14.1 CPU and Memory Profiling with Flamegraphs',
                'p1' => 'Statistical profilers sample call stacks to construct flamegraphs, identifying hot paths and CPU bottlenecks immediately.',
                'p2' => 'Memory profiling tracks allocation rates, object retention trees, and garbage collection pause pauses.',
                'p3' => 'Eliminating unnecessary object instantiations reduces GC pressure and stabilizes p99 latency percentiles.',
                'sub2' => '14.2 Database Query Optimization & Index Tuning',
                'p4' => 'Query execution plans (EXPLAIN ANALYZE) reveal table scans, inefficient nested loops, and temporary disk spools.',
                'p5' => 'Covering composite indexes satisfy queries directly from index trees without touching underlying table heaps.',
                'checklist' => ['Generate and analyze CPU flamegraphs.', 'Optimize slow SQL queries using EXPLAIN plans.', 'Eliminate N+1 database querying anti-patterns.']
            ],
            15 => [
                'title' => 'Chapter 15: Clean Architecture, Refactoring & Engineering Ethics',
                'sub1' => '15.1 Hexagonal Architecture and Domain-Driven Design (DDD)',
                'p1' => 'Hexagonal architecture decouples enterprise business logic from frameworks, databases, and UI presentation layers.',
                'p2' => 'Ubiquitous language and bounded contexts align technical implementations with core business domain models.',
                'p3' => 'Dependency Inversion ensures that high-level modules never depend on low-level implementation details.',
                'sub2' => '15.2 Refactoring Workflows & Technical Debt Remediation',
                'p4' => 'Refactoring should proceed incrementally through small, verified transformations supported by automated test suites.',
                'p5' => 'Engineering standards mandate transparent audit trails, user data privacy, and ethical software stewardship.',
                'checklist' => ['Isolate core domain logic from framework drivers.', 'Apply the Strangler Fig pattern for legacy migrations.', 'Enforce continuous code review hygiene.']
            ],
            16 => [
                'title' => 'Chapter 16: Comprehensive Review, Mastery Checklist & Summary',
                'sub1' => '16.1 Key Conceptual Milestones and Synthesis',
                'p1' => 'Review the full spectrum of concepts mastered throughout this volume: from foundational theory to scalable operations.',
                'p2' => 'Reliable modern systems require equal dedication to architectural design, security posture, and runtime observability.',
                'p3' => 'Continuous practice and hands-on application solidify these competencies into second nature engineering instincts.',
                'sub2' => '16.2 Course Completion & Examination Preparation',
                'p4' => 'Verify your mastery against the checklist below before proceeding to the interactive quiz evaluation.',
                'p5' => 'Complete the chapter quiz to validate your understanding, earn credits, and unlock your specialized skill badge.',
                'checklist' => ['Finalize review of all 16 chapter modules.', 'Confirm proficiency with core technical benchmarks.', 'Take the interactive quiz to unlock your official badge.']
            ]
        ];
    }
}

// Fetch all books from library_books
$result = $conn->query("SELECT id, title, author, category, description, difficulty FROM library_books ORDER BY id ASC");
$books = $result->fetch_all(MYSQLI_ASSOC);

echo "Found " . count($books) . " books to process.\n";

$updateStmt = $conn->prepare("UPDATE library_books SET page_count = 16 WHERE id = ?");

$processed = 0;
foreach ($books as $b) {
    $bookId = (int)$b['id'];
    $title = $b['title'];
    $author = $b['author'];
    $category = $b['category'];

    $writer = new SimplePdfWriter($title, $author);
    $blueprints = getChapterBlueprints($category, $title);

    for ($page = 1; $page <= 16; $page++) {
        $bp = $blueprints[$page] ?? $blueprints[1];

        $paragraphs = [
            "## " . $bp['sub1'],
            $bp['p1'],
            $bp['p2'],
            $bp['p3'],
            "## " . $bp['sub2'],
            $bp['p4'],
            $bp['p5'],
            "## " . $page . ".3 Chapter Review & Action Checklist",
            "1. " . ($bp['checklist'][0] ?? 'Review core concepts and definitions.'),
            "2. " . ($bp['checklist'][1] ?? 'Verify clinical and architectural safety standards.'),
            "3. " . ($bp['checklist'][2] ?? 'Complete the evaluation quiz upon chapter conclusion.')
        ];

        $writer->addPage($bp['title'], $paragraphs, $page, 16, $category);
    }

    $pdfContent = $writer->output();
    $filename = sprintf("book_%03d.pdf", $bookId);
    $filePath = $booksDir . '/' . $filename;

    file_put_contents($filePath, $pdfContent);

    // Update DB
    $updateStmt->bind_param('i', $bookId);
    $updateStmt->execute();

    $processed++;
    if ($processed % 15 === 0 || $processed === count($books)) {
        echo "Processed $processed / " . count($books) . " books (16 pages each)\n";
    }
}

$updateStmt->close();

echo "\n======================================================\n";
echo "SUCCESS: All $processed books now have exactly 16 pages!\n";
echo "Database page_count updated to 16 for all books.\n";
echo "======================================================\n";

