<?php
require_once '../config.php';
// Optional: Remove requireLogin() if you want the privacy policy accessible to all
// requireLogin();

// If login is required, get user info
$isLoggedIn = isset($_SESSION['user_id']);
if ($isLoggedIn) {
    $user = getCurrentUser();
    $userType = $_SESSION['user_type'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Doctor Wallet</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="../icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="../icon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../icon.png">
    <link rel="mask-icon" href="../icon.svg" color="#0F2E44">
    <meta name="msapplication-TileImage" content="../icon.png">
    <meta name="msapplication-TileColor" content="#0F2E44">
    <meta name="theme-color" content="#0F2E44">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        html {
            scroll-behavior: smooth;
        }
        
        .privacy-section {
            scroll-margin-top: 100px;
        }
        
        .print-hide {
            display: block;
        }
        
        @media print {
            .print-hide {
                display: none !important;
            }
            
            body {
                background: white;
            }
            
            .container {
                max-width: 100%;
                padding: 20px;
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
<body class="bg-gray-100 min-h-screen flex flex-col [&_*::selection]:bg-yellow-100 [&_*::selection]:text-white" style="visibility: hidden;">
    
    <!-- Navigation -->
    <nav class="bg-gradient-to-r from-blue-800 via-blue-700 to-blue-800 text-white p-4 print-hide" id="top">
        <div class="container mx-auto flex flex-col md:flex-row md:items-center md:justify-between space-y-3 md:space-y-0">
            <h1 class="text-xl md:text-2xl font-bold text-center md:text-left">Doctor Wallet - Privacy Policy</h1>
            
            <div class="flex flex-col md:flex-row md:items-center md:space-x-4 text-center md:text-left space-y-2 md:space-y-0">
                <?php if ($isLoggedIn): ?>
                    <span class="text-sm md:text-base">
                        Welcome, <?php echo htmlspecialchars($user['name'] ?? $user['doctor_name']); ?>
                    </span>
                    <a href="../index.php" class="bg-green-500 hover:bg-green-600 px-3 py-2 rounded text-sm md:text-base">
                        <i class="fas fa-home mr-1"></i> Back to Dashboard
                    </a>
                    <a href="../logout.php" class="bg-red-500 hover:bg-red-600 px-3 py-2 rounded text-sm md:text-base">
                        Logout
                    </a>
                <?php else: ?>
                    <a href="../login.php" class="bg-green-500 hover:bg-green-600 px-3 py-2 rounded text-sm md:text-base">
                        <i class="fas fa-sign-in-alt mr-1"></i> Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Quick Navigation -->
    <div class="bg-white shadow-md sticky top-0 z-10 print-hide">
        <div class="container mx-auto px-6 py-3">
            <div class="flex flex-wrap gap-2 justify-center text-sm">
                <a href="#introduction" class="text-blue-600 hover:text-blue-800 hover:underline">Introduction</a>
                <span class="text-gray-400">|</span>
                <a href="#information-collection" class="text-blue-600 hover:text-blue-800 hover:underline">Information We Collect</a>
                <span class="text-gray-400">|</span>
                <a href="#information-use" class="text-blue-600 hover:text-blue-800 hover:underline">How We Use Information</a>
                <span class="text-gray-400">|</span>
                <a href="#data-security" class="text-blue-600 hover:text-blue-800 hover:underline">Data Security</a>
                <span class="text-gray-400">|</span>
                <a href="#your-rights" class="text-blue-600 hover:text-blue-800 hover:underline">Your Rights</a>
                <span class="text-gray-400">|</span>
                <a href="#contact" class="text-blue-600 hover:text-blue-800 hover:underline">Contact Us</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-6 py-8 flex-grow">
        <div class="bg-white rounded-lg shadow-lg p-8 md:p-12">
            
            <!-- Header -->
            <div class="text-center mb-12">
                <img src="../iconbgr_b.png" alt="Doctor Wallet Logo" class="h-20 w-auto mx-auto mb-4 rounded-lg">
                <h1 class="text-4xl font-bold text-gray-800 mb-2">Privacy Policy</h1>
                <p class="text-gray-600">Last Updated: October 28, 2025</p>
                <button onclick="window.print()" class="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded print-hide">
                    <i class="fas fa-print mr-2"></i>Print Policy
                </button>
            </div>

            <!-- Introduction -->
            <section id="introduction" class="privacy-section mb-10">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-info-circle text-blue-600 mr-3"></i>
                    Introduction
                </h2>
                <div class="text-gray-700 leading-relaxed space-y-4">
                    <p>
                        Welcome to Doctor Wallet, a comprehensive doctor management system designed to streamline patient care, 
                        medicine inventory, and medical record management. We are committed to protecting your privacy and ensuring 
                        the security of your personal and medical information.
                    </p>
                    <p>
                        This Privacy Policy explains how Doctor Wallet collects, uses, stores, and protects your information when 
                        you use our platform. By using Doctor Wallet, you agree to the terms outlined in this policy.
                    </p>
                    <div class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded">
                        <p class="font-semibold text-blue-800">
                            <i class="fas fa-shield-alt mr-2"></i>
                            Your privacy is our priority. We comply with all applicable data protection laws and medical privacy regulations.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Information We Collect -->
            <section id="information-collection" class="privacy-section mb-10">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-database text-green-600 mr-3"></i>
                    Information We Collect
                </h2>
                <div class="text-gray-700 leading-relaxed space-y-6">
                    
                    <!-- Patient Information -->
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <h3 class="text-xl font-semibold text-gray-800 mb-3">
                            <i class="fas fa-user-injured text-emerald-600 mr-2"></i>
                            Patient Information
                        </h3>
                        <ul class="list-disc list-inside space-y-2 ml-4">
                            <li>Personal details (name, age, date of birth, gender)</li>
                            <li>Contact information (phone number, address)</li>
                            <li>Medical history and health records</li>
                            <li>Prescription and medication information</li>
                            <li>Laboratory and diagnostic reports</li>
                            <li>Visit dates and appointment schedules</li>
                            <li>Token numbers and queue management data</li>
                        </ul>
                    </div>

                    <!-- Doctor/Staff Information -->
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <h3 class="text-xl font-semibold text-gray-800 mb-3">
                            <i class="fas fa-user-md text-blue-600 mr-2"></i>
                            Doctor & Staff Information
                        </h3>
                        <ul class="list-disc list-inside space-y-2 ml-4">
                            <li>Professional credentials and qualifications</li>
                            <li>Contact information and clinic details</li>
                            <li>Login credentials and access permissions</li>
                            <li>Activity logs and system usage data</li>
                        </ul>
                    </div>

                    <!-- System Information -->
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <h3 class="text-xl font-semibold text-gray-800 mb-3">
                            <i class="fas fa-server text-purple-600 mr-2"></i>
                            Technical Information
                        </h3>
                        <ul class="list-disc list-inside space-y-2 ml-4">
                            <li>IP addresses and device information</li>
                            <li>Browser type and version</li>
                            <li>Login times and session duration</li>
                            <li>System usage patterns and interactions</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- How We Use Information -->
            <section id="information-use" class="privacy-section mb-10">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-tasks text-orange-600 mr-3"></i>
                    How We Use Your Information
                </h2>
                <div class="text-gray-700 leading-relaxed space-y-4">
                    <p>Doctor Wallet uses collected information for the following purposes:</p>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="border-l-4 border-blue-500 pl-4">
                            <h4 class="font-semibold text-lg mb-2">Patient Care</h4>
                            <ul class="space-y-1 text-sm">
                                <li>• Maintaining accurate medical records</li>
                                <li>• Managing prescriptions and medicines</li>
                                <li>• Scheduling appointments and visits</li>
                                <li>• Sending appointment reminders via SMS</li>
                            </ul>
                        </div>
                        
                        <div class="border-l-4 border-green-500 pl-4">
                            <h4 class="font-semibold text-lg mb-2">System Operations</h4>
                            <ul class="space-y-1 text-sm">
                                <li>• Processing e-receipts and billing</li>
                                <li>• Managing medicine inventory</li>
                                <li>• Generating reports and analytics</li>
                                <li>• Token management and queue system</li>
                            </ul>
                        </div>
                        
                        <div class="border-l-4 border-purple-500 pl-4">
                            <h4 class="font-semibold text-lg mb-2">Communication</h4>
                            <ul class="space-y-1 text-sm">
                                <li>• Sending SMS reminders for visits</li>
                                <li>• Notifying about prescriptions</li>
                                <li>• Emergency contact purposes</li>
                                <li>• System updates and announcements</li>
                            </ul>
                        </div>
                        
                        <div class="border-l-4 border-red-500 pl-4">
                            <h4 class="font-semibold text-lg mb-2">Security & Compliance</h4>
                            <ul class="space-y-1 text-sm">
                                <li>• User authentication and access control</li>
                                <li>• Preventing unauthorized access</li>
                                <li>• Maintaining audit trails</li>
                                <li>• Complying with legal requirements</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Data Security -->
            <section id="data-security" class="privacy-section mb-10">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-lock text-red-600 mr-3"></i>
                    Data Security
                </h2>
                <div class="text-gray-700 leading-relaxed space-y-4">
                    <p>
                        We implement industry-standard security measures to protect your information from unauthorized access, 
                        alteration, disclosure, or destruction.
                    </p>
                    
                    <div class="grid md:grid-cols-3 gap-4">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-6 rounded-lg text-center">
                            <i class="fas fa-key text-4xl text-blue-600 mb-3"></i>
                            <h4 class="font-semibold mb-2">Encryption</h4>
                            <p class="text-sm">Secure data transmission and storage encryption</p>
                        </div>
                        
                        <div class="bg-gradient-to-br from-green-50 to-green-100 p-6 rounded-lg text-center">
                            <i class="fas fa-user-shield text-4xl text-green-600 mb-3"></i>
                            <h4 class="font-semibold mb-2">Access Control</h4>
                            <p class="text-sm">Role-based permissions for doctors and staff</p>
                        </div>
                        
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-6 rounded-lg text-center">
                            <i class="fas fa-history text-4xl text-purple-600 mb-3"></i>
                            <h4 class="font-semibold mb-2">Audit Logs</h4>
                            <p class="text-sm">Comprehensive activity tracking and monitoring</p>
                        </div>
                    </div>

                    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded mt-6">
                        <p class="font-semibold text-yellow-800">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Important: No system is 100% secure. While we take extensive measures to protect your data, 
                            you should also take precautions such as keeping your login credentials confidential.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Data Sharing -->
            <section id="data-sharing" class="privacy-section mb-10">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-share-alt text-indigo-600 mr-3"></i>
                    Data Sharing and Disclosure
                </h2>
                <div class="text-gray-700 leading-relaxed space-y-4">
                    <p>Doctor Wallet <strong class="text-red-600">does not sell</strong> your personal or medical information to third parties.</p>
                    
                    <p>We may share information only in the following circumstances:</p>
                    
                    <div class="space-y-3">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-check-circle text-green-500 mt-1"></i>
                            <div>
                                <strong>With Healthcare Providers:</strong> Your information is accessible to your assigned doctor and authorized staff members for treatment purposes.
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-check-circle text-green-500 mt-1"></i>
                            <div>
                                <strong>Legal Requirements:</strong> When required by law, court order, or governmental authority.
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-check-circle text-green-500 mt-1"></i>
                            <div>
                                <strong>With Your Consent:</strong> When you explicitly authorize us to share information with specific parties.
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-check-circle text-green-500 mt-1"></i>
                            <div>
                                <strong>Emergency Situations:</strong> To protect your health, safety, or the safety of others.
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Your Rights -->
            <section id="your-rights" class="privacy-section mb-10">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-balance-scale text-cyan-600 mr-3"></i>
                    Your Rights and Choices
                </h2>
                <div class="text-gray-700 leading-relaxed">
                    <p class="mb-4">You have the following rights regarding your personal and medical information:</p>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="bg-white border-2 border-gray-200 p-5 rounded-lg hover:shadow-lg transition">
                            <div class="flex items-center mb-3">
                                <i class="fas fa-eye text-blue-600 text-2xl mr-3"></i>
                                <h4 class="font-semibold text-lg">Access</h4>
                            </div>
                            <p class="text-sm">Request access to your personal and medical records at any time.</p>
                        </div>
                        
                        <div class="bg-white border-2 border-gray-200 p-5 rounded-lg hover:shadow-lg transition">
                            <div class="flex items-center mb-3">
                                <i class="fas fa-edit text-green-600 text-2xl mr-3"></i>
                                <h4 class="font-semibold text-lg">Correction</h4>
                            </div>
                            <p class="text-sm">Request corrections to inaccurate or incomplete information.</p>
                        </div>
                        
                        <div class="bg-white border-2 border-gray-200 p-5 rounded-lg hover:shadow-lg transition">
                            <div class="flex items-center mb-3">
                                <i class="fas fa-download text-purple-600 text-2xl mr-3"></i>
                                <h4 class="font-semibold text-lg">Portability</h4>
                            </div>
                            <p class="text-sm">Request a copy of your data in a portable format.</p>
                        </div>
                        
                        <div class="bg-white border-2 border-gray-200 p-5 rounded-lg hover:shadow-lg transition">
                            <div class="flex items-center mb-3">
                                <i class="fas fa-ban text-red-600 text-2xl mr-3"></i>
                                <h4 class="font-semibold text-lg">Opt-Out</h4>
                            </div>
                            <p class="text-sm">Opt-out of non-essential communications like SMS reminders.</p>
                        </div>
                    </div>
                    
                    <div class="mt-6 bg-blue-50 p-4 rounded-lg">
                        <p class="text-sm">
                            <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                            <strong>Note:</strong> Some rights may be limited by legal or medical record-keeping requirements. 
                            Contact your doctor or our support team to exercise your rights.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Data Retention -->
            <section id="data-retention" class="privacy-section mb-10">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-clock text-yellow-600 mr-3"></i>
                    Data Retention
                </h2>
                <div class="text-gray-700 leading-relaxed space-y-4">
                    <p>
                        We retain your information for as long as necessary to provide our services and comply with legal obligations:
                    </p>
                    
                    <ul class="space-y-2 ml-6">
                        <li class="flex items-start">
                            <i class="fas fa-circle text-xs text-gray-400 mr-3 mt-2"></i>
                            <span><strong>Medical Records:</strong> Retained according to medical record-keeping regulations (typically 7-10 years or longer)</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-circle text-xs text-gray-400 mr-3 mt-2"></i>
                            <span><strong>Financial Records:</strong> Retained for accounting and tax purposes (typically 7 years)</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-circle text-xs text-gray-400 mr-3 mt-2"></i>
                            <span><strong>System Logs:</strong> Retained for security and audit purposes (typically 1-2 years)</span>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- SMS Service -->
            <section id="sms-service" class="privacy-section mb-10">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-sms text-cyan-600 mr-3"></i>
                    SMS Service
                </h2>
                <div class="text-gray-700 leading-relaxed space-y-4">
                    <p>
                        Doctor Wallet uses SMS messaging to send appointment reminders and important notifications:
                    </p>
                    
                    <div class="bg-gray-50 p-5 rounded-lg">
                        <ul class="space-y-2">
                            <li>• SMS reminders are sent before scheduled appointments based on your preferences</li>
                            <li>• You can opt-out of SMS reminders at any time by contacting your doctor or staff</li>
                            <li>• Standard SMS rates from your mobile carrier may apply</li>
                            <li>• We do not use your phone number for marketing purposes</li>
                            <li>• Your phone number is not shared with third parties</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Updates to Privacy Policy -->
            <section id="updates" class="privacy-section mb-10">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-sync-alt text-indigo-600 mr-3"></i>
                    Updates to This Privacy Policy
                </h2>
                <div class="text-gray-700 leading-relaxed space-y-4">
                    <p>
                        We may update this Privacy Policy from time to time to reflect changes in our practices or legal requirements. 
                        When we make significant changes, we will:
                    </p>
                    
                    <ul class="list-disc list-inside space-y-2 ml-4">
                        <li>Update the "Last Updated" date at the top of this policy</li>
                        <li>Notify users through the system dashboard</li>
                        <li>For major changes, obtain renewed consent where required</li>
                    </ul>
                    
                    <p>
                        We encourage you to review this Privacy Policy periodically to stay informed about how we protect your information.
                    </p>
                </div>
            </section>

            <!-- Contact Information -->
            <section id="contact" class="privacy-section mb-10">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-envelope text-red-600 mr-3"></i>
                    Contact Us
                </h2>
                <div class="text-gray-700 leading-relaxed">
                    <p class="mb-6">
                        If you have questions, concerns, or requests regarding this Privacy Policy or your personal information, 
                        please contact us:
                    </p>
                    
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-8 rounded-lg border-2 border-blue-200">
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="font-semibold text-lg mb-4 text-blue-800">Doctor Wallet Support</h4>
                                <div class="space-y-3">
                                    <div class="flex items-center">
                                        <i class="fas fa-map-marker-alt text-blue-600 w-6"></i>
                                        <span class="ml-2">Kandy, Sri Lanka</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-phone-alt text-blue-600 w-6"></i>
                                        <span class="ml-2">+94 75 337 4975</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-envelope text-blue-600 w-6"></i>
                                        <span class="ml-2 break-all">shakkyajayawardana2008@gmail.com</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="font-semibold text-lg mb-4 text-blue-800">Response Time</h4>
                                <p class="text-sm mb-3">
                                    We aim to respond to all privacy-related inquiries within 5-7 business days.
                                </p>
                                <div class="bg-white p-4 rounded border border-blue-200">
                                    <p class="text-sm font-semibold text-blue-800 mb-2">For urgent privacy concerns:</p>
                                    <p class="text-sm">Please mark your communication as "URGENT - Privacy Matter" and we will prioritize your request.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Consent -->
            <section id="consent" class="privacy-section">
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-300 p-6 rounded-lg">
                    <h3 class="text-xl font-bold text-green-800 mb-4 flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-3"></i>
                        Your Consent
                    </h3>
                    <p class="text-gray-700 leading-relaxed">
                        By using Doctor Wallet, you acknowledge that you have read, understood, and agree to this Privacy Policy. 
                        If you do not agree with any part of this policy, please discontinue use of the system and contact us 
                        to discuss your concerns.
                    </p>
                </div>
            </section>

        </div>

        <!-- Back to Top Button -->
        <div class="text-center mt-8 print-hide">
            <a href="#top" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg shadow-lg transition">
                <i class="fas fa-arrow-up mr-2"></i>
                Back to Top
            </a>
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

</body>
<script>
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
</html>