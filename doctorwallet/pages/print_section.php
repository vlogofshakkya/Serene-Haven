<?php
require_once '../config.php';
requireLogin();

$user = getCurrentUser();
$userType = $_SESSION['user_type'];

// Only doctors can access print section
if ($userType !== 'doctor') {
    header('Location: ../index.php');
    exit();
}

$doctorId = $user['id'];

// Fetch doctor's signature
$stmt = $pdo->prepare("SELECT file_path FROM doctor_images WHERE doctor_id = ? AND image_type = 'signature' AND is_active = 1");
$stmt->execute([$doctorId]);
$doctorSignature = $stmt->fetch();

// Fetch dosage types from database
$stmt = $pdo->prepare("SELECT code, description FROM dosage_types WHERE is_active = 1 ORDER BY id");
$stmt->execute();
$dosageTypes = $stmt->fetchAll();

// Fetch patients for this doctor
$stmt = $pdo->prepare("
    SELECT 'adult' as type, id, name, phone_number, nic_number, allergies, birthday, age 
    FROM adults WHERE doctor_id = ? 
    UNION ALL 
    SELECT 'kid' as type, k.id, k.name, a.phone_number, NULL as nic_number, k.allergies, k.birthday, k.age
    FROM kids k 
    JOIN adults a ON k.parent_id = a.id 
    WHERE k.doctor_id = ?
    ORDER BY name
");
$stmt->execute([$doctorId, $doctorId]);
$patients = $stmt->fetchAll();

// Medicine list for autocomplete (from your examples file)
$medicineList = [
    "Co. Amoxiclav(Augmentin)-375mg",
    "Co. Amoxiclav(Curam) - 375mg",
    "Co. Amoxiclav(Augmentin)-625mg",
    "Co. Amoxiclav(Curam) - 625mg",
    "Syrup Co. Amoxiclav(Augmentin)",
    "Syrup Co. Amoxiclav(Curam)",
    "Amoxicillin(Amoxil) - 250mg",
    "Amoxicillin(Amoxil) - 500mg",
    "Syrup Amoxicillin(Amoxil)",
    "Clarithromycin(Claritec) - 250mg",
    "Clarithromycin(Claritec) - 500mg",
    "Syrup Clarithromycin(Claritec)",
    "Azithromycin(Azilet) -250mg",
    "Azithromycin(Azilet) -500mg",
    "Syrup Azithromycin(Azilet)",
    "Cefuroxime(Zinnat) - 250mg",
    "Cefuroxime(Zinnat) - 500mg",
    "Syrup Cefuroxime(Zinnat)",
    "Cephalexin(Sporidox) - 250mg",
    "Cephalexin(Sporidox) - 500mg",
    "Syrup Cephalexin(Sporidox)",
    "Ciprofloxacin(Ciprobid) - 250mg",
    "Ciprofloxacin(Ciprobid) - 500mg",
    "Levofloxacin(Leflox) - 250mg",
    "Levofloxacin(Leflox) - 500mg",
    "Cefixime(Rite.o.cef) - 200mg",
    "Cefixime(Taxim-o) - 100mg",
    "Pantoprazole(Ultop) - 20mg",
    "Pantoprazole(Ultop) - 40mg",
    "Pantoprazole(pantocix) - 20mg",
    "Pantoprazole(pantocix) - 40mg",
    "Ranitidine(Ranntac) - 150mg",
    "Famotidine(Famocid) - 20mg",
    "Esomeprazole(Nexpro) - 20mg",
    "Esomeprazole(Nexpro) - 40mg",
    "Syrup Gelusil",
    "Syrup Sucralfate",
    "Syrup Cremaffin",
    "Syrup Simethicone(Gasmed)",
    "Syrup Simethicone",
    "Syrup Lactulose",
    "Pyloocain Cream",
    "2% Lignocain gel",
    "Fusidil acid (Foban) Cream",
    "Fusidil acid ointment",
    "Fusidil acid intertulle",
    "Terbinafine cream",
    "2%Ketoconazole(Mycoral) cream",
    "Clotrimazole(candid) cream",
    "Clotrimazole(candid v6) c.tablet - 100mg",
    "Fluconazole(Flucon) - 150mg",
    "Griseofulvin(Fulvin) - 500mg",
    "Pevison Cream",
    "Pevaryl cream",
    "Syrup Theophylline",
    "Syrup Deriphyllin",
    "Syrup Terbutaline",
    "Terbutaline - 2.5mg",
    "Syrup Phenicof",
    "Syrup Phenycof Junior",
    "Syrup Libitus",
    "Syrup Tusq D",
    "Syrup Tusq X",
    "Solvin - 2mg",
    "Solvin - 10mg",
    "Betahistidine - 16mg",
    "Betahistidine - 8mg",
    "Acyclevir - 200mg",
    "Acyclevir - 400mg",
    "Acyclevir - 800mg",
    "Syrup Acyclevir",
    "Acyclevir cream",
    "0.1% Betamethasone cream",
    "0.1% Betamethasone ointment",
    "Betaleb-N cream",
    "Disclofenac sodium(Jonad) gel",
    "Diclofenac sodium suppositarient - 100mg",
    "Diclofenac sodium suppositarient - 50mg",
    "Diclofenac sodium suppositarient - 25mg",
    "Domperidone suppositarien - 60mg",
    "Domperidone suppositarien - 30mg",
    "Domperidone suppositarien - 10mg",
    "Syrup Domperidone",
    "Domperidone(Domstal)",
    "Ondansetran - 4mg",
    "Mebaverine - 135mg",
    "Mosapride(Mosid) - 5mg",
    "1xCholecaciferol - 0.25mg",
    "CaCO3 - 500mg",
    "CaCO3 - 1g",
    "Kalzona - 500mg",
    "Vit D3 (D.max) 2000u",
    "Vit D3 (D.max) 1000u",
    "Grovit Multivitamin Syrup",
    "Zincovit Multivitamin Syrup",
    "Zincovit Multivitamin Capsules",
    "Neurobion vitamin tablets",
    "Bezinc Multivitamin Capsules",
    "Glysoft Soap",
    "Glysoft cream",
    "Acnil Soap",
    "Acnil facewash",
    "Psoratar soap",
    "Ketoplus Shampoo",
    "1% Clindamycin gel(Aclin)",
    "5% Benzoyl Peroxide(Pernex AC)",
    "Diciofenae Sodium CR(Subcyde CR) - 100mg",
    "Celecoxib(CeloxR) - 200mg",
    "Celecoxib(CeloxR) - 100mg",
    "Gabapentin - 100mg",
    "Gabapentin - 200mg",
    "Carbamazipine - 200mg",
    "Fluoxetine - 20mg",
    "Flunarizine - 5mg",
    "Flunarizine - 10mg",
    "Tamsulosin(Urimax) - 0.4mg",
    "MDI Foracort - 100mcg",
    "MDI Foracort - 200mcg",
    "MDI Foracort - 400mcg",
    "MDI Salbutamol - 100mcg",
    "MDI Salbutamol - 200mcg",
    "MDI Salbutamol - 400mcg",
    "DPC Salbutamol - 100mcg",
    "DPC Salbutamol - 200mcg",
    "DPC Salbutamol - 400mcg",
    "DPC Formovent - 100mcg",
    "DPC Formovent - 200mcg",
    "DPC Formovent - 400mcg",
    "DPC Beclomenthasone - 200mcg",
    "DPC Beclomenthasone - 400mcg",
    "MDI Beclomenthasone - 250mcg",
    "MDI Beclomenthasone - 100mcg",
    "Ventihaler",
    "Bisoprolol(Concor) - 5mg",
    "Bisoprolol(Concor) - 2.5mg",
    "Metopralol - 25mg",
    "Metopralol - 50mg",
    "Metopralol XL - 25mg",
    "MEtopralol XL - 50mg",
    "Telmisartan - 20mg",
    "Telmisartan - 40mg",
    "Digoxin - 0.25mg",
    "Thyroxin - 50mcg",
    "Thyroxin - 100mcg",
    "Cinnarizine - 25mg",
    "Norethisterone - 5mg",
    "Tranexamic acid - 250mg",
    "Tranexamic acid - 500mg",
    "Sitagliptin - 50mg",
    "Sitagliptin - 100mg",
    "Empagliflozin - 10mg",
    "Empagliflozin - 12.5mg",
    "Empagliflozin - 25mg",
    "Multiforte Multivitamin Caps",
    "Forceval Multivitamin Caps",
    "Minterra Multivitamin Caps",
    "Vitacaps Multivitamin Caps",
    "Guardian Multivitamin Tabs",
    "Ferup SG",
    "Irpo-FA",
    "Probiotic(Bifilac) Sachet",
    "Probiotic(Bifilac) Tablet",
    "Nasal Spray Fluticasome(Flutinase) - 50mcg",
    "Betamethasone+Neomycin E/E/N Drops(Probeta-N)",
    "Syndropa - 275mg"
];

// Medical consultants list
$medicalConsultants = [
    "Consultant Physician",
    "Consultant Surgeon",
    "Consultant Psychiatrist",
    "Consultant Pediatrician",
    "Consultant Neurologist",
    "Consultant Dermatologist",
    "Consultant Neuro Surgeon",
    "Consultant Cardiologist",
    "Consultant Cardio electrophysiologist",
    "Consultant obstetrician & Gynecologist",
    "Consultant Ophthalmologist",
    "Consultant ENT Surgeon",
    "Consultant OMF Surgeon",
    "Consultant Cardiothoracic Surgeon",
    "Consultant Vascular Surgeon",
    "Consultant Gastroenterologist",
    "Consultant Gastrointestinal Surgeon",
    "Consultant Genitourinary Surgeon",
    "Consultant Oncologist",
    "Consultant Onco Surgeon",
    "Consultant Nephrologist",
    "Consultant Haematologist",
    "Consultant Microbiologist",
    "Consultant Pathologist"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Section - Doctor Wallet</title>
<!-- Favicon (modern browsers) -->
<link rel="icon" type="image/png" sizes="32x32" href="../icon.png">

<!-- High-res favicon -->
<link rel="icon" type="image/png" sizes="192x192" href="../icon.png">

<!-- Apple touch icon (iOS home screen) -->
<link rel="apple-touch-icon" sizes="180x180" href="../icon.png">

<!-- Safari pinned tab (monochrome SVG) -->
<link rel="mask-icon" href="../icon.svg" color="#0F2E44">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-content { 
                font-family: 'Times New Roman', serif;
                line-height: 1.6;
                color: black;
                position: relative;
            }
            .page-break { page-break-after: always; }
            
            /* Watermark for signature protection */
            .signature-watermark::before {
                content: 'Doctor Wallet - Authorized Copy';
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg);
                font-size: 48px;
                opacity: 0.05;
                color: gray;
                z-index: 1;
                pointer-events: none;
                white-space: nowrap;
            }
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 1200px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        textarea {
            resize: vertical;
        }
        
        /* Autocomplete styles */
        .autocomplete-container {
            position: relative;
        }
        
        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ccc;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .autocomplete-suggestion {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        
        .autocomplete-suggestion:hover,
        .autocomplete-suggestion.selected {
            background-color: #f0f0f0;
        }
        
        .medicine-table {
            max-height: 300px;
            overflow-y: auto;
        }

        /* Custom dropdown styles for consultant selector */
        .custom-dropdown {
            position: relative;
        }
        
        .dropdown-input {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .dropdown-input input {
            padding-right: 40px;
        }
        
        .dropdown-arrow {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 12px;
        }
        
        .consultant-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ccc;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .consultant-suggestion {
            padding: 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        
        .consultant-suggestion:last-child {
            border-bottom: none;
        }
        
        .consultant-suggestion:hover,
        .consultant-suggestion.selected {
            background-color: #f8f9fa;
        }
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
    
        .container.mx-auto.p-6 {
            flex: 1;
        }
    
        footer {
            margin-top: auto;
        }

/* Modern Premium Smooth Transitions */
body {
    margin: 0;
    padding: 0;
    position: relative;
    overflow-x: hidden;
}

/* Gradient overlay for smooth transition */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at center, rgba(99, 102, 241, 0.03) 0%, rgba(0, 0, 0, 0.4) 100%);
    opacity: 0;
    pointer-events: none;
    z-index: 9998;
    transition: opacity 0.6s cubic-bezier(0.65, 0, 0.35, 1);
    backdrop-filter: blur(0px);
}

/* Animated gradient overlay */
body::after {
    content: '';
    position: fixed;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.15) 0%, transparent 70%);
    opacity: 0;
    pointer-events: none;
    z-index: 9999;
    transform: scale(0);
    transition: all 0.7s cubic-bezier(0.65, 0, 0.35, 1);
}

