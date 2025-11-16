<?php
require_once '../config.php';
requireDoctor();

$message = '';
$error = '';
date_default_timezone_set('Asia/Colombo');

// Function to round to nearest 50
function roundToNearest50($amount) {
    return round($amount / 50) * 50;
}

$stmt = $pdo->prepare("
    SELECT dsc.*, ssi.sender_id, 
           (dsc.total_units - dsc.used_units) as remaining_units
    FROM doctor_sms_config dsc
    JOIN sms_sender_ids ssi ON dsc.sender_id = ssi.id
    WHERE dsc.doctor_id = ? AND dsc.is_active = 1
");
$stmt->execute([$_SESSION['user_id']]);
$smsConfig = $stmt->fetch();
$smsEnabled = $smsConfig && $smsConfig['remaining_units'] > 0;
$remainingSMSUnits = $smsConfig ? $smsConfig['remaining_units'] : 0;

// Function to add allergies (append new ones)
function updatePatientAllergies($pdo, $patient_type, $patient_id, $new_allergies) {
    try {
        // First get current allergies
        if ($patient_type === 'adult') {
            $stmt = $pdo->prepare("SELECT allergies FROM adults WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("SELECT allergies FROM kids WHERE id = ?");
        }
        $stmt->execute([$patient_id]);
        $current_data = $stmt->fetch();
        
        $current_allergies = $current_data['allergies'] ?? '';
        $new_allergies = trim($new_allergies);
        
        // If no new allergies provided, keep current
        if (empty($new_allergies)) {
            return $current_allergies;
        }
        
        // Combine allergies
        $updated_allergies = '';
        if (!empty($current_allergies) && !empty($new_allergies)) {
            // Check if new allergy already exists
            $current_items = array_map('trim', explode(',', $current_allergies));
            $new_items = array_map('trim', explode(',', $new_allergies));
            
            // Merge and remove duplicates
            $all_items = array_unique(array_merge($current_items, $new_items));
            $updated_allergies = implode(', ', array_filter($all_items));
        } elseif (!empty($new_allergies)) {
            $updated_allergies = $new_allergies;
        } else {
            $updated_allergies = $current_allergies;
        }
        
        // Update database
        if ($patient_type === 'adult') {
            $stmt = $pdo->prepare("UPDATE adults SET allergies = ? WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE kids SET allergies = ? WHERE id = ?");
        }
        $stmt->execute([$updated_allergies, $patient_id]);
        
        return $updated_allergies;
        
    } catch (Exception $e) {
        throw new Exception("Error updating allergies: " . $e->getMessage());
    }
}

// Handle investigation data submission FIRST - before any HTML
if ($_POST && isset($_POST['save_investigation'])) {
    // Clean any previous output
    ob_clean();
    
    // Set proper headers for JSON response
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        // Get form data
        $patient_type = $_POST['investigation_patient_type'] ?? '';
        $patient_id = $_POST['investigation_patient_id'] ?? '';
        $investigation_date = $_POST['investigation_date'] ?? '';
        $investigation_type = $_POST['investigation_type'] ?? '';
        $notes = trim($_POST['investigation_notes'] ?? '');
        
        // Validate required fields
        if (empty($patient_id)) {
            echo json_encode(['success' => false, 'error' => 'Patient ID is required']);
            exit;
        }
        
        if (empty($investigation_date)) {
            echo json_encode(['success' => false, 'error' => 'Investigation date is required']);
            exit;
        }
        
        if (empty($investigation_type) && empty($notes)) {
            echo json_encode(['success' => false, 'error' => 'Either select an investigation type OR enter notes']);
            exit;
        }
        
        // Check if database connection exists
        if (!isset($pdo)) {
            echo json_encode(['success' => false, 'error' => 'Database connection not available']);
            exit;
        }
        
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'User not logged in']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        // Collect investigation data
        $investigation_data = [];
        if (!empty($investigation_type) && isset($_POST['investigation_data'])) {
            foreach ($_POST['investigation_data'] as $param => $value) {
                if (!empty(trim($value))) {
                    $investigation_data[$param] = trim($value);
                }
            }
        }
        
        // If investigation type is selected but no data and no notes
        if (!empty($investigation_type) && empty($investigation_data) && empty($notes)) {
            echo json_encode(['success' => false, 'error' => 'Please enter at least one parameter value or add notes']);
            exit;
        }
        
        $final_investigation_type = !empty($investigation_type) ? $investigation_type : 'fbc';
        
        // Insert investigation
        $stmt = $pdo->prepare("INSERT INTO patient_investigations (patient_type, patient_id, doctor_id, investigation_date, investigation_type, test_data, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $result = $stmt->execute([
            $patient_type, 
            $patient_id, 
            $_SESSION['user_id'], 
            $investigation_date, 
            $final_investigation_type, 
            json_encode($investigation_data), 
            $notes
        ]);
        
        if ($result) {
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Investigation saved successfully']);
        } else {
            throw new Exception("Failed to save investigation");
        }
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit; // Important: Stop execution after handling AJAX request
}

