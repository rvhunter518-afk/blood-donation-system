<?php
/**
 * Simple Pure PHP Multi-Page PDF Generator
 * Generates 100% valid, compliant PDF 1.4 documents without external libraries.
 */
class SimplePdfWriter {
    private array $pages = [];
    private string $title = '';
    private string $author = '';

    public function __construct(string $title = '', string $author = '') {
        $this->title = $title;
        $this->author = $author;
    }

    public function addPage(string $header, array $paragraphs, int $pageNum, int $totalPages, string $category): void {
        $stream = "q\n";
        // Header background banner
        $stream .= "0.92 0.94 0.98 rg\n";
        $stream .= "0 780 595 62 re f\n";

        // Accent top line
        $stream .= "0.85 0.15 0.22 RG\n";
        $stream .= "3 w\n";
        $stream .= "0 842 m 595 842 l S\n";

        // Header text: Category & LifeFlow
        $stream .= "BT\n";
        $stream .= "/F1 9 Tf\n";
        $stream .= "0.4 0.45 0.55 rg\n";
        $stream .= "40 812 Td\n";
        $stream .= "(" . $this->escape("LIFEFLOW LEARNING ACADEMY  |  " . strtoupper($category)) . ") Tj\n";
        $stream .= "ET\n";

        // Page Header Title
        $stream .= "BT\n";
        $stream .= "/F1 11 Tf\n";
        $stream .= "0.1 0.15 0.25 rg\n";
        $stream .= "40 794 Td\n";
        $stream .= "(" . $this->escape($this->title) . ") Tj\n";
        $stream .= "ET\n";

        // Section Title
        $stream .= "BT\n";
        $stream .= "/F1 18 Tf\n";
        $stream .= "0.85 0.15 0.22 rg\n";
        $stream .= "40 730 Td\n";
        $stream .= "(" . $this->escape($header) . ") Tj\n";
        $stream .= "ET\n";

        // Underline for section
        $stream .= "0.88 0.9 0.94 RG\n";
        $stream .= "1 w\n";
        $stream .= "40 720 m 555 720 l S\n";

        // Body paragraphs
        $y = 690;
        foreach ($paragraphs as $p) {
            if ($y < 120) break;
            // Check if bullet point or bold header
            $isHeading = str_starts_with($p, '## ');
            $cleanP = $isHeading ? substr($p, 3) : $p;

            $fontSize = $isHeading ? 13 : 10;
            $stream .= "BT\n";
            $stream .= "/F1 " . $fontSize . " Tf\n";
            if ($isHeading) {
                $stream .= "0.15 0.2 0.3 rg\n";
            } else {
                $stream .= "0.22 0.26 0.32 rg\n";
            }
            $stream .= "40 " . $y . " Td\n";
            $stream .= "(" . $this->escape($cleanP) . ") Tj\n";
            $stream .= "ET\n";
            $y -= ($isHeading ? 24 : 18);
        }

        // Footer line
        $stream .= "0.88 0.9 0.94 RG\n";
        $stream .= "1 w\n";
        $stream .= "40 60 m 555 60 l S\n";

        // Footer text
        $stream .= "BT\n";
        $stream .= "/F1 9 Tf\n";
        $stream .= "0.5 0.55 0.6 rg\n";
        $stream .= "40 44 Td\n";
        $stream .= "(" . $this->escape("Official Publication: " . $this->author . "  *  Authorized Educational Material") . ") Tj\n";
        $stream .= "ET\n";

        // Page Number
        $stream .= "BT\n";
        $stream .= "/F1 9 Tf\n";
        $stream .= "0.85 0.15 0.22 rg\n";
        $stream .= "500 44 Td\n";
        $stream .= "(" . $this->escape("Page " . $pageNum . " of " . $totalPages) . ") Tj\n";
        $stream .= "ET\n";

        $stream .= "Q\n";
        $this->pages[] = $stream;
    }

    private function escape(string $s): string {
        // Strip non-ASCII or high chars for standard Helvetica font encoding
        $s = preg_replace('/[^\x20-\x7E]/', ' ', $s);
        $s = str_replace('\\', '\\\\', $s);
        $s = str_replace('(', '\\(', $s);
        $s = str_replace(')', '\\)', $s);
        return $s;
    }