/* Exit animation */
body.page-transitioning-out {
    animation: smoothOut 0.7s cubic-bezier(0.65, 0, 0.35, 1) forwards;
    pointer-events: none;
}

body.page-transitioning-out::before {
    opacity: 1;
    backdrop-filter: blur(20px);
}

body.page-transitioning-out::after {
    opacity: 1;
    transform: scale(2);
}

/* Enter animation */
body.page-transitioning-in {
    animation: smoothIn 0.8s cubic-bezier(0.65, 0, 0.35, 1) forwards;
}

body.page-transitioning-in::after {
    animation: pulseIn 0.8s cubic-bezier(0.65, 0, 0.35, 1);
}

/* Smooth exit with elastic feel */
@keyframes smoothOut {
    0% {
        opacity: 1;
        transform: translateY(0) scale(1) rotateX(0deg);
        filter: blur(0px) brightness(1);
    }
    100% {
        opacity: 0;
        transform: translateY(-40px) scale(0.95) rotateX(2deg);
        filter: blur(10px) brightness(0.8);
    }
}

/* Smooth enter with bounce */
@keyframes smoothIn {
    0% {
        opacity: 0;
        transform: translateY(50px) scale(0.94) rotateX(-2deg);
        filter: blur(15px) brightness(0.7);
    }
    60% {
        opacity: 0.8;
        transform: translateY(-5px) scale(1.01) rotateX(0deg);
        filter: blur(2px) brightness(0.95);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1) rotateX(0deg);
        filter: blur(0px) brightness(1);
    }
}

/* Pulse effect */
@keyframes pulseIn {
    0%, 100% {
        opacity: 0;
        transform: scale(0);
    }
    50% {
        opacity: 1;
        transform: scale(1.5);
    }
}

/* Prevent scroll during transition */
body.page-transitioning-out,
body.page-transitioning-in {
    overflow: hidden;
    height: 100vh;
}

/* Stagger content animation */
body.page-transitioning-in * {
    animation: contentSlideIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) backwards;
}

body.page-transitioning-in *:nth-child(1) { animation-delay: 0.1s; }
body.page-transitioning-in *:nth-child(2) { animation-delay: 0.15s; }
body.page-transitioning-in *:nth-child(3) { animation-delay: 0.2s; }
body.page-transitioning-in *:nth-child(4) { animation-delay: 0.25s; }