// Mark token as completed when dispensing
if ($_POST && isset($_POST['dispense'])) {
    $patient_type = $_POST['patient_type'];
    $patient_id = $_POST['patient_id'];
    $symptoms = trim($_POST['symptoms']);
    $outside_prescription_medicines = $_POST['outside_medicines'] ?? [];
    $new_allergies = trim($_POST['allergies']);
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $consultation_fee = (float)($_POST['consultation_fee'] ?? 0);
    $medicines = $_POST['medicines'] ?? [];
    $next_visit_date = !empty($_POST['next_visit_date']) ? $_POST['next_visit_date'] : null;
    $reminder_days_before = (int)($_POST['reminder_days_before'] ?? 1);
    
    if ($patient_id && $symptoms && (!empty($medicines) || !empty($outside_prescription_medicines))) {
        try {
            $pdo->beginTransaction();
            $pdo->exec("SET time_zone = '+05:30'");
            
            // Update patient allergies
            $updated_allergies = updatePatientAllergies($pdo, $patient_type, $patient_id, $new_allergies);
            
            // Prepare outside prescription text from medicines array
            $outside_prescription_text = '';
            if (!empty($outside_prescription_medicines)) {
                $outside_items = [];
                foreach ($outside_prescription_medicines as $med) {
                    if (!empty(trim($med['name']))) {
                        $instruction = trim($med['name']);
                        if (!empty($med['per_dose']) && !empty($med['dosage']) && !empty($med['days'])) {
                            $instruction .= " - " . $med['per_dose'] . " tablet(s) " . $med['dosage'] . " for " . $med['days'] . " days";
                        }
                        $outside_items[] = $instruction;
                    }
                }
                $outside_prescription_text = implode("\n", $outside_items);
            }
            
            // Create e-receipt
            $stmt = $pdo->prepare("INSERT INTO e_receipts (patient_type, patient_id, symptoms, outside_prescription, diagnosis, doctor_id, consultation_fee) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$patient_type, $patient_id, $symptoms, $outside_prescription_text, $diagnosis, $_SESSION['user_id'], $consultation_fee]);
            $receipt_id = $pdo->lastInsertId();
            
            $medicine_total = 0;
            
            foreach ($medicines as $med) {
                $medicine_id = $med['medicine_id'];
                $quantity = (int)$med['quantity'];
                $dosage = trim($med['dosage']);
                $tablets_per_dose = (float)$med['tablets_per_dose'];
                
                if ($quantity > 0) {
                    // Get medicine details
                    $stmt = $pdo->prepare("SELECT price_per_tablet, current_stock FROM medicines WHERE id = ?");
                    $stmt->execute([$medicine_id]);
                    $medicine = $stmt->fetch();
                    
                    if ($medicine && $medicine['current_stock'] >= $quantity) {
                        $amount = $quantity * $medicine['price_per_tablet'];
                        $medicine_total += $amount;
                        
                        // Add to receipt items
                        $stmt = $pdo->prepare("INSERT INTO receipt_items (receipt_id, medicine_id, quantity_issued, dosage, tablets_per_dose, amount) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$receipt_id, $medicine_id, $quantity, $dosage, $tablets_per_dose, $amount]);
                        
                        // Update medicine stock
                        $new_stock = $medicine['current_stock'] - $quantity;
                        $stmt = $pdo->prepare("UPDATE medicines SET current_stock = ? WHERE id = ?");
                        $stmt->execute([$new_stock, $medicine_id]);
                    } else {
                        throw new Exception("Insufficient stock for medicine ID: $medicine_id");
                    }
                }
            }
            
            // Calculate rounded total
            $subtotal = $medicine_total + $consultation_fee;
            $total_amount = roundToNearest50($subtotal);
            
            // Update total amount in receipt
            $stmt = $pdo->prepare("UPDATE e_receipts SET total_amount = ? WHERE id = ?");
            $stmt->execute([$total_amount, $receipt_id]);
            
            // Mark token as completed
            $stmt = $pdo->prepare("UPDATE tokens SET status = 'completed', completed_at = NOW() WHERE patient_type = ? AND patient_id = ? AND doctor_id = ? AND token_date = CURDATE() AND status = 'waiting'");
            $stmt->execute([$patient_type, $patient_id, $_SESSION['user_id']]);
            
            // *** NEW: Save next visit appointment if provided ***
            $nextVisitMessage = '';
            if ($next_visit_date) {
                try {
                    // Validate date
                    $dateObj = DateTime::createFromFormat('Y-m-d', $next_visit_date);
                    if ($dateObj && $dateObj->format('Y-m-d') === $next_visit_date && strtotime($next_visit_date) >= strtotime('today')) {
                        
                        // Check for existing appointment on same date
                        $stmt = $pdo->prepare("
                            SELECT id FROM next_visit_appointments 
                            WHERE doctor_id = ? AND patient_type = ? AND patient_id = ? 
                            AND next_visit_date = ? AND status != 'cancelled'
                        ");
                        $stmt->execute([$_SESSION['user_id'], $patient_type, $patient_id, $next_visit_date]);
                        $existing = $stmt->fetch();
                        
                        if (!$existing) {
                            // Insert next visit appointment
                            $stmt = $pdo->prepare("
                                INSERT INTO next_visit_appointments 
                                (doctor_id, patient_type, patient_id, receipt_id, next_visit_date, reminder_days_before, status) 
                                VALUES (?, ?, ?, ?, ?, ?, 'scheduled')
                            ");
                            
                            $stmt->execute([
                                $_SESSION['user_id'],
                                $patient_type,
                                $patient_id,
                                $receipt_id,
                                $next_visit_date,
                                $reminder_days_before
                            ]);
                            
                            $nextVisitMessage = ' Next visit scheduled for ' . date('d M Y', strtotime($next_visit_date)) . '.';
                            
                            // Check SMS status
                            if (!$smsEnabled) {
                                $nextVisitMessage .= ' <span class="text-yellow-600">(SMS reminder unavailable - no units remaining)</span>';
                            } elseif ($smsConfig && $smsConfig['remaining_units'] <= 10) {
                                $nextVisitMessage .= ' <span class="text-yellow-600">(Warning: Only ' . $smsConfig['remaining_units'] . ' SMS units remaining)</span>';
                            } else {
                                $reminderDaysText = $reminder_days_before == 0 ? 'on visit day' : ($reminder_days_before == 1 ? '1 day before' : $reminder_days_before . ' days before');
                                $nextVisitMessage .= ' SMS reminder will be sent ' . $reminderDaysText . '.';
                            }
                        } else {
                            $nextVisitMessage = ' <span class="text-orange-600">Note: Appointment already exists for this date.</span>';
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error saving next visit: " . $e->getMessage());
                    $nextVisitMessage = ' <span class="text-red-600">Error scheduling next visit.</span>';
                }
            }
            
            $pdo->commit();
            $message = 'Medicines dispensed successfully! E-receipt created. Patient allergies updated: ' . $updated_allergies . $nextVisitMessage;
            
            // Clear form data
            $_POST = [];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error dispensing medicines: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill all required fields and select at least one medicine or outside prescription';
    }
}

// Handle new patient registration
if ($_POST && isset($_POST['add_patient'])) {
    $patient_type = $_POST['new_patient_type'];
    $name = trim($_POST['new_patient_name']);
    $phone = trim($_POST['new_phone_number']);
    $nic = trim($_POST['new_nic_number']);
    $birthday = $_POST['new_birthday'] ?? null;
    $age = $_POST['new_age'] ?? null;
    $parent_id = $_POST['parent_id'] ?? null;
    $allergies = trim($_POST['new_allergies']);
    
    if ($name) {
        try {
            if ($patient_type === 'adult') {
                $stmt = $pdo->prepare("INSERT INTO adults (name, phone_number, nic_number, birthday, age, allergies, doctor_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $phone, $nic, $birthday, $age, $allergies, $_SESSION['user_id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO kids (name, parent_id, birthday, age, allergies, doctor_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $parent_id, $birthday, $age, $allergies, $_SESSION['user_id']]);
            }
            $message = 'New patient registered successfully!';
        } catch (Exception $e) {
            $error = 'Error registering patient: ' . $e->getMessage();
        }
    }
}

// Handle file upload
if ($_POST && isset($_POST['upload_file']) && isset($_FILES['patient_file'])) {
    $patient_type = $_POST['file_patient_type'];
    $patient_id = $_POST['file_patient_id'];
    $file = $_FILES['patient_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = 'patient_' . $patient_id . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $stmt = $pdo->prepare("INSERT INTO file_uploads (patient_type, patient_id, file_name, file_path, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$patient_type, $patient_id, $file['name'], $file_path, $file['type'], $_SESSION['user_id']]);
            $message = 'File uploaded successfully!';
        } else {
            $error = 'Failed to upload file.';
        }
    }
}

// Get all medicines with stock for the logged-in doctor only
$medicines = [];
$stmt = $pdo->prepare("SELECT id, drug_name, current_stock, price_per_tablet FROM medicines WHERE current_stock > 0 AND doctor_id = ? ORDER BY drug_name");
$stmt->execute([$_SESSION['user_id']]);
$medicines = $stmt->fetchAll();

// Get all adults for parent selection (only for the logged-in doctor)
$adults = [];
$stmt = $pdo->prepare("SELECT id, name, phone_number FROM adults WHERE doctor_id = ? ORDER BY name");
$stmt->execute([$_SESSION['user_id']]);
$adults = $stmt->fetchAll();

// Get current user data for print section
$user = getCurrentUser();

// Fetch doctor's signature
$stmt = $pdo->prepare("SELECT file_path FROM doctor_images WHERE doctor_id = ? AND image_type = 'signature' AND is_active = 1");
$stmt->execute([$_SESSION['user_id']]);
$doctorSignature = $stmt->fetch();

// Fetch dosage types from database
$stmt = $pdo->prepare("SELECT code, description FROM dosage_types WHERE is_active = 1 ORDER BY id");
$stmt->execute();
$dosageTypes = $stmt->fetchAll();

// Get investigation parameters for modal (fixed charset issues)
$stmt = $pdo->prepare("SELECT investigation_type, parameter_name, parameter_code, normal_range, unit, sort_order FROM investigation_parameters WHERE is_active = 1 ORDER BY investigation_type, sort_order");
$stmt->execute();
$investigationParams = [];
while ($row = $stmt->fetch()) {
    $investigationParams[$row['investigation_type']][] = $row;
}

// Get all drug names for autocomplete
$stmt = $pdo->prepare("SELECT DISTINCT drug_name FROM medicines WHERE doctor_id = ? ORDER BY drug_name");
$stmt->execute([$_SESSION['user_id']]);
$allMedicines = $stmt->fetchAll(PDO::FETCH_COLUMN);
$medicineList = $allMedicines;

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
    "DCP Salbutamol - 100mcg",
    "DCP Salbutamol - 200mcg",
    "DCP Salbutamol - 400mcg",
    "DCP Formovent - 100mcg",
    "DCP Formovent - 200mcg",
    "DCP Formovent - 400mcg",
    "DCP Beclomenthasone - 200mcg",
    "DCP Beclomenthasone - 400mcg",
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drug Dispenser - Doctor Wallet</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="../icon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../icon.png">
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
        
        /* Autocomplete Styles */
        .autocomplete-container {
            position: relative;
            width: 100%;
        }
        
        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #d1d5db;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .autocomplete-suggestion {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        
        .autocomplete-suggestion:hover,
        .autocomplete-suggestion.selected {
            background-color: #f3f4f6;
        }
        
        .autocomplete-suggestion:last-child {
            border-bottom: none;
        }
        
        .patient-type-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 8px;
            font-weight: bold;
        }
        
        .adult-badge {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .kid-badge {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .no-results {
            padding: 10px 12px;
            color: #6b7280;
            font-style: italic;
            text-align: center;
        }
        
        .loading {
            padding: 10px 12px;
            color: #6b7280;
            text-align: center;
        }
        
        .selected-patient-info {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
        }
        
        .selected-patient-info h4 {
            margin: 0 0 8px 0;
            color: #0c4a6e;
            font-weight: bold;
        }
        
        .patient-detail {
            margin: 4px 0;
            font-size: 14px;
            color: #374151;
        }
        
        .patient-allergies {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 8px;
            margin-top: 8px;
            color: #991b1b;
            font-weight: 500;
        }
        
        .clear-patient-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 8px;
        }
        
        .clear-patient-btn:hover {
            background: #dc2626;
        }
        
        /* Medicine card styles */
        .medicine-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            background: white;
            transition: all 0.2s;
        }
        
        .medicine-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
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
            max-height: 150px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .autocomplete-suggestion {
            padding: 6px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            font-size: 0.875rem;
        }
        
        .autocomplete-suggestion:hover,
        .autocomplete-suggestion.selected {
            background-color: #f0f0f0;
        }
        
        .outside-medicine-table {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .medicine-add-section {
            background: #f0f9ff;
            border: 2px solid #3b82f6;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 20px;
            max-width: 90vw;
            max-height: 90vh;
            overflow-y: auto;
        }
        .medicine-group-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .group-item {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .group-item:hover {
            background-color: #f7fafc;
        }
        .group-item.selected {
            background-color: #e6fffa;
            border-color: #38b2ac;
        }
/* Medicine Groups Button Card Styles */
.medicine-group-list {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 5px;
}

.group-button-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 20px 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
    border: 2px solid transparent;
    color: white;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.group-button-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
}

.group-button-card.selected {
    border-color: #fbbf24;
    box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.4);
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
}

.group-name {
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 1px;
    color: white;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    line-height: 1.1;
}

.group-info {
    font-size: 9px;
    color: rgba(255, 255, 255, 0.8);
    font-weight: 400;
    line-height: 1;
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .group-button-card {
        padding: 16px 12px;
    }
    
    .group-name {
        font-size: 14px;
    }
    
    .group-info {
        font-size: 10px;
    }
}

/* Alternative color schemes for variety (optional) */
.group-button-card:nth-child(4n+1) {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.group-button-card:nth-child(4n+2) {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.group-button-card:nth-child(4n+3) {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.group-button-card:nth-child(4n+4) {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

/* Hover effects for different colors */
.group-button-card:nth-child(4n+2):hover {
    background: linear-gradient(135deg, #e785f0 0%, #f04464 100%);
}

.group-button-card:nth-child(4n+3):hover {
    background: linear-gradient(135deg, #3d9bfe 0%, #00e8fe 100%);
}

.group-button-card:nth-child(4n+4):hover {
    background: linear-gradient(135deg, #38d66b 0%, #2de8c7 100%);
}

/* Scrollbar styling for the groups list */
.medicine-group-list::-webkit-scrollbar {
    width: 6px;
}

.medicine-group-list::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.medicine-group-list::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.medicine-group-list::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Token Banner Styles */
@keyframes slideDown {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

#tokenBanner {
    animation: slideDown 0.5s ease-out;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@media print {
    #tokenBanner,
    #bannerSpacer {
        display: none !important;
    }
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
    <h1 class="text-xl md:text-2xl font-bold text-center md:text-left">Drug Dispenser</h1>

    <!-- Center menu (responsive stack) -->
    <div class="flex flex-wrap justify-center md:justify-center gap-3">
      <a href="patients.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-user-injured"></i>
        <span>Patients</span>
      </a>
      <a href="medicines.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-pills"></i>
        <span>Medicines</span>
      </a>
      <a href="file_upload.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-file-upload"></i>
        <span>File upload</span>
      </a>
      <a href="reports.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-chart-bar"></i>
        <span>Reports</span>
      </a>
      <a href="receipts.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-receipt"></i>
        <span>All receipts</span>
      </a>

      <!-- New Print Section Button -->
      <a href="print_section.php" class="group relative inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-pink-500 to-pink-600 hover:from-pink-600 hover:to-pink-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200">
        <i class="fas fa-print text-sm"></i>
        <span>Print Section</span>
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

    <div class="container mx-auto p-6">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
<!-- Token Display Banner -->
<div id="tokenBanner" class="no-print hidden fixed top-0 left-0 right-0 bg-red-600 text-white py-3 px-6 shadow-lg z-50">
    <div class="container mx-auto flex justify-between items-center">
        <div class="flex items-center space-x-4">
            <i class="fas fa-ticket-alt text-2xl animate-pulse"></i>
            <div>
                <div class="text-sm font-semibold">Current Token</div>
                <div class="text-xl font-bold">
                    #<span id="tokenNumber">----</span> - <span id="tokenPatientName">Waiting...</span>
                </div>
            </div>
        </div>
        <button onclick="dismissToken()" class="hover:bg-red-700 px-3 py-1 rounded">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>
</div>

<div id="bannerSpacer" class="hidden" style="height: 70px;"></div>
        <!-- Main Form Layout -->
        <form method="POST" id="dispenserForm">
            <!-- Top Row: Patient Information -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Patient Information</h2>
                    <button type="button" id="newPatientBtn" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-plus mr-2"></i>New Patient
                    </button>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Left Column: Patient Selection -->
                    <div class="space-y-4">
                        <div>
                            <!-- Patient Selection Section -->
                            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                                <h2 class="text-xl font-bold text-gray-800 mb-4">Patient Selection</h2>
            
                            <!-- Patient Search with Autocomplete -->
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Search Patient *</label>
                                <div class="autocomplete-container">
                                    <input 
                                        type="text" 
                                        id="patient_search_input" 
                                        placeholder="Type patient name, phone number, or NIC..." 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        autocomplete="off"
                                    />
                                    <div id="patient_autocomplete_suggestions" class="autocomplete-suggestions" style="display: none;"></div>
                                </div>
                
                                <!-- Hidden form fields for selected patient -->
                                    <input type="hidden" id="selected_patient_id" name="patient_id" />
                                    <input type="hidden" id="selected_patient_type" name="patient_type" />
                            </div>
            
                            <!-- Selected Patient Display -->
                                <div id="selected_patient_display" style="display: none;" class="selected-patient-info">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h4 id="patient_display_name"></h4>
                                            <div id="patient_display_details"></div>
                                            <div id="patient_display_allergies" style="display: none;" class="patient-allergies">
                                                <strong>Allergies:</strong> <span id="patient_allergies_text"></span>
                                            </div>
                                        </div>
                                        <button type="button" id="clear_patient_btn" class="clear-patient-btn">
                                            <i class="fas fa-times"></i> Clear
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Symptoms *</label>
                            <textarea name="symptoms" required rows="4" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                      placeholder="Describe patient symptoms"></textarea>
                        </div>
                    </div>

                    <!-- Right Column: Allergies and Diagnosis -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                <i class="fas fa-exclamation-triangle text-orange-500 mr-1"></i>
                                Patient Allergies
                            </label>
                            <div class="bg-orange-50 p-3 rounded-lg border-2 border-orange-200">
                                <div>
                                    <label class="text-sm font-medium text-orange-800 mb-1 block">Add New Allergies:</label>
                                    <input type="text" name="allergies" id="patient_allergies" 
                                           class="w-full px-3 py-2 border border-orange-300 rounded focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm" 
                                           placeholder="Type new allergies (comma separated)">
                                    <p class="text-xs text-orange-600 mt-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        New allergies will be added to existing ones
                                    </p>
                                </div>
                        <div id="current_allergies_display" class="mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg hidden">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                                <span class="font-medium text-yellow-800">Current Allergies:</span>
                                <span id="current_allergies_text" class="ml-2 text-yellow-700"></span>
                            </div>
                        </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Diagnosis</label>
                            <div class="flex gap-2">
                                <input type="text" name="diagnosis" 
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                       placeholder="Enter diagnosis">
                                <button type="button" id="uploadFileBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Patient Files Display -->
                        <div id="patient_files" class="space-y-2">
                            <!-- Patient files will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Medicine Selection and Display Row -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
                <!-- Left Column: Patient Reports -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-gray-800">Patient Reports</h2>
                        <button id="investigationsBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium disabled:opacity-50" disabled>
                            <i class="fas fa-flask mr-2"></i>Investigations
                        </button>
                    </div>
                    <div id="patient_reports" class="space-y-3" style="max-height: 400px; overflow-y: auto;">
                        <p class="text-gray-500 text-center py-8">Select a patient to view reports</p>
                    </div>
                </div>

                <!-- Right Column: Available Medicines -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-gray-800">Available Medicines</h2>
                        <div class="text-sm text-gray-600">
                            <input type="text" id="medicine_search" placeholder="Search medicine..." 
                                   class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm w-48">
                        </div>
                    </div>
                    
                    <!-- Medicine Add Section -->
                    <div class="medicine-add-section mb-4">
                        <h4 class="text-md font-semibold mb-3 text-blue-800">Add Medicine</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                            <div>
                                <label class="block text-sm font-medium text-blue-700 mb-1">Medicine Name</label>
                                <div class="autocomplete-container">
                                    <input type="text" id="medicineNameInput" 
                                           class="w-full px-2 py-1.5 border border-blue-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" 
                                           placeholder="Type medicine name..." autocomplete="off">
                                    <div id="medicineAutocomplete" class="autocomplete-suggestions"></div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-blue-700 mb-1">Tablets Per Dose</label>
                                <select id="medicineTabletsPerDose" class="w-full px-2 py-1.5 border border-blue-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                                    <option value="">Select</option>
                                    <option value="0.5">1/2</option>
                                    <option value="1">1</option>
                                    <option value="1.5">1 1/2</option>
                                    <option value="2">2</option>
                                    <option value="2.5">2 1/2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                            <div>
                                <label class="block text-sm font-medium text-blue-700 mb-1">Dosage</label>
                                <select id="medicineDosage" class="w-full px-2 py-1.5 border border-blue-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                                    <option value="">Select</option>
                                    <?php foreach ($dosageTypes as $dosage): ?>
                                        <option value="<?php echo htmlspecialchars($dosage['code']); ?>">
                                            <?php echo htmlspecialchars($dosage['code']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-blue-700 mb-1">Total Tablets</label>
                                <input type="number" id="medicineTotalTablets" 
                                       class="w-full px-2 py-1.5 border border-blue-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" 
                                       placeholder="Total" min="1">
                            </div>
                            <div class="flex items-end">
                                <button type="button" onclick="addMedicine()" 
                                        class="w-full bg-blue-600 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700 flex items-center justify-center">
                                    <i class="fas fa-plus mr-1"></i> Add
                                </button>
                            </div>
                        </div>
                        <div id="medicineInfo" class="text-sm text-blue-700 hidden">
                            <p><strong>Available Stock:</strong> <span id="availableStock">0</span></p>
                            <p><strong>Price per tablet:</strong> Rs. <span id="pricePerTablet">0.00</span></p>
                            <p><strong>Total Price:</strong> Rs. <span id="totalPrice">0.00</span></p>
                        </div>
                    </div>
                    
                    <div style="max-height: 300px; overflow-y: auto;" id="medicines_container">
                        <h4 class="text-md font-semibold mb-2 text-gray-700">Available Medicines</h4>
                        <?php foreach ($medicines as $medicine): ?>
                            <div class="medicine-card" data-medicine-id="<?php echo $medicine['id']; ?>" data-drug-name="<?php echo strtolower($medicine['drug_name']); ?>">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-800 mb-1"><?php echo htmlspecialchars($medicine['drug_name']); ?></h4>
                                        <div class="text-sm text-gray-600">
                                            <span class="mr-3">Stock: <strong><?php echo $medicine['current_stock']; ?></strong></span>
                                            <span>Price: <strong><?php echo formatCurrency($medicine['price_per_tablet']); ?>/tab</strong></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

<!-- Add this to your Selected Medicines Table section, replace the existing table -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-gray-800">Selected Medicines</h2>
        <div class="space-x-2">
            <button type="button" id="groupedMedicinesBtn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                <i class="fas fa-layer-group mr-2"></i>Grouped Medicines
            </button>
            <button type="button" id="addNewMedicineGroupBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors" disabled>
                <i class="fas fa-plus-circle mr-2"></i>Add New Medicine Group
            </button>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full table-auto" id="selected_medicines_table">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Drug</th>
                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Tab/Dose</th>
                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Frequency</th>
                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">T.Tabs</th>
                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Price</th>
                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Action</th>
                </tr>
            </thead>
            <tbody id="selected_medicines_body">
                <tr id="no_medicines_row">
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No medicines selected</td>
                </tr>
            </tbody>
        </table>
                    
                    <!-- Outside Prescription Section with Enhanced Medicine Management -->
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6 border-t-4 pt-4 mt-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-4">
                                <i class="fas fa-prescription-bottle text-purple-600 mr-2"></i>
                                Outside Prescription (OC)
                            </h3>
                            
                            <!-- Medicine Entry Section -->
                            <div class="bg-purple-50 p-4 rounded-lg mb-4 border-2 border-purple-200">
                                <h4 class="text-md font-semibold mb-3 text-purple-800">Add Medicine</h4>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-3">
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-purple-700 mb-1">Medicine Name</label>
                                        <div class="autocomplete-container">
                                            <input type="text" id="outsideMedicineNameInput" 
                                                   class="w-full px-2 py-1.5 border border-purple-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-purple-400" 
                                                   placeholder="Type medicine name..." autocomplete="off">
                                            <div id="outsideMedicineAutocomplete" class="autocomplete-suggestions"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-purple-700 mb-1">Per Dose</label>
                                        <select id="outsideMedicinePerDose" class="w-full px-2 py-1.5 border border-purple-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-purple-400">
                                            <option value="">Select</option>
                                            <option value="0.5">1/2</option>
                                            <option value="1">1</option>
                                            <option value="1.5">1 1/2</option>
                                            <option value="2">2</option>
                                            <option value="2.5">2 1/2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-purple-700 mb-1">Dosage</label>
                                        <select id="outsideMedicineDosage" class="w-full px-2 py-1.5 border border-purple-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-purple-400">
                                            <option value="">Select</option>
                                            <?php foreach ($dosageTypes as $dosage): ?>
                                                <option value="<?php echo htmlspecialchars($dosage['code']); ?>">
                                                    <?php echo htmlspecialchars($dosage['code']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2 mb-3">
                                    <div>
                                        <label class="block text-sm font-medium text-purple-700 mb-1">Days</label>
                                        <input type="number" id="outsideMedicineDays" 
                                               class="w-full px-2 py-1.5 border border-purple-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-purple-400" 
                                               placeholder="Days" min="1" value="5">
                                    </div>
                                    <div class="flex items-end">
                                        <button type="button" onclick="addOutsideMedicine()" 
                                                class="w-full bg-purple-600 text-white px-3 py-1.5 rounded text-sm hover:bg-purple-700 flex items-center justify-center">
                                            <i class="fas fa-plus mr-1"></i> Add
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Outside Medicine Table -->
                            <div class="mb-4">
                                <h4 class="text-md font-semibold mb-2 text-purple-800">Added Medicines</h4>
                                <div class="outside-medicine-table border rounded-lg border-purple-200">
                                    <table class="w-full text-sm" id="outsideMedicineTable">
                                        <thead class="bg-purple-50">
                                            <tr>
                                                <th class="px-2 py-1.5 text-left text-purple-700">Medicine</th>
                                                <th class="px-2 py-1.5 text-left text-purple-700">Dosage</th>
                                                <th class="px-2 py-1.5 text-left text-purple-700">Days</th>
                                                <th class="px-2 py-1.5 text-left text-purple-700">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="outsideMedicineTableBody">
                                            <tr id="noOutsideMedicineRow">
                                                <td colspan="4" class="px-2 py-4 text-center text-gray-500 text-sm">No medicines added yet</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Print Button for Outside Prescription -->
                            <button type="button" id="printOutsidePrescriptionBtn" 
                                    class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm">
                                <i class="fas fa-print mr-1"></i>Print Outside Prescription
                            </button>
                        </div>
                        
                        <!-- Consultation Fee Section -->
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Consultation Fee</label>
                            <div class="space-y-3">
                                <!-- Quick Fee Buttons -->
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="fee-btn px-3 py-1 bg-blue-100 text-blue-800 rounded-md text-sm hover:bg-blue-200" data-fee="500">Rs. 500</button>
                                    <button type="button" class="fee-btn px-3 py-1 bg-blue-100 text-blue-800 rounded-md text-sm hover:bg-blue-200" data-fee="1000">Rs. 1000</button>
                                    <button type="button" class="fee-btn px-3 py-1 bg-blue-100 text-blue-800 rounded-md text-sm hover:bg-blue-200" data-fee="1500">Rs. 1500</button>
                                    <button type="button" class="fee-btn px-3 py-1 bg-blue-100 text-blue-800 rounded-md text-sm hover:bg-blue-200" data-fee="2000">Rs. 2000</button>
                                </div>
                                
                                <!-- Custom Fee Input -->
                                <div class="flex gap-2 items-center">
                                    <span class="text-sm text-gray-600">Custom:</span>
                                    <input type="number" name="consultation_fee" id="consultation_fee" min="0" step="50" 
                                           value="<?php echo isset($_POST['consultation_fee']) ? htmlspecialchars($_POST['consultation_fee']) : '0'; ?>"
                                           class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-32" 
                                           placeholder="0">
                                    <span class="text-lg text-red-600" id="final_total">Rs. 0.00</span>
                                </div>
<div class="bg-white rounded-lg shadow-md p-6 mb-6 border-t-4 border-t-green-500">
    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
        <i class="fas fa-calendar-check text-green-600 mr-2"></i>
        Next Visit Date (Optional)
        <?php if (!$smsEnabled): ?>
            <span class="ml-3 text-xs bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                SMS Reminders Disabled - No Units Available
            </span>
        <?php else: ?>
            <span class="ml-3 text-xs bg-green-100 text-green-800 px-3 py-1 rounded-full">
                <i class="fas fa-check-circle mr-1"></i>
                SMS Reminders Active (<?php echo $remainingSMSUnits; ?> units)
            </span>
        <?php endif; ?>
    </h3>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Direct Date Selection -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-calendar-alt text-blue-500 mr-1"></i>
                Select Date Directly
            </label>
            <input type="date" 
                   id="nextVisitDate" 
                   name="next_visit_date"
                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                   placeholder="Select next visit date">
            <p class="text-xs text-gray-500 mt-1">Choose a specific date for next visit</p>
        </div>
        
        <!-- OR Divider -->
        <div class="flex items-center justify-center">
            <span class="text-gray-400 font-semibold">OR</span>
        </div>
        
        <!-- Calculate from Days -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-calculator text-purple-500 mr-1"></i>
                Calculate from Days
            </label>
            <div class="flex gap-2">
                <input type="number" 
                       id="daysToNextVisit" 
                       min="1" 
                       max="365"
                       placeholder="Days"
                       class="w-24 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                <button type="button" 
                        id="calculateNextVisitBtn"
                        class="flex-1 bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-150">
                    <i class="fas fa-magic mr-1"></i>
                    Calculate Date
                </button>
            </div>
            <p class="text-xs text-gray-500 mt-1">Enter days from today</p>
        </div>
    </div>
    
    <!-- Reminder Settings -->
    <div class="mt-4 pt-4 border-t border-gray-200">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-bell text-orange-500 mr-1"></i>
                    Send SMS Reminder
                </label>
                <select id="reminderDaysBefore" 
                        name="reminder_days_before"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                        <?php echo !$smsEnabled ? 'disabled' : ''; ?>>
                    <option value="0">On the same day</option>
                    <option value="1" selected>1 day before</option>
                    <option value="2">2 days before</option>
                    <option value="3">3 days before</option>
                    <option value="7">1 week before</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">
                    <?php if ($smsEnabled): ?>
                        Automatic SMS reminder will be sent
                    <?php else: ?>
                        SMS reminders unavailable - contact admin for units
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="flex items-end">
                <div id="nextVisitSummary" class="w-full bg-blue-50 border border-blue-200 rounded-lg p-3 hidden">
                    <div class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Next Visit:</strong> <span id="summaryDate">-</span><br>
                        <i class="fas fa-sms mr-1"></i>
                        <strong>SMS Reminder:</strong> <span id="summaryReminder">-</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Clear Button -->
    <div class="mt-4">
        <button type="button" 
                id="clearNextVisitBtn"
                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm">
            <i class="fas fa-times mr-1"></i>
            Clear Next Visit
        </button>
    </div>
</div>

<!-- SMS Units Warning Modal -->
<div id="smsUnitsWarningModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-900">SMS Units Low</h3>
                    <p class="text-sm text-gray-600">Action Required</p>
                </div>
            </div>
            
            <div class="mb-6">
                <p class="text-gray-700 mb-2">
                    You have <strong class="text-red-600" id="remainingUnitsText">0</strong> SMS units remaining.
                </p>
                <p class="text-gray-600 text-sm">
                    Next visit appointment will be saved, but automatic SMS reminders cannot be sent until you add more units.
                </p>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" 
                        id="closeUnitsWarningBtn"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg text-sm font-medium">
                    I Understand
                </button>
                <button type="button" 
                        onclick="window.open('/pages/sms_new.php', '_blank')"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-1"></i>
                    Contact Admin
                </button>
            </div>
        </div>
    </div>
</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="bg-white rounded-lg shadow p-6">
                <button type="submit" name="dispense" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-lg text-lg">
                    Dispense Medicines & Generate Receipt
                </button>
            </div>
        </form>
    </div>

    <!-- New Patient Modal -->
    <div id="newPatientModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <form method="POST" id="newPatientForm">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Add New Patient</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Patient Type *</label>
                        <select name="new_patient_type" id="new_patient_type" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Type</option>
                            <option value="adult">Adult</option>
                            <option value="kid">Kid</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Full Name *</label>
                        <input type="text" name="new_patient_name" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Birthday and Age Fields (for both adults and kids) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Birthday (Optional)</label>
                            <input type="date" name="new_birthday" id="new_birthday" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Age (Optional)</label>
                            <input type="number" name="new_age" id="new_age" min="0" max="120" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter age">
                        </div>
                    </div>
                    
                    <div id="adult_fields" class="space-y-4 hidden">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Phone Number</label>
                            <input type="tel" name="new_phone_number" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">NIC Number</label>
                            <input type="text" name="new_nic_number" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div id="kid_fields" class="hidden">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Parent/Guardian *</label>
                        <select name="parent_id" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Parent</option>
                            <?php foreach ($adults as $adult): ?>
                                <option value="<?php echo $adult['id']; ?>"><?php echo htmlspecialchars($adult['name']); ?> (<?php echo $adult['phone_number']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Allergies</label>
                        <textarea name="new_allergies" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter any known allergies (optional)"></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" id="cancelNewPatient" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400">Cancel</button>
                    <button type="submit" name="add_patient" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">Add Patient</button>
                </div>
            </form>
        </div>
    </div>

    <!-- File Upload Modal -->
    <div id="fileUploadModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <form method="POST" enctype="multipart/form-data" id="fileUploadForm">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Upload Patient File</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Patient</label>
                        <input type="text" id="file_patient_display" readonly class="w-full px-3 py-2 border border-gray-300 rounded bg-gray-100">
                        <input type="hidden" name="file_patient_type" id="file_patient_type">
                        <input type="hidden" name="file_patient_id" id="file_patient_id">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Select File *</label>
                        <input type="file" name="patient_file" required accept="image/*,.pdf,.doc,.docx" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Supported: Images, PDF, Word documents</p>
                    </div>
                </div>
                
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" id="cancelFileUpload" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400">Cancel</button>
                    <button type="submit" name="upload_file" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Outside Prescription Print Modal -->
    <div id="outsidePrescriptionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
            <div class="p-6 no-print">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-2xl font-bold">Outside Prescription</h3>
                    <button onclick="closeOutsidePrescriptionModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Next Visit Date</label>
                        <input type="date" id="printNextVisit" class="w-full p-2 border border-gray-300 rounded">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button onclick="generateOutsidePrescriptionPrint()" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Generate & Print</button>
                    <button onclick="closeOutsidePrescriptionModal()" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">Close</button>
                </div>
            </div>
            
            <div id="outsidePrescriptionPrint" class="print-content p-8 hidden signature-watermark">
                <!-- Outside prescription content will be generated here -->
            </div>
        </div>
    </div>

<div id="investigationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <div class="bg-blue-600 text-white p-4 flex justify-between items-center">
                <h3 class="text-lg font-semibold">Patient Investigations</h3>
                <button id="closeInvestigationModal" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-200px)]">
                <!-- Debug info display -->
                <div id="debugInfo" class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4 hidden"></div>
                
                <form id="investigationForm" method="POST">
                    <input type="hidden" id="investigation_patient_type" name="investigation_patient_type">
                    <input type="hidden" id="investigation_patient_id" name="investigation_patient_id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Investigation Date *</label>
                            <input type="date" id="investigation_date" name="investigation_date" 
                                   class="w-full p-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Investigation Type</label>
                            <select id="investigation_type" name="investigation_type" 
                                    class="w-full p-2 border border-gray-300 rounded-md">
                                <option value="">Select Investigation Type</option>
                                <option value="fbc">FBC (Full Blood Count)</option>
                                <option value="sugar">Sugar Profile</option>
                                <option value="vitals">Vital Signs</option>
                                <option value="lipid_profile">Lipid Profile</option>
                                <option value="renal_function">Renal Function</option>
                                <option value="electrolytes">Electrolytes</option>
                                <option value="liver_function">Liver Function</option>
                                <option value="inflammatory_markers">Inflammatory Markers</option>
                                <option value="urine_report">Urine Analysis</option>
                                <option value="thyroid_function">Thyroid Function</option>
                            </select>
                        </div>
                    </div>

                    <!-- Dynamic Investigation Parameters -->
                    <div id="investigationParametersContainer" class="mb-6"></div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Investigation Notes</label>
                        <textarea id="investigation_notes" name="investigation_notes" rows="3" 
                                  class="w-full p-2 border border-gray-300 rounded-md" 
                                  placeholder="Enter any additional notes..."></textarea>
                    </div>

                    <!-- Investigation History -->
                    <div id="existingInvestigations" class="mb-6">
                        <h4 class="text-lg font-semibold text-gray-800 mb-3">Previous Investigations</h4>
                        <div id="investigationHistory" class="space-y-3 max-h-60 overflow-y-auto">
                            <!-- Will load dynamically -->
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" id="cancelInvestigation" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit" name="save_investigation" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Save Investigation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Grouped Medicines Modal -->
<div id="groupedMedicinesModal" class="modal hidden">
    <div class="modal-content w-full max-w-6xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Select Medicine Group</h3>
            <button type="button" id="closeGroupedMedicinesModal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Left Side - Groups List -->
            <div>
                <h4 class="text-lg font-semibold text-gray-700 mb-3">Medicine Groups</h4>
                <div id="medicineGroupsList" class="medicine-group-list">
                    <div class="flex items-center justify-center py-8 text-gray-500">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Loading groups...
                    </div>
                </div>
            </div>
            
            <!-- Right Side - Group Medicines -->
            <div>
                <h4 class="text-lg font-semibold text-gray-700 mb-3">Medicines in Group</h4>
                <div id="groupMedicinesContainer" class="border border-gray-200 rounded-lg p-4 min-h-[300px]">
                    <div class="text-center text-gray-500 py-8">
                        Select a group to view medicines
                    </div>
                </div>
                
                <div class="mt-4 flex justify-end space-x-2">
                    <button type="button" id="cancelGroupSelection" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md">
                        Cancel
                    </button>
                    <button type="button" id="addGroupMedicinesToSelected" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md" disabled>
                        Add Selected Group Medicines
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add New Medicine Group Modal -->
<div id="addNewMedicineGroupModal" class="modal hidden">
    <div class="modal-content w-full max-w-4xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Create New Medicine Group</h3>
            <button type="button" id="closeAddNewMedicineGroupModal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form id="newMedicineGroupForm" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Group Name</label>
                <input type="text" id="newGroupName" class="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                       placeholder="Enter group name" required>
            </div>
            
            <div>
                <h4 class="text-lg font-semibold text-gray-700 mb-3">Medicines in this Group</h4>
                <div id="newGroupMedicinesContainer" class="border border-gray-200 rounded-lg p-4 min-h-[300px]">
                    <div class="space-y-3" id="newGroupMedicinesList">
                        <!-- Medicines will be populated here -->
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-2 pt-4">
                <button type="button" id="cancelNewGroup" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md">
                    <i class="fas fa-save mr-2"></i>Save Group
                </button>
            </div>
        </form>
    </div>
</div>

    <!-- Footer -->
    <footer class="bg-gradient-to-r from-blue-800 via-blue-700 to-blue-800 text-white mt-12 shadow-2xl print-hide" style="border-radius: 50px 50px 0 0;">
        <div class="container mx-auto px-6 py-8">
            <div class="text-center">
                <p class="text-indigo-300 text-sm mb-2">
                    Copyright © 2025 Doctor Wallet. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

<!-- Hidden form fields for patient data -->
<input type="hidden" id="selected_patient_id" name="patient_id" value="">
<input type="hidden" id="selected_patient_type" name="patient_type" value="">
<script>
    let medicineCounter = 0;
    let currentMedicine = null;
    let selectedMedicines = [];
    let outsideMedicines = [];
    let currentPatientAllergies = '';
    let currentPatientData = null;
    let outsideMedicineCounter = 0;
    let selectedSuggestionIndex = -1;
    let availableMedicines = {};
    let searchTimeout = null;
    
    // Medicine Groups Variables
    let selectedGroupId = null;
    let selectedGroupMedicines = [];
    let medicineGroups = [];

    // Medicine list for autocomplete
    const medicineList = <?php echo json_encode($medicineList); ?>;

    // Investigation parameters data
    const investigationParams = <?php echo json_encode($investigationParams); ?>;

    // Available medicines data
    <?php foreach ($medicines as $medicine): ?>
    availableMedicines[<?php echo $medicine['id']; ?>] = {
        id: <?php echo $medicine['id']; ?>,
        name: "<?php echo addslashes($medicine['drug_name']); ?>",
        stock: <?php echo $medicine['current_stock']; ?>,
        price: <?php echo $medicine['price_per_tablet']; ?>
    };
    <?php endforeach; ?>

    // Function to round to nearest 50
    function roundToNearest50(amount) {
        return Math.celi(amount / 50) * 50;
    }

    // Dosage descriptions
    const dosageDescriptions = {
        'M': 'M', 'N': 'N', 'Bd': 'Bd', 'Tds': 'Tds',
        'Qds': 'Qds', 'SOS': 'SOS', 'EOD': 'EOD',
        'STAT': 'STAT', 'VESP': 'VESP', 'NOON': 'NOON',
        '3H': '3H', '4H': '4H', '6H': '6H',
        '8H': '8H', 'WEEKLY': 'WEEKLY', '5X': '5X'
    };

    // MEDICINE GROUPS FUNCTIONALITY

    // Enable/disable Add New Medicine Group button based on selected medicines
    function updateAddNewGroupButton() {
        const hasSelectedMedicines = selectedMedicines.length > 0;
        $('#addNewMedicineGroupBtn').prop('disabled', !hasSelectedMedicines);
        
        if (hasSelectedMedicines) {
            $('#addNewMedicineGroupBtn').removeClass('bg-blue-400').addClass('bg-blue-600 hover:bg-blue-700');
        } else {
            $('#addNewMedicineGroupBtn').removeClass('bg-blue-600 hover:bg-blue-700').addClass('bg-blue-400');
        }
    }

    // Open Grouped Medicines Modal
    $(document).on('click', '#groupedMedicinesBtn', function() {
        loadMedicineGroups();
        $('#groupedMedicinesModal').removeClass('hidden');
    });

    // Open Add New Medicine Group Modal
    $(document).on('click', '#addNewMedicineGroupBtn', function() {
        if (selectedMedicines.length === 0) {
            alert('Please select medicines first to create a group');
            return;
        }
        
        populateNewGroupMedicines();
        $('#addNewMedicineGroupModal').removeClass('hidden');
    });

    // Close modals
    $(document).on('click', '#closeGroupedMedicinesModal, #cancelGroupSelection', function() {
        $('#groupedMedicinesModal').addClass('hidden');
        resetGroupSelection();
    });

    $(document).on('click', '#closeAddNewMedicineGroupModal, #cancelNewGroup', function() {
        $('#addNewMedicineGroupModal').addClass('hidden');
        $('#newMedicineGroupForm')[0].reset();
    });

    // Load Medicine Groups
    function loadMedicineGroups() {
        const container = $('#medicineGroupsList');
        if (!container.length) return;
        
        container.html('<div class="flex items-center justify-center py-8 text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading groups...</div>');
        
        $.ajax({
            url: '../ajax/get_medicine_groups.php',
            type: 'GET',
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                console.log('Groups response:', response);
                if (response.success && response.groups) {
                    displayMedicineGroups(response.groups);
                    medicineGroups = response.groups;
                } else {
                    container.html('<div class="text-center text-gray-500 py-8">No medicine groups found</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading medicine groups:', {xhr: xhr, status: status, error: error});
                let errorMsg = 'Error loading groups';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                } else if (xhr.responseText) {
                    errorMsg = 'Server error: ' + xhr.responseText.substring(0, 100);
                }
                container.html('<div class="text-center text-red-500 py-8">' + errorMsg + '</div>');
            }
        });
    }

// Updated displayMedicineGroups function with small pill-shaped button cards
function displayMedicineGroups(groups) {
    const container = $('#medicineGroupsList');
    if (!container.length) return;
    
    let html = '';
    
    if (groups.length === 0) {
        html = '<div class="text-center text-gray-500 py-8">No medicine groups found</div>';
    } else {
        // Create a flex container for the small button cards
        html += '<div class="flex flex-wrap gap-2 justify-start">';
        
        groups.forEach(group => {
            // Prioritize medicine count over date for the small info text
            const secondaryInfo = group.medicine_count > 0 
                ? `${group.medicine_count} meds` 
                : new Date(group.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            
            html += `
                <div class="group-item group-button-card" data-group-id="${group.id}">
                    <div class="group-name">${group.group_name}</div>
                    <div class="group-info">${secondaryInfo}</div>
                </div>
            `;
        });
        
        html += '</div>';
    }
    
    container.html(html);
    
    // Add click handlers
    container.find('.group-item').off('click').on('click', function() {
        const groupId = $(this).data('group-id');
        selectMedicineGroup(groupId);
    });
}

    // Select Medicine Group
    function selectMedicineGroup(groupId) {
        selectedGroupId = groupId;
        
        // Update UI
        $('.group-item').removeClass('selected');
        $(`.group-item[data-group-id="${groupId}"]`).addClass('selected');
        
        // Load group medicines
        loadGroupMedicines(groupId);
    }

// Enhanced Load Group Medicines with detailed debugging
    function loadGroupMedicines(groupId) {
        const container = $('#groupMedicinesContainer');
        if (!container.length) return;
        
        console.log('loadGroupMedicines called with groupId:', groupId);
        
        container.html('<div class="flex items-center justify-center py-8 text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading medicines...</div>');
        
        $.ajax({
            url: '../ajax/get_group_medicines.php',
            type: 'GET',
            data: { group_id: groupId },
            dataType: 'json',
            timeout: 15000,
            cache: false,
            success: function(response) {
                console.log('Full response from get_group_medicines.php:', response);
                
                if (response && response.success) {
                    console.log('Response success, medicines count:', response.medicines ? response.medicines.length : 0);
                    console.log('Medicines data:', response.medicines);
                    console.log('Debug info - total items:', response.debug_total_items);
                    console.log('Debug info - total joined:', response.debug_total_joined);
                    
                    if (response.medicines && response.medicines.length > 0) {
                        displayGroupMedicines(response.medicines, response.group_name);
                        selectedGroupMedicines = response.medicines;
                        $('#addGroupMedicinesToSelected').prop('disabled', false);
                    } else {
                        console.warn('No medicines found in response');
                        container.html(`
                            <div class="text-center text-gray-500 py-8">
                                <p>No medicines in this group</p>
                                ${response.debug_total_items ? '<p class="text-xs text-red-500 mt-2">Debug: Found ' + response.debug_total_items + ' items in database but ' + (response.debug_total_joined || 0) + ' after JOIN</p>' : ''}
                            </div>
                        `);
                        $('#addGroupMedicinesToSelected').prop('disabled', true);
                    }
                } else {
                    console.error('Response indicates failure:', response);
                    container.html('<div class="text-center text-red-500 py-8">Error: ' + (response.error || 'Unknown error') + '</div>');
                    $('#addGroupMedicinesToSelected').prop('disabled', true);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error in loadGroupMedicines:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                
                const errorMessage = handleAjaxError(xhr, status, error, 'loadGroupMedicines');
                container.html(`<div class="text-center text-red-500 py-8">
                    <i class="fas fa-exclamation-triangle mb-2"></i><br>
                    ${errorMessage}
                    <br><button onclick="loadGroupMedicines(${groupId})" class="mt-2 px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">Retry</button>
                </div>`);
                $('#addGroupMedicinesToSelected').prop('disabled', true);
            }
        });
    }

    // Enhanced Display Group Medicines with detailed logging
    function displayGroupMedicines(medicines, groupName) {
        const container = $('#groupMedicinesContainer');
        if (!container.length) return;
        
        console.log('displayGroupMedicines called with:', medicines.length, 'medicines for group:', groupName);
        
        let html = `<div class="mb-3 font-medium text-gray-700">Group: ${groupName}</div>`;
        
        if (medicines.length === 0) {
            html += '<div class="text-center text-gray-500 py-8">No medicines in this group</div>';
        } else {
            html += '<div class="space-y-3">';
            
            medicines.forEach((medicine, index) => {
                console.log('Processing medicine:', medicine);
                
                // Ensure we have required fields
                const medicineName = medicine.drug_name || 'Unknown Medicine';
                const medicineId = medicine.medicine_id || medicine.id;
                const currentStock = medicine.current_stock || 0;
                const pricePerTablet = medicine.price_per_tablet || 0;
                const tabletsPerDose = medicine.tablets_per_dose || 1;
                const dosage = medicine.dosage || 'Bd';
                const totalTablets = medicine.total_tablets || 10;
                
                html += `
                    <div class="group-medicine-item border border-gray-200 rounded-lg p-3" data-medicine-id="${medicineId}">
                        <div class="flex items-center justify-between mb-2">
                            <div class="font-semibold text-gray-800">${medicineName}</div>
                            <div class="text-sm text-gray-600">Stock: ${currentStock} | Price: Rs. ${pricePerTablet}</div>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Tab/Dose</label>
                                <input type="number" step="0.5" min="0.5" value="${tabletsPerDose}" 
                                       class="w-full p-1 border border-gray-300 rounded text-sm group-tablets-per-dose" 
                                       data-medicine-id="${medicineId}">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Frequency</label>
                                <select class="w-full p-1 border border-gray-300 rounded text-sm group-dosage" 
                                        data-medicine-id="${medicineId}">
                                    <option value="M" ${dosage === 'M' ? 'selected' : ''}>M</option>
                                    <option value="N" ${dosage === 'N' ? 'selected' : ''}>N</option>
                                    <option value="Bd" ${dosage === 'Bd' ? 'selected' : ''}>Bd</option>
                                    <option value="Tds" ${dosage === 'Tds' ? 'selected' : ''}>Tds</option>
                                    <option value="Qds" ${dosage === 'Qds' ? 'selected' : ''}>Qds</option>
                                    <option value="SOS" ${dosage === 'SOS' ? 'selected' : ''}>SOS</option>
                                    <option value="EOD" ${dosage === 'EOD' ? 'selected' : ''}>EOD</option>
                                    <option value="STAT" ${dosage === 'STAT' ? 'selected' : ''}>STAT</option>
                                    <option value="VESP" ${dosage === 'VESP' ? 'selected' : ''}>VESP</option>
                                    <option value="NOON" ${dosage === 'NOON' ? 'selected' : ''}>NOON</option>
                                    <option value="3H" ${dosage === '3H' ? 'selected' : ''}>3H</option>
                                    <option value="4H" ${dosage === '4H' ? 'selected' : ''}>4H</option>
                                    <option value="6H" ${dosage === '6H' ? 'selected' : ''}>6H</option>
                                    <option value="8H" ${dosage === '8H' ? 'selected' : ''}>8H</option>
                                    <option value="WEEKLY" ${dosage === 'WEEKLY' ? 'selected' : ''}>WEEKLY</option>
                                    <option value="5X" ${dosage === '5X' ? 'selected' : ''}>5X</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Total Tabs</label>
                                <input type="number" min="1" value="${totalTablets}" 
                                       class="w-full p-1 border border-gray-300 rounded text-sm group-total-tablets" 
                                       data-medicine-id="${medicineId}">
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        }
        
        console.log('Setting container HTML with', medicines.length, 'medicines');
        container.html(html);
    }

    // Make functions globally available
    window.loadGroupMedicines = loadGroupMedicines;
    window.displayGroupMedicines = displayGroupMedicines;
    
    // Add debugging function to test database manually
    window.debugMedicineGroups = function() {
        console.log('=== Medicine Groups Debug ===');
        console.log('Selected medicines:', selectedMedicines);
        console.log('Medicine groups:', medicineGroups);
        console.log('Selected group medicines:', selectedGroupMedicines);
        
        // Test loading groups
        console.log('Testing load groups...');
        loadMedicineGroups();
        
        // If you have a specific group ID to test, uncomment this:
        // console.log('Testing load medicines for group 1...');
        // loadGroupMedicines(1);
    };

    // Debug: Log when document is ready
    $(document).ready(function() {
        console.log('Medicine Groups system initialized');
        console.log('Available functions:', typeof loadMedicineGroups, typeof loadGroupMedicines);
        
        // You can call debugMedicineGroups() in console to test
        console.log('To debug, call debugMedicineGroups() in console');
    });

// Replace the addGroupMedicinesToSelected function in your JavaScript with this corrected version:

// Add Group Medicines to Selected
$(document).on('click', '#addGroupMedicinesToSelected', function() {
    if (!selectedGroupMedicines || selectedGroupMedicines.length === 0) {
        alert('No medicines selected from group');
        return;
    }
    
    let addedCount = 0;
    let skippedCount = 0;
    
    selectedGroupMedicines.forEach(medicine => {
        // Check if medicine already selected
        if (selectedMedicines.find(med => med.id == medicine.medicine_id)) {
            skippedCount++;
            return;
        }
        
        // Get updated values from inputs (FIXED: Ensure we get the correct adjusted values)
        const tabletsPerDoseInput = $(`.group-tablets-per-dose[data-medicine-id="${medicine.medicine_id}"]`);
        const dosageInput = $(`.group-dosage[data-medicine-id="${medicine.medicine_id}"]`);
        const totalTabletsInput = $(`.group-total-tablets[data-medicine-id="${medicine.medicine_id}"]`);
        
        // Use adjusted values from inputs, fallback to original values if inputs not found
        const tabletsPerDose = tabletsPerDoseInput.length ? parseFloat(tabletsPerDoseInput.val()) : (medicine.tablets_per_dose || 1);
        const dosage = dosageInput.length ? dosageInput.val() : (medicine.dosage || 'Bd');
        const totalTablets = totalTabletsInput.length ? parseInt(totalTabletsInput.val()) : (medicine.total_tablets || 10);
        
        console.log(`Medicine ${medicine.drug_name}:`, {
            original: { tablets_per_dose: medicine.tablets_per_dose, dosage: medicine.dosage, total_tablets: medicine.total_tablets },
            adjusted: { tabletsPerDose, dosage, totalTablets }
        });
        
        // Validate the adjusted values
        if (!tabletsPerDose || tabletsPerDose <= 0) {
            alert(`Invalid tablets per dose for ${medicine.drug_name}`);
            skippedCount++;
            return;
        }
        
        if (!totalTablets || totalTablets <= 0) {
            alert(`Invalid total tablets for ${medicine.drug_name}`);
            skippedCount++;
            return;
        }
        
        // Check stock with adjusted total tablets
        if (totalTablets > medicine.current_stock) {
            alert(`Insufficient stock for ${medicine.drug_name}! Available: ${medicine.current_stock}, Requested: ${totalTablets}`);
            skippedCount++;
            return;
        }
        
        // Calculate total price with adjusted total tablets
        const totalPrice = totalTablets * medicine.price_per_tablet;
        
        const medicineData = {
            id: medicine.medicine_id,
            name: medicine.drug_name,
            stock: medicine.current_stock,
            price: medicine.price_per_tablet
        };
        
        // FIXED: Pass the adjusted values to addMedicineToTable
        addMedicineToTable(medicineData, dosage, tabletsPerDose, totalTablets, totalPrice);
        addedCount++;
    });
    
    // Close modal and show result
    $('#groupedMedicinesModal').addClass('hidden');
    resetGroupSelection();
    
    // Optional: Show success message (commented out as requested)
    // if (addedCount > 0) {
    //     let message = `Added ${addedCount} medicines from group`;
    //     if (skippedCount > 0) {
    //         message += `, skipped ${skippedCount} already selected medicines`;
    //     }
    //     alert(message);
    // }
    
    console.log(`Group medicines added: ${addedCount}, skipped: ${skippedCount}`);
});

// Also, let's make sure the addMedicineToTable function properly handles the parameters
// Replace or verify your addMedicineToTable function matches this structure:

function addMedicineToTable(medicine, dosage, tabletsPerDose, totalTablets, totalPrice) {
    if (!$('#selected_medicines_body').length) return;
    
    medicineCounter++;
    
    // FIXED: Store the medicine with the correct adjusted values
    selectedMedicines.push({
        id: medicine.id,
        counter: medicineCounter,
        name: medicine.name,
        dosage: dosage,                    // Use the adjusted dosage
        tabletsPerDose: tabletsPerDose,    // Use the adjusted tablets per dose
        totalTablets: totalTablets,        // Use the adjusted total tablets
        price: totalPrice                  // Use the calculated total price
    });
    
    $('#no_medicines_row').hide();
    
    const html = `
        <tr id="medicine_row_${medicineCounter}" data-medicine-id="${medicine.id}">
            <td class="px-4 py-2 font-semibold">${medicine.name}</td>
            <td class="px-4 py-2">${tabletsPerDose}</td>
            <td class="px-4 py-2">${dosage}</td>
            <td class="px-4 py-2">${totalTablets}</td>
            <td class="px-4 py-2">Rs. ${totalPrice.toFixed(2)}</td>
            <td class="px-4 py-2">
                <button type="button" class="remove-medicine bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-sm" data-counter="${medicineCounter}" data-id="${medicine.id}">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
            
            <input type="hidden" name="medicines[${medicineCounter}][medicine_id]" value="${medicine.id}">
            <input type="hidden" name="medicines[${medicineCounter}][dosage]" value="${dosage}">
            <input type="hidden" name="medicines[${medicineCounter}][tablets_per_dose]" value="${tabletsPerDose}">
            <input type="hidden" name="medicines[${medicineCounter}][quantity]" value="${totalTablets}">
        </tr>
    `;
    
    $('#selected_medicines_body').append(html);
    updateTotals();
    updateAddNewGroupButton();
    
    console.log(`Added medicine to table: ${medicine.name} - ${tabletsPerDose} tabs ${dosage} for ${totalTablets} total tabs`);
}

    // Reset Group Selection
    function resetGroupSelection() {
        selectedGroupId = null;
        selectedGroupMedicines = [];
        $('.group-item').removeClass('selected');
        if ($('#groupMedicinesContainer').length) {
            $('#groupMedicinesContainer').html('<div class="text-center text-gray-500 py-8">Select a group to view medicines</div>');
        }
        $('#addGroupMedicinesToSelected').prop('disabled', true);
    }

    // Populate New Group Medicines
    function populateNewGroupMedicines() {
        const container = $('#newGroupMedicinesList');
        if (!container.length) return;
        
        let html = '';
        
        selectedMedicines.forEach(medicine => {
            html += `
                <div class="new-group-medicine-item border border-gray-200 rounded-lg p-3" data-medicine-id="${medicine.id}">
                    <div class="flex items-center justify-between mb-2">
                        <div class="font-semibold text-gray-800">${medicine.name}</div>
                        <button type="button" class="remove-from-new-group text-red-500 hover:text-red-700" data-medicine-id="${medicine.id}">
                            <i class="fas fa-trash text-sm"></i>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tab/Dose</label>
                            <input type="number" step="0.5" min="0.5" value="${medicine.tabletsPerDose}" 
                                   class="w-full p-1 border border-gray-300 rounded text-sm new-group-tablets-per-dose" 
                                   data-medicine-id="${medicine.id}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Frequency</label>
                            <select class="w-full p-1 border border-gray-300 rounded text-sm new-group-dosage" 
                                    data-medicine-id="${medicine.id}">
                                <option value="M" ${medicine.dosage === 'M' ? 'selected' : ''}>M</option>
                                <option value="N" ${medicine.dosage === 'N' ? 'selected' : ''}>N</option>
                                <option value="Bd" ${medicine.dosage === 'Bd' ? 'selected' : ''}>Bd</option>
                                <option value="Tds" ${medicine.dosage === 'Tds' ? 'selected' : ''}>Tds</option>
                                <option value="Qds" ${medicine.dosage === 'Qds' ? 'selected' : ''}>Qds</option>
                                <option value="SOS" ${medicine.dosage === 'SOS' ? 'selected' : ''}>SOS</option>
                                <option value="EOD" ${medicine.dosage === 'EOD' ? 'selected' : ''}>EOD</option>
                                <option value="STAT" ${medicine.dosage === 'STAT' ? 'selected' : ''}>STAT</option>
                                <option value="VESP" ${medicine.dosage === 'VESP' ? 'selected' : ''}>VESP</option>
                                <option value="NOON" ${medicine.dosage === 'NOON' ? 'selected' : ''}>NOON</option>
                                <option value="3H" ${medicine.dosage === '3H' ? 'selected' : ''}>3H</option>
                                <option value="4H" ${medicine.dosage === '4H' ? 'selected' : ''}>4H</option>
                                <option value="6H" ${medicine.dosage === '6H' ? 'selected' : ''}>6H</option>
                                <option value="8H" ${medicine.dosage === '8H' ? 'selected' : ''}>8H</option>
                                <option value="WEEKLY" ${medicine.dosage === 'WEEKLY' ? 'selected' : ''}>WEEKLY</option>
                                <option value="5X" ${medicine.dosage === '5X' ? 'selected' : ''}>5X</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Total Tabs</label>
                            <input type="number" min="1" value="${medicine.totalTablets}" 
                                   class="w-full p-1 border border-gray-300 rounded text-sm new-group-total-tablets" 
                                   data-medicine-id="${medicine.id}">
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.html(html);
        
        // Add remove handlers
        container.find('.remove-from-new-group').off('click').on('click', function() {
            const medicineId = $(this).data('medicine-id');
            $(`.new-group-medicine-item[data-medicine-id="${medicineId}"]`).remove();
        });
    }

    // Save New Medicine Group
    $(document).on('submit', '#newMedicineGroupForm', function(e) {
        e.preventDefault();
        
        const groupName = $('#newGroupName').val().trim();
        if (!groupName) {
            alert('Please enter group name');
            return;
        }
        
        const medicines = [];
        $('.new-group-medicine-item').each(function() {
            const medicineId = $(this).data('medicine-id');
            const tabletsPerDose = parseFloat($(`.new-group-tablets-per-dose[data-medicine-id="${medicineId}"]`).val()) || 1;
            const dosage = $(`.new-group-dosage[data-medicine-id="${medicineId}"]`).val() || 'Bd';
            const totalTablets = parseInt($(`.new-group-total-tablets[data-medicine-id="${medicineId}"]`).val()) || 10;
            
            medicines.push({
                medicine_id: medicineId,
                tablets_per_dose: tabletsPerDose,
                dosage: dosage,
                total_tablets: totalTablets
            });
        });
        
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="fas fa-spinner fa-spin mr-2"></i>Saving...').prop('disabled', true);
        
        $.ajax({
            url: '../ajax/save_medicine_group.php',
            type: 'POST',
            data: {
                group_name: groupName,
                medicines: medicines
            },
            dataType: 'json',
            timeout: 15000,
            //success: function(response) {
            //    console.log('Save group response:', response);
            //    if (response.success) {
            //        alert('Medicine group saved successfully!');
            //        $('#addNewMedicineGroupModal').addClass('hidden');
            //        $('#newMedicineGroupForm')[0].reset();
            //    } else {
            //        alert('Error: ' + (response.error || 'Failed to save medicine group'));
            //    }
            //},
            //error: function(xhr, status, error) {
            //    console.error('Error saving medicine group:', {xhr: xhr, status: status, error: error});
            //    let errorMsg = 'Error saving medicine group. Please try again.';
            //    if (xhr.responseJSON && xhr.responseJSON.error) {
            //        errorMsg = 'Error: ' + xhr.responseJSON.error;
            //    } else if (xhr.responseText && xhr.responseText.includes('Fatal error')) {
            //        errorMsg = 'Server error occurred. Please check your database connection.';
            //    }
            //    alert(errorMsg);
            //},
            complete: function() {
                submitBtn.html(originalText).prop('disabled', false);
            }
        });
    });

    // PATIENT AUTOCOMPLETE FUNCTIONALITY
    $('#patient_search_input').on('input', function() {
        const searchTerm = $(this).val().trim();
        
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        if (searchTerm.length < 1) {
            hidePatientSuggestions();
            return;
        }
        
        searchTimeout = setTimeout(() => {
            searchPatients(searchTerm);
        }, 300);
    });

    $('#patient_search_input').on('keydown', function(e) {
        const suggestions = $('#patient_autocomplete_suggestions .autocomplete-suggestion');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedSuggestionIndex = Math.min(selectedSuggestionIndex + 1, suggestions.length - 1);
            updatePatientSuggestionSelection();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedSuggestionIndex = Math.max(selectedSuggestionIndex - 1, -1);
            updatePatientSuggestionSelection();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedSuggestionIndex >= 0 && suggestions[selectedSuggestionIndex]) {
                const patientId = $(suggestions[selectedSuggestionIndex]).data('patient-id');
                const patientType = $(suggestions[selectedSuggestionIndex]).data('patient-type');
                const patientData = $(suggestions[selectedSuggestionIndex]).data('patient-data');
                selectPatient(patientId, patientType, patientData);
            }
        } else if (e.key === 'Escape') {
            hidePatientSuggestions();
        }
    });

    function searchPatients(searchTerm) {
        const suggestionsDiv = $('#patient_autocomplete_suggestions');
        suggestionsDiv.html('<div class="loading"><i class="fas fa-spinner fa-spin"></i> Searching...</div>').show();
        
        $.ajax({
            url: '../ajax/get_patients.php',
            type: 'GET',
            data: { search: searchTerm },
            dataType: 'json',
            success: function(data) {
                if (data && data.length > 0) {
                    displayPatientSuggestions(data);
                } else {
                    suggestionsDiv.html('<div class="no-results">No patients found</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error searching patients:', error);
                suggestionsDiv.html('<div class="no-results text-red-500">Error loading patients</div>');
            }
        });
    }

    function displayPatientSuggestions(patients) {
        const suggestionsDiv = $('#patient_autocomplete_suggestions');
        let html = '';
        
        patients.forEach((patient, index) => {
            const badgeClass = patient.type === 'adult' ? 'adult-badge' : 'kid-badge';
            const badgeText = patient.type === 'adult' ? 'Adult' : 'Kid';
            
            html += `
                <div class="autocomplete-suggestion" 
                     data-patient-id="${patient.id}" 
                     data-patient-type="${patient.type}"
                     data-patient-data='${JSON.stringify(patient)}'>
                    ${patient.display}
                    <span class="patient-type-badge ${badgeClass}">${badgeText}</span>
                </div>
            `;
        });
        
        suggestionsDiv.html(html).show();
        selectedSuggestionIndex = -1;
        
        suggestionsDiv.find('.autocomplete-suggestion').on('click', function() {
            const patientId = $(this).data('patient-id');
            const patientType = $(this).data('patient-type');
            const patientData = $(this).data('patient-data');
            selectPatient(patientId, patientType, patientData);
        });
    }

    function updatePatientSuggestionSelection() {
        const suggestions = $('#patient_autocomplete_suggestions .autocomplete-suggestion');
        suggestions.removeClass('selected');
        if (selectedSuggestionIndex >= 0) {
            $(suggestions[selectedSuggestionIndex]).addClass('selected');
        }
    }

    function hidePatientSuggestions() {
        $('#patient_autocomplete_suggestions').hide();
        selectedSuggestionIndex = -1;
    }

    function selectPatient(patientId, patientType, patientData) {
        $('#selected_patient_id').val(patientId);
        $('#selected_patient_type').val(patientType);
        $('#patient_search_input').val(patientData.name);
        
        currentPatientData = patientData;
        currentPatientAllergies = patientData.allergies || '';
        
        displaySelectedPatient(patientData);
        hidePatientSuggestions();
        $('#investigationsBtn').prop('disabled', false);
        
        loadPatientReports(patientType, patientId);
        loadPatientFiles(patientType, patientId);
    }

    function displaySelectedPatient(patient) {
        const displayDiv = $('#selected_patient_display');
        const nameDiv = $('#patient_display_name');
        const detailsDiv = $('#patient_display_details');
        const allergiesDiv = $('#patient_display_allergies');
        const allergiesText = $('#patient_allergies_text');
        
        const badgeClass = patient.type === 'adult' ? 'adult-badge' : 'kid-badge';
        const badgeText = patient.type === 'adult' ? 'Adult' : 'Kid';
        nameDiv.html(`${patient.name} <span class="patient-type-badge ${badgeClass}">${badgeText}</span>`);
        
        let details = '';
        if (patient.phone_number) {
            details += `<div class="patient-detail"><strong>Phone:</strong> ${patient.phone_number}</div>`;
        }
        if (patient.nic_number) {
            details += `<div class="patient-detail"><strong>NIC:</strong> ${patient.nic_number}</div>`;
        }
        if (patient.age) {
            details += `<div class="patient-detail"><strong>Age:</strong> ${patient.age} years</div>`;
        }
        if (patient.parent_name) {
            details += `<div class="patient-detail"><strong>Parent:</strong> ${patient.parent_name}</div>`;
        }
        detailsDiv.html(details);
        
        if (patient.allergies && patient.allergies.trim() !== '') {
            allergiesText.text(patient.allergies);
            allergiesDiv.show();
        } else {
            allergiesDiv.hide();
        }
        
        displayDiv.show();
    }

    $('#clear_patient_btn').on('click', function() {
        clearPatientSelection();
    });

    function clearPatientSelection() {
        $('#selected_patient_id').val('');
        $('#selected_patient_type').val('');
        $('#patient_search_input').val('');
        
        currentPatientData = null;
        currentPatientAllergies = '';
        
        $('#selected_patient_display').hide();
        $('#investigationsBtn').prop('disabled', true);
        
        if ($('#patient_reports').length) {
            $('#patient_reports').html('<p class="text-gray-500 text-center py-8">Select a patient to view reports</p>');
        }
        if ($('#patient_files').length) {
            $('#patient_files').empty();
        }
    }

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.autocomplete-container').length) {
            hidePatientSuggestions();
        }
    });

    // MEDICINE FUNCTIONALITY

    // Medicine search functionality
    $('#medicine_search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        const $medicineCards = $('.medicine-card');
        
        $medicineCards.each(function() {
            const $card = $(this);
            const drugName = $card.data('drug-name');
            
            if (drugName && drugName.toLowerCase().includes(searchTerm)) {
                $card.show();
            } else {
                $card.hide();
            }
        });
    });

    // Medicine Autocomplete Functionality
    if (document.getElementById('medicineNameInput')) {
        document.getElementById('medicineNameInput').addEventListener('input', function() {
            const input = this.value.toLowerCase();
            const suggestions = document.getElementById('medicineAutocomplete');
            
            if (input.length === 0) {
                suggestions.style.display = 'none';
                $('#medicineInfo').addClass('hidden');
                return;
            }
            
            const filteredMedicines = Object.values(availableMedicines).filter(medicine => 
                medicine.name.toLowerCase().includes(input)
            ).slice(0, 8);
            
            if (filteredMedicines.length === 0) {
                suggestions.style.display = 'none';
                $('#medicineInfo').addClass('hidden');
                return;
            }
            
            suggestions.innerHTML = '';
            filteredMedicines.forEach((medicine, index) => {
                const div = document.createElement('div');
                div.className = 'autocomplete-suggestion';
                div.textContent = medicine.name;
                div.addEventListener('click', () => selectMedicine(medicine));
                suggestions.appendChild(div);
            });
            
            suggestions.style.display = 'block';
            selectedSuggestionIndex = -1;
        });

        document.getElementById('medicineNameInput').addEventListener('keydown', function(e) {
            const suggestions = document.querySelectorAll('#medicineAutocomplete .autocomplete-suggestion');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedSuggestionIndex = Math.min(selectedSuggestionIndex + 1, suggestions.length - 1);
                updateMedicineSuggestionSelection(suggestions);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedSuggestionIndex = Math.max(selectedSuggestionIndex - 1, -1);
                updateMedicineSuggestionSelection(suggestions);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedSuggestionIndex >= 0 && suggestions[selectedSuggestionIndex]) {
                    const medicineName = suggestions[selectedSuggestionIndex].textContent;
                    const medicine = Object.values(availableMedicines).find(m => m.name === medicineName);
                    if (medicine) {
                        selectMedicine(medicine);
                    }
                }
            } else if (e.key === 'Escape') {
                document.getElementById('medicineAutocomplete').style.display = 'none';
                selectedSuggestionIndex = -1;
            }
        });
    }

    function updateMedicineSuggestionSelection(suggestions) {
        suggestions.forEach((suggestion, index) => {
            if (index === selectedSuggestionIndex) {
                suggestion.classList.add('selected');
            } else {
                suggestion.classList.remove('selected');
            }
        });
    }

    function selectMedicine(medicine) {
        document.getElementById('medicineNameInput').value = medicine.name;
        document.getElementById('medicineAutocomplete').style.display = 'none';
        selectedSuggestionIndex = -1;
        
        currentMedicine = medicine;
        if ($('#availableStock').length) {
            $('#availableStock').text(medicine.stock);
        }
        if ($('#pricePerTablet').length) {
            $('#pricePerTablet').text(medicine.price.toFixed(2));
        }
        if ($('#medicineInfo').length) {
            $('#medicineInfo').removeClass('hidden');
        }
        
        updateMedicinePrice();
    }

    function updateMedicinePrice() {
        const totalTablets = parseInt($('#medicineTotalTablets').val()) || 0;
        if (currentMedicine && totalTablets > 0 && $('#totalPrice').length) {
            const totalPrice = totalTablets * currentMedicine.price;
            $('#totalPrice').text(totalPrice.toFixed(2));
        } else if ($('#totalPrice').length) {
            $('#totalPrice').text('0.00');
        }
    }

    $('#medicineTotalTablets').on('input', updateMedicinePrice);

    function addMedicine() {
        if (!document.getElementById('medicineNameInput')) return;
        
        const name = document.getElementById('medicineNameInput').value.trim();
        const tabletsPerDose = document.getElementById('medicineTabletsPerDose') ? document.getElementById('medicineTabletsPerDose').value : '';
        const dosage = document.getElementById('medicineDosage') ? document.getElementById('medicineDosage').value : '';
        const totalTablets = parseInt(document.getElementById('medicineTotalTablets') ? document.getElementById('medicineTotalTablets').value : 0) || 0;
        
        if (!name || !currentMedicine) {
            alert('Please select a medicine first');
            return;
        }
        
        if (!tabletsPerDose || !dosage || !totalTablets) {
            alert('Please fill all fields');
            return;
        }
        
        if (totalTablets > currentMedicine.stock) {
            alert(`Insufficient stock! Available: ${currentMedicine.stock}`);
            return;
        }
        
        if (selectedMedicines.find(med => med.id == currentMedicine.id)) {
            alert('Medicine already selected!');
            return;
        }
        
        const totalPrice = totalTablets * currentMedicine.price;
        
        addMedicineToTable(currentMedicine, dosage, tabletsPerDose, totalTablets, totalPrice);
        clearMedicineForm();
    }

    function addMedicineToTable(medicine, dosage, tabletsPerDose, totalTablets, totalPrice) {
        if (!$('#selected_medicines_body').length) return;
        
        medicineCounter++;
        
        selectedMedicines.push({
            id: medicine.id,
            counter: medicineCounter,
            name: medicine.name,
            dosage: dosage,
            tabletsPerDose: tabletsPerDose,
            totalTablets: totalTablets,
            price: totalPrice
        });
        
        $('#no_medicines_row').hide();
        
        const html = `
            <tr id="medicine_row_${medicineCounter}" data-medicine-id="${medicine.id}">
                <td class="px-4 py-2 font-semibold">${medicine.name}</td>
                <td class="px-4 py-2">${tabletsPerDose}</td>
                <td class="px-4 py-2">${dosage}</td>
                <td class="px-4 py-2">${totalTablets}</td>
                <td class="px-4 py-2">Rs. ${totalPrice.toFixed(2)}</td>
                <td class="px-4 py-2">
                    <button type="button" class="remove-medicine bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-sm" data-counter="${medicineCounter}" data-id="${medicine.id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
                
                <input type="hidden" name="medicines[${medicineCounter}][medicine_id]" value="${medicine.id}">
                <input type="hidden" name="medicines[${medicineCounter}][dosage]" value="${dosage}">
                <input type="hidden" name="medicines[${medicineCounter}][tablets_per_dose]" value="${tabletsPerDose}">
                <input type="hidden" name="medicines[${medicineCounter}][quantity]" value="${totalTablets}">
            </tr>
        `;
        
        $('#selected_medicines_body').append(html);
        updateTotals();
        updateAddNewGroupButton();
    }

    function clearMedicineForm() {
        if (document.getElementById('medicineNameInput')) {
            document.getElementById('medicineNameInput').value = '';
        }
        if (document.getElementById('medicineTabletsPerDose')) {
            document.getElementById('medicineTabletsPerDose').value = '';
        }
        if (document.getElementById('medicineDosage')) {
            document.getElementById('medicineDosage').value = '';
        }
        if (document.getElementById('medicineTotalTablets')) {
            document.getElementById('medicineTotalTablets').value = '';
        }
        if ($('#medicineInfo').length) {
            $('#medicineInfo').addClass('hidden');
        }
        currentMedicine = null;
    }

    $(document).on('click', '.remove-medicine', function() {
        const counter = $(this).data('counter');
        const medicineId = $(this).data('id');
        
        selectedMedicines = selectedMedicines.filter(med => med.counter !== counter);
        $(`#medicine_row_${counter}`).remove();
        
        if (selectedMedicines.length === 0 && $('#no_medicines_row').length) {
            $('#no_medicines_row').show();
        }
        
        updateTotals();
        updateAddNewGroupButton();
    });

$(document).ready(function() {
    $('#investigation_date').val(new Date().toISOString().split('T')[0]);
});

// Test function - use this in browser console to test
window.setTestPatient = function() {
    $('#selected_patient_id').val('1');
    $('#selected_patient_type').val('adult');
    currentPatientData = {id: '1', type: 'adult', name: 'Test Patient'};
    console.log('Test patient set');
};

$('#investigationsBtn').click(function() {
    const patientId = $('#selected_patient_id').val();
    const patientType = $('#selected_patient_type').val();
    
    if (!patientId) {
        alert('Please select a patient first. For testing, use setTestPatient() in browser console.');
        return;
    }
    
    $('#investigation_patient_type').val(patientType);
    $('#investigation_patient_id').val(patientId);
    $('#investigation_date').val(new Date().toISOString().split('T')[0]);
    
    $('#investigationModal').removeClass('hidden');
});

$('#closeInvestigationModal, #cancelInvestigation').click(function() {
    $('#investigationModal').addClass('hidden');
    $('#investigationForm')[0].reset();
    $('#investigationParametersContainer').empty();
});

$('#investigation_type').change(function() {
    loadInvestigationParameters($(this).val());
});

function loadInvestigationParameters(investigationType) {
    const container = $('#investigationParametersContainer');
    container.empty();
    
    if (!investigationType || !investigationParams[investigationType]) {
        return;
    }
    
    const params = investigationParams[investigationType];
    let html = `
        <div class="border border-gray-200 rounded-lg p-4">
            <h4 class="text-lg font-semibold text-gray-800 mb-4">${getInvestigationTitle(investigationType)} Parameters</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    `;
    
    params.forEach(param => {
        html += `
            <div class="space-y-1">
                <label class="block text-sm font-medium text-gray-700">
                    ${param.parameter_name}
                    <span class="text-xs text-gray-500">(${param.normal_range})</span>
                </label>
                <input type="text" 
                       name="investigation_data[${param.parameter_code}]" 
                       placeholder="${param.unit}"
                       class="w-full p-2 border border-gray-300 rounded-md text-sm">
            </div>
        `;
    });
    
    html += '</div></div>';
    container.html(html);
}

function getInvestigationTitle(type) {
    const titles = {
        'fbc': 'Full Blood Count',
        'sugar': 'Sugar Profile',
        'vitals': 'Vital Signs',
        'lipid_profile': 'Lipid Profile',
        'renal_function': 'Renal Function',
        'electrolytes': 'Electrolytes',
        'liver_function': 'Liver Function',
        'inflammatory_markers': 'Inflammatory Markers',
        'urine_report': 'Urine Analysis',
        'thyroid_function': 'Thyroid Function'
    };
    return titles[type] || type.replace('_', ' ').toUpperCase();
}

$('#investigationForm').on('submit', function(e) {
    e.preventDefault();
    
    const investigationType = $('#investigation_type').val();
    const investigationDate = $('#investigation_date').val();
    const notes = $('#investigation_notes').val().trim();
    
    if (!investigationDate) {
        alert('Please select investigation date');
        return;
    }
    
    if (!investigationType && !notes) {
        alert('Please either select an investigation type OR enter notes');
        return;
    }
    
    const submitBtn = $(this).find('button[type="submit"]');
    const originalText = submitBtn.text();
    submitBtn.text('Saving...').prop('disabled', true);
    
    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: $(this).serialize(),
        success: function(response) {
            console.log('Raw response:', response);
            
            // Try to parse JSON
            let jsonResponse;
            try {
                if (typeof response === 'string') {
                    jsonResponse = JSON.parse(response);
                } else {
                    jsonResponse = response;
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response was:', response);
                alert('Server returned invalid response. Check console for details.');
                return;
            }
            
            if (jsonResponse.success) {
                alert('Investigation saved successfully!');
                $('#investigationForm')[0].reset();
                $('#investigationParametersContainer').empty();
                $('#investigation_date').val(new Date().toISOString().split('T')[0]);
            } else {
                alert('Error: ' + (jsonResponse.error || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', {xhr, status, error});
            console.error('Response text:', xhr.responseText);
            alert('Error saving investigation: ' + error);
        },
        complete: function() {
            submitBtn.text(originalText).prop('disabled', false);
        }
    });
});

    // OUTSIDE MEDICINE FUNCTIONALITY
    if (document.getElementById('outsideMedicineNameInput')) {
        document.getElementById('outsideMedicineNameInput').addEventListener('input', function() {
            const input = this.value.toLowerCase();
            const suggestions = document.getElementById('outsideMedicineAutocomplete');
            
            if (!suggestions) return;
            
            if (input.length === 0) {
                suggestions.style.display = 'none';
                return;
            }
            
            const filteredMedicines = medicineList.filter(medicine => 
                medicine.toLowerCase().includes(input)
            ).slice(0, 8);
            
            if (filteredMedicines.length === 0) {
                suggestions.style.display = 'none';
                return;
            }
            
            suggestions.innerHTML = '';
            filteredMedicines.forEach((medicine, index) => {
                const div = document.createElement('div');
                div.className = 'autocomplete-suggestion';
                div.textContent = medicine;
                div.addEventListener('click', () => selectOutsideMedicine(medicine));
                suggestions.appendChild(div);
            });
            
            suggestions.style.display = 'block';
            selectedSuggestionIndex = -1;
        });

        document.getElementById('outsideMedicineNameInput').addEventListener('keydown', function(e) {
            const suggestions = document.querySelectorAll('#outsideMedicineAutocomplete .autocomplete-suggestion');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedSuggestionIndex = Math.min(selectedSuggestionIndex + 1, suggestions.length - 1);
                updateOutsideSuggestionSelection(suggestions);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedSuggestionIndex = Math.max(selectedSuggestionIndex - 1, -1);
                updateOutsideSuggestionSelection(suggestions);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedSuggestionIndex >= 0 && suggestions[selectedSuggestionIndex]) {
                    selectOutsideMedicine(suggestions[selectedSuggestionIndex].textContent);
                }
            } else if (e.key === 'Escape') {
                document.getElementById('outsideMedicineAutocomplete').style.display = 'none';
                selectedSuggestionIndex = -1;
            }
        });
    }

    function updateOutsideSuggestionSelection(suggestions) {
        suggestions.forEach((suggestion, index) => {
            if (index === selectedSuggestionIndex) {
                suggestion.classList.add('selected');
            } else {
                suggestion.classList.remove('selected');
            }
        });
    }

    function selectOutsideMedicine(medicine) {
        if (document.getElementById('outsideMedicineNameInput')) {
            document.getElementById('outsideMedicineNameInput').value = medicine;
        }
        if (document.getElementById('outsideMedicineAutocomplete')) {
            document.getElementById('outsideMedicineAutocomplete').style.display = 'none';
        }
        selectedSuggestionIndex = -1;
    }

    function addOutsideMedicine() {
        if (!document.getElementById('outsideMedicineNameInput')) return;
        
        const name = document.getElementById('outsideMedicineNameInput').value.trim();
        const dosage = document.getElementById('outsideMedicineDosage') ? document.getElementById('outsideMedicineDosage').value : '';
        const perDose = document.getElementById('outsideMedicinePerDose') ? document.getElementById('outsideMedicinePerDose').value : '';
        const days = document.getElementById('outsideMedicineDays') ? document.getElementById('outsideMedicineDays').value : '';
        
        if (!name) {
            alert('Please enter medicine name');
            return;
        }
        
        outsideMedicineCounter++;
        const medicine = {
            id: outsideMedicineCounter,
            name: name,
            dosage: dosage,
            perDose: perDose,
            days: days
        };
        
        outsideMedicines.push(medicine);
        updateOutsideMedicineTable();
        clearOutsideMedicineForm();
    }

    function updateOutsideMedicineTable() {
        const tbody = document.getElementById('outsideMedicineTableBody');
        const noMedicineRow = document.getElementById('noOutsideMedicineRow');
        
        if (!tbody || !noMedicineRow) return;
        
        if (outsideMedicines.length === 0) {
            noMedicineRow.style.display = 'table-row';
            return;
        }
        
        noMedicineRow.style.display = 'none';
        
        const existingRows = tbody.querySelectorAll('tr:not(#noOutsideMedicineRow)');
        existingRows.forEach(row => row.remove());
        
        outsideMedicines.forEach((medicine, index) => {
            const row = document.createElement('tr');
            row.className = 'border-b';
            
            let dosageInfo = '';
            if (medicine.perDose && medicine.dosage && medicine.days) {
                dosageInfo = `${medicine.perDose} tab ${medicine.dosage}`;
            } else if (medicine.dosage) {
                dosageInfo = medicine.dosage;
            }
            
            let daysInfo = medicine.days ? `${medicine.days} days` : '';
            
            row.innerHTML = `
                <td class="px-2 py-1.5 text-sm">${medicine.name}</td>
                <td class="px-2 py-1.5 text-sm">${dosageInfo}</td>
                <td class="px-2 py-1.5 text-sm">${daysInfo}</td>
                <td class="px-2 py-1.5 text-sm">
                    <button type="button" onclick="removeOutsideMedicine(${medicine.id})" 
                            class="text-red-600 hover:text-red-800 text-xs">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
                <input type="hidden" name="outside_medicines[${medicine.id}][name]" value="${medicine.name}">
                <input type="hidden" name="outside_medicines[${medicine.id}][per_dose]" value="${medicine.perDose}">
                <input type="hidden" name="outside_medicines[${medicine.id}][dosage]" value="${medicine.dosage}">
                <input type="hidden" name="outside_medicines[${medicine.id}][days]" value="${medicine.days}">
            `;
            tbody.appendChild(row);
        });
    }

    function removeOutsideMedicine(id) {
        outsideMedicines = outsideMedicines.filter(med => med.id !== id);
        updateOutsideMedicineTable();
    }

    function clearOutsideMedicineForm() {
        if (document.getElementById('outsideMedicineNameInput')) {
            document.getElementById('outsideMedicineNameInput').value = '';
        }
        if (document.getElementById('outsideMedicineDosage')) {
            document.getElementById('outsideMedicineDosage').value = '';
        }
        if (document.getElementById('outsideMedicinePerDose')) {
            document.getElementById('outsideMedicinePerDose').value = '';
        }
        if (document.getElementById('outsideMedicineDays')) {
            document.getElementById('outsideMedicineDays').value = '5';
        }
    }

    // OTHER FUNCTIONALITY
    $('#new_birthday').change(function() {
        const birthday = new Date($(this).val());
        const today = new Date();
        const age = Math.floor((today - birthday) / (365.25 * 24 * 60 * 60 * 1000));
        
        if (age >= 0 && age <= 120) {
            $('#new_age').val(age);
        }
    });

    $('.fee-btn').click(function() {
        const fee = $(this).data('fee');
        $('#consultation_fee').val(fee);
        updateTotals();
        
        $('.fee-btn').removeClass('bg-blue-300 text-blue-900').addClass('bg-blue-100 text-blue-800');
        $(this).removeClass('bg-blue-100 text-blue-800').addClass('bg-blue-300 text-blue-900');
    });

    $('#consultation_fee').on('input', function() {
        updateTotals();
        $('.fee-btn').removeClass('bg-blue-300 text-blue-900').addClass('bg-blue-100 text-blue-800');
    });

    function updateTotals() {
        let medicineTotal = 0;
        selectedMedicines.forEach(med => {
            medicineTotal += med.price;
        });
        
        const consultationFee = parseFloat($('#consultation_fee').val()) || 0;
        const subtotal = medicineTotal + consultationFee;
        const finalTotal = roundToNearest50(subtotal);
        
        if ($('#final_total').length) {
            $('#final_total').text('Rs. ' + finalTotal.toFixed(2));
        }
    }

    // MODAL HANDLERS
    $('#newPatientBtn').click(function() {
        if ($('#newPatientModal').length) {
            $('#newPatientModal').removeClass('hidden');
        }
    });

    $('#cancelNewPatient').click(function() {
        if ($('#newPatientModal').length) {
            $('#newPatientModal').addClass('hidden');
        }
        if ($('#newPatientForm').length) {
            $('#newPatientForm')[0].reset();
        }
        if ($('#adult_fields').length && $('#kid_fields').length) {
            $('#adult_fields, #kid_fields').addClass('hidden');
        }
    });

    $('#new_patient_type').change(function() {
        const type = $(this).val();
        if (type === 'adult') {
            if ($('#adult_fields').length) $('#adult_fields').removeClass('hidden');
            if ($('#kid_fields').length) $('#kid_fields').addClass('hidden');
        } else if (type === 'kid') {
            if ($('#adult_fields').length) $('#adult_fields').addClass('hidden');
            if ($('#kid_fields').length) $('#kid_fields').removeClass('hidden');
        } else {
            if ($('#adult_fields').length && $('#kid_fields').length) {
                $('#adult_fields, #kid_fields').addClass('hidden');
            }
        }
    });

    $('#uploadFileBtn').click(function() {
        if (!currentPatientData) {
            alert('Please select a patient first');
            return;
        }
        
        if ($('#file_patient_type').length) {
            $('#file_patient_type').val(currentPatientData.type);
        }
        if ($('#file_patient_id').length) {
            $('#file_patient_id').val(currentPatientData.id);
        }
        if ($('#file_patient_display').length) {
            $('#file_patient_display').val(currentPatientData.name);
        }
        if ($('#fileUploadModal').length) {
            $('#fileUploadModal').removeClass('hidden');
        }
    });

    $('#cancelFileUpload').click(function() {
        if ($('#fileUploadModal').length) {
            $('#fileUploadModal').addClass('hidden');
        }
        if ($('#fileUploadForm').length) {
            $('#fileUploadForm')[0].reset();
        }
    });

    $('#printOutsidePrescriptionBtn').click(function() {
        if (!currentPatientData) {
            alert('Please select a patient first');
            return;
        }
        
        if (outsideMedicines.length === 0) {
            alert('Please add at least one outside medicine first');
            return;
        }
        
        const nextWeek = new Date();
        nextWeek.setDate(nextWeek.getDate() + 7);
        if ($('#printNextVisit').length) {
            $('#printNextVisit').val(nextWeek.toISOString().split('T')[0]);
        }
        
        if ($('#outsidePrescriptionModal').length) {
            $('#outsidePrescriptionModal').removeClass('hidden');
        }
    });

    function loadPatientReports(patientType, patientId) {
        if (!$('#patient_reports').length) return;
        
        $.get('../ajax/get_patient_reports.php', { 
            type: patientType, 
            id: patientId 
        }, function(data) {
            $('#patient_reports').html(data);
        }).fail(function() {
            $('#patient_reports').html('<p class="text-gray-500 text-center py-8">No reports found</p>');
        });
    }

    function loadPatientFiles(patientType, patientId) {
        if (!$('#patient_files').length) return;
        
        $.get('../ajax/get_patient_files.php', { 
            type: patientType, 
            id: patientId 
        }, function(data) {
            $('#patient_files').html(data);
        }).fail(function() {
            $('#patient_files').html('<p class="text-gray-500 text-center py-4">No files found</p>');
        });
    }

    function closeOutsidePrescriptionModal() {
        if ($('#outsidePrescriptionModal').length) {
            $('#outsidePrescriptionModal').addClass('hidden');
        }
    }

    function generateOutsidePrescriptionPrint() {
        if (!currentPatientData) {
            alert('Patient data not available');
            return;
        }
        
        if (outsideMedicines.length === 0) {
            alert('No outside medicines added');
            return;
        }
        
        const refNo = 'DW/' + new Date().toISOString().slice(0,10).replace(/-/g, '') + '/' + Math.floor(Math.random() * 100);
        const patientName = currentPatientData.name;
        const patientAge = currentPatientData.age || 'N/A';
        const nextVisit = $('#printNextVisit').length ? $('#printNextVisit').val() || 'N/A' : 'N/A';
        const currentDate = new Date().toISOString().split('T')[0];
        const doctorName = '<?php echo isset($user["doctor_name"]) ? addslashes($user["doctor_name"]) : "Doctor"; ?>';
        
        function getSignatureHTML() {
            const doctorSignature = '<?php echo $doctorSignature ? addslashes($doctorSignature["file_path"]) : ""; ?>';
            if (doctorSignature) {
                return `<img src="${doctorSignature}" style="max-width: 150px; max-height: 80px; margin: 10px 0;" alt="Doctor Signature">`;
            }
            return '<br><br>';
        }
        
        const medicineRows = outsideMedicines.map(medicine => {
            let dosageInfo = '';
            if (medicine.perDose && medicine.dosage && medicine.days) {
                dosageInfo = `${medicine.perDose} tablet(s) ${medicine.dosage}`;
            } else if (medicine.dosage) {
                dosageInfo = medicine.dosage;
            } else {
                dosageInfo = 'As directed';
            }
            
            let durationInfo = medicine.days ? `${medicine.days} days` : 'As needed';
            
            return `
                <tr>
                    <td style="border: 1px solid #000; padding: 8px;">${medicine.name}</td>
                    <td style="border: 1px solid #000; padding: 8px;">${dosageInfo}</td>
                    <td style="border: 1px solid #000; padding: 8px;">${durationInfo}</td>
                </tr>
            `;
        }).join('');
        
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
        
        if ($('#outsidePrescriptionPrint').length) {
            $('#outsidePrescriptionPrint').html(content);
            $('#outsidePrescriptionPrint').removeClass('hidden');
        }
        
        setTimeout(() => {
            window.print();
        }, 100);
    }

    // FORM VALIDATION AND SUBMISSION
    $('#dispenserForm').submit(function(e) {
        if (!currentPatientData) {
            e.preventDefault();
            alert('Please select a patient first!');
            return;
        }
        
        if (selectedMedicines.length === 0 && outsideMedicines.length === 0) {
            e.preventDefault();
            alert('Please select at least one medicine or add outside prescription!');
            return;
        }
    });

    // MODAL WINDOW CLICK HANDLERS
    $(window).click(function(event) {
        if (event.target.id === 'groupedMedicinesModal') {
            $('#groupedMedicinesModal').addClass('hidden');
            resetGroupSelection();
        }
        if (event.target.id === 'addNewMedicineGroupModal') {
            $('#addNewMedicineGroupModal').addClass('hidden');
            $('#newMedicineGroupForm')[0].reset();
        }
        if (event.target.id === 'newPatientModal' && $('#newPatientModal').length) {
            $('#newPatientModal').addClass('hidden');
        }
        if (event.target.id === 'fileUploadModal' && $('#fileUploadModal').length) {
            $('#fileUploadModal').addClass('hidden');
        }
        if (event.target.id === 'outsidePrescriptionModal' && $('#outsidePrescriptionModal').length) {
            $('#outsidePrescriptionModal').addClass('hidden');
        }
        if (event.target.id === 'investigationModal' && $('#investigationModal').length) {
            $('#investigationModal').addClass('hidden');
        }
    });

    // OLD PATIENT SELECTION SYSTEM INTEGRATION (for backward compatibility)
    $('#patient_type').change(function() {
        const patientType = $(this).val();
        const $patientSelect = $('#patient_select');
        
        if ($patientSelect.length) {
            $patientSelect.empty().append('<option value="">Loading...</option>');
            clearPatientData();
            
            if (patientType) {
                $.get('../ajax/get_patients.php', { type: patientType, doctor_filter: true }, function(data) {
                    $patientSelect.html(data);
                });
            } else {
                $patientSelect.html('<option value="">Select Patient</option>');
            }
        }
    });

    function clearPatientData() {
        currentPatientAllergies = '';
        currentPatientData = null;
        if ($('#patient_allergies').length) {
            $('#patient_allergies').val('');
        }
        if ($('#current_allergies_display').length) {
            $('#current_allergies_display').addClass('hidden');
        }
        if ($('#patient_reports').length) {
            $('#patient_reports').html('<p class="text-gray-500 text-center py-8">Select a patient to view reports</p>');
        }
        if ($('#patient_files').length) {
            $('#patient_files').empty();
        }
        $('#investigationsBtn').prop('disabled', true);
    }

    $('#patient_select').change(function() {
        const patientId = $(this).val();
        const patientType = $('#patient_type').val();
        
        if (patientId && patientType) {
            const selectedOption = $(this).find('option:selected');
            const patientText = selectedOption.text();
            
            const patientName = patientText.split(' (')[0];
            
            currentPatientData = {
                id: patientId,
                type: patientType,
                name: patientName,
                phone: '',
                age: 0
            };
            
            loadPatientDetails(patientType, patientId);
            loadPatientReports(patientType, patientId);
            loadPatientFiles(patientType, patientId);
            $('#investigationsBtn').prop('disabled', false);
        } else {
            clearPatientData();
        }
    });

    function loadPatientDetails(patientType, patientId) {
        $.get('../ajax/get_patient_details.php', { 
            type: patientType, 
            id: patientId 
        }, function(data) {
            if (data && !data.error) {
                currentPatientAllergies = data.allergies || '';
                
                if (currentPatientData) {
                    currentPatientData.allergies = data.allergies || '';
                    currentPatientData.age = data.age || calculateAgeFromBirthday(data.birthday) || 'Unknown';
                    currentPatientData.phone = data.phone_number || '';
                    currentPatientData.nic = data.nic_number || '';
                    currentPatientData.birthday = data.birthday || '';
                }

                if ($('#current_allergies_text').length && data.allergies && data.allergies.trim() !== '') {
                    $('#current_allergies_text').text(data.allergies);
                    $('#current_allergies_display').removeClass('hidden');
                } else if ($('#current_allergies_display').length) {
                    $('#current_allergies_display').addClass('hidden');
                }
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('AJAX error loading patient details:', error);
        });
    }
    
    function calculateAgeFromBirthday(birthday) {
        if (!birthday) return null;
        
        const birthDate = new Date(birthday);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        
        return age >= 0 ? age : null;
    }

    $('#patient_search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        const $options = $('#patient_select option');
        
        $options.each(function() {
            const $option = $(this);
            const text = $option.text().toLowerCase();
            
            if (text.includes(searchTerm) || $option.val() === '') {
                $option.show();
            } else {
                $option.hide();
            }
        });
    });

    // KEYBOARD EVENT HANDLERS
    $(document).keydown(function(e) {
        if (e.keyCode === 27) { // Escape key
            if ($('#investigationModal').length && !$('#investigationModal').hasClass('hidden')) {
                $('#investigationModal').addClass('hidden');
            }
            if ($('#groupedMedicinesModal').length && !$('#groupedMedicinesModal').hasClass('hidden')) {
                $('#groupedMedicinesModal').addClass('hidden');
                resetGroupSelection();
            }
            if ($('#addNewMedicineGroupModal').length && !$('#addNewMedicineGroupModal').hasClass('hidden')) {
                $('#addNewMedicineGroupModal').addClass('hidden');
            }
        }
    });
    
    $(document).on('keypress', '#investigationParametersContainer input', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            const inputs = $('#investigationParametersContainer input');
            const currentIndex = inputs.index(this);
            if (currentIndex < inputs.length - 1) {
                inputs.eq(currentIndex + 1).focus();
            }
        }
    });

    // MAKE FUNCTIONS GLOBALLY AVAILABLE
    window.addMedicine = addMedicine;
    window.addOutsideMedicine = addOutsideMedicine;
    window.removeOutsideMedicine = removeOutsideMedicine;
    window.closeOutsidePrescriptionModal = closeOutsidePrescriptionModal;
    window.generateOutsidePrescriptionPrint = generateOutsidePrescriptionPrint;

    // INITIALIZE ON PAGE LOAD
    $(document).ready(function() {
        // Initialize arrays and counters
        selectedMedicines = [];
        outsideMedicines = [];
        medicineCounter = 0;
        outsideMedicineCounter = 0;
        
        // Set default dates
        const nextWeek = new Date();
        nextWeek.setDate(nextWeek.getDate() + 7);
        if ($('#printNextVisit').length) {
            $('#printNextVisit').val(nextWeek.toISOString().split('T')[0]);
        }
        
        if ($('#investigation_date').length) {
            $('#investigation_date').val(new Date().toISOString().split('T')[0]);
        }
        
        // Initialize button states
        updateAddNewGroupButton();
        clearPatientSelection();
        
        // Initialize totals
        updateTotals();
        
        console.log('Drug Dispenser with Medicine Groups initialized successfully');
    });

// ===== TOKEN SYSTEM =====
let currentToken = null;
let tokenCheckInterval = null;

// Start token checking
function startTokenChecking() {
    checkNextToken();
    tokenCheckInterval = setInterval(checkNextToken, 5000); // Check every 5 seconds
}

// Stop token checking
function stopTokenChecking() {
    if (tokenCheckInterval) {
        clearInterval(tokenCheckInterval);
        tokenCheckInterval = null;
    }
}

// Check for next token
function checkNextToken() {
    $.ajax({
        url: '../ajax/get_next_token.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.token) {
                if (!currentToken || currentToken.id !== response.token.id) {
                    currentToken = response.token;
                    displayToken(response.token);
                }
            } else if (!response.token) {
                hideToken();
            }
        },
        error: function(xhr, status, error) {
            console.error('Error checking token:', error);
        }
    });
}

// Display token banner
// Display token banner
function displayToken(token) {
    $('#tokenNumber').text(token.token_number);
    $('#tokenPatientName').text(token.patient_name);
    $('#tokenBanner').removeClass('hidden');
    $('#bannerSpacer').removeClass('hidden');
    
    // Auto-fill patient if empty
    if (!$('#selected_patient_id').val()) {
        autoFillPatientFromToken(token);
    }
}

// Auto-fill patient from token
function autoFillPatientFromToken(token) {
    // Set patient data
    $('#selected_patient_id').val(token.patient_id);
    $('#selected_patient_type').val(token.patient_type);
    $('#patient_search_input').val(token.patient_name);
    
    // Display selected patient
    const badgeClass = token.patient_type === 'adult' ? 'adult-badge' : 'kid-badge';
    const badgeText = token.patient_type === 'adult' ? 'Adult' : 'Kid';
    
    $('#patient_display_name').html(`${token.patient_name} <span class="patient-type-badge ${badgeClass}">${badgeText}</span>`);
    $('#selected_patient_display').show();
    
    // Load patient details
    loadPatientDetails(token.patient_type, token.patient_id);
    loadPatientReports(token.patient_type, token.patient_id);
    loadPatientFiles(token.patient_type, token.patient_id);
    $('#investigationsBtn').prop('disabled', false);
    
    // Store current patient data
    currentPatientData = {
        id: token.patient_id,
        type: token.patient_type,
        name: token.patient_name
    };
}

// Hide token banner
function hideToken() {
    currentToken = null;
    $('#tokenBanner').addClass('hidden');
    $('#bannerSpacer').addClass('hidden');
}

// Dismiss token (manually)
function dismissToken() {
    hideToken();
}

// Make function globally available
window.dismissToken = dismissToken;

// ===== FORM SUBMISSION ENHANCEMENT =====
// Override the existing form submission to refresh token after dispensing
const originalFormSubmit = $('#dispenserForm').submit;
$('#dispenserForm').submit(function(e) {
    if (!currentPatientData) {
        e.preventDefault();
        alert('Please select a patient first!');
        return;
    }
    
    if (selectedMedicines.length === 0 && outsideMedicines.length === 0) {
        e.preventDefault();
        alert('Please select at least one medicine or add outside prescription!');
        return;
    }
    
    // After form submission, refresh token
    setTimeout(() => {
        checkNextToken();
    }, 2000);
});

let nextVisitDateValue = null;
let reminderDaysBeforeValue = 1;

// Calculate next visit date from days
$('#calculateNextVisitBtn').on('click', function() {
    const days = parseInt($('#daysToNextVisit').val());
    
    if (!days || days < 1) {
        alert('Please enter a valid number of days (minimum 1)');
        return;
    }
    
    const today = new Date();
    const nextDate = new Date(today.setDate(today.getDate() + days));
    const formattedDate = nextDate.toISOString().split('T')[0];
    
    $('#nextVisitDate').val(formattedDate);
    updateNextVisitSummary();
});

// Update summary when date changes
$('#nextVisitDate, #reminderDaysBefore').on('change', function() {
    updateNextVisitSummary();
});

// Clear next visit
$('#clearNextVisitBtn').on('click', function() {
    $('#nextVisitDate').val('');
    $('#daysToNextVisit').val('');
    $('#reminderDaysBefore').val('1');
    $('#nextVisitSummary').addClass('hidden');
    nextVisitDateValue = null;
});

// Update next visit summary
function updateNextVisitSummary() {
    const dateValue = $('#nextVisitDate').val();
    const reminderDays = parseInt($('#reminderDaysBefore').val());
    
    if (!dateValue) {
        $('#nextVisitSummary').addClass('hidden');
        nextVisitDateValue = null;
        return;
    }
    
    nextVisitDateValue = dateValue;
    reminderDaysBeforeValue = reminderDays;
    
    const visitDate = new Date(dateValue);
    const reminderDate = new Date(visitDate);
    reminderDate.setDate(reminderDate.getDate() - reminderDays);
    
    const visitDateStr = visitDate.toLocaleDateString('en-US', { 
        weekday: 'short', 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
    
    const reminderDateStr = reminderDate.toLocaleDateString('en-US', { 
        weekday: 'short', 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
    
    $('#summaryDate').text(visitDateStr);
    
    if (reminderDays === 0) {
        $('#summaryReminder').text('On visit day (' + reminderDateStr + ')');
    } else if (reminderDays === 1) {
        $('#summaryReminder').text('1 day before (' + reminderDateStr + ')');
    } else {
        $('#summaryReminder').text(reminderDays + ' days before (' + reminderDateStr + ')');
    }
    
    $('#nextVisitSummary').removeClass('hidden');
}

// Close SMS units warning modal
$('#closeUnitsWarningBtn').on('click', function() {
    $('#smsUnitsWarningModal').addClass('hidden');
});

// Show SMS units warning if needed
function checkSMSUnitsBeforeSave() {
    const remainingUnits = <?php echo $remainingSMSUnits; ?>;
    const smsEnabled = <?php echo $smsEnabled ? 'true' : 'false'; ?>;
    
    if (nextVisitDateValue && !smsEnabled) {
        $('#remainingUnitsText').text(remainingUnits);
        $('#smsUnitsWarningModal').removeClass('hidden');
        return false; // Don't block saving, just warn
    }
    return true;
}

// Save next visit appointment after dispensing
function saveNextVisitAppointment(receiptId) {
    if (!nextVisitDateValue || !currentPatientData) {
        return Promise.resolve({ success: true, skipped: true });
    }
    
    return new Promise((resolve, reject) => {
        $.ajax({
            url: '../ajax/save_next_visit.php',
            type: 'POST',
            data: {
                patient_type: currentPatientData.type,
                patient_id: currentPatientData.id,
                next_visit_date: nextVisitDateValue,
                reminder_days_before: reminderDaysBeforeValue,
                receipt_id: receiptId
            },
            dataType: 'json',
            success: function(response) {
                resolve(response);
            },
            error: function(xhr, status, error) {
                reject(error);
            }
        });
    });
}

// Modified form submission to include next visit
const originalDispenserSubmit = $('#dispenserForm').off('submit');
$('#dispenserForm').on('submit', function(e) {
    if (!currentPatientData) {
        e.preventDefault();
        alert('Please select a patient first!');
        return;
    }
    
    if (selectedMedicines.length === 0 && outsideMedicines.length === 0) {
        e.preventDefault();
        alert('Please select at least one medicine or add outside prescription!');
        return;
    }
    
    // Check SMS units if next visit is set
    if (nextVisitDateValue) {
        checkSMSUnitsBeforeSave();
    }
    
    // Let the form submit normally, but we'll handle next visit via AJAX
    // The form will reload the page, so we need to save receipt_id somehow
    // We'll modify the PHP to return the receipt_id and save next visit
});

// Add this to the PHP section where receipt is created (in drug_dispenser_part.php)
// After successful dispensing and receipt creation, add:
/*
if ($_POST && isset($_POST['dispense'])) {
    // ... existing dispensing code ...
    
    if ($result) {
        $pdo->commit();
        
        // Save next visit if provided
        if (!empty($_POST['next_visit_date'])) {
            try {
                $nextVisitDate = $_POST['next_visit_date'];
                $reminderDays = (int)($_POST['reminder_days_before'] ?? 1);
                
                $stmt = $pdo->prepare("
                    INSERT INTO next_visit_appointments 
                    (doctor_id, patient_type, patient_id, receipt_id, next_visit_date, reminder_days_before, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'scheduled')
                ");
                
                $stmt->execute([
                    $_SESSION['user_id'],
                    $patient_type,
                    $patient_id,
                    $receipt_id,
                    $nextVisitDate,
                    $reminderDays
                ]);
                
                $message .= ' Next visit scheduled for ' . date('d M Y', strtotime($nextVisitDate)) . '.';
                
                // Check if SMS units are low
                if ($smsConfig && $smsConfig['remaining_units'] <= 10) {
                    $message .= ' Note: You have only ' . $smsConfig['remaining_units'] . ' SMS units remaining.';
                }
                
            } catch (Exception $e) {
                error_log("Error saving next visit: " . $e->getMessage());
                // Don't fail the whole transaction, just log the error
            }
        }
        
        $message = 'Medicines dispensed successfully! E-receipt created. Patient allergies updated: ' . $updated_allergies;
    }
}
*/

// Notification system for low SMS units
function checkAndNotifyLowSMSUnits() {
    const remainingUnits = <?php echo $remainingSMSUnits; ?>;
    
    if (remainingUnits > 0 && remainingUnits <= 10) {
        // Show subtle notification
        const notification = $('<div>', {
            class: 'fixed bottom-4 right-4 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded shadow-lg z-50',
            html: `
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-xl mr-3"></i>
                    <div>
                        <p class="font-semibold">Low SMS Units</p>
                        <p class="text-sm">Only ${remainingUnits} SMS units remaining</p>
                    </div>
                    <button onclick="$(this).parent().parent().remove()" class="ml-4 text-yellow-700 hover:text-yellow-900">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `
        });
        
        $('body').append(notification);
        
        // Auto-hide after 10 seconds
        setTimeout(() => {
            notification.fadeOut(500, function() { $(this).remove(); });
        }, 10000);
    }
}

// Check SMS units on page load
$(document).ready(function() {
    checkAndNotifyLowSMSUnits();
    
    // Clear next visit fields after successful submission
    if (<?php echo isset($message) && !empty($message) ? 'true' : 'false'; ?>) {
        $('#nextVisitDate').val('');
        $('#daysToNextVisit').val('');
        $('#reminderDaysBefore').val('1');
        $('#nextVisitSummary').addClass('hidden');
        nextVisitDateValue = null;
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

// ===== INITIALIZATION =====
$(document).ready(function() {
    // ... existing initialization code ...
    
    // Start token checking system
    startTokenChecking();
    console.log('Token system initialized');
});

// Clean up on page unload
$(window).on('beforeunload', function() {
    stopTokenChecking();
});
</script>
</body>
</html>