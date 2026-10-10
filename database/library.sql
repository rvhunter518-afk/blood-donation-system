USE blood_connect;

CREATE TABLE IF NOT EXISTS library_books (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(180) NULL,
    category VARCHAR(80) NOT NULL,
    description VARCHAR(500) NULL,
    pdf_url TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_library_book_title (title),
    INDEX (category)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gamification_profiles (
    user_id INT UNSIGNED PRIMARY KEY,
    points INT UNSIGNED NOT NULL DEFAULT 0,
    tier ENUM('Bronze', 'Silver', 'Gold', 'Life Saver') NOT NULL DEFAULT 'Bronze',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_gamification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS book_reads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    book_id INT UNSIGNED NOT NULL,
    first_read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_book_read (user_id, book_id),
    CONSTRAINT fk_book_reads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_book_reads_book FOREIGN KEY (book_id) REFERENCES library_books(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_completions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    quiz_key VARCHAR(80) NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_quiz (user_id, quiz_key),
    CONSTRAINT fk_quiz_completions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

DELETE FROM library_books
WHERE title IN ('The Blood Book: Understanding Donation & Lifesaving', 'Donor wellness quiz companion');

INSERT INTO library_books (title, author, category, description, pdf_url) VALUES
('The Ultimate Guide to Blood Donation', 'LifeFlow Education', 'Donation & health', 'A practical introduction to donor preparation, eligibility, and aftercare.', 'https://iris.who.int/server/api/core/bitstreams/440f386c-facc-4ac1-85d4-6a1775d9469c/content'),
('Blood Donation and Post Donation Care: A Clinical Update', 'Clinical Education Series', 'Donation & health', 'Clinical considerations for safe donation and recovery.', 'https://iris.who.int/server/api/core/bitstreams/440f386c-facc-4ac1-85d4-6a1775d9469c/content'),
('The Blood Book: Understanding Donation & Lifesaving', 'LifeFlow Education', 'Donation & health', 'Understand how every safe donation supports patients.', 'https://www.redcrossblood.org/local-homepage/news/article/blood-donation-process.html'),
('Handbook of Transfusion Medicine', 'NHS Blood and Transplant', 'Transfusion medicine', 'A reference guide to transfusion practice and patient safety.', 'https://www.transfusionguidelines.org/transfusion-handbook'),
('Blood Transfusion in Clinical Practice', 'Clinical Education Series', 'Transfusion medicine', 'Core concepts for clinical transfusion decisions.', 'https://www.who.int/health-topics/blood-transfusion-safety'),
('WHO Guidelines on Drawing Blood: Best Practices in Phlebotomy', 'World Health Organization', 'Donation & health', 'WHO best practices for drawing blood safely and respectfully.', 'https://iris.who.int/server/api/core/bitstreams/10682984-b7a0-4e4b-bd32-0cbc7d22136b/content'),
('Blood Donor Selection: Guidelines on Assessing Donor Eligibility', 'World Health Organization', 'Donation & health', 'Principles for assessing donor eligibility and protecting recipients.', 'https://iris.who.int/server/api/core/bitstreams/440f386c-facc-4ac1-85d4-6a1775d9469c/content'),
('Essential Guide to Blood Groups and Types', 'LifeFlow Education', 'Hematology', 'A clear primer on ABO and Rh blood groups.', 'https://www.nhs.uk/conditions/blood-groups/'),
('Post-Donation Recovery & Nutrition Manual', 'LifeFlow Education', 'Donation & health', 'Hydration, nutrition, rest, and when to seek clinical advice.', 'https://www.redcrossblood.org/local-homepage/news/article/blood-donation-process.html'),
('The Science of Hemoglobin: Maintaining Healthy Levels', 'Clinical Education Series', 'Hematology', 'An accessible guide to hemoglobin and healthy blood formation.', 'https://www.nhlbi.nih.gov/health/anemia'),
('Modern Blood Banking & Transfusion Practices', 'AABB Education', 'Transfusion medicine', 'A field guide to modern blood banking and transfusion systems.', 'https://www.aabb.org/education'),
('Technical Manual of the AABB (American Association of Blood Banks)', 'AABB', 'Transfusion medicine', 'Explore the official AABB technical education resources.', 'https://www.aabb.org/education'),
('Mollison''s Blood Transfusion in Clinical Medicine', 'Wiley / Clinical reference', 'Transfusion medicine', 'Publisher reference information for a foundational transfusion text.', 'https://www.wiley.com/en-us/Mollison%27s+Blood+Transfusion+in+Clinical+Medicine-p-9781119307853'),
('Clinical Hematology Made Ridiculously Simple', 'MedMaster', 'Hematology', 'A concise learning aid for core hematology concepts.', 'https://www.medmaster.net/'),
('The Gift of Life: Ethics and Practice of Voluntary Blood Donation', 'WHO / Ethics resources', 'Safety & ethics', 'Ethical foundations of voluntary, non-remunerated donation.', 'https://www.who.int/health-topics/blood-transfusion-safety'),
('Safe Blood and Blood Products: WHO Training Modules', 'World Health Organization', 'Safety & ethics', 'Training resources for safe blood and blood products.', 'https://iris.who.int/server/api/core/bitstreams/440f386c-facc-4ac1-85d4-6a1775d9469c/content'),
('Blood Component Therapy: Principles and Guidelines', 'Clinical Education Series', 'Transfusion medicine', 'How components are selected and used in patient care.', 'https://www.who.int/health-topics/blood-transfusion-safety'),
('Iron Deficiency Anemia: Prevention and Management Guide', 'National Heart, Lung, and Blood Institute', 'Hematology', 'Learn about iron deficiency, prevention, and clinical management.', 'https://www.nhlbi.nih.gov/health/anemia/iron-deficiency-anemia'),
('Donor Management & Care Standards Manual', 'AABB Education', 'Donation & health', 'Standards-informed donor care and service quality resources.', 'https://www.aabb.org/education'),
('Emergency Transfusion Protocol Handbook', 'WHO / Clinical resources', 'Transfusion medicine', 'Emergency planning and safe transfusion reference material.', 'https://www.who.int/health-topics/blood-transfusion-safety'),
('Pediatric and Neonatal Transfusion Medicine Guide', 'Clinical Education Series', 'Transfusion medicine', 'A learning guide to pediatric and neonatal transfusion topics.', 'https://www.rch.org.au/bloodtrans/'),
('இரத்த தானம்: பாதுகாப்பான வழிகாட்டி', 'LifeFlow Tamil Education', 'Tamil resources', 'இரத்த தானத்திற்கு முன், தானத்தின் போது, மற்றும் பின் கவனிக்க வேண்டியவை.', 'https://www.who.int/health-topics/blood-transfusion-safety'),
('இரத்தக் குழுக்கள் மற்றும் வகைகள்', 'LifeFlow Tamil Education', 'Tamil resources', 'ABO மற்றும் Rh இரத்தக் குழுக்களை எளிய முறையில் அறிந்து கொள்ளுங்கள்.', 'https://www.nhs.uk/conditions/blood-groups/'),
('இரத்த தானத்திற்கு முன் தயாராகுதல்', 'LifeFlow Tamil Education', 'Tamil resources', 'தானத்திற்கு முன் உடல்நலம், உணவு, நீர் மற்றும் ஓய்வு குறித்த வழிகாட்டி.', 'https://www.redcrossblood.org/local-homepage/news/article/blood-donation-process.html'),
('இரத்த தானத்திற்குப் பிந்தைய பராமரிப்பு', 'LifeFlow Tamil Education', 'Tamil resources', 'தானத்திற்குப் பிறகு பாதுகாப்பாக மீள உதவும் பராமரிப்பு குறிப்புகள்.', 'https://www.redcrossblood.org/local-homepage/news/article/blood-donation-process.html'),
('ஹீமோகுளோபின் மற்றும் இரத்த சோகை', 'LifeFlow Tamil Education', 'Tamil resources', 'ஹீமோகுளோபின் மற்றும் இரும்புச்சத்து குறைபாடு பற்றிய அறிமுகம்.', 'https://www.nhlbi.nih.gov/health/anemia'),
('இரத்த மாற்று சிகிச்சை அடிப்படைகள்', 'LifeFlow Tamil Education', 'Tamil resources', 'இரத்த மாற்று சிகிச்சையின் அடிப்படை பாதுகாப்பு கருத்துகள்.', 'https://www.who.int/health-topics/blood-transfusion-safety'),
('இரத்த தானம்: தகுதி மற்றும் பாதுகாப்பு', 'LifeFlow Tamil Education', 'Tamil resources', 'யார் இரத்தம் தானம் செய்யலாம் மற்றும் பெறுநரை எவ்வாறு பாதுகாப்பது.', 'https://iris.who.int/server/api/core/bitstreams/440f386c-facc-4ac1-85d4-6a1775d9469c/content'),
('சத்துணவு மற்றும் இரத்த ஆரோக்கியம்', 'LifeFlow Tamil Education', 'Tamil resources', 'இரத்த ஆரோக்கியத்திற்கு தேவையான உணவு மற்றும் வாழ்வியல் பழக்கங்கள்.', 'https://www.nhlbi.nih.gov/health/anemia'),
('தன்னார்வ இரத்த தானத்தின் நெறிமுறைகள்', 'LifeFlow Tamil Education', 'Tamil resources', 'தன்னார்வம், சமத்துவம் மற்றும் பாதுகாப்பான இரத்த தானத்தின் மதிப்புகள்.', 'https://www.who.int/health-topics/blood-transfusion-safety'),
('அவசர இரத்த உதவி கையேடு', 'LifeFlow Tamil Education', 'Tamil resources', 'அவசரநிலையில் மருத்துவமனை இரத்த வங்கியுடன் ஒருங்கிணைவதற்கான குறிப்புகள்.', 'https://www.who.int/health-topics/blood-transfusion-safety')
ON DUPLICATE KEY UPDATE title = VALUES(title), pdf_url = VALUES(pdf_url);

UPDATE library_books
SET pdf_url = 'https://iris.who.int/server/api/core/bitstreams/10682984-b7a0-4e4b-bd32-0cbc7d22136b/content'
WHERE title = 'WHO Guidelines on Drawing Blood: Best Practices in Phlebotomy';

DELIMITER $$
DROP TRIGGER IF EXISTS award_points_on_donation_insert$$
CREATE TRIGGER award_points_on_donation_insert
AFTER INSERT ON donations
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' THEN
        INSERT INTO gamification_profiles (user_id, points)
        SELECT d.user_id, 100 FROM donors d WHERE d.id = NEW.donor_id
        ON DUPLICATE KEY UPDATE points = points + 100,
            tier = CASE WHEN points + 100 >= 1000 THEN 'Life Saver' WHEN points + 100 >= 500 THEN 'Gold' WHEN points + 100 >= 200 THEN 'Silver' ELSE 'Bronze' END;
    END IF;
END$$

DROP TRIGGER IF EXISTS award_points_on_donation_complete$$
CREATE TRIGGER award_points_on_donation_complete
AFTER UPDATE ON donations
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND OLD.status <> 'completed' THEN
        INSERT INTO gamification_profiles (user_id, points)
        SELECT d.user_id, 100 FROM donors d WHERE d.id = NEW.donor_id
        ON DUPLICATE KEY UPDATE points = points + 100,
            tier = CASE WHEN points + 100 >= 1000 THEN 'Life Saver' WHEN points + 100 >= 500 THEN 'Gold' WHEN points + 100 >= 200 THEN 'Silver' ELSE 'Bronze' END;
    END IF;
END$$
DELIMITER ;