@keyframes contentSlideIn {
    0% {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Smooth links hover effect */
a {
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

a:hover {
    transform: translateY(-2px);
}
    </style>
</head>
<body class="bg-gray-100 min-h-screen" style="visibility: hidden;">
<!-- Navigation -->
<nav class="bg-gradient-to-r from-blue-800 via-blue-700 to-blue-800 text-white p-4">
  <div class="container mx-auto flex flex-col md:flex-row md:items-center md:justify-between gap-4">

    <!-- Left side (title) -->
    <h1 class="text-xl md:text-2xl font-bold text-center md:text-left">Print Section</h1>

    <!-- Center menu (responsive stack) -->
    <div class="flex flex-wrap justify-center md:justify-center gap-3">
      <!-- Patients Link -->
      <a href="patients.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-user-injured"></i>
        <span>Patients</span>
      </a>

      <!-- Medicines Link -->
      <a href="medicines.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-pills"></i>
        <span>Medicines</span>
      </a>

      <!-- Drug dispenser Link -->
      <a href="drug_dispenser.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-stethoscope"></i>
        <span>Drug dispenser</span>
      </a>

      <!-- File upload Link -->
      <a href="file_upload.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-file-upload"></i>
        <span>File upload</span>
      </a>

      <!-- All receipts Link -->
      <a href="reports.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-chart-bar"></i>
        <span>Reports</span>
      </a>

      <!-- Reports Link -->
      <a href="receipts.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-receipt"></i>
        <span>All receipts</span>
      </a>
    </div>

    <!-- Right side -->
    <div class="flex justify-center md:justify-end">
      <a href="../index.php" class="bg-blue-800 hover:bg-blue-900 px-4 py-2 rounded text-sm md:text-base">
        Back to Dashboard
      </a>
    </div>
  </div>
</nav>


    <div class="container mx-auto p-6 no-print">
        <h2 class="text-3xl font-bold text-gray-800 mb-8">Medical Document Templates</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Medical Certificate Template -->
            <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6 cursor-pointer" onclick="openModal('medicalModal')">
                <div class="text-center">
                    <i class="fas fa-file-medical-alt text-4xl mb-4 bg-gradient-to-r from-green-400 to-green-600 bg-clip-text text-transparent"></i>
                    <h3 class="text-xl font-semibold text-gray-700">Medical Certificate</h3>
                    <p class="text-gray-500 mt-2">Issue medical leave certificates</p>
                </div>
            </div>
            
            <!-- Referral Template -->
            <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6 cursor-pointer" onclick="openModal('referralModal')">
                <div class="text-center">
                    <i class="fas fa-notes-medical text-4xl mb-4 bg-gradient-to-r from-blue-400 to-blue-600 bg-clip-text text-transparent"></i>
                    <h3 class="text-xl font-semibold text-gray-700">Medical Referrals</h3>
                    <p class="text-gray-500 mt-2">Multiple referral types available</p>
                </div>
            </div>
            
            <!-- Outside Prescription Template -->
            <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6 cursor-pointer" onclick="openModal('prescriptionModal')">
                <div class="text-center">
                    <i class="fas fa-prescription-bottle text-4xl mb-4 bg-gradient-to-r from-purple-400 to-purple-600 bg-clip-text text-transparent"></i>
                    <h3 class="text-xl font-semibold text-gray-700">Outside Prescription</h3>
                    <p class="text-gray-500 mt-2">Create external prescriptions</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Medical Certificate Modal -->
    <div id="medicalModal" class="modal">
        <div class="modal-content">
            <div class="p-6 no-print">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-2xl font-bold">Medical Certificate</h3>
                    <button onclick="closeModal('medicalModal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Patient</label>
                    <select id="medicalPatient" class="w-full p-2 border border-gray-300 rounded" onchange="loadPatientData('medical')">
                        <option value="">Select a patient...</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>" 
                                    data-type="<?php echo $patient['type']; ?>"
                                    data-name="<?php echo htmlspecialchars($patient['name']); ?>"
                                    data-phone="<?php echo htmlspecialchars($patient['phone_number']); ?>"
                                    data-age="<?php echo $patient['age'] ?? 0; ?>">
                                <?php echo htmlspecialchars($patient['name']); ?> (<?php echo ucfirst($patient['type']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Days of Leave</label>
                        <input type="number" id="medicalDays" class="w-full p-2 border border-gray-300 rounded" value="5">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                        <input type="date" id="medicalFromDate" class="w-full p-2 border border-gray-300 rounded" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                        <input type="date" id="medicalToDate" class="w-full p-2 border border-gray-300 rounded">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Place of Residence</label>
                    <textarea id="medicalResidence" rows="2" class="w-full p-2 border border-gray-300 rounded" placeholder="Enter patient's residence address..."></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Signs and Symptoms</label>
                        <textarea id="medicalSymptoms" rows="3" class="w-full p-2 border border-gray-300 rounded" placeholder="Enter signs and symptoms...">cough and fever</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nature of Disease</label>
                        <textarea id="medicalDisease" rows="3" class="w-full p-2 border border-gray-300 rounded" placeholder="Enter nature of disease...">Viral fever</textarea>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button onclick="generateMedical()" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Generate & Print</button>
                    <button onclick="closeModal('medicalModal')" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">Close</button>
                </div>
            </div>
            
            <div id="medicalPrint" class="print-content p-8 hidden signature-watermark">
                <!-- Medical certificate content will be generated here -->
            </div>
        </div>
    </div>

    <!-- Referral Modal -->
    <div id="referralModal" class="modal">
        <div class="modal-content">
            <div class="p-6 no-print">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-2xl font-bold">Medical Referrals</h3>
                    <button onclick="closeModal('referralModal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Referral Type</label>
                    <select id="referralType" class="w-full p-2 border border-gray-300 rounded" onchange="updateReferralFields()">
                        <option value="">Select referral type...</option>
                        <option value="referral">Referral</option>
                        <option value="financial">Financial Support</option>
                        <option value="fitness">Fitness Certificate</option>
                        <option value="ultrasound">Ultrasound Scan</option>
                        <option value="investigation">Medical Investigation</option>
                        <option value="examination">Medical Examination</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Patient</label>
                    <select id="referralPatient" class="w-full p-2 border border-gray-300 rounded" onchange="loadPatientData('referral')">
                        <option value="">Select a patient...</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>" 
                                    data-type="<?php echo $patient['type']; ?>"
                                    data-name="<?php echo htmlspecialchars($patient['name']); ?>"
                                    data-phone="<?php echo htmlspecialchars($patient['phone_number']); ?>"
                                    data-age="<?php echo $patient['age'] ?? 0; ?>">
                                <?php echo htmlspecialchars($patient['name']); ?> (<?php echo ucfirst($patient['type']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Common fields -->
                <div id="commonFields" class="hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Presenting Complaint</label>
                            <textarea id="referralComplaint" rows="2" class="w-full p-2 border border-gray-300 rounded" placeholder="Enter presenting complaint..."></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">History</label>
                            <textarea id="referralHistory" rows="2" class="w-full p-2 border border-gray-300 rounded" placeholder="Enter history..."></textarea>
                        </div>
                    </div>
                <!-- New Consultant Selection Field -->
                <div class="mb-4" id="consultantFields">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Medical Consultant (Optional)</label>
                    <div class="custom-dropdown">
                        <div class="dropdown-input">
                            <input type="text" 
                                   id="consultantInput" 
                                   class="w-full p-2 border border-gray-300 rounded" 
                                   placeholder="Search or select a consultant..."
                                   autocomplete="off">
                            <i class="fas fa-chevron-down dropdown-arrow" onclick="toggleConsultantDropdown()"></i>
                        </div>
                        <div id="consultantSuggestions" class="consultant-suggestions">
                            <!-- Suggestions will be populated by JavaScript -->
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Leave blank if not referring to a specific consultant</p>
                </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Investigations</label>
                        <textarea id="referralInvestigations" rows="2" class="w-full p-2 border border-gray-300 rounded" placeholder="Enter investigations..."></textarea>
                    </div>
                </div>
                
                <!-- Financial Support Fields -->
                <div id="financialFields" class="hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Medical Condition</label>
                        <textarea id="financialCondition" rows="2" class="w-full p-2 border border-gray-300 rounded" placeholder="Enter medical condition..."></textarea>
                    </div>
                </div>
                
                <!-- Fitness Fields -->
                <div id="fitnessFields" class="hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Residence Address</label>
                            <textarea id="fitnessAddress" rows="2" class="w-full p-2 border border-gray-300 rounded" placeholder="Enter residence address..."></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Purpose (Fit for)</label>
                            <textarea id="fitnessPurpose" rows="2" class="w-full p-2 border border-gray-300 rounded" placeholder="Enter purpose...">employment/sports/travel</textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Ultrasound Fields -->
                <div id="ultrasoundFields" class="hidden">
                    <div class="mb-4">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Consultant's hospital(optional)</label>
                            <textarea id="consultantHospital" rows="2" class="w-full p-2 border border-gray-300 rounded" placeholder="Enter consultant's hospital..."></textarea>
                        </div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ultrasound Type</label>
                        <input type="text" id="ultrasoundType" class="w-full p-2 border border-gray-300 rounded" placeholder="e.g., Abdominal Ultrasound">
                    </div>
                </div>
                
                <!-- Investigation Fields -->
                <div id="investigationFields" class="hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Investigation Tests</label>
                        <textarea id="investigationTests" rows="4" class="w-full p-2 border border-gray-300 rounded" placeholder="Enter investigation tests (one per line)...">Full Blood Count
ESR
Blood Sugar (Fasting)</textarea>
                    </div>
                </div>
                
                <!-- Medical Examination Fields -->
                <div id="examinationFields" class="hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Examination Purpose</label>
                        <textarea id="examinationPurpose" rows="2" class="w-full p-2 border border-gray-300 rounded" placeholder="Enter examination purpose...">General health assessment</textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Examination Findings</label>
                        <textarea id="examinationFindings" rows="3" class="w-full p-2 border border-gray-300 rounded" placeholder="Enter examination findings...">Patient appears healthy with normal vital signs</textarea>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button onclick="generateReferral()" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700" id="generateBtn" disabled>Generate & Print</button>
                    <button onclick="closeModal('referralModal')" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">Close</button>
                </div>
            </div>
            
            <div id="referralPrint" class="print-content p-8 hidden signature-watermark">
                <!-- Referral content will be generated here -->
            </div>
        </div>
    </div>

    <!-- Enhanced Prescription Modal -->
    <div id="prescriptionModal" class="modal">
        <div class="modal-content">
            <div class="p-6 no-print">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-2xl font-bold">Outside Prescription</h3>
                    <button onclick="closeModal('prescriptionModal')" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Patient</label>
                    <select id="prescriptionPatient" class="w-full p-2 border border-gray-300 rounded" onchange="loadPatientData('prescription')">
                        <option value="">Select a patient...</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>" 
                                    data-type="<?php echo $patient['type']; ?>"
                                    data-name="<?php echo htmlspecialchars($patient['name']); ?>"
                                    data-phone="<?php echo htmlspecialchars($patient['phone_number']); ?>"
                                    data-age="<?php echo $patient['age'] ?? 0; ?>">
                                <?php echo htmlspecialchars($patient['name']); ?> (<?php echo ucfirst($patient['type']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Next Visit Date</label>
                        <input type="date" id="prescriptionNextVisit" class="w-full p-2 border border-gray-300 rounded">
                    </div>
                </div>
                
                <!-- Medicine Entry Section -->
                <div class="bg-gray-50 p-4 rounded-lg mb-4">
                    <h4 class="text-lg font-semibold mb-3">Add Medicine</h4>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-3">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Medicine Name</label>
                            <div class="autocomplete-container">
                                <input type="text" id="medicineNameInput" class="w-full p-2 border border-gray-300 rounded" 
                                       placeholder="Type medicine name..." autocomplete="off">
                                <div id="medicineAutocomplete" class="autocomplete-suggestions"></div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Per Dose</label>
                            <select id="medicinePerDose" class="w-full p-2 border border-gray-300 rounded">
                                <option value="">Select</option>
                                <option value="0.5">1/2</option>
                                <option value="1">1</option>
                                <option value="1.5">1 1/2</option>
                                <option value="2">2</option>
                                <option value="2.5">2 1/2</option>
                                <option value="3">3</option>
                                <option value="3.5">3 1/2</option>
                                <option value="4">4</option>
                                <option value="4.5">4 1/2</option>
                                <option value="5">5</option>
                                <option value="5.5">5 1/2</option>
                                <option value="6">6</option>
                                <option value="6.5">6 1/2</option>
                                <option value="7">7</option>
                                <option value="7.5">7 1/2</option>
                                <option value="8">8</option>
                                <option value="8.5">8 1/2</option>
                                <option value="9">9</option>
                                <option value="9.5">9 1/2</option>
                                <option value="10">10</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Dosage</label>
                            <select id="medicineDosage" class="w-full p-2 border border-gray-300 rounded">
                                <option value="">Select</option>
                                <?php foreach ($dosageTypes as $dosage): ?>
                                    <option value="<?php echo htmlspecialchars($dosage['code']); ?>">
                                        <?php echo htmlspecialchars($dosage['code']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Days</label>
                            <input type="number" id="medicineDays" class="w-full p-2 border border-gray-300 rounded" 
                                   placeholder="Days" min="1" value="5">
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" onclick="addMedicine()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 flex items-center">
                            <span class="mr-2">+</span> Add Medicine
                        </button>
                    </div>
                </div>
                
                <!-- Medicine Table -->
                <div class="mb-4">
                    <h4 class="text-lg font-semibold mb-3">Added Medicines</h4>
                    <div class="medicine-table border rounded-lg">
                        <table class="w-full" id="medicineTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left">Medicine</th>
                                    <th class="px-4 py-2 text-left">Dosage</th>
                                    <th class="px-4 py-2 text-left">Per Dose</th>
                                    <th class="px-4 py-2 text-left">Days</th>
                                    <th class="px-4 py-2 text-left">Instructions</th>
                                    <th class="px-4 py-2 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody id="medicineTableBody">
                                <tr id="noMedicineRow">
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No medicines added yet</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button onclick="generatePrescription()" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Generate & Print</button>
                    <button onclick="closeModal('prescriptionModal')" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">Close</button>
                </div>
            </div>
            
            <div id="prescriptionPrint" class="print-content p-8 hidden signature-watermark">
                <!-- Prescription content will be generated here -->
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gradient-to-r from-blue-800 via-blue-700 to-blue-800 text-white shadow-2xl no-print" style="border-radius: 50px 50px 0 0;">
        <div class="container mx-auto px-6 py-8">
            <div class="text-center">
                <p class="text-indigo-300 text-sm mb-2">
                    Copyright © 2025 Doctor Wallet. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

<script>
        const doctorSignature = <?php echo $doctorSignature ? "'" . addslashes($doctorSignature['file_path']) . "'" : 'null'; ?>;
        const doctorName = '<?php echo addslashes($user['doctor_name']); ?>';
        
        // Medicine list for autocomplete
        const medicineList = <?php echo json_encode($medicineList); ?>;
        
        // Medical consultants list
        const medicalConsultants = <?php echo json_encode($medicalConsultants); ?>;
        
        // Array to store added medicines
        let addedMedicines = [];
        let consultantSuggestionIndex = -1;
        let selectedMedicineIndex = -1;
        
        function getSignatureHTML() {
            if (doctorSignature) {
                return `<img src="${doctorSignature}" style="max-width: 150px; max-height: 80px; margin: 10px 0;" alt="Doctor Signature">`;
            }
            return '<br><br>';
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            // Reset forms when closing
            if (modalId === 'referralModal') {
                document.getElementById('referralType').value = '';
                updateReferralFields();
            }
            if (modalId === 'prescriptionModal') {
                resetPrescriptionForm();
            }
            if (modalId === 'medicalModal') {
                resetMedicalForm();
            }
        }

        function updateReferralFields() {
            const type = document.getElementById('referralType').value;
            const generateBtn = document.getElementById('generateBtn');
            
            // Hide all field groups
            document.getElementById('commonFields').classList.add('hidden');
            document.getElementById('financialFields').classList.add('hidden');
            document.getElementById('fitnessFields').classList.add('hidden');
            document.getElementById('ultrasoundFields').classList.add('hidden');
            document.getElementById('investigationFields').classList.add('hidden');
            document.getElementById('examinationFields').classList.add('hidden');
            document.getElementById('consultantFields').classList.add('hidden');
            
            // Show relevant fields based on type
            if (type === 'referral' || type === 'ultrasound') {
                document.getElementById('commonFields').classList.remove('hidden');
                document.getElementById('consultantFields').classList.remove('hidden');
                if (type === 'ultrasound') {
                    document.getElementById('consultantFields').classList.add('hidden');
                    document.getElementById('ultrasoundFields').classList.remove('hidden');
                }
                generateBtn.disabled = false;
            } else if (type === 'financial') {
                document.getElementById('financialFields').classList.remove('hidden');
                generateBtn.disabled = false;
            } else if (type === 'fitness') {
                document.getElementById('fitnessFields').classList.remove('hidden');
                generateBtn.disabled = false;
            } else if (type === 'investigation') {
                document.getElementById('investigationFields').classList.remove('hidden');
                generateBtn.disabled = false;
            } else if (type === 'examination') {
                document.getElementById('commonFields').classList.remove('hidden');
                document.getElementById('examinationFields').classList.remove('hidden');
                generateBtn.disabled = false;
            } else {
                generateBtn.disabled = true;
            }
        }

        function resetMedicalForm() {
            document.getElementById('medicalPatient').value = '';
            document.getElementById('medicalDays').value = '5';
            document.getElementById('medicalFromDate').value = new Date().toISOString().split('T')[0];
            updateToDate();
            document.getElementById('medicalResidence').value = '';
            document.getElementById('consultantInput').value = '';
            document.getElementById('medicalSymptoms').value = 'cough and fever';
            document.getElementById('medicalDisease').value = 'Viral fever';
            hideConsultantSuggestions();
        }

        function loadPatientData(type) {
            const select = document.getElementById(type + 'Patient');
            const option = select.options[select.selectedIndex];
            
            if (option.value) {
                const patientData = {
                    name: option.dataset.name,
                    age: option.dataset.age,
                    phone: option.dataset.phone,
                    type: option.dataset.type
                };
                
                // Auto-calculate dates for medical certificate
                if (type === 'medical') {
                    const days = parseInt(document.getElementById('medicalDays').value) || 5;
                    const fromDate = new Date(document.getElementById('medicalFromDate').value);
                    const toDate = new Date(fromDate);
                    toDate.setDate(toDate.getDate() + days - 1);
                    document.getElementById('medicalToDate').value = toDate.toISOString().split('T')[0];
                }
                
                // Auto-fill residence for medical certificate
                if (type === 'medical') {
                    document.getElementById('medicalResidence').value = patientData.name;
                }
            }
        }

        // Auto-calculate to date when days or from date changes
        if (document.getElementById('medicalDays')) {
            document.getElementById('medicalDays').addEventListener('change', function() {
                updateToDate();
            });
        }
        
        if (document.getElementById('medicalFromDate')) {
            document.getElementById('medicalFromDate').addEventListener('change', function() {
                updateToDate();
            });
        }
        
        function updateToDate() {
            const daysInput = document.getElementById('medicalDays');
            const fromDateInput = document.getElementById('medicalFromDate');
            const toDateInput = document.getElementById('medicalToDate');
            
            if (!daysInput || !fromDateInput || !toDateInput) return;
            
            const days = parseInt(daysInput.value) || 5;
            const fromDate = new Date(fromDateInput.value);
            const toDate = new Date(fromDate);
            toDate.setDate(toDate.getDate() + days - 1);
            toDateInput.value = toDate.toISOString().split('T')[0];
        }

        // Consultant Search and Dropdown Functionality
        function setupConsultantAutocomplete() {
            const input = document.getElementById('consultantInput');
            const suggestionsContainer = document.getElementById('consultantSuggestions');
            
            if (!input || !suggestionsContainer) return;
            
            // Populate all consultants initially
            populateConsultantSuggestions(medicalConsultants);
            
            input.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                if (searchTerm === '') {
                    populateConsultantSuggestions(medicalConsultants);
                } else {
                    const filteredConsultants = medicalConsultants.filter(consultant =>
                        consultant.toLowerCase().includes(searchTerm)
                    );
                    populateConsultantSuggestions(filteredConsultants);
                }
                showConsultantSuggestions();
                consultantSuggestionIndex = -1;
            });
            
            input.addEventListener('focus', function() {
                showConsultantSuggestions();
            });
            
            input.addEventListener('keydown', function(e) {
                const suggestions = document.querySelectorAll('.consultant-suggestion');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    consultantSuggestionIndex = Math.min(consultantSuggestionIndex + 1, suggestions.length - 1);
                    updateConsultantSelection(suggestions);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    consultantSuggestionIndex = Math.max(consultantSuggestionIndex - 1, -1);
                    updateConsultantSelection(suggestions);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (consultantSuggestionIndex >= 0 && suggestions[consultantSuggestionIndex]) {
                        selectConsultant(suggestions[consultantSuggestionIndex].textContent);
                    }
                } else if (e.key === 'Escape') {
                    hideConsultantSuggestions();
                    consultantSuggestionIndex = -1;
                }
            });
        }
        
        function populateConsultantSuggestions(consultants) {
            const suggestionsContainer = document.getElementById('consultantSuggestions');
            if (!suggestionsContainer) return;
            
            suggestionsContainer.innerHTML = '';
            
            consultants.forEach((consultant, index) => {
                const div = document.createElement('div');
                div.className = 'consultant-suggestion';
                div.textContent = consultant;
                div.addEventListener('click', () => selectConsultant(consultant));
                suggestionsContainer.appendChild(div);
            });
        }
        
        function updateConsultantSelection(suggestions) {
            suggestions.forEach((suggestion, index) => {
                if (index === consultantSuggestionIndex) {
                    suggestion.classList.add('selected');
                } else {
                    suggestion.classList.remove('selected');
                }
            });
        }
        
        function selectConsultant(consultant) {
            const input = document.getElementById('consultantInput');
            if (input) input.value = consultant;
            hideConsultantSuggestions();
            consultantSuggestionIndex = -1;
        }
        
        function toggleConsultantDropdown() {
            const suggestionsContainer = document.getElementById('consultantSuggestions');
            if (!suggestionsContainer) return;
            
            if (suggestionsContainer.style.display === 'none' || suggestionsContainer.style.display === '') {
                populateConsultantSuggestions(medicalConsultants);
                showConsultantSuggestions();
            } else {
                hideConsultantSuggestions();
            }
        }
        
        function showConsultantSuggestions() {
            const suggestionsContainer = document.getElementById('consultantSuggestions');
            if (suggestionsContainer) suggestionsContainer.style.display = 'block';
        }
        
        function hideConsultantSuggestions() {
            const suggestionsContainer = document.getElementById('consultantSuggestions');
            if (suggestionsContainer) suggestionsContainer.style.display = 'none';
        }

        // Medicine Autocomplete Functionality
        function setupMedicineAutocomplete() {
            const input = document.getElementById('medicineNameInput');
            if (!input) return;
            
            input.addEventListener('input', function() {
                const inputValue = this.value.toLowerCase();
                const suggestions = document.getElementById('medicineAutocomplete');
                
                if (!suggestions) return;
                
                if (inputValue.length === 0) {
                    suggestions.style.display = 'none';
                    return;
                }
                
                const filteredMedicines = medicineList.filter(medicine => 
                    medicine.toLowerCase().includes(inputValue)
                ).slice(0, 10); // Limit to 10 suggestions
                
                if (filteredMedicines.length === 0) {
                    suggestions.style.display = 'none';
                    return;
                }
                
                suggestions.innerHTML = '';
                filteredMedicines.forEach((medicine, index) => {
                    const div = document.createElement('div');
                    div.className = 'autocomplete-suggestion';
                    div.textContent = medicine;
                    div.addEventListener('click', () => selectMedicine(medicine));
                    suggestions.appendChild(div);
                });
                
                suggestions.style.display = 'block';
                selectedMedicineIndex = -1;
            });

            input.addEventListener('keydown', function(e) {
                const suggestions = document.querySelectorAll('.autocomplete-suggestion');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    selectedMedicineIndex = Math.min(selectedMedicineIndex + 1, suggestions.length - 1);
                    updateMedicineSuggestionSelection(suggestions);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    selectedMedicineIndex = Math.max(selectedMedicineIndex - 1, -1);
                    updateMedicineSuggestionSelection(suggestions);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (selectedMedicineIndex >= 0 && suggestions[selectedMedicineIndex]) {
                        selectMedicine(suggestions[selectedMedicineIndex].textContent);
                    }
                } else if (e.key === 'Escape') {
                    const autocomplete = document.getElementById('medicineAutocomplete');
                    if (autocomplete) autocomplete.style.display = 'none';
                    selectedMedicineIndex = -1;
                }
            });
        }

        function updateMedicineSuggestionSelection(suggestions) {
            suggestions.forEach((suggestion, index) => {
                if (index === selectedMedicineIndex) {
                    suggestion.classList.add('selected');
                } else {
                    suggestion.classList.remove('selected');
                }
            });
        }

        function selectMedicine(medicine) {
            const input = document.getElementById('medicineNameInput');
            const autocomplete = document.getElementById('medicineAutocomplete');
            
            if (input) input.value = medicine;
            if (autocomplete) autocomplete.style.display = 'none';
            selectedMedicineIndex = -1;
        }

        // Add Medicine Function
        function addMedicine() {
            const nameInput = document.getElementById('medicineNameInput');
            const dosageInput = document.getElementById('medicineDosage');
            const perDoseInput = document.getElementById('medicinePerDose');
            const daysInput = document.getElementById('medicineDays');
            
            if (!nameInput || !dosageInput || !perDoseInput || !daysInput) return;
            
            const name = nameInput.value.trim();
            const dosage = dosageInput.value;
            const perDose = perDoseInput.value;
            const days = daysInput.value;
            
            if (!name || !dosage || !perDose || !days) {
                alert('Please fill all medicine fields');
                return;
            }
            
            const medicine = {
                name: name,
                dosage: dosage,
                perDose: perDose,
                days: days,
                instruction: `${perDose} tablet(s) ${dosage} for ${days} days`
            };
            
            addedMedicines.push(medicine);
            updateMedicineTable();
            clearMedicineForm();
        }

        function updateMedicineTable() {
            const tbody = document.getElementById('medicineTableBody');
            const noMedicineRow = document.getElementById('noMedicineRow');
            
            if (!tbody || !noMedicineRow) return;
            
            if (addedMedicines.length === 0) {
                noMedicineRow.style.display = 'table-row';
                return;
            }
            
            noMedicineRow.style.display = 'none';
            
            // Clear existing rows except the no-medicine row
            const existingRows = tbody.querySelectorAll('tr:not(#noMedicineRow)');
            existingRows.forEach(row => row.remove());
            
            addedMedicines.forEach((medicine, index) => {
                const row = document.createElement('tr');
                row.className = 'border-b';
                row.innerHTML = `
                    <td class="px-4 py-2">${medicine.name}</td>
                    <td class="px-4 py-2">${medicine.dosage}</td>
                    <td class="px-4 py-2">${medicine.perDose}</td>
                    <td class="px-4 py-2">${medicine.days}</td>
                    <td class="px-4 py-2">${medicine.instruction}</td>
                    <td class="px-4 py-2">
                        <button onclick="removeMedicine(${index})" class="text-red-600 hover:text-red-800">
                            Remove
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function removeMedicine(index) {
            addedMedicines.splice(index, 1);
            updateMedicineTable();
        }

        function clearMedicineForm() {
            const nameInput = document.getElementById('medicineNameInput');
            const dosageInput = document.getElementById('medicineDosage');
            const perDoseInput = document.getElementById('medicinePerDose');
            const daysInput = document.getElementById('medicineDays');
            
            if (nameInput) nameInput.value = '';
            if (dosageInput) dosageInput.value = '';
            if (perDoseInput) perDoseInput.value = '';
            if (daysInput) daysInput.value = '5';
        }

        function resetPrescriptionForm() {
            addedMedicines = [];
            updateMedicineTable();
            clearMedicineForm();
            
            const patientInput = document.getElementById('prescriptionPatient');
            const nextVisitInput = document.getElementById('prescriptionNextVisit');
            
            if (patientInput) patientInput.value = '';
            if (nextVisitInput) nextVisitInput.value = '';
        }

        function generateMedical() {
            const patientSelect = document.getElementById('medicalPatient');
            if (!patientSelect) return;
            
            const option = patientSelect.options[patientSelect.selectedIndex];
            
            if (!option.value) {
                alert('Please select a patient first');
                return;
            }
            
            const refNo = 'DW/' + new Date().toISOString().slice(0,10).replace(/-/g, '') + '/' + Math.floor(Math.random() * 100);
            const patientName = option.dataset.name;
            const patientAge = option.dataset.age;
            
            const residenceInput = document.getElementById('medicalResidence');
            const symptomsInput = document.getElementById('medicalSymptoms');
            const diseaseInput = document.getElementById('medicalDisease');
            const daysInput = document.getElementById('medicalDays');
            const fromDateInput = document.getElementById('medicalFromDate');
            const toDateInput = document.getElementById('medicalToDate');
            
            const residence = residenceInput ? residenceInput.value : '';
            const symptoms = symptomsInput ? symptomsInput.value : '';
            const disease = diseaseInput ? diseaseInput.value : '';
            const days = daysInput ? daysInput.value : '';
            const fromDate = fromDateInput ? fromDateInput.value : '';
            const toDate = toDateInput ? toDateInput.value : '';
            const currentDate = new Date().toISOString().split('T')[0];
            
            
            const content = `
                <div style="font-family: 'Times New Roman', serif; line-height: 1.6; max-width: 800px; margin: 0 auto;">
                    <p><strong>Ref No:</strong> ${refNo}</p>
                    <br>
                    <h2 style="text-align: center; text-decoration: underline; font-size: 24px; margin-bottom: 30px;">Medical Certificate</h2>
        
                    <br>
                    <p><strong>Name :</strong> ${patientName}</p>
                    <p><strong>Age :</strong> ${patientAge} Years</p>
                    <p><strong>Place of Residence :</strong> ${residence}</p>
                    <br>
                    <p><strong>Signs and Symptoms :</strong> ${symptoms}</p>
                    <p><strong>Nature of Disease :</strong> ${disease}</p>
                    <br>
                    <p>I am of the opinion that <strong>${patientName}</strong> is not fit for work due to the above-mentioned medical condition. I recommend granting leave for <strong>${days} days</strong>, from <strong>${fromDate}</strong> to <strong>${toDate}</strong></p>
                    <br>
                    <br><br>
                    <div style="display: flex; justify-content: space-between; margin-top: 50px;">
                        <div>
                            <p><strong>Date:</strong> ${currentDate}</p>
                        </div>
                        <div style="text-align: right;">
                            <p><strong>Authorized Signature :</strong></p>
                            ${getSignatureHTML()}
                            <p>---------------------------</p>
                            <p><strong>Dr. ${doctorName}</strong></p>
                        </div>
                    </div>
                </div>
            `;
            
            const printElement = document.getElementById('medicalPrint');
            if (printElement) {
                printElement.innerHTML = content;
                printElement.classList.remove('hidden');
                
                setTimeout(() => {
                    window.print();
                }, 100);
            }
        }

        function generateReferral() {
            const typeInput = document.getElementById('referralType');
            const patientSelect = document.getElementById('referralPatient');
            const consultantInput = document.getElementById('consultantInput');
            
            if (!typeInput || !patientSelect) return;
            
            const type = typeInput.value;
            const option = patientSelect.options[patientSelect.selectedIndex];
            const selectedConsultant = consultantInput ? consultantInput.value.trim() : '';
            
            if (!type) {
                alert('Please select a referral type first');
                return;
            }
            
            if (!option.value) {
                alert('Please select a patient first');
                return;
            }
            
            const refNo = 'DW/' + new Date().toISOString().slice(0,10).replace(/-/g, '') + '/' + Math.floor(Math.random() * 100);
            const patientName = option.dataset.name;
            const patientAge = option.dataset.age;
            const currentDate = new Date().toISOString().split('T')[0];
            
            // Determine the greeting based on whether a consultant is selected
            let greeting = '';
            if (selectedConsultant) {
                greeting = `<p><strong>${selectedConsultant},</strong></p>`;
            }
            
            switch(type) {
                case 'referral':
                    const complaintInput = document.getElementById('referralComplaint');
                    const historyInput = document.getElementById('referralHistory');
                    const investigationsInput = document.getElementById('referralInvestigations');
                    
                    const complaint = complaintInput ? complaintInput.value : '';
                    const history = historyInput ? historyInput.value : '';
                    const investigations = investigationsInput ? investigationsInput.value : '';
                    
                    content = `
                        <div style="font-family: 'Times New Roman', serif; line-height: 1.6; max-width: 800px; margin: 0 auto;">
                            <p><strong>Ref No:</strong> ${refNo}</p>
                            <br>
                            <h2 style="text-align: center; text-decoration: underline; font-size: 24px; margin-bottom: 30px;">Medical Referral</h2>
                            
                            <p><strong>Re: Referral of ${patientName},</strong></p>
                            <br>
                            ${greeting}

                            <p><strong>Dear Sir,</strong></p>
                            <br>
                            <p>I am writing to refer <strong>${patientName}</strong>, a <strong>${patientAge} Years</strong> old patient, to you for further evaluation and management.</p>
                            <br>
                            <p><strong>Clinical Details:</strong></p>
                            <ul style="margin-left: 20px;">
                                <li><strong>Presenting complaint:</strong> ${complaint}</li>
                                <li><strong>History:</strong> ${history}</li>
                                <li><strong>Investigations:</strong> ${investigations}</li>
                            </ul>
                            <br>
                            <p>I would appreciate your expert assessment and recommendations regarding management.</p>
                            <br>
                            <p>Thank you for your attention to this referral.</p>
                            <br><br>
                            <div style="display: flex; justify-content: space-between; margin-top: 50px;">
                                <div>
                                    <p><strong>Date:</strong> ${currentDate}</p>
                                </div>
                                <div style="text-align: right;">
                                    <p><strong>Authorized Signature :</strong></p>
                                    ${getSignatureHTML()}
                                    <p>---------------------------</p>
                                    <p><strong>Dr. ${doctorName}</strong></p>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'financial':
                    const conditionInput = document.getElementById('financialCondition');
                    const condition = conditionInput ? conditionInput.value : '';
                    
                    content = `
                        <div style="font-family: 'Times New Roman', serif; line-height: 1.6; max-width: 800px; margin: 0 auto;">
                            <p><strong>Ref No:</strong> ${refNo}</p>
                            <br>
                            <h2 style="text-align: center; text-decoration: underline; font-size: 24px; margin-bottom: 30px;">Financial Support Letter</h2>
                            
                            <p><strong>To Whom It May Concern,</strong></p>
                            <br>
                            <p><strong>Subject: Request for Financial Support for ${patientName}</strong></p>
                            <br>
                            <p>I am writing on behalf of my patient, <strong>${patientName}</strong>, who is under my care for <strong>${condition}</strong>. ${patientName} is experiencing financial difficulties that affect their ability to afford necessary treatments and daily expenses.</p>
                            <br>
                            <p>Any financial support provided would significantly improve <strong>${patientName}</strong>'s well-being and ability to manage their condition. Please feel free to contact me if further information is needed.</p>
                            <br>
                            <p>Thank you for your support.</p>
                            <br><br>
                            <div style="display: flex; justify-content: space-between; margin-top: 50px;">
                                <div>
                                    <p><strong>Date:</strong> ${currentDate}</p>
                                </div>
                                <div style="text-align: right;">
                                    <p><strong>Authorized Signature :</strong></p>
                                    ${getSignatureHTML()}
                                    <p>---------------------------</p>
                                    <p><strong>Dr. ${doctorName}</strong></p>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'fitness':
                    const addressInput = document.getElementById('fitnessAddress');
                    const purposeInput = document.getElementById('fitnessPurpose');
                    
                    const address = addressInput ? addressInput.value : '';
                    const purpose = purposeInput ? purposeInput.value : '';
                    
                    content = `
                        <div style="font-family: 'Times New Roman', serif; line-height: 1.6; max-width: 800px; margin: 0 auto;">
                            <p><strong>Ref No:</strong> ${refNo}</p>
                            <br>
                            <h2 style="text-align: center; text-decoration: underline; font-size: 24px; margin-bottom: 30px;">Fitness Certificate</h2>
                            
                            <p><strong>To Whom It May Concern,</strong></p>
                            <br>
                            <p><strong>Subject: Fitness Certificate for ${patientName}</strong></p>
                            <br>
                            <p>This is to certify that I, <strong>Dr. ${doctorName}</strong>, have examined <strong>${patientName}</strong>, residing at <strong>${address}</strong>, on <strong>${currentDate}</strong>.</p>
                            <br>
                            <p>After a thorough medical assessment, I confirm that <strong>${patientName}</strong> is in good health and fit to <strong>${purpose}</strong>.</p>
                            <br>
                            <p>There are no medical conditions identified that would impair <strong>${patientName}</strong>'s ability to perform the required tasks or activities associated with <strong>${purpose}</strong>.</p>
                            <br>
                            <p>Should further details or clarification be required, please do not hesitate to contact our clinic.</p>
                            <br><br>
                            <div style="display: flex; justify-content: space-between; margin-top: 50px;">
                                <div>
                                    <p><strong>Date:</strong> ${currentDate}</p>
                                </div>
                                <div style="text-align: right;">
                                    <p><strong>Authorized Signature :</strong></p>
                                    ${getSignatureHTML()}
                                    <p>---------------------------</p>
                                    <p><strong>Dr. ${doctorName}</strong></p>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'ultrasound':
                    const ultrasoundTypeInput = document.getElementById('ultrasoundType');
                    const usComplaintInput = document.getElementById('referralComplaint');
                    const usHistoryInput = document.getElementById('referralHistory');
                    const usInvestigationInput = document.getElementById('referralInvestigations');
                    const consultanthosInput = document.getElementById('consultantHospital');
                    
                    const ultrasoundType = ultrasoundTypeInput ? ultrasoundTypeInput.value : '';
                    const usComplaint = usComplaintInput ? usComplaintInput.value : '';
                    const usHistory = usHistoryInput ? usHistoryInput.value : '';
                    const usInvestigation = usInvestigationInput ? usInvestigationInput.value : '';
                    const conhos = consultanthosInput ? consultanthosInput.value : '';

                    content = `
                        <div style="font-family: 'Times New Roman', serif; line-height: 1.6; max-width: 800px; margin: 0 auto;">
                            <p><strong>Ref No:</strong> ${refNo}</p>
                            <br>
                            <h2 style="text-align: center; text-decoration: underline; font-size: 24px; margin-bottom: 30px;">Ultrasound Scan Request</h2>
                            
                            <p><strong>Consultant Radiologist,</strong></p>
                            <p><strong> ${conhos},<strong></p>
                            <p><strong>Dear Sir:</strong></p>
                            <br>
                            <p><strong>Re: Ultrasound Scan Request</strong></p>
                            <br>
                            <p><strong>Patient Name:</strong> ${patientName}</p>
                            <p><strong>Age:</strong> ${patientAge} Years</p>
                            <br>
                            <p>I am referring my patient, <strong>${patientName}</strong>, a <strong>${patientAge} Years</strong> old patient, for <strong>${ultrasoundType}</strong>.</p>
                            <br>
                            <p><strong>Clinical Details:</strong></p>
                            <ul style="margin-left: 20px;">
                                <li><strong>Presenting Complain:</strong> ${usComplaint}</li>
                                <li><strong>History:</strong> ${usHistory}</li>
                                <li><strong>Investigation:</strong> ${usInvestigation}</li>
                            </ul>
                            <br>
                            <p>I would appreciate it if you could expedite the scan as the results are crucial for further management.</p>
                            <br>
                            <p>Thank you for your attention to this referral.</p>
                            <br><br>
                            <div style="display: flex; justify-content: space-between; margin-top: 50px;">
                                <div>
                                    <p><strong>Date:</strong> ${currentDate}</p>
                                </div>
                                <div style="text-align: right;">
                                    <p><strong>Authorized Signature :</strong></p>
                                    ${getSignatureHTML()}
                                    <p>---------------------------</p>
                                    <p><strong>Dr. ${doctorName}</strong></p>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'investigation':
                    const testsInput = document.getElementById('investigationTests');
                    const tests = testsInput ? testsInput.value : '';
                    
                    content = `
                        <div style="font-family: 'Times New Roman', serif; line-height: 1.6; max-width: 800px; margin: 0 auto;">
                            <p><strong>Ref No:</strong> ${refNo}</p>
                            <br>
                            <h2 style="text-align: center; text-decoration: underline; font-size: 24px; margin-bottom: 30px;">Medical Investigation</h2>
                            
                            <p><strong>Name :</strong> ${patientName}</p>
                            <p><strong>Age :</strong> ${patientAge} Years</p>
                            <br>
                            <p>Based on the patient's medical history and current condition, I recommend conducting the following tests:</p>
                            <br>
                            <p><strong>Ix:</strong></p>
                            <ol style="margin-left: 20px;">
                                ${tests.split('\n').filter(line => line.trim()).map((test, index) => 
                                    `<li>${test.trim()}</li>`
                                ).join('')}
                            </ol>
                            <br>
                            <p>If any additional information is required, feel free to contact me.</p>
                            <br><br>
                            <div style="display: flex; justify-content: space-between; margin-top: 50px;">
                                <div>
                                    <p><strong>Date:</strong> ${currentDate}</p>
                                </div>
                                <div style="text-align: right;">
                                    <p><strong>Authorized Signature :</strong></p>
                                    ${getSignatureHTML()}
                                    <p>---------------------------</p>
                                    <p><strong>Dr. ${doctorName}</strong></p>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'examination':
                    const examComplaintInput = document.getElementById('referralComplaint');
                    const examHistoryInput = document.getElementById('referralHistory');
                    const examInvestigationsInput = document.getElementById('referralInvestigations');
                    const examPurposeInput = document.getElementById('examinationPurpose');
                    const examFindingsInput = document.getElementById('examinationFindings');
                    
                    const examComplaint = examComplaintInput ? examComplaintInput.value : '';
                    const examHistory = examHistoryInput ? examHistoryInput.value : '';
                    const examInvestigations = examInvestigationsInput ? examInvestigationsInput.value : '';
                    const examPurpose = examPurposeInput ? examPurposeInput.value : '';
                    const examFindings = examFindingsInput ? examFindingsInput.value : '';
                    
                    content = `
                        <div style="font-family: 'Times New Roman', serif; line-height: 1.6; max-width: 800px; margin: 0 auto;">
                            <p><strong>Ref No:</strong> ${refNo}</p>
                            <br>
                            <h2 style="text-align: center; text-decoration: underline; font-size: 24px; margin-bottom: 30px;">Medical Examination Report</h2>
                            
                            <p><strong>Patient Name:</strong> ${patientName}</p>
                            <p><strong>Age:</strong> ${patientAge} Years</p>
                            <p><strong>Date of Examination:</strong> ${currentDate}</p>
                            <br>
                            <p><strong>Purpose of Examination:</strong> ${examPurpose}</p>
                            <br>
                            <p><strong>Clinical Details:</strong></p>
                            <ul style="margin-left: 20px;">
                                <li><strong>Presenting Complaint:</strong> ${examComplaint}</li>
                                <li><strong>History:</strong> ${examHistory}</li>
                                <li><strong>Previous Investigations:</strong> ${examInvestigations}</li>
                            </ul>
                            <br>
                            <p><strong>Examination Findings:</strong></p>
                            <p>${examFindings}</p>
                            <br>
                            <p><strong>Conclusion:</strong> Based on the clinical examination, the patient has been assessed and findings documented above for medical record purposes.</p>
                            <br><br>
                            <div style="display: flex; justify-content: space-between; margin-top: 50px;">
                                <div>
                                    <p><strong>Date:</strong> ${currentDate}</p>
                                </div>
                                <div style="text-align: right;">
                                    <p><strong>Authorized Signature :</strong></p>
                                    ${getSignatureHTML()}
                                    <p>---------------------------</p>
                                    <p><strong>Dr. ${doctorName}</strong></p>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
            }
            
            const referralPrint = document.getElementById('referralPrint');
            if (referralPrint) {
                referralPrint.innerHTML = content;
                referralPrint.classList.remove('hidden');
                
                setTimeout(() => {
                    window.print();
                }, 100);
            }
        }

        function generatePrescription() {
            const patientSelect = document.getElementById('prescriptionPatient');
            if (!patientSelect) return;
            
            const option = patientSelect.options[patientSelect.selectedIndex];
            
            if (!option.value) {
                alert('Please select a patient first');
                return;
            }
            
            if (addedMedicines.length === 0) {
                alert('Please add at least one medicine');
                return;
            }
            
            const refNo = 'DW/' + new Date().toISOString().slice(0,10).replace(/-/g, '') + '/' + Math.floor(Math.random() * 100);
            const patientName = option.dataset.name;
            const patientAge = option.dataset.age;
            
            const nextVisitInput = document.getElementById('prescriptionNextVisit');
            const nextVisit = nextVisitInput ? (nextVisitInput.value || 'N/A') : 'N/A';
            const currentDate = new Date().toISOString().split('T')[0];
            
            // Generate medicine rows for the prescription
            const medicineRows = addedMedicines.map(medicine => `
                <tr>
                    <td style="border: 1px solid #000; padding: 8px;">${medicine.name}</td>
                    <td style="border: 1px solid #000; padding: 8px;">${medicine.perDose} tablet(s) ${medicine.dosage}</td>
                    <td style="border: 1px solid #000; padding: 8px;">${medicine.days} days</td>
                </tr>
            `).join('');
            
            const content = `
                <div style="font-family: 'Times New Roman', serif; line-height: 1.6; max-width: 800px; margin: 0 auto;">
                    <p><strong>Ref No:</strong> ${refNo}</p>
                    <br>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                        <tr>
                            <td style="width: 50%;"><strong>Name :</strong> ${patientName}</td>
                            <td style="width: 50%;"><strong>Age :</strong> ${patientAge} Years</td>
                        </tr>
                        <tr>
                            <td colspan="2"><strong>Next Visit Date :</strong> ${nextVisit}</td>
                        </tr>
                    </table>
                    
                    <br>
                    
                    <h3 style="text-decoration: underline; margin-bottom: 15px;"><strong>Rx : (Outside)</strong></h3>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #000;">
                        <thead>
                            <tr style="background-color: #f0f0f0;">
                                <th style="border: 1px solid #000; padding: 8px; text-align: left;"><strong>Medicine</strong></th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left;"><strong>Dosage</strong></th>
                                <th style="border: 1px solid #000; padding: 8px; text-align: left;"><strong>Duration</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            ${medicineRows}
                        </tbody>
                    </table>
                    
                    <br><br>
                    <div style="display: flex; justify-content: space-between; margin-top: 50px;">
                        <div>
                            <p><strong>Date:</strong> ${currentDate}</p>
                        </div>
                        <div style="text-align: right;">
                            <p><strong>Authorized Signature :</strong></p>
                            ${getSignatureHTML()}
                            <p>---------------------------</p>
                            <p><strong>Dr. ${doctorName}</strong></p>
                        </div>
                    </div>
                </div>
            `;
            
            const prescriptionPrint = document.getElementById('prescriptionPrint');
            if (prescriptionPrint) {
                prescriptionPrint.innerHTML = content;
                prescriptionPrint.classList.remove('hidden');
                
                setTimeout(() => {
                    window.print();
                }, 100);
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
        }

        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            // Hide consultant suggestions
            if (!e.target.closest('.custom-dropdown')) {
                hideConsultantSuggestions();
            }
            
            // Hide medicine autocomplete
            if (!e.target.closest('.autocomplete-container')) {
                const medicineAutocomplete = document.getElementById('medicineAutocomplete');
                if (medicineAutocomplete) medicineAutocomplete.style.display = 'none';
            }
        });

        // Initialize dates and setup functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Set today's date for medical certificate
            const today = new Date().toISOString().split('T')[0];
            const medicalFromDate = document.getElementById('medicalFromDate');
            if (medicalFromDate) medicalFromDate.value = today;
            
            updateToDate();
            
            // Setup consultant autocomplete
            setupConsultantAutocomplete();
            
            // Setup medicine autocomplete
            setupMedicineAutocomplete();
            
            // Set next visit date to 7 days from today by default
            const nextWeek = new Date();
            nextWeek.setDate(nextWeek.getDate() + 7);
            const prescriptionNextVisit = document.getElementById('prescriptionNextVisit');
            if (prescriptionNextVisit) {
                prescriptionNextVisit.value = nextWeek.toISOString().split('T')[0];
            }
        });

// Modern smooth page transition system
document.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', e => {
        // Only handle internal links
        if (link.hostname === window.location.hostname && 
            !link.target && 
            !link.classList.contains('no-transition') &&
            !link.hash) {
            
            e.preventDefault();
            const targetUrl = link.href;
            
            // Prevent multiple clicks
            if (document.body.classList.contains('page-transitioning-out')) {
                return;
            }
            
            // Start smooth exit transition
            document.body.classList.add('page-transitioning-out');
            
            // Add subtle haptic feel with smooth timing
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 700);
        }
    });
});

// Smooth enter animation on page load
window.addEventListener('DOMContentLoaded', () => {
    // Prevent flash of unstyled content
    document.body.style.visibility = 'visible';
    document.body.classList.add('page-transitioning-in');
    
    setTimeout(() => {
        document.body.classList.remove('page-transitioning-in');
    }, 800);
});

// Handle browser navigation
window.addEventListener('pageshow', (e) => {
    if (e.persisted) {
        document.body.classList.remove('page-transitioning-out', 'page-transitioning-in');
    }
});

// Prevent flash on initial load
document.body.style.visibility = 'hidden';
    </script>
</body>
</html>