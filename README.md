# 🌿 Serene Haven Main Website

This repository contains **4 connected web projects** that together form the complete **Serene Haven online ecosystem**.

---

## 📝 Project Overview

### 1️⃣ `serenehaven`
The **main website** for Serene Haven.  
This site contains links to all other services, including guest houses, hotels, products, and the Doctor Wallet system.

### 2️⃣ `theserenehaven`
The dedicated **Guest House Website**.  
This site is linked inside the “Guest Houses” section of the main website.

### 3️⃣ `thedoctorwallet`
The **overview / landing website** of the Doctor Wallet system.  
It is linked under “Other Services” on the Serene Haven main website.

### 4️⃣ `doctorwallet`
The **main Doctor Wallet system**.  
It contains **3 portals** which can be accessed from the `thedoctorwallet` website:

- **Doctor Portal** – Main dashboard for doctors  
- **Patient Portal** – Patient-facing portal  
- **Laboratory Portal** – Laboratory dashboard  

---

## 📁 Project Structure (Tree View)

```text
Serene Haven Main Website
│
├── Guest Houses
│   │
│   ├── Guest Houses Page
│   │    │
│   │    └── theserenehaven (Guest House Website)
│   │
│   └── (Future guest houses can be added here)
│
├── Hotels
│   │
│   └── Hotels Page
│
├── Products
│   │
│   └── Products Page
│
└── Other Services
    │
    └── Doctor Wallet (Redirects to the Doctor Wallet Overview Website)
         │
         └── thedoctorwallet Website (Overview / Landing Page)
              │
              ├── Doctor Portal (Main system backend & dashboard)
              │    │
              │    └── Doctor Profile (Private Doctor Dashboard Pages)
              │
              ├── Patient Portal
              │
              └── Laboratory Portal
```
