<?php
/**
 * CLSN LMS : First-Run Setup Script
 * Run this once to create tables, seed data, and an admin account.
 * DELETE THIS FILE after setup is complete.
 */

// ─── Configuration ────────────────────────────────────────────────────────────
$dbHost     = 'localhost';
$dbUser     = 'root';
$dbPass     = '';         // Change if your MySQL root has a password
$dbName     = 'clsn_lms';

$adminFirst = 'CLSN';
$adminLast  = 'Admin';
$adminEmail = 'admin@candlelightspecialneeds.org';
$adminPass  = 'Admin@CLSN2026!'; // Change this after setup!
// ──────────────────────────────────────────────────────────────────────────────

$errors = [];
$done   = [];

// Step 1: Connect (without selecting a database yet)
$conn = new mysqli($dbHost, $dbUser, $dbPass);
if ($conn->connect_error) {
    die('<div style="font-family:sans-serif;padding:40px;color:red"><h2>MySQL Connection Failed</h2><p>' . htmlspecialchars($conn->connect_error) . '</p></div>');
}
$conn->set_charset('utf8mb4');

// Step 2: Create database
if ($conn->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    $done[] = '<i class="fas fa-check-circle text-green-600 mr-1"></i> Database <strong>' . $dbName . '</strong> created / verified.';
} else {
    $errors[] = '<i class="fas fa-times-circle text-red-600 mr-1"></i> Could not create database: ' . $conn->error;
}

$conn->select_db($dbName);

// Step 3: Create tables from schema SQL
$schemaFile = __DIR__ . '/database/schema.sql';
if (file_exists($schemaFile)) {
    $sql = file_get_contents($schemaFile);
    // Remove the CREATE DATABASE / USE statements (already handled above)
    $sql = preg_replace('/CREATE DATABASE.*?;/si', '', $sql);
    $sql = preg_replace('/USE\s+`?clsn_lms`?\s*;/i', '', $sql);
    // Execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            if (!$conn->query($stmt)) {
                $errors[] = '<i class="fas fa-times-circle text-red-600 mr-1"></i> SQL Error: ' . $conn->error . '<br><code>' . htmlspecialchars(substr($stmt, 0, 120)) . '...</code>';
            }
        }
    }
    $done[] = '<i class="fas fa-check-circle text-green-600 mr-1"></i> All database tables created / verified.';
} else {
    $errors[] = '<i class="fas fa-times-circle text-red-600 mr-1"></i> Schema file not found at database/schema.sql';
}

// Step 4: Seed autism course data
// Only seed if courses table is empty
$result = $conn->query("SELECT COUNT(*) AS cnt FROM lms_courses");
$row    = $result->fetch_assoc();
if ((int)$row['cnt'] === 0) {
    seedAutismCourse($conn, $done, $errors);
} else {
    $done[] = '<i class="fas fa-info-circle text-blue-500 mr-1"></i> Course data already exists, skipping seed.';
}

// Step 5: Create admin user
$check = $conn->prepare("SELECT id FROM lms_users WHERE email = ?");
$check->bind_param('s', $adminEmail);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
    $ins  = $conn->prepare("INSERT INTO lms_users (first_name, last_name, email, password, role) VALUES (?,?,?,?,'admin')");
    $ins->bind_param('ssss', $adminFirst, $adminLast, $adminEmail, $hash);
    if ($ins->execute()) {
        $done[] = '<i class="fas fa-check-circle text-green-600 mr-1"></i> Admin account created: <strong>' . $adminEmail . '</strong> / <strong>' . $adminPass . '</strong>';
    } else {
        $errors[] = '<i class="fas fa-times-circle text-red-600 mr-1"></i> Could not create admin: ' . $ins->error;
    }
    $ins->close();
} else {
    $done[] = '<i class="fas fa-info-circle text-blue-500 mr-1"></i> Admin user already exists, skipping.';
}
$check->close();
$conn->close();

