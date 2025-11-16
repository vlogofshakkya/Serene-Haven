<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$userType = $_SESSION['user_type'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Wallet - Dashboard</title>
<!-- Favicon (modern browsers) -->
<link rel="icon" type="image/png" sizes="32x32" href="icon.png">

<!-- High-res favicon -->
<link rel="icon" type="image/png" sizes="192x192" href="icon.png">

<!-- Apple touch icon (iOS home screen) -->
<link rel="apple-touch-icon" sizes="180x180" href="icon.png">

<!-- Safari pinned tab (monochrome SVG) -->
<link rel="mask-icon" href="icon.svg" color="#0F2E44">

<!-- Microsoft tile icon -->
<meta name="msapplication-TileImage" content="icon.png">
<meta name="msapplication-TileColor" content="#0F2E44">

<!-- Theme color for mobile browsers -->
<meta name="theme-color" content="#0F2E44">

<link rel="manifest" href="manifest.json">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
  html {
    scroll-behavior: smooth;
  }

  .scroll-smooth {
    scroll-behavior: smooth;
  }

  /* Download Modal Styles */
  .download-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(8px);
    animation: fadeIn 0.3s ease-out;
  }

  .download-modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .modal-content {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 30px;
    padding: 0;
    width: 90%;
    max-width: 600px;
    position: relative;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    animation: slideUp 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    overflow: hidden;
  }

  .modal-header {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
    padding: 30px;
    text-align: center;
    position: relative;
  }

  .modal-close {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    font-size: 24px;
    cursor: pointer;
    transition: background 0.3s ease, transform 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
    z-index: 10;
  }

  .modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
  }

  .modal-close i {
    pointer-events: none;
  }

  .modal-icon {
    font-size: 80px;
    margin-bottom: 20px;
    animation: bounce 1s infinite;
  }

  .modal-title {
    color: white;
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 10px;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
  }

  .modal-subtitle {
    color: rgba(255, 255, 255, 0.9);
    font-size: 16px;
  }

  .modal-body {
    padding: 40px 30px;
  }

  .download-buttons {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 30px;
  }

  .download-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 30px;
    background: white;
    border-radius: 15px;
    text-decoration: none;
    color: #667eea;
    font-weight: bold;
    font-size: 18px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    position: relative;
    overflow: hidden;
  }

  .download-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.5), transparent);
    transition: left 0.5s ease;
  }

  .download-btn:hover::before {
    left: 100%;
  }

  .download-btn:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
  }

  .download-btn:active {
    transform: translateY(-2px);
  }

  .btn-icon {
    font-size: 32px;
    margin-right: 15px;
    animation: pulse 2s infinite;
  }

  .btn-text {
    flex: 1;
    text-align: left;
  }

  .btn-label {
    font-size: 14px;
    color: #888;
    font-weight: normal;
    display: block;
    margin-bottom: 5px;
  }

  .btn-arrow {
    font-size: 24px;
    transition: transform 0.3s ease;
  }

  .download-btn:hover .btn-arrow {
    transform: translateX(10px);
  }

  .warning-note {
    background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
    padding: 20px;
    border-radius: 15px;
    color: white;
    font-size: 14px;
    line-height: 1.6;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    animation: pulse-warning 2s infinite;
  }

  .warning-note i {
    margin-right: 10px;
    font-size: 18px;
  }

  .warning-title {
    font-weight: bold;
    font-size: 16px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
    }
    to {
      opacity: 1;
    }
  }

  @keyframes slideUp {
    from {
      transform: translateY(100px);
      opacity: 0;
    }
    to {
      transform: translateY(0);
      opacity: 1;
    }
  }

  @keyframes bounce {
    0%, 100% {
      transform: translateY(0);
    }
    50% {
      transform: translateY(-20px);
    }
  }

  @keyframes pulse {
    0%, 100% {
      transform: scale(1);
    }
    50% {
      transform: scale(1.1);
    }
  }

  @keyframes pulse-warning {
    0%, 100% {
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    50% {
      box-shadow: 0 4px 20px rgba(255, 107, 107, 0.4);
    }
  }

  /* Responsive Design */
  @media (max-width: 768px) {
    .modal-content {
      width: 95%;
      margin: 20px;
    }

    .modal-header {
      padding: 20px;
    }

    .modal-title {
      font-size: 24px;
    }

    .modal-subtitle {
      font-size: 14px;
    }

    .modal-icon {
      font-size: 60px;
    }

    .modal-body {
      padding: 30px 20px;
    }

    .download-btn {
      padding: 15px 20px;
      font-size: 16px;
    }

    .btn-icon {
      font-size: 28px;
    }

    .btn-arrow {
      font-size: 20px;
    }

    .warning-note {
      font-size: 13px;
      padding: 15px;
    }
  }

  @media (max-width: 480px) {
    .modal-title {
      font-size: 20px;
    }

    .download-btn {
      flex-direction: column;
      text-align: center;
      padding: 20px 15px;
    }

    .btn-icon {
      margin-right: 0;
      margin-bottom: 10px;
    }

    .btn-text {
      text-align: center;
    }

    .btn-arrow {
      display: none;
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

/*glow animations for icons */
/* ✨ Base card styles */
.card {
  background: white;
  border-radius: 0.75rem;
  position: relative;
  overflow: visible;
  transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  isolation: isolate;
}

.card::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 0.75rem;
  opacity: 0;
  transition: opacity 0.8s ease-in-out;
  pointer-events: none;
  z-index: -1;
}

.card::after {
  content: '';
  position: absolute;
  inset: -40px;
  border-radius: 0.75rem;
  opacity: 0;
  transition: opacity 1s ease-in-out;
  pointer-events: none;
  z-index: -2;
}

.card:hover {
  transform: translateY(-6px) scale(1.02);
  box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

.card:hover::before {
  opacity: 1;
  transition: opacity 0.6s ease-in-out 0.1s;
}

.card:hover::after {
  opacity: 1;
  transition: opacity 0.8s ease-in-out 0.3s;
}

/* ✨ Glow icon base */
.glow {
  position: relative;
  display: inline-block;
  transition: all 0.7s cubic-bezier(0.34, 1.56, 0.64, 1);
}

/* 🌿 Emerald */
.card-emerald .glow {
  filter: drop-shadow(0 0 15px rgba(16,185,129,0.9)) 
          drop-shadow(0 0 25px rgba(16,185,129,0.6))
          drop-shadow(0 0 35px rgba(16,185,129,0.4));
}

.card-emerald::before {
  background: 
    linear-gradient(135deg, rgba(16,185,129,0.04) 0%, rgba(16,185,129,0.02) 50%, rgba(16,185,129,0.04) 100%),
    linear-gradient(to bottom, rgba(16,185,129,0.06), rgba(16,185,129,0.02));
}

.card-emerald::after {
  background: 
    radial-gradient(ellipse at center, rgba(16,185,129,0.4) 0%, rgba(16,185,129,0.25) 30%, rgba(16,185,129,0.1) 60%, transparent 100%);
  filter: blur(25px);
}

.card-emerald:hover {
  box-shadow: 0 8px 30px rgba(16,185,129,0.3), 0 0 60px rgba(16,185,129,0.2);
}

.card-emerald:hover .glow {
  filter: drop-shadow(0 0 8px rgba(16,185,129,0.5));
  transform: scale(1.15) translateY(-2px);
}

/* 🔵 Blue */
.card-blue .glow {
  filter: drop-shadow(0 0 15px rgba(59,130,246,0.9)) 
          drop-shadow(0 0 25px rgba(59,130,246,0.6))
          drop-shadow(0 0 35px rgba(59,130,246,0.4));
}

.card-blue::before {
  background: 
    linear-gradient(135deg, rgba(59,130,246,0.04) 0%, rgba(59,130,246,0.02) 50%, rgba(59,130,246,0.04) 100%),
    linear-gradient(to bottom, rgba(59,130,246,0.06), rgba(59,130,246,0.02));
}

.card-blue::after {
  background: 
    radial-gradient(ellipse at center, rgba(59,130,246,0.4) 0%, rgba(59,130,246,0.25) 30%, rgba(59,130,246,0.1) 60%, transparent 100%);
  filter: blur(25px);
}

.card-blue:hover {
  box-shadow: 0 8px 30px rgba(59,130,246,0.3), 0 0 60px rgba(59,130,246,0.2);
}

.card-blue:hover .glow {
  filter: drop-shadow(0 0 8px rgba(59,130,246,0.5));
  transform: scale(1.15) translateY(-2px);
}

/* 💜 Purple */
.card-purple .glow {
  filter: drop-shadow(0 0 15px rgba(168,85,247,0.9)) 
          drop-shadow(0 0 25px rgba(168,85,247,0.6))
          drop-shadow(0 0 35px rgba(168,85,247,0.4));
}

.card-purple::before {
  background: 
    linear-gradient(135deg, rgba(168,85,247,0.04) 0%, rgba(168,85,247,0.02) 50%, rgba(168,85,247,0.04) 100%),
    linear-gradient(to bottom, rgba(168,85,247,0.06), rgba(168,85,247,0.02));
}

.card-purple::after {
  background: 
    radial-gradient(ellipse at center, rgba(168,85,247,0.4) 0%, rgba(168,85,247,0.25) 30%, rgba(168,85,247,0.1) 60%, transparent 100%);
  filter: blur(25px);
}

.card-purple:hover {
  box-shadow: 0 8px 30px rgba(168,85,247,0.3), 0 0 60px rgba(168,85,247,0.2);
}

.card-purple:hover .glow {
  filter: drop-shadow(0 0 8px rgba(168,85,247,0.5));
  transform: scale(1.15) translateY(-2px);
}

/* 🟠 Orange */
.card-orange .glow {
  filter: drop-shadow(0 0 15px rgba(249,115,22,0.9)) 
          drop-shadow(0 0 25px rgba(249,115,22,0.6))
          drop-shadow(0 0 35px rgba(249,115,22,0.4));
}

.card-orange::before {
  background: 
    linear-gradient(135deg, rgba(249,115,22,0.04) 0%, rgba(249,115,22,0.02) 50%, rgba(249,115,22,0.04) 100%),
    linear-gradient(to bottom, rgba(249,115,22,0.06), rgba(249,115,22,0.02));
}

.card-orange::after {
  background: 
    radial-gradient(ellipse at center, rgba(249,115,22,0.4) 0%, rgba(249,115,22,0.25) 30%, rgba(249,115,22,0.1) 60%, transparent 100%);
  filter: blur(25px);
}

.card-orange:hover {
  box-shadow: 0 8px 30px rgba(249,115,22,0.3), 0 0 60px rgba(249,115,22,0.2);
}

.card-orange:hover .glow {
  filter: drop-shadow(0 0 8px rgba(249,115,22,0.5));
  transform: scale(1.15) translateY(-2px);
}

/* 🟡 Yellow */
.card-yellow .glow {
  filter: drop-shadow(0 0 15px rgba(234,179,8,0.9)) 
          drop-shadow(0 0 25px rgba(234,179,8,0.6))
          drop-shadow(0 0 35px rgba(234,179,8,0.4));
}

.card-yellow::before {
  background: 
    linear-gradient(135deg, rgba(234,179,8,0.04) 0%, rgba(234,179,8,0.02) 50%, rgba(234,179,8,0.04) 100%),
    linear-gradient(to bottom, rgba(234,179,8,0.06), rgba(234,179,8,0.02));
}

.card-yellow::after {
  background: 
    radial-gradient(ellipse at center, rgba(234,179,8,0.4) 0%, rgba(234,179,8,0.25) 30%, rgba(234,179,8,0.1) 60%, transparent 100%);
  filter: blur(25px);
}

.card-yellow:hover {
  box-shadow: 0 8px 30px rgba(234,179,8,0.3), 0 0 60px rgba(234,179,8,0.2);
}

.card-yellow:hover .glow {
  filter: drop-shadow(0 0 8px rgba(234,179,8,0.5));
  transform: scale(1.15) translateY(-2px);
}

/* ⚫ Gray */
.card-gray .glow {
  filter: drop-shadow(0 0 15px rgba(107,114,128,0.9)) 
          drop-shadow(0 0 25px rgba(107,114,128,0.6))
          drop-shadow(0 0 35px rgba(107,114,128,0.4));
}

.card-gray::before {
  background: 
    linear-gradient(135deg, rgba(107,114,128,0.04) 0%, rgba(107,114,128,0.02) 50%, rgba(107,114,128,0.04) 100%),
    linear-gradient(to bottom, rgba(107,114,128,0.06), rgba(107,114,128,0.02));
}

.card-gray::after {
  background: 
    radial-gradient(ellipse at center, rgba(107,114,128,0.4) 0%, rgba(107,114,128,0.25) 30%, rgba(107,114,128,0.1) 60%, transparent 100%);
  filter: blur(25px);
}

.card-gray:hover {
  box-shadow: 0 8px 30px rgba(107,114,128,0.3), 0 0 60px rgba(107,114,128,0.2);
}

.card-gray:hover .glow {
  filter: drop-shadow(0 0 8px rgba(107,114,128,0.5));
  transform: scale(1.15) translateY(-2px);
}

/* 💗 Pink */
.card-pink .glow {
  filter: drop-shadow(0 0 15px rgba(236,72,153,0.9)) 
          drop-shadow(0 0 25px rgba(236,72,153,0.6))
          drop-shadow(0 0 35px rgba(236,72,153,0.4));
}

.card-pink::before {
  background: 
    linear-gradient(135deg, rgba(236,72,153,0.04) 0%, rgba(236,72,153,0.02) 50%, rgba(236,72,153,0.04) 100%),
    linear-gradient(to bottom, rgba(236,72,153,0.06), rgba(236,72,153,0.02));
}

.card-pink::after {
  background: 
    radial-gradient(ellipse at center, rgba(236,72,153,0.4) 0%, rgba(236,72,153,0.25) 30%, rgba(236,72,153,0.1) 60%, transparent 100%);
  filter: blur(25px);
}

.card-pink:hover {
  box-shadow: 0 8px 30px rgba(236,72,153,0.3), 0 0 60px rgba(236,72,153,0.2);
}

.card-pink:hover .glow {
  filter: drop-shadow(0 0 8px rgba(236,72,153,0.5));
  transform: scale(1.15) translateY(-2px);
}

/* 🩵 Cyan */
.card-cyan .glow {
  filter: drop-shadow(0 0 15px rgba(34,211,238,0.9)) 
          drop-shadow(0 0 25px rgba(34,211,238,0.6))
          drop-shadow(0 0 35px rgba(34,211,238,0.4));
}

.card-cyan::before {
  background: 
    linear-gradient(135deg, rgba(34,211,238,0.04) 0%, rgba(34,211,238,0.02) 50%, rgba(34,211,238,0.04) 100%),
    linear-gradient(to bottom, rgba(34,211,238,0.06), rgba(34,211,238,0.02));
}

.card-cyan::after {
  background: 
    radial-gradient(ellipse at center, rgba(34,211,238,0.4) 0%, rgba(34,211,238,0.25) 30%, rgba(34,211,238,0.1) 60%, transparent 100%);
  filter: blur(25px);
}

.card-cyan:hover {
  box-shadow: 0 8px 30px rgba(34,211,238,0.3), 0 0 60px rgba(34,211,238,0.2);
}

.card-cyan:hover .glow {
  filter: drop-shadow(0 0 8px rgba(34,211,238,0.5));
  transform: scale(1.15) translateY(-2px);
}
</style>

</head>
<body class="bg-gray-100 min-h-screen flex flex-col scroll-smooth" style="visibility: hidden;">
<!-- Navigation -->
<nav class="bg-gradient-to-r from-blue-800 via-blue-600 to-blue-800 to text-white p-4 [&_*::selection]:bg-white [&_*::selection]:text-indigo-900" style="border-radius: 0 0 20px 20px;" id="home">
  <div class="container mx-auto flex flex-col md:flex-row md:items-center md:justify-between relative space-y-3 md:space-y-0">

    <!-- Left side -->
    <h1 class="text-xl md:text-2xl font-bold text-center md:text-left">Doctor Wallet</h1>

    <!-- Center Logo (hidden on small screens) -->
    <div class="absolute left-1/2 transform -translate-x-1/2 hidden md:block">
      <img src="iconbgr_b.png" 
           alt="Logo" 
           class="h-14 w-auto object-contain rounded-lg hover:scale-105 transition-transform duration-300" />
    </div>

    <!-- Right side -->
    <div class="flex flex-col md:flex-row md:items-center md:space-x-4 text-center md:text-left space-y-2 md:space-y-0">
      <span class="text-sm md:text-base">
        Welcome, <?php echo htmlspecialchars($user['name'] ?? $user['doctor_name']); ?>
      </span>
      <span class="bg-blue-800 px-2 py-1 rounded text-xs md:text-sm inline-block">
        <?php echo ucfirst($userType); ?>
      </span>
      <?php if ($userType === 'doctor'): ?>
        <a href="pages/doctor_profile.php" 
           class="bg-green-500 hover:bg-green-600 px-3 py-2 rounded flex justify-center items-center text-sm md:text-base">
          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
          </svg>
          Profile
        </a>
      <?php endif; ?>                
      <a href="logout.php" 
         class="bg-red-500 hover:bg-red-600 px-3 py-2 rounded text-sm md:text-base">
        Logout
      </a>
    </div>
  </div>
</nav>


    <div class="container mx-auto p-6 flex-grow">
        <?php if ($userType === 'doctor'): ?>
            <!-- Doctor Dashboard -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <!-- Quick Stats -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Patients</h3>
                    <?php
                    $doctorId = $user['id'];
                    $stmt = $pdo->prepare("SELECT (SELECT COUNT(*) FROM adults WHERE doctor_id = ?) + (SELECT COUNT(*) FROM kids WHERE doctor_id = ?) as total");
                    $stmt->execute([$doctorId, $doctorId]);
                    $total = $stmt->fetch()['total'];
                    ?>
                    <p class="text-3xl font-bold text-blue-600"><?php echo $total; ?></p>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Medicines in Store</h3>
                    <?php
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM medicines WHERE doctor_id = ? AND current_stock > 0");
                    $stmt->execute([$doctorId]);
                    $count = $stmt->fetch()['count'];
                    ?>
                    <p class="text-3xl font-bold text-green-600"><?php echo $count; ?></p>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Today's Receipts</h3>
                    <?php
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM e_receipts WHERE doctor_id = ? AND DATE(created_at) = CURDATE()");
                    $stmt->execute([$doctorId]);
                    $count = $stmt->fetch()['count'];
                    ?>
                    <p class="text-3xl font-bold text-purple-600"><?php echo $count; ?></p>
                </div>
            </div>

            <!-- Doctor Menu -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="all">
    <a href="pages/patients.php" class="card card-emerald bg-white rounded-lg p-6 block">
        <div class="text-center">
            <i class="fas fa-user-injured text-4xl mb-4 bg-gradient-to-r from-emerald-400 to-emerald-600 bg-clip-text text-transparent glow"></i>
            <h3 class="text-xl font-semibold text-gray-700">Manage Patients</h3>
            <p class="text-gray-500 mt-2">Add and manage patient details</p>
        </div>
    </a>
    
    <a href="pages/medicines.php" class="card card-blue bg-white rounded-lg p-6 block">
        <div class="text-center">
            <i class="fas fa-pills text-4xl mb-4 bg-gradient-to-r from-blue-400 to-blue-600 bg-clip-text text-transparent glow"></i>
            <h3 class="text-xl font-semibold text-gray-700">Medicine Store</h3>
            <p class="text-gray-500 mt-2">Manage medicine inventory</p>
        </div>
    </a>
    
    <a href="pages/drug_dispenser.php" class="card card-purple bg-white rounded-lg p-6 block">
        <div class="text-center">
            <i class="fas fa-stethoscope text-4xl mb-4 bg-gradient-to-r from-purple-400 to-purple-600 bg-clip-text text-transparent glow"></i>
            <h3 class="text-xl font-semibold text-gray-700">Drug Dispenser</h3>
            <p class="text-gray-500 mt-2">Issue medicines to patients</p>
        </div>
    </a>
    
    <a href="pages/file_upload.php" class="card card-orange bg-white rounded-lg p-6 block">
        <div class="text-center">
            <i class="fas fa-file-upload text-4xl mb-4 bg-gradient-to-r from-orange-400 to-orange-600 bg-clip-text text-transparent glow"></i>
            <h3 class="text-xl font-semibold text-gray-700">File Upload</h3>
            <p class="text-gray-500 mt-2">Upload patient files</p>
        </div>
    </a>
    
    <a href="pages/reports.php" class="card card-yellow bg-white rounded-lg p-6 block">
        <div class="text-center">
            <i class="fas fa-chart-bar text-4xl mb-4 bg-gradient-to-r from-yellow-400 to-yellow-600 bg-clip-text text-transparent glow"></i>
            <h3 class="text-xl font-semibold text-gray-700">Reports</h3>
            <p class="text-gray-500 mt-2">View patient reports</p>
        </div>
    </a>
    
    <a href="pages/receipts.php" class="card card-gray bg-white rounded-lg p-6 block">
        <div class="text-center">
            <i class="fas fa-receipt text-4xl mb-4 bg-gradient-to-r from-gray-400 to-gray-600 bg-clip-text text-transparent glow"></i>
            <h3 class="text-xl font-semibold text-gray-700">All Receipts</h3>
            <p class="text-gray-500 mt-2">View issued receipts</p>
        </div>
    </a>

    <a href="pages/print_section.php" class="card card-pink bg-white rounded-lg p-6 block">
        <div class="text-center">
            <i class="fas fa-print text-4xl mb-4 bg-gradient-to-r from-pink-400 to-pink-600 bg-clip-text text-transparent glow"></i>
            <h3 class="text-xl font-semibold text-gray-700">Print Section</h3>
            <p class="text-gray-500 mt-2">View Print Section</p>
        </div>
    </a>

    <a href="pages/sms.php" class="card card-cyan bg-white rounded-lg p-6 block" id="sms">
        <div class="text-center">
            <i class="fas fa-sms text-4xl mb-4 bg-gradient-to-r from-cyan-400 to-cyan-600 bg-clip-text text-transparent glow"></i>
            <h3 class="text-xl font-semibold text-gray-700">SMS Service</h3>
            <p class="text-gray-500 mt-2">Send SMS to patients</p>
        </div>
    </a>
    <a href="pages/lab_reports.php" class="card card-blue bg-white rounded-lg p-6 block">
        <div class="text-center">
            <i class="fas fa-flask text-4xl mb-4 bg-gradient-to-r from-blue-400 to-blue-600 bg-clip-text text-transparent glow"></i>
            <h3 class="text-xl font-semibold text-gray-700">Lab Reports</h3>
            <p class="text-gray-500 mt-2">Manage patient lab reports</p>
        </div>
    </a>
</div>

<?php else: ?>
    <!-- Staff Dashboard -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        <!-- Token Management -->
        <a href="pages/token.php" class="bg-white rounded-lg shadow hover:shadow-lg transition p-6 block">
            <div class="text-center">
                <i class="fas fa-ticket-alt text-4xl mb-4 bg-gradient-to-r from-red-400 to-red-600 bg-clip-text text-transparent"></i>
                <h3 class="text-xl font-semibold text-gray-700">Token Management</h3>
                <p class="text-gray-500 mt-2">Create and manage patient tokens</p>
            </div>
        </a>
        
        <!-- SMS Reminders - NEW -->
        <a href="pages/staff_sms_reminders.php" class="bg-white rounded-lg shadow hover:shadow-lg transition p-6 block relative">
            <div class="text-center">
                <i class="fas fa-sms text-4xl mb-4 bg-gradient-to-r from-green-400 to-green-600 bg-clip-text text-transparent"></i>
                <h3 class="text-xl font-semibold text-gray-700">SMS Reminders</h3>
                <p class="text-gray-500 mt-2">Send visit reminders to patients</p>
                
                <?php
                // Get pending SMS count
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM next_visit_appointments 
                    WHERE sms_sent = 0 
                    AND status = 'scheduled'
                    AND DATE_SUB(next_visit_date, INTERVAL reminder_days_before DAY) <= CURDATE()
                ");
                $stmt->execute();
                $pendingSMS = $stmt->fetch()['count'];
                
                if ($pendingSMS > 0):
                ?>
                <span class="absolute top-4 right-4 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full animate-pulse">
                    <?php echo $pendingSMS; ?>
                </span>
                <?php endif; ?>
            </div>
        </a>
        
        <!-- Quick Stats -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Today's Tokens</h3>
            <?php
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tokens WHERE staff_id = ? AND token_date = CURDATE()");
            $stmt->execute([$_SESSION['user_id']]);
            $todayTokens = $stmt->fetch()['count'];
            ?>
            <p class="text-3xl font-bold text-blue-600"><?php echo $todayTokens; ?></p>
            <p class="text-sm text-gray-500 mt-1">Tokens created today</p>
        </div>
    </div>
    
    <!-- SMS Statistics Card -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6" id="sms">
        <!-- Pending SMS Today -->
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-l-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">SMS To Send Today</p>
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as count 
                        FROM next_visit_appointments 
                        WHERE sms_sent = 0 
                        AND status = 'scheduled'
                        AND DATE_SUB(next_visit_date, INTERVAL reminder_days_before DAY) = CURDATE()
                    ");
                    $stmt->execute();
                    $todaySMS = $stmt->fetch()['count'];
                    ?>
                    <p class="text-3xl font-bold text-green-600"><?php echo $todaySMS; ?></p>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <i class="fas fa-calendar-check text-2xl text-green-600"></i>
                </div>
            </div>
        </div>
        
        <!-- Overdue SMS -->
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-l-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Overdue SMS</p>
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as count 
                        FROM next_visit_appointments 
                        WHERE sms_sent = 0 
                        AND status = 'scheduled'
                        AND DATE_SUB(next_visit_date, INTERVAL reminder_days_before DAY) < CURDATE()
                    ");
                    $stmt->execute();
                    $overdueSMS = $stmt->fetch()['count'];
                    ?>
                    <p class="text-3xl font-bold text-red-600"><?php echo $overdueSMS; ?></p>
                </div>
                <div class="bg-red-100 p-3 rounded-full">
                    <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                </div>
            </div>
        </div>
        
        <!-- SMS Sent Today -->
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-l-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">SMS Sent Today</p>
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as count 
                        FROM next_visit_appointments 
                        WHERE sms_sent = 1 
                        AND DATE(sms_sent_at) = CURDATE()
                    ");
                    $stmt->execute();
                    $sentTodaySMS = $stmt->fetch()['count'];
                    ?>
                    <p class="text-3xl font-bold text-purple-600"><?php echo $sentTodaySMS; ?></p>
                </div>
                <div class="bg-purple-100 p-3 rounded-full">
                    <i class="fas fa-check-circle text-2xl text-purple-600"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6" id="all">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Real-time Receipts</h2>
        <div id="receipts-container">
            <!-- Receipts will be loaded here -->
        </div>
    </div>

    <script>
    // Auto-refresh receipts every 5 seconds
    function loadReceipts() {
        $.get('ajax/get_receipts.php', function(data) {
            $('#receipts-container').html(data);
        });
    }

    // Load receipts on page load
    loadReceipts();
    
    // Auto-refresh every 5 seconds
    setInterval(loadReceipts, 5000);
    </script>
<?php endif; ?>
    </div>

    <!-- Download Modal -->
    <div id="downloadModal" class="download-modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close" onclick="closeDownloadModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-icon">
                    <i class="fas fa-download" style="color: white;"></i>
                </div>
                <h2 class="modal-title">Download Doctor Wallet</h2>
                <p class="modal-subtitle">Choose your platform and get started</p>
            </div>
            
            <div class="modal-body">
                <div class="download-buttons">
                    <!-- PC Version Button -->
                    <a href="https://github.com/vlogofshakkya/doctorwallet/releases/download/DoctorWallet_V1.1/DoctorWallet.exe" class="download-btn" download>
                        <i class="fas fa-desktop btn-icon" style="color: #667eea;"></i>
                        <div class="btn-text">
                            <span class="btn-label">Desktop Application</span>
                            <div>PC VERSION</div>
                        </div>
                        <i class="fas fa-arrow-right btn-arrow"></i>
                    </a>
                    
                    <!-- Mobile Version Button -->
                    <a href="https://github.com/vlogofshakkya/doctorwallet/releases/download/DoctorWallet_android_v1.3/Doctor.Wallet.v1.3.apk" class="download-btn" download>
                        <i class="fas fa-mobile-alt btn-icon" style="color: #764ba2;"></i>
                        <div class="btn-text">
                            <span class="btn-label">Android Application</span>
                            <div>MOBILE VERSION</div>
                        </div>
                        <i class="fas fa-arrow-right btn-arrow"></i>
                    </a>
                </div>
                
                <!-- Warning Note -->
                <div class="warning-note">
                    <div class="warning-title">
                        <i class="fas fa-exclamation-circle"></i>
                        Important Notice
                    </div>
                    <p>
                        Some browsers may display a warning when downloading. Please continue with the download - this is normal for direct downloads. 
                        If you encounter any problems installing the mobile app, please contact IT support. Thank you!
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer - Hidden on mobile (below 768px) -->
    <footer class="hidden md:block bg-gradient-to-r from-blue-800 via-blue-700 to-blue-800 text-white mt-auto mx-6 mb-6 shadow-2xl [&_*::selection]:bg-white [&_*::selection]:text-indigo-900" style="border-radius: 85px;" id="footer">
        <div class="container mx-auto px-6 py-10">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- About Section -->
                <div>
                    <h3 class="text-lg font-bold mb-4 pointer-events-none">About Doctor Wallet</h3>
                    <p class="text-indigo-200 text-sm leading-relaxed pointer-events-none">
                        Doctor Wallet is a cloud-based medical management system that digitalizes private practice operations in Sri Lanka. It helps doctors manage patients, prescriptions, reports, and clinic data securely and efficiently. With real-time updates, automated SMS reminders, and multi-clinic support, Doctor Wallet streamlines healthcare workflows and enhances patient care.
                    </p>
                </div>

                <!-- Useful Links 1 -->
                <div>
                    <h3 class="text-lg font-bold mb-4 pointer-events-none">Useful Links</h3>
                    <ul class="space-y-2">
                        <li><a href="#home" class="text-indigo-200 hover:text-white transition">Home</a></li>
                        <li><a href="#sms" class="text-indigo-200 hover:text-white transition">Services</a></li>
                        <li><a href="#all" class="text-indigo-200 hover:text-white transition">Features</a></li>
                        <li><a href="#footer" class="text-indigo-200 hover:text-white transition">Support</a></li>
                        <li><a href="pages/privacy_policy.php" class="text-indigo-200 hover:text-white transition">Privacy Policy</a></li>
                    </ul>
                </div>

                <!-- Useful Links 2 -->
                <div>
                    <h3 class="text-lg font-bold mb-4 cursor-not-allowed pointer-events-none">Quick Access</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-indigo-200 hover:text-white transition pointer-events-none">COMING SOON</a></li>
                        <li><a href="#" class="text-indigo-200 hover:text-white transition pointer-events-none">COMING SOON</a></li>
                        <li><a href="#" class="text-indigo-200 hover:text-white transition pointer-events-none">COMING SOON</a></li>
                        <li><a href="#" class="text-indigo-200 hover:text-white transition pointer-events-none">COMING SOON</a></li>
                    </ul>
                </div>

                <!-- Contact Info & App Download -->
                <div>
                    <h3 class="text-lg font-bold mb-4 pointer-events-none">Contact Info</h3>
                    <ul class="space-y-3 text-indigo-200 text-sm pointer-events-none">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt mt-1 mr-2"></i>
                            <span>Kandy, Sri Lanka</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone-alt mr-2"></i>
                            <span>+94 75 337 4975</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope mr-2"></i>
                            <span class="break-all">shakkyajayawardana2008@gmail.com</span>
                        </li>
                    </ul>
                    
                    <!-- App Download Button - Updated to open modal -->
                    <button onclick="openDownloadModal()" class="mt-4 inline-flex items-center bg-white text-indigo-800 px-4 py-2 rounded-lg hover:bg-indigo-100 transition font-semibold cursor-pointer border-none">
                        <i class="fas fa-download mr-2"></i>
                        Download Our App
                    </button>
                </div>
            </div>

            <!-- Copyright -->
            <div class="border-t border-indigo-700 mt-8 pt-6 text-center pointer-events-none">
                <p class="text-indigo-300 text-sm">
                    Copyright © 2025 Doctor Wallet. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <!-- Mobile Footer - Only visible on screens below 768px -->
    <footer class="md:hidden bg-gradient-to-r from-blue-800 via-blue-600 to-blue-800 text-white mt-auto shadow-lg"  style="border-radius: 85px 85px 0 0;">
        <div class="container mx-auto px-4 py-6">
            <!-- Logo/Brand -->
            <div class="text-center mb-4">
                <h3 class="text-xl font-bold mb-2">Doctor Wallet</h3>
                <p class="text-indigo-200 text-sm">
                    Your trusted healthcare management system
                </p>
            </div>
            
            <!-- Download Button -->
            <div class="flex justify-center mb-4">
                <button onclick="openDownloadModal()" class="inline-flex items-center bg-white text-indigo-800 px-6 py-3 rounded-full hover:bg-indigo-100 transition font-semibold shadow-lg transform hover:scale-105 active:scale-95">
                    <i class="fas fa-download mr-2"></i>
                    Download Our App
                </button>
            </div>
            
            <!-- Quick Links -->
            <div class="flex flex-wrap justify-center gap-4 mb-4 text-sm">
                <a href="#home" class="text-indigo-200 hover:text-white transition">Home</a>
                <span class="text-indigo-400">•</span>
                <a href="#sms" class="text-indigo-200 hover:text-white transition">Services</a>
                <span class="text-indigo-400">•</span>
                <a href="pages/privacy_policy.php" class="text-indigo-200 hover:text-white transition">Privacy</a>
            </div>
            
            <!-- Contact Info -->
            <div class="text-center mb-4 text-indigo-200 text-sm space-y-1">
                <div class="flex items-center justify-center">
                    <i class="fas fa-map-marker-alt mr-2"></i>
                    <span>Kandy, Sri Lanka</span>
                </div>
                <div class="flex items-center justify-center">
                    <i class="fas fa-phone-alt mr-2"></i>
                    <span>+94 75 337 4975</span>
                </div>
            </div>
            
            <!-- Copyright -->
            <div class="border-t border-indigo-700 pt-4 text-center">
                <p class="text-indigo-300 text-xs">
                    Copyright © 2025 Doctor Wallet. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <!-- Modal JavaScript -->
    <script>
        function openDownloadModal() {
            document.getElementById('downloadModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeDownloadModal() {
            document.getElementById('downloadModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('downloadModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDownloadModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDownloadModal();
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