    public function output(): string {
        $numPages = count($this->pages);
        $offsets = [];
        $out = "%PDF-1.4\n";

        // Object 1: Catalog
        $offsets[1] = strlen($out);
        $out .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // Object 2: Pages list placeholder (fill kids)
        $kids = [];
        for ($i = 0; $i < $numPages; $i++) {
            $kids[] = (4 + $i * 2) . " 0 R";
        }
        $offsets[2] = strlen($out);
        $out .= "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . $numPages . " >>\nendobj\n";

        // Object 3: Font
        $offsets[3] = strlen($out);
        $out .= "3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        // Generate Page Objects & Content Streams
        for ($i = 0; $i < $numPages; $i++) {
            $pageObjId = 4 + $i * 2;
            $contObjId = 5 + $i * 2;

            // Page Object
            $offsets[$pageObjId] = strlen($out);
            $out .= $pageObjId . " 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents " . $contObjId . " 0 R /Resources << /Font << /F1 3 0 R >> >> >>\nendobj\n";

            // Content Object
            $stream = $this->pages[$i];
            $len = strlen($stream);
            $offsets[$contObjId] = strlen($out);
            $out .= $contObjId . " 0 obj\n<< /Length " . $len . " >>\nstream\n" . $stream . "\nendstream\nendobj\n";
        }

        // XRef Table
        $xrefOffset = strlen($out);
        $totalObjs = 3 + $numPages * 2;
        $out .= "xref\n0 " . ($totalObjs + 1) . "\n";
        $out .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $totalObjs; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        // Trailer
        $out .= "trailer\n<< /Size " . ($totalObjs + 1) . " /Root 1 0 R >>\n";
        $out .= "startxref\n" . $xrefOffset . "\n%%EOF\n";

        return $out;
    }
}

// Test writing one book
$pdf = new SimplePdfWriter("Test Book: Fundamentals of Hematology", "LifeFlow Academy");
$pdf->addPage("Chapter 1: Blood Fundamentals", [
    "## 1.1 The Cellular Composition of Human Blood",
    "Human blood is a specialized body fluid that performs critical transport and protective functions.",
    "The total blood volume in an adult human averages between 4.5 and 5.5 liters, accounting for ~7% body weight.",
    "Plasma makes up roughly 55% of total blood volume, consisting of 90% water, electrolytes, and vital proteins.",
    "Erythrocytes (red blood cells) constitute approximately 45% of blood volume, known as the hematocrit.",
    "## 1.2 Oxygen Transport and Hemoglobin Kinetics",
    "Each erythrocyte contains approximately 270 million hemoglobin molecules.",
    "Hemoglobin is a tetrameric protein composed of two alpha and two beta globin chains with heme groups.",
    "Oxygen binds reversibly to the ferrous iron (Fe2+) at the center of each protoporphyrin ring.",
    "The sigmoidal oxygen-hemoglobin dissociation curve demonstrates cooperative binding kinetics.",
    "Shift to the right (Bohr effect) occurs with increased hydrogen ion concentration, CO2, and temperature."
], 1, 3, "Hematology");

$pdf->addPage("Chapter 2: Transfusion Safety", [
    "## 2.1 Compatibility and Crossmatching",
    "Pre-transfusion testing ensures complete immunological compatibility between donor and recipient.",
    "ABO blood group system antibodies (anti-A and anti-B) are naturally occurring IgM antibodies.",
    "An acute hemolytic transfusion reaction can occur with as little as 10 to 15 mL of ABO-incompatible red cells.",
    "Immediate signs include fever, chills, hemoglobinuria, dyspnea, hypotension, and acute renal failure.",
    "## 2.2 Storage Lesion and Quality Metrics",
    "Whole blood and packed red blood cells are stored at 1 to 6 degrees Celsius in regulated preservation solutions.",
    "CPDA-1 anticoagulated blood has a licensed shelf-life of 35 days, whereas additive solutions extend to 42 days.",
    "Storage lesion changes include progressive decline in 2,3-DPG, intracellular potassium leakage, and microparticles.",
    "Stringent quality controls mandate 24-hour post-transfusion in vivo erythrocyte survival greater than 75%."
], 2, 3, "Hematology");

$pdf->addPage("Chapter 3: Summary and Review", [
    "## 3.1 Key Clinical Pearls",
    "1. Always confirm two independent patient identifiers before administering any blood component.",
    "2. O Negative uncrossmatched red blood cells should be reserved for catastrophic exsanguinating hemorrhage.",
    "3. RhD negative women of childbearing age must strictly receive RhD negative components to prevent isoimmunization.",
    "4. Post-transfusion hemoglobin typically increments by approximately 1 g/dL per unit of packed red cells.",
    "## 3.2 Completion Notice",
    "Congratulations on completing this study guide.",
    "You have fulfilled the required reading material for this topic.",
    "Proceed to the Knowledge Quiz to test your retention and earn course points and badges."
], 3, 3, "Hematology");

$content = $pdf->output();
file_put_contents(__DIR__ . '/../books/test_book.pdf', $content);
echo "SUCCESS: test_book.pdf created. Size: " . strlen($content) . " bytes\n";