// ─── Seed Function ─────────────────────────────────────────────────────────────
function seedAutismCourse(mysqli $conn, array &$done, array &$errors): void {
    // Insert course
    $stmt = $conn->prepare("INSERT INTO lms_courses (title, slug, short_description, description, is_free, total_modules, duration, level, instructor) VALUES (?,?,?,?,1,8,'8 Weeks','Beginner','Candlelight Foundation')");
    $title = 'Understanding Autism: A Comprehensive Training Course';
    $slug  = 'understanding-autism';
    $short = 'A structured 8-week course providing in-depth knowledge about Autism Spectrum Disorder for parents, caregivers, and educators.';
    $desc  = '<p>This comprehensive course equips parents, caregivers, educators, and healthcare professionals with a thorough understanding of Autism Spectrum Disorder (ASD). Over 8 structured modules, you will gain practical knowledge, evidence-based strategies, and actionable tools.</p>';
    $stmt->bind_param('ssss', $title, $slug, $short, $desc);
    $stmt->execute();
    $courseId = $conn->insert_id;
    $stmt->close();
    $done[] = '<i class="fas fa-check-circle text-green-600 mr-1"></i> Autism course inserted (ID: ' . $courseId . ').';

    // Module data: [module_number, title, description, notes, duration_minutes]
    $modules = [
        [1, 'Introduction to Autism Spectrum Disorder',
         'An overview of what autism is, its history, and how it is defined in modern medicine.',
         '<h2>What is Autism Spectrum Disorder?</h2><p>Autism Spectrum Disorder (ASD) is a complex neurodevelopmental condition characterized by challenges with social communication, restricted interests, and repetitive behaviors. The term "spectrum" reflects the wide variation in challenges and strengths each person with autism possesses.</p><h3>Key Historical Milestones</h3><ul><li><strong>1943:</strong> Leo Kanner first described autism in children</li><li><strong>1944:</strong> Hans Asperger described a milder variant</li><li><strong>1980:</strong> Autism added to the DSM-III</li><li><strong>2013:</strong> DSM-5 unified all subtypes under "Autism Spectrum Disorder"</li></ul><h3>The Spectrum</h3><p>Autism affects individuals differently. Some may need significant support, while others live independently. The spectrum includes variations in social communication, sensory sensitivities, behavioral patterns, and cognitive abilities.</p><h3>Prevalence</h3><p>According to the CDC, approximately <strong>1 in 36 children</strong> in the United States has been identified with ASD. It is more common in boys than girls (approximately 4:1 ratio).</p>',
         45],
        [2, 'Early Signs and Diagnosis of Autism',
         'Learn to identify early warning signs and understand the diagnostic process for autism.',
         '<h2>Recognizing Early Signs of Autism</h2><p>Early identification of autism is crucial for accessing interventions that can significantly improve outcomes. Many signs can be observed before a child\'s second birthday.</p><h3>Red Flags by Age</h3><h4>By 12 Months:</h4><ul><li>No babbling or pointing</li><li>No back-and-forth gestures (waving, reaching)</li><li>Does not respond to name</li></ul><h4>By 18 Months:</h4><ul><li>No single words spoken</li><li>Loss of previously acquired language</li></ul><h4>By 24 Months:</h4><ul><li>No two-word spontaneous phrases</li><li>Unusual toy play patterns</li></ul><h3>The Diagnostic Process</h3><ol><li><strong>Developmental Screening</strong> : M-CHAT during routine check-ups</li><li><strong>Comprehensive Evaluation</strong> : multidisciplinary team assessment</li><li><strong>Diagnostic Tools</strong> : ADOS-2, ADI-R, CARS</li></ol><h3>Why Early Diagnosis Matters</h3><p>Research consistently shows that early intervention (especially before age 5) leads to significantly better outcomes in communication, social skills, and adaptive behavior.</p>',
         50],
        [3, 'Communication Strategies for Children with Autism',
         'Explore evidence-based communication strategies including AAC, PECS, and verbal prompting.',
         '<h2>Communication in Autism</h2><p>Communication challenges are a core characteristic of autism, ranging from complete absence of verbal speech to subtle conversational difficulties.</p><h3>Types of Communication Challenges</h3><ul><li><strong>Verbal:</strong> Delayed speech, echolalia, scripted speech</li><li><strong>Non-verbal:</strong> Difficulty with gestures, facial expressions, and eye contact</li><li><strong>Pragmatic:</strong> Challenges with social use of language (turn-taking, topic maintenance)</li></ul><h3>AAC : Augmentative and Alternative Communication</h3><p>AAC encompasses all methods that supplement or replace speech:</p><ul><li><strong>Low-tech:</strong> Picture exchange, communication boards, sign language</li><li><strong>High-tech:</strong> Speech generating devices (SGDs), Proloquo2Go app</li></ul><h3>PECS : Picture Exchange Communication System</h3><p>PECS teaches communication using pictures across 6 progressive phases, from exchanging a single picture to complex commenting.</p><h3>Key Communication Strategies</h3><ul><li>Use simple, direct language</li><li>Allow processing time before expecting a response</li><li>Pair verbal instructions with visual supports</li><li>Acknowledge all communication attempts</li><li>Create communication-rich environments</li></ul>',
         55],
        [4, 'Applied Behavior Analysis (ABA) and Behavioral Interventions',
         'Understand the science behind ABA therapy and how it is applied to support children with autism.',
         '<h2>What is Applied Behavior Analysis (ABA)?</h2><p>ABA is a scientific approach to understanding behavior and how it is affected by the environment. It is the most evidence-based intervention for autism, endorsed by the American Academy of Pediatrics.</p><h3>The ABC Model</h3><ul><li><strong>Antecedent</strong> : What happens BEFORE the behavior</li><li><strong>Behavior</strong> : The action itself</li><li><strong>Consequence</strong> : What happens AFTER the behavior</li></ul><h3>Core ABA Techniques</h3><h4>Discrete Trial Training (DTT)</h4><p>Structured, repetitive teaching sessions breaking skills into small components. Highly effective for early learners.</p><h4>Natural Environment Teaching (NET)</h4><p>Learning within natural daily routines, improving generalization of skills.</p><h4>Positive Reinforcement</h4><p>Providing meaningful rewards immediately following desired behaviors to increase their frequency.</p><h4>Prompting and Fading</h4><p>Providing assistance to help perform a behavior, then gradually reducing it.</p><h3>Modern ABA : Person-Centered Approach</h3><p>Contemporary ABA prioritizes assent-based therapy, naturalistic teaching, quality of life outcomes, and caregiver involvement.</p>',
         60],
        [5, 'Sensory Processing and Sensory Integration',
         'Understand sensory processing differences in autism and learn strategies to support sensory regulation.',
         '<h2>Sensory Processing in Autism</h2><p>Up to 90% of individuals with autism experience sensory processing differences that can significantly impact daily functioning, behavior, and learning.</p><h3>The 8 Sensory Systems</h3><ol><li><strong>Visual</strong> : Sight</li><li><strong>Auditory</strong> : Sound</li><li><strong>Tactile</strong> : Touch</li><li><strong>Olfactory</strong> : Smell</li><li><strong>Gustatory</strong> : Taste</li><li><strong>Vestibular</strong> : Balance and movement</li><li><strong>Proprioceptive</strong> : Body position and pressure</li><li><strong>Interoceptive</strong> : Internal body signals</li></ol><h3>Hyper vs. Hyposensitivity</h3><ul><li><strong>Hypersensitivity:</strong> Over-responsive to sensory input (covering ears, avoiding textures)</li><li><strong>Hyposensitivity:</strong> Under-responsive, seeks intense sensory experiences (spinning, chewing)</li></ul><h3>Sensory-Supportive Strategies</h3><ul><li>Use noise-canceling headphones in noisy environments</li><li>Provide a quiet sensory retreat space</li><li>Use visual schedules to reduce anxiety</li><li>Offer sensory diet activities (proprioceptive input, calming strategies)</li></ul><h3>Sensory Integration Therapy</h3><p>Developed by occupational therapist Jean Ayres, SI therapy uses structured, playful activities to help the brain process sensory information more efficiently.</p>',
         50],
        [6, 'Social Skills Development',
         'Practical strategies and programs for developing social skills in children with autism.',
         '<h2>Social Skills and Autism</h2><p>Unlike children who naturally absorb social rules, children with autism often need explicit instruction to understand and navigate social situations.</p><h3>Key Social Skills to Target</h3><ul><li>Joint attention (sharing focus on objects/events)</li><li>Turn-taking in conversation and play</li><li>Reading facial expressions and body language</li><li>Initiating and maintaining conversations</li><li>Understanding personal space and boundaries</li><li>Managing emotions in social contexts</li></ul><h3>Evidence-Based Programs</h3><h4>Social Stories™ (Carol Gray)</h4><p>Short personalized narratives describing social situations and appropriate responses, helping individuals understand "the hidden curriculum."</p><h4>PEERS® (UCLA)</h4><p>A research-supported program teaching concrete social skills through structured lessons and behavioral rehearsal.</p><h4>Video Modeling</h4><p>Using videos to demonstrate appropriate social behaviors for observation and imitation.</p><h3>The Role of Play</h3><p>Play is the natural vehicle for social learning. Structured play groups and facilitated peer interactions build social competence.</p>',
         55],
        [7, 'Supporting Families and Caregivers',
         'Understand the challenges families face and how to build effective support systems.',
         '<h2>The Family Journey with Autism</h2><p>A diagnosis of autism affects the entire family. Parents, siblings, and extended family all need support, understanding, and practical tools.</p><h3>The Emotional Journey</h3><p>Many families experience a grief cycle following diagnosis: shock, guilt, anger, bargaining, depression, and eventually acceptance and advocacy. These are normal responses : there is no "right" way to feel.</p><h3>Impact on Siblings</h3><ul><li>Feelings of being overlooked</li><li>Social challenges explaining autism to peers</li><li>Development of empathy and resilience</li></ul><h3>Caregiver Training Strategies</h3><ul><li><strong>NDBI</strong> : Naturalistic Developmental Behavioral Intervention</li><li><strong>Hanen : More Than Words</strong> program for parents of young children</li><li><strong>Parent-Implemented Intervention (PII)</strong></li></ul><h3>Building Your Support Network</h3><ul><li>Connect with parent support groups</li><li>Seek respite care services</li><li>Advocate within your child\'s school system</li><li>Practice self-care : you cannot pour from an empty cup</li></ul>',
         60],
        [8, 'Transition to Adulthood and Future Planning',
         'Prepare families for the transition from childhood services to adult life for individuals with autism.',
         '<h2>Transitioning to Adulthood</h2><p>The transition from childhood to adulthood is one of the most significant periods for individuals with autism and their families. Planning ahead is essential.</p><h3>Key Transition Domains</h3><h4>Education</h4><ul><li>IEP transition planning begins at age 16 in the US</li><li>Options include vocational training, community college, supported higher education</li></ul><h4>Employment</h4><ul><li>Supported employment and job coaching</li><li>Vocational rehabilitation programs</li><li>Identifying autism-friendly employers</li></ul><h4>Independent Living</h4><ul><li>Continuum from fully independent to group homes</li><li>Daily living skills training (cooking, budgeting, hygiene)</li></ul><h4>Healthcare Transition</h4><ul><li>Moving from pediatric to adult healthcare providers</li><li>Mental health support (anxiety and depression are common in autistic adults)</li></ul><h3>Legal and Financial Planning</h3><ul><li>Special Needs Trust</li><li>ABLE Accounts (Achieving a Better Life Experience)</li><li>Benefits planning (SSI, Medicaid)</li></ul><h3>The Neurodiversity Movement</h3><p>Many autistic adults embrace their identity and advocate for acceptance and accommodation rather than a "cure." The neurodiversity movement emphasizes autism as a natural variation in human neurology.</p>',
         65],
    ];

    $modStmt = $conn->prepare("INSERT INTO lms_modules (course_id, module_number, title, description, notes, duration_minutes, sort_order) VALUES (?,?,?,?,?,?,?)");
    $moduleIds = [];
    foreach ($modules as $m) {
        $modStmt->bind_param('iisssii', $courseId, $m[0], $m[1], $m[2], $m[3], $m[4], $m[0]);
        $modStmt->execute();
        $moduleIds[$m[0]] = $conn->insert_id;
    }
    $modStmt->close();
    $done[] = '<i class="fas fa-check-circle text-green-600 mr-1"></i> 8 modules inserted.';

    // Insert PDFs (placeholders : admin uploads real files)
    $pdfStmt = $conn->prepare("INSERT INTO lms_module_pdfs (module_id, title, file_path, file_size) VALUES (?,?,?,?)");
    $pdfTitles = [
        1 => ['Week 1 Workbook : Introduction to ASD', 'uploads/pdfs/week1-workbook.pdf', '2.4 MB'],
        2 => ['Week 2 Workbook : Early Signs & Diagnosis', 'uploads/pdfs/week2-workbook.pdf', '1.8 MB'],
        3 => ['Week 3 Workbook : Communication Strategies', 'uploads/pdfs/week3-workbook.pdf', '3.1 MB'],
        4 => ['Week 4 Workbook : ABA Therapy Guide', 'uploads/pdfs/week4-workbook.pdf', '2.7 MB'],
        5 => ['Week 5 Workbook : Sensory Processing', 'uploads/pdfs/week5-workbook.pdf', '2.2 MB'],
        6 => ['Week 6 Workbook : Social Skills Activities', 'uploads/pdfs/week6-workbook.pdf', '3.5 MB'],
        7 => ['Week 7 Workbook : Family Support Guide', 'uploads/pdfs/week7-workbook.pdf', '2.0 MB'],
        8 => ['Week 8 Workbook : Transition Planning', 'uploads/pdfs/week8-workbook.pdf', '4.1 MB'],
    ];
    foreach ($pdfTitles as $modNum => $pdf) {
        $mid = $moduleIds[$modNum];
        $pdfStmt->bind_param('isss', $mid, $pdf[0], $pdf[1], $pdf[2]);
        $pdfStmt->execute();
    }
    $pdfStmt->close();
    $done[] = '<i class="fas fa-check-circle text-green-600 mr-1"></i> PDF resource records inserted.';

    // Insert Quizzes + Questions + Options
    $quizData = getQuizData();
    $quizStmt = $conn->prepare("INSERT INTO lms_quizzes (module_id, title, pass_percentage, max_attempts) VALUES (?,?,70,3)");
    $qStmt    = $conn->prepare("INSERT INTO lms_quiz_questions (quiz_id, question_text, sort_order) VALUES (?,?,?)");
    $oStmt    = $conn->prepare("INSERT INTO lms_quiz_options (question_id, option_text, is_correct, sort_order) VALUES (?,?,?,?)");

    foreach ($quizData as $modNum => $quiz) {
        $mid = $moduleIds[$modNum];
        $quizStmt->bind_param('is', $mid, $quiz['title']);
        $quizStmt->execute();
        $quizId = $conn->insert_id;

        foreach ($quiz['questions'] as $qi => $q) {
            $sortQ = $qi + 1;
            $qStmt->bind_param('isi', $quizId, $q['question'], $sortQ);
            $qStmt->execute();
            $questionId = $conn->insert_id;

            foreach ($q['options'] as $oi => $opt) {
                $sortO   = $oi + 1;
                $correct = (int)$opt[1];
                $oStmt->bind_param('isii', $questionId, $opt[0], $correct, $sortO);
                $oStmt->execute();
            }
        }
    }
    $quizStmt->close();
    $qStmt->close();
    $oStmt->close();
    $done[] = '<i class="fas fa-check-circle text-green-600 mr-1"></i> Quizzes, questions, and answer options inserted.';
}

function getQuizData(): array {
    return [
        1 => [
            'title' => 'Module 1 Quiz: Introduction to ASD',
            'questions' => [
                ['question' => 'What does ASD stand for?', 'options' => [['Attention Span Disorder', 0], ['Autism Spectrum Disorder', 1], ['Autistic Social Deficit', 0], ['Attention Sensitivity Disorder', 0]]],
                ['question' => 'In what year did Leo Kanner first describe autism in children?', 'options' => [['1930', 0], ['1943', 1], ['1955', 0], ['1970', 0]]],
                ['question' => 'According to the CDC, approximately how many children in the US are identified with ASD?', 'options' => [['1 in 100', 0], ['1 in 250', 0], ['1 in 36', 1], ['1 in 500', 0]]],
                ['question' => 'What is the approximate diagnosis ratio of boys to girls with autism?', 'options' => [['2 boys for every 1 girl', 0], ['4 boys for every 1 girl', 1], ['Equal between boys and girls', 0], ['6 boys for every 1 girl', 0]]],
                ['question' => 'Which DSM edition unified all autism subtypes under a single diagnosis?', 'options' => [['DSM-III (1980)', 0], ['DSM-IV (1994)', 0], ['DSM-5 (2013)', 1], ['DSM-5-TR (2022)', 0]]],
                ['question' => 'Which of the following is a core characteristic of Autism Spectrum Disorder?', 'options' => [['Above-average intelligence', 0], ['Challenges with social communication and restricted/repetitive behaviors', 1], ['Hyperactivity and impulsivity', 0], ['Reading and writing difficulties', 0]]],
                ['question' => 'The word "spectrum" in Autism Spectrum Disorder refers to:', 'options' => [['Only children with severe autism', 0], ['Autism affecting only verbal communication', 0], ['The wide variation in challenges and strengths among individuals', 1], ['Only three severity levels', 0]]],
            ],
        ],
        2 => [
            'title' => 'Module 2 Quiz: Early Signs and Diagnosis',
            'questions' => [
                ['question' => 'What is the M-CHAT used for?', 'options' => [['Measuring height and weight', 0], ['Screening for autism during routine pediatric check-ups', 1], ['Testing academic readiness', 0], ['Diagnosing ADHD in toddlers', 0]]],
                ['question' => 'By what age is no single words spoken considered a red flag for autism?', 'options' => [['By 6 months', 0], ['By 18 months', 1], ['By 3 years', 0], ['By 5 years', 0]]],
                ['question' => 'Which of the following is a red flag for autism by 12 months?', 'options' => [['Walking independently', 0], ['Not responding to their name', 1], ['Not eating solid foods', 0], ['Preferring one type of toy', 0]]],
                ['question' => 'What does ADOS-2 stand for?', 'options' => [['Autism Diagnostic Observation Schedule, Second Edition', 1], ['Autism Developmental Observation System', 0], ['Autistic Disorder Observation Scale', 0], ['Advanced Diagnostic Observation Survey', 0]]],
                ['question' => 'Why is early diagnosis of autism important?', 'options' => [['To access financial benefits faster', 0], ['Early intervention leads to significantly better outcomes', 1], ['To get into special school faster', 0], ['Parents need a diagnosis to get help', 0]]],
                ['question' => 'Which professional is typically NOT part of an autism diagnostic team?', 'options' => [['Developmental pediatrician', 0], ['Speech-language pathologist', 0], ['Dentist', 1], ['Child psychologist', 0]]],
            ],
        ],
        3 => [
            'title' => 'Module 3 Quiz: Communication Strategies',
            'questions' => [
                ['question' => 'What does AAC stand for?', 'options' => [['Alternative and Adaptive Communication', 0], ['Augmentative and Alternative Communication', 1], ['Autism and Communication', 0], ['Audio and Auditory Communication', 0]]],
                ['question' => 'How many phases does the PECS system have?', 'options' => [['3 phases', 0], ['4 phases', 0], ['6 phases', 1], ['8 phases', 0]]],
                ['question' => 'Echolalia is best described as:', 'options' => [['Difficulty expressing emotions', 0], ['Repetition of words or phrases heard from others', 1], ['Using sign language instead of speech', 0], ['Selective mutism', 0]]],
                ['question' => 'Which of the following is an example of high-tech AAC?', 'options' => [['Communication board', 0], ['Sign language', 0], ['Picture cards', 0], ['Speech generating device (SGD)', 1]]],
                ['question' => 'What is a key principle when giving verbal instructions to a child with autism?', 'options' => [['Speak as fast as possible', 0], ['Allow adequate processing time before expecting a response', 1], ['Repeat loudly if not followed', 0], ['Use complex vocabulary to expand learning', 0]]],
                ['question' => 'Pragmatic language refers to:', 'options' => [['Grammar and sentence structure', 0], ['Vocabulary size', 0], ['The social use of language (conversation rules, turn-taking)', 1], ['Reading comprehension', 0]]],
                ['question' => 'Proloquo2Go is an example of:', 'options' => [['A social story application', 0], ['A behavior tracking app', 0], ['A tablet-based AAC app for speech generation', 1], ['A diagnostic screening tool', 0]]],
            ],
        ],
        4 => [
            'title' => 'Module 4 Quiz: ABA Therapy',
            'questions' => [
                ['question' => 'What does ABA stand for?', 'options' => [['Alternative Behavioral Assessment', 0], ['Applied Behavior Analysis', 1], ['Autism Behavioral Approach', 0], ['Advanced Behavioral Assistance', 0]]],
                ['question' => 'In the ABC model, what does "A" stand for?', 'options' => [['Action', 0], ['Antecedent', 1], ['Attitude', 0], ['Analysis', 0]]],
                ['question' => 'What is Discrete Trial Training (DTT)?', 'options' => [['Teaching through free play in natural settings', 0], ['Video-based social modeling', 0], ['Structured, repetitive teaching that breaks skills into small steps', 1], ['Group therapy with peers', 0]]],
                ['question' => 'Positive reinforcement works by:', 'options' => [['Punishing negative behaviors', 0], ['Providing rewards after desired behaviors to increase their frequency', 1], ['Ignoring all behaviors', 0], ['Withholding attention from the child', 0]]],
                ['question' => 'What is "prompting" in ABA?', 'options' => [['Physically guiding a child through a task only', 0], ['Providing assistance to help perform a behavior, then gradually reducing it', 1], ['Asking a child repeatedly to complete a task', 0], ['Modeling the behavior on video', 0]]],
                ['question' => 'Which organization certifies behavior analysts?', 'options' => [['APA (American Psychological Association)', 0], ['IBCCES', 0], ['BACB (Behavior Analyst Certification Board)', 1], ['CDC', 0]]],
            ],
        ],
        5 => [
            'title' => 'Module 5 Quiz: Sensory Processing',
            'questions' => [
                ['question' => 'How many sensory systems does the human body have?', 'options' => [['5', 0], ['6', 0], ['7', 0], ['8', 1]]],
                ['question' => 'What is hypersensitivity in sensory processing?', 'options' => [['Under-responsiveness to sensory input', 0], ['Over-responsiveness to sensory input', 1], ['Normal sensory processing', 0], ['Seeking intense sensory experiences', 0]]],
                ['question' => 'The vestibular system relates to:', 'options' => [['Internal body signals like hunger', 0], ['Body position and pressure', 0], ['Balance and movement', 1], ['Smell and taste', 0]]],
                ['question' => 'Proprioception refers to:', 'options' => [['The ability to smell and taste', 0], ['Balance and coordination', 0], ['Body position awareness and pressure', 1], ['Internal body signals', 0]]],
                ['question' => 'Who developed Sensory Integration (SI) therapy?', 'options' => [['Leo Kanner', 0], ['Jean Ayres', 1], ['B.F. Skinner', 0], ['Temple Grandin', 0]]],
                ['question' => 'What percentage of individuals with autism experience sensory processing differences?', 'options' => [['About 30%', 0], ['About 50%', 0], ['About 70%', 0], ['Up to 90%', 1]]],
            ],
        ],
        6 => [
            'title' => 'Module 6 Quiz: Social Skills Development',
            'questions' => [
                ['question' => 'Who developed Social Stories™?', 'options' => [['Temple Grandin', 0], ['Carol Gray', 1], ['Lorna Wing', 0], ['B.F. Skinner', 0]]],
                ['question' => 'What does PEERS® stand for?', 'options' => [['Program for the Education and Enrichment of Relational Skills', 1], ['Planning Effective Educational Relationships for Students', 0], ['Peer Education Enrichment and Relational System', 0], ['Practical Education for the Enrichment of Relationships', 0]]],
                ['question' => 'Joint attention involves:', 'options' => [['Refusing to look at others', 0], ['Sharing focus on objects or events with another person', 1], ['Only pointing at desired objects', 0], ['Avoiding all shared activities', 0]]],
                ['question' => 'Video modeling is used to:', 'options' => [['Replace speech therapy', 0], ['Demonstrate appropriate social behaviors for observation and imitation', 1], ['Record problematic behaviors', 0], ['Create visual schedules', 0]]],
                ['question' => 'Which of the following is NOT typically a target social skill for children with autism?', 'options' => [['Turn-taking in conversation', 0], ['Reading facial expressions', 0], ['Advanced calculus skills', 1], ['Understanding personal space', 0]]],
                ['question' => 'The "hidden curriculum" refers to:', 'options' => [['The school curriculum for reading and math', 0], ['Unwritten social rules and expectations most people learn implicitly', 1], ['Hidden resources in the classroom', 0], ['A child\'s private thoughts and feelings', 0]]],
            ],
        ],
        7 => [
            'title' => 'Module 7 Quiz: Family Support',
            'questions' => [
                ['question' => 'Which of the following is a normal emotional response to an autism diagnosis?', 'options' => [['Only anger and frustration', 0], ['Only denial and sadness', 0], ['A range including shock, guilt, grief, and eventual acceptance', 1], ['Immediate acceptance without emotional struggle', 0]]],
                ['question' => 'How might siblings of a child with autism be affected?', 'options' => [['Siblings are never significantly affected', 0], ['Siblings may feel overlooked or experience social challenges', 1], ['Siblings always develop autism themselves', 0], ['Siblings naturally become caregivers without support needed', 0]]],
                ['question' => 'What is the Hanen "More Than Words" program designed for?', 'options' => [['Therapists working in clinical settings', 0], ['Parents of young children with autism to support communication', 1], ['Educators in mainstream classrooms', 0], ['Typically developing children', 0]]],
                ['question' => 'Respite care services provide:', 'options' => [['Financial assistance for therapy', 0], ['Temporary relief for primary caregivers', 1], ['Legal representation for families', 0], ['Academic tutoring for siblings', 0]]],
                ['question' => 'What does NDBI stand for?', 'options' => [['Non-Directive Behavioral Instruction', 0], ['National Disability Behavior Initiative', 0], ['Naturalistic Developmental Behavioral Intervention', 1], ['Neurological Development Behavior Index', 0]]],
            ],
        ],
        8 => [
            'title' => 'Module 8 Quiz: Transition to Adulthood',
            'questions' => [
                ['question' => 'At what age does IEP transition planning typically begin in the United States?', 'options' => [['Age 10', 0], ['Age 14', 0], ['Age 16', 1], ['Age 18', 0]]],
                ['question' => 'What is a Special Needs Trust?', 'options' => [['A savings account managed by the individual', 0], ['A legal financial tool that holds assets without affecting disability benefits', 1], ['A government grant program', 0], ['A type of health insurance', 0]]],
                ['question' => 'What does ABLE Account stand for?', 'options' => [['Autism Benefits and Learning Experience', 0], ['Achieving a Better Life Experience', 1], ['Advanced Benefits for Life Enrichment', 0], ['Autism-Based Learning and Employment', 0]]],
                ['question' => 'What is "supported decision-making" in the context of transition to adulthood?', 'options' => [['Complete legal guardianship over the adult', 0], ['An alternative to guardianship where the individual makes decisions with trusted support', 1], ['A school-based transition skills program', 0], ['A government-appointed decision maker', 0]]],
                ['question' => 'The neurodiversity movement primarily advocates for:', 'options' => [['Finding a cure for autism', 0], ['Segregating autistic individuals for their protection', 0], ['Acceptance and accommodation of autism as a natural neurological variation', 1], ['Reducing autism diagnoses through early intervention', 0]]],
                ['question' => 'Which of the following is a financial support program for adults with disabilities in the US?', 'options' => [['Medicare Part D', 0], ['Supplemental Security Income (SSI)', 1], ['Social Security Retirement', 0], ['COBRA Health Insurance', 0]]],
            ],
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CLSN LMS Setup</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-8">
<div class="max-w-2xl w-full bg-white rounded-3xl shadow-xl p-10">
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-orange-100 mb-4">
            <svg class="w-8 h-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 3 8 3s8-.79 8-3V7M4 7c0 2.21 3.582 3 8 3s8-2.79 8-3M4 7c0-2.21 3.582 3 8 3s8-2.79 8-3"></path></svg>
        </div>
        <h1 class="text-3xl font-bold text-gray-900">CLSN LMS Setup</h1>
        <p class="text-gray-500 mt-2">Candlelight Foundation Learning Management System</p>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 rounded-2xl p-6 mb-6">
        <h3 class="text-red-700 font-bold mb-3"><i class="fas fa-times-circle mr-1"></i> Errors Encountered</h3>
        <ul class="space-y-2 text-red-600 text-sm">
            <?php foreach ($errors as $e): ?>
            <li><?= $e ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="bg-green-50 border border-green-200 rounded-2xl p-6 mb-8">
        <h3 class="text-green-700 font-bold mb-3">Setup Log</h3>
        <ul class="space-y-2 text-sm text-gray-700">
            <?php foreach ($done as $d): ?>
            <li><?= $d ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if (empty($errors)): ?>
    <div class="bg-orange-50 border border-orange-200 rounded-2xl p-6 mb-8">
        <h3 class="text-orange-700 font-bold mb-2"><i class="fas fa-exclamation-triangle mr-1"></i> Security Notice</h3>
        <p class="text-sm text-orange-700">Setup complete. <strong>Delete this file</strong> (setup.php) from your server immediately.</p>
        <p class="text-sm text-orange-700 mt-2">Admin Login: <strong><?= htmlspecialchars($adminEmail) ?></strong> / <strong><?= htmlspecialchars($adminPass) ?></strong></p>
    </div>
    <div class="flex gap-4">
        <a href="index.php" class="flex-1 text-center px-6 py-3 bg-orange-500 text-white rounded-xl font-semibold hover:bg-orange-600 transition">Go to LMS Home →</a>
        <a href="admin/index.php" class="flex-1 text-center px-6 py-3 bg-gray-800 text-white rounded-xl font-semibold hover:bg-gray-900 transition">Admin Panel →</a>
    </div>
    <?php else: ?>
    <a href="setup.php" class="block text-center px-6 py-3 bg-red-500 text-white rounded-xl font-semibold hover:bg-red-600 transition">Retry Setup</a>
    <?php endif; ?>
</div>
</body>
</html>
